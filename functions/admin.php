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

    add_submenu_page(
        null,
        __('TN WP Migrate Database / Images Diff', 'tn-wp-migrate-code-diff'),
        __('WP Migrate Database / Images Diff', 'tn-wp-migrate-code-diff'),
        twmcd_admin_capability(),
        TWMCD_DATABASE_PAGE_SLUG,
        'twmcd_render_database_admin_page'
    );
}

function twmcd_render_database_admin_page()
{
    if (!current_user_can(twmcd_admin_capability())) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tn-wp-migrate-code-diff'));
    }

    $wp_migrate_available = twmcd_is_wp_migrate_available();
    $migrate_url = twmcd_migrate_admin_url();

    require TWMCD_PLUGIN_DIR . 'templates/database-page.php';
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

function twmcd_render_integration_notice_mount()
{
    if (!current_user_can(twmcd_admin_capability())) {
        return;
    }
    ?>
    <div id="twmcd-integration-notice-mount" class="twmcd-integration-notice-mount">
        <div id="twmcd-integration-notice" class="twmcd-migrate-notice" role="status">
            <span class="twmcd-notice-icon" aria-hidden="true">&#8644;</span>
            <strong><?php esc_html_e('Site comparison is listening — waiting for WP Migrate state.', 'tn-wp-migrate-code-diff'); ?></strong>
        </div>
    </div>
    <?php
}
