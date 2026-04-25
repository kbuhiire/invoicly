<?php

namespace Invoicly\WP;

defined('ABSPATH') || exit;

/**
 * [invoicly_invoice_button] shortcode
 *
 * Renders a button that, when clicked, calls the WP REST proxy (server-side)
 * which calls the Invoicly API with the stored token and streams the PDF back
 * to the browser. The API token is never exposed to the browser.
 *
 * Attributes:
 *   client_id  (int, required)  — Client ID from the Invoicly account
 *   label      (string)         — Button label (default: "Download Invoice")
 *   class      (string)         — Additional CSS class(es) for the button
 *
 * Example:
 *   [invoicly_invoice_button client_id="5" label="Get my invoice"]
 */
class Shortcode
{
    public static function register(): void
    {
        add_shortcode('invoicly_invoice_button', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueScripts']);
    }

    /**
     * @param  array<string, string>|string  $atts
     */
    public static function render(array|string $atts): string
    {
        $atts = shortcode_atts([
            'client_id'   => '',
            'invoice_id'  => '',
            'label'       => __('Download Invoice', 'invoicly'),
            'class'       => '',
        ], is_array($atts) ? $atts : []);

        if ($atts['client_id'] === '' && $atts['invoice_id'] === '') {
            return '<!-- invoicly: client_id or invoice_id attribute is required -->';
        }

        $nonce    = wp_create_nonce('invoicly_download');
        $restUrl  = esc_url(rest_url('invoicly/v1/download'));
        $clientId = intval($atts['client_id']);
        $invoiceId = intval($atts['invoice_id']);
        $label    = esc_html($atts['label']);
        $class    = esc_attr('invoicly-btn ' . $atts['class']);

        $params = '';
        if ($clientId > 0) {
            $params .= 'client_id=' . $clientId . '&';
        }
        if ($invoiceId > 0) {
            $params .= 'invoice_id=' . $invoiceId . '&';
        }
        $params .= '_wpnonce=' . $nonce;

        // The button opens a hidden form that POSTs to the REST endpoint.
        // The response is a redirect to a temporary PDF stream or a JSON error.
        $html  = '<span class="invoicly-wrapper">';
        $html .= '<button type="button" class="' . $class . '" ';
        $html .= 'data-invoicly-url="' . $restUrl . '" ';
        $html .= 'data-invoicly-params="' . esc_attr($params) . '">';
        $html .= $label;
        $html .= '</button>';
        $html .= '<span class="invoicly-error" style="display:none;color:red;font-size:0.85em;margin-left:8px;"></span>';
        $html .= '</span>';

        return $html;
    }

    public static function enqueueScripts(): void
    {
        // Only load on pages/posts that actually contain the shortcode
        global $post;
        if (
            is_a($post, 'WP_Post') &&
            has_shortcode($post->post_content, 'invoicly_invoice_button')
        ) {
            wp_add_inline_script('jquery', self::inlineJs());
        }
    }

    private static function inlineJs(): string
    {
        return <<<JS
        jQuery(function($) {
            $(document).on('click', '.invoicly-btn', function() {
                var btn    = $(this);
                var url    = btn.data('invoicly-url');
                var params = btn.data('invoicly-params');
                var errEl  = btn.siblings('.invoicly-error');

                btn.prop('disabled', true).text('Generating…');
                errEl.hide().text('');

                window.location.href = url + '?' + params;

                // Re-enable after a short delay in case of error redirect
                setTimeout(function() {
                    btn.prop('disabled', false).text(btn.data('original-label') || 'Download Invoice');
                }, 4000);
            }).on('mousedown', '.invoicly-btn', function() {
                $(this).data('original-label', $(this).text());
            });
        });
        JS;
    }
}
