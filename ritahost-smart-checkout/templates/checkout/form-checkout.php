<?php
/**
 * Custom checkout form – RitaHost reusable structure.
 */
defined('ABSPATH') || exit;

do_action('woocommerce_before_checkout_form', $checkout);

if (!$checkout->is_registration_enabled() && $checkout->is_registration_required() && !is_user_logged_in()) {
    echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message', __('You must be logged in to checkout.', 'woocommerce')));
    return;
}
?>
<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__('Checkout', 'woocommerce'); ?>">
    <div class="rkzn-layout">
        <div class="rkzn-summary-column">
            <aside class="rkzn-summary">
                <h3 class="rkzn-summary-title"><?php echo esc_html(rkzn_text('سفارش شما', 'Your order')); ?></h3>
                <div id="order_review" class="woocommerce-checkout-review-order">
                    <?php woocommerce_order_review(); ?>
                </div>
                <div id="rkzn-place-order-host" class="rkzn-place-order-host"></div>
                <a class="rkzn-summary-back" href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php echo esc_html(rkzn_text('بازگشت به سبد خرید', 'Back to cart')); ?></a>
            </aside>
        </div>

        <main class="rkzn-main">
            <?php rkzn_render_address_list(); ?>

            <section id="rkzn-address-form" class="rkzn-section rkzn-address-form-section">
                <div class="rkzn-address-form-header">
                    <h3><?php echo esc_html(rkzn_text('وارد کردن آدرس:', 'Enter address:')); ?></h3>
                    <button type="button" class="rkzn-clear-fields"><?php echo rkzn_icon('trash'); ?> <?php echo esc_html(rkzn_text('پاک کردن', 'Clear')); ?></button>
                </div>
                <?php do_action('woocommerce_checkout_billing'); ?>
            </section>

            <?php rkzn_render_shipping(); ?>
            <?php rkzn_render_delivery($checkout); ?>
            <?php rkzn_render_payment(); ?>
            <?php rkzn_render_extras($checkout); ?>

            <?php do_action('woocommerce_checkout_after_customer_details'); ?>
            <?php do_action('woocommerce_checkout_after_order_review'); ?>
        </main>
    </div>
</form>
<?php do_action('woocommerce_after_checkout_form', $checkout); ?>