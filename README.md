# RitaHost WooCommerce Tools

[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL_v2_or_later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-compatible-96588A.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)

A modular collection of independent WordPress plugins for improving WooCommerce checkout, cart, customer account, order, navigation, notification, and product-management experiences.

Each tool can run independently as a regular plugin or join the shared, language-aware **RitaHost Panel** when installed as a must-use plugin. Admin labels follow the active WordPress locale: Persian sites see Persian labels and English sites see English labels.

## Highlights

- Modular architecture: install only the tools a site needs.
- Compatible with both regular and must-use plugin workflows.
- Persian/English admin labels based on the active WordPress language.
- Configurable customer-facing colors and behavior where appropriate.
- WordPress capabilities, sanitization, escaping, and nonce checks for sensitive actions.
- Backward-compatible migration for legacy RitaHost customer metadata.

## Installation

### As must-use plugins

Copy the PHP files and the `ritahost-smart-checkout` directory to `wp-content/mu-plugins`. WordPress loads the top-level PHP files automatically. Keep `00-ritahost-admin-panel.php` to group settings pages under the bilingual **RitaHost Panel** menu.

### As regular plugins

Each top-level PHP file has a standard WordPress plugin header and can be placed in `wp-content/plugins` and activated independently. Plugins with settings fall back to the standard WordPress or WooCommerce menu when the shared admin panel is absent. The smart checkout plugin must remain beside its `ritahost-smart-checkout` template directory.

## Included tools

- `00-ritahost-admin-panel.php`: shared Persian/English admin menu and tool overview.
- `ritahost-smart-checkout.php`: checkout templates, delivery schedules, fulfillment methods, and extra order fields.
- `ritahost-woocommerce-cart.php`: responsive cart layout, AJAX quantities, secure cart clearing, and configurable colors.
- `ritahost-woocommerce-add-to-cart-popup.php`: protected AJAX add-to-cart flow and configurable confirmation popup for product pages.
- `ritahost-woocommerce-user-notifications.php`: welcome, order status, and tracking notifications in My Account.
- `ritahost-woocommerce-wishlist.php`: independent wishlist storage, product icon/list shortcodes, AJAX updates, and settings.
- `ritahost-woocommerce-account-dashboard.php`: configurable My Account dashboard and order summaries.
- `ritahost-woocommerce-order-invoice.php`: thank-you and order-payment invoice layout.
- `ritahost-woocommerce-order-details.php`: responsive order details in My Account.
- `ritahost-woocommerce-address-card.php`: responsive billing and shipping address cards.
- `ritahost-mobile-bottom-navigation.php`: configurable fixed mobile navigation.
- `ritahost-product-workflow-statuses.php`: internal WooCommerce product workflow statuses and bulk actions.
- `ritahost-local-pickup-text.php`: clearer local-pickup label on checkout.

## Defaults and compatibility

New settings use the behavior and colors that were active before settings were added. Existing option names, user metadata keys, order metadata keys, shortcodes, and endpoint slugs are retained for backward compatibility.

Legacy wishlist and next-purchase user data are migrated automatically to RitaHost metadata keys when each user first accesses the related feature.

Requires WordPress 6.0+, PHP 7.4+, and WooCommerce for WooCommerce-specific tools. Test releases on a staging site before production deployment.

## Security

Administrative settings use WordPress capabilities, sanitization, and nonce-protected settings forms. Customer data remains scoped to the current WordPress user, WooCommerce session, or valid order key. Report security issues privately to the repository maintainer rather than opening a public issue with exploit details.

## Contributing and security

Contributions are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting a change. Please report suspected vulnerabilities privately according to [SECURITY.md](SECURITY.md), rather than opening a public issue.

## License

Licensed under the GNU General Public License v2.0 or later. See [LICENSE](LICENSE).

