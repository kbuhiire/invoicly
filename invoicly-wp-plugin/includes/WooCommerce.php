<?php

namespace Invoicly\WP;

defined('ABSPATH') || exit;

/**
 * WooCommerce → Invoicly integration.
 *
 * Automatically creates an Invoicly invoice whenever a WooCommerce order
 * reaches a configured trigger status (e.g. "completed"). Each unique
 * WooCommerce customer (matched by billing e-mail) is mapped to an Invoicly
 * client; new clients are created on-the-fly when none exists.
 *
 * A bulk-sync tool lets site admins back-fill invoices for existing orders.
 */
class WooCommerce
{
    // wp_options keys for WooCommerce-specific settings
    const OPTION_ENABLED          = 'invoicly_wc_enabled';
    const OPTION_TRIGGER_STATUSES = 'invoicly_wc_trigger_statuses';

    // Order-meta keys
    const META_INVOICE_ID     = '_invoicly_invoice_id';
    const META_INVOICE_NUMBER = '_invoicly_invoice_number';
    const META_SYNCED_AT      = '_invoicly_synced_at';

    // Transient TTL for cached Invoicly client IDs (1 hour)
    private const CLIENT_CACHE_TTL = HOUR_IN_SECONDS;

    // Maximum orders processed per bulk-sync batch
    private const BULK_BATCH_SIZE = 50;

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'registerWCSettings']);
        add_action('woocommerce_order_status_changed', [self::class, 'onOrderStatusChanged'], 10, 3);
        add_action('admin_post_invoicly_bulk_sync', [self::class, 'handleBulkSync']);
    }

    // -------------------------------------------------------------------------
    // Settings registration
    // -------------------------------------------------------------------------

    public static function registerWCSettings(): void
    {
        register_setting('invoicly_wc_settings', self::OPTION_ENABLED, [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);

        register_setting('invoicly_wc_settings', self::OPTION_TRIGGER_STATUSES, [
            'type'              => 'array',
            'sanitize_callback' => [self::class, 'sanitizeTriggerStatuses'],
            'default'           => ['wc-completed'],
        ]);
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    public static function sanitizeTriggerStatuses(mixed $value): array
    {
        if (! is_array($value)) {
            return ['wc-completed'];
        }

        $valid = array_keys(wc_get_order_statuses());

        return array_values(array_filter(
            array_map('sanitize_key', $value),
            fn (string $s) => in_array($s, $valid, true)
        ));
    }

    // -------------------------------------------------------------------------
    // Automatic trigger
    // -------------------------------------------------------------------------

    public static function onOrderStatusChanged(int $orderId, string $oldStatus, string $newStatus): void
    {
        if (! get_option(self::OPTION_ENABLED)) {
            return;
        }

        $triggerStatuses = (array) get_option(self::OPTION_TRIGGER_STATUSES, ['wc-completed']);
        if (! in_array('wc-' . $newStatus, $triggerStatuses, true)) {
            return;
        }

        // Skip if already synced to avoid duplicate invoices
        if (get_post_meta($orderId, self::META_INVOICE_ID, true)) {
            return;
        }

        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return;
        }

        self::syncOrder($order);
    }

    // -------------------------------------------------------------------------
    // Core sync logic
    // -------------------------------------------------------------------------

    /**
     * Create an Invoicly invoice for a WooCommerce order.
     * Returns the Invoicly invoice ID on success, null on failure.
     */
    public static function syncOrder(\WC_Order $order): ?int
    {
        if (! class_exists('\Invoicly\InvoiclyClient')) {
            error_log('Invoicly: SDK not found. Run composer install in the plugin directory.');
            return null;
        }

        $baseUrl = get_option(Settings::OPTION_BASE_URL, '');
        $token   = Settings::getToken();

        if (! $baseUrl || ! $token) {
            error_log('Invoicly: plugin not configured (missing API URL or token).');
            return null;
        }

        try {
            $sdk      = new \Invoicly\InvoiclyClient($baseUrl, $token);
            $clientId = self::findOrCreateClient($sdk, $order);

            $lineItems = self::buildLineItems($order);
            if (empty($lineItems)) {
                error_log("Invoicly: order #{$order->get_id()} has no line items — skipped.");
                return null;
            }

            $invoiceStatus = $order->get_status() === 'completed' ? 'paid' : 'awaiting_payment';

            $payload = [
                'client_id'  => $clientId,
                'issue_date' => $order->get_date_created()?->format('Y-m-d') ?? wp_date('Y-m-d'),
                'status'     => $invoiceStatus,
                'currency'   => strtoupper($order->get_currency()),
                'line_items' => $lineItems,
            ];

            // Include tax as vat_amount when present
            $tax = (float) $order->get_total_tax();
            if ($tax > 0) {
                $payload['vat_amount'] = $tax;
            }

            $result    = $sdk->invoices()->create($payload);
            $invoiceId = (int) ($result['data']['id'] ?? 0);

            if ($invoiceId > 0) {
                update_post_meta($order->get_id(), self::META_INVOICE_ID, $invoiceId);
                update_post_meta($order->get_id(), self::META_INVOICE_NUMBER, $result['data']['number'] ?? '');
                update_post_meta($order->get_id(), self::META_SYNCED_AT, time());

                $order->add_order_note(
                    sprintf(
                        /* translators: %s = Invoicly invoice number */
                        __('Invoicly invoice %s created.', 'invoicly'),
                        esc_html($result['data']['number'] ?? $invoiceId)
                    )
                );

                return $invoiceId;
            }
        } catch (\Throwable $e) {
            $detail = $e instanceof \Invoicly\Exceptions\InvoiclyException
                ? $e->getMessage() . ' ' . wp_json_encode($e->getErrors())
                : $e->getMessage();
            error_log("Invoicly: failed to sync order #{$order->get_id()} — {$detail}");
        }

        return null;
    }

    /**
     * Build Invoicly line_items from WooCommerce order items + shipping.
     *
     * @return list<array{description: string, quantity: float, unit_price: float}>
     */
    private static function buildLineItems(\WC_Order $order): array
    {
        $items = [];

        foreach ($order->get_items() as $item) {
            /** @var \WC_Order_Item_Product $item */
            $qty      = (float) $item->get_quantity();
            $subtotal = (float) $item->get_subtotal();
            $unitPrice = $qty > 0 ? round($subtotal / $qty, 4) : 0;

            $items[] = [
                'description' => $item->get_name(),
                'quantity'    => $qty,
                'unit_price'  => $unitPrice,
            ];
        }

        // Add shipping as a separate line item when present
        $shipping = (float) $order->get_shipping_total();
        if ($shipping > 0) {
            $items[] = [
                'description' => __('Shipping', 'invoicly'),
                'quantity'    => 1,
                'unit_price'  => $shipping,
            ];
        }

        return $items;
    }

    // -------------------------------------------------------------------------
    // Client lookup / creation
    // -------------------------------------------------------------------------

    /**
     * Find an existing Invoicly client by billing e-mail, or create one.
     * Result is cached in a WP transient keyed by the MD5 of the e-mail.
     *
     * @throws \Invoicly\Exceptions\InvoiclyException
     */
    private static function findOrCreateClient(\Invoicly\InvoiclyClient $sdk, \WC_Order $order): int
    {
        $email    = strtolower(trim($order->get_billing_email()));
        $cacheKey = 'invoicly_client_' . md5($email);

        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return (int) $cached;
        }

        // Search existing Invoicly clients by billing e-mail
        $list = $sdk->clients()->list(['search' => $email, 'type' => 'external', 'per_page' => 1]);
        if (! empty($list['data'][0]['id'])) {
            $clientId = (int) $list['data'][0]['id'];
            set_transient($cacheKey, $clientId, self::CLIENT_CACHE_TTL);
            return $clientId;
        }

        // No match — create a new client from the order's billing address
        $company    = trim($order->get_billing_company());
        $isBusiness = $company !== '';

        $country = strtoupper(trim($order->get_billing_country()));
        if (strlen($country) !== 2) {
            throw new \RuntimeException(
                "Order #{$order->get_id()} has no valid billing country — cannot create Invoicly client."
            );
        }

        $street     = trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2());
        $city       = trim($order->get_billing_city());
        $postalCode = trim($order->get_billing_postcode());

        $payload = [
            'type'        => 'external',
            'is_business' => $isBusiness,
            'email'       => $email ?: null,
            'country'     => $country,
            'street'      => $street ?: 'N/A',
            'city'        => $city ?: 'N/A',
            'postal_code' => $postalCode ?: 'N/A',
        ];

        if ($isBusiness) {
            $payload['business_name'] = $company;
        } else {
            $firstName = trim($order->get_billing_first_name());
            $lastName  = trim($order->get_billing_last_name());
            $payload['first_name'] = $firstName ?: 'Unknown';
            $payload['last_name']  = $lastName ?: 'Unknown';
        }

        $result   = $sdk->clients()->create($payload);
        $clientId = (int) ($result['data']['id'] ?? 0);

        if ($clientId > 0) {
            set_transient($cacheKey, $clientId, self::CLIENT_CACHE_TTL);
        }

        return $clientId;
    }

    // -------------------------------------------------------------------------
    // Bulk sync
    // -------------------------------------------------------------------------

    public static function handleBulkSync(): void
    {
        check_admin_referer('invoicly_bulk_sync');

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'invoicly'));
        }

        $offset = max(0, (int) ($_POST['offset'] ?? 0));

        // Query orders that don't already have an Invoicly invoice ID
        $orderIds = wc_get_orders([
            'status'     => array_keys(wc_get_order_statuses()),
            'limit'      => self::BULK_BATCH_SIZE,
            'offset'     => $offset,
            'return'     => 'ids',
            'meta_query' => [
                [
                    'key'     => self::META_INVOICE_ID,
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        $synced  = 0;
        $failed  = 0;
        $skipped = 0;

        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);
            if (! $order instanceof \WC_Order) {
                ++$skipped;
                continue;
            }

            $result = self::syncOrder($order);
            if ($result !== null) {
                ++$synced;
            } else {
                ++$failed;
            }
        }

        $hasMore    = count($orderIds) >= self::BULK_BATCH_SIZE;
        $nextOffset = $offset + count($orderIds);
        $tabUrl     = admin_url('options-general.php?page=invoicly-settings&tab=woocommerce');

        $redirectUrl = add_query_arg([
            'synced'  => $synced,
            'failed'  => $failed,
            'skipped' => $skipped,
            'more'    => $hasMore ? $nextOffset : 0,
        ], $tabUrl);

        wp_safe_redirect($redirectUrl);
        exit;
    }

    // -------------------------------------------------------------------------
    // Unsynced order count (for the admin UI)
    // -------------------------------------------------------------------------

    public static function countUnsyncedOrders(): int
    {
        $ids = wc_get_orders([
            'status'     => array_keys(wc_get_order_statuses()),
            'limit'      => -1,
            'return'     => 'ids',
            'meta_query' => [
                [
                    'key'     => self::META_INVOICE_ID,
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        return count($ids);
    }

    // -------------------------------------------------------------------------
    // Admin UI
    // -------------------------------------------------------------------------

    /**
     * Renders the WooCommerce tab content inside the plugin settings page.
     * The integration settings section shares the existing options.php form
     * (opened in Settings::renderPage), so no separate form is needed here.
     */
    public static function renderSettingsTab(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $synced  = isset($_GET['synced'])  ? (int) $_GET['synced']  : null;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $failed  = isset($_GET['failed'])  ? (int) $_GET['failed']  : null;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $more    = isset($_GET['more'])    ? (int) $_GET['more']    : 0;

        // Show bulk-sync results
        if ($synced !== null) {
            $failedCount = $failed ?? 0;
            $msg = sprintf(
                /* translators: 1: synced count, 2: failed count */
                __('Bulk sync complete: %1$d invoices created, %2$d failed.', 'invoicly'),
                $synced,
                $failedCount
            );
            if ($failedCount > 0) {
                $msg .= ' ' . __('Check your PHP error log for details on the failed orders.', 'invoicly');
            }
            $noticeClass = $failedCount > 0 ? 'notice-warning' : 'notice-success';
            echo '<div class="notice ' . esc_attr($noticeClass) . ' inline"><p>' . esc_html($msg) . '</p></div>';

            if ($more > 0) {
                echo '<div class="notice notice-warning inline"><p>'
                    . esc_html__('There are more orders to sync. Click "Sync next batch" to continue.', 'invoicly')
                    . '</p></div>';
            }
        }

        $enabled         = (bool) get_option(self::OPTION_ENABLED, false);
        $triggerStatuses = (array) get_option(self::OPTION_TRIGGER_STATUSES, ['wc-completed']);
        $allStatuses     = wc_get_order_statuses();

        ?>
        <h2><?php esc_html_e('WooCommerce Integration', 'invoicly'); ?></h2>
        <p><?php esc_html_e('Automatically create Invoicly invoices from WooCommerce orders.', 'invoicly'); ?></p>

        <!-- Integration settings (inside the parent options.php form) -->
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Enable integration', 'invoicly'); ?></th>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr(self::OPTION_ENABLED); ?>"
                            value="1"
                            <?php checked($enabled); ?>
                        />
                        <?php esc_html_e('Generate invoices automatically when an order reaches a trigger status', 'invoicly'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Trigger on order status', 'invoicly'); ?></th>
                <td>
                    <?php foreach ($allStatuses as $statusKey => $statusLabel) : ?>
                        <label style="display:block;margin-bottom:4px;">
                            <input
                                type="checkbox"
                                name="<?php echo esc_attr(self::OPTION_TRIGGER_STATUSES); ?>[]"
                                value="<?php echo esc_attr($statusKey); ?>"
                                <?php checked(in_array($statusKey, $triggerStatuses, true)); ?>
                            />
                            <?php echo esc_html($statusLabel); ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php esc_html_e('An invoice is created the first time an order reaches any of the checked statuses.', 'invoicly'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php
    }

    /**
     * Renders the bulk-sync section (heading + form) for the WooCommerce tab.
     * Must be called OUTSIDE the settings <form> to avoid a nested-form issue
     * that would prevent the Save settings button from submitting.
     */
    public static function renderBulkSync(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $more = isset($_GET['more']) ? (int) $_GET['more'] : 0;

        ?>
        <hr style="margin:24px 0;" />

        <h3><?php esc_html_e('Bulk sync existing orders', 'invoicly'); ?></h3>
        <?php
        $unsynced = self::countUnsyncedOrders();
        if ($unsynced === 0) {
            echo '<p>' . esc_html__('All eligible orders already have Invoicly invoices.', 'invoicly') . '</p>';
        } else {
            printf(
                '<p>' . esc_html(
                    /* translators: %d = number of unsynced orders */
                    _n(
                        '%d order does not have an Invoicly invoice yet.',
                        '%d orders do not have Invoicly invoices yet.',
                        $unsynced,
                        'invoicly'
                    )
                ) . '</p>',
                $unsynced
            );
        }
        ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('invoicly_bulk_sync'); ?>
            <input type="hidden" name="action" value="invoicly_bulk_sync" />
            <?php if ($more > 0) : ?>
                <input type="hidden" name="offset" value="<?php echo (int) $more; ?>" />
            <?php endif; ?>
            <p>
                <button
                    type="submit"
                    class="button button-primary"
                    <?php echo $unsynced === 0 && $more === 0 ? 'disabled' : ''; ?>
                >
                    <?php echo $more > 0
                        ? esc_html__('Sync next batch', 'invoicly')
                        : esc_html__('Sync now', 'invoicly'); ?>
                </button>
                <span class="description" style="margin-left:8px;">
                    <?php
                    printf(
                        /* translators: %d = batch size */
                        esc_html__('Processes up to %d orders per batch.', 'invoicly'),
                        self::BULK_BATCH_SIZE
                    );
                    ?>
                </span>
            </p>
        </form>
        <?php
    }
}
