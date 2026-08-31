<?php
/**
 * Plugin Name: RitaHost Smart Checkout
 * Description: Adds reusable WooCommerce checkout templates, weekly delivery schedules, fulfillment controls, and additional order fields.
 * Version: 12.1.1
 * Author: RitaHost
 * Text Domain: ritahost
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * این ثابت باعث می‌شود نسخه‌های قبلی rubikala-checkout.php که در mu-plugins
 * باقی مانده‌اند، پس از لود این فایل اجرا نشوند.
 */
if (!defined('RK_CHECKOUT_MU_LOADED')) {
    define('RK_CHECKOUT_MU_LOADED', '12.1.1-smart-checkout');
}

if (defined('RKZN_CHECKOUT_LOADED')) {
    return;
}
define('RKZN_CHECKOUT_LOADED', '12.1.1');
define('RKZN_CHECKOUT_DIR', __DIR__ . '/ritahost-smart-checkout/');
define('RKZN_CHECKOUT_OPTION', 'rtsc_checkout_settings');

function rkzn_is_checkout_page() {
    return function_exists('is_checkout')
        && is_checkout()
        && !is_wc_endpoint_url('order-received')
        && !is_wc_endpoint_url('order-pay');
}

/* دکمه نهایی داخل خلاصه سفارش قرار می‌گیرد. */
add_filter('woocommerce_order_button_text', function ($text) {
    return rkzn_is_checkout_page() ? 'پرداخت و ثبت سفارش' : $text;
}, 999);

function rkzn_weekday_labels() {
    return [
        6 => 'شنبه',
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنجشنبه',
        5 => 'جمعه',
    ];
}

function rkzn_default_slots() {
    return [
        ['from' => '09:00', 'to' => '12:00'],
        ['from' => '12:00', 'to' => '15:00'],
        ['from' => '15:00', 'to' => '18:00'],
    ];
}

function rkzn_default_week_schedule() {
    $schedule = [];
    foreach (array_keys(rkzn_weekday_labels()) as $weekday) {
        $schedule[(string) $weekday] = [
            'enabled' => $weekday === 5 ? 'no' : 'yes',
            'slots'   => $weekday === 5 ? [] : rkzn_default_slots(),
        ];
    }
    return $schedule;
}

function rkzn_defaults() {
    return [
        'delivery_enabled'      => 'yes',
        'delivery_days'         => 7,
        // Empty rules mean: delivery time selection is enabled for every shipping method.
        // A method is stored here only after the admin explicitly changes its toggle.
        'delivery_method_rules' => [],
        'week_schedule'         => rkzn_default_week_schedule(),
        'invoice'          => 'yes',
        'confidential'     => 'yes',
        'hide_price'       => 'yes',
        'gift'             => 'yes',
        'call_before'      => 'yes',
        'colors' => [
            'primary' => '#96349a',
            'primary_dark' => '#74227b',
            'soft' => '#ead2eb',
            'border' => '#d8d8d8',
            'bg' => '#f4f4f4',
            'text' => '#242424',
            'muted' => '#777777',
            'green' => '#66b83f',
            'card' => '#ffffff',
        ],
    ];
}

function rkzn_legacy_slots_to_rows($raw) {
    $rows = preg_split('/\r\n|\r|\n/', trim((string) $raw));
    $slots = [];
    foreach ($rows as $row) {
        $parts = array_map('trim', explode('|', $row, 2));
        if (empty($parts[0])) {
            continue;
        }
        if (preg_match('/^(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', $parts[0], $m)) {
            $slots[] = ['from' => $m[1], 'to' => $m[2]];
        }
    }
    return $slots ?: rkzn_default_slots();
}

function rkzn_migrate_legacy_settings($legacy) {
    $defaults = rkzn_defaults();
    $legacy = is_array($legacy) ? $legacy : [];
    $schedule = rkzn_default_week_schedule();
    $closed = array_values(array_filter(array_map('intval', explode(',', (string) ($legacy['closed_weekdays'] ?? '5'))), static function ($day) { return $day >= 0 && $day <= 6; }));
    $slots = rkzn_legacy_slots_to_rows($legacy['delivery_slots'] ?? '');

    foreach (array_keys(rkzn_weekday_labels()) as $weekday) {
        $schedule[(string) $weekday] = [
            'enabled' => in_array($weekday, $closed, true) ? 'no' : 'yes',
            'slots'   => in_array($weekday, $closed, true) ? [] : $slots,
        ];
    }

    foreach (['delivery_enabled', 'invoice', 'confidential', 'hide_price', 'gift', 'call_before'] as $key) {
        if (isset($legacy[$key])) {
            $defaults[$key] = $legacy[$key] === 'yes' ? 'yes' : 'no';
        }
    }
    $defaults['delivery_days'] = max(1, min(14, absint($legacy['delivery_days'] ?? 7)));
    $defaults['week_schedule'] = $schedule;
    return $defaults;
}

function rkzn_settings() {
    $saved = get_option(RKZN_CHECKOUT_OPTION, null);
    if ($saved === null) {
        $legacy = get_option('rkzn_checkout_settings', null);
        if (is_array($legacy)) {
            $saved = rkzn_migrate_legacy_settings($legacy);
        }
    }
    return wp_parse_args(is_array($saved) ? $saved : [], rkzn_defaults());
}

/* ---------------------------------------------------------
 * WooCommerce template overrides
 * --------------------------------------------------------- */
add_filter('woocommerce_locate_template', function ($template, $template_name) {
    $allowed = [
        'checkout/form-checkout.php',
        'checkout/form-billing.php',
        'checkout/review-order.php',
    ];

    if (in_array($template_name, $allowed, true)) {
        $custom = RKZN_CHECKOUT_DIR . 'templates/' . $template_name;
        if (is_readable($custom)) {
            return $custom;
        }
    }

    return $template;
}, 9999, 2);

/* ---------------------------------------------------------
 * Checkout fields: precise two-column order
 * --------------------------------------------------------- */
add_filter('woocommerce_checkout_fields', function ($fields) {
    $priority = [
        'billing_first_name' => 10,
        'billing_last_name'  => 20,
        'billing_phone'      => 30,
        'billing_email'      => 40,
        'billing_country'    => 50,
        'billing_state'      => 60,
        'billing_city'       => 70,
        'billing_postcode'   => 80,
        'billing_address_1'  => 90,
        'billing_address_2'  => 100,
    ];

    if (!empty($fields['billing']) && is_array($fields['billing'])) {
        foreach ($fields['billing'] as $key => &$field) {
            $field['class'] = array_values(array_unique(array_merge((array) ($field['class'] ?? []), ['rkzn-field'])));
            $field['input_class'] = array_values(array_unique(array_merge((array) ($field['input_class'] ?? []), ['rkzn-input'])));

            if (isset($priority[$key])) {
                $field['priority'] = $priority[$key];
            }

            if ($key === 'billing_address_1') {
                $field['type'] = 'textarea';
                $field['label'] = !empty($field['label']) ? $field['label'] : 'آدرس';
                $field['placeholder'] = !empty($field['placeholder']) ? $field['placeholder'] : 'نشانی کامل پستی، پلاک و واحد';
                $field['custom_attributes']['rows'] = '3';
            }

            if ($key === 'billing_address_2' && empty($field['label'])) {
                $field['label'] = 'توضیحات آدرس';
                $field['placeholder'] = 'طبقه، واحد، نام ساختمان یا توضیح تکمیلی';
            }
        }
        unset($field);
    }

    if (!empty($fields['order']) && is_array($fields['order'])) {
        foreach ($fields['order'] as $key => &$field) {
            $field['class'] = array_values(array_unique(array_merge((array) ($field['class'] ?? []), ['rkzn-field'])));
            $field['input_class'] = array_values(array_unique(array_merge((array) ($field['input_class'] ?? []), ['rkzn-input'])));
            if ($key === 'order_comments') {
                $field['label'] = 'توضیحات سفارش';
                $field['placeholder'] = 'یادداشت یا نکته‌ای درباره سفارش و تحویل...';
                $field['priority'] = 110;
            }
        }
        unset($field);
    }

    return $fields;
}, 30);


/* ---------------------------------------------------------
 * Birth-date fields: day / month / year dropdowns
 * --------------------------------------------------------- */
function rkzn_is_birthdate_field($key, $field) {
    $type = strtolower((string) ($field['type'] ?? ''));
    $label = wp_strip_all_tags((string) ($field['label'] ?? ''));
    $haystack = strtolower((string) $key . ' ' . $label);

    if ($type === 'date') {
        return true;
    }

    return (bool) preg_match('/(?:birth|birthday|date[\s_-]*of[\s_-]*birth|dob|تولد)/iu', $haystack);
}

function rkzn_to_latin_digits($value) {
    return strtr((string) $value, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

function rkzn_birthdate_parts($value) {
    $value = rkzn_to_latin_digits($value);
    $parts = array_values(array_filter(preg_split('/[^0-9]+/', $value), 'strlen'));
    $year = $month = $day = '';

    if (count($parts) >= 3) {
        if (strlen($parts[0]) === 4) {
            [$year, $month, $day] = array_slice($parts, 0, 3);
        } elseif (strlen($parts[2]) === 4) {
            $year = $parts[2];
            if ((int) $parts[0] > 12) {
                $day = $parts[0];
                $month = $parts[1];
            } else {
                $month = $parts[0];
                $day = $parts[1];
            }
        }
    }

    return [
        'year'  => $year !== '' ? (int) $year : '',
        'month' => $month !== '' ? (int) $month : '',
        'day'   => $day !== '' ? (int) $day : '',
    ];
}

function rkzn_current_jalali_year() {
    return max(1400, ((int) wp_date('Y')) - 621);
}

function rkzn_render_birthdate_field($key, $field, $value = '') {
    $parts = rkzn_birthdate_parts($value);
    $required = !empty($field['required']);
    $label = !empty($field['label']) ? $field['label'] : 'تاریخ تولد';
    $classes = array_values(array_unique(array_merge(
        ['form-row', 'form-row-wide', 'rkzn-field', 'rkzn-birthdate-field'],
        (array) ($field['class'] ?? [])
    )));
    $months = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];
    $max_year = rkzn_current_jalali_year();
    $min_year = 1300;
    if ($parts['year'] && ($parts['year'] < $min_year || $parts['year'] > $max_year)) {
        $max_year = max($max_year, (int) $parts['year']);
        $min_year = min($min_year, (int) $parts['year']);
    }
    ?>
    <p class="<?php echo esc_attr(implode(' ', $classes)); ?>" id="<?php echo esc_attr($key); ?>_field" data-priority="<?php echo esc_attr($field['priority'] ?? 999); ?>">
        <label class="rkzn-birthdate-label" for="<?php echo esc_attr($key); ?>_day">
            <?php echo esc_html($label); ?>
            <?php if ($required) : ?><abbr class="required" title="ضروری">*</abbr><?php endif; ?>
        </label>
        <span class="rkzn-birthdate-selects" data-rkzn-birthdate>
            <select id="<?php echo esc_attr($key); ?>_day" name="<?php echo esc_attr($key); ?>_day" class="rkzn-birthdate-select rkzn-birthdate-day" aria-label="روز تولد">
                <option value="">روز</option>
                <?php for ($day = 1; $day <= 31; $day++) : ?>
                    <option value="<?php echo esc_attr($day); ?>" <?php selected($parts['day'], $day); ?>><?php echo esc_html($day); ?></option>
                <?php endfor; ?>
            </select>
            <select id="<?php echo esc_attr($key); ?>_month" name="<?php echo esc_attr($key); ?>_month" class="rkzn-birthdate-select rkzn-birthdate-month" aria-label="ماه تولد">
                <option value="">ماه</option>
                <?php foreach ($months as $month_number => $month_name) : ?>
                    <option value="<?php echo esc_attr($month_number); ?>" <?php selected($parts['month'], $month_number); ?>><?php echo esc_html($month_name); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="<?php echo esc_attr($key); ?>_year" name="<?php echo esc_attr($key); ?>_year" class="rkzn-birthdate-select rkzn-birthdate-year" aria-label="سال تولد">
                <option value="">سال</option>
                <?php for ($year = $max_year; $year >= $min_year; $year--) : ?>
                    <option value="<?php echo esc_attr($year); ?>" <?php selected($parts['year'], $year); ?>><?php echo esc_html($year); ?></option>
                <?php endfor; ?>
            </select>
            <input type="hidden" class="rkzn-birthdate-value" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" <?php echo $required ? 'aria-required="true"' : ''; ?>>
        </span>
    </p>
    <?php
}

add_filter('woocommerce_checkout_posted_data', function ($data) {
    if (!function_exists('WC') || !WC()->checkout()) {
        return $data;
    }

    $groups = ['billing', 'order'];
    foreach ($groups as $group) {
        $fields = WC()->checkout()->get_checkout_fields($group);
        foreach ((array) $fields as $key => $field) {
            if (!rkzn_is_birthdate_field($key, $field)) {
                continue;
            }

            $day = isset($_POST[$key . '_day']) ? absint(wp_unslash($_POST[$key . '_day'])) : 0;
            $month = isset($_POST[$key . '_month']) ? absint(wp_unslash($_POST[$key . '_month'])) : 0;
            $year = isset($_POST[$key . '_year']) ? absint(wp_unslash($_POST[$key . '_year'])) : 0;
            $value = '';

            if ($day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && $year >= 1200 && $year <= 2200) {
                $value = sprintf('%04d/%02d/%02d', $year, $month, $day);
            }

            $data[$key] = $value;
            $_POST[$key] = $value;
        }
    }

    return $data;
}, 5);

/* ---------------------------------------------------------
 * Admin settings – reusable and operator-friendly
 * --------------------------------------------------------- */
add_action('admin_menu', function () {
    if (!class_exists('WooCommerce')) {
        return;
    }

    $parent = function_exists('ritahost_register_admin_tool') ? 'ritahost-panel' : 'woocommerce';
    $page_title = function_exists('ritahost_admin_text') ? ritahost_admin_text('تنظیمات چک‌اوت هوشمند', 'Smart Checkout Settings') : (is_rtl() ? 'تنظیمات چک‌اوت هوشمند' : 'Smart Checkout Settings');
    $menu_title = function_exists('ritahost_admin_text') ? ritahost_admin_text('چک‌اوت هوشمند', 'Smart Checkout') : (is_rtl() ? 'چک‌اوت هوشمند' : 'Smart Checkout');

    add_submenu_page(
        $parent,
        $page_title,
        $menu_title,
        'manage_woocommerce',
        'ritahost-smart-checkout',
        'rkzn_settings_page'
    );
});

if (function_exists('ritahost_register_admin_tool')) {
    ritahost_register_admin_tool('ritahost-smart-checkout', 'چک‌اوت هوشمند', 'Smart Checkout', 'تنظیم برنامه ارسال، روش‌های تحویل و اطلاعات تکمیلی تسویه‌حساب.', 'Configure delivery schedules, fulfillment methods, and additional checkout fields.', 'manage_woocommerce');
}

add_action('admin_init', function () {
    register_setting('rkzn_checkout_group', RKZN_CHECKOUT_OPTION, [
        'type'              => 'array',
        'sanitize_callback' => 'rkzn_sanitize_settings',
        'default'           => rkzn_defaults(),
    ]);
});

function rkzn_sanitize_time($value) {
    $value = trim((string) $value);
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
        return '';
    }
    return $value;
}

/**
 * Return configured shipping method instances from all WooCommerce shipping zones.
 * Rate IDs (for example flat_rate:3) are used so two instances of the same method
 * can have independent delivery-time behavior.
 */
function rkzn_configured_shipping_methods() {
    if (!class_exists('WC_Shipping_Zones')) {
        return [];
    }

    $items = [];
    $zones = WC_Shipping_Zones::get_zones();

    // Zone 0 is WooCommerce's "Locations not covered by your other zones" zone.
    $zone_zero = WC_Shipping_Zones::get_zone(0);
    if ($zone_zero) {
        $zones[0] = [
            'zone_id'          => 0,
            'zone_name'        => 'سایر مناطق',
            'shipping_methods' => $zone_zero->get_shipping_methods(true),
        ];
    }

    foreach ($zones as $zone) {
        $zone_name = isset($zone['zone_name']) ? (string) $zone['zone_name'] : 'بدون نام';
        $methods = isset($zone['shipping_methods']) && is_array($zone['shipping_methods']) ? $zone['shipping_methods'] : [];

        foreach ($methods as $method) {
            if (!is_object($method) || empty($method->id)) {
                continue;
            }

            if (method_exists($method, 'get_rate_id')) {
                $rate_id = (string) $method->get_rate_id();
            } else {
                $instance_id = isset($method->instance_id) ? absint($method->instance_id) : 0;
                $rate_id = (string) $method->id . ($instance_id ? ':' . $instance_id : '');
            }

            if ($rate_id === '') {
                continue;
            }

            $title = '';
            if (method_exists($method, 'get_title')) {
                $title = wp_strip_all_tags((string) $method->get_title());
            }
            if ($title === '' && isset($method->title)) {
                $title = wp_strip_all_tags((string) $method->title);
            }
            if ($title === '' && method_exists($method, 'get_method_title')) {
                $title = wp_strip_all_tags((string) $method->get_method_title());
            }
            if ($title === '') {
                $title = $rate_id;
            }

            $items[$rate_id] = [
                'rate_id'   => $rate_id,
                'method_id' => (string) $method->id,
                'title'     => $title,
                'zone'      => $zone_name,
            ];
        }
    }

    uasort($items, static function ($a, $b) {
        $zone_compare = strnatcasecmp((string) ($a['zone'] ?? ''), (string) ($b['zone'] ?? ''));
        return $zone_compare !== 0 ? $zone_compare : strnatcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
    });

    return $items;
}

function rkzn_sanitize_shipping_rate_id($value) {
    $value = trim((string) $value);
    if ($value === '' || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
        return '';
    }
    return $value;
}

function rkzn_sanitize_settings($input) {
    $defaults = rkzn_defaults();
    $input = is_array($input) ? $input : [];
    $out = [];

    foreach (['delivery_enabled', 'invoice', 'confidential', 'hide_price', 'gift', 'call_before'] as $key) {
        $out[$key] = !empty($input[$key]) ? 'yes' : 'no';
    }

    $out['delivery_days'] = max(1, min(14, absint($input['delivery_days'] ?? $defaults['delivery_days'])));

    $out['delivery_method_rules'] = [];
    $raw_method_rules = isset($input['delivery_method_rules']) && is_array($input['delivery_method_rules']) ? $input['delivery_method_rules'] : [];
    foreach ($raw_method_rules as $rate_id => $enabled) {
        $rate_id = rkzn_sanitize_shipping_rate_id($rate_id);
        if ($rate_id === '') {
            continue;
        }
        $out['delivery_method_rules'][$rate_id] = ((string) $enabled === 'yes') ? 'yes' : 'no';
    }

    $out['week_schedule'] = [];
    $raw_schedule = isset($input['week_schedule']) && is_array($input['week_schedule']) ? $input['week_schedule'] : [];

    foreach (array_keys(rkzn_weekday_labels()) as $weekday) {
        $key = (string) $weekday;
        $day = isset($raw_schedule[$key]) && is_array($raw_schedule[$key]) ? $raw_schedule[$key] : [];
        $slots = [];
        $seen = [];

        foreach ((array) ($day['slots'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $from = rkzn_sanitize_time($row['from'] ?? '');
            $to   = rkzn_sanitize_time($row['to'] ?? '');
            if ($from === '' || $to === '' || $from >= $to) {
                continue;
            }
            $slot_key = $from . '-' . $to;
            if (isset($seen[$slot_key])) {
                continue;
            }
            $seen[$slot_key] = true;
            $slots[] = ['from' => $from, 'to' => $to];
        }

        $out['week_schedule'][$key] = [
            'enabled' => !empty($day['enabled']) ? 'yes' : 'no',
            'slots'   => $slots,
        ];
    }

    $default_colors = rkzn_defaults()['colors'];
    $out['colors'] = [];
    foreach ($default_colors as $color_key => $default_color) {
        $value = isset($input['colors'][$color_key]) ? sanitize_hex_color($input['colors'][$color_key]) : '';
        $out['colors'][$color_key] = $value ?: $default_color;
    }

    return $out;
}

function rkzn_admin_slot_row($option_name, $weekday, $index, $slot = []) {
    $from = isset($slot['from']) ? $slot['from'] : '09:00';
    $to   = isset($slot['to']) ? $slot['to'] : '12:00';
    ?>
    <div class="rtsc-slot-row" data-rtsc-slot-row>
        <label>
            <span>از</span>
            <input type="time" name="<?php echo esc_attr($option_name); ?>[week_schedule][<?php echo esc_attr($weekday); ?>][slots][<?php echo esc_attr($index); ?>][from]" value="<?php echo esc_attr($from); ?>" step="300">
        </label>
        <label>
            <span>تا</span>
            <input type="time" name="<?php echo esc_attr($option_name); ?>[week_schedule][<?php echo esc_attr($weekday); ?>][slots][<?php echo esc_attr($index); ?>][to]" value="<?php echo esc_attr($to); ?>" step="300">
        </label>
        <button type="button" class="button-link-delete rtsc-remove-slot" aria-label="حذف بازه">حذف</button>
    </div>
    <?php
}

function rkzn_settings_page() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    $s = rkzn_settings();
    $labels = rkzn_weekday_labels();
    $option = RKZN_CHECKOUT_OPTION;
    $shipping_methods = rkzn_configured_shipping_methods();
    $method_rules = isset($s['delivery_method_rules']) && is_array($s['delivery_method_rules']) ? $s['delivery_method_rules'] : [];
    ?>
    <div class="wrap rtsc-admin" dir="rtl">
        <h1>تنظیمات چک‌اوت هوشمند</h1>
        <p class="rtsc-lead">زمان ارسال را برای هر روز هفته جداگانه تعریف کنید. روز خاموش در چک‌اوت نمایش داده نمی‌شود.</p>

        <form action="options.php" method="post">
            <?php settings_fields('rkzn_checkout_group'); ?>

            <section class="rtsc-panel">
                <div class="rtsc-panel-head">
                    <div>
                        <h2>زمان‌بندی ارسال</h2>
                        <p>برنامه هفتگی تکرارشونده برای انتخاب روز و ساعت تحویل</p>
                    </div>
                    <label class="rtsc-switch-label">
                        <input type="checkbox" name="<?php echo esc_attr($option); ?>[delivery_enabled]" value="1" <?php checked($s['delivery_enabled'], 'yes'); ?>>
                        <span>فعال باشد</span>
                    </label>
                </div>

                <div class="rtsc-inline-setting">
                    <label for="rtsc-delivery-days"><strong>تعداد تاریخ‌های آینده قابل نمایش</strong></label>
                    <input id="rtsc-delivery-days" type="number" min="1" max="14" name="<?php echo esc_attr($option); ?>[delivery_days]" value="<?php echo esc_attr($s['delivery_days']); ?>">
                    <span>مثلاً عدد ۷ یعنی هفت تاریخ فعال بعدی نمایش داده شود.</span>
                </div>

                <div class="rtsc-method-setting">
                    <div class="rtsc-method-setting-head">
                        <div>
                            <h3>اعمال انتخاب زمان بر اساس روش ارسال</h3>
                            <p>به‌صورت پیش‌فرض برای همه روش‌های ارسال فعال است. هر روشی را خاموش کنید، مشتری با انتخاب همان روش دیگر روز و بازه ارسال را نمی‌بیند و انتخاب آن هم اجباری نخواهد بود.</p>
                        </div>
                    </div>
                    <?php if ($shipping_methods) : ?>
                        <div class="rtsc-method-grid">
                            <?php foreach ($shipping_methods as $rate_id => $shipping_method) :
                                $method_enabled = !isset($method_rules[$rate_id]) || $method_rules[$rate_id] !== 'no';
                                ?>
                                <label class="rtsc-method-item <?php echo $method_enabled ? 'is-enabled' : ''; ?>" data-rtsc-method>
                                    <input type="hidden" name="<?php echo esc_attr($option); ?>[delivery_method_rules][<?php echo esc_attr($rate_id); ?>]" value="no">
                                    <input type="checkbox" class="rtsc-method-enabled" name="<?php echo esc_attr($option); ?>[delivery_method_rules][<?php echo esc_attr($rate_id); ?>]" value="yes" <?php checked($method_enabled); ?>>
                                    <span class="rtsc-method-copy">
                                        <strong><?php echo esc_html($shipping_method['title']); ?></strong>
                                        <small><?php echo esc_html($shipping_method['zone']); ?> · <code><?php echo esc_html($rate_id); ?></code></small>
                                    </span>
                                    <em><?php echo $method_enabled ? 'فعال' : 'غیرفعال'; ?></em>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="rtsc-method-empty">هنوز روش ارسال فعالی در ناحیه‌های حمل‌ونقل ووکامرس پیدا نشد. روش‌های جدید بعد از ایجاد، به‌صورت خودکار برای زمان‌بندی فعال خواهند بود.</div>
                    <?php endif; ?>
                </div>

                <div class="rtsc-week-list">
                    <?php foreach ($labels as $weekday => $label) :
                        $day = $s['week_schedule'][(string) $weekday] ?? ['enabled' => 'no', 'slots' => []];
                        $slots = !empty($day['slots']) && is_array($day['slots']) ? $day['slots'] : [];
                        ?>
                        <article class="rtsc-day-card <?php echo $day['enabled'] === 'yes' ? 'is-enabled' : ''; ?>" data-rtsc-day>
                            <div class="rtsc-day-toggle">
                                <label>
                                    <input type="checkbox" class="rtsc-day-enabled" name="<?php echo esc_attr($option); ?>[week_schedule][<?php echo esc_attr($weekday); ?>][enabled]" value="1" <?php checked($day['enabled'], 'yes'); ?>>
                                    <strong><?php echo esc_html($label); ?></strong>
                                </label>
                                <small><?php echo $day['enabled'] === 'yes' ? 'فعال' : 'تعطیل'; ?></small>
                            </div>

                            <div class="rtsc-slots" data-rtsc-slots data-weekday="<?php echo esc_attr($weekday); ?>">
                                <?php
                                foreach ($slots as $index => $slot) {
                                    rkzn_admin_slot_row($option, $weekday, $index, $slot);
                                }
                                ?>
                            </div>

                            <button type="button" class="button rtsc-add-slot" data-weekday="<?php echo esc_attr($weekday); ?>">+ افزودن بازه زمانی</button>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="rtsc-panel">
                <div class="rtsc-panel-head"><div><h2>اطلاعات تکمیلی سفارش</h2><p>مواردی که کاربر در چک‌اوت می‌تواند انتخاب کند</p></div></div>
                <div class="rtsc-option-grid">
                    <?php
                    $checks = [
                        'invoice'      => 'درخواست فاکتور',
                        'confidential' => 'ارسال محرمانه',
                        'hide_price'   => 'عدم درج قیمت',
                        'gift'         => 'سفارش هدیه',
                        'call_before'  => 'تماس قبل از ارسال',
                    ];
                    foreach ($checks as $key => $label) : ?>
                        <label class="rtsc-option-item">
                            <input type="checkbox" name="<?php echo esc_attr($option); ?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked($s[$key], 'yes'); ?>>
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="rtsc-panel">
                <div class="rtsc-panel-head"><div><h2>تنظیمات رنگ و ظاهر</h2><p>رنگ‌های چک‌اوت برای استفاده در فروشگاه‌های مختلف</p></div></div>
                <div class="rtsc-inline-setting">
                    <label>پیش‌تنظیم ظاهر</label>
                    <select name="' . $option . '[appearance_preset]">
                        <option value="custom">سفارشی</option>
                        <option value="rubikala">روبیکالا</option>
                        <option value="zanoone">زنونه</option>
                        <option value="medical">فروشگاه پزشکی</option>
                        <option value="minimal">مینیمال</option>
                    </select>
                </div>
                <div class="rtsc-color-grid">
                <?php $color_labels = ['primary'=>'رنگ اصلی','primary_dark'=>'رنگ تیره دکمه','soft'=>'پس‌زمینه ملایم','border'=>'رنگ حاشیه','bg'=>'پس‌زمینه کلی','text'=>'رنگ متن','muted'=>'رنگ متن کم‌رنگ','green'=>'رنگ موفقیت','card'=>'رنگ کارت']; foreach($color_labels as $key=>$label): $val=$s['colors'][$key] ?? ''; ?>
                    <label class="rtsc-color-item"><span><?php echo esc_html($label); ?></span><div class="rtsc-color-controls"><input type="color" value="<?php echo esc_attr($val); ?>" data-color-target="<?php echo esc_attr($key); ?>"><input class="rtsc-hex-input" type="text" maxlength="7" name="<?php echo esc_attr($option); ?>[colors][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($val); ?>"></div></label>
                <?php endforeach; ?>
                </div>
                <script>document.querySelectorAll('.rtsc-color-item').forEach(function(w){var c=w.querySelector('input[type=color]'),h=w.querySelector('.rtsc-hex-input');c.addEventListener('input',function(){h.value=c.value});h.addEventListener('input',function(){if(/^#[0-9a-f]{6}$/i.test(h.value)){c.value=h.value}})});</script>
            </section>

            <?php submit_button('ذخیره تنظیمات'); ?>
        </form>
    </div>

    <style>
        .rtsc-admin{max-width:1180px}.rtsc-admin h1{font-weight:900}.rtsc-lead{font-size:14px;color:#646970;margin-bottom:22px}
        .rtsc-panel{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:22px;margin:0 0 20px;box-shadow:0 3px 14px rgba(0,0,0,.035)}
        .rtsc-color-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.rtsc-color-item{display:flex;align-items:center;justify-content:space-between;background:#fafafa;border:1px solid #eee;border-radius:10px;padding:12px;font-weight:700}.rtsc-color-item input[type=color]{width:45px;height:35px;border:0;background:none;padding:0}.rtsc-color-controls{display:flex;align-items:center;gap:8px}.rtsc-hex-input{width:90px!important;height:34px!important;border:1px solid #ddd!important;background:#fff!important;border-radius:6px!important;padding:5px 8px!important;direction:ltr}
        .rtsc-panel-head{display:flex;align-items:center;justify-content:space-between;gap:20px;padding-bottom:18px;margin-bottom:18px;border-bottom:1px solid #eee}.rtsc-panel-head h2{margin:0 0 5px;font-size:18px}.rtsc-panel-head p{margin:0;color:#646970}
        .rtsc-switch-label{display:flex;align-items:center;gap:8px;font-weight:800}.rtsc-switch-label input{width:18px;height:18px}
        .rtsc-inline-setting{display:grid;grid-template-columns:minmax(220px,1fr) 90px minmax(260px,2fr);align-items:center;gap:14px;background:#f8f8f8;border-radius:9px;padding:14px;margin-bottom:18px}.rtsc-inline-setting input{width:90px}.rtsc-inline-setting span{color:#646970}
        .rtsc-method-setting{margin:0 0 18px;padding:15px;border:1px solid #e1e1e1;border-radius:10px;background:#fbfbfc}.rtsc-method-setting-head h3{margin:0 0 5px;font-size:14px}.rtsc-method-setting-head p{margin:0 0 12px;color:#646970;line-height:1.9}.rtsc-method-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.rtsc-method-item{display:grid;grid-template-columns:20px minmax(0,1fr) auto;align-items:center;gap:10px;min-height:58px;padding:10px 12px;border:1px solid #ddd;border-radius:9px;background:#fff;cursor:pointer}.rtsc-method-item.is-enabled{border-color:#cbb3dd;background:#fdfaff}.rtsc-method-item input[type=checkbox]{width:18px;height:18px;margin:0}.rtsc-method-copy{display:flex;flex-direction:column;gap:3px;min-width:0}.rtsc-method-copy strong{font-size:13px}.rtsc-method-copy small{color:#72777c;white-space:normal}.rtsc-method-copy code{direction:ltr;display:inline-block;font-size:10px}.rtsc-method-item em{font-style:normal;font-size:11px;font-weight:800;color:#8a8f94}.rtsc-method-item.is-enabled em{color:#5a237b}.rtsc-method-empty{padding:12px;border:1px dashed #c7c7c7;border-radius:8px;background:#fff;color:#646970}
        .rtsc-week-list{display:grid;gap:10px}.rtsc-day-card{display:grid;grid-template-columns:135px minmax(0,1fr) 150px;gap:14px;align-items:start;border:1px solid #e1e1e1;border-radius:10px;padding:14px;background:#fafafa;opacity:.68}.rtsc-day-card.is-enabled{background:#fff;opacity:1;border-color:#cbb3dd}
        .rtsc-day-toggle label{display:flex;align-items:center;gap:9px;font-size:15px}.rtsc-day-toggle input{width:18px;height:18px}.rtsc-day-toggle small{display:block;margin:7px 27px 0;color:#777}.rtsc-day-card.is-enabled .rtsc-day-toggle small{color:#5a237b;font-weight:800}
        .rtsc-slots{display:grid;gap:8px}.rtsc-slot-row{display:flex;align-items:end;gap:8px}.rtsc-slot-row label{display:flex;align-items:center;gap:6px}.rtsc-slot-row input[type=time]{width:118px;direction:ltr}.rtsc-remove-slot{margin:0 4px 6px 0!important}.rtsc-add-slot{width:100%;min-height:34px}
        .rtsc-option-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.rtsc-option-item{display:flex;align-items:center;gap:9px;border:1px solid #e1e1e1;border-radius:9px;padding:13px;background:#fafafa;font-weight:700}.rtsc-option-item input{width:17px;height:17px}
        @media(max-width:900px){.rtsc-method-grid{grid-template-columns:1fr}.rtsc-day-card{grid-template-columns:1fr}.rtsc-inline-setting{grid-template-columns:1fr 90px}.rtsc-inline-setting span{grid-column:1/-1}.rtsc-option-grid{grid-template-columns:1fr 1fr}}
        @media(max-width:600px){.rtsc-option-grid{grid-template-columns:1fr}.rtsc-slot-row{flex-wrap:wrap}.rtsc-slot-row label{flex:1}.rtsc-slot-row input[type=time]{width:100%}}
    </style>

    <script>
    (function(){
        'use strict';
        var optionName = <?php echo wp_json_encode($option); ?>;
        function updateDay(card){
            var checked = card.querySelector('.rtsc-day-enabled').checked;
            card.classList.toggle('is-enabled', checked);
            var status = card.querySelector('.rtsc-day-toggle small');
            if(status){ status.textContent = checked ? 'فعال' : 'تعطیل'; }
        }
        function updateMethod(item){
            if(!item){ return; }
            var checkbox = item.querySelector('.rtsc-method-enabled');
            var checked = checkbox && checkbox.checked;
            item.classList.toggle('is-enabled', !!checked);
            var status = item.querySelector('em');
            if(status){ status.textContent = checked ? 'فعال' : 'غیرفعال'; }
        }
        document.querySelectorAll('[data-rtsc-day]').forEach(updateDay);
        document.querySelectorAll('[data-rtsc-method]').forEach(updateMethod);
        document.addEventListener('change', function(e){
            if(e.target.classList.contains('rtsc-day-enabled')){ updateDay(e.target.closest('[data-rtsc-day]')); }
            if(e.target.classList.contains('rtsc-method-enabled')){ updateMethod(e.target.closest('[data-rtsc-method]')); }
        });
        document.addEventListener('click', function(e){
            var add = e.target.closest('.rtsc-add-slot');
            if(add){
                e.preventDefault();
                var weekday = add.getAttribute('data-weekday');
                var box = add.closest('[data-rtsc-day]').querySelector('[data-rtsc-slots]');
                var index = Date.now().toString();
                var row = document.createElement('div');
                row.className = 'rtsc-slot-row';
                row.setAttribute('data-rtsc-slot-row','');
                row.innerHTML = '<label><span>از</span><input type="time" step="300" name="'+optionName+'[week_schedule]['+weekday+'][slots]['+index+'][from]" value="09:00"></label>'+
                    '<label><span>تا</span><input type="time" step="300" name="'+optionName+'[week_schedule]['+weekday+'][slots]['+index+'][to]" value="12:00"></label>'+
                    '<button type="button" class="button-link-delete rtsc-remove-slot" aria-label="حذف بازه">حذف</button>';
                box.appendChild(row);
                return;
            }
            var remove = e.target.closest('.rtsc-remove-slot');
            if(remove){
                e.preventDefault();
                var row = remove.closest('[data-rtsc-slot-row]');
                if(row){ row.remove(); }
            }
        });
    })();
    </script>
    <?php
}

/* ---------------------------------------------------------
 * Icons and section heading
 * --------------------------------------------------------- */
function rkzn_icon($name) {
    $icons = [
        'pin'   => '<svg viewBox="0 0 24 24"><path d="M12 21s6-5.4 6-11a6 6 0 1 0-12 0c0 5.6 6 11 6 11Z"/><circle cx="12" cy="10" r="2"/></svg>',
        'plus'  => '<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>',
        'truck' => '<svg viewBox="0 0 24 24"><path d="M3 6h11v11H3z"/><path d="M14 10h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'card'  => '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18"/></svg>',
        'note'  => '<svg viewBox="0 0 24 24"><path d="M6 3h12v18H6z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function rkzn_heading($icon, $title, $subtitle = '') {
    echo '<div class="rkzn-heading">';
    echo '<span class="rkzn-heading-icon">' . rkzn_icon($icon) . '</span>';
    echo '<div><h3>' . esc_html($title) . '</h3>';
    if ($subtitle !== '') {
        echo '<p>' . esc_html($subtitle) . '</p>';
    }
    echo '</div></div>';
}

/* ---------------------------------------------------------
 * Address list block
 * --------------------------------------------------------- */
function rkzn_saved_address() {
    if (!is_user_logged_in()) {
        return [];
    }

    $id = get_current_user_id();
    $data = [
        'name'     => trim(get_user_meta($id, 'billing_first_name', true) . ' ' . get_user_meta($id, 'billing_last_name', true)),
        'phone'    => trim((string) get_user_meta($id, 'billing_phone', true)),
        'state'    => trim((string) get_user_meta($id, 'billing_state', true)),
        'city'     => trim((string) get_user_meta($id, 'billing_city', true)),
        'address1' => trim((string) get_user_meta($id, 'billing_address_1', true)),
        'address2' => trim((string) get_user_meta($id, 'billing_address_2', true)),
        'postcode' => trim((string) get_user_meta($id, 'billing_postcode', true)),
    ];

    $data['has'] = (bool) array_filter($data);
    return $data;
}

function rkzn_render_address_list() {
    $a = rkzn_saved_address();
    ?>
    <section class="rkzn-section rkzn-address-list-section">
        <div class="rkzn-simple-title"><span><?php echo rkzn_icon('pin'); ?></span><h2>لیست آدرس‌ها</h2></div>
        <div class="rkzn-address-list-box">
            <?php if (!empty($a['has'])) : ?>
                <button type="button" class="rkzn-saved-address is-selected" data-rkzn-scroll-form>
                    <span class="rkzn-address-radio"></span>
                    <span class="rkzn-address-copy">
                        <strong><?php echo esc_html($a['name'] ?: 'آدرس فعلی شما'); ?></strong>
                        <small><?php echo esc_html(trim(implode('، ', array_filter([$a['state'], $a['city'], $a['address1'], $a['address2']])))); ?></small>
                        <?php if ($a['phone']) : ?><small dir="ltr"><?php echo esc_html($a['phone']); ?></small><?php endif; ?>
                    </span>
                    <span class="rkzn-address-edit">ویرایش</span>
                </button>
            <?php endif; ?>

            <button type="button" class="rkzn-add-address" data-rkzn-scroll-form>
                <span><?php echo rkzn_icon('plus'); ?></span>
                <strong>افزودن آدرس</strong>
            </button>
        </div>
    </section>
    <?php
}

/* ---------------------------------------------------------
 * Shipping methods block and checkout fragment
 * --------------------------------------------------------- */
function rkzn_shipping_html() {
    ob_start();
    ?>
    <section id="rkzn-shipping-section" class="rkzn-section rkzn-shipping-section">
        <?php rkzn_heading('truck', 'روش ارسال', 'روش مناسب ارسال سفارش را انتخاب کنید.'); ?>
        <div class="rkzn-shipping-options">
            <?php
            $shown = false;
            if (function_exists('WC') && WC()->cart && WC()->cart->needs_shipping()) {
                $packages = WC()->shipping()->get_packages();
                $chosen = WC()->session ? (array) WC()->session->get('chosen_shipping_methods', []) : [];

                foreach ($packages as $package_index => $package) {
                    $rates = $package['rates'] ?? [];
                    foreach ($rates as $method) {
                        $shown = true;
                        $id = 'rkzn_ship_' . absint($package_index) . '_' . sanitize_html_class(str_replace(':', '_', $method->id));
                        ?>
                        <label class="rkzn-shipping-option" for="<?php echo esc_attr($id); ?>">
                            <input type="radio"
                                   name="shipping_method[<?php echo esc_attr($package_index); ?>]"
                                   data-index="<?php echo esc_attr($package_index); ?>"
                                   id="<?php echo esc_attr($id); ?>"
                                   value="<?php echo esc_attr($method->id); ?>"
                                   class="shipping_method"
                                   <?php checked($method->id, $chosen[$package_index] ?? ''); ?>>
                            <span class="rkzn-choice-dot"></span>
                            <span class="rkzn-shipping-vehicle"><?php echo rkzn_icon('truck'); ?></span>
                            <span class="rkzn-shipping-copy">
                                <strong><?php echo wp_kses_post($method->get_label()); ?></strong>
                                <small>زمان و شرایط تحویل براساس نشانی شما محاسبه می‌شود</small>
                            </span>
                            <span class="rkzn-shipping-price">
                                <?php echo (float) $method->get_cost() > 0 ? wp_kses_post(wc_price((float) $method->get_cost())) : 'رایگان'; ?>
                            </span>
                        </label>
                        <?php
                        do_action('woocommerce_after_shipping_rate', $method, $package_index);
                    }
                }
            }

            if (!$shown) : ?>
                <div class="rkzn-shipping-empty">برای نمایش روش‌های ارسال، استان و شهر را تکمیل کنید.</div>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

function rkzn_render_shipping() {
    echo rkzn_shipping_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

add_filter('woocommerce_update_order_review_fragments', function ($fragments) {
    $fragments['#rkzn-shipping-section'] = rkzn_shipping_html();
    return $fragments;
});

/* ---------------------------------------------------------
 * Delivery date and time based on the weekly schedule
 * --------------------------------------------------------- */
function rkzn_posted_checkout_data() {
    $data = [];

    if (isset($_POST['post_data']) && is_string($_POST['post_data'])) {
        parse_str(wp_unslash($_POST['post_data']), $data);
    }

    return is_array($data) ? $data : [];
}

function rkzn_selected_shipping_method_ids() {
    $methods = [];

    if (isset($_POST['shipping_method'])) {
        $raw = wp_unslash($_POST['shipping_method']);
        $methods = is_array($raw) ? $raw : [$raw];
    } else {
        $posted = rkzn_posted_checkout_data();
        if (isset($posted['shipping_method'])) {
            $methods = is_array($posted['shipping_method']) ? $posted['shipping_method'] : [$posted['shipping_method']];
        } elseif (function_exists('WC') && WC()->session) {
            $methods = (array) WC()->session->get('chosen_shipping_methods', []);
        }
    }

    $clean = [];
    foreach ($methods as $method) {
        $method = rkzn_sanitize_shipping_rate_id($method);
        if ($method !== '') {
            $clean[] = $method;
        }
    }
    return array_values(array_unique($clean));
}

function rkzn_delivery_enabled_for_shipping_rate($rate_id) {
    $settings = rkzn_settings();
    if (($settings['delivery_enabled'] ?? 'no') !== 'yes') {
        return false;
    }

    $rate_id = rkzn_sanitize_shipping_rate_id($rate_id);
    if ($rate_id === '') {
        return true;
    }

    $rules = isset($settings['delivery_method_rules']) && is_array($settings['delivery_method_rules']) ? $settings['delivery_method_rules'] : [];
    if (isset($rules[$rate_id])) {
        return $rules[$rate_id] !== 'no';
    }

    // Fallback supports a future rule saved for a whole method type rather than an instance.
    $method_id = explode(':', $rate_id, 2)[0];
    if ($method_id !== '' && isset($rules[$method_id])) {
        return $rules[$method_id] !== 'no';
    }

    // Unseen/new shipping methods are enabled by default.
    return true;
}

function rkzn_delivery_applies_to_checkout() {
    $settings = rkzn_settings();
    if (($settings['delivery_enabled'] ?? 'no') !== 'yes') {
        return false;
    }

    $selected = rkzn_selected_shipping_method_ids();
    if (!$selected) {
        // Before WooCommerce has a selected rate, preserve the existing behavior.
        return true;
    }

    // With multi-package shipping, one scheduled package is enough to require a slot.
    foreach ($selected as $rate_id) {
        if (rkzn_delivery_enabled_for_shipping_rate($rate_id)) {
            return true;
        }
    }

    return false;
}

function rkzn_checkout_value($key, $checkout = null) {
    if (isset($_POST[$key])) {
        return sanitize_text_field(wp_unslash($_POST[$key]));
    }

    $posted = rkzn_posted_checkout_data();
    if (isset($posted[$key]) && !is_array($posted[$key])) {
        return sanitize_text_field((string) $posted[$key]);
    }

    if ($checkout && is_object($checkout) && method_exists($checkout, 'get_value')) {
        return sanitize_text_field((string) $checkout->get_value($key));
    }

    return '';
}

function rkzn_schedule_for_weekday($weekday) {
    $settings = rkzn_settings();
    $schedule = isset($settings['week_schedule']) && is_array($settings['week_schedule']) ? $settings['week_schedule'] : [];
    $day = $schedule[(string) (int) $weekday] ?? ['enabled' => 'no', 'slots' => []];
    return is_array($day) ? $day : ['enabled' => 'no', 'slots' => []];
}

function rkzn_slot_value($slot) {
    if (!is_array($slot)) {
        return '';
    }
    $from = rkzn_sanitize_time($slot['from'] ?? '');
    $to   = rkzn_sanitize_time($slot['to'] ?? '');
    return ($from && $to && $from < $to) ? $from . '-' . $to : '';
}

function rkzn_slot_label($slot) {
    if (!is_array($slot)) {
        return '';
    }
    $from = rkzn_sanitize_time($slot['from'] ?? '');
    $to   = rkzn_sanitize_time($slot['to'] ?? '');
    return ($from && $to) ? $from . ' تا ' . $to : '';
}

function rkzn_delivery_slots_for_weekday($weekday) {
    $day = rkzn_schedule_for_weekday($weekday);
    if (($day['enabled'] ?? 'no') !== 'yes') {
        return [];
    }
    $slots = [];
    foreach ((array) ($day['slots'] ?? []) as $slot) {
        $value = rkzn_slot_value($slot);
        if ($value !== '') {
            $slots[$value] = rkzn_slot_label($slot);
        }
    }
    return $slots;
}

function rkzn_delivery_dates() {
    $s = rkzn_settings();
    $labels = rkzn_weekday_labels();
    $dates = [];
    $cursor = current_time('timestamp');
    $guard = 0;
    $limit = max(1, min(14, (int) $s['delivery_days']));

    while (count($dates) < $limit && $guard < 90) {
        $guard++;
        $weekday = (int) wp_date('w', $cursor);
        $slots = rkzn_delivery_slots_for_weekday($weekday);
        if ($slots) {
            $value = wp_date('Y-m-d', $cursor);
            $distance = (int) floor(($cursor - current_time('timestamp')) / DAY_IN_SECONDS);
            $day_title = $distance <= 0 ? 'امروز' : ($distance === 1 ? 'فردا' : ($labels[$weekday] ?? ''));
            $dates[$value] = [
                'day'     => $day_title,
                'date'    => date_i18n('j F', $cursor),
                'weekday' => $weekday,
                'slots'   => $slots,
            ];
        }
        $cursor = strtotime('+1 day', $cursor);
    }
    return $dates;
}

function rkzn_delivery_date_data($date_value) {
    $dates = rkzn_delivery_dates();
    return $dates[$date_value] ?? null;
}

function rkzn_delivery_html($checkout = null) {
    if (!$checkout && function_exists('WC')) {
        $checkout = WC()->checkout();
    }

    ob_start();
    ?>
    <div id="rkzn-delivery-section-host">
        <?php if (rkzn_delivery_applies_to_checkout()) :
            $dates = rkzn_delivery_dates();
            $selected_date = rkzn_checkout_value('rk_delivery_date', $checkout);
            $selected_slot = rkzn_checkout_value('rk_delivery_slot', $checkout);
            $initial = $selected_date && isset($dates[$selected_date]) ? $dates[$selected_date] : (reset($dates) ?: null);
            $initial_slots = is_array($initial) ? ($initial['slots'] ?? []) : [];
            ?>
            <section class="rkzn-section rkzn-delivery-section" data-rkzn-delivery>
                <?php rkzn_heading('clock', 'انتخاب زمان ارسال', 'روز و بازه زمانی مناسب را مشخص کنید.'); ?>
                <?php if ($dates) : ?>
                    <div class="rkzn-date-grid">
                        <?php foreach ($dates as $value => $date) : ?>
                            <label class="rkzn-date-option">
                                <input type="radio" name="rk_delivery_date" value="<?php echo esc_attr($value); ?>" data-slots="<?php echo esc_attr(wp_json_encode($date['slots'], JSON_UNESCAPED_UNICODE)); ?>" <?php checked($selected_date, $value); ?>>
                                <span><strong><?php echo esc_html($date['day']); ?></strong><small><?php echo esc_html($date['date']); ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="rkzn-slot-grid" data-rkzn-slot-grid data-selected-slot="<?php echo esc_attr($selected_slot); ?>">
                        <?php foreach ($initial_slots as $value => $label) : ?>
                            <label class="rkzn-slot-option">
                                <input type="radio" name="rk_delivery_slot" value="<?php echo esc_attr($value); ?>" <?php checked($selected_slot, $value); ?>>
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="rkzn-shipping-empty">هیچ روز و بازه فعالی برای ارسال تعریف نشده است.</div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function rkzn_render_delivery($checkout) {
    echo rkzn_delivery_html($checkout); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

add_filter('woocommerce_update_order_review_fragments', function ($fragments) {
    $fragments['#rkzn-delivery-section-host'] = rkzn_delivery_html();
    return $fragments;
}, 20);

/* ---------------------------------------------------------
 * Payment and extra information
 * --------------------------------------------------------- */
function rkzn_render_payment() {
    ?>
    <section class="rkzn-section rkzn-payment-section">
        <?php rkzn_heading('card', 'روش پرداخت', 'یکی از روش‌های پرداخت فعال را انتخاب کنید.'); ?>
        <div class="rkzn-payment-inner">
            <?php woocommerce_checkout_payment(); ?>
        </div>
    </section>
    <?php
}

function rkzn_render_extras($checkout) {
    $s = rkzn_settings();
    $items = [
        'rk_need_invoice'     => ['setting' => 'invoice', 'label' => 'فاکتور سفارش را می‌خواهم'],
        'rk_confidential'     => ['setting' => 'confidential', 'label' => 'ارسال بسته محرمانه باشد'],
        'rk_hide_price'       => ['setting' => 'hide_price', 'label' => 'قیمت داخل بسته درج نشود'],
        'rk_is_gift'          => ['setting' => 'gift', 'label' => 'این سفارش هدیه است'],
        'rk_call_before_send' => ['setting' => 'call_before', 'label' => 'قبل از ارسال تماس گرفته شود'],
    ];
    ?>
    <section class="rkzn-section rkzn-extra-section">
        <?php rkzn_heading('note', 'اطلاعات تکمیلی سفارش', 'گزینه‌های مورد نیاز سفارش را مشخص کنید.'); ?>
        <div class="rkzn-extra-grid">
            <?php foreach ($items as $key => $item) :
                if ($s[$item['setting']] !== 'yes') continue; ?>
                <label class="rkzn-extra-option">
                    <input type="checkbox" name="<?php echo esc_attr($key); ?>" value="yes" <?php checked($checkout->get_value($key), 'yes'); ?>>
                    <span class="rkzn-checkbox-ui"></span>
                    <strong><?php echo esc_html($item['label']); ?></strong>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="rkzn-gift-message" hidden>
            <?php woocommerce_form_field('rk_gift_message', [
                'type'        => 'textarea',
                'label'       => 'متن کارت هدیه',
                'required'    => false,
                'placeholder' => 'پیام کوتاه شما برای گیرنده...',
                'class'       => ['form-row-wide', 'rkzn-field'],
                'input_class' => ['rkzn-input'],
            ], $checkout->get_value('rk_gift_message')); ?>
        </div>
    </section>
    <?php
}

/* ---------------------------------------------------------
 * Summary data
 * --------------------------------------------------------- */
function rkzn_savings_total() {
    if (!function_exists('WC') || !WC()->cart) {
        return 0;
    }
    $total = 0.0;
    foreach (WC()->cart->get_cart() as $item) {
        $product = $item['data'] ?? null;
        if (!$product instanceof WC_Product) continue;
        $regular = (float) $product->get_regular_price();
        $current = (float) $product->get_price();
        if ($regular > $current) {
            $total += ($regular - $current) * max(1, (int) ($item['quantity'] ?? 1));
        }
    }
    $total += (float) WC()->cart->get_discount_total();
    return max(0, $total);
}

function rkzn_cart_count() {
    return function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
}

/* ---------------------------------------------------------
 * Checkout validation and HPOS-safe order meta
 * --------------------------------------------------------- */
add_action('woocommerce_checkout_process', function () {
    if (!rkzn_delivery_applies_to_checkout()) {
        return;
    }

    $date = isset($_POST['rk_delivery_date']) ? sanitize_text_field(wp_unslash($_POST['rk_delivery_date'])) : '';
    $slot = isset($_POST['rk_delivery_slot']) ? sanitize_text_field(wp_unslash($_POST['rk_delivery_slot'])) : '';

    if ($date === '') {
        wc_add_notice('لطفاً روز ارسال را انتخاب کنید.', 'error');
        return;
    }

    $date_data = rkzn_delivery_date_data($date);
    if (!$date_data) {
        wc_add_notice('روز ارسال انتخاب‌شده دیگر قابل رزرو نیست. لطفاً دوباره انتخاب کنید.', 'error');
        return;
    }

    if ($slot === '' || !isset($date_data['slots'][$slot])) {
        wc_add_notice('لطفاً یکی از بازه‌های معتبر همان روز را انتخاب کنید.', 'error');
    }
});

add_action('woocommerce_checkout_create_order', function ($order) {
    if (!$order instanceof WC_Order) return;

    $delivery_applies = rkzn_delivery_applies_to_checkout();
    $map = [
        'rk_need_invoice'     => '_rk_need_invoice',
        'rk_confidential'     => '_rk_confidential',
        'rk_hide_price'       => '_rk_hide_price',
        'rk_is_gift'          => '_rk_is_gift',
        'rk_call_before_send' => '_rk_call_before_send',
        'rk_gift_message'     => '_rk_gift_message',
    ];

    if ($delivery_applies) {
        $map = [
            'rk_delivery_date' => '_rk_delivery_date',
            'rk_delivery_slot' => '_rk_delivery_slot',
        ] + $map;
    }

    foreach ($map as $post_key => $meta_key) {
        if (!isset($_POST[$post_key])) continue;
        $raw = wp_unslash($_POST[$post_key]);
        $value = $post_key === 'rk_gift_message' ? sanitize_textarea_field($raw) : sanitize_text_field($raw);
        if ($value !== '') $order->update_meta_data($meta_key, $value);
    }

    if (!$delivery_applies) {
        return;
    }

    $date = isset($_POST['rk_delivery_date']) ? sanitize_text_field(wp_unslash($_POST['rk_delivery_date'])) : '';
    $slot = isset($_POST['rk_delivery_slot']) ? sanitize_text_field(wp_unslash($_POST['rk_delivery_slot'])) : '';
    $date_data = $date ? rkzn_delivery_date_data($date) : null;
    if ($date_data) {
        $order->update_meta_data('_rk_delivery_date_label', trim(($date_data['day'] ?? '') . '، ' . ($date_data['date'] ?? ''), '، '));
        if ($slot && isset($date_data['slots'][$slot])) {
            $order->update_meta_data('_rk_delivery_slot_label', $date_data['slots'][$slot]);
        }
    }
}, 20);

function rkzn_order_meta($order) {
    if (!$order instanceof WC_Order) return [];
    $labels = [
        '_rk_delivery_date_label' => 'روز ارسال',
        '_rk_delivery_slot_label' => 'بازه ارسال',
        '_rk_need_invoice'     => 'درخواست فاکتور',
        '_rk_confidential'     => 'ارسال محرمانه',
        '_rk_hide_price'       => 'عدم درج قیمت',
        '_rk_is_gift'          => 'سفارش هدیه',
        '_rk_call_before_send' => 'تماس قبل از ارسال',
        '_rk_gift_message'     => 'پیام هدیه',
    ];
    $rows = [];
    if (!$order->get_meta('_rk_delivery_date_label') && $order->get_meta('_rk_delivery_date')) {
        $rows['روز ارسال'] = $order->get_meta('_rk_delivery_date');
    }
    if (!$order->get_meta('_rk_delivery_slot_label') && $order->get_meta('_rk_delivery_slot')) {
        $rows['بازه ارسال'] = $order->get_meta('_rk_delivery_slot');
    }
    foreach ($labels as $key => $label) {
        $value = $order->get_meta($key);
        if ($value === '' || $value === null) continue;
        $rows[$label] = $value === 'yes' ? 'بله' : $value;
    }
    return $rows;
}

add_action('woocommerce_admin_order_data_after_shipping_address', function ($order) {
    $rows = rkzn_order_meta($order);
    if (!$rows) return;
    echo '<div class="rkzn-admin-meta"><h3>اطلاعات ارسال و سفارش</h3>';
    foreach ($rows as $label => $value) {
        echo '<p><strong>' . esc_html($label) . ':</strong> ' . nl2br(esc_html($value)) . '</p>';
    }
    echo '</div>';
});

add_filter('woocommerce_email_order_meta_fields', function ($fields, $sent_to_admin, $order) {
    foreach (rkzn_order_meta($order) as $label => $value) {
        $fields['rkzn_' . sanitize_key($label)] = ['label' => $label, 'value' => $value];
    }
    return $fields;
}, 20, 3);

add_action('woocommerce_order_details_after_order_table', function ($order) {
    $rows = rkzn_order_meta($order);
    if (!$rows) return;
    echo '<section class="rkzn-order-meta"><h2>اطلاعات ارسال و سفارش</h2><dl>';
    foreach ($rows as $label => $value) {
        echo '<div><dt>' . esc_html($label) . '</dt><dd>' . nl2br(esc_html($value)) . '</dd></div>';
    }
    echo '</dl></section>';
});

/* ---------------------------------------------------------
 * Page class, CSS and JavaScript
 * --------------------------------------------------------- */
add_filter('body_class', function ($classes) {
    if (rkzn_is_checkout_page()) {
        $classes[] = 'rkzn-checkout-page';
    }
    return $classes;
});

add_action('wp_head', function () {
    if (!rkzn_is_checkout_page()) return;
    ?>
    <style id="rkzn-checkout-css">
    <?php $c = rkzn_settings()['colors']; ?>
:root{--rkzn-purple:<?php echo esc_attr($c['primary']); ?>;--rkzn-purple-dark:<?php echo esc_attr($c['primary_dark']); ?>;--rkzn-soft:<?php echo esc_attr($c['soft']); ?>;--rkzn-border:<?php echo esc_attr($c['border']); ?>;--rkzn-bg:<?php echo esc_attr($c['bg']); ?>;--rkzn-text:<?php echo esc_attr($c['text']); ?>;--rkzn-muted:<?php echo esc_attr($c['muted']); ?>;--rkzn-green:<?php echo esc_attr($c['green']); ?>;--rkzn-card:<?php echo esc_attr($c['card']); ?>}
    body.rkzn-checkout-page{background:#f2f2f2!important}
    body.rkzn-checkout-page .woocommerce{width:min(1180px,calc(100% - 32px))!important;max-width:1180px!important;margin:20px auto 70px!important;padding:0!important;direction:rtl!important;overflow:visible!important}
    body.rkzn-checkout-page .woocommerce form.checkout{width:100%!important;margin:0!important;padding:0!important;background:transparent!important;border:0!important;box-shadow:none!important;overflow:visible!important}
    .rkzn-layout{display:grid;grid-template-columns:320px minmax(0,1fr);gap:24px;align-items:start;direction:ltr;overflow:visible!important;position:relative!important}
    .rkzn-layout,.rkzn-layout *{font-family:"IRANYekanX",Tahoma,Arial,sans-serif!important}
    body.rkzn-checkout-page .select2-container,body.rkzn-checkout-page .select2-dropdown,body.rkzn-checkout-page .select2-results,body.rkzn-checkout-page button,body.rkzn-checkout-page input,body.rkzn-checkout-page select,body.rkzn-checkout-page textarea{font-family:"IRANYekanX",Tahoma,Arial,sans-serif!important}
    body.rkzn-checkout-page .site,body.rkzn-checkout-page .site-content,body.rkzn-checkout-page .content-area,body.rkzn-checkout-page .site-main,body.rkzn-checkout-page .entry-content,body.rkzn-checkout-page .page-content{overflow:visible!important}
    .rkzn-main,.rkzn-summary{direction:rtl;min-width:0}
    .rkzn-main{background:#fff;border:1px solid #cfcfcf;border-radius:4px;overflow:hidden}
    .rkzn-summary{position:sticky!important;top:24px!important;right:auto!important;left:auto!important;bottom:auto!important;align-self:start!important;z-index:20;background:#fff;border:1px solid #ece7f3;border-radius:18px;padding:22px 20px 20px;box-shadow:0 14px 42px rgba(35,18,52,.055);overflow:visible!important}
    body.admin-bar.rkzn-checkout-page .rkzn-summary{top:56px!important}
    .rkzn-summary.rkzn-summary-fixed,.rkzn-summary.rkzn-summary-bottom{position:sticky!important;top:24px!important;right:auto!important;left:auto!important;bottom:auto!important;width:auto!important;margin:0!important}
    body.admin-bar.rkzn-checkout-page .rkzn-summary.rkzn-summary-fixed,body.admin-bar.rkzn-checkout-page .rkzn-summary.rkzn-summary-bottom{top:56px!important}
    .rkzn-summary-title{display:flex;align-items:center;justify-content:flex-start;gap:7px;margin:0 0 18px;padding:0 0 16px;border-bottom:1px solid #ece7f3;font-size:20px!important;line-height:1.6!important;font-weight:950!important;color:#351052!important;text-align:right!important}
    .rkzn-summary-title svg{display:none!important}
    .rkzn-section{padding:18px 14px;border-bottom:1px solid #d8d8d8;background:#fff}
    .rkzn-section:last-child{border-bottom:0}
    .rkzn-simple-title{display:flex;align-items:center;justify-content:flex-start;gap:6px;margin-bottom:13px}
    .rkzn-simple-title span{width:18px;height:18px;color:#777;display:inline-flex}.rkzn-simple-title svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8}
    .rkzn-simple-title h2{margin:0!important;font-size:14px!important;font-weight:900!important;color:#222!important}
    .rkzn-address-list-box{background:#f5f5f5;border:1px solid #d7d7d7;border-radius:4px;padding:12px;display:grid;gap:9px}
    .rkzn-add-address{width:100%;min-height:84px;border:1px solid var(--rkzn-purple)!important;border-radius:4px!important;background:#fff!important;color:var(--rkzn-purple)!important;display:flex!important;align-items:center!important;justify-content:center!important;gap:6px!important;cursor:pointer!important;box-shadow:none!important}
    .rkzn-add-address span{display:inline-flex;width:18px;height:18px}.rkzn-add-address svg{width:18px;height:18px;stroke:var(--rkzn-green);fill:none;stroke-width:2}.rkzn-add-address strong{font-size:13px}
    .rkzn-saved-address{width:100%;border:1px solid var(--rkzn-purple)!important;border-radius:4px!important;background:#fff!important;padding:10px!important;display:grid!important;grid-template-columns:18px 1fr auto!important;gap:9px!important;align-items:center!important;text-align:right!important;color:#222!important;box-shadow:none!important;cursor:pointer!important}
    .rkzn-address-radio{width:14px;height:14px;border:1px solid var(--rkzn-purple);border-radius:50%;box-shadow:inset 0 0 0 3px #fff;background:var(--rkzn-purple)}
    .rkzn-address-copy{display:flex;flex-direction:column;gap:3px;min-width:0}.rkzn-address-copy strong{font-size:12px}.rkzn-address-copy small{font-size:10px;color:#777;line-height:1.8}.rkzn-address-edit{font-size:10px;color:var(--rkzn-purple);font-weight:800}
    .rkzn-heading{display:flex;align-items:flex-start;gap:8px;margin:0 0 15px}
    .rkzn-heading-icon{width:28px;height:28px;min-width:28px;border-radius:50%;background:#f3e6f4;color:var(--rkzn-purple);display:flex;align-items:center;justify-content:center}
    .rkzn-heading-icon svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.8}
    .rkzn-heading h3{margin:0!important;font-size:14px!important;line-height:1.7!important;font-weight:900!important;color:#222!important}.rkzn-heading p{margin:2px 0 0!important;color:#777!important;font-size:10px!important;line-height:1.8!important}
    .rkzn-address-form-section{padding-top:16px}
    .rkzn-address-form-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:13px}.rkzn-address-form-header h3{font-size:14px!important;margin:0!important;font-weight:900!important}.rkzn-clear-fields{border:1px solid var(--rkzn-purple)!important;background:#fff!important;color:var(--rkzn-purple)!important;border-radius:3px!important;padding:5px 9px!important;font-size:10px!important;display:inline-flex!important;align-items:center!important;gap:4px!important;box-shadow:none!important}.rkzn-clear-fields svg{width:13px;height:13px;stroke:var(--rkzn-green);fill:none;stroke-width:1.8}
    .woocommerce-billing-fields__field-wrapper.rkzn-fields-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:10px 10px!important}
    body.rkzn-checkout-page .rkzn-fields-grid .form-row{width:auto!important;float:none!important;margin:0!important;position:relative!important;padding:7px 0 0!important;clear:none!important}
    body.rkzn-checkout-page .rkzn-fields-grid .form-row label{position:absolute!important;top:0!important;right:9px!important;z-index:3!important;margin:0!important;padding:0 4px!important;background:#fff!important;color:#666!important;font-size:9px!important;font-weight:700!important;line-height:1.4!important;max-width:calc(100% - 18px)!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
    body.rkzn-checkout-page .rkzn-fields-grid .form-row label .required{color:#d50000!important}
    body.rkzn-checkout-page .rkzn-fields-grid input.input-text,body.rkzn-checkout-page .rkzn-fields-grid select,body.rkzn-checkout-page .rkzn-fields-grid textarea,body.rkzn-checkout-page .rkzn-fields-grid .select2-selection{width:100%!important;border:1px solid #aaa!important;border-radius:3px!important;background:#fff!important;color:#222!important;box-shadow:none!important;font-size:11px!important;min-height:38px!important;padding:9px 10px!important;line-height:1.7!important}
    body.rkzn-checkout-page .rkzn-fields-grid textarea{min-height:72px!important;resize:vertical!important}
    body.rkzn-checkout-page .rkzn-fields-grid .select2-container{width:100%!important}.rkzn-fields-grid .select2-selection__rendered{line-height:36px!important;padding-right:9px!important}.rkzn-fields-grid .select2-selection__arrow{height:36px!important}
    body.rkzn-checkout-page .rkzn-fields-grid .form-row-wide{grid-column:auto!important}
    body.rkzn-checkout-page .rkzn-fields-grid #billing_country_field{display:none!important}
    body.rkzn-checkout-page .rkzn-fields-grid #billing_postcode_field,
    body.rkzn-checkout-page .rkzn-fields-grid .rkzn-birthdate-field,
    body.rkzn-checkout-page .rkzn-fields-grid #billing_address_1_field,
    body.rkzn-checkout-page .rkzn-fields-grid #order_comments_field{grid-column:auto!important;min-width:0!important}
    body.rkzn-checkout-page .rkzn-fields-grid #billing_address_1_field textarea,
    body.rkzn-checkout-page .rkzn-fields-grid #order_comments_field textarea{min-height:82px!important;height:82px!important}
    body.rkzn-checkout-page .rkzn-fields-grid .form-row[style*="display: none"]{display:none!important}
    .rkzn-birthdate-field{grid-column:auto!important;padding-top:0!important;min-width:0!important}
    body.rkzn-checkout-page .rkzn-fields-grid .rkzn-birthdate-field>.rkzn-birthdate-label{position:static!important;display:block!important;margin:0 0 6px!important;padding:0!important;background:transparent!important;color:#666!important;font-size:11px!important;line-height:1.8!important;max-width:none!important;white-space:normal!important;overflow:visible!important}
    .rkzn-birthdate-selects{display:grid!important;grid-template-columns:minmax(54px,.7fr) minmax(92px,1.2fr) minmax(72px,.9fr)!important;gap:9px!important;width:100%!important}
    body.rkzn-checkout-page .rkzn-fields-grid .rkzn-birthdate-selects select{width:100%!important;min-height:42px!important;margin:0!important;border:1px solid #aaa!important;border-radius:3px!important;background:#fff!important;color:#222!important;padding:7px 9px!important;font-size:11px!important;box-shadow:none!important;font-family:inherit!important}
    .rkzn-shipping-options{display:grid;gap:8px}.rkzn-shipping-option{display:grid;grid-template-columns:18px 46px 1fr auto;gap:10px;align-items:center;min-height:60px;padding:8px 11px;background:#ead3eb;border:1px solid #dfc1e2;border-radius:3px;cursor:pointer}.rkzn-shipping-option input{position:absolute;opacity:0;pointer-events:none}.rkzn-choice-dot{width:14px;height:14px;border:1px solid var(--rkzn-purple);border-radius:50%;background:#fff;box-shadow:inset 0 0 0 3px #fff}.rkzn-shipping-option input:checked+.rkzn-choice-dot{background:var(--rkzn-purple)}.rkzn-shipping-vehicle{width:42px;height:42px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center}.rkzn-shipping-vehicle svg{width:27px;height:27px;stroke:var(--rkzn-green);fill:none;stroke-width:1.6}.rkzn-shipping-copy{display:flex;flex-direction:column;gap:2px}.rkzn-shipping-copy strong{font-size:12px;color:#222}.rkzn-shipping-copy small{font-size:9px;color:#666}.rkzn-shipping-price{font-size:10px;font-weight:800;white-space:nowrap}.rkzn-shipping-empty{padding:15px;border:1px dashed #c8c8c8;background:#fafafa;color:#777;text-align:center;font-size:11px}
    .rkzn-date-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border:1px solid #d0d0d0;border-radius:3px;overflow:hidden}.rkzn-date-option{margin:0!important;border-left:1px solid #ddd;cursor:pointer}.rkzn-date-option:last-child{border-left:0}.rkzn-date-option input{position:absolute;opacity:0}.rkzn-date-option span{min-height:53px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;background:#fff}.rkzn-date-option strong{font-size:10px}.rkzn-date-option small{font-size:9px;color:#777}.rkzn-date-option input:checked+span{background:#f4e3f5;color:var(--rkzn-purple)}
    .rkzn-slot-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:9px;min-height:30px}.rkzn-slot-option{margin:0!important;cursor:pointer}.rkzn-slot-option input{position:absolute;opacity:0}.rkzn-slot-option span{display:inline-flex;align-items:center;justify-content:center;min-height:30px;padding:4px 13px;border:1px solid #ccc;border-radius:3px;background:#fff;font-size:10px}.rkzn-slot-option input:checked+span{border-color:var(--rkzn-purple);background:#f4e3f5;color:var(--rkzn-purple);font-weight:900}
    .rkzn-payment-inner #payment{background:transparent!important;border:0!important;border-radius:0!important}
    .rkzn-payment-inner #payment ul.payment_methods{margin:0!important;padding:0!important;border:0!important;display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;grid-auto-flow:row!important;grid-auto-rows:1fr!important;align-items:stretch!important;gap:10px!important;list-style:none!important}
    .rkzn-payment-inner #payment ul.payment_methods>li,
    .rkzn-payment-inner #payment ul.payment_methods>li.wc_payment_method{position:relative!important;inset:auto!important;float:none!important;clear:none!important;transform:none!important;width:100%!important;max-width:none!important;min-width:0!important;height:100%!important;margin:0!important;padding:9px 10px!important;box-sizing:border-box!important;border:1px solid #e6ddec!important;border-radius:10px!important;background:#fff!important;list-style:none!important;box-shadow:0 4px 14px rgba(53,16,82,.035)!important;display:grid!important;grid-template-columns:18px minmax(0,1fr)!important;grid-template-rows:auto 1fr!important;align-content:start!important;align-items:start!important;column-gap:7px!important;transition:border-color .18s ease,background .18s ease,box-shadow .18s ease!important}
    .rkzn-payment-inner #payment ul.payment_methods>li:has(input[type=radio]:checked){border-color:var(--rkzn-purple)!important;background:#fcf8ff!important;box-shadow:0 5px 16px rgba(108,31,175,.08)!important}
    .rkzn-payment-inner #payment ul.payment_methods>li>input[type=radio]{grid-column:1!important;grid-row:1!important;align-self:center!important;margin:0!important;vertical-align:middle!important}
    .rkzn-payment-inner #payment ul.payment_methods>li>label{grid-column:2!important;grid-row:1!important;min-width:0!important;min-height:38px!important;margin:0!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:8px!important;font-size:11px!important;font-weight:800!important;line-height:1.7!important;cursor:pointer!important}
    .rkzn-payment-inner #payment ul.payment_methods>li>label img{display:block!important;float:none!important;margin:0 auto 0 0!important;max-width:105px!important;max-height:32px!important;width:auto!important;height:auto!important;object-fit:contain!important}
    .rkzn-payment-inner #payment .payment_box{grid-column:1/-1!important;grid-row:2!important;width:100%!important;box-sizing:border-box!important;margin:7px 0 0!important;padding:7px 9px!important;border:0!important;border-radius:7px!important;background:#f7f4f9!important;color:#666!important;font-size:9px!important;line-height:1.75!important;box-shadow:none!important}
    .rkzn-payment-inner #payment .payment_box p{margin:0!important}
    .rkzn-payment-inner #payment .payment_box:before{display:none!important}
    .rkzn-payment-inner #payment .place-order{display:none!important}
    .rkzn-extra-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.rkzn-extra-option{display:grid;grid-template-columns:18px 1fr;gap:7px;align-items:center;min-height:42px;margin:0!important;padding:8px 10px;border:1px solid #ddd;border-radius:3px;background:#fff;cursor:pointer}.rkzn-extra-option input{position:absolute;opacity:0}.rkzn-checkbox-ui{width:15px;height:15px;border:1px solid #aaa;border-radius:3px;background:#fff;position:relative}.rkzn-extra-option input:checked+.rkzn-checkbox-ui{background:var(--rkzn-purple);border-color:var(--rkzn-purple)}.rkzn-extra-option input:checked+.rkzn-checkbox-ui:after{content:"✓";position:absolute;color:#fff;font-size:11px;right:2px;top:-1px}.rkzn-extra-option strong{font-size:10px;line-height:1.7}.rkzn-gift-message{margin-top:10px}.rkzn-gift-message .form-row{margin:0!important}.rkzn-gift-message label{font-size:10px}.rkzn-gift-message textarea{width:100%;min-height:65px;border:1px solid #aaa;border-radius:3px;padding:9px;font-size:11px}
    .rkzn-summary #order_review{margin:0!important;padding:0!important;border:0!important;background:transparent!important;box-shadow:none!important;overflow:visible!important}
    .rkzn-summary .shop_table,.rkzn-summary .shop_table tbody{width:100%!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;border-collapse:separate!important;border-spacing:0!important;background:transparent!important;box-shadow:none!important;overflow:visible!important}
    .rkzn-summary .shop_table tr{border:0!important;background:transparent!important;box-shadow:none!important}
    .rkzn-summary .shop_table th,.rkzn-summary .shop_table td{padding:14px 0!important;border:0!important;border-bottom:1px solid #eee8f3!important;background:transparent!important;font-size:13px!important;line-height:1.9!important;color:#4b235f!important;vertical-align:middle!important}
    .rkzn-summary .shop_table th{width:58%!important;text-align:right!important;font-weight:750!important}
    .rkzn-summary .shop_table td{width:42%!important;text-align:left!important;font-weight:950!important;white-space:nowrap!important;color:#351052!important}
    .rkzn-summary .shop_table tr:last-child th,.rkzn-summary .shop_table tr:last-child td{border-bottom:0!important}
    .rkzn-summary .rkzn-saving-row th,.rkzn-summary .rkzn-saving-row td{color:#16a060!important}
    .rkzn-summary .rkzn-summary-shipping th,.rkzn-summary .rkzn-summary-shipping td{padding-top:13px!important;padding-bottom:13px!important;background:#f4ecff!important;border-bottom:0!important}
    .rkzn-summary .rkzn-summary-shipping th{padding-right:13px!important;border-radius:0 13px 13px 0!important}
    .rkzn-summary .rkzn-summary-shipping td{padding-left:13px!important;border-radius:13px 0 0 13px!important;color:#6c1faf!important}
    .rkzn-summary .order-total th,.rkzn-summary .order-total td{padding-top:18px!important;font-size:15px!important;font-weight:950!important;color:#351052!important}
    .rkzn-summary .woocommerce-shipping-totals{display:none!important}
    .rkzn-place-order-host{margin-top:18px!important;padding:0!important;background:transparent!important;border:0!important}
    .rkzn-place-order-host .place-order{margin:0!important;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important}
    .rkzn-place-order-host .woocommerce-terms-and-conditions-wrapper{margin:0 0 12px!important;font-size:11px!important;line-height:1.9!important;color:#81758b!important}
    .rkzn-place-order-host .woocommerce-privacy-policy-text p{margin:0 0 10px!important}
    .rkzn-place-order-host #place_order{float:none!important;width:100%!important;min-height:50px!important;margin:0!important;border:0!important;border-radius:12px!important;background:var(--rkzn-purple)!important;color:#fff!important;font-family:inherit!important;font-size:14px!important;font-weight:900!important;line-height:1.5!important;letter-spacing:normal!important;text-indent:0!important;text-align:center!important;display:flex!important;align-items:center!important;justify-content:center!important;box-shadow:none!important;transition:.2s ease!important}
    .rkzn-place-order-host #place_order:hover{background:var(--rkzn-purple-dark)!important;transform:translateY(-1px)}
    .rkzn-summary-back{width:100%;min-height:44px;border:1px solid #ef4056!important;border-radius:12px!important;background:#fff!important;color:#ef4056!important;text-decoration:none!important;font-size:12px!important;font-weight:900!important;display:flex!important;align-items:center!important;justify-content:center!important;margin-top:12px!important;box-sizing:border-box!important}
    body.rkzn-checkout-page .woocommerce-error,body.rkzn-checkout-page .woocommerce-info,body.rkzn-checkout-page .woocommerce-message{font-size:11px!important;line-height:1.9!important;margin:0 0 12px!important}
    body.rkzn-checkout-page .woocommerce-checkout-review-order-table .woocommerce-Price-currencySymbol{font-size:.8em}
    body.rkzn-checkout-page .blockUI.blockOverlay{background:#fff!important;opacity:.65!important}
    @media(max-width:920px){body.rkzn-checkout-page .woocommerce{width:min(100% - 18px,760px)!important}.rkzn-layout{grid-template-columns:1fr}.rkzn-main{order:1}.rkzn-summary,.rkzn-summary.rkzn-summary-fixed,.rkzn-summary.rkzn-summary-bottom{order:2;position:static!important;top:auto!important;right:auto!important;left:auto!important;bottom:auto!important;width:100%!important}.rkzn-date-grid{grid-template-columns:repeat(2,1fr)}.rkzn-date-option:nth-child(2){border-left:0}.rkzn-fields-grid{grid-template-columns:1fr 1fr!important}.rkzn-payment-inner #payment ul.payment_methods{grid-template-columns:1fr!important}}
    @media(max-width:560px){body.rkzn-checkout-page .woocommerce{width:calc(100% - 14px)!important}.rkzn-section{padding:15px 11px}.woocommerce-billing-fields__field-wrapper.rkzn-fields-grid{grid-template-columns:1fr!important}.rkzn-birthdate-selects{grid-template-columns:1fr!important}.rkzn-extra-grid{grid-template-columns:1fr}.rkzn-date-grid{grid-template-columns:1fr 1fr}.rkzn-shipping-option{grid-template-columns:17px 38px 1fr}.rkzn-shipping-price{grid-column:3}.rkzn-shipping-vehicle{width:36px;height:36px}.rkzn-summary{padding:13px}}
    </style>
    <?php
}, 999);

add_action('wp_footer', function () {
    if (!rkzn_is_checkout_page()) return;
    ?>
    <script id="rkzn-checkout-js">
    (function($){
        'use strict';
        function syncBirthdate($wrap){
            var day = parseInt($wrap.find('.rkzn-birthdate-day').val(),10) || 0;
            var month = parseInt($wrap.find('.rkzn-birthdate-month').val(),10) || 0;
            var year = parseInt($wrap.find('.rkzn-birthdate-year').val(),10) || 0;
            var value = '';
            if(day && month && year){
                value = String(year).padStart(4,'0') + '/' + String(month).padStart(2,'0') + '/' + String(day).padStart(2,'0');
            }
            $wrap.find('.rkzn-birthdate-value').val(value).trigger('change');
        }
        function movePlaceOrder(){
            var $host = $('#rkzn-place-order-host');
            var $place = $('#payment .place-order');
            if($host.length && $place.length){ $host.empty().append($place); }
        }
        function giftToggle(){
            var checked = $('input[name="rk_is_gift"]').is(':checked');
            $('.rkzn-gift-message').prop('hidden', !checked);
        }
        function renderDeliverySlots($dateInput){
            var $grid = $('[data-rkzn-slot-grid]');
            if(!$grid.length || !$dateInput || !$dateInput.length){ return; }
            var slots = {};
            try { slots = JSON.parse($dateInput.attr('data-slots') || '{}'); } catch(e) { slots = {}; }
            var selected = $grid.attr('data-selected-slot') || $('input[name="rk_delivery_slot"]:checked').val() || '';
            $grid.empty();
            $.each(slots, function(value, label){
                var id = 'rk-delivery-slot-' + String(value).replace(/[^a-zA-Z0-9_-]/g,'-');
                var $label = $('<label/>', {'class':'rkzn-slot-option'});
                var $input = $('<input/>', {type:'radio', name:'rk_delivery_slot', id:id, value:value});
                if(String(value) === String(selected)){ $input.prop('checked', true); }
                $label.append($input).append($('<span/>').text(label));
                $grid.append($label);
            });
            $grid.attr('data-selected-slot','');
        }
        function boot(){
            movePlaceOrder();
            giftToggle();
            $('.rkzn-birthdate-selects').each(function(){ syncBirthdate($(this)); });
            var $checked = $('input[name="rk_delivery_date"]:checked');
            if($checked.length){ renderDeliverySlots($checked); }
        }
        $(document).on('change','input[name="rk_is_gift"]',giftToggle);
        $(document).on('change','.rkzn-birthdate-select',function(){ syncBirthdate($(this).closest('[data-rkzn-birthdate]')); });
        $(document).on('change','input[name="rk_delivery_date"]',function(){ renderDeliverySlots($(this)); });
        $(document).on('click','[data-rkzn-scroll-form]',function(){
            var $target = $('#rkzn-address-form');
            if($target.length){ $('html,body').animate({scrollTop:$target.offset().top-90},350); setTimeout(function(){$target.find('input,select,textarea').filter(':visible').first().trigger('focus');},380); }
        });
        $(document).on('click','.rkzn-clear-fields',function(){
            $('#rkzn-address-form').find('input.input-text,textarea').not('[type="hidden"]').val('').trigger('change');
            $('#rkzn-address-form').find('select').val('').trigger('change');
            $('#rkzn-address-form').find('.rkzn-birthdate-value').val('').trigger('change');
        });
        $(document.body).on('updated_checkout',boot);
        $(boot);
    })(jQuery);
    </script>
    <?php
}, 999);

/* ---------------------------------------------------------
 * v10 layout hardening: explicit field rows and payment grid
 * --------------------------------------------------------- */
add_action('wp_head', function () {
    if (!rkzn_is_checkout_page()) {
        return;
    }
    ?>
    <style id="rkzn-checkout-v10-layout-css">
    body.rkzn-checkout-page .woocommerce-billing-fields__field-wrapper.rkzn-fields-grid{
        display:block!important;
        width:100%!important;
    }
    body.rkzn-checkout-page .rkzn-hidden-checkout-fields,
    body.rkzn-checkout-page .rkzn-hidden-checkout-fields #billing_country_field{
        display:none!important;
    }
    body.rkzn-checkout-page .rkzn-field-row{
        display:grid!important;
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:10px!important;
        width:100%!important;
        margin:0 0 10px!important;
        padding:0!important;
        align-items:stretch!important;
        clear:both!important;
    }
    body.rkzn-checkout-page .rkzn-field-row:last-child{margin-bottom:0!important}
    body.rkzn-checkout-page .rkzn-field-row>.form-row{
        grid-column:auto!important;
        width:100%!important;
        min-width:0!important;
        max-width:none!important;
        margin:0!important;
        float:none!important;
        clear:none!important;
        box-sizing:border-box!important;
    }
    body.rkzn-checkout-page .rkzn-field-row>.form-row:only-child{
        grid-column:1/-1!important;
    }
    body.rkzn-checkout-page .rkzn-field-row-address-notes>.form-row{
        display:flex!important;
        flex-direction:column!important;
        min-height:94px!important;
    }
    body.rkzn-checkout-page .rkzn-field-row-address-notes>.form-row .woocommerce-input-wrapper{
        display:flex!important;
        flex:1 1 auto!important;
        width:100%!important;
    }
    body.rkzn-checkout-page .rkzn-field-row-address-notes textarea{
        width:100%!important;
        min-height:86px!important;
        height:86px!important;
        max-height:86px!important;
        resize:none!important;
    }
    body.rkzn-checkout-page .rkzn-field-row-postcode-birth{
        align-items:end!important;
    }
    body.rkzn-checkout-page .rkzn-field-row-postcode-birth>.rkzn-birthdate-field{
        position:relative!important;
        display:block!important;
        padding:7px 0 0!important;
        min-height:45px!important;
    }
    body.rkzn-checkout-page .rkzn-field-row-postcode-birth>.rkzn-birthdate-field>.rkzn-birthdate-label{
        position:absolute!important;
        top:0!important;
        right:9px!important;
        z-index:3!important;
        display:block!important;
        max-width:calc(100% - 18px)!important;
        margin:0!important;
        padding:0 4px!important;
        overflow:hidden!important;
        background:#fff!important;
        color:#666!important;
        font-size:9px!important;
        font-weight:700!important;
        line-height:1.4!important;
        white-space:nowrap!important;
        text-overflow:ellipsis!important;
    }
    body.rkzn-checkout-page .rkzn-field-row-postcode-birth .rkzn-birthdate-selects{
        display:grid!important;
        grid-template-columns:minmax(54px,.72fr) minmax(92px,1.25fr) minmax(74px,.9fr)!important;
        gap:8px!important;
        width:100%!important;
    }
    body.rkzn-checkout-page .rkzn-field-row-postcode-birth .rkzn-birthdate-selects select{
        width:100%!important;
        min-width:0!important;
        min-height:38px!important;
        height:38px!important;
        margin:0!important;
        padding:7px 8px!important;
        border:1px solid #aaa!important;
        border-radius:3px!important;
        background:#fff!important;
        font-family:"IRANYekanX",Tahoma,Arial,sans-serif!important;
        font-size:10px!important;
        line-height:1.5!important;
        box-shadow:none!important;
    }
    body.rkzn-checkout-page .rkzn-field-row-extra{
        margin-top:0!important;
    }

    body.rkzn-checkout-page .rkzn-payment-inner #payment{
        width:100%!important;
        overflow:visible!important;
    }
    body.rkzn-checkout-page .rkzn-payment-inner #payment ul.payment_methods,
    body.rkzn-checkout-page .rkzn-payment-inner #payment ul.wc_payment_methods{
        display:grid!important;
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        grid-auto-flow:row!important;
        grid-auto-rows:auto!important;
        align-items:stretch!important;
        justify-items:stretch!important;
        gap:10px!important;
        width:100%!important;
        margin:0!important;
        padding:0!important;
        border:0!important;
        list-style:none!important;
        direction:rtl!important;
    }
    body.rkzn-checkout-page .rkzn-payment-inner #payment ul.payment_methods>li,
    body.rkzn-checkout-page .rkzn-payment-inner #payment ul.wc_payment_methods>li,
    body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method{
        position:relative!important;
        inset:auto!important;
        top:auto!important;
        right:auto!important;
        bottom:auto!important;
        left:auto!important;
        float:none!important;
        clear:none!important;
        transform:none!important;
        display:grid!important;
        grid-template-columns:18px minmax(0,1fr)!important;
        grid-template-rows:auto auto!important;
        align-content:start!important;
        align-items:center!important;
        align-self:stretch!important;
        column-gap:8px!important;
        row-gap:0!important;
        width:100%!important;
        min-width:0!important;
        max-width:none!important;
        min-height:72px!important;
        height:auto!important;
        margin:0!important;
        padding:10px 11px!important;
        box-sizing:border-box!important;
        overflow:hidden!important;
        border:1px solid #e3d7e8!important;
        border-radius:10px!important;
        background:#fff!important;
        box-shadow:0 4px 14px rgba(53,16,82,.035)!important;
        list-style:none!important;
        vertical-align:top!important;
    }
    body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method.rkzn-payment-selected{
        border-color:#96349a!important;
        background:#fcf8ff!important;
        box-shadow:0 5px 16px rgba(108,31,175,.08)!important;
    }
    body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method>input[type=radio]{
        position:static!important;
        grid-column:1!important;
        grid-row:1!important;
        align-self:center!important;
        width:15px!important;
        height:15px!important;
        margin:0!important;
        opacity:1!important;
    }
    body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method>label{
        position:static!important;
        grid-column:2!important;
        grid-row:1!important;
        display:flex!important;
        align-items:center!important;
        justify-content:space-between!important;
        gap:8px!important;
        width:100%!important;
        min-width:0!important;
        min-height:36px!important;
        margin:0!important;
        padding:0!important;
        color:#4b235f!important;
        font-family:"IRANYekanX",Tahoma,Arial,sans-serif!important;
        font-size:11px!important;
        font-weight:800!important;
        line-height:1.7!important;
        text-align:right!important;
        cursor:pointer!important;
    }
    body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method>label img{
        position:static!important;
        float:none!important;
        flex:0 0 auto!important;
        display:block!important;
        width:auto!important;
        height:auto!important;
        max-width:92px!important;
        max-height:30px!important;
        margin:0 auto 0 0!important;
        object-fit:contain!important;
    }
    body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method>.payment_box{
        position:static!important;
        grid-column:1/-1!important;
        grid-row:2!important;
        width:100%!important;
        min-width:0!important;
        margin:7px 0 0!important;
        padding:7px 8px!important;
        box-sizing:border-box!important;
        border:0!important;
        border-radius:7px!important;
        background:#f7f4f9!important;
        color:#666!important;
        font-family:"IRANYekanX",Tahoma,Arial,sans-serif!important;
        font-size:9px!important;
        line-height:1.75!important;
        box-shadow:none!important;
    }
    body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method>.payment_box:before{display:none!important}
    body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method>.payment_box p{margin:0!important}

    @media(max-width:920px){
        body.rkzn-checkout-page .rkzn-payment-inner #payment ul.payment_methods,
        body.rkzn-checkout-page .rkzn-payment-inner #payment ul.wc_payment_methods{
            grid-template-columns:repeat(2,minmax(0,1fr))!important;
        }
    }
    @media(max-width:640px){
        body.rkzn-checkout-page .rkzn-field-row{
            grid-template-columns:1fr!important;
        }
        body.rkzn-checkout-page .rkzn-payment-inner #payment ul.payment_methods,
        body.rkzn-checkout-page .rkzn-payment-inner #payment ul.wc_payment_methods{
            grid-template-columns:1fr!important;
        }
        body.rkzn-checkout-page .rkzn-field-row-postcode-birth .rkzn-birthdate-selects{
            grid-template-columns:repeat(3,minmax(0,1fr))!important;
        }
    }
    </style>
    <?php
}, 10000);

add_action('wp_footer', function () {
    if (!rkzn_is_checkout_page()) {
        return;
    }
    ?>
    <script id="rkzn-checkout-v10-layout-js">
    (function($){
        'use strict';

        function fieldById(id){
            var $field = $('#' + id);
            return $field.length ? $field.first() : $();
        }

        function findBirthField($grid){
            var $birth = $grid.find('.rkzn-birthdate-field').first();
            if ($birth.length) return $birth;

            $birth = $grid.find('.form-row').filter(function(){
                var haystack = [this.id || '', $(this).text() || ''].join(' ').toLowerCase();
                return /birth|birthday|date[\s_-]*of[\s_-]*birth|dob|تولد/.test(haystack);
            }).first();
            return $birth;
        }

        function ensureRow($grid, className){
            var $row = $grid.children('.' + className).first();
            if (!$row.length){
                $row = $('<div/>', {'class':'rkzn-field-row ' + className});
                $grid.append($row);
            }
            return $row;
        }

        function normalizeFieldRows(){
            var $grid = $('.woocommerce-billing-fields__field-wrapper.rkzn-fields-grid').first();
            if (!$grid.length) return;

            var $country = fieldById('billing_country_field');
            var $hidden = $grid.children('.rkzn-hidden-checkout-fields').first();
            if (!$hidden.length){
                $hidden = $('<div/>', {'class':'rkzn-hidden-checkout-fields', 'aria-hidden':'true'}).prependTo($grid);
            }
            if ($country.length) $hidden.append($country);

            var rows = [
                ['rkzn-field-row-names', fieldById('billing_first_name_field'), fieldById('billing_last_name_field')],
                ['rkzn-field-row-contact', fieldById('billing_phone_field'), fieldById('billing_email_field')],
                ['rkzn-field-row-location', fieldById('billing_state_field'), fieldById('billing_city_field')],
                ['rkzn-field-row-postcode-birth', fieldById('billing_postcode_field'), findBirthField($grid)],
                ['rkzn-field-row-address-notes', fieldById('billing_address_1_field'), fieldById('order_comments_field')]
            ];

            $.each(rows, function(_, data){
                var $row = ensureRow($grid, data[0]);
                $.each([data[1], data[2]], function(__, $field){
                    if ($field && $field.length) $row.append($field);
                });
            });

            var known = [
                'billing_country_field','billing_first_name_field','billing_last_name_field',
                'billing_phone_field','billing_email_field','billing_state_field','billing_city_field',
                'billing_postcode_field','billing_address_1_field','order_comments_field'
            ];
            var $birth = findBirthField($grid);
            var $extra = ensureRow($grid, 'rkzn-field-row-extra');
            $grid.children('.form-row').each(function(){
                if (known.indexOf(this.id) === -1 && (!$birth.length || this !== $birth.get(0))){
                    $extra.append(this);
                }
            });
            if (!$extra.children().length) $extra.remove();
        }

        function normalizePayments(){
            var $list = $('.rkzn-payment-inner #payment ul.payment_methods, .rkzn-payment-inner #payment ul.wc_payment_methods').first();
            if (!$list.length) return;

            $list.css({
                display:'grid',
                gridTemplateColumns: window.matchMedia('(max-width:640px)').matches ? '1fr' : 'repeat(2,minmax(0,1fr))',
                gridAutoFlow:'row',
                alignItems:'stretch',
                gap:'10px',
                width:'100%',
                margin:'0',
                padding:'0'
            });

            $list.children('li.wc_payment_method').each(function(){
                var $item = $(this);
                $item.toggleClass('rkzn-payment-selected', $item.children('input[type=radio]').is(':checked'));
                this.style.setProperty('position','relative','important');
                this.style.setProperty('top','auto','important');
                this.style.setProperty('right','auto','important');
                this.style.setProperty('bottom','auto','important');
                this.style.setProperty('left','auto','important');
                this.style.setProperty('float','none','important');
                this.style.setProperty('clear','none','important');
                this.style.setProperty('transform','none','important');
                this.style.setProperty('width','100%','important');
                this.style.setProperty('max-width','none','important');
                this.style.setProperty('margin','0','important');
                this.style.setProperty('align-self','stretch','important');
            });
        }

        function hardenLayout(){
            normalizeFieldRows();
            normalizePayments();
        }

        $(document).on('change', '.rkzn-payment-inner input[name="payment_method"]', function(){
            window.setTimeout(normalizePayments, 20);
        });
        $(document.body).on('updated_checkout', function(){
            window.setTimeout(hardenLayout, 20);
            window.setTimeout(hardenLayout, 180);
        });
        $(window).on('resize', normalizePayments);
        $(function(){
            hardenLayout();
            window.setTimeout(hardenLayout, 200);
        });
    })(jQuery);
    </script>
    <?php
}, 10000);

/**
 * v11 final layout corrections:
 * - hard reset payment cards into a real two-column flex row
 * - dedicated summary column with reliable fixed/contained sticky behavior
 */
add_action('wp_head', function () {
    if (!rkzn_is_checkout_page()) {
        return;
    }
    ?>
    <style id="rkzn-checkout-v11-final-css">
    @media (min-width: 921px) {
        body.rkzn-checkout-page .rkzn-layout {
            display: grid !important;
            grid-template-columns: 320px minmax(0, 1fr) !important;
            grid-template-rows: auto !important;
            align-items: stretch !important;
            overflow: visible !important;
            position: relative !important;
        }
        body.rkzn-checkout-page .rkzn-summary-column {
            grid-column: 1 !important;
            grid-row: 1 !important;
            min-width: 0 !important;
            width: 100% !important;
            height: 100% !important;
            position: relative !important;
            align-self: stretch !important;
            overflow: visible !important;
            direction: rtl !important;
        }
        body.rkzn-checkout-page .rkzn-main {
            grid-column: 2 !important;
            grid-row: 1 !important;
            min-width: 0 !important;
        }
        body.rkzn-checkout-page .rkzn-summary-column > .rkzn-summary {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            right: auto !important;
            bottom: auto !important;
            transform: none !important;
        }
    }

    body.rkzn-checkout-page .rkzn-payment-inner #payment,
    body.rkzn-checkout-page .rkzn-payment-inner #payment .form-row,
    body.rkzn-checkout-page .rkzn-payment-inner #payment .wc_payment_methods {
        width: 100% !important;
        max-width: none !important;
    }

    body.rkzn-checkout-page .rkzn-payment-inner #payment ul.payment_methods,
    body.rkzn-checkout-page .rkzn-payment-inner #payment ul.wc_payment_methods {
        display: flex !important;
        flex-flow: row wrap !important;
        align-items: stretch !important;
        justify-content: flex-start !important;
        gap: 12px !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        list-style: none !important;
        direction: rtl !important;
    }

    body.rkzn-checkout-page .rkzn-payment-inner #payment ul.payment_methods > li,
    body.rkzn-checkout-page .rkzn-payment-inner #payment ul.wc_payment_methods > li,
    body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method {
        position: relative !important;
        inset: auto !important;
        top: auto !important;
        right: auto !important;
        bottom: auto !important;
        left: auto !important;
        float: none !important;
        clear: none !important;
        order: 0 !important;
        grid-column: auto !important;
        grid-row: auto !important;
        transform: none !important;
        translate: none !important;
        flex: 0 0 calc(50% - 6px) !important;
        width: calc(50% - 6px) !important;
        max-width: calc(50% - 6px) !important;
        min-width: 0 !important;
        height: auto !important;
        min-height: 108px !important;
        align-self: stretch !important;
        margin: 0 !important;
        padding: 12px 13px !important;
        box-sizing: border-box !important;
        vertical-align: top !important;
        overflow: hidden !important;
        display: grid !important;
        grid-template-columns: 18px minmax(0, 1fr) !important;
        grid-template-rows: auto 1fr !important;
        align-content: start !important;
        align-items: center !important;
        column-gap: 9px !important;
        row-gap: 0 !important;
    }

    body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method > input[type="radio"] {
        position: static !important;
        inset: auto !important;
        grid-column: 1 !important;
        grid-row: 1 !important;
        align-self: center !important;
        margin: 0 !important;
    }

    body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method > label {
        position: static !important;
        inset: auto !important;
        grid-column: 2 !important;
        grid-row: 1 !important;
        align-self: center !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method > .payment_box {
        position: static !important;
        inset: auto !important;
        grid-column: 1 / -1 !important;
        grid-row: 2 !important;
        align-self: start !important;
        width: 100% !important;
        max-width: none !important;
        margin: 8px 0 0 !important;
        transform: none !important;
    }

    @media (max-width: 920px) {
        body.rkzn-checkout-page .rkzn-layout {
            display: flex !important;
            flex-direction: column !important;
        }
        body.rkzn-checkout-page .rkzn-main {
            order: 1 !important;
        }
        body.rkzn-checkout-page .rkzn-summary-column {
            order: 2 !important;
            width: 100% !important;
            height: auto !important;
            position: static !important;
        }
        body.rkzn-checkout-page .rkzn-summary-column > .rkzn-summary {
            position: static !important;
            width: 100% !important;
            max-width: none !important;
            left: auto !important;
            top: auto !important;
            bottom: auto !important;
        }
    }

    @media (max-width: 680px) {
        body.rkzn-checkout-page .rkzn-payment-inner #payment ul.payment_methods > li,
        body.rkzn-checkout-page .rkzn-payment-inner #payment ul.wc_payment_methods > li,
        body.rkzn-checkout-page .rkzn-payment-inner #payment li.wc_payment_method {
            flex-basis: 100% !important;
            width: 100% !important;
            max-width: 100% !important;
        }
    }
    </style>
    <?php
}, 99999);

add_action('wp_footer', function () {
    if (!rkzn_is_checkout_page()) {
        return;
    }
    ?>
    <script id="rkzn-checkout-v11-final-js">
    (function ($) {
        'use strict';

        var stickyFrame = null;

        function setImportant(el, property, value) {
            if (el && el.style) {
                el.style.setProperty(property, value, 'important');
            }
        }

        function normalizePaymentCardsV11() {
            /* Disabled in v12: payment methods are rendered through a clean mirror grid. */
            return;
        }

        function resetSummaryV11($summary) {
            var summary = $summary.get(0);
            if (!summary) return;
            setImportant(summary, 'position', window.matchMedia('(max-width:920px)').matches ? 'static' : 'sticky');
            setImportant(summary, 'top', window.matchMedia('(max-width:920px)').matches ? 'auto' : (($('#wpadminbar').outerHeight() || 0) + 18) + 'px');
            setImportant(summary, 'left', 'auto');
            setImportant(summary, 'right', 'auto');
            setImportant(summary, 'bottom', 'auto');
            setImportant(summary, 'width', '100%');
            setImportant(summary, 'max-width', '100%');
            setImportant(summary, 'margin', '0');
            setImportant(summary, 'transform', 'none');
        }

        function syncSummaryV11() {
            stickyFrame = null;

            var $wrapper = $('.rkzn-summary-column').first();
            var $summary = $wrapper.children('.rkzn-summary').first();
            if (!$wrapper.length || !$summary.length) return;

            if (window.matchMedia('(max-width:920px)').matches) {
                resetSummaryV11($summary);
                return;
            }

            var wrapper = $wrapper.get(0);
            var summary = $summary.get(0);
            var wrapperRect = wrapper.getBoundingClientRect();
            var wrapperTop = window.pageYOffset + wrapperRect.top;
            var wrapperHeight = Math.max($wrapper.outerHeight(), $('.rkzn-main').outerHeight());
            var wrapperBottom = wrapperTop + wrapperHeight;
            var summaryHeight = $summary.outerHeight(true);
            var adminHeight = $('#wpadminbar').outerHeight() || 0;
            var offset = adminHeight + 18;
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;

            if (wrapperHeight <= summaryHeight + 4 || scrollTop + offset <= wrapperTop) {
                resetSummaryV11($summary);
                return;
            }

            if (scrollTop + offset + summaryHeight >= wrapperBottom) {
                setImportant(summary, 'position', 'absolute');
                setImportant(summary, 'top', Math.max(0, wrapperHeight - summaryHeight) + 'px');
                setImportant(summary, 'left', '0');
                setImportant(summary, 'right', 'auto');
                setImportant(summary, 'bottom', 'auto');
                setImportant(summary, 'width', '100%');
                setImportant(summary, 'max-width', '100%');
                setImportant(summary, 'margin', '0');
                return;
            }

            wrapperRect = wrapper.getBoundingClientRect();
            setImportant(summary, 'position', 'fixed');
            setImportant(summary, 'top', offset + 'px');
            setImportant(summary, 'left', wrapperRect.left + 'px');
            setImportant(summary, 'right', 'auto');
            setImportant(summary, 'bottom', 'auto');
            setImportant(summary, 'width', wrapperRect.width + 'px');
            setImportant(summary, 'max-width', wrapperRect.width + 'px');
            setImportant(summary, 'margin', '0');
        }

        function requestSummarySyncV11() {
            if (stickyFrame !== null) return;
            stickyFrame = window.requestAnimationFrame(syncSummaryV11);
        }

        function refreshV11() {
            normalizePaymentCardsV11();
            requestSummarySyncV11();
        }

        $(document).on('change', '.rkzn-payment-inner input[name="payment_method"]', function () {
            window.setTimeout(refreshV11, 10);
            window.setTimeout(refreshV11, 120);
        });

        $(document.body).on('updated_checkout', function () {
            window.setTimeout(refreshV11, 20);
            window.setTimeout(refreshV11, 200);
        });

        $(window).on('scroll resize orientationchange', requestSummarySyncV11);

        $(function () {
            refreshV11();
            window.setTimeout(refreshV11, 250);
            window.setTimeout(refreshV11, 800);
        });
    })(jQuery);
    </script>
    <?php
}, 99999);


/**
 * v12 visual finish:
 * - render payment methods through a clean two-column mirror grid
 * - keep the real WooCommerce radios in the form for gateway compatibility
 * - unify the main checkout card and order-summary card border/shadow
 */
add_action('wp_head', function () {
    if (!rkzn_is_checkout_page()) {
        return;
    }
    ?>
    <style id="rkzn-checkout-v12-final-css">
    body.rkzn-checkout-page {
        --rtsc-card-border: #e3dbe8;
        --rtsc-card-radius: 18px;
        --rtsc-card-shadow: 0 12px 34px rgba(53, 16, 82, .07);
    }

    /* The two principal checkout cards now use exactly the same visual shell. */
    body.rkzn-checkout-page .rkzn-main,
    body.rkzn-checkout-page .rkzn-summary {
        background: #fff !important;
        border: 1px solid var(--rtsc-card-border) !important;
        border-radius: var(--rtsc-card-radius) !important;
        box-shadow: var(--rtsc-card-shadow) !important;
        box-sizing: border-box !important;
    }

    body.rkzn-checkout-page .rkzn-main {
        overflow: hidden !important;
    }

    /* Hide only the original gateway list visually. Its checked radios remain in the form. */
    body.rkzn-checkout-page .rkzn-payment-inner #payment ul.rtsc-original-payment-methods {
        display: none !important;
        visibility: hidden !important;
        width: 0 !important;
        height: 0 !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        pointer-events: none !important;
    }

    body.rkzn-checkout-page .rtsc-payment-grid {
        width: 100% !important;
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        align-items: stretch !important;
        gap: 12px !important;
        margin: 0 !important;
        padding: 0 !important;
        direction: rtl !important;
    }

    body.rkzn-checkout-page .rtsc-payment-card {
        appearance: none !important;
        -webkit-appearance: none !important;
        width: 100% !important;
        min-width: 0 !important;
        min-height: 94px !important;
        margin: 0 !important;
        padding: 14px 15px !important;
        display: grid !important;
        grid-template-columns: 20px minmax(0, 1fr) !important;
        column-gap: 10px !important;
        align-items: start !important;
        align-self: stretch !important;
        text-align: right !important;
        color: #351052 !important;
        background: #fff !important;
        border: 1px solid #e5dbe9 !important;
        border-radius: 14px !important;
        box-shadow: 0 5px 18px rgba(53, 16, 82, .045) !important;
        cursor: pointer !important;
        box-sizing: border-box !important;
        transition: border-color .18s ease, background .18s ease, box-shadow .18s ease !important;
    }

    body.rkzn-checkout-page .rtsc-payment-card:hover {
        border-color: #c9a5cf !important;
        box-shadow: 0 7px 22px rgba(53, 16, 82, .075) !important;
    }

    body.rkzn-checkout-page .rtsc-payment-card.is-selected {
        border-color: var(--rkzn-purple) !important;
        background: #fcf8fd !important;
        box-shadow: 0 7px 22px rgba(150, 52, 154, .11) !important;
    }

    body.rkzn-checkout-page .rtsc-payment-radio {
        width: 17px !important;
        height: 17px !important;
        margin-top: 2px !important;
        border: 1.5px solid #9b91a1 !important;
        border-radius: 50% !important;
        background: #fff !important;
        box-shadow: inset 0 0 0 4px #fff !important;
        box-sizing: border-box !important;
    }

    body.rkzn-checkout-page .rtsc-payment-card.is-selected .rtsc-payment-radio {
        border-color: var(--rkzn-purple) !important;
        background: var(--rkzn-purple) !important;
    }

    body.rkzn-checkout-page .rtsc-payment-content {
        min-width: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
    }

    body.rkzn-checkout-page .rtsc-payment-label {
        min-width: 0 !important;
        min-height: 32px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        font-size: 13px !important;
        font-weight: 850 !important;
        line-height: 1.8 !important;
        color: #351052 !important;
    }

    body.rkzn-checkout-page .rtsc-payment-label img {
        display: block !important;
        float: none !important;
        flex: 0 0 auto !important;
        width: auto !important;
        height: auto !important;
        max-width: 110px !important;
        max-height: 34px !important;
        margin: 0 auto 0 0 !important;
        object-fit: contain !important;
    }

    body.rkzn-checkout-page .rtsc-payment-description {
        width: 100% !important;
        margin: 0 !important;
        padding: 8px 10px !important;
        border: 0 !important;
        border-radius: 9px !important;
        background: #f7f3f8 !important;
        color: #666 !important;
        font-size: 10px !important;
        line-height: 1.9 !important;
        box-sizing: border-box !important;
    }

    body.rkzn-checkout-page .rtsc-payment-description:empty,
    body.rkzn-checkout-page .rtsc-payment-card:not(.is-selected) .rtsc-payment-description {
        display: none !important;
    }

    body.rkzn-checkout-page .rtsc-payment-description p {
        margin: 0 !important;
    }

    @media (max-width: 760px) {
        body.rkzn-checkout-page .rtsc-payment-grid {
            grid-template-columns: 1fr !important;
        }
        body.rkzn-checkout-page .rtsc-payment-card {
            min-height: 82px !important;
        }
    }
    </style>
    <?php
}, PHP_INT_MAX);

add_action('wp_footer', function () {
    if (!rkzn_is_checkout_page()) {
        return;
    }
    ?>
    <script id="rkzn-checkout-v12-payment-grid-js">
    (function ($) {
        'use strict';

        var rebuildTimer = null;

        function gatewayList() {
            return $('.rkzn-payment-inner #payment ul.payment_methods, .rkzn-payment-inner #payment ul.wc_payment_methods').first();
        }

        function cleanClone($source) {
            var $clone = $source.clone(false, false);
            $clone.find('[id]').removeAttr('id');
            $clone.find('[for]').removeAttr('for');
            $clone.find('input, button, script, style').remove();
            $clone.removeAttr('style');
            $clone.find('[style]').removeAttr('style');
            return $clone;
        }

        function buildPaymentGridV12() {
            var $list = gatewayList();
            if (!$list.length) return;

            var $payment = $list.closest('#payment');
            if (!$payment.length) return;

            $payment.children('.rtsc-payment-grid').remove();
            $list.addClass('rtsc-original-payment-methods');

            var $grid = $('<div class="rtsc-payment-grid" role="radiogroup" aria-label="روش پرداخت"></div>');

            $list.children('li').each(function () {
                var $item  = $(this);
                var $input = $item.find('input[name="payment_method"]').first();
                if (!$input.length) return;

                var inputId = $input.attr('id') || '';
                var $label = inputId ? $item.find('label[for="' + inputId.replace(/([:.\[\],=@])/g, '\\$1') + '"]').first() : $item.children('label').first();
                if (!$label.length) $label = $item.find('label').first();

                var $box = $item.children('.payment_box').first();
                var checked = $input.is(':checked');
                var method = String($input.val() || '');

                var $card = $('<button type="button" class="rtsc-payment-card"></button>')
                    .attr('data-payment-method', method)
                    .attr('role', 'radio')
                    .attr('aria-checked', checked ? 'true' : 'false')
                    .toggleClass('is-selected', checked);

                $card.append('<span class="rtsc-payment-radio" aria-hidden="true"></span>');

                var $content = $('<span class="rtsc-payment-content"></span>');
                var $labelView = $('<span class="rtsc-payment-label"></span>');
                if ($label.length) {
                    $labelView.append(cleanClone($label).contents());
                } else {
                    $labelView.text(method);
                }
                $content.append($labelView);

                if ($box.length) {
                    var $description = $('<span class="rtsc-payment-description"></span>');
                    $description.append(cleanClone($box).contents());
                    $content.append($description);
                }

                $card.append($content);

                $card.on('click', function () {
                    if (!$input.is(':checked')) {
                        $input.prop('checked', true).trigger('click').trigger('change');
                    } else {
                        $input.trigger('change');
                    }
                    schedulePaymentGridV12(20);
                    schedulePaymentGridV12(160);
                });

                $grid.append($card);
            });

            $grid.insertBefore($list);

            var list = $list.get(0);
            if (list && list.style) {
                list.style.setProperty('display', 'none', 'important');
                list.style.setProperty('visibility', 'hidden', 'important');
            }
        }

        function schedulePaymentGridV12(delay) {
            window.clearTimeout(rebuildTimer);
            rebuildTimer = window.setTimeout(buildPaymentGridV12, delay || 0);
        }

        $(document).on('change', '.rkzn-payment-inner input[name="payment_method"]', function () {
            schedulePaymentGridV12(10);
            window.setTimeout(buildPaymentGridV12, 140);
        });

        $(document.body).on('updated_checkout', function () {
            schedulePaymentGridV12(25);
            window.setTimeout(buildPaymentGridV12, 220);
            window.setTimeout(buildPaymentGridV12, 650);
        });

        $(function () {
            buildPaymentGridV12();
            window.setTimeout(buildPaymentGridV12, 250);
            window.setTimeout(buildPaymentGridV12, 900);
        });
    })(jQuery);
    </script>
    <?php
}, PHP_INT_MAX);

