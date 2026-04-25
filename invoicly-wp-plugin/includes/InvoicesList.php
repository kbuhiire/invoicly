<?php

namespace Invoicly\WP;

defined('ABSPATH') || exit;

/**
 * Renders the Invoices tab in the plugin admin page.
 *
 * Fetches paginated invoices from the Invoicly API using the stored token
 * and displays them in a standard WP admin list table.
 * Each row includes a "Download PDF" link that routes through the WP REST
 * proxy so the API token is never exposed to the browser.
 *
 * Results are cached in a 60-second transient to avoid hammering the API
 * on every admin page load.
 */
class InvoicesList
{
    private const PER_PAGE       = 15;
    private const CACHE_SECONDS  = 60;

    public static function render(): void
    {
        $baseUrl = get_option(Settings::OPTION_BASE_URL, '');
        $token   = Settings::getToken();

        if (! $baseUrl || ! $token) {
            self::renderNotice(
                'error',
                sprintf(
                    /* translators: %s = link to settings tab */
                    __('Invoicly is not configured. Please enter your API URL and token on the <a href="%s">Settings tab</a>.', 'invoicly'),
                    esc_url(admin_url('options-general.php?page=invoicly-settings'))
                )
            );

            return;
        }

        if (! class_exists('\Invoicly\InvoiclyClient')) {
            self::renderNotice(
                'error',
                __('Invoicly SDK not found. Run <code>composer install</code> in the plugin directory.', 'invoicly')
            );

            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $currentPage = max(1, (int) ($_GET['paged'] ?? 1));

        $cacheKey = 'invoicly_invoices_p' . $currentPage;
        $cached   = get_transient($cacheKey);

        if ($cached !== false) {
            $result = $cached;
        } else {
            try {
                $sdk    = new \Invoicly\InvoiclyClient($baseUrl, $token);
                $result = $sdk->invoices()->list([
                    'per_page' => self::PER_PAGE,
                    'page'     => $currentPage,
                ]);
                set_transient($cacheKey, $result, self::CACHE_SECONDS);
            } catch (\Invoicly\Exceptions\InvoiclyException $e) {
                self::renderNotice('error', esc_html($e->getMessage()));

                return;
            }
        }

        $invoices = $result['data'] ?? [];
        $meta     = $result['meta'] ?? [];
        $total    = (int) ($meta['total'] ?? count($invoices));
        $lastPage = (int) ($meta['last_page'] ?? 1);

        // "Force-refresh" link clears the transient and reloads
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['invoicly_refresh']) && check_admin_referer('invoicly_refresh')) {
            delete_transient($cacheKey);
            wp_safe_redirect(admin_url('options-general.php?page=invoicly-settings&tab=invoices'));
            exit;
        }

        $baseTabUrl   = admin_url('options-general.php?page=invoicly-settings&tab=invoices');
        $refreshNonce = wp_create_nonce('invoicly_refresh');
        $refreshUrl   = wp_nonce_url($baseTabUrl . '&invoicly_refresh=1', 'invoicly_refresh');
        $downloadNonce = wp_create_nonce('invoicly_download');

        ?>
        <div class="invoicly-invoices-list">

            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="<?php echo esc_url($refreshUrl); ?>" class="button">
                        <?php esc_html_e('Refresh', 'invoicly'); ?>
                    </a>
                </div>
                <?php if ($lastPage > 1) : ?>
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php
                            /* translators: %d = total invoice count */
                            printf(esc_html(_n('%d invoice', '%d invoices', $total, 'invoicly')), (int) $total);
                            ?>
                        </span>
                        <span class="pagination-links">
                            <?php if ($currentPage > 1) : ?>
                                <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1, $baseTabUrl)); ?>">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                                <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $currentPage - 1, $baseTabUrl)); ?>">
                                    <span aria-hidden="true">&lsaquo;</span>
                                </a>
                            <?php endif; ?>
                            <span class="paging-input">
                                <?php
                                /* translators: 1: current page, 2: total pages */
                                printf(esc_html__('%1$d of %2$d', 'invoicly'), $currentPage, $lastPage);
                                ?>
                            </span>
                            <?php if ($currentPage < $lastPage) : ?>
                                <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $currentPage + 1, $baseTabUrl)); ?>">
                                    <span aria-hidden="true">&rsaquo;</span>
                                </a>
                                <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $lastPage, $baseTabUrl)); ?>">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
                <br class="clear" />
            </div>

            <?php if (empty($invoices)) : ?>
                <p><?php esc_html_e('No invoices found.', 'invoicly'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" style="width:130px;"><?php esc_html_e('Invoice #', 'invoicly'); ?></th>
                            <th scope="col"><?php esc_html_e('Client', 'invoicly'); ?></th>
                            <th scope="col" style="width:110px;"><?php esc_html_e('Issue date', 'invoicly'); ?></th>
                            <th scope="col" style="width:100px;"><?php esc_html_e('Due date', 'invoicly'); ?></th>
                            <th scope="col" style="width:130px;"><?php esc_html_e('Status', 'invoicly'); ?></th>
                            <th scope="col" style="width:110px;"><?php esc_html_e('Amount', 'invoicly'); ?></th>
                            <th scope="col" style="width:120px;"><?php esc_html_e('Actions', 'invoicly'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice) :
                            $invoiceId  = (int) ($invoice['id'] ?? 0);
                            $number     = esc_html($invoice['number'] ?? '—');
                            $clientName = esc_html($invoice['client']['name'] ?? '—');
                            $issueDate  = esc_html($invoice['issue_date'] ?? '—');
                            $dueDate    = esc_html($invoice['due_date'] ?? '—');
                            $status     = $invoice['status'] ?? '';
                            $amount     = isset($invoice['amount'], $invoice['currency'])
                                ? esc_html(number_format((float) $invoice['amount'], 2) . ' ' . strtoupper($invoice['currency']))
                                : '—';
                            $pdfUrl     = $invoiceId > 0
                                ? rest_url("invoicly/v1/download?invoice_id={$invoiceId}&_wpnonce={$downloadNonce}")
                                : '';
                        ?>
                            <tr>
                                <td><strong><?php echo $number; ?></strong></td>
                                <td><?php echo $clientName; ?></td>
                                <td><?php echo $issueDate; ?></td>
                                <td><?php echo $dueDate !== '—' ? $dueDate : '<span style="color:#999">—</span>'; ?></td>
                                <td><?php echo self::statusBadge($status); ?></td>
                                <td><?php echo $amount; ?></td>
                                <td>
                                    <?php if ($pdfUrl) : ?>
                                        <a href="<?php echo esc_url($pdfUrl); ?>" class="button button-small">
                                            <?php esc_html_e('Download PDF', 'invoicly'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="col"><?php esc_html_e('Invoice #', 'invoicly'); ?></th>
                            <th scope="col"><?php esc_html_e('Client', 'invoicly'); ?></th>
                            <th scope="col"><?php esc_html_e('Issue date', 'invoicly'); ?></th>
                            <th scope="col"><?php esc_html_e('Due date', 'invoicly'); ?></th>
                            <th scope="col"><?php esc_html_e('Status', 'invoicly'); ?></th>
                            <th scope="col"><?php esc_html_e('Amount', 'invoicly'); ?></th>
                            <th scope="col"><?php esc_html_e('Actions', 'invoicly'); ?></th>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>

            <?php if ($lastPage > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(esc_html(_n('%d invoice', '%d invoices', $total, 'invoicly')), (int) $total); ?>
                        </span>
                        <span class="pagination-links">
                            <?php if ($currentPage > 1) : ?>
                                <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1, $baseTabUrl)); ?>">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                                <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $currentPage - 1, $baseTabUrl)); ?>">
                                    <span aria-hidden="true">&lsaquo;</span>
                                </a>
                            <?php endif; ?>
                            <span class="paging-input">
                                <?php printf(esc_html__('%1$d of %2$d', 'invoicly'), $currentPage, $lastPage); ?>
                            </span>
                            <?php if ($currentPage < $lastPage) : ?>
                                <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $currentPage + 1, $baseTabUrl)); ?>">
                                    <span aria-hidden="true">&rsaquo;</span>
                                </a>
                                <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $lastPage, $baseTabUrl)); ?>">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            <?php endif; ?>
                        </span>
                    </div>
                    <br class="clear" />
                </div>
            <?php endif; ?>

        </div><!-- .invoicly-invoices-list -->
        <?php
    }

    /**
     * Render a coloured status badge using inline styles that work in
     * both light and dark WP admin themes.
     */
    private static function statusBadge(string $status): string
    {
        $labels = [
            'awaiting_payment' => ['label' => __('Awaiting payment', 'invoicly'), 'color' => '#b45309', 'bg' => '#fef3c7'],
            'paid'             => ['label' => __('Paid', 'invoicly'),             'color' => '#065f46', 'bg' => '#d1fae5'],
            'draft'            => ['label' => __('Draft', 'invoicly'),            'color' => '#374151', 'bg' => '#f3f4f6'],
            'overdue'          => ['label' => __('Overdue', 'invoicly'),          'color' => '#991b1b', 'bg' => '#fee2e2'],
            'cancelled'        => ['label' => __('Cancelled', 'invoicly'),        'color' => '#6b7280', 'bg' => '#f3f4f6'],
        ];

        $normalized = strtolower(str_replace('-', '_', $status));
        $config     = $labels[$normalized] ?? ['label' => esc_html(ucfirst(str_replace('_', ' ', $status))), 'color' => '#374151', 'bg' => '#f3f4f6'];

        return sprintf(
            '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:0.8em;font-weight:600;color:%s;background:%s;">%s</span>',
            esc_attr($config['color']),
            esc_attr($config['bg']),
            esc_html($config['label'])
        );
    }

    /**
     * Render a dismissible WP-style admin notice inside the tab content area.
     */
    private static function renderNotice(string $type, string $message): void
    {
        $type = in_array($type, ['error', 'warning', 'success', 'info'], true) ? $type : 'info';
        echo '<div class="notice notice-' . esc_attr($type) . ' inline"><p>' . wp_kses_post($message) . '</p></div>';
    }
}
