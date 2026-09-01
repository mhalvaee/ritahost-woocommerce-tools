<?php
/**
 * Plugin Name: RitaHost Local Pickup Text
 * Description: Adds configurable pickup instructions and a map link to the WooCommerce checkout without embedding site-specific location data.
 * Version: 1.1.1
 * Author: RitaHost
 * Text Domain: ritahost
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

function ritahost_pickup_is_fa() {
    return strpos(determine_locale(), 'fa') === 0;
}

function ritahost_pickup_label($fa, $en) {
    return ritahost_pickup_is_fa() ? $fa : $en;
}

function ritahost_pickup_register_menu() {
    $parent = function_exists('ritahost_register_admin_tool') ? 'ritahost-panel' : 'woocommerce';

    if (function_exists('ritahost_register_admin_tool')) {
        ritahost_register_admin_tool('ritahost-local-pickup-text', 'تحویل حضوری', 'Local Pickup', 'نشانی و لینک نقشه تحویل حضوری را بدون اطلاعات ثابت داخل کد مدیریت می‌کند.', 'Manages pickup instructions and a map link without hard-coded location data.', 'manage_woocommerce');
    }

    add_submenu_page(
        $parent,
        ritahost_pickup_label('تنظیمات تحویل حضوری', 'Local Pickup Settings'),
        ritahost_pickup_label('تحویل حضوری', 'Local Pickup'),
        'manage_woocommerce',
        'ritahost-local-pickup-text',
        'ritahost_pickup_render_settings'
    );
}
add_action('admin_menu', 'ritahost_pickup_register_menu', 30);

function ritahost_pickup_register_settings() {
    register_setting('ritahost_pickup_settings', 'ritahost_pickup_settings', array(
        'type'              => 'array',
        'sanitize_callback' => function ($value) {
            $value = is_array($value) ? $value : array();
            return array(
                'enabled'    => empty($value['enabled']) ? 0 : 1,
                'match_text' => sanitize_text_field($value['match_text'] ?? ''),
                'address'    => sanitize_textarea_field($value['address'] ?? ''),
                'map_url'    => esc_url_raw($value['map_url'] ?? ''),
                'link_text'  => sanitize_text_field($value['link_text'] ?? ''),
            );
        },
        'default'           => array(),
    ));
}
add_action('admin_init', 'ritahost_pickup_register_settings');

function ritahost_pickup_render_settings() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    $settings = get_option('ritahost_pickup_settings', array());
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(ritahost_pickup_label('تنظیمات تحویل حضوری', 'Local Pickup Settings')); ?></h1>
        <p><?php echo esc_html(ritahost_pickup_label('نشانی و لینک نقشه فروشگاه خود را وارد کنید. هیچ نشانی‌ای به‌صورت پیش‌فرض منتشر نمی‌شود.', 'Enter your store address and map link. No location is published by default.')); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('ritahost_pickup_settings'); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><?php echo esc_html(ritahost_pickup_label('فعال‌سازی', 'Enable')); ?></th><td><label><input type="checkbox" name="ritahost_pickup_settings[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>> <?php echo esc_html(ritahost_pickup_label('نمایش توضیحات تحویل حضوری', 'Show pickup instructions')); ?></label></td></tr>
                <tr><th scope="row"><label for="ritahost-pickup-match"><?php echo esc_html(ritahost_pickup_label('عبارت شناسایی روش ارسال', 'Shipping method label')); ?></label></th><td><input class="regular-text" id="ritahost-pickup-match" name="ritahost_pickup_settings[match_text]" value="<?php echo esc_attr($settings['match_text'] ?? ritahost_pickup_label('تحویل حضوری', 'Local pickup')); ?>"></td></tr>
                <tr><th scope="row"><label for="ritahost-pickup-address"><?php echo esc_html(ritahost_pickup_label('نشانی تحویل', 'Pickup address')); ?></label></th><td><textarea class="large-text" rows="4" id="ritahost-pickup-address" name="ritahost_pickup_settings[address]"><?php echo esc_textarea($settings['address'] ?? ''); ?></textarea></td></tr>
                <tr><th scope="row"><label for="ritahost-pickup-map"><?php echo esc_html(ritahost_pickup_label('لینک نقشه', 'Map URL')); ?></label></th><td><input class="large-text" type="url" id="ritahost-pickup-map" name="ritahost_pickup_settings[map_url]" value="<?php echo esc_attr($settings['map_url'] ?? ''); ?>"></td></tr>
                <tr><th scope="row"><label for="ritahost-pickup-link-text"><?php echo esc_html(ritahost_pickup_label('متن لینک', 'Link text')); ?></label></th><td><input class="regular-text" id="ritahost-pickup-link-text" name="ritahost_pickup_settings[link_text]" value="<?php echo esc_attr($settings['link_text'] ?? ritahost_pickup_label('مشاهده مسیر روی نقشه', 'View on map')); ?>"></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('wp_footer', function(){

    if (!is_checkout()) {
        return;
    }
    $settings = get_option('ritahost_pickup_settings', array());
    if (empty($settings['enabled']) || empty($settings['address'])) {
        return;
    }
    $config = array(
        'matchText' => $settings['match_text'] ?? ritahost_pickup_label('تحویل حضوری', 'Local pickup'),
        'address'   => $settings['address'],
        'mapUrl'    => $settings['map_url'] ?? '',
        'linkText'  => $settings['link_text'] ?? ritahost_pickup_label('مشاهده مسیر روی نقشه', 'View on map'),
    );
    ?>

    <script>
    document.addEventListener('DOMContentLoaded', function(){

        const pickupConfig = <?php echo wp_json_encode($config); ?>;

        function changePickupText(){

            document.querySelectorAll('.rkzn-shipping-option, .woocommerce-shipping-methods li').forEach(function(item){

                const title = item.querySelector('strong, label');
                let desc  = item.querySelector('small, .ritahost-pickup-description');

                if(!title) return;

                if(title.innerText.includes(pickupConfig.matchText)){
                    if (!desc) {
                        desc = document.createElement('small');
                        desc.className = 'ritahost-pickup-description';
                        desc.style.display = 'block';
                        item.append(desc);
                    }
                    desc.textContent = '';
                    desc.append(document.createTextNode('📍 ' + pickupConfig.address));

                    if (pickupConfig.mapUrl) {
                        desc.append(document.createElement('br'));
                        const link = document.createElement('a');
                        link.href = pickupConfig.mapUrl;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.textContent = pickupConfig.linkText;
                        desc.append(link);
                    }

                }

            });

        }


        changePickupText();


        jQuery(document.body).on(
            'updated_checkout updated_shipping_method',
            function(){
                changePickupText();
            }
        );

    });
    </script>

    <?php
});