<?php

if (!defined('ABSPATH')) {
    exit;
}

function tncri_register_hooks()
{
    add_action(is_multisite() ? 'network_admin_menu' : 'admin_menu', 'tncri_register_admin_page');
    add_action('admin_enqueue_scripts', 'tncri_enqueue_admin_assets');
    add_action('admin_post_tncri_upload_release', 'tncri_handle_release_upload');
    add_filter('plugin_action_links_' . plugin_basename(TNCRI_PLUGIN_FILE), 'tncri_add_plugin_action_link');
    add_filter('pre_set_site_transient_update_plugins', 'tncri_add_github_update_data');
    add_filter('site_transient_update_plugins', 'tncri_add_github_update_data');
    add_filter('plugins_api', 'tncri_github_plugin_information', 20, 3);
    add_filter('plugin_row_meta', 'tncri_add_github_plugin_row_meta', 10, 2);
    add_action('admin_init', 'tncri_handle_manual_update_check');
    add_action('upgrader_process_complete', 'tncri_clear_github_cache_after_upgrade', 10, 2);
}

tncri_register_hooks();
