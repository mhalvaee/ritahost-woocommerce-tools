<?php
/**
 * Compact order summary – no product list; totals only.
 */
defined('ABSPATH') || exit;
?>
<table class="shop_table woocommerce-checkout-review-order-table">
    <tbody>
        <tr class="cart-subtotal">
            <th><?php echo esc_html(rkzn_text('جمع کل کالاها', 'Products subtotal')); ?></th>
            <td><?php wc_cart_totals_subtotal_html(); ?></td>
        </tr>

        <?php foreach (WC()->cart->get_coupons() as $code => $coupon) : ?>
            <tr class="cart-discount coupon-<?php echo esc_attr(sanitize_title($code)); ?>">
                <th><?php wc_cart_totals_coupon_label($coupon); ?></th>
                <td><?php wc_cart_totals_coupon_html($coupon); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php foreach (WC()->cart->get_fees() as $fee) : ?>
            <tr class="fee">
                <th><?php echo esc_html($fee->name); ?></th>
                <td><?php wc_cart_totals_fee_html($fee); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if (WC()->cart->needs_shipping()) : ?>
            <tr class="rkzn-summary-shipping">
                <th><?php echo esc_html(rkzn_text('هزینه ارسال', 'Shipping')); ?></th>
                <td><?php echo wp_kses_post(WC()->cart->get_cart_shipping_total()); ?></td>
            </tr>
        <?php endif; ?>

        <?php $saving = rkzn_savings_total(); if ($saving > 0) : ?>
            <tr class="rkzn-saving-row">
                <th><?php echo esc_html(rkzn_text('سود شما از این خرید', 'You save')); ?></th>
                <td><?php echo wp_kses_post(wc_price($saving)); ?></td>
            </tr>
        <?php endif; ?>

        <?php if (wc_tax_enabled() && !WC()->cart->display_prices_including_tax()) : ?>
            <?php if ('itemized' === get_option('woocommerce_tax_total_display')) : ?>
                <?php foreach (WC()->cart->get_tax_totals() as $code => $tax) : ?>
                    <tr class="tax-rate tax-rate-<?php echo esc_attr(sanitize_title($code)); ?>">
                        <th><?php echo esc_html($tax->label); ?></th>
                        <td><?php echo wp_kses_post($tax->formatted_amount); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr class="tax-total"><th><?php echo esc_html(WC()->countries->tax_or_vat()); ?></th><td><?php wc_cart_totals_taxes_total_html(); ?></td></tr>
            <?php endif; ?>
        <?php endif; ?>

        <tr class="order-total">
            <th><?php echo esc_html(rkzn_text('مبلغ قابل پرداخت', 'Total')); ?></th>
            <td><?php wc_cart_totals_order_total_html(); ?></td>
        </tr>
    </tbody>
</table>

