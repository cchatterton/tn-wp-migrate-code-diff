<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_register_hooks()
{
    add_action(is_multisite() ? 'network_admin_menu' : 'admin_menu', 'twmcd_register_admin_page');
    add_action('admin_enqueue_scripts', 'twmcd_enqueue_admin_assets');
    add_action('wp_ajax_twmcd_compare_code', 'twmcd_ajax_compare_code');
    add_action('wp_ajax_twmcd_prepare_comparison', 'twmcd_ajax_prepare_comparison');
    add_action('wp_ajax_twmcd_save_profile', 'twmcd_ajax_save_profile');
    add_filter('plugin_action_links_' . plugin_basename(TWMCD_PLUGIN_FILE), 'twmcd_add_plugin_action_link');
    add_filter('pre_set_site_transient_update_plugins', 'twmcd_add_github_update_data');
    add_filter('site_transient_update_plugins', 'twmcd_add_github_update_data');
    add_filter('plugins_api', 'twmcd_github_plugin_information', 20, 3);
    add_filter('plugin_row_meta', 'twmcd_add_github_plugin_row_meta', 10, 2);
    add_action('admin_init', 'twmcd_handle_manual_update_check');
    add_action('upgrader_process_complete', 'twmcd_clear_github_cache_after_upgrade', 10, 2);
    add_action('wpmdb_notices', 'twmcd_render_integration_notice_mount');
}

twmcd_register_hooks();
