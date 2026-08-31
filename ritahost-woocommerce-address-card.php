<?php
/**
 * Plugin Name: RitaHost WooCommerce Address Card
 * Description: Displays WooCommerce My Account addresses as responsive cards without an add-address button.
 * Version: 1.0.0
 * Author: RitaHost
 * Text Domain: ritahost
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

if (function_exists('ritahost_register_admin_tool')) {
    ritahost_register_admin_tool('ritahost-address-card', 'کارت آدرس حساب کاربری', 'Account Address Card', 'آدرس‌های صورتحساب و ارسال ووکامرس را به شکل کارت واکنش‌گرا نمایش می‌دهد.', 'Displays WooCommerce billing and shipping addresses as responsive cards.', 'manage_options');
}

/* ============================
   Rubikala - My Address Card Style
   بدون دکمه افزودن آدرس
   ============================ */

add_action('wp', function () {

    if ( ! function_exists('is_account_page') || ! is_account_page() ) {
        return;
    }

    if ( ! function_exists('woocommerce_account_edit_address') ) {
        return;
    }

    remove_action('woocommerce_account_edit-address_endpoint', 'woocommerce_account_edit_address');
    add_action('woocommerce_account_edit-address_endpoint', 'rk_rubikala_address_card_only', 10, 1);

}, 99);


if ( ! function_exists('rk_rubikala_address_card_only') ) {

    function rk_rubikala_address_card_only($type = '') {

        /*
         * وقتی کاربر وارد فرم ویرایش billing یا shipping شد،
         * فرم اصلی ووکامرس نمایش داده شود.
         */
        if ($type === 'billing' || $type === 'shipping') {
            woocommerce_account_edit_address($type);
            return;
        }

        if ( ! is_user_logged_in() || ! class_exists('WC_Customer') ) {
            return;
        }

        $user_id  = get_current_user_id();
        $customer = new WC_Customer($user_id);

        $edit_url = wc_get_endpoint_url(
            'edit-address',
            'billing',
            wc_get_page_permalink('myaccount')
        );

        $first_name = $customer->get_billing_first_name();
        $last_name  = $customer->get_billing_last_name();
        $phone      = $customer->get_billing_phone();
        $state      = $customer->get_billing_state();
        $city       = $customer->get_billing_city();
        $address_1  = $customer->get_billing_address_1();
        $address_2  = $customer->get_billing_address_2();
        $postcode   = $customer->get_billing_postcode();

        $name = trim($first_name . ' ' . $last_name);

        if (empty($name)) {
            $user = wp_get_current_user();
            $name = $user ? $user->display_name : '';
        }

        $full_address = trim($address_1 . ' ' . $address_2);

        $has_address = ! empty($name)
            || ! empty($phone)
            || ! empty($state)
            || ! empty($city)
            || ! empty($full_address)
            || ! empty($postcode);

        ?>

        <style>
          .rk-address-page{
            direction:rtl;
            width:100%;
            padding:0;
          }

          .rk-address-title{
            display:flex;
            align-items:center;
            justify-content:flex-start;
            gap:8px;
            margin:0 0 18px;
            padding:0 0 14px;
            border-bottom:1px solid rgba(0,0,0,.12);
          }

          .rk-address-title .rk-location-icon{
            color:#888;
            font-size:22px;
            line-height:1;
          }

          .rk-address-title h2{
            margin:0 !important;
            font-size:18px !important;
            font-weight:900 !important;
            color:#111 !important;
            line-height:1.6 !important;
          }

          .rk-address-card{
            position:relative;
            width:100%;
            max-width:440px;
            min-height:176px;
            background:#fff;
            border:1px solid rgba(0,0,0,.15);
            border-radius:6px;
            box-shadow:0 2px 4px rgba(0,0,0,.18);
            padding:22px 28px 20px;
            margin:0;
          }

          .rk-address-edit{
            position:absolute;
            top:20px;
            left:22px;
            width:26px;
            height:26px;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#333 !important;
            text-decoration:none !important;
            font-size:0 !important;
            line-height:1 !important;
            z-index:2;
          }

          .rk-address-edit::before{
            content:"✎";
            font-size:22px;
            font-weight:900;
            color:#333;
          }

          .rk-address-info{
            display:flex;
            flex-direction:column;
            gap:13px;
            padding-top:4px;
          }

          .rk-address-row{
            display:flex;
            align-items:center;
            justify-content:flex-start;
            gap:9px;
            color:#111;
            font-size:14px;
            font-weight:700;
            line-height:1.8;
            text-align:right;
          }

          .rk-address-row .rk-ico{
            width:20px;
            min-width:20px;
            color:#8c8c8c;
            font-size:18px;
            text-align:center;
            opacity:.85;
          }

          .rk-address-empty{
            font-size:14px;
            font-weight:700;
            color:#555;
            line-height:2;
            padding-left:40px;
          }

          .rk-address-empty a{
            color:#67009e !important;
            font-weight:900;
            text-decoration:none !important;
          }

          @media (max-width:768px){

            .rk-address-page{
              padding:0;
            }

            .rk-address-title{
              margin-bottom:14px;
            }

            .rk-address-title h2{
              font-size:17px !important;
            }

            .rk-address-card{
              max-width:100%;
              min-height:auto;
              padding:48px 18px 18px;
              border-radius:16px;
              box-shadow:0 3px 12px rgba(0,0,0,.08);
            }

            .rk-address-edit{
              top:16px;
              left:16px;
            }

            .rk-address-row{
              font-size:14px;
              line-height:1.9;
              align-items:flex-start;
            }

            .rk-address-row .rk-ico{
              margin-top:2px;
            }
          }
          
.rk-address-page {
  background: var(--rk-card);
  border: 1px solid var(--rk-border);
  border-radius: var(--rk-radius);
  box-shadow: var(--rk-shadow);
  padding: 20px

}
        </style>

        <div class="rk-address-page">

            <div class="rk-address-title">
                <span class="rk-location-icon">●</span>
                <h2>آدرس های من</h2>
            </div>

            <div class="rk-address-card">

                <a class="rk-address-edit" href="<?php echo esc_url($edit_url); ?>">
                    ویرایش آدرس
                </a>

                <?php if ($has_address) : ?>

                    <div class="rk-address-info">

                        <?php if (! empty($name)) : ?>
                            <div class="rk-address-row">
                                <span class="rk-ico">👤</span>
                                <span><?php echo esc_html($name); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (! empty($phone)) : ?>
                            <div class="rk-address-row">
                                <span class="rk-ico">☎</span>
                                <span dir="ltr"><?php echo esc_html($phone); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (! empty($state) || ! empty($city)) : ?>
                            <div class="rk-address-row">
                                <span class="rk-ico">●</span>
                                <span><?php echo esc_html(trim($state . '، ' . $city, '، ')); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (! empty($full_address)) : ?>
                            <div class="rk-address-row">
                                <span class="rk-ico">⌁</span>
                                <span><?php echo esc_html($full_address); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (! empty($postcode)) : ?>
                            <div class="rk-address-row">
                                <span class="rk-ico">↯</span>
                                <span>کد پستی: <?php echo esc_html($postcode); ?></span>
                            </div>
                        <?php endif; ?>

                    </div>

                <?php else : ?>

                    <div class="rk-address-empty">
                        هنوز آدرسی ثبت نشده است.
                        <br>
                        <a href="<?php echo esc_url($edit_url); ?>">ثبت آدرس</a>
                    </div>

                <?php endif; ?>

            </div>

        </div>

        <?php
    }
}

