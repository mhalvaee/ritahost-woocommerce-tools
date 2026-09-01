<?php
/**
 * Plugin Name: RitaHost WooCommerce Order Details
 * Description: Replaces the WooCommerce My Account order view with a responsive invoice-style order details page.
 * Version: 1.1.0
 * Author: RitaHost
 * Text Domain: ritahost
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

function rkvo_text($fa, $en) {
    return strpos(determine_locale(), 'fa') === 0 ? $fa : $en;
}

if (function_exists('ritahost_register_admin_tool')) {
    ritahost_register_admin_tool('ritahost-order-details', 'جزئیات سفارش حساب کاربری', 'Account Order Details', 'صفحه مشاهده سفارش ووکامرس را به صورت فاکتور واکنش‌گرا نمایش می‌دهد.', 'Renders the WooCommerce account order view as a responsive invoice.', 'manage_options');
}

/**
 * Replace default WooCommerce view-order endpoint output.
 */
add_action('wp', function () {

    if ( ! function_exists('is_account_page') || ! is_account_page() ) {
        return;
    }

    if ( ! function_exists('woocommerce_account_view_order') ) {
        return;
    }

    remove_action('woocommerce_account_view-order_endpoint', 'woocommerce_account_view_order');
    add_action('woocommerce_account_view-order_endpoint', 'rkvo_render_invoice_view_order', 10, 1);

}, 99);


if ( ! function_exists('rkvo_render_invoice_view_order') ) {

    function rkvo_render_invoice_view_order($order_id) {

        if ( ! function_exists('wc_get_order') ) {
            return;
        }

        $order_id = absint($order_id);
        $order    = wc_get_order($order_id);

        if ( ! $order ) {
            wc_print_notice(rkvo_text('سفارش مورد نظر پیدا نشد.', 'The requested order was not found.'), 'error');
            return;
        }

        $current_user_id = get_current_user_id();

        if (
            (int) $order->get_customer_id() !== (int) $current_user_id
            && ! current_user_can('manage_woocommerce')
        ) {
            wc_print_notice(rkvo_text('شما اجازه مشاهده این سفارش را ندارید.', 'You are not allowed to view this order.'), 'error');
            return;
        }

        $orders_url     = wc_get_endpoint_url('orders', '', wc_get_page_permalink('myaccount'));
        $order_number   = $order->get_order_number();
        $order_status   = $order->get_status();
        $status_name    = wc_get_order_status_name($order_status);
        $payment_title  = $order->get_payment_method_title();
        $currency       = $order->get_currency();

        $date_created = $order->get_date_created();
        $order_date   = $date_created ? wc_format_datetime($date_created, 'j F Y') : '—';
        $order_time   = $date_created ? wc_format_datetime($date_created, 'H:i') : '';

        $tracking_code = rkvo_get_order_tracking_code($order);
        $progress_level = rkvo_get_order_progress_level($order_status);

        $billing_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $billing_phone = $order->get_billing_phone();
        $billing_email = $order->get_billing_email();
        $billing_state = $order->get_billing_state();
        $billing_city = $order->get_billing_city();
        $billing_address_1 = $order->get_billing_address_1();
        $billing_address_2 = $order->get_billing_address_2();
        $billing_postcode = $order->get_billing_postcode();

        $billing_full_address = trim($billing_address_1 . ' ' . $billing_address_2);
        $edit_address_url = wc_get_endpoint_url('edit-address', 'billing', wc_get_page_permalink('myaccount'));

        $available_actions = wc_get_account_orders_actions($order);

        ?>
        <style>
            :root{
                --rkvo-purple:#67009e;
                --rkvo-purple-dark:#4e0875;
                --rkvo-purple-soft:#f4e9fb;
                --rkvo-border:#e7dfef;
                --rkvo-bg:#faf8fc;
                --rkvo-text:#15121a;
                --rkvo-muted:#74707d;
                --rkvo-green:#22a65a;
                --rkvo-red:#e53935;
                --rkvo-shadow:0 18px 45px rgba(64, 20, 95, .08);
                --rkvo-radius:22px;
            }

            body.woocommerce-account{
                background:var(--rkvo-bg) !important;
            }

            .rkvo-wrap{
                direction:rtl;
                width:100%;
                max-width:920px;
                margin:0 auto 34px;
                color:var(--rkvo-text);
                font-family:inherit;
            }

            .rkvo-actions-top{
                display:flex;
                justify-content:space-between;
                align-items:center;
                gap:12px;
                margin:0 0 16px;
                flex-wrap:wrap;
            }

            .rkvo-action-group{
                display:flex;
                align-items:center;
                gap:10px;
                flex-wrap:wrap;
            }

            .rkvo-btn{
                appearance:none;
                border:0;
                outline:0;
                display:inline-flex;
                align-items:center;
                justify-content:center;
                gap:8px;
                min-height:42px;
                padding:10px 18px;
                border-radius:12px;
                font-size:13px;
                font-weight:900;
                line-height:1.4;
                text-decoration:none !important;
                cursor:pointer;
                transition:.18s ease;
                white-space:nowrap;
            }

            .rkvo-btn--primary{
                background:var(--rkvo-purple) !important;
                color:#fff !important;
                box-shadow:0 10px 22px rgba(103,0,158,.18);
            }

            .rkvo-btn--outline{
                background:#fff !important;
                color:var(--rkvo-purple) !important;
                border:1px solid rgba(103,0,158,.28) !important;
            }

            .rkvo-btn--ghost{
                background:transparent !important;
                color:var(--rkvo-purple) !important;
                padding-right:0;
                padding-left:0;
            }

            .rkvo-btn:hover{
                transform:translateY(-1px);
            }

            .rkvo-invoice{
                background:#fff;
                border:1px solid var(--rkvo-border);
                border-radius:var(--rkvo-radius);
                box-shadow:var(--rkvo-shadow);
                overflow:hidden;
            }

            .rkvo-invoice-head{
                display:flex;
                justify-content:space-between;
                align-items:flex-start;
                gap:20px;
                padding:26px 28px 22px;
                border-bottom:1px solid var(--rkvo-border);
                background:
                    radial-gradient(circle at top left, rgba(103,0,158,.08), transparent 32%),
                    #fff;
            }

            .rkvo-title-block h1{
                margin:0 0 8px !important;
                color:var(--rkvo-purple-dark) !important;
                font-size:26px !important;
                font-weight:950 !important;
                line-height:1.5 !important;
            }

            .rkvo-title-block p{
                margin:0 !important;
                color:var(--rkvo-muted);
                font-size:13px;
                font-weight:700;
                line-height:1.9;
            }

            .rkvo-brand{
                text-align:left;
                min-width:150px;
            }

            .rkvo-brand-name{
                color:var(--rkvo-purple);
                font-size:28px;
                font-weight:950;
                line-height:1.2;
                letter-spacing:-.5px;
            }

            .rkvo-brand-sub{
                margin-top:7px;
                color:var(--rkvo-muted);
                font-size:12px;
                font-weight:700;
            }

            .rkvo-summary{
                display:grid;
                grid-template-columns:repeat(4, 1fr);
                border-bottom:1px solid var(--rkvo-border);
                background:#fff;
            }

            .rkvo-summary-item{
                padding:18px 20px;
                border-left:1px solid var(--rkvo-border);
            }

            .rkvo-summary-item:last-child{
                border-left:0;
            }

            .rkvo-label{
                display:block;
                margin-bottom:8px;
                color:var(--rkvo-muted);
                font-size:12px;
                font-weight:800;
                line-height:1.5;
            }

            .rkvo-value{
                display:block;
                color:var(--rkvo-text);
                font-size:15px;
                font-weight:950;
                line-height:1.7;
            }

            .rkvo-value-small{
                display:block;
                color:var(--rkvo-muted);
                font-size:12px;
                font-weight:800;
                margin-top:2px;
            }

            .rkvo-status-pill{
                display:inline-flex;
                align-items:center;
                justify-content:center;
                min-height:28px;
                padding:5px 12px;
                border-radius:999px;
                background:#e8f8ef;
                color:var(--rkvo-green);
                font-size:12px;
                font-weight:950;
                line-height:1.4;
            }

            .rkvo-status-pill.is-pending,
            .rkvo-status-pill.is-on-hold{
                background:#fff4db;
                color:#9a6400;
            }

            .rkvo-status-pill.is-cancelled,
            .rkvo-status-pill.is-failed,
            .rkvo-status-pill.is-refunded{
                background:#ffe7e7;
                color:var(--rkvo-red);
            }

            .rkvo-section{
                padding:22px 28px;
                border-bottom:1px solid var(--rkvo-border);
            }

            .rkvo-section:last-child{
                border-bottom:0;
            }

            .rkvo-section-title{
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:12px;
                margin:0 0 16px;
            }

            .rkvo-section-title h2{
                margin:0 !important;
                color:var(--rkvo-purple-dark) !important;
                font-size:18px !important;
                font-weight:950 !important;
                line-height:1.6 !important;
            }

            .rkvo-products{
                border:1px solid var(--rkvo-border);
                border-radius:18px;
                overflow:hidden;
                background:#fff;
            }

            .rkvo-product-head,
            .rkvo-product-row{
                display:grid;
                grid-template-columns: 1.65fr .7fr .35fr .7fr;
                align-items:center;
                gap:16px;
            }

            .rkvo-product-head{
                background:#f8f3fc;
                border-bottom:1px solid var(--rkvo-border);
                padding:14px 18px;
                color:#40334b;
                font-size:13px;
                font-weight:950;
            }

            .rkvo-product-row{
                padding:18px;
                border-bottom:1px solid var(--rkvo-border);
            }

            .rkvo-product-row:last-child{
                border-bottom:0;
            }

            .rkvo-product-cell{
                min-width:0;
            }

            .rkvo-product-title{
                display:flex;
                align-items:center;
                gap:14px;
                min-width:0;
            }

            .rkvo-product-thumb{
                width:62px;
                min-width:62px;
                height:62px;
                border-radius:16px;
                border:1px solid var(--rkvo-border);
                background:#fff;
                display:flex;
                align-items:center;
                justify-content:center;
                overflow:hidden;
            }

            .rkvo-product-thumb img{
                width:100%;
                height:100%;
                object-fit:cover;
                display:block;
            }

            .rkvo-product-name{
                color:var(--rkvo-text);
                font-size:14px;
                font-weight:950;
                line-height:1.8;
                text-decoration:none !important;
            }

            .rkvo-product-name:hover{
                color:var(--rkvo-purple);
            }

            .rkvo-product-meta{
                margin-top:3px;
                color:var(--rkvo-muted);
                font-size:12px;
                font-weight:700;
                line-height:1.8;
            }

            .rkvo-money,
            .rkvo-qty{
                color:var(--rkvo-text);
                font-size:14px;
                font-weight:950;
                line-height:1.7;
            }

            .rkvo-qty{
                display:inline-flex;
                align-items:center;
                justify-content:center;
                min-width:34px;
                height:30px;
                border-radius:999px;
                background:var(--rkvo-purple-soft);
                color:var(--rkvo-purple);
            }

            .rkvo-totals{
                margin-top:14px;
                border:1px solid var(--rkvo-border);
                border-radius:18px;
                overflow:hidden;
                background:#fff;
            }

            .rkvo-total-row{
                display:grid;
                grid-template-columns:1fr auto;
                align-items:center;
                gap:16px;
                padding:14px 18px;
                border-bottom:1px solid var(--rkvo-border);
            }

            .rkvo-total-row:last-child{
                border-bottom:0;
            }

            .rkvo-total-row span:first-child{
                color:#2a2330;
                font-size:14px;
                font-weight:900;
            }

            .rkvo-total-row span:last-child{
                color:#15121a;
                font-size:14px;
                font-weight:950;
                text-align:left;
            }

            .rkvo-total-row.is-final{
                background:#fbf7ff;
                border-top:2px solid var(--rkvo-purple);
            }

            .rkvo-total-row.is-final span:first-child{
                color:var(--rkvo-purple-dark);
                font-size:16px;
            }

            .rkvo-total-row.is-final span:last-child{
                color:var(--rkvo-purple);
                font-size:21px;
                font-weight:950;
            }

            .rkvo-progress-card{
                border:1px solid var(--rkvo-border);
                border-radius:18px;
                background:#fff;
                padding:22px 18px;
                overflow:hidden;
            }

            .rkvo-progress{
                display:grid;
                grid-template-columns:repeat(5, 1fr);
                position:relative;
                gap:0;
            }

            .rkvo-progress::before{
                content:"";
                position:absolute;
                top:26px;
                right:10%;
                left:10%;
                height:3px;
                background:#e5ddeb;
                border-radius:999px;
                z-index:0;
            }

            .rkvo-progress::after{
                content:"";
                position:absolute;
                top:26px;
                right:10%;
                height:3px;
                width:var(--rkvo-progress-width, 0%);
                background:var(--rkvo-purple);
                border-radius:999px;
                z-index:1;
            }

            .rkvo-step{
                position:relative;
                z-index:2;
                text-align:center;
                color:var(--rkvo-muted);
            }

            .rkvo-step-dot{
                width:54px;
                height:54px;
                margin:0 auto 10px;
                border-radius:50%;
                background:#f0eef4;
                border:4px solid #fff;
                box-shadow:0 0 0 1px var(--rkvo-border);
                color:#9a94a3;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:18px;
                font-weight:950;
            }

            .rkvo-step.is-done .rkvo-step-dot,
            .rkvo-step.is-current .rkvo-step-dot{
                background:var(--rkvo-purple);
                color:#fff;
                box-shadow:0 0 0 1px rgba(103,0,158,.25), 0 8px 18px rgba(103,0,158,.24);
            }

            .rkvo-step-label{
                display:block;
                color:inherit;
                font-size:12px;
                font-weight:950;
                line-height:1.8;
            }

            .rkvo-step.is-done,
            .rkvo-step.is-current{
                color:var(--rkvo-purple-dark);
            }

            .rkvo-step-date{
                display:block;
                color:#9a94a3;
                font-size:11px;
                font-weight:700;
                line-height:1.7;
            }

            .rkvo-address-card{
                display:grid;
                grid-template-columns:1fr auto;
                gap:18px;
                border:1px solid var(--rkvo-border);
                border-radius:18px;
                background:#fff;
                padding:20px;
            }

            .rkvo-address-info{
                display:grid;
                gap:10px;
            }

            .rkvo-address-line{
                display:flex;
                align-items:flex-start;
                gap:9px;
                color:var(--rkvo-text);
                font-size:14px;
                font-weight:800;
                line-height:1.9;
            }

            .rkvo-address-icon{
                width:22px;
                min-width:22px;
                color:var(--rkvo-purple);
                font-size:15px;
                text-align:center;
                margin-top:2px;
            }

            .rkvo-empty{
                color:var(--rkvo-muted);
                font-size:14px;
                font-weight:800;
                line-height:2;
            }

            .rkvo-order-actions-extra{
                display:flex;
                align-items:center;
                gap:10px;
                flex-wrap:wrap;
                margin-top:14px;
            }

            .rkvo-order-actions-extra a{
                display:inline-flex;
                align-items:center;
                justify-content:center;
                min-height:38px;
                padding:8px 14px;
                border-radius:10px;
                background:var(--rkvo-purple-soft);
                color:var(--rkvo-purple) !important;
                font-size:12px;
                font-weight:950;
                text-decoration:none !important;
            }

            @media (max-width:768px){

                .rkvo-wrap{
                    max-width:100%;
                    margin-bottom:22px;
                }

                .rkvo-actions-top{
                    flex-direction:column;
                    align-items:stretch;
                    margin-bottom:12px;
                }

                .rkvo-action-group{
                    width:100%;
                    display:grid;
                    grid-template-columns:1fr 1fr;
                    gap:10px;
                }

                .rkvo-btn{
                    width:100%;
                    min-height:46px;
                    padding:11px 12px;
                    font-size:13px;
                }

                .rkvo-btn--ghost{
                    grid-column:1 / -1;
                    justify-content:flex-start;
                    width:auto;
                    min-height:auto;
                    padding:0;
                }

                .rkvo-invoice{
                    border-radius:18px;
                }

                .rkvo-invoice-head{
                    padding:20px 16px;
                    flex-direction:column;
                    gap:12px;
                }

                .rkvo-title-block h1{
                    font-size:22px !important;
                }

                .rkvo-brand{
                    width:100%;
                    min-width:0;
                    text-align:right;
                    padding-top:8px;
                    border-top:1px solid var(--rkvo-border);
                }

                .rkvo-brand-name{
                    font-size:24px;
                }

                .rkvo-summary{
                    grid-template-columns:1fr 1fr;
                }

                .rkvo-summary-item{
                    padding:15px 14px;
                    border-left:1px solid var(--rkvo-border);
                    border-bottom:1px solid var(--rkvo-border);
                }

                .rkvo-summary-item:nth-child(2n){
                    border-left:0;
                }

                .rkvo-summary-item:nth-last-child(-n+2){
                    border-bottom:0;
                }

                .rkvo-section{
                    padding:18px 14px;
                }

                .rkvo-section-title h2{
                    font-size:17px !important;
                }

                .rkvo-product-head{
                    display:none;
                }

                .rkvo-product-row{
                    display:block;
                    padding:14px;
                }

                .rkvo-product-title{
                    align-items:flex-start;
                    margin-bottom:14px;
                }

                .rkvo-product-thumb{
                    width:58px;
                    min-width:58px;
                    height:58px;
                    border-radius:14px;
                }

                .rkvo-product-cell:not(:first-child){
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:12px;
                    padding:9px 0;
                    border-top:1px dashed #e4ddeb;
                }

                .rkvo-product-cell:not(:first-child)::before{
                    content:attr(data-label);
                    color:var(--rkvo-muted);
                    font-size:12px;
                    font-weight:850;
                }

                .rkvo-money{
                    font-size:13px;
                }

                .rkvo-total-row{
                    grid-template-columns:1fr auto;
                    padding:13px 14px;
                }

                .rkvo-total-row.is-final span:last-child{
                    font-size:18px;
                }

                .rkvo-progress-card{
                    padding:18px 12px;
                    overflow-x:auto;
                }

                .rkvo-progress{
                    min-width:620px;
                }

                .rkvo-step-dot{
                    width:48px;
                    height:48px;
                    font-size:16px;
                }

                .rkvo-address-card{
                    grid-template-columns:1fr;
                    padding:16px;
                }

                .rkvo-address-card .rkvo-btn{
                    width:100%;
                }

                .rkvo-order-actions-extra{
                    display:grid;
                    grid-template-columns:1fr;
                    gap:8px;
                }

                .rkvo-order-actions-extra a{
                    width:100%;
                    min-height:42px;
                }
            }

            @media print{

                body *{
                    visibility:hidden !important;
                }

                .rkvo-wrap,
                .rkvo-wrap *{
                    visibility:visible !important;
                }

                .rkvo-wrap{
                    position:absolute !important;
                    top:0 !important;
                    right:0 !important;
                    left:0 !important;
                    max-width:100% !important;
                    margin:0 !important;
                    padding:0 !important;
                }

                .rkvo-actions-top,
                .rkvo-order-actions-extra,
                .rkvo-address-card .rkvo-btn{
                    display:none !important;
                }

                .rkvo-invoice{
                    box-shadow:none !important;
                    border:1px solid #ddd !important;
                    border-radius:0 !important;
                }

                .rkvo-section,
                .rkvo-invoice-head{
                    break-inside:avoid;
                }
            }
        </style>

        <div class="rkvo-wrap" id="rkvo-invoice">

            <div class="rkvo-actions-top">
                <a class="rkvo-btn rkvo-btn--ghost" href="<?php echo esc_url($orders_url); ?>">
                    <?php echo esc_html(rkvo_text('← بازگشت به سفارش‌ها', '← Back to orders')); ?>
                </a>

                <div class="rkvo-action-group">
                    <button type="button" class="rkvo-btn rkvo-btn--primary" onclick="window.print();">
                        <?php echo esc_html(rkvo_text('چاپ / ذخیره PDF', 'Print / Save PDF')); ?>
                    </button>

                    <button type="button" class="rkvo-btn rkvo-btn--outline" onclick="window.print();">
                        <?php echo esc_html(rkvo_text('دریافت فاکتور', 'Download invoice')); ?>
                    </button>
                </div>
            </div>

            <div class="rkvo-invoice">

                <div class="rkvo-invoice-head">
                    <div class="rkvo-title-block">
                        <h1><?php echo esc_html(rkvo_text('جزئیات سفارش', 'Order details')); ?></h1>
                        <p>
                            <?php echo esc_html(rkvo_text('فاکتور سفارش شماره', 'Invoice for order')); ?>
                            <strong dir="ltr">#<?php echo esc_html($order_number); ?></strong>
                            <?php echo esc_html(rkvo_text('در حساب کاربری شما.', 'in your account.')); ?>
                        </p>
                    </div>

                    <div class="rkvo-brand">
                        <div class="rkvo-brand-name">RitaHost</div>
                        <div class="rkvo-brand-sub"><?php echo esc_html(rkvo_text('فاکتور خرید فروشگاه آنلاین', 'Online store invoice')); ?></div>
                    </div>
                </div>

                <div class="rkvo-summary">

                    <div class="rkvo-summary-item">
                        <span class="rkvo-label"><?php echo esc_html(rkvo_text('شماره سفارش', 'Order number')); ?></span>
                        <span class="rkvo-value" dir="ltr">#<?php echo esc_html($order_number); ?></span>
                    </div>

                    <div class="rkvo-summary-item">
                        <span class="rkvo-label"><?php echo esc_html(rkvo_text('تاریخ ثبت سفارش', 'Order date')); ?></span>
                        <span class="rkvo-value"><?php echo esc_html($order_date); ?></span>
                        <?php if ($order_time) : ?>
                            <span class="rkvo-value-small"><?php echo esc_html($order_time); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="rkvo-summary-item">
                        <span class="rkvo-label"><?php echo esc_html(rkvo_text('وضعیت سفارش', 'Order status')); ?></span>
                        <span class="rkvo-status-pill is-<?php echo esc_attr($order_status); ?>">
                            <?php echo esc_html($status_name); ?>
                        </span>
                    </div>

                    <div class="rkvo-summary-item">
                        <span class="rkvo-label"><?php echo esc_html(rkvo_text('روش پرداخت', 'Payment method')); ?></span>
                        <span class="rkvo-value">
                            <?php echo $payment_title ? esc_html($payment_title) : '—'; ?>
                        </span>
                    </div>

                </div>

                <?php if ($tracking_code) : ?>
                    <div class="rkvo-section" style="padding-top:16px;padding-bottom:16px;">
                        <div class="rkvo-address-line">
                            <span class="rkvo-address-icon">⌁</span>
                            <span>
                                <?php echo esc_html(rkvo_text('کد رهگیری:', 'Tracking code:')); ?>
                                <strong dir="ltr" style="color:var(--rkvo-purple);"><?php echo esc_html($tracking_code); ?></strong>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="rkvo-section">

                    <div class="rkvo-section-title">
                        <h2><?php echo esc_html(rkvo_text('اقلام سفارش', 'Order items')); ?></h2>
                    </div>

                    <div class="rkvo-products">

                        <div class="rkvo-product-head">
                            <div><?php echo esc_html(rkvo_text('محصول', 'Product')); ?></div>
                            <div><?php echo esc_html(rkvo_text('قیمت واحد', 'Unit price')); ?></div>
                            <div><?php echo esc_html(rkvo_text('تعداد', 'Quantity')); ?></div>
                            <div style="text-align:left;"><?php echo esc_html(rkvo_text('جمع جزء', 'Subtotal')); ?></div>
                        </div>

                        <?php foreach ($order->get_items() as $item_id => $item) : ?>

                            <?php
                            $product = $item->get_product();
                            $qty     = max(1, (int) $item->get_quantity());

                            $line_total = (float) $item->get_total();
                            $unit_price = $qty > 0 ? $line_total / $qty : $line_total;

                            $product_name = $item->get_name();
                            $product_url  = $product && $product->is_visible() ? $product->get_permalink() : '';
                            $product_sku  = $product ? $product->get_sku() : '';

                            $thumb_html = $product
                                ? $product->get_image('woocommerce_thumbnail', array('class' => 'rkvo-product-img'))
                                : wc_placeholder_img('woocommerce_thumbnail');
                            ?>

                            <div class="rkvo-product-row">

                                <div class="rkvo-product-cell">
                                    <div class="rkvo-product-title">

                                        <div class="rkvo-product-thumb">
                                            <?php echo wp_kses_post($thumb_html); ?>
                                        </div>

                                        <div>
                                            <?php if ($product_url) : ?>
                                                <a class="rkvo-product-name" href="<?php echo esc_url($product_url); ?>">
                                                    <?php echo esc_html($product_name); ?>
                                                </a>
                                            <?php else : ?>
                                                <span class="rkvo-product-name">
                                                    <?php echo esc_html($product_name); ?>
                                                </span>
                                            <?php endif; ?>

                                            <div class="rkvo-product-meta">
                                                <?php if ($product_sku) : ?>
                                                    <?php echo esc_html(rkvo_text('کد محصول:', 'SKU:')); ?> <?php echo esc_html($product_sku); ?>
                                                <?php else : ?>
                                                    <?php echo esc_html(rkvo_text('کد آیتم:', 'Item ID:')); ?> <?php echo esc_html($item_id); ?>
                                                <?php endif; ?>

                                                <?php
                                                $item_meta = wc_display_item_meta($item, array(
                                                    'echo'      => false,
                                                    'separator' => rkvo_text('، ', ', '),
                                                ));

                                                if ($item_meta) {
                                                    echo '<br>' . wp_kses_post($item_meta);
                                                }
                                                ?>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="rkvo-product-cell" data-label="<?php echo esc_attr(rkvo_text('قیمت واحد', 'Unit price')); ?>">
                                    <span class="rkvo-money">
                                        <?php echo wp_kses_post(wc_price($unit_price, array('currency' => $currency))); ?>
                                    </span>
                                </div>

                                <div class="rkvo-product-cell" data-label="<?php echo esc_attr(rkvo_text('تعداد', 'Quantity')); ?>">
                                    <span class="rkvo-qty"><?php echo esc_html($qty); ?></span>
                                </div>

                                <div class="rkvo-product-cell" data-label="<?php echo esc_attr(rkvo_text('جمع جزء', 'Subtotal')); ?>" style="text-align:left;">
                                    <span class="rkvo-money">
                                        <?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?>
                                    </span>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <div class="rkvo-totals">

                        <?php
                        $totals = $order->get_order_item_totals();

                        foreach ($totals as $key => $total) :
                            $is_final = ($key === 'order_total');
                            ?>
                            <div class="rkvo-total-row <?php echo $is_final ? 'is-final' : ''; ?>">
                                <span><?php echo wp_kses_post($total['label']); ?></span>
                                <span><?php echo wp_kses_post($total['value']); ?></span>
                            </div>
                        <?php endforeach; ?>

                    </div>

                    <?php if ( ! empty($available_actions) ) : ?>
                        <div class="rkvo-order-actions-extra">
                            <?php foreach ($available_actions as $key => $action) : ?>
                                <?php if ($key === 'view') continue; ?>
                                <a href="<?php echo esc_url($action['url']); ?>">
                                    <?php echo esc_html($action['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>

                <div class="rkvo-section">

                    <div class="rkvo-section-title">
                        <h2><?php echo esc_html(rkvo_text('وضعیت و روند سفارش', 'Order progress')); ?></h2>
                    </div>

                    <?php
                    $progress_width = rkvo_get_progress_width($progress_level);
                    $steps = array(
                        array('label' => rkvo_text('ثبت سفارش', 'Order placed'), 'icon' => '✓', 'date' => $order_date),
                        array('label' => rkvo_text('در حال بررسی', 'Processing'), 'icon' => '□', 'date' => $progress_level >= 2 ? $order_date : rkvo_text('در انتظار', 'Pending')),
                        array('label' => rkvo_text('بسته‌بندی', 'Packing'), 'icon' => '▣', 'date' => $progress_level >= 3 ? $order_date : rkvo_text('در انتظار', 'Pending')),
                        array('label' => rkvo_text('تحویل به پست', 'Handed to carrier'), 'icon' => '⌁', 'date' => $progress_level >= 4 ? $order_date : rkvo_text('در انتظار', 'Pending')),
                        array('label' => rkvo_text('تحویل شده', 'Delivered'), 'icon' => '✓', 'date' => $progress_level >= 5 ? $order_date : rkvo_text('در انتظار', 'Pending')),
                    );
                    ?>

                    <div class="rkvo-progress-card">
                        <div class="rkvo-progress" style="--rkvo-progress-width:<?php echo esc_attr($progress_width); ?>;">
                            <?php foreach ($steps as $index => $step) : ?>
                                <?php
                                $step_number = $index + 1;
                                $step_class = '';

                                if ($step_number < $progress_level) {
                                    $step_class = 'is-done';
                                } elseif ($step_number === $progress_level) {
                                    $step_class = 'is-current';
                                }
                                ?>
                                <div class="rkvo-step <?php echo esc_attr($step_class); ?>">
                                    <div class="rkvo-step-dot"><?php echo esc_html($step['icon']); ?></div>
                                    <span class="rkvo-step-label"><?php echo esc_html($step['label']); ?></span>
                                    <span class="rkvo-step-date"><?php echo esc_html($step['date']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <div class="rkvo-section">

                    <div class="rkvo-section-title">
                        <h2><?php echo esc_html(rkvo_text('آدرس صورتحساب', 'Billing address')); ?></h2>
                    </div>

                    <div class="rkvo-address-card">

                        <div class="rkvo-address-info">

                            <?php if ($billing_name) : ?>
                                <div class="rkvo-address-line">
                                    <span class="rkvo-address-icon">👤</span>
                                    <span><?php echo esc_html($billing_name); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($billing_phone) : ?>
                                <div class="rkvo-address-line">
                                    <span class="rkvo-address-icon">☎</span>
                                    <span dir="ltr"><?php echo esc_html($billing_phone); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($billing_email) : ?>
                                <div class="rkvo-address-line">
                                    <span class="rkvo-address-icon">✉</span>
                                    <span dir="ltr"><?php echo esc_html($billing_email); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($billing_state || $billing_city || $billing_full_address) : ?>
                                <div class="rkvo-address-line">
                                    <span class="rkvo-address-icon">●</span>
                                    <span>
                                        <?php
                                        $address_parts = array_filter(array(
                                            $billing_state,
                                            $billing_city,
                                            $billing_full_address,
                                        ));

                                        echo esc_html(implode(rkvo_text('، ', ', '), $address_parts));
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if ($billing_postcode) : ?>
                                <div class="rkvo-address-line">
                                    <span class="rkvo-address-icon">⌁</span>
                                    <span><?php echo esc_html(rkvo_text('کد پستی:', 'Postcode:')); ?> <?php echo esc_html($billing_postcode); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ( ! $billing_name && ! $billing_phone && ! $billing_full_address ) : ?>
                                <div class="rkvo-empty">
                                    <?php echo esc_html(rkvo_text('برای این سفارش آدرس صورتحساب ثبت نشده است.', 'No billing address was recorded for this order.')); ?>
                                </div>
                            <?php endif; ?>

                        </div>

                        <div>
                            <a class="rkvo-btn rkvo-btn--outline" href="<?php echo esc_url($edit_address_url); ?>">
                                <?php echo esc_html(rkvo_text('ویرایش آدرس', 'Edit address')); ?>
                            </a>
                        </div>

                    </div>

                </div>

            </div>

        </div>
        <?php
    }
}


if ( ! function_exists('rkvo_get_order_tracking_code') ) {

    function rkvo_get_order_tracking_code($order) {

        if ( ! $order || ! is_a($order, 'WC_Order') ) {
            return '';
        }

        $possible_keys = array(
            '_tracking_code',
            'tracking_code',
            '_post_tracking_code',
            'post_tracking_code',
            '_novin_tracking_code',
            'novin_tracking_code',
            '_shipping_tracking_code',
            'shipping_tracking_code',
            'کد رهگیری',
        );

        foreach ($possible_keys as $key) {
            $value = $order->get_meta($key, true);

            if ( ! empty($value) ) {
                return is_array($value) ? implode('، ', array_filter($value)) : (string) $value;
            }
        }

        return '';
    }
}


if ( ! function_exists('rkvo_get_order_progress_level') ) {

    function rkvo_get_order_progress_level($status) {

        $status = str_replace('wc-', '', (string) $status);

        $map = array(
            'pending'     => 1,
            'failed'      => 1,
            'cancelled'   => 1,
            'refunded'    => 1,
            'on-hold'     => 1,
            'processing'  => 2,

            // وضعیت‌های احتمالی سفارشی
            'review'      => 2,
            'checking'    => 2,
            'packing'     => 3,
            'packed'      => 3,
            'shipped'     => 4,
            'post'        => 4,
            'sent'        => 4,
            'deliver'     => 5,
            'delivered'   => 5,
            'completed'   => 5,
        );

        return isset($map[$status]) ? (int) $map[$status] : 2;
    }
}


if ( ! function_exists('rkvo_get_progress_width') ) {

    function rkvo_get_progress_width($level) {

        $level = max(1, min(5, (int) $level));

        $widths = array(
            1 => '0%',
            2 => '25%',
            3 => '50%',
            4 => '75%',
            5 => '80%',
        );

        return $widths[$level];
    }
}

