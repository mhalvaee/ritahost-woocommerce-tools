<?php
/**
 * Plugin Name: RitaHost WooCommerce Account Dashboard
 * Description: Replaces the WooCommerce My Account dashboard with configurable cards, order summaries, and account tools.
 * Version: 3.3.1
 * Author: RitaHost
 * Text Domain: ritahost
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;

function rk_text($fa, $en) {
    return strpos(determine_locale(), 'fa') === 0 ? $fa : $en;
}

if (defined('RITAHOST_CUSTOM_DASHBOARD_MU_LOADED')) {
    return;
}
define('RITAHOST_CUSTOM_DASHBOARD_MU_LOADED', '3.3.1');

/*
|--------------------------------------------------------------------------
| تنظیمات سریع برند، رنگ‌ها و کش
|--------------------------------------------------------------------------
| برای تحویل به مشتری جدید، فقط رنگ‌های این بخش را تغییر بده.
| همه CSSهای اصلی پایین فایل از همین ثابت‌ها خوانده می‌شوند.
*/
if (!defined('RK_BRAND_NAME')) define('RK_BRAND_NAME', 'RitaHost');

if (!defined('RK_COLOR_PRIMARY')) define('RK_COLOR_PRIMARY', '#6c1faf');
if (!defined('RK_COLOR_PRIMARY_SOFT')) define('RK_COLOR_PRIMARY_SOFT', '#f4ecff');
if (!defined('RK_COLOR_BACKGROUND')) define('RK_COLOR_BACKGROUND', '#f7f7fb');
if (!defined('RK_COLOR_CARD')) define('RK_COLOR_CARD', '#ffffff');
if (!defined('RK_COLOR_BORDER')) define('RK_COLOR_BORDER', '#ece7f3');
if (!defined('RK_COLOR_TEXT')) define('RK_COLOR_TEXT', '#1f1728');
if (!defined('RK_COLOR_MUTED')) define('RK_COLOR_MUTED', '#81758b');
if (!defined('RK_COLOR_DANGER')) define('RK_COLOR_DANGER', '#e53648');
if (!defined('RK_COLOR_SUCCESS')) define('RK_COLOR_SUCCESS', '#19c878');
if (!defined('RK_COLOR_SUCCESS_DARK')) define('RK_COLOR_SUCCESS_DARK', '#16834b');
if (!defined('RK_COLOR_SUCCESS_SOFT')) define('RK_COLOR_SUCCESS_SOFT', '#f1fff7');
if (!defined('RK_COLOR_SUCCESS_BORDER')) define('RK_COLOR_SUCCESS_BORDER', '#bde9cf');

if (!defined('RK_SHADOW')) define('RK_SHADOW', '0 14px 42px rgba(35,18,52,.055)');
if (!defined('RK_RADIUS')) define('RK_RADIUS', '18px');

/* کش سفارش‌ها و آمار: پیش‌فرض ۱۰ دقیقه */
if (!defined('RK_CACHE_TTL')) define('RK_CACHE_TTL', 10 * MINUTE_IN_SECONDS);
if (!defined('RK_ORDERS_PAGE_LIMIT')) define('RK_ORDERS_PAGE_LIMIT', 20);
if (!defined('RK_PHONE_ORDER_LOOKUP_LIMIT')) define('RK_PHONE_ORDER_LOOKUP_LIMIT', 30);


if (!defined('RK_SETTINGS_OPTION')) define('RK_SETTINGS_OPTION', 'ritahost_custom_dashboard_settings');

function rk_dashboard_default_settings() {
    return [
        'brand_name'            => RK_BRAND_NAME,
        'color_primary'         => RK_COLOR_PRIMARY,
        'color_soft'            => RK_COLOR_PRIMARY_SOFT,
        'color_background'      => RK_COLOR_BACKGROUND,
        'color_card'            => RK_COLOR_CARD,
        'color_border'          => RK_COLOR_BORDER,
        'color_text'            => RK_COLOR_TEXT,
        'color_muted'           => RK_COLOR_MUTED,
        'color_danger'          => RK_COLOR_DANGER,
        'color_success'         => RK_COLOR_SUCCESS,
        'color_success_dark'    => RK_COLOR_SUCCESS_DARK,
        'color_success_soft'    => RK_COLOR_SUCCESS_SOFT,
        'color_success_border'  => RK_COLOR_SUCCESS_BORDER,
        'shadow'                => RK_SHADOW,
        'radius'                => RK_RADIUS,
        'font_title'            => '18px',
        'font_text'             => '14px',
        'font_small'            => '12px',
        'font_button'           => '13px',
        'icon_size'             => '27px',
        'card_padding'          => '24px',
        'content_max_width'     => '1200px',
        'sidebar_width'         => '30%',
        'cache_ttl_minutes'     => '10',
        'orders_page_limit'     => (string) RK_ORDERS_PAGE_LIMIT,
        'phone_lookup_limit'    => (string) RK_PHONE_ORDER_LOOKUP_LIMIT,
    ];
}

function rk_dashboard_settings() {
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    $saved = get_option(RK_SETTINGS_OPTION, []);
    if (!is_array($saved)) {
        $saved = [];
    }

    $settings = wp_parse_args($saved, rk_dashboard_default_settings());
    return $settings;
}

function rk_setting($key, $fallback = '') {
    $settings = rk_dashboard_settings();
    return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $fallback;
}

function rk_cache_ttl() {
    $minutes = absint(rk_setting('cache_ttl_minutes', 10));
    return max(1, $minutes) * MINUTE_IN_SECONDS;
}

function rk_orders_page_limit() {
    return max(1, absint(rk_setting('orders_page_limit', RK_ORDERS_PAGE_LIMIT)));
}

function rk_phone_lookup_limit() {
    return max(1, absint(rk_setting('phone_lookup_limit', RK_PHONE_ORDER_LOOKUP_LIMIT)));
}

function rk_sanitize_css_dimension($value, $fallback) {
    $value = trim((string) $value);
    if (preg_match('/^\d+(\.\d+)?(px|rem|em|%|vw|vh)$/', $value)) {
        return $value;
    }
    return $fallback;
}

function rk_sanitize_dashboard_settings($input) {
    $defaults = rk_dashboard_default_settings();
    $input = is_array($input) ? $input : [];
    $output = [];

    $output['brand_name'] = sanitize_text_field($input['brand_name'] ?? $defaults['brand_name']);

    $color_keys = [
        'color_primary', 'color_soft', 'color_background', 'color_card', 'color_border',
        'color_text', 'color_muted', 'color_danger', 'color_success', 'color_success_dark',
        'color_success_soft', 'color_success_border'
    ];

    foreach ($color_keys as $key) {
        $color = sanitize_hex_color($input[$key] ?? '');
        $output[$key] = $color ?: $defaults[$key];
    }

    $dimension_keys = ['radius', 'font_title', 'font_text', 'font_small', 'font_button', 'icon_size', 'card_padding', 'content_max_width', 'sidebar_width'];
    foreach ($dimension_keys as $key) {
        $output[$key] = rk_sanitize_css_dimension($input[$key] ?? $defaults[$key], $defaults[$key]);
    }

    $output['shadow'] = sanitize_text_field($input['shadow'] ?? $defaults['shadow']);
    $output['cache_ttl_minutes'] = (string) max(1, absint($input['cache_ttl_minutes'] ?? $defaults['cache_ttl_minutes']));
    $output['orders_page_limit'] = (string) max(1, absint($input['orders_page_limit'] ?? $defaults['orders_page_limit']));
    $output['phone_lookup_limit'] = (string) max(1, absint($input['phone_lookup_limit'] ?? $defaults['phone_lookup_limit']));

    return wp_parse_args($output, $defaults);
}

add_action('admin_menu', function () {
    $page_title = function_exists('ritahost_admin_text') ? ritahost_admin_text('تنظیمات داشبورد حساب کاربری', 'Account Dashboard Settings') : (is_rtl() ? 'تنظیمات داشبورد حساب کاربری' : 'Account Dashboard Settings');
    $menu_title = function_exists('ritahost_admin_text') ? ritahost_admin_text('داشبورد حساب کاربری', 'Account Dashboard') : (is_rtl() ? 'داشبورد حساب کاربری' : 'Account Dashboard');
    if (function_exists('ritahost_register_admin_tool')) {
        add_submenu_page('ritahost-panel', $page_title, $menu_title, 'manage_options', 'ritahost-custom-dashboard', 'rk_render_dashboard_settings_page');
    } else {
        add_menu_page($page_title, $menu_title, 'manage_options', 'ritahost-custom-dashboard', 'rk_render_dashboard_settings_page', 'dashicons-admin-customizer', 58);
    }
});

if (function_exists('ritahost_register_admin_tool')) {
    ritahost_register_admin_tool('ritahost-custom-dashboard', 'داشبورد حساب کاربری', 'Account Dashboard', 'ظاهر، کش و نحوه نمایش سفارش‌ها در حساب کاربری ووکامرس را مدیریت می‌کند.', 'Controls the WooCommerce account dashboard appearance, cache, and order presentation.', 'manage_options');
}

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['toplevel_page_ritahost-custom-dashboard', 'ritahost-panel_page_ritahost-custom-dashboard'], true)) {
        return;
    }

    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    wp_add_inline_script('wp-color-picker', 'jQuery(function($){ $(".rk-color-field").wpColorPicker(); });');
});

function rk_render_dashboard_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $defaults = rk_dashboard_default_settings();

    if (!empty($_POST['rk_dashboard_settings_action'])) {
        check_admin_referer('rk_dashboard_settings_save', 'rk_dashboard_settings_nonce');

        if ($_POST['rk_dashboard_settings_action'] === 'reset') {
            delete_option(RK_SETTINGS_OPTION);
            $message = 'تنظیمات به حالت پیش‌فرض برگشت.';
        } else {
            $new_settings = rk_sanitize_dashboard_settings($_POST['rk_dashboard_settings'] ?? []);
            update_option(RK_SETTINGS_OPTION, $new_settings, false);
            $message = 'تنظیمات ذخیره شد.';
        }

        delete_transient('rk_hpos_tables_available');
        echo '<div class="notice notice-success is-dismissible"><p>'.esc_html($message).'</p></div>';
    }

    $settings = rk_dashboard_settings();

    $color_fields = [
        'color_primary'        => 'رنگ اصلی',
        'color_soft'           => 'رنگ پس‌زمینه نرم',
        'color_background'     => 'رنگ پس‌زمینه صفحه',
        'color_card'           => 'رنگ کارت‌ها',
        'color_border'         => 'رنگ حاشیه‌ها',
        'color_text'           => 'رنگ متن اصلی',
        'color_muted'          => 'رنگ متن کم‌رنگ',
        'color_danger'         => 'رنگ خروج / خطا',
        'color_success'        => 'رنگ موفقیت / وضعیت سفارش',
        'color_success_dark'   => 'رنگ متن موفقیت',
        'color_success_soft'   => 'رنگ پس‌زمینه موفقیت',
        'color_success_border' => 'رنگ حاشیه موفقیت',
    ];

    $size_fields = [
        'radius'            => 'گردی گوشه‌ها',
        'font_title'        => 'سایز فونت عنوان‌ها',
        'font_text'         => 'سایز فونت متن‌ها',
        'font_small'        => 'سایز فونت ریزمتن‌ها',
        'font_button'       => 'سایز فونت دکمه‌ها',
        'icon_size'         => 'سایز آیکن عنوان کارت‌ها',
        'card_padding'      => 'فاصله داخلی کارت‌ها',
        'content_max_width' => 'حداکثر عرض پنل',
        'sidebar_width'     => 'عرض سایدبار دسکتاپ',
    ];

    ?>
    <div class="wrap rk-settings-wrap" dir="rtl">
        <h1>داشبورد سفارشی ریتاهاست</h1>
        <p>از این بخش می‌توانید رنگ‌ها، سایز فونت‌ها، فاصله‌ها و تنظیمات سرعت داشبورد حساب کاربری را بدون ویرایش کد تغییر دهید.</p>

        <form method="post">
            <?php wp_nonce_field('rk_dashboard_settings_save', 'rk_dashboard_settings_nonce'); ?>
            <input type="hidden" name="rk_dashboard_settings_action" value="save">

            <h2>هویت و رنگ‌ها</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="rk_brand_name">نام برند</label></th>
                    <td><input type="text" id="rk_brand_name" name="rk_dashboard_settings[brand_name]" value="<?php echo esc_attr($settings['brand_name']); ?>" class="regular-text"></td>
                </tr>
                <?php foreach ($color_fields as $key => $label): ?>
                    <tr>
                        <th scope="row"><label for="rk_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td><input type="text" id="rk_<?php echo esc_attr($key); ?>" name="rk_dashboard_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($settings[$key]); ?>" class="rk-color-field" data-default-color="<?php echo esc_attr($defaults[$key]); ?>"></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <h2>سایزها و ظاهر</h2>
            <table class="form-table" role="presentation">
                <?php foreach ($size_fields as $key => $label): ?>
                    <tr>
                        <th scope="row"><label for="rk_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td>
                            <input type="text" id="rk_<?php echo esc_attr($key); ?>" name="rk_dashboard_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($settings[$key]); ?>" class="regular-text ltr">
                            <p class="description">مثال: 14px، 1rem، 30%</p>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th scope="row"><label for="rk_shadow">سایه کارت‌ها</label></th>
                    <td><input type="text" id="rk_shadow" name="rk_dashboard_settings[shadow]" value="<?php echo esc_attr($settings['shadow']); ?>" class="large-text ltr"></td>
                </tr>
            </table>

            <h2>سرعت و کش</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="rk_cache_ttl_minutes">زمان کش سفارش‌ها و آمار</label></th>
                    <td><input type="number" min="1" id="rk_cache_ttl_minutes" name="rk_dashboard_settings[cache_ttl_minutes]" value="<?php echo esc_attr($settings['cache_ttl_minutes']); ?>"> دقیقه</td>
                </tr>
                <tr>
                    <th scope="row"><label for="rk_orders_page_limit">تعداد سفارش در صفحه سفارش‌ها</label></th>
                    <td><input type="number" min="1" id="rk_orders_page_limit" name="rk_dashboard_settings[orders_page_limit]" value="<?php echo esc_attr($settings['orders_page_limit']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="rk_phone_lookup_limit">حداکثر جستجوی سفارش با موبایل</label></th>
                    <td><input type="number" min="1" id="rk_phone_lookup_limit" name="rk_dashboard_settings[phone_lookup_limit]" value="<?php echo esc_attr($settings['phone_lookup_limit']); ?>"></td>
                </tr>
            </table>

            <?php submit_button('ذخیره تنظیمات'); ?>
        </form>

        <form method="post" style="margin-top:12px;">
            <?php wp_nonce_field('rk_dashboard_settings_save', 'rk_dashboard_settings_nonce'); ?>
            <input type="hidden" name="rk_dashboard_settings_action" value="reset">
            <?php submit_button('بازگشت به پیش‌فرض', 'secondary'); ?>
        </form>
    </div>
    <style>
        .rk-settings-wrap .form-table th{width:230px;text-align:right}.rk-settings-wrap input.ltr{direction:ltr;text-align:left}.rk-settings-wrap h2{margin-top:28px}
    </style>
    <?php
}


/*
Upload as:
wp-content/mu-plugins/ritahost-custom-dashboard.php

After upload:
Dashboard > Settings > Permalinks > Save Changes
*/

if (!defined('RK_WISHLIST_SHORTCODE')) {
    define('RK_WISHLIST_SHORTCODE', '[ritahost_wishlist_products]');
}

add_action('init', function () {
    foreach (['wishlist', 'notifications', 'reviews', 'account-settings', 'next-purchase'] as $endpoint) {
        add_rewrite_endpoint($endpoint, EP_ROOT | EP_PAGES);
    }
});

add_filter('woocommerce_account_menu_items', function () {
    $next_count = 0;

    if (is_user_logged_in()) {
        $next_list = rk_get_next_purchase_list(get_current_user_id());
        $next_count = is_array($next_list) ? count($next_list) : 0;
    }

    $next_label = rk_text('لیست خرید بعدی', 'Buy later') . ($next_count > 0 ? ' (' . number_format_i18n($next_count) . ')' : '');

    return [
        'dashboard'        => rk_text('داشبورد', 'Dashboard'),
        'edit-account'     => rk_text('اطلاعات کاربری', 'Account details'),
        'orders'           => rk_text('سفارش‌های من', 'My orders'),
        'next-purchase'    => $next_label,
        'edit-address'     => rk_text('آدرس‌های من', 'My addresses'),
        'wishlist'         => rk_text('علاقه‌مندی‌های من', 'My wishlist'),
        'notifications'    => rk_text('پیام‌ها و اعلان‌ها', 'Notifications'),
        'reviews'          => rk_text('نقد و بررسی‌های من', 'My reviews'),
        'customer-logout'  => rk_text('خروج از حساب', 'Log out'),
    ];
}, 999);

add_action('wp', function () {
    if (function_exists('is_account_page') && is_account_page()) {
        /*
         * فقط داشبورد اختصاصی ریتاهاست نمایش داده شود.
         * این خط خروجی‌های پیش‌فرض ووکامرس یا کدهای قبلی متصل به dashboard را حذف می‌کند
         * تا جدول‌ها و وضعیت سفارش تکراری نمایش داده نشوند.
         */
        remove_all_actions('woocommerce_account_dashboard');
        add_action('woocommerce_account_dashboard', 'rk_render_account_dashboard', 5);
    }
}, 999);

function rk_icon($name) {
    $icons = [
        'home' => '<svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-7h6v7"/></svg>',
        'user' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.8-5 14.2-5 16 0"/></svg>',
        'bag' => '<svg viewBox="0 0 24 24"><path d="M6 8h12l1 13H5L6 8Z"/><path d="M9 8a3 3 0 0 1 6 0"/></svg>',
        'next' => '<svg viewBox="0 0 24 24"><path d="M7 7h10v10H7z"/><path d="M9 3h6"/><path d="M9 21h6"/><path d="M17 12h4l-2-2"/><path d="M21 12l-2 2"/></svg>',
        'pin' => '<svg viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>',
        'heart' => '<svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 1 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>',
        'bell' => '<svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4"/></svg>',
        'star' => '<svg viewBox="0 0 24 24"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3 6.4 20.2 7.5 14 3 9.6l6.2-.9L12 3Z"/></svg>',
        'gear' => '<svg viewBox="0 0 24 24"><path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2 3.4-.2-.1a1.8 1.8 0 0 0-2 .1 7 7 0 0 1-1.5.8 1.8 1.8 0 0 0-1.1 1.6v.2H9v-.2a1.8 1.8 0 0 0-1.1-1.6 7 7 0 0 1-1.5-.8 1.8 1.8 0 0 0-2-.1l-.2.1-2-3.4.1-.1a1.7 1.7 0 0 0 .3-1.9 7 7 0 0 1 0-1.8 1.7 1.7 0 0 0-.3-1.9l-.1-.1 2-3.4.2.1a1.8 1.8 0 0 0 2-.1c.5-.3 1-.6 1.5-.8A1.8 1.8 0 0 0 9 5.4v-.2h4v.2a1.8 1.8 0 0 0 1.1 1.6c.5.2 1 .5 1.5.8a1.8 1.8 0 0 0 2 .1l.2-.1 2 3.4-.1.1a1.7 1.7 0 0 0-.3 1.9 7 7 0 0 1 0 1.8Z"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M12 3h8v18h-8"/></svg>',
        'tag' => '<svg viewBox="0 0 24 24"><path d="M20 13 13 20 4 11V4h7l9 9Z"/><circle cx="8" cy="8" r="1"/></svg>',
        'zap' => '<svg viewBox="0 0 24 24"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></svg>',
        'check' => '<svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>',
        'edit' => '<svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function rk_render_account_dashboard() {
    $user_id = get_current_user_id();
    $user = wp_get_current_user();

    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name  = get_user_meta($user_id, 'last_name', true);
    $phone      = get_user_meta($user_id, 'billing_phone', true);
    $name = trim($first_name . ' ' . $last_name);
    if (!$name) $name = $user->display_name ?: $user->user_login;

    $stats = rk_get_stats($user_id);
    $last_order = rk_get_last_order_for_user($user_id);
    ?>

    <div class="rk-dashboard">

        <section class="rk-top-card">
            <div class="rk-profile-mini">
                <div class="rk-avatar-wrap">
                    <div class="rk-avatar"><?php echo rk_get_profile_avatar($user_id, 72, $name); ?></div>
                </div>
                <div class="rk-profile-name">
                    <h2><?php echo esc_html($name); ?></h2>
                    <p class="phonerita"><?php echo $phone ? esc_html(rk_mask_phone($phone)) : esc_html(rk_text('شماره موبایل ثبت نشده', 'No mobile number saved')); ?></p>
                    <span><?php echo esc_html(rk_text('تاریخ ثبت‌نام:', 'Member since:')); ?> <?php echo esc_html(date_i18n('Y/m/d', strtotime($user->user_registered))); ?></span>
                </div>
            </div>

            <div class="rk-stats">
                <div class="rk-stat">
                    <?php echo rk_icon('tag'); ?>
                    <small><?php echo esc_html(rk_text('سود از تخفیف‌ها', 'Discount savings')); ?></small>
                    <strong><?php echo wp_kses_post(wc_price($stats['discount'])); ?></strong>
                </div>
                <div class="rk-stat">
                    <?php echo rk_icon('bag'); ?>
                    <small><?php echo esc_html(rk_text('جمع کل خرید', 'Total spent')); ?></small>
                    <strong><?php echo wp_kses_post(wc_price($stats['spent'])); ?></strong>
                </div>
                <div class="rk-stat">
                    <?php echo rk_icon('bag'); ?>
                    <small><?php echo esc_html(rk_text('جمع سفارشات', 'Total orders')); ?></small>
                    <strong><?php echo esc_html(number_format_i18n($stats['count'])); ?> <?php echo esc_html(rk_text('سفارش', 'orders')); ?></strong>
                </div>
            </div>
        </section>

        <section class="rk-card">
            <div class="rk-card-title"><h3><?php echo esc_html(rk_text('اطلاعات و مشخصات کاربری', 'Account information')); ?></h3><?php echo rk_icon('user'); ?></div>
            <div class="rk-info-grid">
                <div><label><?php echo esc_html(rk_text('نام', 'First name')); ?></label><strong><?php echo esc_html($first_name ?: rk_text('ثبت نشده', 'Not provided')); ?></strong></div>
                <div><label><?php echo esc_html(rk_text('نام خانوادگی', 'Last name')); ?></label><strong><?php echo esc_html($last_name ?: rk_text('ثبت نشده', 'Not provided')); ?></strong></div>
                <div class="phonerita"><label><?php echo esc_html(rk_text('شماره موبایل', 'Mobile number')); ?></label><strong><?php echo esc_html($phone ? rk_mask_phone($phone) : rk_text('ثبت نشده', 'Not provided')); ?></strong></div>
                <div><label><?php echo esc_html(rk_text('ایمیل', 'Email')); ?></label><strong><?php echo esc_html($user->user_email ?: rk_text('ثبت نشده', 'Not provided')); ?></strong></div>
            </div>
            <a class="rk-btn rk-outline" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>"><?php echo esc_html(rk_text('ویرایش اطلاعات', 'Edit details')); ?></a>
        </section>

        <?php $addresses = rk_get_user_addresses($user_id); ?>
        <section class="rk-card rk-address-card">
            <div class="rk-card-title"><h3><?php echo esc_html(rk_text('آدرس‌ها', 'Addresses')); ?></h3><?php echo rk_icon('pin'); ?></div>

            <?php if (!empty($addresses)): ?>
                <div class="rk-address-list">
                    <?php foreach ($addresses as $address): ?>
                        <article class="rk-address-item">
                            <div class="rk-address-icon"><?php echo rk_icon('pin'); ?></div>
                            <div class="rk-address-content">
                                <div class="rk-address-heading">
                                    <strong><?php echo esc_html($address['title']); ?></strong>
                                    <?php if (!empty($address['edit_url'])): ?>
                                        <a class="rk-address-edit" href="<?php echo esc_url($address['edit_url']); ?>" aria-label="<?php echo esc_attr('ویرایش ' . $address['title']); ?>">
                                            <?php echo rk_icon('edit'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php foreach ($address['lines'] as $line): ?>
                                    <p class="rk-address-line rk-address-line-<?php echo esc_attr($line['type']); ?>">
                                        <span class="rk-address-line-icon"><?php echo rk_address_line_icon($line['type']); ?></span>
                                        <span class="rk-address-line-text"<?php echo $line['type'] === 'phone' ? ' dir="ltr"' : ''; ?>><?php echo esc_html($line['text']); ?></span>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <a class="rk-btn rk-outline" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>"><?php echo esc_html(rk_text('مدیریت آدرس‌ها', 'Manage addresses')); ?></a>
            <?php else: ?>
                <div class="rk-empty">
                    <div><?php echo rk_icon('pin'); ?></div>
                    <strong><?php echo esc_html(rk_text('هیچ آدرسی ثبت نشده است', 'No address has been saved')); ?></strong>
                    <p><?php echo esc_html(rk_text('برای خرید سریع‌تر، آدرس خود را ثبت کنید.', 'Save an address for a faster checkout.')); ?></p>
                    <a class="rk-btn rk-outline" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>"><?php echo esc_html(rk_text('افزودن آدرس جدید', 'Add a new address')); ?></a>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($last_order): ?>
            <section class="rk-card rk-last-order-card">
                <div class="rk-card-title"><h3>آخرین سفارش‌های شما</h3><?php echo rk_icon('bag'); ?></div>
                <div class="rk-order-table-wrap">
                    <table class="rk-order-table" aria-label="آخرین سفارش‌های شما">
                        <thead>
                            <tr>
                                <th>کد سفارش</th>
                                <th>تاریخ</th>
                                <th>وضعیت</th>
                                <th>مبلغ</th>
                                <th>جزئیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td data-title="کد سفارش"><strong>#<?php echo esc_html($last_order->get_order_number()); ?></strong></td>
                                <td data-title="تاریخ"><?php echo esc_html(wc_format_datetime($last_order->get_date_created(), 'Y/m/d')); ?></td>
                                <td data-title="وضعیت"><span class="rk-status-badge"><?php echo esc_html(wc_get_order_status_name($last_order->get_status())); ?></span></td>
                                <td data-title="مبلغ"><strong><?php echo wp_kses_post($last_order->get_formatted_order_total()); ?></strong></td>
                                <td data-title="جزئیات"><a class="rk-order-view-btn" href="<?php echo esc_url($last_order->get_view_order_url()); ?>">مشاهده</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <a class="rk-small-link" href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>">مشاهده تمام سفارش‌ها</a>
            </section>
        <?php endif; ?>

    </div>
    <?php
}

add_action('woocommerce_account_wishlist_endpoint', function () {
    echo '<section class="rk-card"><div class="rk-card-title"><h3>علاقه‌مندی‌های من</h3>'.rk_icon('heart').'</div><div class="rk-wishlist-box">';
    echo do_shortcode(RK_WISHLIST_SHORTCODE);
    echo '</div></section>';
});

add_action('woocommerce_account_notifications_endpoint', function () {
    $user_id = get_current_user_id();
    $missing = [];
    if (!get_user_meta($user_id, 'first_name', true)) $missing[] = 'نام';
    if (!get_user_meta($user_id, 'last_name', true)) $missing[] = 'نام خانوادگی';
    if (!get_user_meta($user_id, 'billing_phone', true)) $missing[] = 'شماره موبایل';

    echo '<section class="rk-card"><div class="rk-card-title"><h3>پیام‌ها و اعلان‌ها</h3>'.rk_icon('bell').'</div><div class="rk-notices">';
    echo '<div class="rk-notice"><strong>به حساب کاربری خوش آمدید</strong><p>پیام‌ها و وضعیت‌های مهم حساب کاربری شما اینجا نمایش داده می‌شود.</p></div>';
    if ($missing) {
        echo '<div class="rk-notice warning"><strong>پروفایل شما ناقص است</strong><p>موارد ناقص: '.esc_html(implode('، ', $missing)).'</p><a href="'.esc_url(wc_get_account_endpoint_url('edit-account')).'">تکمیل اطلاعات</a></div>';
    }
    echo '</div></section>';
});

add_action('woocommerce_account_reviews_endpoint', function () {
    $comments = get_comments(['user_id'=>get_current_user_id(),'type'=>'review','number'=>30,'status'=>'all']);
    echo '<section class="rk-card"><div class="rk-card-title"><h3>نقد و بررسی‌های من</h3>'.rk_icon('star').'</div>';
    if (!$comments) {
        echo '<div class="rk-empty"><div>'.rk_icon('star').'</div><strong>هنوز نقد و بررسی‌ای ثبت نکرده‌اید.</strong></div>';
    } else {
        echo '<div class="rk-review-list">';
        foreach ($comments as $c) {
            echo '<article><div class="rk-review-thumb">'.get_the_post_thumbnail($c->comment_post_ID,'thumbnail').'</div><div><h4>'.esc_html(get_the_title($c->comment_post_ID)).'</h4><p>'.esc_html(wp_trim_words($c->comment_content,24)).'</p><small>'.($c->comment_approved ? 'تأیید شده' : 'در انتظار بررسی').'</small> <a href="'.esc_url(get_permalink($c->comment_post_ID)).'">مشاهده محصول</a></div></article>';
        }
        echo '</div>';
    }
    echo '</section>';
});

add_action('woocommerce_account_account-settings_endpoint', function () {
    echo '<section class="rk-card"><div class="rk-card-title"><h3>تنظیمات حساب</h3>'.rk_icon('gear').'</div><div class="rk-settings">';
    echo '<a href="'.esc_url(wc_get_account_endpoint_url('edit-account')).'">'.rk_icon('user').'<span>ویرایش اطلاعات و رمز عبور</span></a>';
    echo '<a href="'.esc_url(wc_get_account_endpoint_url('edit-address')).'">'.rk_icon('pin').'<span>مدیریت آدرس‌ها</span></a>';
    echo '<a href="'.esc_url(wc_get_account_endpoint_url('notifications')).'">'.rk_icon('bell').'<span>پیام‌ها و اعلان‌ها</span></a>';
    echo '<a class="danger" href="'.esc_url(wc_logout_url()).'">'.rk_icon('logout').'<span>خروج از حساب</span></a>';
    echo '</div></section>';
});


function rk_get_user_address_data($user_id, $type = 'billing') {
    $type = $type === 'shipping' ? 'shipping' : 'billing';

    $fields = [
        'first_name',
        'last_name',
        'company',
        'city',
        'address_1',
        'address_2',
        'postcode',
        'phone',
    ];

    $data = [];

    foreach ($fields as $field) {
        $data[$field] = trim((string) get_user_meta($user_id, $type . '_' . $field, true));
    }

    $has_address = !empty($data['address_1']) || !empty($data['address_2']) || !empty($data['city']) || !empty($data['postcode']);

    if (!$has_address) {
        return null;
    }

    $lines = [];
    $full_name = trim($data['first_name'] . ' ' . $data['last_name']);
    $address_line = trim($data['address_1'] . ' ' . $data['address_2']);

    if ($full_name) {
        $lines[] = [
            'type' => 'user',
            'text' => $full_name,
        ];
    }

    if (!empty($data['company'])) {
        $lines[] = [
            'type' => 'company',
            'text' => $data['company'],
        ];
    }

    if ($address_line) {
        $lines[] = [
            'type' => 'location',
            'text' => $address_line,
        ];
    }

    if (!empty($data['city'])) {
        $lines[] = [
            'type' => 'city',
            'text' => $data['city'],
        ];
    }

    if (!empty($data['postcode'])) {
        $lines[] = [
            'type' => 'postcode',
            'text' => 'کد پستی: ' . $data['postcode'],
        ];
    }

    if (!empty($data['phone'])) {
        $lines[] = [
            'type' => 'phone',
            'text' => 'موبایل: ' . rk_mask_phone($data['phone']),
        ];
    }

    return [
        'type'     => $type,
        'title'    => $type === 'shipping' ? 'آدرس ارسال' : 'آدرس صورتحساب',
        'edit_url' => wc_get_endpoint_url('edit-address', $type, wc_get_page_permalink('myaccount')),
        'lines'    => $lines,
    ];
}

function rk_get_user_addresses($user_id) {
    $addresses = [];

    $billing = rk_get_user_address_data($user_id, 'billing');
    if ($billing) {
        $addresses[] = $billing;
    }

    $shipping = rk_get_user_address_data($user_id, 'shipping');
    if ($shipping) {
        $duplicate = false;
        $shipping_key = rk_address_compare_key($shipping);

        foreach ($addresses as $address) {
            if (rk_address_compare_key($address) === $shipping_key) {
                $duplicate = true;
                break;
            }
        }

        if (!$duplicate) {
            $addresses[] = $shipping;
        }
    }

    return $addresses;
}

function rk_address_compare_key($address) {
    if (empty($address['lines']) || !is_array($address['lines'])) {
        return '';
    }

    $texts = [];
    foreach ($address['lines'] as $line) {
        if (is_array($line) && isset($line['text'])) {
            $texts[] = trim((string) $line['text']);
        } else {
            $texts[] = trim((string) $line);
        }
    }

    return implode('|', array_filter($texts));
}

function rk_address_line_icon($type) {
    $icons = [
        'user' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c1.5-4 12.5-4 14 0"/></svg>',
        'company' => '<svg viewBox="0 0 24 24"><path d="M4 21V7l8-4 8 4v14"/><path d="M9 21v-6h6v6"/><path d="M8 10h.1M12 10h.1M16 10h.1"/></svg>',
        'location' => '<svg viewBox="0 0 24 24"><path d="M12 21s6-5.4 6-11a6 6 0 0 0-12 0c0 5.6 6 11 6 11Z"/><circle cx="12" cy="10" r="2"/></svg>',
        'city' => '<svg viewBox="0 0 24 24"><path d="M4 21V8l6-3v16"/><path d="M10 21V4l10 4v13"/><path d="M7 11h.1M7 15h.1M14 11h.1M17 11h.1M14 15h.1M17 15h.1"/></svg>',
        'postcode' => '<svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="M7 9h10M7 13h6"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24"><path d="M8 3h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M11 18h2"/></svg>',
    ];

    return $icons[$type] ?? $icons['location'];
}

function rk_get_profile_avatar($user_id, $size = 72, $alt = '') {
    $size = max(32, (int) $size);
    $label = esc_attr($alt ?: 'آواتار پیش‌فرض');

    return '<svg class="rk-avatar-img rk-default-avatar" role="img" aria-label="'.$label.'" width="'.$size.'" height="'.$size.'" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
        <circle cx="256" cy="256" r="256" fill="#f1f2f5"/>
        <circle cx="256" cy="180" r="78" fill="#64696c"/>
        <path d="M118 404c0-70 50-115 138-115s138 45 138 115v31c0 11-9 20-20 20H138c-11 0-20-9-20-20v-31Z" fill="#64696c"/>
    </svg>';
}

function rk_get_order_cache_version($user_id) {
    $user_id = absint($user_id);
    $version = (int) get_user_meta($user_id, '_rk_order_cache_version', true);

    if ($version <= 0) {
        $version = 1;
        update_user_meta($user_id, '_rk_order_cache_version', $version);
    }

    return $version;
}

function rk_bump_order_cache_version($user_id) {
    $user_id = absint($user_id);

    if (!$user_id) {
        return;
    }

    $version = rk_get_order_cache_version($user_id);
    update_user_meta($user_id, '_rk_order_cache_version', $version + 1);
}

function rk_clear_order_cache_for_order($order_id) {
    if (!function_exists('wc_get_order')) {
        return;
    }

    $order = wc_get_order($order_id);

    if (!$order instanceof WC_Order) {
        return;
    }

    $customer_id = (int) $order->get_customer_id();

    if ($customer_id) {
        rk_bump_order_cache_version($customer_id);
    }
}

add_action('woocommerce_new_order', 'rk_clear_order_cache_for_order', 10, 1);
add_action('woocommerce_update_order', 'rk_clear_order_cache_for_order', 10, 1);
add_action('woocommerce_order_status_changed', function ($order_id) {
    rk_clear_order_cache_for_order($order_id);
}, 10, 1);

function rk_get_stats($user_id) {
    $user_id = absint($user_id);
    $data = ['spent'=>0,'discount'=>0,'count'=>0];

    $orders = rk_get_user_orders_strict($user_id, -1);
    $data['count'] = count($orders);

    foreach ($orders as $order) {
        if (!is_a($order, 'WC_Order')) continue;
        $data['spent']    += (float) $order->get_total();
        $data['discount'] += (float) $order->get_discount_total();
    }

    return $data;
}

function rk_get_last_order_for_user($user_id) {
    $user_id = absint($user_id);
    $version = rk_get_order_cache_version($user_id);
    $email = rk_order_identity_email($user_id);
    $cache_key = 'rk_acc_last_order_v2_' . $user_id . '_' . $version . '_' . rk_order_identity_hash($user_id);

    $cached_order_id = get_transient($cache_key);

    if ($cached_order_id !== false) {
        $cached_order_id = absint($cached_order_id);
        $cached_order = $cached_order_id ? wc_get_order($cached_order_id) : null;
        return ($cached_order instanceof WC_Order && rk_order_belongs_to_user($cached_order, $user_id, $email))
            ? $cached_order
            : null;
    }

    /*
     * برای داشبورد فقط یک سفارش آخر لازم است؛ به همین دلیل کوئری محدود می‌شود.
     * کش این بخش ۱۰ دقیقه است.
     */
    $orders = rk_get_user_orders_strict($user_id, 1);
    $last_order = $orders[0] ?? null;

    set_transient($cache_key, ($last_order instanceof WC_Order) ? $last_order->get_id() : 0, rk_cache_ttl());

    return ($last_order instanceof WC_Order) ? $last_order : null;
}

function rk_get_user_orders_strict($user_id, $limit = -1) {
    $orders = [];
    $user_id = absint($user_id);
    $limit = (int) $limit;

    if (!function_exists('wc_get_orders') || !$user_id) {
        return $orders;
    }

    $email = rk_order_identity_email($user_id);
    $version = rk_get_order_cache_version($user_id);
    $runtime_key = 'v2_' . $user_id . '_' . $limit . '_' . $version . '_' . rk_order_identity_hash($user_id);

    $transient_key = 'rk_acc_orders_' . md5($runtime_key);
    $cached_ids = get_transient($transient_key);

    if (is_array($cached_ids)) {
        foreach ($cached_ids as $order_id) {
            $order = wc_get_order(absint($order_id));
            if ($order instanceof WC_Order && rk_order_belongs_to_user($order, $user_id, $email)) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    $statuses = array_keys(wc_get_order_statuses());
    $statuses = array_values(array_diff($statuses, ['wc-cancelled', 'wc-refunded', 'wc-failed', 'wc-checkout-draft']));

    $query_limit = $limit > 0 ? max($limit, 5) : -1;

    /*
     * 1) سفارش‌هایی که واقعاً به user_id متصل هستند.
     */
    rk_collect_orders_strict($orders, [
        'customer_id' => (int) $user_id,
        'status'      => $statuses,
        'limit'       => $query_limit,
        'orderby'     => 'date',
        'order'       => 'DESC',
        'return'      => 'objects',
    ], $user_id, $email);

    /*
     * 2) سفارش‌های مهمان یا سفارش‌هایی که فقط با ایمیل همین کاربر ثبت شده‌اند.
     */
    if ($email) {
        rk_collect_orders_strict($orders, [
            'billing_email' => $email,
            'status'        => $statuses,
            'limit'         => $query_limit,
            'orderby'       => 'date',
            'order'         => 'DESC',
            'return'        => 'objects',
    ], $user_id, $email);
    }

    $orders = array_values($orders);

    usort($orders, function($a, $b) {
        $a_date = $a instanceof WC_Order && $a->get_date_created() ? $a->get_date_created()->getTimestamp() : 0;
        $b_date = $b instanceof WC_Order && $b->get_date_created() ? $b->get_date_created()->getTimestamp() : 0;
        return $b_date <=> $a_date;
    });

    if ($limit > 0) {
        $orders = array_slice($orders, 0, $limit);
    }

    $order_ids = [];
    foreach ($orders as $order) {
        if ($order instanceof WC_Order) {
            $order_ids[] = $order->get_id();
        }
    }

    set_transient($transient_key, $order_ids, rk_cache_ttl());

    return $orders;
}

function rk_collect_orders_strict(&$orders, $args, $user_id, $email) {
    try {
        $found = wc_get_orders($args);
    } catch (Throwable $e) {
        return;
    }

    if (!$found) return;

    foreach ($found as $order) {
        if ($order instanceof WC_Order && rk_order_belongs_to_user($order, $user_id, $email)) {
            $orders[$order->get_id()] = $order;
        }
    }
}

function rk_order_belongs_to_user($order, $user_id, $email = '') {
    if (!$order instanceof WC_Order) {
        return false;
    }

    $owner_id = (int) $order->get_customer_id();

    if ($owner_id === (int) $user_id) {
        return true;
    }

    // A registered order can never be claimed by matching editable profile fields.
    if ($owner_id !== 0) {
        return false;
    }

    $billing_email = sanitize_email($order->get_billing_email());
    if ($email && $billing_email && strtolower($billing_email) === strtolower($email)) {
        return true;
    }

    return false;
}

function rk_order_identity_email($user_id) {
    $user = get_userdata(absint($user_id));
    return $user && !empty($user->user_email) ? sanitize_email($user->user_email) : '';
}

function rk_order_identity_hash($user_id) {
    return substr(hash('sha256', strtolower(rk_order_identity_email($user_id))), 0, 16);
}

function rk_hpos_order_tables_available() {
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    $cached = get_transient('rk_hpos_tables_available');

    if ($cached !== false) {
        $available = (bool) $cached;
        return $available;
    }

    global $wpdb;

    $orders_table = $wpdb->prefix . 'wc_orders';
    $addresses_table = $wpdb->prefix . 'wc_order_addresses';

    $orders_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $orders_table));
    $addresses_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $addresses_table));

    $available = (bool) ($orders_table_exists && $addresses_table_exists);
    set_transient('rk_hpos_tables_available', $available ? 1 : 0, DAY_IN_SECONDS);

    return $available;
}

function rk_find_order_ids_by_phone($phone_variations, $statuses, $limit = -1) {
    global $wpdb;

    $ids = [];
    $phone_variations = array_values(array_unique(array_filter((array) $phone_variations)));
    $limit = (int) $limit;

    if (!$phone_variations || !$wpdb) {
        return $ids;
    }

    $last10 = [];
    foreach ($phone_variations as $phone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) >= 10) {
            $last10[] = substr($digits, -10);
        }
    }
    $last10 = array_values(array_unique(array_filter($last10)));

    if (!$last10) {
        return $ids;
    }

    $lookup_limit = $limit > 0 ? max($limit, 10) : rk_phone_lookup_limit();
    $lookup_limit = max(1, min((int) $lookup_limit, 100));

    $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));

    /* حالت معمول ووکامرس با wp_posts / wp_postmeta */
    $like_clauses = [];
    $params = [];

    foreach ($last10 as $phone) {
        $like_clauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(pm.meta_value, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE %s";
        $params[] = '%' . $wpdb->esc_like($phone) . '%';
    }

    $sql = "
        SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'shop_order'
        AND p.post_status IN ($status_placeholders)
        AND pm.meta_key = '_billing_phone'
        AND (" . implode(' OR ', $like_clauses) . ")
        ORDER BY p.ID DESC
        LIMIT {$lookup_limit}
    ";

    $prepared = $wpdb->prepare($sql, array_merge($statuses, $params));
    $post_ids = $wpdb->get_col($prepared);

    if ($post_ids) {
        $ids = array_merge($ids, array_map('absint', $post_ids));
    }

    /* پشتیبانی ساده از HPOS در صورت فعال بودن جدول‌های جدید ووکامرس */
    if (rk_hpos_order_tables_available()) {
        $orders_table = $wpdb->prefix . 'wc_orders';
        $addresses_table = $wpdb->prefix . 'wc_order_addresses';

        $like_clauses = [];
        $params = [];

        foreach ($last10 as $phone) {
            $like_clauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(a.phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE %s";
            $params[] = '%' . $wpdb->esc_like($phone) . '%';
        }

        $sql = "
            SELECT DISTINCT o.id
            FROM {$orders_table} o
            INNER JOIN {$addresses_table} a ON o.id = a.order_id
            WHERE a.address_type = 'billing'
            AND o.status IN ($status_placeholders)
            AND (" . implode(' OR ', $like_clauses) . ")
            ORDER BY o.id DESC
            LIMIT {$lookup_limit}
        ";

        $prepared = $wpdb->prepare($sql, array_merge($statuses, $params));
        $hpos_ids = $wpdb->get_col($prepared);

        if ($hpos_ids) {
            $ids = array_merge($ids, array_map('absint', $hpos_ids));
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

function rk_phone_variations($phone_digits) {
    $phone_digits = preg_replace('/\D+/', '', (string) $phone_digits);
    if (!$phone_digits) return [];

    $variations = [$phone_digits];

    if (strlen($phone_digits) === 10 && substr($phone_digits, 0, 1) === '9') {
        $variations[] = '0' . $phone_digits;
        $variations[] = '98' . $phone_digits;
        $variations[] = '+98' . $phone_digits;
    }

    if (strlen($phone_digits) === 11 && substr($phone_digits, 0, 2) === '09') {
        $without_zero = substr($phone_digits, 1);
        $variations[] = $without_zero;
        $variations[] = '98' . $without_zero;
        $variations[] = '+98' . $without_zero;
    }

    if (strlen($phone_digits) === 12 && substr($phone_digits, 0, 2) === '98') {
        $without_98 = substr($phone_digits, 2);
        $variations[] = '0' . $without_98;
        $variations[] = $without_98;
        $variations[] = '+' . $phone_digits;
    }

    if (strlen($phone_digits) === 14 && substr($phone_digits, 0, 4) === '0098') {
        $without_0098 = substr($phone_digits, 4);
        $variations[] = '0' . $without_0098;
        $variations[] = $without_0098;
        $variations[] = '98' . $without_0098;
        $variations[] = '+98' . $without_0098;
    }

    if (strlen($phone_digits) >= 10) {
        $variations[] = substr($phone_digits, -10);
    }

    return array_values(array_unique(array_filter($variations)));
}

function rk_get_last_order($user_id) {
    return rk_get_last_order_for_user($user_id);
}

function rk_mask_phone($phone) {
    $phone = preg_replace('/\D/', '', (string)$phone);
    if (strlen($phone) < 8) return $phone;
    return substr($phone,0,4).'****'.substr($phone,-3);
}

function rk_step_active($current, $step) {
    $map = ['pending'=>1,'processing'=>2,'on-hold'=>3,'completed'=>4,'packed'=>5];
    return ($map[$step] ?? 0) <= ($map[$current] ?? 0);
}


/**
 * نمایش صفحه اصلی «آدرس‌های من» با کارت اختصاصی ریتاهاست.
 * نکته مهم: در مسیرهای edit-address/billing و edit-address/shipping خروجی پیش‌فرض ووکامرس حفظ می‌شود
 * تا فرم ویرایش آدرس بدون مشکل باز شود.
 */
function rk_is_address_index_page() {
    if (
        !function_exists('is_account_page') ||
        !is_account_page() ||
        !function_exists('is_wc_endpoint_url') ||
        !is_wc_endpoint_url('edit-address')
    ) {
        return false;
    }

    global $wp;

    $editing_address_type = '';
    if (!empty($wp->query_vars['edit-address'])) {
        $editing_address_type = sanitize_key($wp->query_vars['edit-address']);
    }

    return !in_array($editing_address_type, ['billing', 'shipping'], true);
}

add_action('wp', function () {
    if (!rk_is_address_index_page()) {
        return;
    }

    /*
     * WooCommerce در بعضی نسخه‌ها callback پیش‌فرض را به شکل static/class اضافه می‌کند،
     * بنابراین remove_action ساده همیشه جواب نمی‌دهد. برای جلوگیری از دو خروجی، همه اکشن‌های endpoint
     * فقط در صفحه اصلی آدرس‌ها پاک می‌شوند و خروجی اختصاصی خودمان جایگزین می‌شود.
     */
    remove_all_actions('woocommerce_account_edit-address_endpoint');
    add_action('woocommerce_account_edit-address_endpoint', 'rk_render_account_addresses_page', 5);
}, 10000);

add_filter('body_class', function ($classes) {
    if (rk_is_address_index_page()) {
        $classes[] = 'rk-account-address-index';
    }

    return $classes;
});

function rk_render_account_addresses_page() {
    $user_id = get_current_user_id();
    $addresses = rk_get_user_addresses($user_id);
    ?>
    <section class="rk-card rk-address-card rk-address-page-card">
        <div class="rk-card-title">
            <h3>آدرس‌های من</h3>
            <?php echo rk_icon('pin'); ?>
        </div>

        <?php if (!empty($addresses)): ?>
            <div class="rk-address-list">
                <?php foreach ($addresses as $address): ?>
                    <article class="rk-address-item rk-address-item-<?php echo esc_attr($address['type']); ?>">
                        <div class="rk-address-icon">
                            <?php echo rk_icon('pin'); ?>
                        </div>

                        <div class="rk-address-content">
                            <div class="rk-address-heading">
                                <strong><?php echo esc_html($address['title']); ?></strong>

                                <?php if (!empty($address['edit_url'])): ?>
                                    <a class="rk-address-edit" href="<?php echo esc_url($address['edit_url']); ?>" aria-label="<?php echo esc_attr('ویرایش ' . $address['title']); ?>">
                                        <?php echo rk_icon('edit'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <?php foreach ($address['lines'] as $line): ?>
                                <p class="rk-address-line rk-address-line-<?php echo esc_attr($line['type']); ?>">
                                    <span class="rk-address-line-icon">
                                        <?php echo rk_address_line_icon($line['type']); ?>
                                    </span>

                                    <span class="rk-address-line-text"<?php echo $line['type'] === 'phone' ? ' dir="ltr"' : ''; ?>>
                                        <?php echo esc_html($line['text']); ?>
                                    </span>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="rk-empty">
                <div><?php echo rk_icon('pin'); ?></div>
                <strong>هیچ آدرسی ثبت نشده است</strong>
                <p>برای خرید سریع‌تر، آدرس خود را ثبت کنید.</p>
                <div class="rk-address-actions">
                    <a class="rk-btn rk-outline" href="<?php echo esc_url(wc_get_endpoint_url('edit-address', 'billing', wc_get_page_permalink('myaccount'))); ?>">
                        افزودن آدرس صورتحساب
                    </a>
                    <a class="rk-btn rk-outline" href="<?php echo esc_url(wc_get_endpoint_url('edit-address', 'shipping', wc_get_page_permalink('myaccount'))); ?>">
                        افزودن آدرس ارسال
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php
}



/**
 * نمایش اختصاصی صفحه «سفارش‌های من» به‌صورت کارت سبک و قابل باز شدن.
 * دسکتاپ و موبایل از یک خروجی واحد استفاده می‌کنند تا جدول ووکامرس نامرتب نشود.
 */
function rk_is_orders_endpoint_page() {
    return (
        function_exists('is_account_page') &&
        is_account_page() &&
        function_exists('is_wc_endpoint_url') &&
        is_wc_endpoint_url('orders')
    );
}

add_action('wp', function () {
    if (!rk_is_orders_endpoint_page()) {
        return;
    }

    remove_all_actions('woocommerce_account_orders_endpoint');
    add_action('woocommerce_account_orders_endpoint', 'rk_render_account_orders_cards', 5);
}, 10000);

function rk_render_account_orders_cards($current_page = 1) {
    $user_id = get_current_user_id();

    if (!$user_id || !function_exists('wc_get_order')) {
        return;
    }

    $orders = function_exists('rk_get_user_orders_strict')
        ? rk_get_user_orders_strict($user_id, rk_orders_page_limit())
        : wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => rk_orders_page_limit(),
            'orderby'     => 'date',
            'order'       => 'DESC',
            'return'      => 'objects',
        ]);

    echo '<section class="rk-card rk-orders-card">';
    echo '<div class="rk-card-title"><h3>سفارش‌های من</h3>'.rk_icon('bag').'</div>';

    if (empty($orders)) {
        echo '<div class="rk-empty"><div>'.rk_icon('bag').'</div><strong>هنوز سفارشی ثبت نکرده‌اید.</strong></div>';
        echo '</section>';
        return;
    }

    echo '<div class="rk-orders-list">';

    foreach ($orders as $order) {
        if ($order instanceof WC_Order) {
            rk_render_order_card($order);
        }
    }

    echo '</div>';
    echo '</section>';
}

function rk_render_order_card($order) {
    if (!$order instanceof WC_Order) {
        return;
    }

    $order_id      = $order->get_id();
    $order_number  = $order->get_order_number();
    $order_date    = $order->get_date_created() ? wc_format_datetime($order->get_date_created(), 'Y/m/d') : '';
    $order_status  = wc_get_order_status_name($order->get_status());
    $tracking_code = rk_get_order_tracking_code($order);
    $items         = array_values($order->get_items('line_item'));
    $first_item    = $items[0] ?? null;
    $first_title   = $first_item ? $first_item->get_name() : 'بدون آیتم';
    $more_count    = max(0, count($items) - 1);
    $details_id    = 'rk-order-details-' . absint($order_id);

    echo '<article class="rk-order-card" id="rk-order-'.esc_attr($order_id).'">';

    echo '<div class="rk-order-card-head">';
    echo '<strong class="rk-order-card-number">#'.esc_html($order_number).'</strong>';
    echo '<span class="rk-order-card-date">'.esc_html($order_date).'</span>';
    echo '</div>';

    echo '<button type="button" class="rk-order-summary-trigger" data-rk-order-toggle="#'.esc_attr($details_id).'" aria-expanded="false">';
    echo '<span class="rk-order-label">آیتم:</span>';
    echo '<span class="rk-order-first-title">'.esc_html($first_title).'</span>';
    if ($more_count > 0) {
        echo '<small>+'.esc_html($more_count).' آیتم دیگر</small>';
    }
    echo '</button>';

    rk_render_order_thumbs($items);

    echo '<div class="rk-order-total-row">';
    echo '<span class="rk-order-label">مبلغ:</span>';
    echo '<strong class="rk-order-total-value">'.wp_kses_post($order->get_formatted_order_total()).'</strong>';
    echo '</div>';

    if ($tracking_code) {
        echo '<div class="rk-order-tracking-row">';
        echo '<span class="rk-order-label">کد رهگیری:</span>';
        echo '<strong dir="ltr">'.esc_html($tracking_code).'</strong>';
        echo '</div>';
    }

    echo '<div class="rk-order-actions-grid">';
    echo '<button type="button" class="rk-order-action-btn rk-order-details-action" data-rk-order-toggle="#'.esc_attr($details_id).'" aria-expanded="false">جزئیات سفارش</button>';
    echo '<span class="rk-order-action-btn rk-order-status-action">'.esc_html($order_status).'</span>';

    if ($tracking_code) {
        echo '<a class="rk-order-action-btn rk-order-track-action" target="_blank" rel="noopener" href="'.esc_url('https://tracking.post.ir/?id=' . rawurlencode($tracking_code)).'">رهگیری در سایت پست</a>';
    }
    echo '</div>';

    rk_render_order_details_panel($order, $items, $details_id, $tracking_code);

    echo '</article>';
}

function rk_render_order_thumbs($items) {
    echo '<div class="rk-order-thumbs-row" aria-label="تصاویر محصولات سفارش">';

    $shown = 0;

    foreach ($items as $item) {
        if ($shown >= 5) {
            break;
        }

        if (!$item instanceof WC_Order_Item_Product) {
            continue;
        }

        $product = $item->get_product();
        $name    = $item->get_name();
        $url     = $product ? get_permalink($product->get_id()) : '';

        $tag_open  = $url ? '<a class="rk-order-thumb" href="'.esc_url($url).'" aria-label="'.esc_attr($name).'">' : '<span class="rk-order-thumb">';
        $tag_close = $url ? '</a>' : '</span>';

        echo $tag_open;

        if ($product) {
            echo $product->get_image('woocommerce_thumbnail', [
                'alt'     => esc_attr($name),
                'loading' => 'lazy',
            ]);
        } else {
            echo wc_placeholder_img('woocommerce_thumbnail');
        }

        echo $tag_close;

        $shown++;
    }

    if (count($items) > 5) {
        echo '<span class="rk-order-thumb rk-order-thumb-more">+'.esc_html(count($items) - 5).'</span>';
    }

    echo '</div>';
}


function rk_render_order_status_bar($order) {
    if (!$order instanceof WC_Order) {
        return;
    }

    $current_status = $order->get_status();

    $steps = [
        'pending'    => 'در انتظار پرداخت',
        'processing' => 'پرداخت شده',
        'on-hold'    => 'آماده‌سازی',
        'packed'     => 'بسته‌بندی',
        'completed'  => 'تحویل شده',
    ];

    $status_order = array_keys($steps);
    $current_index = array_search($current_status, $status_order, true);

    if ($current_index === false) {
        $current_index = ($current_status === 'completed') ? count($status_order) - 1 : 0;
    }

    echo '<div class="rk-order-status-timeline">';

    foreach ($steps as $status_key => $label) {
        $step_index = array_search($status_key, $status_order, true);
        $is_active = $step_index !== false && $step_index <= $current_index;

        echo '<div class="rk-order-status-step '.($is_active ? 'is-active' : '').'">';
        echo '<span class="rk-order-status-dot">';
        echo $is_active ? rk_icon('check') : '';
        echo '</span>';
        echo '<small>'.esc_html($label).'</small>';
        echo '</div>';
    }

    echo '</div>';
}

function rk_render_order_details_panel($order, $items, $details_id, $tracking_code = '') {
    if (!$order instanceof WC_Order) {
        return;
    }

    echo '<div id="'.esc_attr($details_id).'" class="rk-order-details-panel" hidden>';
    echo '<div class="rk-order-details-title">جزئیات سفارش</div>';

    echo '<div class="rk-order-items-detail-list">';

    foreach ($items as $item) {
        if (!$item instanceof WC_Order_Item_Product) {
            continue;
        }

        $product = $item->get_product();
        $name    = $item->get_name();
        $qty     = $item->get_quantity();
        $total   = $order->get_formatted_line_subtotal($item);
        $url     = $product ? get_permalink($product->get_id()) : '';

        echo '<div class="rk-order-detail-item">';

        echo $url ? '<a class="rk-order-detail-thumb" href="'.esc_url($url).'" aria-label="'.esc_attr($name).'">' : '<span class="rk-order-detail-thumb">';

        if ($product) {
            echo $product->get_image('woocommerce_thumbnail', [
                'alt'     => esc_attr($name),
                'loading' => 'lazy',
            ]);
        } else {
            echo wc_placeholder_img('woocommerce_thumbnail');
        }

        echo $url ? '</a>' : '</span>';

        echo '<div class="rk-order-detail-info">';
        echo '<strong>'.esc_html($name).'</strong>';
        echo '<span>تعداد: '.esc_html(number_format_i18n($qty)).'</span>';
        echo '</div>';

        echo '<div class="rk-order-detail-price">'.wp_kses_post($total).'</div>';

        echo '</div>';
    }

    echo '</div>';

    rk_render_order_status_bar($order);

    echo '<div class="rk-order-details-meta">';
    echo '<div><span>وضعیت</span><strong>'.esc_html(wc_get_order_status_name($order->get_status())).'</strong></div>';
    echo '<div><span>جمع سفارش</span><strong>'.wp_kses_post($order->get_formatted_order_total()).'</strong></div>';

    if ($tracking_code) {
        echo '<div><span>کد رهگیری</span><strong dir="ltr">'.esc_html($tracking_code).'</strong></div>';
    }

    echo '</div>';

    echo '<a class="rk-order-full-link" href="'.esc_url($order->get_view_order_url()).'">مشاهده کامل سفارش</a>';

    echo '</div>';
}

function rk_get_order_tracking_code($order) {
    if (!$order instanceof WC_Order) {
        return '';
    }

    $keys = [
        '_ritahost_tracking_code',
        'ritahost_tracking_code',
        '_' . 'be' . 'ban_tracking_code',
        'be' . 'ban_tracking_code',
        '_marsule_tracking_code',
        'marsule_tracking_code',
        '_tracking_code',
        'tracking_code',
        '_post_tracking_code',
        'post_tracking_code',
        '_tracking_number',
        'tracking_number',
    ];

    foreach ($keys as $key) {
        $value = trim((string) $order->get_meta($key));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}


/**
 * لیست خرید بعدی
 * از متای اختصاصی RitaHost استفاده می‌کند تا با دکمه انتقال از سبد خرید سازگار باشد.
 */
function rk_is_next_purchase_endpoint_page() {
    return (
        function_exists('is_account_page') &&
        is_account_page() &&
        function_exists('is_wc_endpoint_url') &&
        is_wc_endpoint_url('next-purchase')
    );
}

function rk_legacy_next_purchase_meta_key() {
    return '_' . 'be' . 'ban_next_purchase_list';
}

function rk_get_next_purchase_list($user_id) {
    $user_id = absint($user_id);
    $items = get_user_meta($user_id, '_ritahost_next_purchase_list', true);
    if (!is_array($items)) {
        $items = get_user_meta($user_id, rk_legacy_next_purchase_meta_key(), true);
        if (is_array($items)) {
            update_user_meta($user_id, '_ritahost_next_purchase_list', $items);
        }
    }
    return is_array($items) ? $items : [];
}

function rk_update_next_purchase_list($user_id, $items) {
    return update_user_meta(absint($user_id), '_ritahost_next_purchase_list', array_values((array) $items));
}

if (!function_exists('ritahost_product_exists_in_next_purchase')) {
function ritahost_product_exists_in_next_purchase($product_id, $variation_id = 0, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return false;
    }

    $next_purchase_list = rk_get_next_purchase_list($user_id);

    if (!is_array($next_purchase_list)) {
        return false;
    }

    foreach ($next_purchase_list as $item) {
        if ((int) ($item['product_id'] ?? 0) === (int) $product_id && (int) ($item['variation_id'] ?? 0) === (int) $variation_id) {
            return true;
        }
    }

    return false;
}
}


if (!function_exists('ritahost_remove_from_next_purchase')) {
function ritahost_remove_from_next_purchase($product_id, $variation_id = 0, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return false;
    }

    $next_purchase_list = rk_get_next_purchase_list($user_id);

    if (!is_array($next_purchase_list)) {
        return false;
    }

    $removed = false;

    foreach ($next_purchase_list as $key => $item) {
        if ((int) ($item['product_id'] ?? 0) === (int) $product_id && (int) ($item['variation_id'] ?? 0) === (int) $variation_id) {
            unset($next_purchase_list[$key]);
            $removed = true;
            break;
        }
    }

    if ($removed) {
        rk_update_next_purchase_list($user_id, $next_purchase_list);
        do_action('ritahost_removed_from_next_purchase', $product_id, $user_id);
        return true;
    }

    return false;
}
}


add_action('woocommerce_account_next-purchase_endpoint', 'rk_render_next_purchase_page');

function rk_render_next_purchase_page() {
    $user_id = get_current_user_id();
    $next_purchase_list = rk_get_next_purchase_list($user_id);

    if (!is_array($next_purchase_list)) {
        $next_purchase_list = [];
    }

    echo '<section class="rk-card rk-next-purchase-card">';
    echo '<div class="rk-card-title"><h3>لیست خرید بعدی</h3>'.rk_icon('next').'</div>';

    echo '<div class="rk-next-purchase-intro">';
    echo '<p>محصولاتی که فعلاً قصد خریدشان را ندارید اینجا نگه می‌دارید و هر زمان خواستید دوباره به سبد خرید برمی‌گردانید.</p>';

    if (!empty($next_purchase_list)) {
        echo '<button type="button" class="rk-next-add-all-btn" id="rkNextAddAllToCart">'.rk_icon('bag').'<span>انتقال همه به سبد خرید</span></button>';
    }

    echo '</div>';

    if (empty($next_purchase_list)) {
        echo '<div class="rk-empty">';
        echo '<div>'.rk_icon('next').'</div>';
        echo '<strong>لیست خرید بعدی شما خالی است.</strong>';
        echo '<p>در سبد خرید می‌توانید محصولات را به لیست خرید بعدی منتقل کنید.</p>';
        echo '</div>';
        echo '</section>';
        return;
    }

    echo '<div class="rk-next-purchase-grid">';

    foreach ($next_purchase_list as $item) {
        $product_id = absint($item['product_id'] ?? 0);
        $variation_id = absint($item['variation_id'] ?? 0);
        $product = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);

        if (!$product) {
            continue;
        }

        $parent_id = $variation_id ? $product_id : $product->get_id();
        $product_name = $product->get_name();
        $product_url = get_permalink($parent_id ?: $product->get_id());

        echo '<article class="rk-next-purchase-item" data-product-id="'.esc_attr($product_id).'" data-variation-id="'.esc_attr($variation_id).'">';

        echo '<a class="rk-next-purchase-image" href="'.esc_url($product_url).'">';
        echo $product->get_image('woocommerce_thumbnail', [
            'alt'     => esc_attr($product_name),
            'loading' => 'lazy',
        ]);
        echo '</a>';

        echo '<div class="rk-next-purchase-info">';
        echo '<h4><a href="'.esc_url($product_url).'">'.esc_html($product_name).'</a></h4>';
        echo '<div class="rk-next-purchase-price">'.wp_kses_post($product->get_price_html()).'</div>';
        echo '<div class="rk-next-purchase-actions">';
        echo '<button type="button" class="rk-next-add-to-cart-btn" data-product-id="'.esc_attr($product_id).'" data-variation-id="'.esc_attr($variation_id).'">'.rk_icon('bag').'<span>افزودن به سبد</span></button>';
        echo '<button type="button" class="rk-next-remove-btn" data-product-id="'.esc_attr($product_id).'" data-variation-id="'.esc_attr($variation_id).'"><span>حذف</span></button>';
        echo '</div>';
        echo '</div>';

        echo '</article>';
    }

    echo '</div>';
    echo '</section>';
}

add_action('woocommerce_cart_item_name', 'rk_add_move_to_next_purchase_button', 10, 3);

function rk_add_move_to_next_purchase_button($name, $cart_item, $cart_item_key) {
    if (!is_user_logged_in() || !function_exists('is_cart') || !is_cart()) {
        return $name;
    }

    $product_id = absint($cart_item['product_id'] ?? 0);
    $variation_id = absint($cart_item['variation_id'] ?? 0);

    if (!$product_id) {
        return $name;
    }

    $button = '<div class="rk-move-to-next-purchase">';
    $button .= '<button type="button" class="rk-move-next-btn" data-product-id="' . esc_attr($product_id) . '" data-variation-id="' . esc_attr($variation_id) . '" data-cart-key="' . esc_attr($cart_item_key) . '">';
    $button .= 'انتقال به خرید بعدی';
    $button .= '</button>';
    $button .= '</div>';

    return $name . $button;
}

add_action('wp_ajax_ritahost_move_to_next_purchase', 'rk_ajax_move_to_next_purchase');
add_action('wp_ajax_ritahost_remove_from_next_purchase', 'rk_ajax_remove_from_next_purchase');
add_action('wp_ajax_ritahost_add_to_cart_from_next_purchase', 'rk_ajax_add_to_cart_from_next_purchase');
add_action('wp_ajax_ritahost_add_all_to_cart_from_next_purchase', 'rk_ajax_add_all_to_cart_from_next_purchase');

function rk_ajax_move_to_next_purchase() {
    check_ajax_referer('ritahost_next_purchase_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'برای استفاده از این امکان وارد حساب شوید.']);
    }

    $product_id = absint($_POST['product_id'] ?? 0);
    $variation_id = absint($_POST['variation_id'] ?? 0);
    $cart_key = sanitize_text_field(wp_unslash($_POST['cart_key'] ?? ''));

    if (!$product_id || !$cart_key || !WC()->cart) {
        wp_send_json_error(['message' => 'اطلاعات محصول کامل نیست.']);
    }

    $user_id = get_current_user_id();

    if (ritahost_product_exists_in_next_purchase($product_id, $variation_id, $user_id)) {
        wp_send_json_error(['message' => 'این محصول قبلاً در لیست خرید بعدی قرار دارد.']);
    }

    $next_purchase_list = rk_get_next_purchase_list($user_id);

    if (!is_array($next_purchase_list)) {
        $next_purchase_list = [];
    }

    $next_purchase_list[] = [
        'product_id'   => $product_id,
        'variation_id' => $variation_id,
        'added_date'   => current_time('mysql'),
    ];

    rk_update_next_purchase_list($user_id, $next_purchase_list);
    WC()->cart->remove_cart_item($cart_key);

    do_action('ritahost_added_to_next_purchase', $product_id, $user_id);

    wp_send_json_success([
        'message'    => 'محصول به لیست خرید بعدی منتقل شد.',
        'cart_count' => WC()->cart->get_cart_contents_count(),
    ]);
}

function rk_ajax_remove_from_next_purchase() {
    check_ajax_referer('ritahost_next_purchase_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'برای استفاده از این امکان وارد حساب شوید.']);
    }

    $product_id = absint($_POST['product_id'] ?? 0);
    $variation_id = absint($_POST['variation_id'] ?? 0);

    if (!$product_id) {
        wp_send_json_error(['message' => 'محصول معتبر نیست.']);
    }

    ritahost_remove_from_next_purchase($product_id, $variation_id, get_current_user_id());

    wp_send_json_success(['message' => 'محصول از لیست خرید بعدی حذف شد.']);
}

function rk_ajax_add_to_cart_from_next_purchase() {
    check_ajax_referer('ritahost_next_purchase_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'برای استفاده از این امکان وارد حساب شوید.']);
    }

    $product_id = absint($_POST['product_id'] ?? 0);
    $variation_id = absint($_POST['variation_id'] ?? 0);

    if (!$product_id || !WC()->cart) {
        wp_send_json_error(['message' => 'محصول معتبر نیست.']);
    }

    $cart_item_key = WC()->cart->add_to_cart($product_id, 1, $variation_id);

    if (!$cart_item_key) {
        wp_send_json_error(['message' => 'خطا در افزودن به سبد خرید.']);
    }

    ritahost_remove_from_next_purchase($product_id, $variation_id, get_current_user_id());

    wp_send_json_success([
        'message'    => 'محصول به سبد خرید اضافه شد.',
        'cart_count' => WC()->cart->get_cart_contents_count(),
    ]);
}

function rk_ajax_add_all_to_cart_from_next_purchase() {
    check_ajax_referer('ritahost_next_purchase_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'برای استفاده از این امکان وارد حساب شوید.']);
    }

    if (!WC()->cart) {
        wp_send_json_error(['message' => 'سبد خرید در دسترس نیست.']);
    }

    $user_id = get_current_user_id();
    $next_purchase_list = rk_get_next_purchase_list($user_id);

    if (empty($next_purchase_list) || !is_array($next_purchase_list)) {
        wp_send_json_error(['message' => 'لیست خرید بعدی خالی است.']);
    }

    $success_count = 0;
    $error_count = 0;

    foreach ($next_purchase_list as $item) {
        $product_id = absint($item['product_id'] ?? 0);
        $variation_id = absint($item['variation_id'] ?? 0);

        if (!$product_id) {
            $error_count++;
            continue;
        }

        $product = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);

        if (!$product || !$product->is_purchasable()) {
            $error_count++;
            continue;
        }

        $cart_item_key = WC()->cart->add_to_cart($product_id, 1, $variation_id);

        if ($cart_item_key) {
            $success_count++;
            ritahost_remove_from_next_purchase($product_id, $variation_id, $user_id);
        } else {
            $error_count++;
        }
    }

    if ($success_count <= 0) {
        wp_send_json_error(['message' => 'هیچ محصولی به سبد خرید اضافه نشد.']);
    }

    $message = sprintf('تعداد %d محصول به سبد خرید اضافه شد.', $success_count);

    if ($error_count > 0) {
        $message .= sprintf(' تعداد %d محصول اضافه نشد.', $error_count);
    }

    wp_send_json_success([
        'message'       => $message,
        'cart_count'    => WC()->cart->get_cart_contents_count(),
        'success_count' => $success_count,
        'error_count'   => $error_count,
    ]);
}

add_action('woocommerce_add_to_cart', function ($cart_item_key, $product_id, $quantity, $variation_id) {
    if (!is_user_logged_in()) {
        return;
    }

    ritahost_remove_from_next_purchase($product_id, $variation_id, get_current_user_id());
}, 10, 4);

add_action('wp_footer', function () {
    if (
        !is_user_logged_in() ||
        !(
            (function_exists('is_cart') && is_cart()) ||
            rk_is_next_purchase_endpoint_page()
        )
    ) {
        return;
    }

    $ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('ritahost_next_purchase_nonce');
    ?>
<script id="rk-next-purchase-script">
(function () {
    var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
    var nonce = <?php echo wp_json_encode($nonce); ?>;

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function toast(message, type) {
        var box = qs('#rkNextPurchaseToast');
        if (!box) {
            box = document.createElement('div');
            box.id = 'rkNextPurchaseToast';
            box.className = 'rk-next-toast';
            document.body.appendChild(box);
        }

        box.textContent = message || '';
        box.className = 'rk-next-toast is-visible ' + (type === 'error' ? 'is-error' : 'is-success');

        clearTimeout(box._timer);
        box._timer = setTimeout(function () {
            box.classList.remove('is-visible');
        }, 2800);
    }

    function post(action, data) {
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('nonce', nonce);

        Object.keys(data || {}).forEach(function (key) {
            body.append(key, data[key]);
        });

        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        }).then(function (response) {
            return response.json();
        });
    }

    function setLoading(button, loadingText) {
        if (!button) return;
        button.dataset.originalText = button.dataset.originalText || button.textContent;
        button.disabled = true;
        button.classList.add('is-loading');
        if (loadingText) {
            button.textContent = loadingText;
        }
    }

    function unsetLoading(button) {
        if (!button) return;
        button.disabled = false;
        button.classList.remove('is-loading');
        if (button.dataset.originalText) {
            button.textContent = button.dataset.originalText;
        }
    }

    document.addEventListener('click', function (event) {
        var moveBtn = event.target.closest('.rk-move-next-btn');
        var addBtn = event.target.closest('.rk-next-add-to-cart-btn');
        var removeBtn = event.target.closest('.rk-next-remove-btn');
        var addAllBtn = event.target.closest('#rkNextAddAllToCart');

        if (moveBtn) {
            event.preventDefault();
            setLoading(moveBtn, 'در حال انتقال...');

            post('ritahost_move_to_next_purchase', {
                product_id: moveBtn.dataset.productId || '',
                variation_id: moveBtn.dataset.variationId || '',
                cart_key: moveBtn.dataset.cartKey || ''
            }).then(function (response) {
                if (response && response.success) {
                    var row = moveBtn.closest('.woocommerce-cart-form__cart-item');
                    if (row) row.style.display = 'none';
                    qsa('.cart-contents-count').forEach(function (el) { el.textContent = response.data.cart_count; });
                    toast(response.data.message || 'محصول منتقل شد.');
                } else {
                    toast((response && response.data && response.data.message) || 'خطا در انتقال محصول.', 'error');
                    unsetLoading(moveBtn);
                }
            }).catch(function () {
                toast('خطا در ارتباط با سرور.', 'error');
                unsetLoading(moveBtn);
            });
        }

        if (addBtn) {
            event.preventDefault();
            setLoading(addBtn, 'در حال افزودن...');

            post('ritahost_add_to_cart_from_next_purchase', {
                product_id: addBtn.dataset.productId || '',
                variation_id: addBtn.dataset.variationId || ''
            }).then(function (response) {
                if (response && response.success) {
                    var item = addBtn.closest('.rk-next-purchase-item');
                    if (item) item.style.display = 'none';
                    qsa('.cart-contents-count').forEach(function (el) { el.textContent = response.data.cart_count; });
                    toast(response.data.message || 'محصول به سبد خرید اضافه شد.');
                } else {
                    toast((response && response.data && response.data.message) || 'خطا در افزودن محصول.', 'error');
                    unsetLoading(addBtn);
                }
            }).catch(function () {
                toast('خطا در ارتباط با سرور.', 'error');
                unsetLoading(addBtn);
            });
        }

        if (removeBtn) {
            event.preventDefault();

            if (!window.confirm('این محصول از لیست خرید بعدی حذف شود؟')) {
                return;
            }

            setLoading(removeBtn, 'در حال حذف...');

            post('ritahost_remove_from_next_purchase', {
                product_id: removeBtn.dataset.productId || '',
                variation_id: removeBtn.dataset.variationId || ''
            }).then(function (response) {
                if (response && response.success) {
                    var item = removeBtn.closest('.rk-next-purchase-item');
                    if (item) item.style.display = 'none';
                    toast(response.data.message || 'محصول حذف شد.');
                } else {
                    toast((response && response.data && response.data.message) || 'خطا در حذف محصول.', 'error');
                    unsetLoading(removeBtn);
                }
            }).catch(function () {
                toast('خطا در ارتباط با سرور.', 'error');
                unsetLoading(removeBtn);
            });
        }

        if (addAllBtn) {
            event.preventDefault();
            setLoading(addAllBtn, 'در حال انتقال...');

            post('ritahost_add_all_to_cart_from_next_purchase', {}).then(function (response) {
                if (response && response.success) {
                    qsa('.rk-next-purchase-item').forEach(function (el) { el.style.display = 'none'; });
                    qsa('.cart-contents-count').forEach(function (el) { el.textContent = response.data.cart_count; });
                    toast(response.data.message || 'محصولات به سبد خرید اضافه شد.');
                    unsetLoading(addAllBtn);
                } else {
                    toast((response && response.data && response.data.message) || 'خطا در انتقال محصولات.', 'error');
                    unsetLoading(addAllBtn);
                }
            }).catch(function () {
                toast('خطا در ارتباط با سرور.', 'error');
                unsetLoading(addAllBtn);
            });
        }
    });
})();
</script>
    <?php
}, 140);


add_action('wp_footer', function () {
    if (!function_exists('is_account_page') || !is_account_page() || !is_user_logged_in()) {
        return;
    }

    echo '<nav class="rk-mobile-nav">';

    $items = [
        [
            'url'   => wc_get_account_endpoint_url('dashboard'),
            'label' => 'داشبورد',
            'icon'  => 'home',
        ],
        [
            'url'   => wc_get_account_endpoint_url('orders'),
            'label' => 'سفارش‌ها',
            'icon'  => 'bag',
        ],
        [
            'url'   => wc_get_account_endpoint_url('next-purchase'),
            'label' => 'خرید بعدی',
            'icon'  => 'next',
        ],
        [
            'url'   => wc_get_account_endpoint_url('wishlist'),
            'label' => 'علاقه‌مندی',
            'icon'  => 'heart',
        ],
        [
            'url'   => wc_get_account_endpoint_url('notifications'),
            'label' => 'اعلان‌ها',
            'icon'  => 'bell',
        ],
        [
            'url'   => wc_logout_url(),
            'label' => 'خروج',
            'icon'  => 'logout',
        ],
    ];

    foreach ($items as $item) {
        echo '<a href="'.esc_url($item['url']).'" class="rk-mobile-nav-item rk-mobile-nav-'.$item['icon'].'">';
        echo rk_icon($item['icon']);
        echo '<span>'.esc_html($item['label']).'</span>';
        echo '</a>';
    }

    echo '</nav>';
});




add_action('wp_footer', function () {
    if (!rk_is_orders_endpoint_page()) {
        return;
    }
    ?>
<script id="rk-orders-toggle-script">
document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-rk-order-toggle]');
    if (!trigger) {
        return;
    }

    var selector = trigger.getAttribute('data-rk-order-toggle');
    var panel = document.querySelector(selector);

    if (!panel) {
        return;
    }

    var card = trigger.closest('.rk-order-card');
    var isHidden = panel.hasAttribute('hidden');

    if (isHidden) {
        panel.removeAttribute('hidden');
        if (card) {
            card.classList.add('is-details-open');
        }
        document.querySelectorAll('[data-rk-order-toggle="' + selector + '"]').forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'true');
            if (btn.classList.contains('rk-order-details-action')) {
                btn.textContent = 'بستن جزئیات';
            }
        });
    } else {
        panel.setAttribute('hidden', 'hidden');
        if (card) {
            card.classList.remove('is-details-open');
        }
        document.querySelectorAll('[data-rk-order-toggle="' + selector + '"]').forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
            if (btn.classList.contains('rk-order-details-action')) {
                btn.textContent = 'جزئیات سفارش';
            }
        });
    }
});
</script>
    <?php
}, 130);

/**
 * تبدیل فیلد تاریخ تولد account_billing_date به سه انتخاب‌گر سال/ماه/روز در صفحه اطلاعات کاربری.
 * فیلد اصلی از Checkout Field با کلید billing_date آمده و در صفحه حساب معمولاً با نام account_billing_date رندر می‌شود.
 */
add_action('wp_footer', function () {
    if (
        !function_exists('is_account_page') ||
        !is_account_page() ||
        !function_exists('is_wc_endpoint_url') ||
        !is_wc_endpoint_url('edit-account')
    ) {
        return;
    }
    ?>
<script id="rk-account-billing-date-dropdown">
(function () {
    function replaceAllSafe(value, search, replacement) {
        return String(value).split(search).join(replacement);
    }

    function faToEn(value) {
        if (!value) return '';
        var fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        var ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        value = String(value);
        for (var i = 0; i < 10; i++) {
            value = replaceAllSafe(value, fa[i], i);
            value = replaceAllSafe(value, ar[i], i);
        }
        return value;
    }

    function pad(num) {
        num = String(num || '');
        return num.length === 1 ? '0' + num : num;
    }

    function daysInMonth(month) {
        month = parseInt(month || 0, 10);
        if (!month) return 31;
        if (month <= 6) return 31;
        if (month <= 11) return 30;
        return 29;
    }

    function makeSelect(className, placeholder) {
        var select = document.createElement('select');
        select.className = className;
        select.setAttribute('aria-label', placeholder);

        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder;
        select.appendChild(opt);

        return select;
    }

    function findBirthField() {
        var selectors = [
            '#account_billing_date',
            'input[name="account_billing_date"]',
            'select[name="account_billing_date"]',
            '#billing_date',
            'input[name="billing_date"]',
            'select[name="billing_date"]'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var found = document.querySelector(selectors[i]);
            if (found) return found;
        }

        var labels = document.querySelectorAll('.woocommerce-EditAccountForm label');
        for (var j = 0; j < labels.length; j++) {
            var txt = labels[j].textContent || '';
            if (txt.indexOf('تاریخ تولد') !== -1 || txt.indexOf('تولد') !== -1) {
                var forId = labels[j].getAttribute('for');
                if (forId && document.getElementById(forId)) {
                    return document.getElementById(forId);
                }

                var parent = labels[j].closest('p, div');
                if (parent) {
                    var input = parent.querySelector('input, select');
                    if (input) return input;
                }
            }
        }

        return null;
    }

    var original = findBirthField();
    if (!original || original.dataset.rkBirthConverted === '1') return;

    original.dataset.rkBirthConverted = '1';

    var originalName = original.getAttribute('name') || 'account_billing_date';
    var originalId = original.getAttribute('id') || 'account_billing_date';

    var rawValue = faToEn(original.value || original.getAttribute('value') || '').replace(/[-.]/g, '/').trim();
    var match = rawValue.match(/(\d{4})\D+(\d{1,2})\D+(\d{1,2})/);

    var selectedYear  = match ? match[1] : '';
    var selectedMonth = match ? pad(match[2]) : '';
    var selectedDay   = match ? pad(match[3]) : '';

    var hidden;

    if (original.tagName && original.tagName.toLowerCase() === 'select') {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = originalName;
        hidden.id = originalId + '_hidden';

        original.removeAttribute('name');
        original.disabled = true;
        original.classList.add('rk-birth-original-hidden');

        original.insertAdjacentElement('afterend', hidden);
    } else {
        hidden = original;
        try {
            hidden.type = 'hidden';
        } catch (e) {}
        hidden.name = originalName;
        hidden.id = originalId;
        hidden.classList.add('rk-birth-original-hidden');
    }

    var wrap = document.createElement('div');
    wrap.className = 'rk-birth-selects';

    var yearSelect = makeSelect('rk-birth-year', 'سال');
    var monthSelect = makeSelect('rk-birth-month', 'ماه');
    var daySelect = makeSelect('rk-birth-day', 'روز');

    for (var y = 1405; y >= 1320; y--) {
        var yOpt = document.createElement('option');
        yOpt.value = String(y);
        yOpt.textContent = String(y);
        if (String(y) === selectedYear) yOpt.selected = true;
        yearSelect.appendChild(yOpt);
    }

    var months = [
        ['01', 'فروردین'],
        ['02', 'اردیبهشت'],
        ['03', 'خرداد'],
        ['04', 'تیر'],
        ['05', 'مرداد'],
        ['06', 'شهریور'],
        ['07', 'مهر'],
        ['08', 'آبان'],
        ['09', 'آذر'],
        ['10', 'دی'],
        ['11', 'بهمن'],
        ['12', 'اسفند']
    ];

    months.forEach(function (item) {
        var mOpt = document.createElement('option');
        mOpt.value = item[0];
        mOpt.textContent = item[1];
        if (item[0] === selectedMonth) mOpt.selected = true;
        monthSelect.appendChild(mOpt);
    });

    function refreshDays() {
        var oldDay = daySelect.value || selectedDay;
        daySelect.innerHTML = '';

        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'روز';
        daySelect.appendChild(empty);

        var maxDay = daysInMonth(monthSelect.value);

        for (var d = 1; d <= maxDay; d++) {
            var dValue = pad(d);
            var dOpt = document.createElement('option');
            dOpt.value = dValue;
            dOpt.textContent = dValue;
            if (dValue === oldDay) dOpt.selected = true;
            daySelect.appendChild(dOpt);
        }
    }

    function syncBirthValue() {
        var y = yearSelect.value;
        var m = monthSelect.value;
        var d = daySelect.value;

        hidden.value = (y && m && d) ? (y + '/' + m + '/' + d) : '';
    }

    refreshDays();
    syncBirthValue();

    yearSelect.addEventListener('change', syncBirthValue);
    monthSelect.addEventListener('change', function () {
        refreshDays();
        syncBirthValue();
    });
    daySelect.addEventListener('change', syncBirthValue);

    hidden.insertAdjacentElement('afterend', wrap);
    wrap.appendChild(yearSelect);
    wrap.appendChild(monthSelect);
    wrap.appendChild(daySelect);
})();
</script>
    <?php
}, 99);

add_action('woocommerce_save_account_details', function ($user_id) {
    $birth_date = '';

    foreach (['account_billing_date', 'billing_date'] as $key) {
        if (!empty($_POST[$key])) {
            $birth_date = sanitize_text_field(wp_unslash($_POST[$key]));
            break;
        }
    }

    if (!$birth_date) {
        return;
    }

    $birth_date = str_replace(['-', '.'], '/', $birth_date);

    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $birth_date, $m)) {
        $birth_date = sprintf('%04d/%02d/%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }

    update_user_meta($user_id, 'billing_date', $birth_date);
    update_user_meta($user_id, 'account_billing_date', $birth_date);
}, 30);

add_action('wp_head', function () {
    if (!function_exists('is_account_page') || !is_account_page()) return;
    ?>
    
    
<style id="ritahost-custom-dashboard-v328">
:root{
    --rk-primary:<?php echo esc_html(rk_setting('color_primary', RK_COLOR_PRIMARY)); ?>;
    --rk-soft:<?php echo esc_html(rk_setting('color_soft', RK_COLOR_PRIMARY_SOFT)); ?>;
    --rk-bg:<?php echo esc_html(rk_setting('color_background', RK_COLOR_BACKGROUND)); ?>;
    --rk-card:<?php echo esc_html(rk_setting('color_card', RK_COLOR_CARD)); ?>;
    --rk-border:<?php echo esc_html(rk_setting('color_border', RK_COLOR_BORDER)); ?>;
    --rk-text:<?php echo esc_html(rk_setting('color_text', RK_COLOR_TEXT)); ?>;
    --rk-muted:<?php echo esc_html(rk_setting('color_muted', RK_COLOR_MUTED)); ?>;
    --rk-danger:<?php echo esc_html(rk_setting('color_danger', RK_COLOR_DANGER)); ?>;
    --rk-success:<?php echo esc_html(rk_setting('color_success', RK_COLOR_SUCCESS)); ?>;
    --rk-success-dark:<?php echo esc_html(rk_setting('color_success_dark', RK_COLOR_SUCCESS_DARK)); ?>;
    --rk-success-soft:<?php echo esc_html(rk_setting('color_success_soft', RK_COLOR_SUCCESS_SOFT)); ?>;
    --rk-success-border:<?php echo esc_html(rk_setting('color_success_border', RK_COLOR_SUCCESS_BORDER)); ?>;
    --rk-shadow:<?php echo esc_html(rk_setting('shadow', RK_SHADOW)); ?>;
    --rk-radius:<?php echo esc_html(rk_setting('radius', RK_RADIUS)); ?>;
    --rk-font-title:<?php echo esc_html(rk_setting('font_title', '18px')); ?>;
    --rk-font-text:<?php echo esc_html(rk_setting('font_text', '14px')); ?>;
    --rk-font-small:<?php echo esc_html(rk_setting('font_small', '12px')); ?>;
    --rk-font-button:<?php echo esc_html(rk_setting('font_button', '13px')); ?>;
    --rk-icon-size:<?php echo esc_html(rk_setting('icon_size', '27px')); ?>;
    --rk-card-padding:<?php echo esc_html(rk_setting('card_padding', '24px')); ?>;
    --rk-content-max-width:<?php echo esc_html(rk_setting('content_max_width', '1200px')); ?>;
    --rk-sidebar-width:<?php echo esc_html(rk_setting('sidebar_width', '30%')); ?>;
}
body.woocommerce-account{background:var(--rk-bg)!important;}
body.woocommerce-account .entry-title,
body.woocommerce-account .page-title{display:none!important;}
body.woocommerce-account .woocommerce {
    display: flex !important;
    flex-direction: row !important;
    justify-content: center !important;
    align-items: flex-start !important;
    width: 100% !important;
    max-width: var(--rk-content-max-width) !important;
    margin: 30px auto !important;
    padding: 0 16px !important;
    gap: 22px !important;
}
body.woocommerce-account .woocommerce:before,
body.woocommerce-account .woocommerce:after{display:none!important;content:none!important;}
body.woocommerce-account .woocommerce-MyAccount-content{
    float:none!important;width:100%!important;grid-column:1!important;min-width:0!important;
}
body.woocommerce-account .woocommerce-MyAccount-navigation{
    float:none!important;width:var(--rk-sidebar-width)!important;grid-column:2!important;
    background:#fff!important;border:1px solid var(--rk-border)!important;
    border-radius:var(--rk-radius)!important;box-shadow:var(--rk-shadow)!important;
    padding:24px 16px!important;position:sticky!important;top:24px!important;
    min-height:660px!important;
}
body.woocommerce-account .woocommerce-MyAccount-navigation:before{
    content:"حساب کاربری";display:block;text-align:center;margin:0 0 24px;
    font-size:18px;font-weight:900;color:var(--rk-text);
}
body.woocommerce-account .woocommerce-MyAccount-navigation ul{list-style:none!important;margin:0!important;padding:0!important;}
body.woocommerce-account .woocommerce-MyAccount-navigation li{margin:0 0 8px!important;padding:0!important;border:0!important;}
body.woocommerce-account .woocommerce-MyAccount-navigation li a{
    display:flex!important;align-items:center!important;justify-content:space-between!important;
    min-height:48px!important;padding:12px 14px!important;border-radius:12px!important;
    text-decoration:none!important;color:var(--rk-text)!important;background:transparent!important;
    font-size:14px!important;font-weight:700!important;transition:.18s ease!important;
}
body.woocommerce-account .woocommerce-MyAccount-navigation li a:hover,
body.woocommerce-account .woocommerce-MyAccount-navigation li.is-active a{
    background:var(--rk-soft)!important;color:var(--rk-primary)!important;
}
body.woocommerce-account .woocommerce-MyAccount-navigation-link--customer-logout a{color:var(--rk-danger)!important;}
body.woocommerce-account .woocommerce-MyAccount-navigation li a:after{
    width:23px;height:23px;display:inline-flex;
}
body.woocommerce-account .woocommerce-MyAccount-navigation-link--dashboard a:after{content:"⌂";font-size:22px;color:var(--rk-primary);}
body.woocommerce-account .woocommerce-MyAccount-navigation-link--edit-account a:after{content:"♙";font-size:19px;color:#6d6578;}
body.woocommerce-account .woocommerce-MyAccount-navigation-link--orders a:after{content:"▢";font-size:22px;color:#6d6578;}
body.woocommerce-account .woocommerce-MyAccount-navigation-link--edit-address a:after{content:"⌖";font-size:20px;color:#6d6578;}
body.woocommerce-account .woocommerce-MyAccount-navigation-link--wishlist a:after{content:"♡";font-size:22px;color:#6d6578;}
body.woocommerce-account .woocommerce-MyAccount-navigation-link--notifications a:after{content:"♧";font-size:19px;color:#6d6578;}
body.woocommerce-account .woocommerce-MyAccount-navigation-link--reviews a:after{content:"☆";font-size:22px;color:#6d6578;}
body.woocommerce-account .woocommerce-MyAccount-navigation-link--account-settings a:after{content:"⚙";font-size:18px;color:#6d6578;}
body.woocommerce-account .woocommerce-MyAccount-navigation-link--customer-logout a:after{content:"↪";font-size:20px;color:var(--rk-danger);}

body.woocommerce-account .woocommerce-MyAccount-navigation-link--next-purchase a:after{content:"⟳";font-size:20px;color:var(--rk-muted);}
body.woocommerce-account .woocommerce-MyAccount-navigation-link--customer-logout a:after{
    content:"" !important;
    background:var(--rk-danger) !important;
    width:22px !important;
    height:22px !important;
    -webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='black' d='M10 17l5-5-5-5v3H3v4h7v3Zm2-14v2h6v14h-6v2h8V3h-8Z'/%3E%3C/svg%3E") center/22px 22px no-repeat;
    mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='black' d='M10 17l5-5-5-5v3H3v4h7v3Zm2-14v2h6v14h-6v2h8V3h-8Z'/%3E%3C/svg%3E") center/22px 22px no-repeat;
}


.rk-dashboard{display:flex;flex-direction:column;gap:18px;}
.rk-card,.rk-top-card{background:var(--rk-card);border:1px solid var(--rk-border);border-radius:var(--rk-radius);box-shadow:var(--rk-shadow);}
.rk-top-card{display:grid;grid-template-columns:270px minmax(0,1fr);gap:22px;align-items:center;padding:28px;}
.rk-profile-mini{display:flex;align-items:center;gap:16px;flex-direction:row!important;}
.rk-avatar-wrap{display:flex;flex-direction:column;align-items:center;gap:0;flex:0 0 auto;order:1;}
.rk-avatar{width:72px;height:72px;border-radius:50%;overflow:hidden;background:#f1f2f5;display:flex;align-items:center;justify-content:center;}
.rk-avatar img,.rk-avatar-img{width:72px!important;height:72px!important;border-radius:50%!important;border:0!important;box-shadow:none!important;object-fit:cover!important;display:block!important;background:#f1f2f5!important;}
.rk-default-avatar{flex:0 0 auto;}
.rk-profile-name{order:2;}
.rk-profile-name h2{margin:0 0 7px!important;font-size:21px!important;line-height:1.6!important;color:var(--rk-text)!important;font-weight:900!important;}
.rk-profile-name p{margin:0 0 10px!important;color:var(--rk-muted)!important;font-size:14px!important;}
.rk-profile-name span{display:inline-flex;background:var(--rk-soft);color:var(--rk-primary);padding:5px 12px;border-radius:999px;font-size:12px;font-weight:900;}
.rk-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;}
.rk-stat{min-height:126px;border:1px solid var(--rk-border);border-radius:14px;background:#fff;text-decoration:none!important;color:var(--rk-text)!important;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-align:center;padding:14px;}
.rk-stat svg{width:31px;height:31px;stroke:var(--rk-primary);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.rk-stat small{font-size:13px;color:var(--rk-muted);}
.rk-stat strong{font-size:15px;font-weight:900;color:var(--rk-primary);line-height:1.7;}

.rk-card{padding:var(--rk-card-padding);}
.rk-card-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
.rk-card-title h3{margin:0!important;font-size:18px!important;color:var(--rk-text)!important;font-weight:900!important;line-height:1.8!important;}
.rk-card-title svg{width:27px;height:27px;stroke:var(--rk-primary);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.rk-info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:18px;}
.rk-info-grid>div{min-height:72px;background:#fbfaff;border-radius:14px;padding:13px 16px;display:flex;flex-direction:column;justify-content:center;}
.rk-info-grid label{font-size:12px;color:var(--rk-muted);margin-bottom:7px;}
.rk-info-grid strong{font-size:14px;font-weight:900;color:var(--rk-text);word-break:break-word;}
.rk-btn,body.woocommerce-account .woocommerce a.button,body.woocommerce-account .woocommerce button.button,body.woocommerce-account .woocommerce input.button{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:42px!important;border:1px solid var(--rk-primary)!important;border-radius:11px!important;padding:9px 18px!important;background:var(--rk-primary)!important;color:#fff!important;text-decoration:none!important;font-size:13px!important;font-weight:900!important;box-shadow:none!important;}
.rk-outline{background:#fff!important;color:var(--rk-primary)!important;}
.rk-empty{text-align:center;padding:28px 12px;color:var(--rk-muted);}
.rk-empty>div{width:82px;height:82px;margin:0 auto 14px;background:var(--rk-soft);border-radius:28px;display:flex;align-items:center;justify-content:center;}
.rk-empty svg{width:42px;height:42px;stroke:var(--rk-primary);fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round;}
.rk-empty strong{display:block;color:var(--rk-text);font-size:15px;margin-bottom:7px;}
.rk-empty p{margin:0 0 18px;color:var(--rk-muted);font-size:13px;line-height:2;}

.rk-last-order-card{overflow:hidden!important;}
.rk-order-table-wrap{width:100%!important;overflow-x:auto!important;border:1px solid var(--rk-border)!important;border-radius:14px!important;background:#fff!important;}
.rk-order-table{width:100%!important;border-collapse:separate!important;border-spacing:0!important;margin:0!important;table-layout:fixed!important;background:#fff!important;direction:rtl!important;}
.rk-order-table th,
.rk-order-table td{border:0!important;border-left:1px solid var(--rk-border)!important;border-bottom:1px solid var(--rk-border)!important;padding:14px 12px!important;text-align:center!important;vertical-align:middle!important;font-size:13px!important;line-height:1.9!important;white-space:normal!important;color:var(--rk-text)!important;background:#fff!important;}
.rk-order-table th:last-child,
.rk-order-table td:last-child{border-left:0!important;}
.rk-order-table tbody tr:last-child td{border-bottom:0!important;}
.rk-order-table thead th{background:var(--rk-soft)!important;color:var(--rk-primary)!important;font-weight:900!important;font-size:13px!important;}
.rk-order-table tbody td{font-weight:800!important;}
.rk-order-table tbody td strong{font-weight:950!important;color:var(--rk-text)!important;}
.rk-status-badge{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:30px!important;padding:4px 12px!important;border-radius:999px!important;background:var(--rk-success-soft)!important;color:var(--rk-success-dark)!important;border:1px solid var(--rk-success-border)!important;font-size:12px!important;font-weight:900!important;line-height:1.7!important;white-space:nowrap!important;}
.rk-order-view-btn{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:32px!important;padding:5px 13px!important;border-radius:10px!important;background:var(--rk-primary)!important;color:#fff!important;text-decoration:none!important;font-size:12px!important;font-weight:900!important;line-height:1.7!important;}
.rk-order-view-btn:hover{opacity:.9!important;color:#fff!important;}
.rk-small-link{margin-top:12px!important;display:inline-flex!important;color:var(--rk-primary)!important;text-decoration:none!important;font-weight:900!important;font-size:13px!important;}


.rk-notices{display:grid;gap:12px}.rk-notice{border:1px solid var(--rk-border);border-radius:14px;background:#fff;padding:16px}.rk-notice.warning{background:#fff9ec}.rk-notice strong{display:block;margin-bottom:7px}.rk-notice p{margin:0 0 9px;color:var(--rk-muted);line-height:2}.rk-notice a{color:var(--rk-primary)!important;font-weight:900;text-decoration:none!important;}
.rk-review-list{display:grid;gap:12px}.rk-review-list article{display:grid;grid-template-columns:86px 1fr;gap:14px;border:1px solid var(--rk-border);border-radius:14px;padding:14px}.rk-review-thumb img{width:76px;height:76px;object-fit:cover;border-radius:12px}.rk-review-list h4{margin:0 0 7px!important}.rk-review-list p{margin:0 0 8px!important;color:var(--rk-muted)!important}.rk-review-list a{color:var(--rk-primary)!important;text-decoration:none!important;font-weight:900}
.rk-wishlist-box{overflow-x:auto}.rk-settings{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.rk-settings a{min-height:86px;border:1px solid var(--rk-border);border-radius:14px;background:#fff;text-decoration:none!important;color:var(--rk-primary)!important;font-weight:900;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:9px;}.rk-settings svg{width:31px;height:31px;stroke:var(--rk-primary);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}.rk-settings .danger{color:var(--rk-danger)!important}.rk-settings .danger svg{stroke:var(--rk-danger)}

body.woocommerce-account .woocommerce form .form-row input.input-text,body.woocommerce-account .woocommerce form .form-row textarea,body.woocommerce-account .woocommerce form .form-row select,body.woocommerce-account .woocommerce-Input,body.woocommerce-account .input-text{border:1px solid #d9d3df!important;border-radius:10px!important;background:#fff!important;min-height:46px!important;padding:12px 14px!important;box-shadow:none!important;}
body.woocommerce-account .woocommerce form{background:#fff;border:1px solid var(--rk-border);border-radius:var(--rk-radius);box-shadow:var(--rk-shadow);padding:28px!important;}
body.woocommerce-account .woocommerce legend{font-size:16px;font-weight:900;color:var(--rk-text);padding-top:18px;}
body.woocommerce-account .woocommerce form .form-row label{color:var(--rk-muted);font-size:13px;font-weight:700;}

.rk-mobile-nav{display:none}
@media(max-width:1024px){
    body.woocommerce-account .woocommerce{display:block!important;margin:16px 0 92px!important;}
    body.woocommerce-account .woocommerce-MyAccount-navigation{display:none!important;}
    .rk-top-card{grid-template-columns:1fr;padding:18px}.rk-stats{grid-template-columns:1fr}.rk-stat{min-height:58px;display:grid;grid-template-columns:34px 1fr auto;text-align:right}.rk-stat svg{width:24px;height:24px}.rk-info-grid{grid-template-columns:1fr}.rk-settings{grid-template-columns:repeat(2,1fr)}.rk-card{padding:18px}
    .rk-order-table{min-width:620px!important;table-layout:auto!important;}.rk-order-table th,.rk-order-table td{padding:12px 10px!important;font-size:12px!important;}.rk-status-badge{font-size:11px!important;padding:4px 10px!important;}
    .rk-mobile-nav{position:fixed;right:10px;left:10px;bottom:10px;z-index:99999;display:grid;grid-template-columns:repeat(6,1fr);background:rgba(255,255,255,.96);border:1px solid var(--rk-border);border-radius:18px;box-shadow:0 10px 35px rgba(0,0,0,.16);overflow:hidden;backdrop-filter:blur(10px);direction:rtl}.rk-mobile-nav a{min-height:62px;text-decoration:none;color:var(--rk-text);font-size:10px;font-weight:900;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px}.rk-mobile-nav svg{width:20px;height:20px;stroke:var(--rk-primary);fill:none;stroke-width:1.8}
}
@media(max-width:520px){.rk-settings{grid-template-columns:repeat(2,1fr)}.rk-avatar{width:64px;height:64px}.rk-avatar img,.rk-avatar-img{width:64px!important;height:64px!important}.rk-profile-name h2{font-size:18px!important}}
body.woocommerce-account .woocommerce-MyAccount-content > p:first-of-type,
body.woocommerce-account .woocommerce-MyAccount-content .woocommerce-notices-wrapper,
body.woocommerce-account .woocommerce-info,
.woocommerce-MyAccount-content > p {display:none!important;}
.phonerita{direction:ltr!important;text-align:right!important;}


.rk-address-list{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:14px!important;margin-bottom:18px!important;}
.rk-address-item{display:flex!important;align-items:flex-start!important;gap:12px!important;padding:16px!important;border:1px solid var(--rk-border)!important;border-radius:14px!important;background:#fbfaff!important;min-height:112px!important;}
.rk-address-icon{width:38px!important;height:38px!important;border-radius:14px!important;background:var(--rk-soft)!important;display:flex!important;align-items:center!important;justify-content:center!important;flex:0 0 auto!important;}
.rk-address-icon svg{width:20px!important;height:20px!important;stroke:var(--rk-primary)!important;fill:none!important;stroke-width:1.8!important;stroke-linecap:round!important;stroke-linejoin:round!important;}
.rk-address-content{min-width:0!important;flex:1!important;}
.rk-address-content strong{display:block!important;color:var(--rk-text)!important;font-size:14px!important;font-weight:900!important;margin:0 0 12px!important;line-height:1.9!important;}
.rk-address-line{display:flex!important;align-items:center!important;gap:8px!important;margin:0 0 8px!important;color:var(--rk-muted)!important;font-size:13px!important;line-height:1.9!important;}
.rk-address-line-icon{width:24px!important;height:24px!important;min-width:24px!important;border-radius:9px!important;background:var(--rk-soft)!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;}
.rk-address-line-icon svg{width:14px!important;height:14px!important;stroke:var(--rk-primary)!important;fill:none!important;stroke-width:1.9!important;stroke-linecap:round!important;stroke-linejoin:round!important;}
.rk-address-line-text{color:var(--rk-muted)!important;word-break:break-word!important;}
.rk-address-line-phone .rk-address-line-text{direction:ltr!important;unicode-bidi:plaintext!important;text-align:right!important;display:inline-block!important;}
@media(max-width:768px){.rk-address-list{grid-template-columns:1fr!important;}}
body.woocommerce-account .rk-last-order-card .rk-order-table tbody td[data-title="تاریخ"],
body.woocommerce-account .rk-last-order-card .rk-order-table tbody td[data-title="تاریخ"] * {
    color: #1f1728 !important;
    -webkit-text-fill-color: #1f1728 !important;
}
body.woocommerce-account .rk-info-grid > div {
    position: relative !important;
    background: #fff !important;
    border: 1px solid var(--rk-border) !important;
    border-radius: 14px !important;
    min-height: 50px !important;
    padding: 14px 16px 14px !important;
    overflow: visible !important;
}

body.woocommerce-account .rk-info-grid > div label {
    position: absolute !important;
    top: -13px !important;
    right: 14px !important;
    background: #fff !important;
    padding: 0 8px !important;
    color: var(--rk-muted) !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    line-height: 22px !important;
    margin: 0 !important;
    z-index: 2 !important;
}

body.woocommerce-account .rk-info-grid > div strong {
    display: block !important;
    color: var(--rk-text) !important;
    font-size: 14px !important;
    font-weight: 900 !important;
    line-height: 2 !important;
    word-break: break-word !important;
}

body.woocommerce-account .rk-info-grid > div.phonerita strong,
body.woocommerce-account .rk-info-grid > div strong a[href^="tel"],
body.woocommerce-account .rk-info-grid > div strong a[href^="mailto"] {
    direction: ltr !important;
    unicode-bidi: plaintext !important;
    text-align: right !important;
}
body.woocommerce-account .woocommerce-MyAccount-navigation li a {
    position: relative !important;
    justify-content: flex-start !important;
    text-align: right !important;
    padding: 12px 48px 12px 14px !important;
}

body.woocommerce-account .woocommerce-MyAccount-navigation li a:after {
    position: absolute !important;
    right: 16px !important;
    left: auto !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    width: 22px !important;
    height: 22px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin: 0 !important;
    text-align: center !important;
}
body.woocommerce-account .woocommerce-MyAccount-navigation {
    min-height: auto !important;
    height: auto !important;
    align-self: flex-start !important;
}

/* === Edit Account Form - Floating Border Labels + Birth Dropdown === */
body.woocommerce-account .woocommerce-EditAccountForm {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 24px 18px !important;
    background: #fff !important;
    border-radius: 18px !important;
    padding: 28px 30px !important;
    max-width: 900px !important;
    margin: 0 auto !important;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05) !important;
}

body.woocommerce-account .woocommerce-EditAccountForm > * {
    flex: 0 0 100% !important;
}

body.woocommerce-account .woocommerce-EditAccountForm p.form-row,
body.woocommerce-account .woocommerce-EditAccountForm .rh-account-extra-grid .rh-col {
    position: relative !important;
    margin: 0 !important;
    padding: 0 !important;
}

body.woocommerce-account .woocommerce-EditAccountForm p.form-row label,
body.woocommerce-account .woocommerce-EditAccountForm .rh-account-extra-grid .rh-col label {
    position: absolute !important;
    top: -10px !important;
    right: 14px !important;
    z-index: 5 !important;
    background: #fff !important;
    padding: 0 8px !important;
    margin: 0 !important;
    color: #7f738b !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    line-height: 22px !important;
}

body.woocommerce-account .woocommerce-EditAccountForm input[type="text"],
body.woocommerce-account .woocommerce-EditAccountForm input[type="email"],
body.woocommerce-account .woocommerce-EditAccountForm input[type="password"],
body.woocommerce-account .woocommerce-EditAccountForm input[type="tel"],
body.woocommerce-account .woocommerce-EditAccountForm input[type="date"],
body.woocommerce-account .woocommerce-EditAccountForm select {
    width: 100% !important;
    height: 48px !important;
    min-height: 48px !important;
    border: 1px solid #ddd4e7 !important;
    border-radius: 10px !important;
    background: #fff !important;
    padding: 0 15px !important;
    color: #1f1728 !important;
    font-size: 14px !important;
    font-weight: 800 !important;
    box-shadow: none !important;
    outline: none !important;
}

body.woocommerce-account .woocommerce-EditAccountForm input:focus,
body.woocommerce-account .woocommerce-EditAccountForm select:focus {
    border-color: #7b22c8 !important;
    background: #fff !important;
    box-shadow: 0 0 0 3px rgba(123,34,200,.08) !important;
}

body.woocommerce-account .woocommerce-EditAccountForm input[type="email"],
body.woocommerce-account .woocommerce-EditAccountForm input[type="tel"],
body.woocommerce-account .woocommerce-EditAccountForm input[name*="phone"],
body.woocommerce-account .woocommerce-EditAccountForm input[name*="mobile"] {
    direction: ltr !important;
    text-align: right !important;
    unicode-bidi: plaintext !important;
}

body.woocommerce-account .woocommerce-EditAccountForm p.form-row:has(#account_first_name),
body.woocommerce-account .woocommerce-EditAccountForm p.form-row:has(#account_last_name),
body.woocommerce-account .woocommerce-EditAccountForm p.form-row:has(#account_display_name),
body.woocommerce-account .woocommerce-EditAccountForm p.form-row:has(#account_email) {
    flex: 0 0 calc(50% - 9px) !important;
}

body.woocommerce-account .woocommerce-EditAccountForm .rh-account-extra-grid {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 24px 18px !important;
    width: 100% !important;
    order: 30 !important;
}

body.woocommerce-account .woocommerce-EditAccountForm .rh-account-extra-grid .rh-col {
    flex: 0 0 calc(50% - 9px) !important;
}

body.woocommerce-account .woocommerce-EditAccountForm fieldset {
    order: 40 !important;
    border: 0 !important;
    padding: 10px 0 !important;
    margin-top: 0 !important;
}

body.woocommerce-account .woocommerce-EditAccountForm p:has(button[type="submit"]),
body.woocommerce-account .woocommerce-EditAccountForm button[type="submit"] {
    order: 100 !important;
    width: 100% !important;
}

body.woocommerce-account .woocommerce-EditAccountForm .rk-birth-original-hidden {
    display: none !important;
}

body.woocommerce-account .woocommerce-EditAccountForm .rk-birth-selects {
    display: grid !important;
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    gap: 8px !important;
    width: 100% !important;
}

body.woocommerce-account .woocommerce-EditAccountForm .rk-birth-selects select {
    width: 100% !important;
    height: 48px !important;
    min-height: 48px !important;
    border: 1px solid #ddd4e7 !important;
    border-radius: 10px !important;
    background: #fff !important;
    color: #1f1728 !important;
    font-size: 13px !important;
    font-weight: 800 !important;
    padding: 0 10px !important;
    box-shadow: none !important;
    outline: none !important;
    direction: rtl !important;
}

body.woocommerce-account .woocommerce-EditAccountForm .rk-birth-selects select:focus {
    border-color: #7b22c8 !important;
    box-shadow: 0 0 0 3px rgba(123,34,200,.08) !important;
}

body.woocommerce-account .woocommerce-EditAccountForm input::placeholder {
    color: #8e8298 !important;
    font-weight: 600 !important;
}

body.woocommerce-account #account_display_name_description em {
    display: none !important;
}

body.woocommerce-account .woocommerce-Button.button {
    font-family: inherit !important;
}

@media (max-width: 768px) {
    body.woocommerce-account .woocommerce-EditAccountForm p.form-row:has(#account_first_name),
    body.woocommerce-account .woocommerce-EditAccountForm p.form-row:has(#account_last_name),
    body.woocommerce-account .woocommerce-EditAccountForm p.form-row:has(#account_display_name),
    body.woocommerce-account .woocommerce-EditAccountForm p.form-row:has(#account_email),
    body.woocommerce-account .woocommerce-EditAccountForm .rh-account-extra-grid .rh-col {
        flex: 0 0 100% !important;
    }
}

@media(max-width: 520px) {
    body.woocommerce-account .woocommerce-EditAccountForm .rk-birth-selects {
        grid-template-columns: 1fr !important;
    }
}


/* === Address cards edit button / address endpoint page === */
.rk-address-item{
    position: relative !important;
}

.rk-address-heading{
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 10px !important;
    margin: 0 0 12px !important;
}

.rk-address-heading strong{
    margin: 0 !important;
}

.rk-address-edit{
    width: 32px !important;
    height: 32px !important;
    min-width: 32px !important;
    border-radius: 11px !important;
    background: var(--rk-soft) !important;
    color: var(--rk-primary) !important;
    border: 1px solid rgba(108,31,175,.16) !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-decoration: none !important;
    transition: .18s ease !important;
}

.rk-address-edit:hover{
    background: var(--rk-primary) !important;
    color: #fff !important;
}

.rk-address-edit svg{
    width: 16px !important;
    height: 16px !important;
    stroke: currentColor !important;
    fill: none !important;
    stroke-width: 2 !important;
    stroke-linecap: round !important;
    stroke-linejoin: round !important;
}

.rk-address-page-card .rk-address-list{
    margin-bottom: 18px !important;
}

.rk-address-actions{
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 10px !important;
    align-items: center !important;
    justify-content: flex-start !important;
    margin-top: 4px !important;
}

.rk-address-page-card .rk-empty .rk-address-actions{
    justify-content: center !important;
    margin-top: 16px !important;
}

@media(max-width:768px){
    .rk-address-actions{
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .rk-address-actions .rk-btn{
        width: 100% !important;
    }
}



/* Safety: فقط در صفحه اصلی آدرس‌ها، خروجی پیش‌فرض ووکامرس اگر توسط قالب/افزونه دوباره چاپ شد مخفی شود. */
body.rk-account-address-index .woocommerce-MyAccount-content > .woocommerce-Addresses,
body.rk-account-address-index .woocommerce-MyAccount-content > .woocommerce-Address,
body.rk-account-address-index .woocommerce-MyAccount-content > header.woocommerce-Address-title,
body.rk-account-address-index .woocommerce-MyAccount-content > .woocommerce-address-fields {
    display: none !important;
}

body.rk-account-address-index .woocommerce-MyAccount-content > .rk-address-page-card {
    display: block !important;
}
@media(max-width:1024px){
    body.woocommerce-account .rk-profile-mini {
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        gap: 10px !important;
    }

    body.woocommerce-account .rk-avatar-wrap {
        order: 1 !important;
        align-items: center !important;
        justify-content: center !important;
    }

    body.woocommerce-account .rk-profile-name {
        order: 2 !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        width: 100% !important;
    }

    body.woocommerce-account .rk-profile-name h2 {
        margin: 0 0 4px !important;
        text-align: center !important;
    }

    body.woocommerce-account .rk-profile-name p {
        margin: 0 0 6px !important;
        text-align: center !important;
        direction: ltr !important;
    }

    body.woocommerce-account .rk-profile-name span {
        margin: 0 auto !important;
        text-align: center !important;
    }
}
body.woocommerce-account .rk-mobile-nav .rk-mobile-nav-logout {
    color: var(--rk-danger) !important;
}

body.woocommerce-account .rk-mobile-nav .rk-mobile-nav-logout svg {
    stroke: var(--rk-danger) !important;
}


/* === RitaHost Custom Orders Cards - Desktop & Mobile === */
body.woocommerce-account .rk-orders-card {
    padding: var(--rk-card-padding) !important;
}

body.woocommerce-account .rk-orders-list {
    display: grid !important;
    gap: 16px !important;
}

body.woocommerce-account .rk-order-card {
    background: #fff !important;
    border: 1px solid var(--rk-border) !important;
    border-radius: 18px !important;
    padding: 16px !important;
    box-shadow: 0 8px 22px rgba(35,18,52,.045) !important;
    overflow: hidden !important;
}

body.woocommerce-account .rk-order-card-head {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px !important;
    padding-bottom: 12px !important;
    border-bottom: 1px solid var(--rk-border) !important;
}

body.woocommerce-account .rk-order-card-number {
    color: var(--rk-text) !important;
    font-size: 15px !important;
    font-weight: 950 !important;
    direction: ltr !important;
    text-align: right !important;
}

body.woocommerce-account .rk-order-card-date {
    color: var(--rk-muted) !important;
    font-size: 12px !important;
    font-weight: 900 !important;
    white-space: nowrap !important;
    text-align: left !important;
}

body.woocommerce-account .rk-order-summary-trigger {
    width: 100% !important;
    border: 0 !important;
    background: transparent !important;
    padding: 13px 0 10px !important;
    margin: 0 !important;
    display: flex !important;
    align-items: flex-start !important;
    gap: 6px !important;
    text-align: right !important;
    cursor: pointer !important;
    line-height: 2 !important;
}

body.woocommerce-account .rk-order-summary-trigger:hover .rk-order-first-title {
    color: var(--rk-primary) !important;
}

body.woocommerce-account .rk-order-label {
    color: var(--rk-muted) !important;
    font-size: 12px !important;
    font-weight: 900 !important;
    margin-left: 2px !important;
    white-space: nowrap !important;
}

body.woocommerce-account .rk-order-first-title {
    color: var(--rk-text) !important;
    font-size: 13px !important;
    font-weight: 950 !important;
    line-height: 2 !important;
    transition: .18s ease !important;
}

body.woocommerce-account .rk-order-summary-trigger small {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 24px !important;
    padding: 2px 9px !important;
    border-radius: 999px !important;
    background: var(--rk-soft) !important;
    color: var(--rk-primary) !important;
    font-size: 11px !important;
    font-weight: 900 !important;
    white-space: nowrap !important;
}

body.woocommerce-account .rk-order-thumbs-row {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    flex-direction: row !important;
    gap: 0 !important;
    min-height: 48px !important;
    margin: 0 0 12px !important;
    padding-bottom: 12px !important;
    border-bottom: 1px solid var(--rk-border) !important;
}

body.woocommerce-account .rk-order-thumb {
    width: 46px !important;
    height: 46px !important;
    min-width: 46px !important;
    border-radius: 50% !important;
    overflow: hidden !important;
    background: #fff !important;
    border: 2px solid #fff !important;
    box-shadow: 0 0 0 1px var(--rk-border) !important;
    margin-right: -10px !important;
    position: relative !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-decoration: none !important;
}

body.woocommerce-account .rk-order-thumb:first-child {
    margin-right: 0 !important;
}

body.woocommerce-account .rk-order-thumb img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    border-radius: 50% !important;
    display: block !important;
}

body.woocommerce-account .rk-order-thumb-more {
    background: var(--rk-soft) !important;
    color: var(--rk-primary) !important;
    font-size: 12px !important;
    font-weight: 950 !important;
    z-index: 10 !important;
}

body.woocommerce-account .rk-order-total-row,
body.woocommerce-account .rk-order-tracking-row {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 4px !important;
    line-height: 2 !important;
    padding: 0 0 10px !important;
}

body.woocommerce-account .rk-order-total-value,
body.woocommerce-account .rk-order-tracking-row strong {
    color: var(--rk-text) !important;
    font-size: 13px !important;
    font-weight: 950 !important;
}

body.woocommerce-account .rk-order-total-value .woocommerce-Price-amount,
body.woocommerce-account .rk-order-total-value .woocommerce-Price-amount * {
    display: inline !important;
    white-space: nowrap !important;
    word-spacing: 0 !important;
    letter-spacing: 0 !important;
}

body.woocommerce-account .rk-order-actions-grid {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 10px !important;
    padding-top: 2px !important;
}

body.woocommerce-account .rk-order-action-btn {
    min-height: 44px !important;
    height: 44px !important;
    border-radius: 14px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-align: center !important;
    font-size: 13px !important;
    font-weight: 950 !important;
    text-decoration: none !important;
    box-sizing: border-box !important;
    margin: 0 !important;
    line-height: 1.7 !important;
    cursor: pointer !important;
}

body.woocommerce-account .rk-order-details-action {
    background: #fff !important;
    color: var(--rk-text) !important;
    border: 1px solid #d9d3df !important;
}

body.woocommerce-account .rk-order-status-action {
    background: var(--rk-success) !important;
    color: #fff !important;
    border: 1px solid var(--rk-success) !important;
}

body.woocommerce-account .rk-order-track-action {
    grid-column: 1 / -1 !important;
    background: #fff !important;
    color: var(--rk-text) !important;
    border: 1px solid #d9d3df !important;
}

body.woocommerce-account .rk-order-details-action:hover,
body.woocommerce-account .rk-order-track-action:hover {
    border-color: var(--rk-primary) !important;
    color: var(--rk-primary) !important;
}

body.woocommerce-account .rk-order-details-panel {
    margin-top: 14px !important;
    padding: 14px !important;
    border-radius: 16px !important;
    border: 1px solid var(--rk-border) !important;
    background: #fbfaff !important;
}

body.woocommerce-account .rk-order-details-title {
    color: var(--rk-primary) !important;
    font-size: 13px !important;
    font-weight: 950 !important;
    margin-bottom: 12px !important;
}

body.woocommerce-account .rk-order-items-detail-list {
    display: grid !important;
    gap: 10px !important;
}

body.woocommerce-account .rk-order-detail-item {
    display: grid !important;
    grid-template-columns: 46px minmax(0, 1fr) auto !important;
    gap: 10px !important;
    align-items: center !important;
    background: #fff !important;
    border: 1px solid var(--rk-border) !important;
    border-radius: 14px !important;
    padding: 10px !important;
}

body.woocommerce-account .rk-order-detail-thumb {
    width: 46px !important;
    height: 46px !important;
    border-radius: 14px !important;
    overflow: hidden !important;
    background: #fff !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-decoration: none !important;
}

body.woocommerce-account .rk-order-detail-thumb img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    display: block !important;
}

body.woocommerce-account .rk-order-detail-info {
    min-width: 0 !important;
}

body.woocommerce-account .rk-order-detail-info strong {
    display: block !important;
    color: var(--rk-text) !important;
    font-size: 12px !important;
    font-weight: 950 !important;
    line-height: 1.8 !important;
}

body.woocommerce-account .rk-order-detail-info span {
    display: block !important;
    color: var(--rk-muted) !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    margin-top: 2px !important;
}

body.woocommerce-account .rk-order-detail-price {
    color: var(--rk-text) !important;
    font-size: 12px !important;
    font-weight: 950 !important;
    white-space: nowrap !important;
}

body.woocommerce-account .rk-order-details-meta {
    display: grid !important;
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    gap: 10px !important;
    margin-top: 12px !important;
}

body.woocommerce-account .rk-order-details-meta div {
    background: #fff !important;
    border: 1px solid var(--rk-border) !important;
    border-radius: 12px !important;
    padding: 10px !important;
}

body.woocommerce-account .rk-order-details-meta span {
    display: block !important;
    color: var(--rk-muted) !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    margin-bottom: 5px !important;
}

body.woocommerce-account .rk-order-details-meta strong {
    display: block !important;
    color: var(--rk-text) !important;
    font-size: 12px !important;
    font-weight: 950 !important;
    word-break: break-word !important;
}

body.woocommerce-account .rk-order-full-link {
    margin-top: 12px !important;
    min-height: 40px !important;
    border-radius: 12px !important;
    border: 1px solid var(--rk-primary) !important;
    color: var(--rk-primary) !important;
    background: #fff !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-decoration: none !important;
    font-size: 12px !important;
    font-weight: 950 !important;
}

@media(max-width: 768px) {
    body.woocommerce-account .rk-orders-card {
        padding: 16px !important;
    }

    body.woocommerce-account .rk-order-card {
        padding: 14px !important;
        border-radius: 18px !important;
    }

    body.woocommerce-account .rk-order-card-number {
        font-size: 14px !important;
    }

    body.woocommerce-account .rk-order-card-date {
        font-size: 12px !important;
    }

    body.woocommerce-account .rk-order-summary-trigger {
        flex-wrap: wrap !important;
        padding-top: 12px !important;
    }

    body.woocommerce-account .rk-order-summary-trigger small {
        margin-right: auto !important;
    }

    body.woocommerce-account .rk-order-thumbs-row {
        justify-content: flex-end !important;
    }

    body.woocommerce-account .rk-order-details-meta {
        grid-template-columns: 1fr !important;
    }

    body.woocommerce-account .rk-order-detail-item {
        grid-template-columns: 42px minmax(0, 1fr) !important;
    }

    body.woocommerce-account .rk-order-detail-price {
        grid-column: 2 !important;
    }
}
/* === RK Orders Final Fix: Desktop / Mobile Actions + Right Thumbnails === */

/* فونت از قالب خوانده شود */
body.woocommerce-account .rk-orders-card,
body.woocommerce-account .rk-orders-card *,
body.woocommerce-account .rk-order-card,
body.woocommerce-account .rk-order-card * {
    font-family: inherit !important;
}

/* ردیف عکس‌ها همیشه از سمت راست شروع شود */
body.woocommerce-account .rk-orders-card .rk-order-thumbs-row {
    display: flex !important;
    flex-direction: row !important;
    direction: rtl !important;
    justify-content: flex-start !important;
    align-items: center !important;
    gap: 0 !important;
    width: 100% !important;
    margin-top: 12px !important;
    text-align: right !important;
}

body.woocommerce-account .rk-orders-card .rk-order-thumb {
    margin: 0 !important;
    margin-right: -10px !important;
    margin-left: 0 !important;
    flex: 0 0 46px !important;
}

body.woocommerce-account .rk-orders-card .rk-order-thumb:first-child {
    margin-right: 0 !important;
}

/* دسکتاپ: هر ۳ دکمه کنار هم، نه فول ویدث */
@media (min-width: 769px) {
    body.woocommerce-account .rk-orders-card .rk-order-actions-grid {
        display: flex !important;
        flex-direction: row !important;
        direction: rtl !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 10px !important;
        width: auto !important;
        padding-top: 12px !important;
    }

    body.woocommerce-account .rk-orders-card .rk-order-action-btn,
    body.woocommerce-account .rk-orders-card .rk-order-view-action,
    body.woocommerce-account .rk-orders-card .rk-order-status-action,
    body.woocommerce-account .rk-orders-card .rk-order-track-action {
        width: auto !important;
        max-width: none !important;
        min-width: 118px !important;
        height: 40px !important;
        min-height: 40px !important;
        padding: 7px 16px !important;
        flex: 0 0 auto !important;
        grid-column: auto !important;
        box-sizing: border-box !important;
    }

    body.woocommerce-account .rk-orders-card .rk-order-track-action {
        min-width: 170px !important;
    }
}

/* موبایل: دو دکمه کنار هم، رهگیری فول ویدث */
@media (max-width: 768px) {
    body.woocommerce-account .rk-orders-card .rk-order-actions-grid {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        direction: rtl !important;
        gap: 10px !important;
        width: 100% !important;
        padding-top: 12px !important;
    }

    body.woocommerce-account .rk-orders-card .rk-order-action-btn,
    body.woocommerce-account .rk-orders-card .rk-order-view-action,
    body.woocommerce-account .rk-orders-card .rk-order-status-action,
    body.woocommerce-account .rk-orders-card .rk-order-track-action {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        height: 44px !important;
        min-height: 44px !important;
        padding: 8px 10px !important;
        box-sizing: border-box !important;
    }

    body.woocommerce-account .rk-orders-card .rk-order-track-action {
        grid-column: 1 / -1 !important;
    }
}

/* === Order Status Timeline inside accordion === */
body.woocommerce-account .rk-order-status-timeline {
    position: relative !important;
    display: grid !important;
    grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
    gap: 0 !important;
    margin: 14px 0 !important;
    padding: 14px 8px 10px !important;
    border: 1px solid var(--rk-border) !important;
    border-radius: 14px !important;
    background: #fff !important;
}

body.woocommerce-account .rk-order-status-step {
    position: relative !important;
    text-align: center !important;
    color: var(--rk-muted) !important;
    font-size: 11px !important;
    font-weight: 800 !important;
}

body.woocommerce-account .rk-order-status-step::before {
    content: "" !important;
    position: absolute !important;
    top: 15px !important;
    right: 0 !important;
    left: 0 !important;
    height: 1px !important;
    background: #dccded !important;
    z-index: 0 !important;
}

body.woocommerce-account .rk-order-status-step:first-child::before { right: 50% !important; }
body.woocommerce-account .rk-order-status-step:last-child::before { left: 50% !important; }

body.woocommerce-account .rk-order-status-dot {
    position: relative !important;
    z-index: 2 !important;
    width: 30px !important;
    height: 30px !important;
    margin: 0 auto 7px !important;
    border-radius: 50% !important;
    background: #fff !important;
    border: 1px solid #dccded !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

body.woocommerce-account .rk-order-status-dot svg {
    width: 17px !important;
    height: 17px !important;
    stroke: #fff !important;
    fill: none !important;
    stroke-width: 2.2 !important;
}

body.woocommerce-account .rk-order-status-step.is-active {
    color: var(--rk-success-dark) !important;
    font-weight: 900 !important;
}

body.woocommerce-account .rk-order-status-step.is-active .rk-order-status-dot {
    background: var(--rk-success) !important;
    border-color: var(--rk-success) !important;
}

body.woocommerce-account .rk-order-status-step small {
    display: block !important;
    line-height: 1.7 !important;
    font-size: 10.5px !important;
    font-weight: inherit !important;
}

/* === Next Purchase === */
.rk-next-purchase-card .rk-card-title svg,
.rk-next-purchase-card svg {
    stroke: var(--rk-primary);
    fill: none;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.rk-next-purchase-intro {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 14px !important;
    padding: 14px !important;
    border: 1px solid var(--rk-border) !important;
    border-radius: 14px !important;
    background: #fbfaff !important;
    margin-bottom: 18px !important;
}

.rk-next-purchase-intro p {
    margin: 0 !important;
    color: var(--rk-muted) !important;
    font-size: 13px !important;
    line-height: 2 !important;
}

.rk-next-add-all-btn,
.rk-next-add-to-cart-btn,
.rk-next-remove-btn,
.rk-move-next-btn {
    font-family: inherit !important;
}

.rk-next-add-all-btn {
    min-height: 40px !important;
    border: 1px solid var(--rk-primary) !important;
    color: var(--rk-primary) !important;
    background: #fff !important;
    border-radius: 12px !important;
    padding: 8px 14px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 7px !important;
    font-size: 12px !important;
    font-weight: 900 !important;
    cursor: pointer !important;
    white-space: nowrap !important;
}

.rk-next-add-all-btn svg {
    width: 18px !important;
    height: 18px !important;
}

.rk-next-purchase-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)) !important;
    gap: 14px !important;
}

.rk-next-purchase-item {
    display: grid !important;
    grid-template-columns: 86px minmax(0, 1fr) !important;
    gap: 12px !important;
    align-items: center !important;
    border: 1px solid var(--rk-border) !important;
    border-radius: 16px !important;
    padding: 14px !important;
    background: #fff !important;
}

.rk-next-purchase-image {
    width: 86px !important;
    height: 86px !important;
    border-radius: 14px !important;
    border: 1px solid var(--rk-border) !important;
    overflow: hidden !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: #fff !important;
}

.rk-next-purchase-image img {
    width: 100% !important;
    height: 100% !important;
    object-fit: contain !important;
    display: block !important;
}

.rk-next-purchase-info h4 {
    margin: 0 0 8px !important;
    font-size: 13px !important;
    line-height: 2 !important;
    font-weight: 900 !important;
}

.rk-next-purchase-info h4 a {
    color: var(--rk-text) !important;
    text-decoration: none !important;
}

.rk-next-purchase-price {
    color: var(--rk-danger) !important;
    font-size: 13px !important;
    font-weight: 900 !important;
    margin-bottom: 12px !important;
}

.rk-next-purchase-price del {
    color: var(--rk-muted) !important;
    margin-left: 7px !important;
    font-weight: 700 !important;
}

.rk-next-purchase-price ins {
    color: var(--rk-danger) !important;
    text-decoration: none !important;
}

.rk-next-purchase-actions {
    display: grid !important;
    grid-template-columns: 1fr auto !important;
    gap: 8px !important;
}

.rk-next-add-to-cart-btn,
.rk-next-remove-btn {
    min-height: 38px !important;
    border-radius: 11px !important;
    font-size: 12px !important;
    font-weight: 900 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    cursor: pointer !important;
}

.rk-next-add-to-cart-btn {
    border: 1px solid var(--rk-primary) !important;
    color: var(--rk-primary) !important;
    background: #fff !important;
}

.rk-next-add-to-cart-btn svg {
    width: 16px !important;
    height: 16px !important;
}

.rk-next-remove-btn {
    border: 1px solid var(--rk-border) !important;
    color: var(--rk-muted) !important;
    background: #fff !important;
    padding: 0 12px !important;
}

.rk-move-to-next-purchase {
    margin-top: 8px !important;
}

.rk-move-next-btn {
    background: transparent !important;
    color: var(--rk-primary) !important;
    border: 0 !important;
    padding: 0 !important;
    font-size: 12px !important;
    font-weight: 900 !important;
    cursor: pointer !important;
}

.rk-next-toast {
    position: fixed !important;
    right: 18px !important;
    bottom: 90px !important;
    min-width: 240px !important;
    max-width: 360px !important;
    padding: 12px 16px !important;
    border-radius: 14px !important;
    color: #fff !important;
    background: var(--rk-success) !important;
    font-size: 13px !important;
    font-weight: 800 !important;
    line-height: 1.8 !important;
    z-index: 100000 !important;
    opacity: 0 !important;
    transform: translateY(12px) !important;
    pointer-events: none !important;
    transition: .2s ease !important;
    box-shadow: 0 12px 32px rgba(0,0,0,.18) !important;
}

.rk-next-toast.is-visible {
    opacity: 1 !important;
    transform: translateY(0) !important;
}

.rk-next-toast.is-error {
    background: var(--rk-danger) !important;
}

@media(max-width: 768px) {
    .rk-next-purchase-intro {
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .rk-next-purchase-grid {
        grid-template-columns: 1fr !important;
    }

    .rk-next-purchase-item {
        grid-template-columns: 76px minmax(0, 1fr) !important;
    }

    .rk-next-purchase-image {
        width: 76px !important;
        height: 76px !important;
    }

    body.woocommerce-account .rk-order-status-timeline {
        padding: 12px 4px 8px !important;
        overflow-x: auto !important;
    }

    body.woocommerce-account .rk-order-status-step small {
        font-size: 9.5px !important;
    }

    body.woocommerce-account .rk-order-status-dot {
        width: 26px !important;
        height: 26px !important;
    }
}



/* === تنظیمات قابل تغییر از پنل ریتاهاست === */
body.woocommerce-account .rk-card-title h3,
body.woocommerce-account .woocommerce-MyAccount-navigation:before {
    font-size: var(--rk-font-title) !important;
}

body.woocommerce-account .rk-profile-name p,
body.woocommerce-account .rk-info-grid strong,
body.woocommerce-account .rk-address-line,
body.woocommerce-account .rk-order-first-title,
body.woocommerce-account .rk-order-total-value,
body.woocommerce-account .rk-order-tracking-row strong {
    font-size: var(--rk-font-text) !important;
}

body.woocommerce-account .rk-stat small,
body.woocommerce-account .rk-info-grid label,
body.woocommerce-account .rk-order-label,
body.woocommerce-account .rk-order-card-date,
body.woocommerce-account .rk-order-detail-info span {
    font-size: var(--rk-font-small) !important;
}

body.woocommerce-account .rk-btn,
body.woocommerce-account .rk-order-action-btn,
body.woocommerce-account .rk-order-view-btn,
body.woocommerce-account .rk-order-full-link,
body.woocommerce-account .rk-next-purchase-actions button,
body.woocommerce-account .rk-next-purchase-add-all,
.woocommerce-cart-form .rk-move-btn {
    font-size: var(--rk-font-button) !important;
}

body.woocommerce-account .rk-card-title svg {
    width: var(--rk-icon-size) !important;
    height: var(--rk-icon-size) !important;
}

</style>
    <?php
});

