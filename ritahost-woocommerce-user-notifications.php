<?php
/**
 * Plugin Name: RitaHost WooCommerce User Notifications
 * Description: Adds a secure notification center to WooCommerce My Account for welcome, order status, and tracking messages.
 * Version: 3.2.0
 * Author: RitaHost
 * Text Domain: ritahost
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('RKNT_OPTION')) {
    define('RKNT_OPTION', 'ritahost_notification_settings');
}

function rknt_default_settings() {
    return [
        'menu_label_fa' => 'پیام‌ها و اعلان‌ها',
        'menu_label_en' => 'Messages & Notifications',
        'welcome'       => '1',
        'orders'        => '1',
        'tracking'      => '1',
        'max_items'     => 120,
    ];
}

function rknt_settings() {
    return wp_parse_args((array) get_option(RKNT_OPTION, []), rknt_default_settings());
}

function rknt_setting_enabled($key) {
    $settings = rknt_settings();
    return !empty($settings[$key]);
}

function rknt_menu_label() {
    $settings = rknt_settings();
    $locale = get_locale();
    return 0 === strpos(strtolower((string) $locale), 'fa') ? $settings['menu_label_fa'] : $settings['menu_label_en'];
}

if (!defined('RKNT_META_KEY')) {
    define('RKNT_META_KEY', '_ritahost_notifications_v3');
}

if (!defined('RKNT_OLD_META_KEY')) {
    define('RKNT_OLD_META_KEY', '_rk_user_notifications');
}

if (!defined('RKNT_WELCOME_DONE_META')) {
    define('RKNT_WELCOME_DONE_META', '_ritahost_notifications_welcome_done');
}

/* Legacy keys keep existing user data migratable without exposing old branding. */
if (!defined('RKNT_LEGACY_META_KEY')) {
    define('RKNT_LEGACY_META_KEY', '_ru' . 'bikala_notifications_v2');
}

if (!defined('RKNT_LEGACY_WELCOME_DONE_META')) {
    define('RKNT_LEGACY_WELCOME_DONE_META', '_ru' . 'bikala_notifications_welcome_done');
}

if (!defined('RKNT_MAX_ITEMS')) {
    $rknt_initial_settings = rknt_settings();
    define('RKNT_MAX_ITEMS', max(10, min(500, absint($rknt_initial_settings['max_items']))));
    unset($rknt_initial_settings);
}

add_action('admin_init', function () {
    register_setting('ritahost_notifications_group', RKNT_OPTION, [
        'type'              => 'array',
        'default'           => rknt_default_settings(),
        'sanitize_callback' => function ($input) {
            return [
                'menu_label_fa' => sanitize_text_field($input['menu_label_fa'] ?? 'پیام‌ها و اعلان‌ها'),
                'menu_label_en' => sanitize_text_field($input['menu_label_en'] ?? 'Messages & Notifications'),
                'welcome'       => empty($input['welcome']) ? '0' : '1',
                'orders'        => empty($input['orders']) ? '0' : '1',
                'tracking'      => empty($input['tracking']) ? '0' : '1',
                'max_items'     => max(10, min(500, absint($input['max_items'] ?? 120))),
            ];
        },
    ]);
});

function rknt_render_admin_settings() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ritahost'));
    }
    $settings = rknt_settings();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('تنظیمات اعلان‌های کاربران', 'User Notification Settings') : (is_rtl() ? 'تنظیمات اعلان‌های کاربران' : 'User Notification Settings')); ?></h1>
        <p><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('پیش‌فرض‌ها تمام اعلان‌های فعلی را فعال نگه می‌دارند.', 'Defaults keep all current notification types enabled.') : (is_rtl() ? 'پیش‌فرض‌ها تمام اعلان‌های فعلی را فعال نگه می‌دارند.' : 'Defaults keep all current notification types enabled.')); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('ritahost_notifications_group'); ?>
            <table class="form-table" role="presentation">
                <tr><th><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('عنوان فارسی منو', 'Persian menu label') : (is_rtl() ? 'عنوان فارسی منو' : 'Persian menu label')); ?></th><td><input class="regular-text" name="<?php echo esc_attr(RKNT_OPTION); ?>[menu_label_fa]" value="<?php echo esc_attr($settings['menu_label_fa']); ?>"></td></tr>
                <tr><th><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('عنوان انگلیسی منو', 'English menu label') : (is_rtl() ? 'عنوان انگلیسی منو' : 'English menu label')); ?></th><td><input class="regular-text" name="<?php echo esc_attr(RKNT_OPTION); ?>[menu_label_en]" value="<?php echo esc_attr($settings['menu_label_en']); ?>"></td></tr>
                <tr><th><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('انواع اعلان', 'Notification types') : (is_rtl() ? 'انواع اعلان' : 'Notification types')); ?></th><td>
                    <label><input type="checkbox" name="<?php echo esc_attr(RKNT_OPTION); ?>[welcome]" value="1" <?php checked($settings['welcome'], '1'); ?>> <?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('پیام خوش‌آمدگویی', 'Welcome message') : (is_rtl() ? 'پیام خوش‌آمدگویی' : 'Welcome message')); ?></label><br>
                    <label><input type="checkbox" name="<?php echo esc_attr(RKNT_OPTION); ?>[orders]" value="1" <?php checked($settings['orders'], '1'); ?>> <?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('ثبت و تغییر وضعیت سفارش', 'Order creation and status changes') : (is_rtl() ? 'ثبت و تغییر وضعیت سفارش' : 'Order creation and status changes')); ?></label><br>
                    <label><input type="checkbox" name="<?php echo esc_attr(RKNT_OPTION); ?>[tracking]" value="1" <?php checked($settings['tracking'], '1'); ?>> <?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('کد رهگیری', 'Tracking updates') : (is_rtl() ? 'کد رهگیری' : 'Tracking updates')); ?></label>
                </td></tr>
                <tr><th><?php echo esc_html(function_exists('ritahost_admin_text') ? ritahost_admin_text('حداکثر اعلان برای هر کاربر', 'Maximum notifications per user') : (is_rtl() ? 'حداکثر اعلان برای هر کاربر' : 'Maximum notifications per user')); ?></th><td><input type="number" min="10" max="500" name="<?php echo esc_attr(RKNT_OPTION); ?>[max_items]" value="<?php echo esc_attr($settings['max_items']); ?>"></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('admin_menu', function () {
    $page_title = function_exists('ritahost_admin_text') ? ritahost_admin_text('تنظیمات اعلان‌های کاربران', 'User Notification Settings') : (is_rtl() ? 'تنظیمات اعلان‌های کاربران' : 'User Notification Settings');
    $menu_title = function_exists('ritahost_admin_text') ? ritahost_admin_text('اعلان‌های کاربران', 'User Notifications') : (is_rtl() ? 'اعلان‌های کاربران' : 'User Notifications');
    if (function_exists('ritahost_register_admin_tool')) {
        add_submenu_page('ritahost-panel', $page_title, $menu_title, 'manage_options', 'ritahost-user-notifications', 'rknt_render_admin_settings');
    } else {
        add_options_page($page_title, $menu_title, 'manage_options', 'ritahost-user-notifications', 'rknt_render_admin_settings');
    }
}, 20);

if (function_exists('ritahost_register_admin_tool')) {
    ritahost_register_admin_tool('ritahost-user-notifications', 'اعلان‌های کاربران', 'User Notifications', 'اعلان‌های خوش‌آمدگویی، سفارش و کد رهگیری حساب کاربری را مدیریت می‌کند.', 'Manages welcome, order, and tracking notifications in WooCommerce My Account.', 'manage_options');
}

/* Dynamic site branding */
function rknt_site_name() {
    $name = get_bloginfo('name');
    return $name ? $name : 'سایت شما';
}

/* ------------------------------------------------------------
 * Endpoint + menu
 * ------------------------------------------------------------ */
add_action('init', function () {
    add_rewrite_endpoint('notifications', EP_ROOT | EP_PAGES);
}, 1);

add_filter('woocommerce_account_menu_items', function ($items) {
    if (!is_array($items)) {
        return $items;
    }

    if (isset($items['notifications'])) {
        $items['notifications'] = rknt_menu_label();
        return $items;
    }

    $new_items = [];
    $inserted  = false;

    foreach ($items as $key => $label) {
        $new_items[$key] = $label;

        if (!$inserted && in_array($key, ['wishlist', 'edit-address', 'orders'], true)) {
            $new_items['notifications'] = rknt_menu_label();
            $inserted = true;
        }
    }

    if (!$inserted) {
        $new_items['notifications'] = rknt_menu_label();
    }

    return $new_items;
}, 1005);

function rknt_is_account_page_safe() {
    return function_exists('is_account_page') && is_account_page();
}

function rknt_is_notifications_endpoint() {
    if (!rknt_is_account_page_safe()) {
        return false;
    }

    global $wp;

    if (isset($wp->query_vars['notifications'])) {
        return true;
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    return (bool) preg_match('~/notifications/?($|\?)~', $uri);
}

/* ------------------------------------------------------------
 * Data helpers
 * ------------------------------------------------------------ */
function rknt_normalize_notification($item) {
    if (!is_array($item)) {
        return null;
    }

    $id = isset($item['id']) ? sanitize_text_field((string) $item['id']) : '';
    if ($id === '') {
        $base = wp_json_encode($item, JSON_UNESCAPED_UNICODE);
        $id = 'rknt_' . md5($base ?: wp_generate_uuid4());
    }

    $created_at = '';
    if (!empty($item['created_at'])) {
        $created_at = sanitize_text_field((string) $item['created_at']);
    } elseif (!empty($item['date'])) {
        $created_at = sanitize_text_field((string) $item['date']);
    } elseif (!empty($item['time'])) {
        $time = is_numeric($item['time']) ? absint($item['time']) : strtotime((string) $item['time']);
        $created_at = $time ? date('Y-m-d H:i:s', $time) : current_time('mysql');
    } else {
        $created_at = current_time('mysql');
    }

    $type = isset($item['type']) ? sanitize_key((string) $item['type']) : 'general';
    $title = isset($item['title']) ? sanitize_text_field((string) $item['title']) : (isset($item['subject']) ? sanitize_text_field((string) $item['subject']) : 'اعلان');

    // Keep existing stored welcome notifications in sync with the current WordPress Site Title.
    if ($type === 'welcome') {
        $title = 'به ' . rknt_site_name() . ' خوش آمدید';
    }

    return [
        'id'         => $id,
        'type'       => $type,
        'title'      => $title,
        'message'    => isset($item['message']) ? wp_strip_all_tags((string) $item['message']) : (isset($item['text']) ? wp_strip_all_tags((string) $item['text']) : (isset($item['content']) ? wp_strip_all_tags((string) $item['content']) : '')),
        'url'        => !empty($item['url']) ? esc_url_raw((string) $item['url']) : (!empty($item['link']) ? esc_url_raw((string) $item['link']) : ''),
        'order_id'   => !empty($item['order_id']) ? absint($item['order_id']) : 0,
        'unique_key' => !empty($item['unique_key']) ? sanitize_text_field((string) $item['unique_key']) : '',
        'created_at' => $created_at,
        'read'       => !empty($item['read']) || !empty($item['is_read']),
    ];
}

function rknt_get_notifications($user_id) {
    $user_id = absint($user_id);
    if (!$user_id) {
        return [];
    }

    $items = get_user_meta($user_id, RKNT_META_KEY, true);
    $items = is_array($items) ? $items : [];

    $old_items = get_user_meta($user_id, RKNT_OLD_META_KEY, true);
    $old_items = is_array($old_items) ? $old_items : [];

    $legacy_items = get_user_meta($user_id, RKNT_LEGACY_META_KEY, true);
    $legacy_items = is_array($legacy_items) ? $legacy_items : [];

    if (isset($old_items['items']) && is_array($old_items['items'])) {
        $old_items = $old_items['items'];
    }

    if (isset($legacy_items['items']) && is_array($legacy_items['items'])) {
        $legacy_items = $legacy_items['items'];
    }

    $merged = [];
    $seen   = [];

    foreach (array_merge($items, $old_items, $legacy_items) as $item) {
        $normalized = rknt_normalize_notification($item);
        if (!$normalized) {
            continue;
        }

        $key = $normalized['unique_key'] ?: $normalized['id'];
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $merged[] = $normalized;
    }

    usort($merged, function ($a, $b) {
        $at = strtotime($a['created_at'] ?? '') ?: 0;
        $bt = strtotime($b['created_at'] ?? '') ?: 0;
        return $bt <=> $at;
    });

    $merged = array_slice($merged, 0, RKNT_MAX_ITEMS);

    if ($merged !== $items) {
        update_user_meta($user_id, RKNT_META_KEY, $merged);
    }

    return $merged;
}

function rknt_save_notifications($user_id, $items) {
    $user_id = absint($user_id);
    if (!$user_id) {
        return false;
    }

    $clean = [];
    foreach ((array) $items as $item) {
        $normalized = rknt_normalize_notification($item);
        if ($normalized) {
            $clean[] = $normalized;
        }
    }

    usort($clean, function ($a, $b) {
        $at = strtotime($a['created_at'] ?? '') ?: 0;
        $bt = strtotime($b['created_at'] ?? '') ?: 0;
        return $bt <=> $at;
    });

    $clean = array_slice($clean, 0, RKNT_MAX_ITEMS);
    update_user_meta($user_id, RKNT_META_KEY, $clean);
    return true;
}

function rknt_add_notification($user_id, $type, $title, $message, $url = '', $order_id = 0, $unique_key = '') {
    $user_id = absint($user_id);
    if (!$user_id) {
        return false;
    }

    $items = rknt_get_notifications($user_id);
    $unique_key = sanitize_text_field((string) $unique_key);

    if ($unique_key !== '') {
        foreach ($items as $item) {
            if (!empty($item['unique_key']) && hash_equals((string) $item['unique_key'], $unique_key)) {
                return false;
            }
        }
    }

    $items[] = [
        'id'         => 'rknt_' . wp_generate_uuid4(),
        'type'       => sanitize_key((string) $type),
        'title'      => sanitize_text_field((string) $title),
        'message'    => wp_strip_all_tags((string) $message),
        'url'        => esc_url_raw((string) $url),
        'order_id'   => absint($order_id),
        'unique_key' => $unique_key,
        'created_at' => current_time('mysql'),
        'read'       => false,
    ];

    return rknt_save_notifications($user_id, $items);
}

function rknt_unread_count($user_id) {
    $count = 0;
    foreach (rknt_get_notifications($user_id) as $item) {
        if (empty($item['read'])) {
            $count++;
        }
    }
    return $count;
}

function rknt_mark_all_read($user_id) {
    $items = rknt_get_notifications($user_id);
    foreach ($items as &$item) {
        $item['read'] = true;
    }
    unset($item);
    return rknt_save_notifications($user_id, $items);
}

function rknt_mark_one_read($user_id, $notification_id) {
    $notification_id = sanitize_text_field((string) $notification_id);
    $items = rknt_get_notifications($user_id);

    foreach ($items as &$item) {
        if (!empty($item['id']) && hash_equals((string) $item['id'], $notification_id)) {
            $item['read'] = true;
            break;
        }
    }
    unset($item);

    return rknt_save_notifications($user_id, $items);
}

function rknt_delete_all($user_id) {
    delete_user_meta(absint($user_id), RKNT_META_KEY);
    delete_user_meta(absint($user_id), RKNT_OLD_META_KEY);
    delete_user_meta(absint($user_id), RKNT_LEGACY_META_KEY);
}

/* Backward compatible helper for older badge snippets. */
if (!function_exists('rknc_unread_count')) {
    function rknc_unread_count($user_id) {
        return rknt_unread_count($user_id);
    }
}

/* ------------------------------------------------------------
 * Notification producers
 * ------------------------------------------------------------ */
function rknt_add_welcome_if_needed($user_id) {
    if (!rknt_setting_enabled('welcome')) {
        return;
    }
    $user_id = absint($user_id);
    if (!$user_id || get_user_meta($user_id, RKNT_WELCOME_DONE_META, true)) {
        return;
    }

    if (get_user_meta($user_id, RKNT_LEGACY_WELCOME_DONE_META, true)) {
        update_user_meta($user_id, RKNT_WELCOME_DONE_META, 1);
        return;
    }

    rknt_add_notification(
        $user_id,
        'welcome',
        'به ' . rknt_site_name() . ' خوش آمدید',
        'از این بخش می‌توانید پیام‌های مهم حساب، تغییر وضعیت سفارش‌ها و کدهای رهگیری را دنبال کنید.',
        function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('dashboard') : '',
        0,
        'welcome-user-' . $user_id
    );

    update_user_meta($user_id, RKNT_WELCOME_DONE_META, 1);
}

add_action('user_register', function ($user_id) {
    rknt_add_welcome_if_needed($user_id);
}, 10, 1);

add_action('wp', function () {
    if (is_user_logged_in() && rknt_is_account_page_safe()) {
        rknt_add_welcome_if_needed(get_current_user_id());
    }
}, 2);

add_action('woocommerce_checkout_order_processed', function ($order_id, $posted_data = [], $order = null) {
    if (!rknt_setting_enabled('orders') || !function_exists('wc_get_order')) {
        return;
    }

    if (!is_a($order, 'WC_Order')) {
        $order = wc_get_order($order_id);
    }

    if (!is_a($order, 'WC_Order')) {
        return;
    }

    $user_id = $order->get_user_id();
    if (!$user_id) {
        return;
    }

    rknt_add_notification(
        $user_id,
        'order',
        'سفارش شما ثبت شد',
        'سفارش شماره #' . $order->get_id() . ' با موفقیت ثبت شد.',
        $order->get_view_order_url(),
        $order->get_id(),
        'order-created-' . $order->get_id()
    );
}, 10, 3);

add_action('woocommerce_order_status_changed', function ($order_id, $old_status, $new_status, $order = null) {
    if (!rknt_setting_enabled('orders') || !function_exists('wc_get_order')) {
        return;
    }

    if (!is_a($order, 'WC_Order')) {
        $order = wc_get_order($order_id);
    }

    if (!is_a($order, 'WC_Order')) {
        return;
    }

    $user_id = $order->get_user_id();
    if (!$user_id) {
        return;
    }

    $label = function_exists('wc_get_order_status_name') ? wc_get_order_status_name($new_status) : $new_status;

    rknt_add_notification(
        $user_id,
        'order_status',
        'وضعیت سفارش تغییر کرد',
        'سفارش شماره #' . $order->get_id() . ' به وضعیت «' . $label . '» تغییر کرد.',
        $order->get_view_order_url(),
        $order->get_id(),
        'order-status-' . $order->get_id() . '-' . sanitize_key($new_status)
    );
}, 10, 4);

function rknt_tracking_meta_keys() {
    $keys = [
        '_tracking_number',
        'tracking_number',
        '_tracking_code',
        'tracking_code',
        '_post_tracking_number',
        'post_tracking_number',
        '_novin_tracking_code',
        'novin_tracking_code',
        '_novin_tracking_number',
        'novin_tracking_number',
        '_pisol_tracking_id',
        'pisol_tracking_id',
        '_ywot_tracking_code',
        'ywot_tracking_code',
        '_aftership_tracking_number',
        'aftership_tracking_number',
        '_wc_shipment_tracking_items',
    ];

    return apply_filters('rknt_tracking_meta_keys', $keys);
}

function rknt_extract_tracking_value($value) {
    if (is_array($value) || is_object($value)) {
        $encoded = wp_json_encode($value, JSON_UNESCAPED_UNICODE);
        $value = $encoded ?: '';
    }

    $value = trim(wp_strip_all_tags((string) $value));
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strlen') && mb_strlen($value) > 120) {
        $value = mb_substr($value, 0, 120) . '...';
    } elseif (strlen($value) > 120) {
        $value = substr($value, 0, 120) . '...';
    }

    return $value;
}

function rknt_maybe_add_tracking_notification($order_id, $meta_key, $meta_value) {
    if (!rknt_setting_enabled('tracking') || !function_exists('wc_get_order') || !in_array($meta_key, rknt_tracking_meta_keys(), true)) {
        return;
    }

    $order_id = absint($order_id);
    if (!$order_id) {
        return;
    }

    $tracking_value = rknt_extract_tracking_value($meta_value);
    if ($tracking_value === '') {
        return;
    }

    $order = wc_get_order($order_id);
    if (!is_a($order, 'WC_Order')) {
        return;
    }

    $user_id = $order->get_user_id();
    if (!$user_id) {
        return;
    }

    rknt_add_notification(
        $user_id,
        'tracking',
        'کد رهگیری سفارش ثبت شد',
        'کد رهگیری سفارش شماره #' . $order->get_id() . ' ثبت شد: ' . $tracking_value,
        $order->get_view_order_url(),
        $order->get_id(),
        'tracking-' . $order->get_id() . '-' . md5($meta_key . '|' . $tracking_value)
    );
}

function rknt_maybe_add_tracking_from_post_meta($meta_id, $object_id, $meta_key, $meta_value) {
    if (get_post_type($object_id) !== 'shop_order') {
        return;
    }

    rknt_maybe_add_tracking_notification($object_id, (string) $meta_key, $meta_value);
}
add_action('added_post_meta', 'rknt_maybe_add_tracking_from_post_meta', 10, 4);
add_action('updated_post_meta', 'rknt_maybe_add_tracking_from_post_meta', 10, 4);

add_action('woocommerce_update_order', function ($order_id) {
    if (!function_exists('wc_get_order')) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!is_a($order, 'WC_Order')) {
        return;
    }

    foreach (rknt_tracking_meta_keys() as $key) {
        $value = $order->get_meta($key, true);
        if ($value !== '' && $value !== null) {
            rknt_maybe_add_tracking_notification($order_id, $key, $value);
        }
    }
}, 20, 1);

/* ------------------------------------------------------------
 * Actions: mark read / delete
 * ------------------------------------------------------------ */
add_action('template_redirect', function () {
    if (!is_user_logged_in() || !rknt_is_account_page_safe() || empty($_GET['rknt_action'])) {
        return;
    }

    $action = sanitize_key(wp_unslash($_GET['rknt_action']));
    $nonce  = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

    if (!wp_verify_nonce($nonce, 'rknt_action')) {
        wp_safe_redirect(function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('notifications') : remove_query_arg(['rknt_action', 'id', '_wpnonce']));
        exit;
    }

    $user_id = get_current_user_id();

    if ($action === 'read_all') {
        rknt_mark_all_read($user_id);
    } elseif ($action === 'delete_all') {
        rknt_delete_all($user_id);
    } elseif ($action === 'read_one' && !empty($_GET['id'])) {
        rknt_mark_one_read($user_id, sanitize_text_field(wp_unslash($_GET['id'])));
    }

    wp_safe_redirect(function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('notifications') : remove_query_arg(['rknt_action', 'id', '_wpnonce']));
    exit;
}, 1);

/* ------------------------------------------------------------
 * Hard override endpoint content
 * This prevents the old account-panel anonymous notifications output from flashing/disappearing.
 * ------------------------------------------------------------ */
add_action('wp', function () {
    if (!is_user_logged_in() || !rknt_is_account_page_safe()) {
        return;
    }

    remove_all_actions('woocommerce_account_notifications_endpoint');
    add_action('woocommerce_account_notifications_endpoint', 'rknt_render_notifications_page', 1);

    if (rknt_is_notifications_endpoint()) {
        remove_action('woocommerce_account_content', 'woocommerce_account_content');
        add_action('woocommerce_account_content', 'rknt_render_notifications_page', 1);
    }
}, 9999);

/* Fallback if another plugin restores the endpoint action later. */
add_action('woocommerce_account_notifications_endpoint', 'rknt_render_notifications_page', 1);

function rknt_icon($type) {
    $icons = [
        'welcome'      => '✓',
        'order'        => '▣',
        'order_status' => '↻',
        'tracking'     => '⌖',
        'discount'     => '%',
        'wishlist'     => '♡',
        'warning'      => '!',
        'general'      => '♧',
    ];

    return $icons[$type] ?? '♧';
}

function rknt_format_date($created_at) {
    $ts = strtotime((string) $created_at);
    if (!$ts) {
        return esc_html((string) $created_at);
    }

    return esc_html(date_i18n('Y/m/d - H:i', $ts));
}

function rknt_profile_missing_items($user_id) {
    $missing = [];

    if (!get_user_meta($user_id, 'first_name', true)) {
        $missing[] = 'نام';
    }
    if (!get_user_meta($user_id, 'last_name', true)) {
        $missing[] = 'نام خانوادگی';
    }
    if (!get_user_meta($user_id, 'billing_phone', true)) {
        $missing[] = 'شماره موبایل';
    }

    return $missing;
}

function rknt_render_notifications_page() {
    if (!is_user_logged_in()) {
        return;
    }

    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;

    $user_id = get_current_user_id();
    rknt_add_welcome_if_needed($user_id);

    $items   = rknt_get_notifications($user_id);
    $unread  = rknt_unread_count($user_id);
    $missing = rknt_profile_missing_items($user_id);

    $endpoint = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('notifications') : '';
    $edit_account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('edit-account') : '#';

    $read_all_url = wp_nonce_url(add_query_arg('rknt_action', 'read_all', $endpoint), 'rknt_action');
    $delete_all_url = wp_nonce_url(add_query_arg('rknt_action', 'delete_all', $endpoint), 'rknt_action');
    ?>

    <section class="rknt-card" dir="rtl">
        <div class="rknt-head">
            <div class="rknt-title-block">
                <h3>پیام‌ها و اعلان‌ها</h3>
                <p>پیام‌های مهم حساب، وضعیت سفارش‌ها و کدهای رهگیری اینجا نمایش داده می‌شود.</p>
            </div>
            <span class="rknt-head-badge"><?php echo esc_html(number_format_i18n($unread)); ?> خوانده‌نشده</span>
        </div>

        <?php if ($items): ?>
            <div class="rknt-actions">
                <a href="<?php echo esc_url($read_all_url); ?>">خواندن همه</a>
                <a class="danger" href="<?php echo esc_url($delete_all_url); ?>" onclick="return confirm('همه اعلان‌ها حذف شوند؟')">حذف همه</a>
            </div>
        <?php endif; ?>

        <?php if ($missing): ?>
            <article class="rknt-item rknt-warning is-unread">
                <div class="rknt-item-icon">!</div>
                <div class="rknt-item-body">
                    <div class="rknt-item-row">
                        <strong>پروفایل شما ناقص است</strong>
                        <span>هشدار</span>
                    </div>
                    <p>موارد ناقص: <?php echo esc_html(implode('، ', $missing)); ?></p>
                    <div class="rknt-links">
                        <a href="<?php echo esc_url($edit_account_url); ?>">تکمیل اطلاعات</a>
                    </div>
                </div>
            </article>
        <?php endif; ?>

        <?php if (!$items && !$missing): ?>
            <div class="rknt-empty">
                <div>♧</div>
                <strong>فعلاً اعلانی ندارید</strong>
                <p>هر زمان سفارش ثبت شود، وضعیت سفارش تغییر کند یا کد رهگیری ثبت شود، پیام آن در این بخش می‌آید.</p>
            </div>
        <?php endif; ?>

        <?php if ($items): ?>
            <div class="rknt-list">
                <?php foreach ($items as $item): ?>
                    <?php
                    $id      = (string) ($item['id'] ?? '');
                    $type    = sanitize_key((string) ($item['type'] ?? 'general'));
                    $title   = (string) ($item['title'] ?? 'اعلان');
                    $message = (string) ($item['message'] ?? '');
                    $message = str_replace('وضعیت سفارش شماره', 'سفارش شماره', $message);
                    $url     = (string) ($item['url'] ?? '');
                    $read    = !empty($item['read']);
                    $date    = (string) ($item['created_at'] ?? '');
                    $read_one_url = $id ? wp_nonce_url(add_query_arg(['rknt_action' => 'read_one', 'id' => rawurlencode($id)], $endpoint), 'rknt_action') : '';
                    ?>
                    <article class="rknt-item type-<?php echo esc_attr($type); ?> <?php echo $read ? 'is-read' : 'is-unread'; ?>">
                        <div class="rknt-item-icon"><?php echo esc_html(rknt_icon($type)); ?></div>
                        <div class="rknt-item-body">
                            <div class="rknt-item-row">
                                <strong><?php echo esc_html($title); ?></strong>
                                <?php if (!$read): ?>
                                    <span>جدید</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($message !== ''): ?>
                                <p><?php echo esc_html($message); ?></p>
                            <?php endif; ?>
                            <div class="rknt-meta"><?php echo rknt_format_date($date); ?></div>
                            <div class="rknt-links">
                                <?php if ($url !== ''): ?>
                                    <a href="<?php echo esc_url($url); ?>">مشاهده</a>
                                <?php endif; ?>
                                <?php if (!$read && $read_one_url): ?>
                                    <a href="<?php echo esc_url($read_one_url); ?>">خواندم</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php
}

/* ------------------------------------------------------------
 * Styles + badge
 * ------------------------------------------------------------ */
add_action('wp_head', function () {
    if (!rknt_is_account_page_safe()) {
        return;
    }
    ?>
    <style id="ritahost-notifications-clean-v3-css">
        body.woocommerce-account .woocommerce-MyAccount-content .rknt-card,
        .rknt-card{
            display:block!important;
            visibility:visible!important;
            opacity:1!important;
            width:100%!important;
            max-width:100%!important;
            box-sizing:border-box!important;
            background:#fff!important;
            border:1px solid var(--rk-border,#ece7f3)!important;
            border-radius:var(--rk-radius,18px)!important;
            box-shadow:var(--rk-shadow,0 14px 42px rgba(35,18,52,.055))!important;
            padding:24px!important;
            direction:rtl!important;
            color:var(--rk-text,#1f1728)!important;
        }

        .rknt-head{
            display:flex!important;
            align-items:flex-start!important;
            justify-content:space-between!important;
            gap:16px!important;
            margin:0 0 16px!important;
        }

        .rknt-title-block h3{
            margin:0 0 6px!important;
            color:var(--rk-text,#1f1728)!important;
            font-size:18px!important;
            font-weight:900!important;
            line-height:1.8!important;
        }

        .rknt-title-block p{
            margin:0!important;
            color:var(--rk-muted,#81758b)!important;
            font-size:13px!important;
            line-height:2!important;
        }

        .rknt-head-badge{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            min-height:30px!important;
            padding:5px 13px!important;
            border-radius:999px!important;
            background:var(--rk-soft,#f4ecff)!important;
            color:var(--rk-primary,#6c1faf)!important;
            font-size:12px!important;
            font-weight:900!important;
            white-space:nowrap!important;
        }

        .rknt-actions{
            display:flex!important;
            align-items:center!important;
            gap:10px!important;
            flex-wrap:wrap!important;
            margin:0 0 16px!important;
        }

        .rknt-actions a,
        .rknt-links a{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            min-height:34px!important;
            padding:7px 13px!important;
            border-radius:10px!important;
            background:#fff!important;
            color:var(--rk-primary,#6c1faf)!important;
            border:1px solid rgba(108,31,175,.22)!important;
            text-decoration:none!important;
            font-size:12px!important;
            font-weight:900!important;
            line-height:1.6!important;
        }

        .rknt-actions a.danger{
            color:#e53648!important;
            border-color:rgba(229,54,72,.28)!important;
        }

        .rknt-list{
            display:grid!important;
            gap:12px!important;
            margin-top:12px!important;
        }

        .rknt-item{
            display:grid!important;
            grid-template-columns:42px minmax(0,1fr)!important;
            gap:12px!important;
            padding:16px!important;
            border-radius:16px!important;
            border:1px solid var(--rk-border,#ece7f3)!important;
            background:#fff!important;
            box-sizing:border-box!important;
        }

        .rknt-item.is-unread{
            background:#fbf7ff!important;
            border-color:rgba(108,31,175,.24)!important;
        }

        .rknt-warning{
            margin-bottom:12px!important;
            background:#fffaf0!important;
            border-color:#f4d9a6!important;
        }

        .rknt-item-icon{
            width:42px!important;
            height:42px!important;
            border-radius:15px!important;
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            background:var(--rk-soft,#f4ecff)!important;
            color:var(--rk-primary,#6c1faf)!important;
            font-size:17px!important;
            font-weight:900!important;
            line-height:1!important;
        }

        .rknt-item-body{
            min-width:0!important;
        }

        .rknt-item-row{
            display:flex!important;
            align-items:center!important;
            justify-content:space-between!important;
            gap:12px!important;
            margin:0 0 6px!important;
        }

        .rknt-item-row strong{
            color:var(--rk-text,#1f1728)!important;
            font-size:14px!important;
            font-weight:900!important;
            line-height:1.8!important;
        }

        .rknt-item-row span{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            min-height:23px!important;
            padding:3px 9px!important;
            border-radius:999px!important;
            background:var(--rk-primary,#6c1faf)!important;
            color:#fff!important;
            font-size:11px!important;
            font-weight:900!important;
            white-space:nowrap!important;
        }

        .rknt-item p{
            margin:0 0 8px!important;
            color:var(--rk-muted,#81758b)!important;
            font-size:13px!important;
            line-height:2!important;
        }

        .rknt-meta{
            color:#9a90a7!important;
            font-size:12px!important;
            font-weight:700!important;
            margin:0 0 8px!important;
            direction:ltr!important;
            text-align:right!important;
        }

        .rknt-links{
            display:flex!important;
            align-items:center!important;
            gap:8px!important;
            flex-wrap:wrap!important;
        }

        .rknt-empty{
            text-align:center!important;
            padding:34px 16px!important;
            background:#fbfaff!important;
            border:1px solid var(--rk-border,#ece7f3)!important;
            border-radius:16px!important;
        }

        .rknt-empty div{
            width:70px!important;
            height:70px!important;
            margin:0 auto 12px!important;
            border-radius:24px!important;
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            background:var(--rk-soft,#f4ecff)!important;
            color:var(--rk-primary,#6c1faf)!important;
            font-size:28px!important;
            font-weight:900!important;
        }

        .rknt-empty strong{
            display:block!important;
            margin:0 0 8px!important;
            color:var(--rk-text,#1f1728)!important;
            font-size:15px!important;
            font-weight:900!important;
        }

        .rknt-empty p{
            margin:0!important;
            color:var(--rk-muted,#81758b)!important;
            font-size:13px!important;
            line-height:2!important;
        }

        body.woocommerce-account .woocommerce-MyAccount-navigation-link--notifications a{
            position:relative!important;
            justify-content:flex-start!important;
            gap:8px!important;
        }

        body.woocommerce-account .woocommerce-MyAccount-navigation-link--notifications a:after{
            margin-inline-start:auto!important;
        }

        .rknt-sidebar-count{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            min-width:22px!important;
            height:22px!important;
            padding:0 7px!important;
            border-radius:999px!important;
            background:#67009e!important;
            color:#fff!important;
            font-size:11px!important;
            font-weight:900!important;
            line-height:1!important;
            box-shadow:0 6px 14px rgba(103,0,158,.22)!important;
            order:2!important;
        }

        @media(max-width:768px){
            .rknt-card{
                padding:16px!important;
            }
            .rknt-head{
                flex-direction:column!important;
                gap:10px!important;
            }
            .rknt-item{
                grid-template-columns:36px minmax(0,1fr)!important;
                padding:14px!important;
            }
            .rknt-item-icon{
                width:36px!important;
                height:36px!important;
                border-radius:13px!important;
                font-size:15px!important;
            }
            .rknt-item-row{
                align-items:flex-start!important;
                flex-direction:column!important;
                gap:5px!important;
            }
        }
    </style>
    <?php
}, 9999);

add_action('wp_footer', function () {
    if (!is_user_logged_in() || !rknt_is_account_page_safe()) {
        return;
    }

    $count = rknt_unread_count(get_current_user_id());
    $label = $count > 99 ? '99+' : number_format_i18n($count);
    ?>
    <script id="ritahost-notifications-clean-v3-badge-js">
        document.addEventListener('DOMContentLoaded', function(){
            var count = <?php echo (int) $count; ?>;
            var label = <?php echo wp_json_encode($label); ?>;
            var links = document.querySelectorAll('body.woocommerce-account .woocommerce-MyAccount-navigation-link--notifications a');

            links.forEach(function(link){
                link.querySelectorAll('.rknc-sidebar-count,.rknt-sidebar-count').forEach(function(oldBadge){
                    oldBadge.remove();
                });

                if(count < 1){
                    return;
                }

                var badge = document.createElement('span');
                badge.className = 'rknt-sidebar-count';
                badge.textContent = label;
                link.appendChild(badge);
            });
        });
    </script>
    <?php
}, 9999);

