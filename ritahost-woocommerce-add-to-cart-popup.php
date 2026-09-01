<?php
/**
 * Plugin Name: RitaHost WooCommerce Add-to-Cart Popup
 * Description: Adds products to the WooCommerce cart through a protected AJAX request and displays a configurable confirmation popup.
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

define('RHACP_OPTION', 'ritahost_add_to_cart_popup_settings');

function rhacp_text($fa, $en) {
    if (function_exists('ritahost_admin_text')) {
        return ritahost_admin_text($fa, $en);
    }
    return 0 === strpos(strtolower((string) get_locale()), 'fa') ? $fa : $en;
}

function rhacp_defaults() {
    return [
        'enabled'       => '1',
        'overlay_color' => '#000000',
        'overlay_alpha' => '50',
        'surface_color' => '#ffffff',
        'button_color'  => '#172747',
        'title_color'   => '#171615',
        'text_color'    => '#666666',
        'radius'        => '8',
    ];
}

function rhacp_settings() {
    return wp_parse_args((array) get_option(RHACP_OPTION, []), rhacp_defaults());
}

add_action('admin_init', function () {
    register_setting('ritahost_cart_popup_group', RHACP_OPTION, [
        'type'              => 'array',
        'default'           => rhacp_defaults(),
        'sanitize_callback' => function ($input) {
            $defaults = rhacp_defaults();
            return [
                'enabled'       => empty($input['enabled']) ? '0' : '1',
                'overlay_color' => sanitize_hex_color($input['overlay_color'] ?? '') ?: $defaults['overlay_color'],
                'overlay_alpha' => (string) max(0, min(90, absint($input['overlay_alpha'] ?? 50))),
                'surface_color' => sanitize_hex_color($input['surface_color'] ?? '') ?: $defaults['surface_color'],
                'button_color'  => sanitize_hex_color($input['button_color'] ?? '') ?: $defaults['button_color'],
                'title_color'   => sanitize_hex_color($input['title_color'] ?? '') ?: $defaults['title_color'],
                'text_color'    => sanitize_hex_color($input['text_color'] ?? '') ?: $defaults['text_color'],
                'radius'        => (string) max(0, min(40, absint($input['radius'] ?? 8))),
            ];
        },
    ]);
});

function rhacp_settings_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ritahost'));
    }
    $settings = rhacp_settings();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(rhacp_text('تنظیمات اعلان افزودن به سبد', 'Add-to-Cart Popup Settings')); ?></h1>
        <p><?php echo esc_html(rhacp_text('این پنجره پس از افزودن موفق محصول در صفحه محصول نمایش داده می‌شود.', 'This popup appears after a product is successfully added from a product page.')); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('ritahost_cart_popup_group'); ?>
            <table class="form-table" role="presentation">
                <tr><th><?php echo esc_html(rhacp_text('وضعیت', 'Status')); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(RHACP_OPTION); ?>[enabled]" value="1" <?php checked($settings['enabled'], '1'); ?>> <?php echo esc_html(rhacp_text('فعال', 'Enabled')); ?></label></td></tr>
                <tr><th><?php echo esc_html(rhacp_text('رنگ پس‌زمینه تیره', 'Overlay color')); ?></th><td><input type="color" name="<?php echo esc_attr(RHACP_OPTION); ?>[overlay_color]" value="<?php echo esc_attr($settings['overlay_color']); ?>"></td></tr>
                <tr><th><?php echo esc_html(rhacp_text('شفافیت پس‌زمینه', 'Overlay opacity')); ?></th><td><input type="number" min="0" max="90" name="<?php echo esc_attr(RHACP_OPTION); ?>[overlay_alpha]" value="<?php echo esc_attr($settings['overlay_alpha']); ?>"> %</td></tr>
                <tr><th><?php echo esc_html(rhacp_text('رنگ کادر', 'Popup color')); ?></th><td><input type="color" name="<?php echo esc_attr(RHACP_OPTION); ?>[surface_color]" value="<?php echo esc_attr($settings['surface_color']); ?>"></td></tr>
                <tr><th><?php echo esc_html(rhacp_text('رنگ دکمه', 'Button color')); ?></th><td><input type="color" name="<?php echo esc_attr(RHACP_OPTION); ?>[button_color]" value="<?php echo esc_attr($settings['button_color']); ?>"></td></tr>
                <tr><th><?php echo esc_html(rhacp_text('رنگ عنوان', 'Title color')); ?></th><td><input type="color" name="<?php echo esc_attr(RHACP_OPTION); ?>[title_color]" value="<?php echo esc_attr($settings['title_color']); ?>"></td></tr>
                <tr><th><?php echo esc_html(rhacp_text('رنگ نام محصول', 'Product text color')); ?></th><td><input type="color" name="<?php echo esc_attr(RHACP_OPTION); ?>[text_color]" value="<?php echo esc_attr($settings['text_color']); ?>"></td></tr>
                <tr><th><?php echo esc_html(rhacp_text('گردی گوشه‌ها', 'Corner radius')); ?></th><td><input type="number" min="0" max="40" name="<?php echo esc_attr(RHACP_OPTION); ?>[radius]" value="<?php echo esc_attr($settings['radius']); ?>"> px</td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('admin_menu', function () {
    $page_title = rhacp_text('تنظیمات اعلان افزودن به سبد', 'Add-to-Cart Popup Settings');
    $menu_title = rhacp_text('اعلان افزودن به سبد', 'Add-to-Cart Popup');
    if (function_exists('ritahost_register_admin_tool')) {
        add_submenu_page('ritahost-panel', $page_title, $menu_title, 'manage_woocommerce', 'ritahost-cart-popup', 'rhacp_settings_page');
    } else {
        add_submenu_page('woocommerce', $page_title, $menu_title, 'manage_woocommerce', 'ritahost-cart-popup', 'rhacp_settings_page');
    }
}, 20);

if (function_exists('ritahost_register_admin_tool')) {
    ritahost_register_admin_tool('ritahost-cart-popup', 'اعلان افزودن به سبد', 'Add-to-Cart Popup', 'پنجره تأیید افزودن محصول، رنگ‌ها، پس‌زمینه و گردی کادر را مدیریت می‌کند.', 'Controls the add-to-cart confirmation popup, colors, overlay, and corner radius.', 'manage_woocommerce');
}

function rhacp_ajax_add_to_cart() {
    check_ajax_referer('ritahost_add_to_cart', 'nonce');

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json_error(['message' => rhacp_text('سبد خرید در دسترس نیست.', 'The cart is unavailable.')], 503);
    }

    $product_id  = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
    $quantity = isset($_POST['quantity']) ? wc_stock_amount(wp_unslash($_POST['quantity'])) : 1;
    if (!$product_id || $quantity <= 0) {
        wp_send_json_error(['message' => rhacp_text('اطلاعات محصول نامعتبر است.', 'Invalid product data.')], 400);
    }

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
        wp_send_json_error(['message' => rhacp_text('این محصول قابل خرید نیست.', 'This product cannot be purchased.')], 400);
    }

    $variation = [];
    foreach ($_POST as $key => $value) {
        $key = sanitize_key($key);
        if (0 === strpos($key, 'attribute_') && !is_array($value)) {
            $variation[$key] = wc_clean(wp_unslash($value));
        }
    }

    if ($variation_id) {
        $variation_product = wc_get_product($variation_id);
        if (!$variation_product || !$variation_product->is_type('variation') || $variation_product->get_parent_id() !== $product_id || !$variation_product->is_purchasable() || !$variation_product->is_in_stock()) {
            wp_send_json_error(['message' => rhacp_text('تنوع انتخاب‌شده معتبر نیست.', 'The selected variation is invalid.')], 400);
        }
    }

    $passed = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation);
    if (!$passed) {
        wp_send_json_error(['message' => rhacp_text('افزودن محصول تأیید نشد.', 'The product could not be added.')], 400);
    }

    $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
    if (!$cart_item_key) {
        wp_send_json_error(['message' => rhacp_text('افزودن محصول به سبد انجام نشد.', 'The product could not be added to the cart.')], 400);
    }

    $display_product = $variation_id ? wc_get_product($variation_id) : $product;
    do_action('woocommerce_ajax_added_to_cart', $product_id);
    ob_start();
    woocommerce_mini_cart();
    $mini_cart = ob_get_clean();
    $fragments = apply_filters('woocommerce_add_to_cart_fragments', [
        'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
    ]);
    wp_send_json_success([
        'image'     => esc_url_raw(wp_get_attachment_image_url($display_product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src()),
        'title'     => $product->get_name(),
        'price'     => $display_product->get_price_html(),
        'cartCount' => WC()->cart->get_cart_contents_count(),
        'cartHash'  => WC()->cart->get_cart_hash(),
        'fragments' => $fragments,
    ]);
}
add_action('wp_ajax_ritahost_add_to_cart', 'rhacp_ajax_add_to_cart');
add_action('wp_ajax_nopriv_ritahost_add_to_cart', 'rhacp_ajax_add_to_cart');

add_action('wp_enqueue_scripts', function () {
    $settings = rhacp_settings();
    if ('1' !== $settings['enabled'] || !function_exists('is_product') || !is_product()) {
        return;
    }

    $page_product = function_exists('wc_get_product') ? wc_get_product(get_queried_object_id()) : false;
    if (!$page_product || !in_array($page_product->get_type(), ['simple', 'variable'], true)) {
        return;
    }

    $rgb = sscanf($settings['overlay_color'], '#%02x%02x%02x');
    $overlay = sprintf('rgba(%d,%d,%d,%.2f)', $rgb[0], $rgb[1], $rgb[2], ((int) $settings['overlay_alpha']) / 100);

    wp_register_style('ritahost-cart-popup', false, [], '1.0.0');
    wp_enqueue_style('ritahost-cart-popup');
    wp_add_inline_style('ritahost-cart-popup', '.ritahost-cart-popup{position:fixed;inset:0;display:none;align-items:center;justify-content:center;padding:20px;background:' . esc_attr($overlay) . ';z-index:999999}.ritahost-cart-popup.is-open{display:flex}.ritahost-cart-popup__content{position:relative;width:min(400px,100%);padding:20px;border-radius:' . absint($settings['radius']) . 'px;background:' . esc_attr($settings['surface_color']) . ';animation:ritahostCartPopupIn .2s ease}.ritahost-cart-popup__close{position:absolute;top:12px;inset-inline-end:12px;width:34px;height:34px;padding:0;border:0;background:transparent;color:' . esc_attr($settings['title_color']) . ';font-size:24px;cursor:pointer}.ritahost-cart-popup__title{margin:0 0 15px;padding-inline-end:35px;color:' . esc_attr($settings['title_color']) . ';font-size:16px}.ritahost-cart-popup__product{display:flex;align-items:center;gap:15px;padding-top:15px;border-top:1px solid #eee}.ritahost-cart-popup__image{width:80px;height:80px;object-fit:cover;border-radius:4px}.ritahost-cart-popup__name{margin:0 0 8px;color:' . esc_attr($settings['text_color']) . ';font-size:14px;font-weight:400}.ritahost-cart-popup__price{margin:0;color:' . esc_attr($settings['title_color']) . ';font-size:14px}.ritahost-cart-popup__button{display:block;margin-top:16px;padding:11px 24px;border-radius:' . absint($settings['radius']) . 'px;background:' . esc_attr($settings['button_color']) . ';color:#fff!important;text-align:center;text-decoration:none!important}@keyframes ritahostCartPopupIn{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:scale(1)}}');

    wp_register_script('ritahost-cart-popup', false, ['jquery'], '1.0.0', true);
    wp_enqueue_script('ritahost-cart-popup');
    wp_localize_script('ritahost-cart-popup', 'RitaHostCartPopup', [
        'ajaxUrl'   => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('ritahost_add_to_cart'),
        'errorText' => rhacp_text('افزودن محصول انجام نشد. دوباره تلاش کنید.', 'The product could not be added. Please try again.'),
    ]);
    wp_add_inline_script('ritahost-cart-popup', <<<'JS'
jQuery(function($){
    var $popup=$('#ritahostCartPopup');
    function closePopup(){ $popup.removeClass('is-open').attr('aria-hidden','true'); }
    $(document).on('click','.single_add_to_cart_button',function(event){
        var $button=$(this),$form=$button.closest('form.cart');
        if(!$form.length||$button.hasClass('disabled')){return;}
        event.preventDefault();
        var data={action:'ritahost_add_to_cart',nonce:RitaHostCartPopup.nonce,product_id:$form.find('[name="product_id"]').val()||$button.val(),quantity:$form.find('[name="quantity"]').val()||1,variation_id:$form.find('[name="variation_id"]').val()||0};
        $form.find('[name^="attribute_"]').each(function(){data[this.name]=$(this).val();});
        $button.addClass('loading').prop('disabled',true);
        $.post(RitaHostCartPopup.ajaxUrl,data).done(function(response){
            if(!response||!response.success){window.alert(response&&response.data&&response.data.message?response.data.message:RitaHostCartPopup.errorText);return;}
            $('#ritahostCartPopupImage').attr('src',response.data.image);
            $('#ritahostCartPopupTitle').text(response.data.title);
            $('#ritahostCartPopupPrice').html(response.data.price);
            $popup.addClass('is-open').attr('aria-hidden','false');
            $(document.body).trigger('added_to_cart',[response.data.fragments||{},response.data.cartHash,$button]);
            $('.cart-items-count').text(response.data.cartCount);
        }).fail(function(){window.alert(RitaHostCartPopup.errorText);}).always(function(){$button.removeClass('loading').prop('disabled',false);});
    });
    $(document).on('click','.ritahost-cart-popup__close',closePopup);
    $(document).on('click','#ritahostCartPopup',function(event){if(event.target===this){closePopup();}});
    $(document).on('keydown',function(event){if(event.key==='Escape'){closePopup();}});
});
JS
    );
});

add_action('wp_footer', function () {
    $settings = rhacp_settings();
    if ('1' !== $settings['enabled'] || !function_exists('is_product') || !is_product()) {
        return;
    }
    $page_product = function_exists('wc_get_product') ? wc_get_product(get_queried_object_id()) : false;
    if (!$page_product || !in_array($page_product->get_type(), ['simple', 'variable'], true)) {
        return;
    }
    ?>
    <div id="ritahostCartPopup" class="ritahost-cart-popup" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="ritahostCartPopupHeading">
        <div class="ritahost-cart-popup__content">
            <button type="button" class="ritahost-cart-popup__close" aria-label="<?php echo esc_attr(rhacp_text('بستن', 'Close')); ?>">×</button>
            <h2 id="ritahostCartPopupHeading" class="ritahost-cart-popup__title"><?php echo esc_html(rhacp_text('این کالا به سبد خرید اضافه شد', 'This item was added to your cart')); ?></h2>
            <div class="ritahost-cart-popup__product">
                <img id="ritahostCartPopupImage" class="ritahost-cart-popup__image" src="" alt="">
                <div><h3 id="ritahostCartPopupTitle" class="ritahost-cart-popup__name"></h3><p id="ritahostCartPopupPrice" class="ritahost-cart-popup__price"></p></div>
            </div>
            <a class="ritahost-cart-popup__button" href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php echo esc_html(rhacp_text('برو به سبد خرید', 'View cart')); ?></a>
        </div>
    </div>
    <?php
});

