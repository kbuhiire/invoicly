<?php
/**
 * Plugin Name:  Invoicly
 * Plugin URI:   https://github.com/invoicly/invoicly-wp-plugin
 * Description:  Generate and download Invoicly invoices directly from your WordPress site using your personal API token.
 * Version:      1.0.0
 * Author:       Invoicly
 * License:      MIT
 * Text Domain:  invoicly
 *
 * @package Invoicly
 */

defined('ABSPATH') || exit;

define('INVOICLY_VERSION', '1.0.0');
define('INVOICLY_DIR', plugin_dir_path(__FILE__));
define('INVOICLY_URL', plugin_dir_url(__FILE__));

// Autoload bundled SDK (installed via `composer install` in this directory)
if (file_exists(INVOICLY_DIR . 'vendor/autoload.php')) {
    require_once INVOICLY_DIR . 'vendor/autoload.php';
}

require_once INVOICLY_DIR . 'includes/Settings.php';
require_once INVOICLY_DIR . 'includes/InvoicesList.php';
require_once INVOICLY_DIR . 'includes/Shortcode.php';
require_once INVOICLY_DIR . 'includes/RestEndpoint.php';
require_once INVOICLY_DIR . 'includes/WooCommerce.php';

// Boot the plugin
Invoicly\WP\Settings::register();
Invoicly\WP\Shortcode::register();
Invoicly\WP\RestEndpoint::register();

// Boot WooCommerce integration only after all plugins have loaded,
// so that class_exists('WooCommerce') is reliable.
add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        Invoicly\WP\WooCommerce::register();
    }
});
