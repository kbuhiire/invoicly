<?php

namespace Invoicly\WP;

defined('ABSPATH') || exit;

/**
 * WP REST API proxy endpoint for Invoicly PDF downloads.
 *
 * Route:  GET /wp-json/invoicly/v1/download
 * Params: invoice_id (int) OR client_id (int, creates/downloads latest invoice)
 *
 * The endpoint calls the Invoicly API server-side so the API token is
 * never exposed to the browser. It streams the PDF response directly.
 */
class RestEndpoint
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route('invoicly/v1', '/download', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'download'],
            'permission_callback' => [self::class, 'checkPermission'],
            'args'                => [
                'invoice_id' => [
                    'type'    => 'integer',
                    'minimum' => 1,
                ],
                'client_id' => [
                    'type'    => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);
    }

    /**
     * Require a valid nonce. This prevents CSRF and limits access to
     * browsers that have received a rendered shortcode on a WP page.
     */
    public static function checkPermission(\WP_REST_Request $request): bool|\WP_Error
    {
        $nonce = $request->get_header('X-WP-Nonce') ?? $request->get_param('_wpnonce') ?? '';
        if (! wp_verify_nonce($nonce, 'invoicly_download')) {
            return new \WP_Error('rest_forbidden', __('Invalid nonce.', 'invoicly'), ['status' => 403]);
        }

        return true;
    }

    /**
     * @return \WP_Error|\WP_REST_Response|void
     */
    public static function download(\WP_REST_Request $request)
    {
        $baseUrl = get_option(Settings::OPTION_BASE_URL, '');
        $token   = Settings::getToken();

        if (! $baseUrl || ! $token) {
            return new \WP_Error(
                'invoicly_not_configured',
                __('Invoicly is not configured. Please set the API URL and token in Settings → Invoicly.', 'invoicly'),
                ['status' => 503]
            );
        }

        if (! class_exists('\Invoicly\InvoiclyClient')) {
            return new \WP_Error(
                'invoicly_sdk_missing',
                __('Invoicly SDK not found. Run `composer install` in the plugin directory.', 'invoicly'),
                ['status' => 500]
            );
        }

        $invoiceId = (int) $request->get_param('invoice_id');

        if ($invoiceId <= 0) {
            return new \WP_Error(
                'invoicly_missing_param',
                __('An invoice_id is required.', 'invoicly'),
                ['status' => 400]
            );
        }

        try {
            $sdk = new \Invoicly\InvoiclyClient($baseUrl, $token);
            $pdf = $sdk->invoices()->downloadPdf($invoiceId);
        } catch (\Invoicly\Exceptions\InvoiclyException $e) {
            $status = $e->getHttpStatus() >= 400 ? $e->getHttpStatus() : 502;

            return new \WP_Error(
                'invoicly_api_error',
                $e->getMessage(),
                ['status' => $status]
            );
        }

        // Stream the PDF directly to the browser
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="invoice-' . $invoiceId . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-cache');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $pdf;
        exit;
    }
}
