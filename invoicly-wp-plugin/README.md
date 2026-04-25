# Invoicly WordPress Plugin

Connect your WordPress site to your [Invoicly](https://github.com/invoicly/invoicly) account.  
Place a shortcode anywhere to let visitors download invoices as PDFs — the API token is **never** exposed to the browser.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- Composer (for installing the bundled PHP SDK)
- An Invoicly account with an API token

## Installation

1. Copy this plugin folder into `wp-content/plugins/invoicly/`.
2. Inside the plugin directory, run:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Activate **Invoicly** in the WordPress admin (Plugins page).
4. Go to **Settings → Invoicly** and enter:
   - **Invoicly Base URL** — e.g. `https://your-invoicly.app`
   - **API Token** — generate one in Invoicly at Settings → API Tokens (needs at least `invoices:read`)

## Usage

### Download a specific invoice by ID

Place the shortcode on any page or post:

```
[invoicly_invoice_button invoice_id="123"]
```

### Custom button label

```
[invoicly_invoice_button invoice_id="123" label="Download your invoice"]
```

### Custom CSS class

```
[invoicly_invoice_button invoice_id="123" class="wp-element-button my-custom-class"]
```

## How it works

1. The shortcode renders a button in the browser.
2. On click, the browser calls the WP REST endpoint `/wp-json/invoicly/v1/download?invoice_id=…` with a WordPress nonce.
3. WordPress verifies the nonce, then the plugin calls the Invoicly API **server-side** using the stored (encrypted) token.
4. The PDF is streamed directly to the browser — the API token is never sent to the client.

## Security

| Concern | Mitigation |
|---------|-----------|
| API token exposure | Encrypted with AES-256-CBC using `AUTH_KEY` before storage in `wp_options` |
| CSRF | Every download request requires a valid `wp_nonce` |
| Token scope | Create a token with only `invoices:read` for read-only download integrations |
