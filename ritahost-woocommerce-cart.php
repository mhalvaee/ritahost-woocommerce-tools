<?php
/**
 * Plugin Name: RitaHost WooCommerce Cart
 * Description: Replaces the WooCommerce cart with a responsive two-column layout, sticky summary, AJAX quantity controls, and configurable colors.
 * Version: 1.4.0
 * Author: RitaHost
 * Text Domain: ritahost
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

final class RitaHost_Purple_WC_Cart {

    const VERSION = '1.4.0';
    const OPTION  = 'ritahost_cart_settings';

    public static function init() {
        add_shortcode('ritahost_purple_cart', [__CLASS__, 'shortcode_cart']);
        add_filter('render_block', [__CLASS__, 'replace_cart_block'], 10, 2);
        add_filter('do_shortcode_tag', [__CLASS__, 'replace_cart_shortcode'], 10, 4);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 99);
        add_filter('body_class', [__CLASS__, 'body_class']);
        add_action('wp_ajax_ritahost_cart_qty', [__CLASS__, 'ajax_update_quantity']);
        add_action('wp_ajax_nopriv_ritahost_cart_qty', [__CLASS__, 'ajax_update_quantity']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 20);
    }

    public static function defaults() {
        return [
            'enabled'       => '1',
            'replace_block' => '1',
            'replace_code'  => '1',
            'primary'       => '#2ACEBB',
            'primary_dark'  => '#159C8F',
            'accent'        => '#48D8C8',
        ];
    }

    public static function settings() {
        return wp_parse_args((array) get_option(self::OPTION, []), self::defaults());
    }

    public static function register_settings() {
        register_setting('ritahost_cart_group', self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
            'default'           => self::defaults(),
        ]);
    }

    public static function sanitize_settings($input) {
        $defaults = self::defaults();
        return [
            'enabled'       => empty($input['enabled']) ? '0' : '1',
            'replace_block' => empty($input['replace_block']) ? '0' : '1',
            'replace_code'  => empty($input['replace_code']) ? '0' : '1',
            'primary'       => sanitize_hex_color($input['primary'] ?? '') ?: $defaults['primary'],
            'primary_dark'  => sanitize_hex_color($input['primary_dark'] ?? '') ?: $defaults['primary_dark'],
            'accent'        => sanitize_hex_color($input['accent'] ?? '') ?: $defaults['accent'],
        ];
    }

    public static function admin_menu() {
        $page_title = function_exists('ritahost_admin_text') ? ritahost_admin_text('تنظیمات سبد خرید', 'Cart Settings') : (is_rtl() ? 'تنظیمات سبد خرید' : 'Cart Settings');
        $menu_title = function_exists('ritahost_admin_text') ? ritahost_admin_text('سبد خرید', 'Cart') : (is_rtl() ? 'سبد خرید' : 'Cart');
        if (function_exists('ritahost_register_admin_tool')) {
            add_submenu_page('ritahost-panel', $page_title, $menu_title, 'manage_woocommerce', 'ritahost-cart-settings', [__CLASS__, 'settings_page']);
        } else {
            add_submenu_page('woocommerce', $page_title, $menu_title, 'manage_woocommerce', 'ritahost-cart-settings', [__CLASS__, 'settings_page']);
        }
    }

    public static function settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'ritahost'));
        }
        $settings = self::settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('تنظیمات سبد خرید ووکامرس', 'WooCommerce Cart Settings') : (is_rtl() ? 'تنظیمات سبد خرید ووکامرس' : 'WooCommerce Cart Settings')); ?></h1>
            <p><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('پیش‌فرض‌ها همان ظاهر فعلی افزونه هستند.', 'Defaults preserve the plugin current appearance and behavior.') : 'Defaults preserve the plugin current appearance and behavior.'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('ritahost_cart_group'); ?>
                <table class="form-table" role="presentation">
                    <tr><th><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('وضعیت', 'Status') : (is_rtl() ? 'وضعیت' : 'Status')); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked($settings['enabled'], '1'); ?>> <?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('فعال‌سازی طراحی سبد خرید', 'Enable cart design') : (is_rtl() ? 'فعال‌سازی طراحی سبد خرید' : 'Enable cart design')); ?></label></td></tr>
                    <tr><th><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('جایگزینی خودکار', 'Automatic replacement') : (is_rtl() ? 'جایگزینی خودکار' : 'Automatic replacement')); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[replace_block]" value="1" <?php checked($settings['replace_block'], '1'); ?>> <?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('بلوک سبد خرید ووکامرس', 'WooCommerce Cart Block') : (is_rtl() ? 'بلوک سبد خرید ووکامرس' : 'WooCommerce Cart Block')); ?></label><br><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[replace_code]" value="1" <?php checked($settings['replace_code'], '1'); ?>> [woocommerce_cart]</label></td></tr>
                    <tr><th><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('رنگ‌ها', 'Colors') : (is_rtl() ? 'رنگ‌ها' : 'Colors')); ?></th><td>
                        <label><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('رنگ اصلی', 'Primary') : (is_rtl() ? 'رنگ اصلی' : 'Primary')); ?> <input type="color" name="<?php echo esc_attr(self::OPTION); ?>[primary]" value="<?php echo esc_attr($settings['primary']); ?>"></label><br>
                        <label><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('رنگ اصلی تیره', 'Primary dark') : (is_rtl() ? 'رنگ اصلی تیره' : 'Primary dark')); ?> <input type="color" name="<?php echo esc_attr(self::OPTION); ?>[primary_dark]" value="<?php echo esc_attr($settings['primary_dark']); ?>"></label><br>
                        <label><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('رنگ تأکیدی', 'Accent') : (is_rtl() ? 'رنگ تأکیدی' : 'Accent')); ?> <input type="color" name="<?php echo esc_attr(self::OPTION); ?>[accent]" value="<?php echo esc_attr($settings['accent']); ?>"></label>
                    </td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }


    public static function body_class($classes) {
        if ('1' === self::settings()['enabled'] && function_exists('is_cart') && is_cart()) {
            $classes[] = 'rhpc-cart-active';
        }
        return $classes;
    }

    public static function enqueue_assets() {
        if ('1' !== self::settings()['enabled'] || !function_exists('is_cart') || !is_cart()) {
            return;
        }

        wp_register_style('ritahost-purple-cart', false, [], self::VERSION);
        wp_enqueue_style('ritahost-purple-cart');
        wp_add_inline_style('ritahost-purple-cart', self::css());

        wp_register_script(
            'ritahost-purple-cart',
            '',
            ['jquery'],
            self::VERSION,
            true
        );
        wp_enqueue_script('ritahost-purple-cart');

        wp_localize_script('ritahost-purple-cart', 'RitaPurpleCart', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ritahost_cart_nonce'),
            'i18n'    => [
                'updating' => 'در حال بروزرسانی…',
                'error'    => 'بروزرسانی سبد خرید انجام نشد.',
            ],
        ]);

        wp_add_inline_script('ritahost-purple-cart', self::js());
    }

    /**
     * شورتکد اختصاصی برای استفاده مستقیم در برگه یا المنتور:
     * [ritahost_purple_cart]
     */
    public static function shortcode_cart() {
        if (!function_exists('WC') || !WC()->cart) {
            return '';
        }

        return self::render_cart();
    }

    /**
     * فقط بلوک اصلی سبد خرید ووکامرس را جایگزین می‌کند.
     * محتوای دیگر صفحه، لوپ‌های المنتور و کارت‌های محصول دست‌نخورده می‌مانند.
     */
    public static function replace_cart_block($block_content, $block) {
        if ('1' !== self::settings()['enabled'] || '1' !== self::settings()['replace_block']) {
            return $block_content;
        }
        if (
            is_admin() ||
            wp_doing_ajax() ||
            !function_exists('is_cart') ||
            !is_cart() ||
            empty($block['blockName']) ||
            'woocommerce/cart' !== $block['blockName'] ||
            !function_exists('WC') ||
            !WC()->cart
        ) {
            return $block_content;
        }

        return self::render_cart();
    }

    /**
     * فقط خروجی شورتکد پیش‌فرض [woocommerce_cart] را جایگزین می‌کند.
     */
    public static function replace_cart_shortcode($output, $tag, $attr, $m) {
        if ('1' !== self::settings()['enabled'] || '1' !== self::settings()['replace_code']) {
            return $output;
        }
        if (
            is_admin() ||
            wp_doing_ajax() ||
            !function_exists('is_cart') ||
            !is_cart() ||
            'woocommerce_cart' !== $tag ||
            !function_exists('WC') ||
            !WC()->cart
        ) {
            return $output;
        }

        return self::render_cart();
    }

    private static function render_cart() {
        ob_start();

        wc_print_notices();

        if (WC()->cart->is_empty()) {
            self::render_empty_cart();
            return ob_get_clean();
        }

        $cart = WC()->cart->get_cart();
        $total_savings = self::get_total_savings();
        ?>
        <div class="rhpc-wrap" dir="rtl">
                   <div class="rhpc-layout">
                <main class="rhpc-main">
                    <div class="rhpc-heading">
                        <div>
                            <h1>سبد خرید شما <span class="rhpc-heading__count">(<?php echo esc_html(WC()->cart->get_cart_contents_count()); ?> کالا)</span></h1>
                        </div>
                        <form class="rhpc-clear-form" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">
                            <input type="hidden" name="rhpc_action" value="empty_cart">
                            <?php wp_nonce_field('rhpc_empty_cart', 'rhpc_nonce'); ?>
                            <button class="rhpc-clear" type="submit" data-rhpc-clear>حذف همه</button>
                        </form>
                    </div>

                    <form class="rhpc-cart-form woocommerce-cart-form" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">
                        <div class="rhpc-products">
                            <?php foreach ($cart as $cart_item_key => $cart_item) :
                                $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

                                if (!$_product || !$_product->exists() || $cart_item['quantity'] <= 0) {
                                    continue;
                                }

                                $product_permalink = apply_filters(
                                    'woocommerce_cart_item_permalink',
                                    $_product->is_visible() ? $_product->get_permalink($cart_item) : '',
                                    $cart_item,
                                    $cart_item_key
                                );

                                $thumbnail = apply_filters(
                                    'woocommerce_cart_item_thumbnail',
                                    $_product->get_image('woocommerce_thumbnail'),
                                    $cart_item,
                                    $cart_item_key
                                );

                                $product_name = apply_filters(
                                    'woocommerce_cart_item_name',
                                    $_product->get_name(),
                                    $cart_item,
                                    $cart_item_key
                                );

                                $regular_price = (float) $_product->get_regular_price();
                                $sale_price    = (float) $_product->get_sale_price();
                                $has_sale      = $sale_price > 0 && $regular_price > $sale_price;
                                $discount_pct  = $has_sale ? round((($regular_price - $sale_price) / $regular_price) * 100) : 0;
                                ?>
                                <article class="rhpc-product" data-cart-key="<?php echo esc_attr($cart_item_key); ?>">
                                    <div class="rhpc-product__image">
                                        <?php if ($product_permalink) : ?>
                                            <a href="<?php echo esc_url($product_permalink); ?>"><?php echo $thumbnail; ?></a>
                                        <?php else : ?>
                                            <?php echo $thumbnail; ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="rhpc-product__body">
                                        <div class="rhpc-product__top">
                                            <div class="rhpc-product__info">
                                                <h2>
                                                    <?php if ($product_permalink) : ?>
                                                        <a href="<?php echo esc_url($product_permalink); ?>"><?php echo wp_kses_post($product_name); ?></a>
                                                    <?php else : ?>
                                                        <?php echo wp_kses_post($product_name); ?>
                                                    <?php endif; ?>
                                                </h2>

                                                <?php echo wc_get_formatted_cart_item_data($cart_item); ?>

                                                <?php if ($has_sale) :
                                                    $save_amount = ($regular_price - $sale_price) * $cart_item['quantity'];
                                                ?>
                                                    <div class="rhpc-saving-box">
                                                        <span><?php echo esc_html($discount_pct); ?>٪ تخفیف</span>
                                                        <span>|</span>
                                                        <span>سود شما از این خرید: <?php echo wc_price($save_amount); ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="rhpc-prices">
                                                    <?php if ($has_sale) : ?>
                                                        <span class="rhpc-regular-price"><?php echo wc_price($regular_price); ?></span>
                                                    <?php endif; ?>
                                                    <strong class="rhpc-sale-price"><?php echo WC()->cart->get_product_price($_product); ?></strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rhpc-product__bottom">
                                            

                                            <div class="rhpc-qty rhpc-qty-vertical">
                                                <button type="button" class="rhpc-qty__btn rhpc-qty__plus" aria-label="افزایش تعداد">+</button>
                                                <input
                                                    type="number"
                                                    class="rhpc-qty__input"
                                                    value="<?php echo esc_attr($cart_item['quantity']); ?>"
                                                    data-current="<?php echo esc_attr($cart_item['quantity']); ?>"
                                                    readonly
                                                />
                                                <button type="button" class="rhpc-qty__btn rhpc-qty__minus" aria-label="کاهش یا حذف">
                                                    <?php echo $cart_item['quantity'] == 1 ? '🗑' : '−'; ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
                    </form>

                    <a class="rhpc-back" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
                        ← ادامه خرید
                    </a>
                </main>

                <aside class="rhpc-summary">
                    <div class="rhpc-summary__card">
                        <h2>سفارش شما</h2>

                        <div class="rhpc-summary__row">
                            <span>قیمت کالاها</span>
                            <strong data-rhpc-subtotal><?php echo wc_price(self::get_regular_cart_total()); ?></strong>
                        </div>

                        <div class="rhpc-summary__row rhpc-summary__saving">
                            <span>سود شما از این خرید</span>
                            <strong data-rhpc-savings><?php echo wc_price($total_savings); ?></strong>
                        </div>

                        <div class="rhpc-benefit">
                            <span class="rhpc-benefit__icon">🚚</span>
                            <div>
                                <strong>ارسال مطمئن سفارش</strong>
                                <small>ارسال رایگان برای خرید بالای ۵ میلیون تومان</small>
                            </div>
                        </div>

                        <?php if (wc_coupons_enabled()) : ?>
                            <div class="rhpc-coupon">
                                <button type="button" class="rhpc-accordion-btn">
                                    <span>کد تخفیف دارید؟</span>
                                    <span>+</span>
                                </button>
                                <div class="rhpc-accordion-content">
                                    <div class="rhpc-coupon__form">
                                        <input type="text" id="rhpc_coupon_code" placeholder="کد تخفیف">
                                        <button type="button" id="rhpc_apply_coupon">اعمال</button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="rhpc-total">
                            <span>جمع سبد خرید</span>
                            <strong data-rhpc-total><?php echo WC()->cart->get_total(); ?></strong>
                        </div>

                        <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="rhpc-checkout">
                            ادامه فرایند خرید
                            <span>←</span>
                        </a>

                        <div class="rhpc-trust">
                            <span>🔒 پرداخت امن</span>
                            <span>↩ ضمانت بازگشت</span>
                        </div>
                    </div>
                </aside>
            </div>

            <div class="rhpc-loading" aria-hidden="true">
                <span></span>
                <p>در حال بروزرسانی سبد خرید…</p>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    private static function render_empty_cart() {
        ?>
        <div class="rhpc-empty" dir="rtl">
            <div class="rhpc-empty__icon">🛒</div>
            <h1>سبد خرید شما خالی است</h1>
            <p>هنوز محصولی به سبد خرید اضافه نکرده‌اید.</p>
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">مشاهده محصولات</a>
        </div>
        <?php
    }

    private static function get_regular_cart_total() {
        if (!function_exists('WC') || !WC()->cart) {
            return 0;
        }

        $total = 0;

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (empty($cart_item['data']) || !is_object($cart_item['data'])) {
                continue;
            }

            $product = $cart_item['data'];
            $qty = isset($cart_item['quantity']) ? (int) $cart_item['quantity'] : 0;
            $regular = (float) $product->get_regular_price();

            if ($qty > 0 && $regular > 0) {
                $total += $regular * $qty;
            }
        }

        return $total;
    }

    private static function get_total_savings() {
        if (!function_exists('WC') || !WC()->cart) {
            return 0;
        }

        $total_savings = (float) WC()->cart->get_discount_total();

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (empty($cart_item['data']) || !is_object($cart_item['data'])) {
                continue;
            }

            $product       = $cart_item['data'];
            $quantity      = isset($cart_item['quantity']) ? (int) $cart_item['quantity'] : 0;
            $regular_price = (float) $product->get_regular_price();
            $current_price = (float) $product->get_price();

            if ($quantity > 0 && $regular_price > $current_price) {
                $total_savings += ($regular_price - $current_price) * $quantity;
            }
        }

        return max(0, $total_savings);
    }

    public static function ajax_update_quantity() {
        check_ajax_referer('ritahost_cart_nonce', 'nonce');

        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error(['message' => 'سبد خرید در دسترس نیست.']);
        }

        $cart_key = isset($_POST['cart_key']) ? wc_clean(wp_unslash($_POST['cart_key'])) : '';
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

        if (!$cart_key || !isset(WC()->cart->get_cart()[$cart_key])) {
            wp_send_json_error(['message' => 'محصول پیدا نشد.']);
        }

        WC()->cart->set_quantity($cart_key, $quantity, true);
        WC()->cart->calculate_totals();

        wp_send_json_success([
            'count'    => WC()->cart->get_cart_contents_count(),
            'subtotal' => WC()->cart->get_cart_subtotal(),
            'discount' => WC()->cart->get_discount_total() > 0 ? '-' . wc_price(WC()->cart->get_discount_total()) : '',
            'savings'  => wc_price(self::get_total_savings()),
            'total'    => WC()->cart->get_total(),
            'empty'    => WC()->cart->is_empty(),
        ]);
    }

    private static function css() {
        $css = <<<'CSS'
:root{
    --rhpc-primary:#2ACEBB;
    --rhpc-primary-dark:#159C8F;
    --rhpc-primary-soft:#E8FBF8;
    --rhpc-accent:#48D8C8;
    --rhpc-border:#ece5f4;
    --rhpc-text:#24182e;
    --rhpc-muted:#81758b;
    --rhpc-success:#179765;
    --rhpc-danger:#e34d6f;
    --rhpc-bg:#faf8fc;
    --rhpc-white:#fff;
    --rhpc-shadow:0 18px 45px rgba(74,30,116,.10);
}

body.woocommerce-cart{
    background:var(--rhpc-bg);
}

body.rhpc-cart-active .site-main,
body.rhpc-cart-active main,
body.rhpc-cart-active .content-area{
    overflow:visible!important;
}

body.woocommerce-cart .entry-title,
body.woocommerce-cart .page-title,
body.woocommerce-cart .woocommerce-breadcrumb{
    display:none!important;
}

.rhpc-wrap{
    max-width:1240px;
    margin:30px auto 70px;
    padding:0 20px;
    position:relative;
    color:var(--rhpc-text);
    font-family:inherit;
}

.rhpc-progress{
    display:flex;
    align-items:center;
    justify-content:center;
    max-width:820px;
    margin:0 auto 34px;
    padding:18px 25px;
    background:#fff;
    border:1px solid var(--rhpc-border);
    border-radius:18px;
    box-shadow:0 8px 24px rgba(65,26,98,.05);
}

.rhpc-progress__item{
    display:flex;
    align-items:center;
    gap:8px;
    color:#b7aabc;
    font-size:13px;
    white-space:nowrap;
}

.rhpc-progress__item.is-active{
    color:var(--rhpc-primary);
    font-weight:800;
}

.rhpc-progress__icon{
    width:34px;
    height:34px;
    display:grid;
    place-items:center;
    border-radius:50%;
    background:#f4f0f7;
    font-size:15px;
}

.rhpc-progress__item.is-active .rhpc-progress__icon{
    background:var(--rhpc-primary);
    color:#fff;
}

.rhpc-progress__line{
    width:72px;
    height:1px;
    margin:0 14px;
    background:var(--rhpc-border);
}

.rhpc-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr) 370px;
    gap:28px;
    align-items:start;
}

.rhpc-main,
.rhpc-summary__card{
    background:var(--rhpc-white);
    border:1px solid var(--rhpc-border);
    border-radius:22px;
   
}

.rhpc-main{
    padding:28px;
}

.rhpc-heading{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
    margin-bottom:12px;
}

.rhpc-heading h1{
    margin:0 0 5px;
    font-size:25px;
    line-height:1.5;
    color:var(--rhpc-text);
}

.rhpc-heading__count{
    color:var(--rhpc-muted);
    font-size:.62em;
    font-weight:700;
    white-space:nowrap;
}

.rhpc-heading p{
    margin:0;
    color:var(--rhpc-muted);
    font-size:13px;
}

.rhpc-clear{
    color:var(--rhpc-danger)!important;
    font-size:13px;
    font-weight:700;
    text-decoration:none!important;
    padding:8px 12px;
    border-radius:10px;
    background:#fff1f4;
    border:0;
    cursor:pointer;
    font-family:inherit;
}

.rhpc-clear-form{margin:0}

.rhpc-product{
    display:grid;
    grid-template-columns:105px minmax(0,1fr);
    gap:18px;
    padding:24px 0;
    border-bottom:1px solid var(--rhpc-border);
}

.rhpc-product:last-child{
    border-bottom:0;
}

.rhpc-product__image{
    width:105px;
    height:105px;
    border:1px solid var(--rhpc-border);
    border-radius:16px;
    overflow:hidden;
    background:#fff;
    display:grid;
    place-items:center;
}

.rhpc-product__image img{
    width:100%;
    height:100%;
    object-fit:contain;
    padding:8px;
}

.rhpc-product__body{
    min-width:0;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
}

.rhpc-product__top{
    display:flex;
    justify-content:space-between;
    gap:15px;
}

.rhpc-product__info h2{
    margin:0 0 8px;
    font-size:15px;
    line-height:1.8;
    font-weight:800;
}

.rhpc-product__info h2 a{
    color:var(--rhpc-text);
    text-decoration:none;
}

.rhpc-product__info .variation{
    margin:5px 0;
    font-size:12px;
    color:var(--rhpc-muted);
}


.rhpc-saving-box{
    display:inline-flex;
    align-items:center;
    gap:7px;
    margin-top:8px;
    padding:5px 14px;
    border-radius:999px;
    background:var(--rhpc-primary);
    color:#fff;
    font-size:13px;
    font-weight:700;
    line-height:1.8;
}

.rhpc-saving-box span{
    white-space:nowrap;
}

.rhpc-product{
    align-items:center;
}

.rhpc-product__bottom{
    align-items:flex-end;
}

.rhpc-qty-vertical{
    height:105px!important;
    grid-template-rows:35px 35px 35px!important;
    align-self:center;
}

.rhpc-prices{
    margin-top:auto;
}

.rhpc-discount-badge{
    display:inline-flex;
    align-items:center;
    min-height:25px;
    padding:3px 9px;
    margin-left:7px;
    border-radius:7px;
    background:var(--rhpc-primary);
    color:#fff;
    font-size:11px;
    font-weight:800;
}

.rhpc-stock{display:none!important;
    display:inline-flex;
    align-items:center;
    gap:5px;
    color:var(--rhpc-success);
    font-size:11px;
    font-weight:700;
}

.rhpc-stock:before{
    content:"";
    width:7px;
    height:7px;
    border-radius:50%;
    background:currentColor;
}

.rhpc-remove{
    width:30px;
    height:30px;
    border-radius:9px;
    display:grid;
    place-items:center;
    background:#faf7fc;
    color:#a69bad!important;
    font-size:21px;
    text-decoration:none!important;
    transition:.2s;
}

.rhpc-remove:hover{
    background:#fff0f3;
    color:var(--rhpc-danger)!important;
}

.rhpc-product__bottom{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:16px;
    margin-top:15px;
}

.rhpc-prices{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

.rhpc-prices del{
    color:#a89dac;
    font-size:12px;
}

.rhpc-prices strong{
    color:var(--rhpc-primary-dark);
    font-size:17px;
    font-weight:900;
}

.rhpc-qty{
    display:grid;
    grid-template-columns:36px 42px 36px;
    height:40px;
    border:1px solid var(--rhpc-border);
    border-radius:12px;
    overflow:hidden;
    background:#fff;
}

.rhpc-qty__btn{
    border:0!important;
    padding:0!important;
    background:var(--rhpc-primary-soft)!important;
    color:var(--rhpc-primary)!important;
    font-size:20px!important;
    font-weight:700!important;
    border-radius:0!important;
    cursor:pointer;
}

.rhpc-qty__input{
    width:42px!important;
    min-width:42px!important;
    height:40px!important;
    margin:0!important;
    padding:0!important;
    border:0!important;
    border-radius:0!important;
    text-align:center;
    font-weight:800;
    color:var(--rhpc-text);
    box-shadow:none!important;
    -moz-appearance:textfield;
}

.rhpc-qty__input::-webkit-inner-spin-button,
.rhpc-qty__input::-webkit-outer-spin-button{
    -webkit-appearance:none;
    margin:0;
}

.rhpc-back{
    display:inline-flex;
    margin-top:20px;
    color:var(--rhpc-primary)!important;
    text-decoration:none!important;
    font-size:13px;
    font-weight:800;
}

.rhpc-summary{
    position:sticky;
    top:25px;
}

.rhpc-summary__card{
    padding:25px;
}

.rhpc-summary__card h2{
    margin:0 0 22px;
    font-size:20px;
    color:var(--rhpc-text);
}

.rhpc-summary__row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    padding:11px 0;
    font-size:13px;
    color:var(--rhpc-muted);
}

.rhpc-summary__row strong{
    color:var(--rhpc-text);
    font-size:14px;
}

.rhpc-summary__discount strong,
.rhpc-summary__saving strong{
    color:var(--rhpc-success);
}

.rhpc-benefit{
    display:flex;
    align-items:center;
    gap:11px;
    margin:15px 0;
    padding:13px;
    border-radius:14px;
    background:var(--rhpc-primary-soft);
}

.rhpc-benefit__icon{
    width:38px;
    height:38px;
    display:grid;
    place-items:center;
    border-radius:11px;
    background:#fff;
}

.rhpc-benefit div{
    display:flex;
    flex-direction:column;
    gap:3px;
}

.rhpc-benefit strong{
    font-size:12px;
    color:var(--rhpc-primary-dark);
}

.rhpc-benefit small{
    font-size:10px;
    color:var(--rhpc-muted);
    line-height:1.6;
}

.rhpc-coupon{
    border-top:1px solid var(--rhpc-border);
    border-bottom:1px solid var(--rhpc-border);
    margin:15px 0;
}

.rhpc-accordion-btn{
    width:100%;
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:15px 0!important;
    border:0!important;
    background:transparent!important;
    color:var(--rhpc-text)!important;
    font-size:13px!important;
    font-weight:800!important;
}

.rhpc-accordion-content{
    display:none;
    padding:0 0 15px;
}

.rhpc-coupon.is-open .rhpc-accordion-content{
    display:block;
}

.rhpc-coupon__form{
    display:grid;
    grid-template-columns:minmax(0,1fr) 70px;
    gap:8px;
}

.rhpc-coupon__form input{
    width:100%!important;
    height:42px!important;
    padding:0 12px!important;
    border:1px solid var(--rhpc-border)!important;
    border-radius:10px!important;
    box-shadow:none!important;
    font-size:12px!important;
}

.rhpc-coupon__form button{
    border:0!important;
    border-radius:10px!important;
    background:var(--rhpc-primary)!important;
    color:#fff!important;
    font-size:12px!important;
    font-weight:800!important;
}

.rhpc-total{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    margin:20px 0;
}

.rhpc-total span{
    font-size:14px;
    font-weight:800;
}

.rhpc-total strong{
    color:var(--rhpc-primary-dark);
    font-size:20px;
    font-weight:900;
}

.rhpc-checkout{
    width:100%;
    min-height:54px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    border-radius:14px;
    background:linear-gradient(135deg,var(--rhpc-primary),var(--rhpc-accent));
    color:#fff!important;
    text-decoration:none!important;
    font-size:14px;
    font-weight:900;
    box-shadow:0 12px 24px rgba(111,45,189,.25);
    transition:.2s;
}

.rhpc-checkout:hover{
    transform:translateY(-2px);
    box-shadow:0 15px 30px rgba(111,45,189,.32);
}

.rhpc-trust{
    display:flex;
    justify-content:center;
    flex-wrap:wrap;
    gap:12px;
    margin-top:16px;
    color:var(--rhpc-muted);
    font-size:10px;
}

.rhpc-loading{
    position:fixed;
    inset:0;
    z-index:999999;
    display:none;
    place-items:center;
    align-content:center;
    gap:12px;
    background:rgba(250,248,252,.82);
    backdrop-filter:blur(4px);
}

.rhpc-loading.is-active{
    display:grid;
}

.rhpc-loading span{
    width:42px;
    height:42px;
    border:4px solid #e6daf2;
    border-top-color:var(--rhpc-primary);
    border-radius:50%;
    animation:rhpc-spin .8s linear infinite;
}

.rhpc-loading p{
    margin:0;
    color:var(--rhpc-primary-dark);
    font-size:13px;
    font-weight:800;
}

@keyframes rhpc-spin{
    to{transform:rotate(360deg)}
}

.rhpc-empty{
    max-width:700px;
    margin:60px auto;
    padding:60px 30px;
    text-align:center;
    background:#fff;
    border:1px solid var(--rhpc-border);
    border-radius:24px;
    box-shadow:var(--rhpc-shadow);
}

.rhpc-empty__icon{
    width:90px;
    height:90px;
    margin:0 auto 20px;
    display:grid;
    place-items:center;
    border-radius:50%;
    background:var(--rhpc-primary-soft);
    font-size:40px;
}

.rhpc-empty h1{
    margin:0 0 10px;
    font-size:25px;
}

.rhpc-empty p{
    margin:0 0 25px;
    color:var(--rhpc-muted);
}

.rhpc-empty a{
    display:inline-flex;
    padding:13px 24px;
    border-radius:12px;
    background:var(--rhpc-primary);
    color:#fff!important;
    text-decoration:none!important;
    font-weight:800;
}

@media (max-width: 980px){
    .rhpc-layout{
        grid-template-columns:1fr;
    }

    .rhpc-summary{
        position:static;
    }
}

@media (max-width: 700px){
    .rhpc-wrap{
        margin:15px auto 40px;
        padding:0 10px;
    }

    .rhpc-progress{
        justify-content:space-between;
        padding:13px 12px;
        border-radius:14px;
        overflow-x:auto;
    }

    .rhpc-progress__item{
        flex-direction:column;
        gap:4px;
        font-size:9px;
        text-align:center;
    }

    .rhpc-progress__icon{
        width:30px;
        height:30px;
        font-size:13px;
    }

    .rhpc-progress__line{
        min-width:25px;
        width:25px;
        margin:0 5px;
    }

    .rhpc-main{
        padding:18px 14px;
        border-radius:18px;
    }

    .rhpc-heading h1{
        font-size:20px;
    }

    .rhpc-product{
        grid-template-columns:78px minmax(0,1fr);
        gap:12px;
        padding:18px 0;
    }

    .rhpc-product__image{
        width:78px;
        height:78px;
        border-radius:13px;
    }

    .rhpc-product__info h2{
        font-size:13px;
        line-height:1.7;
    }

    .rhpc-product__bottom{
        align-items:center;
    }

    .rhpc-prices{
        flex-direction:column;
        align-items:flex-start;
        gap:2px;
    }

    .rhpc-prices strong{
        font-size:14px;
    }

    .rhpc-summary__card{
        padding:20px;
        border-radius:18px;
    }
}

.rhpc-qty-vertical{
    display:grid!important;
    grid-template-rows:36px 38px 36px!important;
    grid-template-columns:36px!important;
    width:36px!important;
    height:110px!important;
    border:1px solid var(--rhpc-border);
    border-radius:12px;
    overflow:hidden;
}
.rhpc-qty-vertical .rhpc-qty__btn{
    display:flex!important;
    align-items:center;
    justify-content:center;
}
.rhpc-qty-vertical .rhpc-qty__input{
    width:36px!important;
    height:38px!important;
    min-width:36px!important;
}


/* v1.2.3 layout correction - cart page only */
.rhpc-product{
    display:grid!important;
    grid-template-columns:70px minmax(0,1fr) 110px!important;
    grid-template-areas:"qty body image"!important;
    align-items:center!important;
    gap:18px!important;
}

.rhpc-product__image{
    grid-area:image!important;
    width:105px!important;
    height:105px!important;
}

.rhpc-product__body{
    grid-area:body!important;
    min-height:105px!important;
    justify-content:center!important;
}

.rhpc-product__bottom{
    position:absolute!important;
    left:5px;
    top:50%;
    transform:translateY(-50%);
    margin:0!important;
}

.rhpc-product{
    position:relative!important;
}

.rhpc-qty-vertical{
    position:relative!important;
    display:grid!important;
    grid-area:qty!important;
    width:40px!important;
    height:105px!important;
    grid-template-columns:40px!important;
    grid-template-rows:35px 35px 35px!important;
}

.rhpc-prices{
    position:absolute!important;
    right:150px;
    bottom:10px;
}

.rhpc-saving-box{
    margin-top:6px!important;
    font-size:13px!important;
    padding:4px 12px!important;
}

@media(max-width:700px){
 .rhpc-product{
   grid-template-columns:45px minmax(0,1fr) 78px!important;
 }
}


/* v1.2.4 layout correction - cart page only */
.rhpc-product{
    display:grid!important;
    grid-template-columns:45px minmax(0,1fr) 110px!important;
    direction:ltr;
    align-items:center!important;
    gap:20px!important;
}
.rhpc-product__image{grid-column:3!important;grid-row:1!important;width:110px!important;height:110px!important;}
.rhpc-product__body{grid-column:2!important;grid-row:1!important;display:block!important;}
.rhpc-product__top{display:block!important;}
.rhpc-product__bottom{display:flex!important;flex-direction:column!important;align-items:flex-start!important;gap:10px!important;margin-top:10px!important;}
.rhpc-prices{order:2!important;margin-top:0!important;}
.rhpc-saving-box{order:1!important;}
.rhpc-qty-vertical{grid-column:1!important;grid-row:1!important;align-self:center!important;}
@media(max-width:700px){
.rhpc-product{grid-template-columns:36px minmax(0,1fr) 78px!important;gap:10px!important;}
.rhpc-product__image{width:78px!important;height:78px!important;}
}


.rhpc-product__info,
.rhpc-product__info h2,
.rhpc-product__info .variation{
    text-align:right!important;
}

.rhpc-saving-box{
    justify-content:flex-start!important;
    direction:rtl!important;
}

.rhpc-prices{
    direction:rtl!important;
    justify-content:flex-start!important;
    align-items:center!important;
    display:flex!important;
    gap:12px!important;
    text-align:right!important;
}

.rhpc-regular-price{
    color:#cda6e8!important;
    font-size:13px!important;
    text-decoration:none!important;
    font-weight:700!important;
}

.rhpc-sale-price{
    color:var(--rhpc-primary-dark)!important;
    font-size:17px!important;
    font-weight:900!important;
}


/* v1.2.6 price position and style fix */
.rhpc-product__info{
    text-align:right!important;
}
.rhpc-product__info h2{
    text-align:right!important;
}
.rhpc-product__bottom{
    display:block!important;
    margin-top:10px!important;
}
.rhpc-prices{
    display:flex!important;
    flex-direction:row-reverse!important;
    justify-content:flex-start!important;
    align-items:center!important;
    gap:10px!important;
    margin-top:8px!important;
    direction:rtl!important;
}
.rhpc-prices del{
    text-decoration:none!important;
    color:#c89ae8!important;
    font-size:14px!important;
    opacity:1!important;
}
.rhpc-prices strong{
    color:#6f2dbd!important;
    font-size:18px!important;
}
.rhpc-saving-box{
    text-align:right!important;
}


/* v1.2.9 place prices below the purple saving box */
.rhpc-product__top,
.rhpc-product__info{
    width:100%!important;
}

.rhpc-product__body,
.rhpc-product__info{
    direction:rtl!important;
}

.rhpc-product__info .rhpc-prices{
    position:static!important;
    inset:auto!important;
    width:100%!important;
    margin:2px 0 8px!important;
    display:flex!important;
    flex-direction:row!important;
    justify-content:flex-start!important;
    align-items:center!important;
    gap:10px!important;
    direction:rtl!important;
    text-align:right!important;
}

.rhpc-product__bottom .rhpc-prices{
    display:none!important;
}


/* v1.3.2 mobile cart redesign */
@media (max-width:700px){
    body.rhpc-cart-active{
        padding-bottom:88px!important;
    }

    .rhpc-wrap{
        max-width:560px!important;
        margin:10px auto 30px!important;
        padding:0 10px 20px!important;
    }

    .rhpc-progress{
        display:none!important;
    }

    .rhpc-layout{
        display:block!important;
    }

    .rhpc-main{
        padding:0!important;
        background:transparent!important;
        border:0!important;
        border-radius:0!important;
        box-shadow:none!important;
    }

    .rhpc-heading{
        display:flex!important;
        align-items:center!important;
        justify-content:space-between!important;
        gap:10px!important;
        margin:0 0 12px!important;
        padding:15px 14px!important;
        background:#fff!important;
        border:1px solid var(--rhpc-border)!important;
        border-radius:17px!important;
        box-shadow:0 8px 24px rgba(74,30,116,.06)!important;
    }

    .rhpc-heading h1{
        margin:0!important;
        font-size:18px!important;
        line-height:1.7!important;
        white-space:nowrap!important;
    }

    .rhpc-heading__count{
        font-size:14px!important;
    }

    .rhpc-clear{
        flex:0 0 auto!important;
        padding:7px 10px!important;
        font-size:11px!important;
        border-radius:9px!important;
    }

    .rhpc-summary{
        width:100%!important;
        margin:0 0 12px!important;
        position:static!important;
    }

    .rhpc-summary__card{
        display:flex!important;
        flex-direction:column!important;
        padding:16px!important;
        border-radius:17px!important;
        box-shadow:0 8px 24px rgba(74,30,116,.06)!important;
    }

    .rhpc-summary__card h2{
        order:1!important;
        margin:0 0 12px!important;
        font-size:18px!important;
    }

    .rhpc-benefit{
        order:2!important;
        margin:0 0 10px!important;
        padding:11px!important;
        border-radius:13px!important;
    }

    .rhpc-summary__row{
        order:3!important;
        padding:10px 0!important;
        border-bottom:1px solid var(--rhpc-border)!important;
        font-size:12px!important;
    }

    .rhpc-summary__row strong{
        font-size:13px!important;
    }

    .rhpc-summary__saving strong{
        color:var(--rhpc-success)!important;
    }

    .rhpc-coupon{
        order:4!important;
        margin:8px 0 0!important;
    }

    .rhpc-total{
        order:5!important;
        margin:14px 0 5px!important;
    }

    .rhpc-total strong{
        font-size:17px!important;
    }

    .rhpc-checkout{
        order:6!important;
        position:fixed!important;
        right:0!important;
        left:0!important;
        bottom:70px!important;
        z-index:9999!important;
        width:auto!important;
        max-width:540px!important;
        min-height:45px!important;
        margin:0 auto!important;
        border-radius:5px!important;
        box-shadow:0 12px 30px rgba(111,45,189,.34)!important;
    }

    .rhpc-trust{
        order:7!important;
        margin-top:10px!important;
    }

    .rhpc-cart-form{
        margin:0!important;
    }

    .rhpc-products{
        display:flex!important;
        flex-direction:column!important;
        gap:10px!important;
    }

    .rhpc-product{
        display:grid!important;
        grid-template-columns:38px minmax(0,1fr) 82px!important;
        grid-template-areas:"qty body image"!important;
        align-items:center!important;
        gap:10px!important;
        min-height:116px!important;
        margin:0!important;
        padding:14px 12px!important;
        direction:ltr!important;
        background:#fff!important;
        border:1px solid var(--rhpc-border)!important;
        border-radius:16px!important;
        box-shadow:0 7px 20px rgba(74,30,116,.045)!important;
    }

    .rhpc-product:last-child{
        border-bottom:1px solid var(--rhpc-border)!important;
    }

    .rhpc-product__image{
        grid-area:image!important;
        grid-column:auto!important;
        grid-row:auto!important;
        width:82px!important;
        height:82px!important;
        border-radius:13px!important;
    }

    .rhpc-product__body{
        grid-area:body!important;
        grid-column:auto!important;
        grid-row:auto!important;
        display:block!important;
        min-width:0!important;
        min-height:0!important;
        direction:rtl!important;
    }

    .rhpc-product__top,
    .rhpc-product__info{
        display:block!important;
        width:100%!important;
        min-width:0!important;
    }

    .rhpc-product__info h2{
        margin:0 0 7px!important;
        font-size:12.5px!important;
        line-height:1.65!important;
        display:-webkit-box!important;
        -webkit-line-clamp:2!important;
        -webkit-box-orient:vertical!important;
        overflow:hidden!important;
    }

    .rhpc-product__info .variation{
        font-size:10px!important;
        margin:3px 0!important;
    }

    .rhpc-saving-box{
        display:flex!important;
        width:100%!important;
        max-width:100%!important;
        justify-content:center!important;
        flex-wrap:wrap!important;
        gap:4px!important;
        margin:5px 0 0!important;
        padding:5px 7px!important;
        border-radius:10px!important;
        font-size:9.5px!important;
        line-height:1.5!important;
        text-align:center!important;
    }

    .rhpc-saving-box span{
        white-space:normal!important;
    }

    .rhpc-product__info .rhpc-prices{
        position:static!important;
        width:100%!important;
        margin:7px 0 0!important;
        display:flex!important;
        flex-direction:row!important;
        align-items:center!important;
        justify-content:flex-start!important;
        gap:7px!important;
        flex-wrap:wrap!important;
    }

    .rhpc-regular-price{
        font-size:10.5px!important;
    }

    .rhpc-sale-price,
    .rhpc-prices strong{
        font-size:14px!important;
    }

    .rhpc-product__bottom{
        position:static!important;
        grid-area:qty!important;
        grid-column:auto!important;
        grid-row:auto!important;
        display:block!important;
        margin:0!important;
        transform:none!important;
        align-self:center!important;
    }

    .rhpc-qty-vertical{
        position:static!important;
        grid-area:auto!important;
        display:grid!important;
        width:38px!important;
        height:99px!important;
        grid-template-columns:38px!important;
        grid-template-rows:33px 33px 33px!important;
        border-radius:11px!important;
    }

    .rhpc-qty-vertical .rhpc-qty__input{
        width:38px!important;
        min-width:38px!important;
        height:33px!important;
        font-size:12px!important;
    }

    .rhpc-qty-vertical .rhpc-qty__btn{
        width:38px!important;
        min-height:33px!important;
        font-size:18px!important;
    }

    .rhpc-back{
        display:none!important;
    }
}


/* v1.3.3 mobile product-card structural fix */
@media (max-width:700px){
    .rhpc-product{
        grid-template-columns:38px minmax(0,1fr) 82px!important;
        grid-template-areas:"qty info image"!important;
        align-items:center!important;
        column-gap:10px!important;
        row-gap:0!important;
        min-height:0!important;
        padding:14px 12px!important;
        overflow:hidden!important;
    }

    /* Let top and bottom participate directly in the product grid. */
    .rhpc-product__body{
        display:contents!important;
    }

    .rhpc-product__top{
        grid-area:info!important;
        display:block!important;
        min-width:0!important;
        width:100%!important;
        align-self:center!important;
    }

    .rhpc-product__bottom{
        grid-area:qty!important;
        position:static!important;
        display:block!important;
        width:38px!important;
        margin:0!important;
        transform:none!important;
        align-self:center!important;
    }

    .rhpc-product__image{
        grid-area:image!important;
        align-self:center!important;
    }

    .rhpc-product__info{
        width:100%!important;
        min-width:0!important;
    }

    .rhpc-product__info h2{
        margin:0 0 8px!important;
        font-size:13px!important;
        line-height:1.7!important;
        text-align:right!important;
    }

    .rhpc-saving-box{
        width:auto!important;
        max-width:100%!important;
        display:flex!important;
        justify-content:flex-start!important;
        align-items:center!important;
        flex-wrap:wrap!important;
        gap:4px 6px!important;
        margin:6px 0 0!important;
        padding:6px 8px!important;
        font-size:9.5px!important;
        line-height:1.55!important;
        border-radius:10px!important;
        text-align:right!important;
        box-sizing:border-box!important;
    }

    .rhpc-saving-box span{
        white-space:nowrap!important;
    }

    .rhpc-product__info .rhpc-prices{
        width:100%!important;
        margin:8px 0 0!important;
        display:flex!important;
        flex-direction:row!important;
        justify-content:flex-start!important;
        align-items:center!important;
        flex-wrap:wrap!important;
        gap:5px 8px!important;
        line-height:1.5!important;
    }

    .rhpc-regular-price{
        font-size:10.5px!important;
    }

    .rhpc-sale-price,
    .rhpc-prices strong{
        font-size:14px!important;
    }

    .rhpc-qty-vertical{
        position:static!important;
        width:38px!important;
        height:99px!important;
        grid-template-columns:38px!important;
        grid-template-rows:33px 33px 33px!important;
        margin:0!important;
    }
}

@media (max-width:390px){
    .rhpc-product{
        grid-template-columns:36px minmax(0,1fr) 72px!important;
        column-gap:8px!important;
        padding:12px 10px!important;
    }

    .rhpc-product__image{
        width:72px!important;
        height:72px!important;
    }

    .rhpc-product__bottom,
    .rhpc-qty-vertical,
    .rhpc-qty-vertical .rhpc-qty__input,
    .rhpc-qty-vertical .rhpc-qty__btn{
        width:36px!important;
        min-width:36px!important;
    }

    .rhpc-product__info h2{
        font-size:12px!important;
    }

    .rhpc-saving-box{
        font-size:8.8px!important;
        padding:5px 6px!important;
        gap:3px 5px!important;
    }

    .rhpc-sale-price,
    .rhpc-prices strong{
        font-size:13px!important;
    }
}
CSS;
        $settings = self::settings();
        return strtr($css, [
            '#2ACEBB' => $settings['primary'],
            '#159C8F' => $settings['primary_dark'],
            '#48D8C8' => $settings['accent'],
        ]);
    }

    private static function js() {
        return <<<'JS'
jQuery(function($){
    const $loading = $('.rhpc-loading');

    function showLoading(){
        $loading.addClass('is-active').attr('aria-hidden','false');
    }

    function hideLoading(){
        $loading.removeClass('is-active').attr('aria-hidden','true');
    }

    let qtyTimer = null;

    function updateQty($product, quantity){
        const cartKey = $product.data('cart-key');
        showLoading();

        $.post(RitaPurpleCart.ajaxUrl, {
            action: 'ritahost_cart_qty',
            nonce: RitaPurpleCart.nonce,
            cart_key: cartKey,
            quantity: quantity
        }).done(function(response){
            if (!response || !response.success) {
                alert((response && response.data && response.data.message) || RitaPurpleCart.i18n.error);
                location.reload();
                return;
            }

            if (response.data.empty) {
                location.reload();
                return;
            }

            $('[data-rhpc-subtotal]').html(response.data.subtotal);
            $('[data-rhpc-total]').html(response.data.total);
            $('[data-rhpc-savings]').html(response.data.savings);

            if (response.data.discount && $('[data-rhpc-discount]').length) {
                $('[data-rhpc-discount]').html(response.data.discount);
            }

            const qtyNow = parseInt($product.find('.rhpc-qty__input').val(),10) || 1;
            const $minus = $product.find('.rhpc-qty__minus');
            $minus.html(qtyNow === 1 ? '🗑' : '−');

            $(document.body).trigger('wc_fragment_refresh');
        }).fail(function(){
            alert(RitaPurpleCart.i18n.error);
            location.reload();
        }).always(function(){
            hideLoading();
        });
    }

    $(document).on('click', '.rhpc-qty__plus, .rhpc-qty__minus', function(){
        const $btn = $(this);
        const $product = $btn.closest('.rhpc-product');
        const $input = $product.find('.rhpc-qty__input');
        const current = parseInt($input.val(), 10) || 0;

        if ($btn.hasClass('rhpc-qty__minus') && current === 1) {
            updateQty($product, 0);
            return;
        }

        const max = parseInt($input.attr('max'), 10);
        let next = $btn.hasClass('rhpc-qty__plus') ? current + 1 : current - 1;

        if (!isNaN(max) && max > 0) {
            next = Math.min(next, max);
        }

        next = Math.max(next, 0);
        $input.val(next).trigger('change');
    });

    $(document).on('change', '.rhpc-qty__input', function(){
        const $input = $(this);
        const $product = $input.closest('.rhpc-product');
        let quantity = parseInt($input.val(), 10) || 0;
        quantity = Math.max(quantity, 0);
        $input.val(quantity);

        clearTimeout(qtyTimer);
        qtyTimer = setTimeout(function(){
            updateQty($product, quantity);
        }, 350);
    });

    $(document).on('click', '.rhpc-accordion-btn', function(){
        const $coupon = $(this).closest('.rhpc-coupon');
        $coupon.toggleClass('is-open');
        $(this).find('span:last').text($coupon.hasClass('is-open') ? '−' : '+');
    });

    $(document).on('click', '#rhpc_apply_coupon', function(){
        const code = ($('#rhpc_coupon_code').val() || '').trim();

        if (!code) {
            $('#rhpc_coupon_code').focus();
            return;
        }

        showLoading();

        $.post(
            wc_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_coupon'),
            {
                security: wc_cart_params.apply_coupon_nonce,
                coupon_code: code
            }
        ).done(function(response){
            $('.woocommerce-error, .woocommerce-message, .woocommerce-info').remove();
            $('.rhpc-wrap').before(response);
            setTimeout(function(){ location.reload(); }, 500);
        }).fail(function(){
            alert(RitaPurpleCart.i18n.error);
            hideLoading();
        });
    });



    const $summary = $('.rhpc-summary');
    const $layout  = $('.rhpc-layout');
    const $main    = $('.rhpc-main');

    function placeMobileSummary(){
        if (!$summary.length || !$layout.length || !$main.length) {
            return;
        }

        if (window.matchMedia('(max-width:700px)').matches) {
            if (!$summary.hasClass('is-mobile-placed')) {
                $summary.insertAfter($main.find('.rhpc-heading')).addClass('is-mobile-placed');
            }
        } else if ($summary.hasClass('is-mobile-placed')) {
            $summary.appendTo($layout).removeClass('is-mobile-placed');
        }
    }

    placeMobileSummary();
    $(window).on('resize.rhpcMobile', placeMobileSummary);

    $(document).on('click', '[data-rhpc-clear]', function(e){
        if (!confirm('همه محصولات از سبد خرید حذف شوند؟')) {
            e.preventDefault();
        }
    });
});
JS;
    }
}

RitaHost_Purple_WC_Cart::init();

if (function_exists('ritahost_register_admin_tool')) {
    ritahost_register_admin_tool('ritahost-cart-settings', 'سبد خرید ووکامرس', 'WooCommerce Cart', 'ظاهر و رفتار صفحه سبد خرید، جایگزینی خودکار و رنگ‌های اصلی را تنظیم می‌کند.', 'Configures the cart page appearance, automatic replacement, and primary colors.', 'manage_woocommerce');
}

/**
 * حذف کامل سبد خرید با درخواست POST و nonce اختصاصی.
 */
add_action('template_redirect', function () {
    if (
        isset($_SERVER['REQUEST_METHOD'], $_POST['rhpc_action']) &&
        'POST' === strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) &&
        'empty_cart' === sanitize_key(wp_unslash($_POST['rhpc_action'])) &&
        function_exists('WC') &&
        WC()->cart
    ) {
        check_admin_referer('rhpc_empty_cart', 'rhpc_nonce');
        WC()->cart->empty_cart();
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }
});

