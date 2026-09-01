<?php
/**
 * Plugin Name: RitaHost Admin Panel
 * Description: Provides a shared bilingual WordPress admin menu for RitaHost tools while allowing every tool to run independently.
 * Version: 1.0.0
 * Author: RitaHost
 * Text Domain: ritahost
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

/**
 * Return one label based on the WordPress site language.
 */
function ritahost_admin_text($persian, $english) {
    $locale = get_locale();
    return 0 === strpos(strtolower((string) $locale), 'fa') ? $persian : $english;
}

/**
 * Register a RitaHost tool for the overview shown on the parent admin page.
 */
function ritahost_register_admin_tool($slug, $title_fa, $title_en, $description_fa, $description_en, $capability) {
    $GLOBALS['ritahost_admin_tools'][sanitize_key($slug)] = [
        'title'       => ritahost_admin_text($title_fa, $title_en),
        'description' => ritahost_admin_text($description_fa, $description_en),
        'capability'  => sanitize_key($capability),
    ];
}

function ritahost_admin_panel_capability() {
    return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
}

function ritahost_render_admin_panel() {
    if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ritahost'));
    }

    $tools = isset($GLOBALS['ritahost_admin_tools']) && is_array($GLOBALS['ritahost_admin_tools'])
        ? $GLOBALS['ritahost_admin_tools']
        : [];
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(ritahost_admin_text('پنل ریتاهاست', 'RitaHost Panel')); ?></h1>
        <p><?php echo esc_html(ritahost_admin_text('تنظیمات ابزارهای فعال ریتاهاست از زیرمنوهای این بخش در دسترس است.', 'Settings for active RitaHost tools are available from this menu.')); ?></p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;max-width:1000px;margin-top:20px">
            <?php foreach ($tools as $tool) : ?>
                <?php if (current_user_can($tool['capability'])) : ?>
                    <div class="card" style="margin:0;max-width:none">
                        <h2><?php echo esc_html($tool['title']); ?></h2>
                        <p><?php echo esc_html($tool['description']); ?></p>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

add_action('admin_menu', function () {
    add_menu_page(
        ritahost_admin_text('پنل ریتاهاست', 'RitaHost Panel'),
        ritahost_admin_text('پنل ریتاهاست', 'RitaHost Panel'),
        ritahost_admin_panel_capability(),
        'ritahost-panel',
        'ritahost_render_admin_panel',
        'dashicons-admin-generic',
        58
    );
}, 5);