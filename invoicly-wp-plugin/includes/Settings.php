<?php

namespace Invoicly\WP;

defined('ABSPATH') || exit;

/**
 * Admin settings page — stores the Invoicly user's API base URL and token.
 * The token is stored encrypted using WordPress's auth keys so it is never
 * visible in plain text in the database.
 */
class Settings
{
    const OPTION_BASE_URL   = 'invoicly_base_url';
    const OPTION_TOKEN_HASH = 'invoicly_api_token_enc';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenuPage']);
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_init', [self::class, 'saveTokenOnOptionsUpdate']);
    }

    public static function addMenuPage(): void
    {
        add_options_page(
            __('Invoicly Settings', 'invoicly'),
            __('Invoicly', 'invoicly'),
            'manage_options',
            'invoicly-settings',
            [self::class, 'renderPage']
        );
    }

    public static function registerSettings(): void
    {
        register_setting('invoicly_settings', self::OPTION_BASE_URL, [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);

        add_settings_section(
            'invoicly_main',
            __('API Connection', 'invoicly'),
            fn () => print('<p>' . esc_html__('Enter the details from your Invoicly account (Settings → API Tokens).', 'invoicly') . '</p>'),
            'invoicly-settings'
        );

        add_settings_field(
            self::OPTION_BASE_URL,
            __('Invoicly Base URL', 'invoicly'),
            [self::class, 'renderBaseUrlField'],
            'invoicly-settings',
            'invoicly_main'
        );

        add_settings_field(
            'invoicly_api_token',
            __('API Token', 'invoicly'),
            [self::class, 'renderTokenField'],
            'invoicly-settings',
            'invoicly_main'
        );
    }

    public static function renderBaseUrlField(): void
    {
        $value = esc_attr(get_option(self::OPTION_BASE_URL, ''));
        echo '<input type="url" name="' . esc_attr(self::OPTION_BASE_URL) . '" value="' . $value . '" class="regular-text" placeholder="https://your-invoicly.app" />';
    }

    public static function renderTokenField(): void
    {
        $stored = self::getToken();
        $placeholder = $stored ? __('(token saved — paste a new one to replace)', 'invoicly') : 'inv_tok_xxxxxxxxxxxxxxxx';
        echo '<input type="password" name="invoicly_api_token" value="" class="regular-text" placeholder="' . esc_attr($placeholder) . '" autocomplete="off" />';
        echo '<p class="description">' . esc_html__('The token is stored encrypted. It is never shown again after saving.', 'invoicly') . '</p>';
    }

    public static function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $wcActive = class_exists('WooCommerce');

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $activeTab    = sanitize_key($_GET['tab'] ?? 'settings');
        $allowedTabs  = ['settings', 'invoices'];
        if ($wcActive) {
            $allowedTabs[] = 'woocommerce';
        }
        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'settings';
        }

        $settingsUrl    = admin_url('options-general.php?page=invoicly-settings');
        $invoicesUrl    = admin_url('options-general.php?page=invoicly-settings&tab=invoices');
        $woocommerceUrl = admin_url('options-general.php?page=invoicly-settings&tab=woocommerce');

        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';

        echo '<nav class="nav-tab-wrapper" style="margin-bottom:0;">';
        echo '<a href="' . esc_url($settingsUrl) . '" class="nav-tab' . ($activeTab === 'settings' ? ' nav-tab-active' : '') . '">'
            . esc_html__('Settings', 'invoicly') . '</a>';
        echo '<a href="' . esc_url($invoicesUrl) . '" class="nav-tab' . ($activeTab === 'invoices' ? ' nav-tab-active' : '') . '">'
            . esc_html__('Invoices', 'invoicly') . '</a>';
        if ($wcActive) {
            echo '<a href="' . esc_url($woocommerceUrl) . '" class="nav-tab' . ($activeTab === 'woocommerce' ? ' nav-tab-active' : '') . '">'
                . esc_html__('WooCommerce', 'invoicly') . '</a>';
        }
        echo '</nav>';

        echo '<div class="invoicly-tab-content" style="padding-top:16px;">';

        if ($activeTab === 'invoices') {
            InvoicesList::render();
        } elseif ($activeTab === 'woocommerce' && $wcActive) {
            // WooCommerce settings use their own options group (invoicly_wc_settings)
            // so saving this tab does not overwrite the main Settings tab values,
            // and vice-versa. The bulk-sync form is rendered AFTER this form closes
            // to avoid nesting.
            settings_errors('invoicly_wc_settings');
            echo '<form method="post" action="options.php">';
            settings_fields('invoicly_wc_settings');
            \Invoicly\WP\WooCommerce::renderSettingsTab();
            submit_button(__('Save settings', 'invoicly'));
            echo '</form>';
            \Invoicly\WP\WooCommerce::renderBulkSync();
        } else {
            settings_errors('invoicly_settings');
            echo '<form method="post" action="options.php">';
            settings_fields('invoicly_settings');
            do_settings_sections('invoicly-settings');
            submit_button(__('Save settings', 'invoicly'));
            echo '</form>';
        }

        echo '</div>'; // .invoicly-tab-content
        echo '</div>'; // .wrap
    }

    // --- Token save hook ---

    /**
     * Intercepts the options.php POST for the invoicly_settings group and
     * saves the API token before WordPress redirects back to the settings page.
     * This runs on every admin_init but exits immediately unless the request
     * is a matching options form submission.
     */
    public static function saveTokenOnOptionsUpdate(): void
    {
        if (
            empty($_POST['option_page']) ||
            $_POST['option_page'] !== 'invoicly_settings'
        ) {
            return;
        }

        // options.php already verified this nonce; re-check to be safe
        check_admin_referer('invoicly_settings-options');

        if (! current_user_can('manage_options')) {
            return;
        }

        $token = sanitize_text_field(wp_unslash($_POST['invoicly_api_token'] ?? ''));
        if ($token !== '') {
            self::storeToken($token);
        }
    }

    // --- Token encryption helpers ---

    /**
     * Encrypt and store the plain-text token in wp_options.
     * Uses AUTH_KEY as the key (falls back to a site-specific hash).
     */
    public static function storeToken(string $plainToken): void
    {
        if ($plainToken === '') {
            return;
        }

        $key   = self::encryptionKey();
        $iv    = random_bytes(16);
        $enc   = openssl_encrypt($plainToken, 'aes-256-cbc', $key, 0, $iv);
        $value = base64_encode($iv) . '::' . $enc;

        update_option(self::OPTION_TOKEN_HASH, $value, false);
    }

    /**
     * Decrypt and return the stored token, or null if not set.
     */
    public static function getToken(): ?string
    {
        $stored = get_option(self::OPTION_TOKEN_HASH, '');
        if (! $stored || ! str_contains($stored, '::')) {
            return null;
        }

        [$ivB64, $enc] = explode('::', $stored, 2);
        $iv    = base64_decode($ivB64);
        $plain = openssl_decrypt($enc, 'aes-256-cbc', self::encryptionKey(), 0, $iv);

        return $plain !== false ? $plain : null;
    }

    private static function encryptionKey(): string
    {
        $raw = defined('AUTH_KEY') ? AUTH_KEY : get_bloginfo('url');
        return substr(hash('sha256', $raw, true), 0, 32);
    }
}
