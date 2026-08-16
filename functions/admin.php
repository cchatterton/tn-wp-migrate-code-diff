<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_register_admin_page()
{
    if (is_multisite()) {
        add_submenu_page(
            'settings.php',
            __('Upload Release', 'tn-wp-migrate-code-diff'),
            __('Upload Release', 'tn-wp-migrate-code-diff'),
            twmcd_release_install_capability(),
            TWMCD_UPLOAD_PAGE_SLUG,
            'twmcd_render_upload_release_page'
        );
        add_submenu_page(
            'settings.php',
            __('Release Notes', 'tn-wp-migrate-code-diff'),
            __('Release Notes', 'tn-wp-migrate-code-diff'),
            twmcd_release_install_capability(),
            TWMCD_HISTORY_PAGE_SLUG,
            'twmcd_render_release_history_page'
        );
    } else {
        add_options_page(
            __('Upload Release', 'tn-wp-migrate-code-diff'),
            __('Upload Release', 'tn-wp-migrate-code-diff'),
            twmcd_release_install_capability(),
            TWMCD_UPLOAD_PAGE_SLUG,
            'twmcd_render_upload_release_page'
        );
        add_options_page(
            __('Release Notes', 'tn-wp-migrate-code-diff'),
            __('Release Notes', 'tn-wp-migrate-code-diff'),
            twmcd_release_install_capability(),
            TWMCD_HISTORY_PAGE_SLUG,
            'twmcd_render_release_history_page'
        );
    }

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
        __('TN WP Migrate Database Diff', 'tn-wp-migrate-code-diff'),
        __('WP Migrate Database Diff', 'tn-wp-migrate-code-diff'),
        twmcd_admin_capability(),
        TWMCD_DATABASE_PAGE_SLUG,
        'twmcd_render_database_admin_page'
    );

    add_submenu_page(
        null,
        __('TN WP Migrate Options Diff', 'tn-wp-migrate-code-diff'),
        __('WP Migrate Options Diff', 'tn-wp-migrate-code-diff'),
        twmcd_admin_capability(),
        TWMCD_OPTIONS_PAGE_SLUG,
        'twmcd_render_options_admin_page'
    );
}

function twmcd_release_history_page_url()
{
    return is_multisite()
        ? network_admin_url('settings.php?page=' . TWMCD_HISTORY_PAGE_SLUG)
        : admin_url('options-general.php?page=' . TWMCD_HISTORY_PAGE_SLUG);
}

function twmcd_render_release_history_page()
{
    if (!current_user_can(twmcd_release_install_capability())) {
        wp_die(esc_html__('You do not have permission to view release notes.', 'tn-wp-migrate-code-diff'));
    }

    $history_rows = twmcd_get_release_history();
    require TWMCD_PLUGIN_DIR . 'templates/release-history-page.php';
}

function twmcd_release_install_capability()
{
    return 'update_plugins';
}

function twmcd_upload_release_page_url()
{
    return is_multisite()
        ? network_admin_url('settings.php?page=' . TWMCD_UPLOAD_PAGE_SLUG)
        : admin_url('options-general.php?page=' . TWMCD_UPLOAD_PAGE_SLUG);
}

function twmcd_render_upload_release_page()
{
    if (!current_user_can(twmcd_release_install_capability())) {
        wp_die(esc_html__('You do not have permission to install releases.', 'tn-wp-migrate-code-diff'));
    }

    $result = get_site_transient('twmcd_install_result_' . get_current_user_id());
    delete_site_transient('twmcd_install_result_' . get_current_user_id());
    require TWMCD_PLUGIN_DIR . 'templates/upload-release-page.php';
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

function twmcd_render_options_admin_page()
{
    if (!current_user_can(twmcd_admin_capability())) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tn-wp-migrate-code-diff'));
    }

    $wp_migrate_available = twmcd_is_wp_migrate_available();
    $migrate_url = twmcd_migrate_admin_url();

    require TWMCD_PLUGIN_DIR . 'templates/options-page.php';
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
        '<a href="' . esc_url(twmcd_upload_release_page_url()) . '">' . esc_html__('Upload Release', 'tn-wp-migrate-code-diff') . '</a>'
    );
    array_unshift(
        $links,
        '<a href="' . esc_url(twmcd_migrate_admin_url()) . '">' . esc_html__('WP Migrate', 'tn-wp-migrate-code-diff') . '</a>'
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
