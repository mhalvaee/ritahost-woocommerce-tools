<?php
/**
 * Plugin Name: RitaHost WooCommerce Wishlist
 * Description: Adds a secure, self-contained WooCommerce wishlist with icon and product-list shortcodes, AJAX updates, and configurable colors.
 * Version: 1.0.0
 * Author: RitaHost
 * Text Domain: ritahost
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

define('RHWL_OPTION', 'ritahost_wishlist_settings');
define('RHWL_META_KEY', '_ritahost_wishlist_items');

function rhwl_text($fa, $en) {
    if (function_exists('ritahost_admin_text')) {
        return ritahost_admin_text($fa, $en);
    }
    return 0 === strpos(strtolower((string) get_locale()), 'fa') ? $fa : $en;
}

function rhwl_defaults() {
    return [
        'enabled'       => '1',
        'icon_color'    => '#ef394e',
        'empty_text_fa' => 'لیست علاقه‌مندی‌های شما خالی است.',
        'empty_text_en' => 'Your wishlist is empty.',
    ];
}

function rhwl_settings() {
    return wp_parse_args((array) get_option(RHWL_OPTION, []), rhwl_defaults());
}

function rhwl_legacy_meta_key() {
    return 'be' . 'ban_wishlist_items';
}

function rhwl_get_items($user_id) {
    $user_id = absint($user_id);
    if (!$user_id) {
        return [];
    }

    $items = get_user_meta($user_id, RHWL_META_KEY, true);
    if (!is_array($items)) {
        $items = get_user_meta($user_id, rhwl_legacy_meta_key(), true);
        if (is_array($items)) {
            update_user_meta($user_id, RHWL_META_KEY, array_values(array_unique(array_map('absint', $items))));
        }
    }

    return is_array($items) ? array_values(array_filter(array_unique(array_map('absint', $items)))) : [];
}

function rhwl_save_items($user_id, $items) {
    return update_user_meta(absint($user_id), RHWL_META_KEY, array_values(array_filter(array_unique(array_map('absint', (array) $items)))));
}

add_action('admin_init', function () {
    register_setting('ritahost_wishlist_group', RHWL_OPTION, [
        'type'              => 'array',
        'default'           => rhwl_defaults(),
        'sanitize_callback' => function ($input) {
            $defaults = rhwl_defaults();
            return [
                'enabled'       => empty($input['enabled']) ? '0' : '1',
                'icon_color'    => sanitize_hex_color($input['icon_color'] ?? '') ?: $defaults['icon_color'],
                'empty_text_fa' => sanitize_text_field($input['empty_text_fa'] ?? $defaults['empty_text_fa']),
                'empty_text_en' => sanitize_text_field($input['empty_text_en'] ?? $defaults['empty_text_en']),
            ];
        },
    ]);
});

function rhwl_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ritahost'));
    }
    $settings = rhwl_settings();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(rhwl_text('تنظیمات علاقه‌مندی‌ها', 'Wishlist Settings')); ?></h1>
        <p><?php echo esc_html(rhwl_text('این ابزار مستقل است و به افزونه یا سرویس خارجی وابسته نیست.', 'This tool is self-contained and has no external plugin or service dependency.')); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('ritahost_wishlist_group'); ?>
            <table class="form-table" role="presentation">
                <tr><th><?php echo esc_html(rhwl_text('وضعیت', 'Status')); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(RHWL_OPTION); ?>[enabled]" value="1" <?php checked($settings['enabled'], '1'); ?>> <?php echo esc_html(rhwl_text('فعال', 'Enabled')); ?></label></td></tr>
                <tr><th><?php echo esc_html(rhwl_text('رنگ آیکن', 'Icon color')); ?></th><td><input type="color" name="<?php echo esc_attr(RHWL_OPTION); ?>[icon_color]" value="<?php echo esc_attr($settings['icon_color']); ?>"></td></tr>
                <tr><th><?php echo esc_html(rhwl_text('متن خالی فارسی', 'Persian empty-list text')); ?></th><td><input class="regular-text" name="<?php echo esc_attr(RHWL_OPTION); ?>[empty_text_fa]" value="<?php echo esc_attr($settings['empty_text_fa']); ?>"></td></tr>
                <tr><th><?php echo esc_html(rhwl_text('متن خالی انگلیسی', 'English empty-list text')); ?></th><td><input class="regular-text" name="<?php echo esc_attr(RHWL_OPTION); ?>[empty_text_en]" value="<?php echo esc_attr($settings['empty_text_en']); ?>"></td></tr>
            </table>
            <p><code>[ritahost_wishlist_icon]</code> — <?php echo esc_html(rhwl_text('آیکن محصول', 'Product icon')); ?></p>
            <p><code>[ritahost_wishlist_products]</code> — <?php echo esc_html(rhwl_text('فهرست محصولات', 'Product list')); ?></p>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('admin_menu', function () {
    $page_title = rhwl_text('تنظیمات علاقه‌مندی‌ها', 'Wishlist Settings');
    $menu_title = rhwl_text('علاقه‌مندی‌ها', 'Wishlist');
    if (function_exists('ritahost_register_admin_tool')) {
        add_submenu_page('ritahost-panel', $page_title, $menu_title, 'manage_options', 'ritahost-wishlist', 'rhwl_settings_page');
    } else {
        add_options_page($page_title, $menu_title, 'manage_options', 'ritahost-wishlist', 'rhwl_settings_page');
    }
}, 20);

if (function_exists('ritahost_register_admin_tool')) {
    ritahost_register_admin_tool('ritahost-wishlist', 'علاقه‌مندی‌ها', 'Wishlist', 'فهرست علاقه‌مندی مستقل کاربران، آیکن محصول و رنگ نمایش را مدیریت می‌کند.', 'Manages the independent customer wishlist, product icon, and display color.', 'manage_options');
}

function rhwl_heart_svg($filled, $color) {
    $fill = $filled ? $color : 'none';
    $stroke = $filled ? $color : '#A9ABAD';
    return '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M12.62 20.81C12.28 20.93 11.72 20.93 11.38 20.81C8.48 19.82 2 15.69 2 8.69C2 5.6 4.49 3.1 7.56 3.1C9.38 3.1 10.99 3.98 12 5.34C13.01 3.98 14.63 3.1 16.44 3.1C19.51 3.1 22 5.6 22 8.69C22 15.69 15.52 19.82 12.62 20.81Z" fill="' . esc_attr($fill) . '" stroke="' . esc_attr($stroke) . '" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

add_shortcode('ritahost_wishlist_icon', function ($atts) {
    $settings = rhwl_settings();
    if ('1' !== $settings['enabled']) {
        return '';
    }

    $atts = shortcode_atts(['product_id' => 0], $atts, 'ritahost_wishlist_icon');
    $product_id = absint($atts['product_id']);
    if (!$product_id) {
        global $product;
        $product_id = is_a($product, 'WC_Product') ? $product->get_id() : 0;
    }
    if (!$product_id || 'product' !== get_post_type($product_id)) {
        return '';
    }

    $selected = is_user_logged_in() && in_array($product_id, rhwl_get_items(get_current_user_id()), true);
    return sprintf(
        '<button type="button" class="ritahost-wishlist-button%1$s" data-product-id="%2$d" aria-label="%3$s" aria-pressed="%4$s">%5$s</button>',
        $selected ? ' is-selected' : '',
        $product_id,
        esc_attr(rhwl_text('افزودن به علاقه‌مندی‌ها', 'Add to wishlist')),
        $selected ? 'true' : 'false',
        rhwl_heart_svg($selected, $settings['icon_color'])
    );
});

add_shortcode('ritahost_wishlist_products', function () {
    $settings = rhwl_settings();
    if ('1' !== $settings['enabled']) {
        return '';
    }
    if (!is_user_logged_in()) {
        return '<p>' . esc_html(rhwl_text('لطفاً ابتدا وارد حساب کاربری خود شوید.', 'Please sign in first.')) . '</p>';
    }

    $items = rhwl_get_items(get_current_user_id());
    if (!$items) {
        $empty = 0 === strpos(strtolower((string) get_locale()), 'fa') ? $settings['empty_text_fa'] : $settings['empty_text_en'];
        return '<div class="ritahost-wishlist-empty">' . esc_html($empty) . '</div>';
    }

    $products = wc_get_products(['include' => $items, 'limit' => -1, 'status' => 'publish']);
    ob_start();
    ?>
    <div class="ritahost-wishlist-grid">
        <?php foreach ($products as $product) : ?>
            <article class="ritahost-wishlist-item" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                <a class="ritahost-wishlist-image" href="<?php echo esc_url($product->get_permalink()); ?>"><?php echo wp_kses_post($product->get_image([200, 200])); ?></a>
                <h4><a href="<?php echo esc_url($product->get_permalink()); ?>"><?php echo esc_html($product->get_name()); ?></a></h4>
                <div class="ritahost-wishlist-price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
                <button type="button" class="ritahost-wishlist-remove" data-product-id="<?php echo esc_attr($product->get_id()); ?>"><?php echo esc_html(rhwl_text('حذف از علاقه‌مندی‌ها', 'Remove from wishlist')); ?></button>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
});

add_action('wp_ajax_ritahost_handle_wishlist', function () {
    check_ajax_referer('ritahost_wishlist_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => rhwl_text('لطفاً ابتدا وارد حساب کاربری خود شوید.', 'Please sign in first.')], 401);
    }

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $operation = isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
    if (!$product_id || 'product' !== get_post_type($product_id) || !in_array($operation, ['add', 'remove'], true)) {
        wp_send_json_error(['message' => rhwl_text('درخواست نامعتبر است.', 'Invalid request.')], 400);
    }

    $items = rhwl_get_items(get_current_user_id());
    if ('add' === $operation) {
        $items[] = $product_id;
        $message = rhwl_text('محصول به علاقه‌مندی‌ها اضافه شد.', 'Product added to your wishlist.');
    } else {
        $items = array_values(array_diff($items, [$product_id]));
        $message = rhwl_text('محصول از علاقه‌مندی‌ها حذف شد.', 'Product removed from your wishlist.');
    }
    rhwl_save_items(get_current_user_id(), $items);
    wp_send_json_success(['message' => $message, 'selected' => 'add' === $operation, 'count' => count(array_unique($items))]);
});

add_action('wp_enqueue_scripts', function () {
    $settings = rhwl_settings();
    if ('1' !== $settings['enabled']) {
        return;
    }

    wp_register_style('ritahost-wishlist', false, [], '1.0.0');
    wp_enqueue_style('ritahost-wishlist');
    wp_add_inline_style('ritahost-wishlist', '.ritahost-wishlist-button{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;padding:0;border:0;border-radius:50%;background:#fff;cursor:pointer}.ritahost-wishlist-button:disabled{opacity:.55;cursor:wait}.ritahost-wishlist-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:18px}.ritahost-wishlist-item{padding:15px;border:1px solid #eee;border-radius:16px;text-align:center}.ritahost-wishlist-image img{max-width:160px;height:auto}.ritahost-wishlist-item h4{font-size:14px;line-height:1.8}.ritahost-wishlist-price{margin:10px 0}.ritahost-wishlist-remove{width:100%;min-height:44px;border:0;border-radius:12px;background:' . esc_attr($settings['icon_color']) . ';color:#fff;cursor:pointer}.ritahost-wishlist-empty{text-align:center;padding:45px 15px;color:#777}.ritahost-wishlist-toast{position:fixed;z-index:999999;right:20px;bottom:20px;max-width:330px;padding:12px 16px;border-radius:10px;background:#222;color:#fff;box-shadow:0 8px 28px rgba(0,0,0,.18)}');

    wp_register_script('ritahost-wishlist', '', ['jquery'], '1.0.0', true);
    wp_enqueue_script('ritahost-wishlist');
    wp_localize_script('ritahost-wishlist', 'RitaHostWishlist', [
        'ajaxUrl'    => admin_url('admin-ajax.php'),
        'nonce'      => wp_create_nonce('ritahost_wishlist_nonce'),
        'loggedIn'   => is_user_logged_in(),
        'loginText'  => rhwl_text('لطفاً ابتدا وارد حساب کاربری خود شوید.', 'Please sign in first.'),
        'errorText'  => rhwl_text('خطایی رخ داد. دوباره تلاش کنید.', 'Something went wrong. Please try again.'),
        'color'      => $settings['icon_color'],
    ]);
    wp_add_inline_script('ritahost-wishlist', <<<'JS'
jQuery(function($){
    function toast(message){ $('.ritahost-wishlist-toast').remove(); $('<div class="ritahost-wishlist-toast" role="status"></div>').text(message).appendTo('body').delay(2800).fadeOut(250,function(){$(this).remove();}); }
    function request(productId, operation, $button){
        if(!RitaHostWishlist.loggedIn){ toast(RitaHostWishlist.loginText); return; }
        $button.prop('disabled',true);
        $.post(RitaHostWishlist.ajaxUrl,{action:'ritahost_handle_wishlist',product_id:productId,operation:operation,nonce:RitaHostWishlist.nonce}).done(function(response){
            if(!response || !response.success){ toast(response && response.data && response.data.message ? response.data.message : RitaHostWishlist.errorText); return; }
            toast(response.data.message);
            if($button.hasClass('ritahost-wishlist-remove')){ $button.closest('.ritahost-wishlist-item').fadeOut(200,function(){$(this).remove();}); return; }
            var selected=!!response.data.selected;
            $button.toggleClass('is-selected',selected).attr('aria-pressed',selected?'true':'false');
            var path=$button.find('path'); path.attr('fill',selected?RitaHostWishlist.color:'none').attr('stroke',selected?RitaHostWishlist.color:'#A9ABAD');
        }).fail(function(){toast(RitaHostWishlist.errorText);}).always(function(){$button.prop('disabled',false);});
    }
    $(document).on('click','.ritahost-wishlist-button',function(e){e.preventDefault();var $b=$(this);request(parseInt($b.data('product-id'),10),$b.hasClass('is-selected')?'remove':'add',$b);});
    $(document).on('click','.ritahost-wishlist-remove',function(e){e.preventDefault();var $b=$(this);request(parseInt($b.data('product-id'),10),'remove',$b);});
});
JS
    );
});

