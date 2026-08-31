<?php
/**
 * Billing and order fields in explicit rows.
 *
 * Rows:
 * 1) first name / last name
 * 2) phone / email
 * 3) state / city
 * 4) postcode / birth date
 * 5) full address / order notes
 */
defined('ABSPATH') || exit;

$billing_fields = (array) $checkout->get_checkout_fields('billing');
$order_fields   = (array) $checkout->get_checkout_fields('order');

uasort($billing_fields, 'wc_checkout_fields_uasort_comparison');
uasort($order_fields, 'wc_checkout_fields_uasort_comparison');

$rendered_billing = [];
$rendered_order   = [];

$render_field = static function ($key, $field, $group = 'billing') use ($checkout, &$rendered_billing, &$rendered_order) {
    if (!$key || !is_array($field)) {
        return false;
    }

    if ($group === 'billing') {
        $rendered_billing[$key] = true;
    } else {
        $rendered_order[$key] = true;
    }

    if (function_exists('rkzn_is_birthdate_field') && rkzn_is_birthdate_field($key, $field)) {
        rkzn_render_birthdate_field($key, $field, $checkout->get_value($key));
        return true;
    }

    woocommerce_form_field($key, $field, $checkout->get_value($key));
    return true;
};

$render_billing = static function ($key) use (&$billing_fields, $render_field) {
    return isset($billing_fields[$key]) ? $render_field($key, $billing_fields[$key], 'billing') : false;
};

$render_order = static function ($key) use (&$order_fields, $render_field) {
    return isset($order_fields[$key]) ? $render_field($key, $order_fields[$key], 'order') : false;
};

$birth_key = '';
$birth_group = '';
foreach ($billing_fields as $key => $field) {
    if (function_exists('rkzn_is_birthdate_field') && rkzn_is_birthdate_field($key, $field)) {
        $birth_key = $key;
        $birth_group = 'billing';
        break;
    }
}
if ($birth_key === '') {
    foreach ($order_fields as $key => $field) {
        if (function_exists('rkzn_is_birthdate_field') && rkzn_is_birthdate_field($key, $field)) {
            $birth_key = $key;
            $birth_group = 'order';
            break;
        }
    }
}
?>
<div class="woocommerce-billing-fields">
    <?php do_action('woocommerce_before_checkout_billing_form', $checkout); ?>

    <div class="woocommerce-billing-fields__field-wrapper rkzn-fields-grid">

        <?php /* Country is submitted normally but has no visual grid cell. */ ?>
        <div class="rkzn-hidden-checkout-fields" aria-hidden="true">
            <?php $render_billing('billing_country'); ?>
        </div>

        <div class="rkzn-field-row rkzn-field-row-names">
            <?php $render_billing('billing_first_name'); ?>
            <?php $render_billing('billing_last_name'); ?>
        </div>

        <div class="rkzn-field-row rkzn-field-row-contact">
            <?php $render_billing('billing_phone'); ?>
            <?php $render_billing('billing_email'); ?>
        </div>

        <div class="rkzn-field-row rkzn-field-row-location">
            <?php $render_billing('billing_state'); ?>
            <?php $render_billing('billing_city'); ?>
        </div>

        <div class="rkzn-field-row rkzn-field-row-postcode-birth">
            <?php $render_billing('billing_postcode'); ?>
            <?php
            if ($birth_key !== '') {
                if ($birth_group === 'billing') {
                    $render_field($birth_key, $billing_fields[$birth_key], 'billing');
                } else {
                    $render_field($birth_key, $order_fields[$birth_key], 'order');
                }
            }
            ?>
        </div>

        <div class="rkzn-field-row rkzn-field-row-address-notes">
            <?php $render_billing('billing_address_1'); ?>
            <?php $render_order('order_comments'); ?>
        </div>

        <?php
        $remaining_billing = [];
        foreach ($billing_fields as $key => $field) {
            if (empty($rendered_billing[$key])) {
                $remaining_billing[$key] = $field;
            }
        }
        $remaining_order = [];
        foreach ($order_fields as $key => $field) {
            if (empty($rendered_order[$key])) {
                $remaining_order[$key] = $field;
            }
        }
        ?>

        <?php if ($remaining_billing || $remaining_order) : ?>
            <div class="rkzn-field-row rkzn-field-row-extra">
                <?php foreach ($remaining_billing as $key => $field) : ?>
                    <?php $render_field($key, $field, 'billing'); ?>
                <?php endforeach; ?>
                <?php foreach ($remaining_order as $key => $field) : ?>
                    <?php $render_field($key, $field, 'order'); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php do_action('woocommerce_after_checkout_billing_form', $checkout); ?>
</div>

<?php if (!is_user_logged_in() && $checkout->is_registration_enabled()) : ?>
    <div class="woocommerce-account-fields">
        <?php if (!$checkout->is_registration_required()) : ?>
            <p class="form-row form-row-wide create-account">
                <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                    <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="createaccount" <?php checked((true === $checkout->get_value('createaccount') || (true === apply_filters('woocommerce_create_account_default_checked', false))), true); ?> type="checkbox" name="createaccount" value="1" />
                    <span><?php esc_html_e('Create an account?', 'woocommerce'); ?></span>
                </label>
            </p>
        <?php endif; ?>
        <?php do_action('woocommerce_before_checkout_registration_form', $checkout); ?>
        <?php if ($checkout->get_checkout_fields('account')) : ?>
            <div class="create-account">
                <?php foreach ($checkout->get_checkout_fields('account') as $key => $field) : ?>
                    <?php woocommerce_form_field($key, $field, $checkout->get_value($key)); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php do_action('woocommerce_after_checkout_registration_form', $checkout); ?>
    </div>
<?php endif; ?>

