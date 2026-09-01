<?php
/**
 * Plugin Name: RitaHost Product Workflow Statuses
 * Description: Adds configurable-style workflow statuses to WooCommerce products and exposes them in product bulk actions.
 * Version: 1.1.1
 * Author: RitaHost
 * Text Domain: ritahost
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;

function rhpws_text($fa, $en) {
    return strpos(determine_locale(), 'fa') === 0 ? $fa : $en;
}

if (function_exists('ritahost_register_admin_tool')) {
    ritahost_register_admin_tool('ritahost-product-workflow-statuses', 'وضعیت‌های گردش کار محصول', 'Product Workflow Statuses', 'وضعیت‌های داخلی محصولات دارویی را به فهرست و عملیات گروهی ووکامرس اضافه می‌کند.', 'Adds internal product workflow statuses and WooCommerce bulk actions.', 'manage_woocommerce');
}

function pharma_workflow_statuses_list() {
    return [
        'pharma_future' => rhpws_text('محصولات آینده', 'Future products'),
        'pharma_waiting' => rhpws_text('در انتظار داروخانه', 'Waiting for pharmacy'),
        'pharma_approved' => rhpws_text('تأیید داروخانه', 'Pharmacy approved'),
        'pharma_code_diff' => rhpws_text('اختلاف کد', 'Code mismatch'),
    ];
}

/**
 * Register custom product statuses
 */
add_action('init', function () {

    foreach (pharma_workflow_statuses_list() as $key => $label) {
        register_post_status($key, [
            'label'                     => $label,
            'public'                    => false,
            'internal'                  => false,
            'protected'                 => true,
            'private'                   => false,
            'exclude_from_search'        => true,
            'show_in_admin_all_list'     => true,
            'show_in_admin_status_list'  => true,
            'label_count'               => _n_noop(
                $label . ' <span class="count">(%s)</span>',
                $label . ' <span class="count">(%s)</span>'
            ),
        ]);
    }

});

/**
 * Add custom status links above products list
 */
add_filter('views_edit-product', function ($views) {

    $counts = wp_count_posts('product');
    $base = admin_url('edit.php?post_type=product');

    foreach (pharma_workflow_statuses_list() as $key => $label) {
        $count = isset($counts->$key) ? intval($counts->$key) : 0;

        $views[$key] = '<a href="' . esc_url(add_query_arg('post_status', $key, $base)) . '">' .
            esc_html($label) .
            ' <span class="count">(' . $count . ')</span></a>';
    }

    return $views;
});

/**
 * Add bulk actions
 */
add_filter('bulk_actions-edit-product', function ($actions) {

    foreach (pharma_workflow_statuses_list() as $key => $label) {
        $actions['pharma_set_' . $key] = sprintf(rhpws_text('تغییر وضعیت به %s', 'Change status to %s'), $label);
    }

    $actions['pharma_set_publish'] = rhpws_text('انتشار محصولات انتخاب‌شده', 'Publish selected products');

    return $actions;
});

/**
 * Handle bulk actions
 */
add_filter('handle_bulk_actions-edit-product', function ($redirect_to, $action, $post_ids) {

    $statuses = pharma_workflow_statuses_list();
    $new_status = '';

    foreach ($statuses as $key => $label) {
        if ($action === 'pharma_set_' . $key) {
            $new_status = $key;
            break;
        }
    }

    if ($action === 'pharma_set_publish') {
        $new_status = 'publish';
    }

    if (!$new_status) {
        return $redirect_to;
    }

    $changed = 0;
    $post_type = get_post_type_object('product');
    $publish_capability = $post_type && !empty($post_type->cap->publish_posts)
        ? $post_type->cap->publish_posts
        : 'publish_products';

    foreach (array_unique(array_map('absint', (array) $post_ids)) as $post_id) {
        if (!$post_id || get_post_type($post_id) !== 'product') {
            continue;
        }

        if (!current_user_can('edit_post', $post_id)) {
            continue;
        }

        if ($new_status === 'publish' && !current_user_can($publish_capability)) {
            continue;
        }

        $result = wp_update_post([
            'ID'          => $post_id,
            'post_status' => $new_status,
        ], true);

        if (!is_wp_error($result) && $result) {
            $changed++;
        }
    }

    return add_query_arg('pharma_changed', $changed, $redirect_to);

}, 10, 3);

/**
 * Admin notice
 */
add_action('admin_notices', function () {

    if (!empty($_GET['pharma_changed'])) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        printf(
            esc_html(rhpws_text('%d محصول با موفقیت تغییر وضعیت داده شد.', '%d products were updated successfully.')),
            intval($_GET['pharma_changed'])
        );
        echo '</p></div>';
    }

});

/**
 * Show custom status beside product title
 */
add_filter('display_post_states', function ($states, $post) {

    if ($post->post_type !== 'product') {
        return $states;
    }

    foreach (pharma_workflow_statuses_list() as $key => $label) {
        if ($post->post_status === $key) {
            $states[] = $label;
        }
    }

    return $states;

}, 10, 2);

/**
 * Add custom statuses to single product edit dropdown
 */
add_action('admin_footer-post.php', 'pharma_add_statuses_to_product_dropdown');
add_action('admin_footer-post-new.php', 'pharma_add_statuses_to_product_dropdown');

function pharma_add_statuses_to_product_dropdown() {
    global $post;

    if (!$post || $post->post_type !== 'product') {
        return;
    }

    $statuses = pharma_workflow_statuses_list();
    ?>
    <script>
    jQuery(function($) {

        var statuses = <?php echo wp_json_encode($statuses); ?>;
        var currentStatus = '<?php echo esc_js($post->post_status); ?>';

        $.each(statuses, function(value, label) {
            if ($('#post_status option[value="' + value + '"]').length === 0) {
                $('#post_status').append('<option value="' + value + '">' + label + '</option>');
            }
        });

        if (statuses[currentStatus]) {
            $('#post_status').val(currentStatus);
            $('#post-status-display').text(statuses[currentStatus]);
        }

    });
    </script>
    <?php
}

/**
 * Add custom statuses to Quick Edit and Bulk Edit dropdown
 */
add_action('admin_footer-edit.php', function () {

    $screen = get_current_screen();

    if (!$screen || $screen->id !== 'edit-product') {
        return;
    }

    $statuses = pharma_workflow_statuses_list();
    ?>
    <script>
    jQuery(function($) {

        var statuses = <?php echo wp_json_encode($statuses); ?>;

        function addPharmaStatusesToInlineEdit() {

            var selects = $('#inline-edit select[name="_status"], #bulk-edit select[name="_status"], select[name="_status"]');

            selects.each(function() {

                var select = jQuery(this);

                jQuery.each(statuses, function(value, label) {
                    if (select.find('option[value="' + value + '"]').length === 0) {
                        select.append('<option value="' + value + '">' + label + '</option>');
                    }
                });

            });
        }

        addPharmaStatusesToInlineEdit();

        jQuery(document).on('click', '.editinline, #doaction, #doaction2, #bulk_edit', function() {
            setTimeout(addPharmaStatusesToInlineEdit, 300);
        });

        jQuery(document).ajaxComplete(function() {
            addPharmaStatusesToInlineEdit();
        });

    });
    </script>
    <?php
});