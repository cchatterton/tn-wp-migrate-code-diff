<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_register_admin_page()
{
    add_submenu_page(
        null,
        __('TN WP Migrate Code Diff', 'tn-wp-migrate-code-diff'),
        __('WP Migrate Code Diff', 'tn-wp-migrate-code-diff'),
        twmcd_admin_capability(),
        TWMCD_PAGE_SLUG,
        'twmcd_render_admin_page'
    );
}

function twmcd_render_admin_page()
{
    if (!current_user_can(twmcd_admin_capability())) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tn-wp-migrate-code-diff'));
    }

    $wp_migrate_available = twmcd_is_wp_migrate_available();
    $migrate_url = twmcd_migrate_admin_url();

    require TWMCD_PLUGIN_DIR . 'templates/admin-page.php';
}

function twmcd_add_plugin_action_link($links)
{
    array_unshift(
        $links,
        '<a href="' . esc_url(twmcd_migrate_admin_url()) . '">' . esc_html__('Open WP Migrate', 'tn-wp-migrate-code-diff') . '</a>'
    );

    return $links;
}
