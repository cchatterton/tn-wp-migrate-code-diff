<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_admin_capability()
{
    return is_multisite() ? 'manage_network_options' : 'export';
}

function twmcd_admin_page_url()
{
    return is_multisite()
        ? network_admin_url('admin.php?page=' . TWMCD_PAGE_SLUG)
        : admin_url('admin.php?page=' . TWMCD_PAGE_SLUG);
}

function twmcd_database_admin_page_url()
{
    return is_multisite()
        ? network_admin_url('admin.php?page=' . TWMCD_DATABASE_PAGE_SLUG)
        : admin_url('admin.php?page=' . TWMCD_DATABASE_PAGE_SLUG);
}

function twmcd_migrate_admin_url()
{
    return is_multisite()
        ? network_admin_url('settings.php?page=wp-migrate-db-pro')
        : admin_url('tools.php?page=wp-migrate-db-pro');
}

function twmcd_default_profile_name()
{
    return 'Release-' . date_i18n('Ymd-Hi');
}

function twmcd_is_wp_migrate_available()
{
    return false !== twmcd_wp_migrate_version();
}

function twmcd_wp_migrate_version()
{
    if (!empty($GLOBALS['wpmdb_meta']['wp-migrate-db-pro']['version'])) {
        return (string) $GLOBALS['wpmdb_meta']['wp-migrate-db-pro']['version'];
    }

    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugins = get_plugins();
    $plugin_file = 'wp-migrate-db-pro/wp-migrate-db-pro.php';

    $plugin_active = is_plugin_active($plugin_file)
        || (is_multisite() && is_plugin_active_for_network($plugin_file));

    if (!$plugin_active || empty($plugins[$plugin_file]['Version'])) {
        return false;
    }

    return (string) $plugins[$plugin_file]['Version'];
}

function twmcd_parse_connection_info($connection_info)
{
    $lines = preg_split('/\R/', trim((string) $connection_info));
    $lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));

    if (count($lines) < 2 || !wp_http_validate_url($lines[0])) {
        return new WP_Error(
            'twmcd_invalid_connection',
            __('Enter the remote URL on the first line and its WP Migrate secret key on the second line.', 'tn-wp-migrate-code-diff')
        );
    }

    if (strlen($lines[1]) < 20) {
        return new WP_Error(
            'twmcd_invalid_key',
            __('The WP Migrate secret key does not appear to be valid.', 'tn-wp-migrate-code-diff')
        );
    }

    return array(
        'url' => untrailingslashit(esc_url_raw($lines[0])),
        'key' => sanitize_text_field($lines[1]),
    );
}

function twmcd_context_transient_key($token)
{
    return 'twmcd_ctx_' . get_current_user_id() . '_' . sanitize_key($token);
}

function twmcd_store_comparison_context($context)
{
    $token = wp_generate_password(20, false, false);

    set_site_transient(
        twmcd_context_transient_key($token),
        $context,
        15 * MINUTE_IN_SECONDS
    );

    return $token;
}

function twmcd_sanitize_boolean($value)
{
    return true === $value || 1 === $value || '1' === $value || 'true' === $value;
}

function twmcd_sanitize_migration_context($raw_context)
{
    $raw_context = is_array($raw_context) ? $raw_context : array();
    $raw_multisite = isset($raw_context['multisite_tools']) && is_array($raw_context['multisite_tools'])
        ? $raw_context['multisite_tools']
        : array();
    $raw_migration = isset($raw_context['migration']) && is_array($raw_context['migration'])
        ? $raw_context['migration']
        : array();

    return array(
        'multisite_tools' => array(
            'enabled'             => twmcd_sanitize_boolean(isset($raw_multisite['enabled']) ? $raw_multisite['enabled'] : false),
            'selected_subsite'    => absint(isset($raw_multisite['selected_subsite']) ? $raw_multisite['selected_subsite'] : 0),
            'destination_subsite' => absint(isset($raw_multisite['destination_subsite']) ? $raw_multisite['destination_subsite'] : 0),
            'new_prefix'          => isset($raw_multisite['new_prefix']) ? sanitize_key($raw_multisite['new_prefix']) : '',
        ),
        'migration' => array(
            'local_source'        => twmcd_sanitize_boolean(isset($raw_migration['local_source']) ? $raw_migration['local_source'] : false),
            'two_multisites'      => twmcd_sanitize_boolean(isset($raw_migration['two_multisites']) ? $raw_migration['two_multisites'] : false),
            'local_is_multisite'  => twmcd_sanitize_boolean(isset($raw_migration['local_is_multisite']) ? $raw_migration['local_is_multisite'] : false),
            'remote_is_multisite' => twmcd_sanitize_boolean(isset($raw_migration['remote_is_multisite']) ? $raw_migration['remote_is_multisite'] : false),
            'scope_label'         => isset($raw_migration['scope_label']) ? sanitize_text_field($raw_migration['scope_label']) : '',
        ),
    );
}

function twmcd_validate_multisite_context($context)
{
    $multisite = $context['multisite_tools'];
    $migration = $context['migration'];

    if (!$multisite['enabled']) {
        return true;
    }

    if (($migration['local_is_multisite'] || $migration['remote_is_multisite'])
        && 1 > $multisite['selected_subsite']) {
        return new WP_Error(
            'twmcd_subsite_required',
            __('Select the required subsite in WP Migrate before comparing sites.', 'tn-wp-migrate-code-diff')
        );
    }

    if ($migration['two_multisites'] && 1 > $multisite['destination_subsite']) {
        return new WP_Error(
            'twmcd_destination_subsite_required',
            __('Select the destination subsite in WP Migrate before comparing sites.', 'tn-wp-migrate-code-diff')
        );
    }

    return true;
}

function twmcd_get_comparison_context($token)
{
    if (empty($token)) {
        return false;
    }

    return get_site_transient(twmcd_context_transient_key($token));
}

function twmcd_create_connection_signature($data, $key)
{
    unset($data['sig']);
    $data = array_map('twmcd_sanitize_signature_value', $data);
    ksort($data);

    return base64_encode(hash_hmac('sha1', implode('', $data), $key, true));
}

function twmcd_sanitize_signature_value($value)
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}

function twmcd_unscramble_response($response_body)
{
    $response_body = trim((string) $response_body, "\xEF\xBB\xBF\t\n\r\0\x0B");
    $json_value = json_decode($response_body, true);

    if (is_string($json_value)) {
        $response_body = $json_value;
    }

    if (0 !== strpos($response_body, 'WPMDB-SCRAMBLED')) {
        return $response_body;
    }

    $response_body = str_replace(
        array('WPMDB-SCRAMBLED', '%#047%', '%#092%'),
        array('', '/', '\\'),
        $response_body
    );

    return str_rot13($response_body);
}

function twmcd_request_remote_site_data($remote_url, $secret_key, $intent)
{
    $version = twmcd_wp_migrate_version();

    if (false === $version) {
        return new WP_Error(
            'twmcd_wp_migrate_missing',
            __('WP Migrate Pro must be installed and active before a site comparison can run.', 'tn-wp-migrate-code-diff')
        );
    }

    $request_data = array(
        'action'  => 'wpmdb_verify_connection_to_remote_site',
        'intent'  => $intent,
        'referer' => preg_replace('#^https?://#i', '', untrailingslashit(home_url())),
        'version' => $version,
    );
    $request_data['sig'] = twmcd_create_connection_signature($request_data, $secret_key);

    $response = wp_safe_remote_post(
        $remote_url . '/wp-admin/admin-ajax.php',
        array(
            'timeout'     => 45,
            'redirection' => 3,
            'body'        => $request_data,
        )
    );

    if (is_wp_error($response)) {
        return new WP_Error('twmcd_remote_request', $response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code < 200 || $response_code > 399) {
        return new WP_Error(
            'twmcd_remote_http',
            sprintf(
                __('The remote site returned HTTP %d.', 'tn-wp-migrate-code-diff'),
                (int) $response_code
            )
        );
    }

    $response_body = twmcd_unscramble_response(wp_remote_retrieve_body($response));
    $decoded = json_decode($response_body, true);

    if (is_string($decoded)) {
        $decoded = json_decode(twmcd_unscramble_response($decoded), true);
    }

    if (!is_array($decoded)) {
        return new WP_Error(
            'twmcd_remote_response',
            __('The remote response could not be read. Confirm that both sites run the same WP Migrate Pro version.', 'tn-wp-migrate-code-diff')
        );
    }

    if (isset($decoded['success']) && false === $decoded['success']) {
        $error_message = isset($decoded['data']) && is_scalar($decoded['data'])
            ? wp_strip_all_tags((string) $decoded['data'])
            : __('The WP Migrate connection was rejected by the remote site.', 'tn-wp-migrate-code-diff');

        return new WP_Error('twmcd_remote_rejected', $error_message);
    }

    if (!empty($decoded['error'])) {
        return new WP_Error(
            'twmcd_remote_error',
            __('The remote WP Migrate connection returned an error.', 'tn-wp-migrate-code-diff')
        );
    }

    return $decoded;
}

function twmcd_request_remote_inventory($remote_url, $secret_key, $intent)
{
    $decoded = twmcd_request_remote_site_data($remote_url, $secret_key, $intent);
    if (is_wp_error($decoded)) {
        return $decoded;
    }

    $site_details = isset($decoded['site_details']) && is_array($decoded['site_details'])
        ? $decoded['site_details']
        : array();

    if (!array_key_exists('plugins', $site_details) || !array_key_exists('themes', $site_details)) {
        return new WP_Error(
            'twmcd_inventory_unavailable',
            __('The remote site did not return its code package inventory. Confirm that the WP Migrate Themes & Plugins capability is available on both sites.', 'tn-wp-migrate-code-diff')
        );
    }

    return twmcd_normalize_remote_inventory($decoded);
}

function twmcd_normalize_remote_inventory($remote_data)
{
    $site_details = isset($remote_data['site_details']) && is_array($remote_data['site_details'])
        ? $remote_data['site_details']
        : array();

    $is_multisite = isset($site_details['is_multisite']) && twmcd_sanitize_boolean($site_details['is_multisite']);

    return array(
        'url'       => isset($remote_data['url']) ? esc_url_raw($remote_data['url']) : '',
        'is_multisite' => $is_multisite,
        'plugins'   => twmcd_normalize_remote_packages(isset($site_details['plugins']) ? $site_details['plugins'] : array(), $is_multisite, false),
        'themes'    => twmcd_normalize_remote_packages(isset($site_details['themes']) ? $site_details['themes'] : array(), $is_multisite, false),
        'muplugins' => twmcd_normalize_remote_packages(isset($site_details['muplugins']) ? $site_details['muplugins'] : array(), $is_multisite, true),
    );
}

function twmcd_normalize_remote_packages($remote_packages, $is_multisite, $is_mu_plugin)
{
    $packages = array();

    if (!is_array($remote_packages)) {
        return $packages;
    }

    foreach ($remote_packages as $package_key => $package_rows) {
        $package = isset($package_rows[0]) && is_array($package_rows[0])
            ? $package_rows[0]
            : (is_array($package_rows) ? $package_rows : array());

        $packages[(string) $package_key] = array(
            'key'     => (string) $package_key,
            'name'    => isset($package['name']) ? wp_strip_all_tags((string) $package['name']) : basename((string) $package_key),
            'version' => isset($package['version']) ? sanitize_text_field((string) $package['version']) : '',
            'path'    => isset($package['path']) ? (string) $package['path'] : (string) $package_key,
            'activation' => $is_mu_plugin
                ? 'always_active'
                : twmcd_remote_activation_state(isset($package['active']) ? $package['active'] : null, $is_multisite),
        );
    }

    return $packages;
}

function twmcd_remote_activation_state($active, $is_multisite)
{
    if (null === $active) {
        return 'unknown';
    }

    if (!twmcd_sanitize_boolean($active)) {
        return 'inactive';
    }

    return $is_multisite ? 'active_in_network' : 'site_active';
}

function twmcd_local_inventory($context = array())
{
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    return array(
        'url'       => esc_url_raw(home_url()),
        'is_multisite' => is_multisite(),
        'plugins'   => twmcd_local_plugins($context),
        'themes'    => twmcd_local_themes($context),
        'muplugins' => twmcd_local_mu_plugins(),
    );
}

function twmcd_local_plugins($context = array())
{
    $packages = array();

    foreach (get_plugins() as $plugin_key => $plugin_data) {
        if (0 === strpos($plugin_key, 'wp-migrate-db')) {
            continue;
        }

        $plugin_directory = dirname($plugin_key);
        $package_path = '.' === $plugin_directory
            ? WP_PLUGIN_DIR . '/' . $plugin_key
            : WP_PLUGIN_DIR . '/' . $plugin_directory;

        $packages[$plugin_key] = array(
            'key'     => $plugin_key,
            'name'    => isset($plugin_data['Name']) ? $plugin_data['Name'] : basename($plugin_key),
            'version' => isset($plugin_data['Version']) ? (string) $plugin_data['Version'] : '',
            'path'    => $package_path,
            'activation' => twmcd_local_plugin_activation_state($plugin_key, $context),
        );
    }

    return $packages;
}

function twmcd_local_themes($context = array())
{
    $packages = array();

    foreach (wp_get_themes() as $theme_key => $theme) {
        $packages[$theme_key] = array(
            'key'     => $theme_key,
            'name'    => $theme->get('Name'),
            'version' => (string) $theme->get('Version'),
            'path'    => get_theme_root($theme_key) . '/' . $theme_key,
            'activation' => twmcd_local_theme_activation_state($theme_key, $context),
        );
    }

    return $packages;
}

function twmcd_local_mu_plugins()
{
    $packages = array();

    foreach (get_mu_plugins() as $plugin_key => $plugin_data) {
        if ('wp-migrate-db-pro-compatibility.php' === $plugin_key) {
            continue;
        }

        $packages[$plugin_key] = array(
            'key'     => $plugin_key,
            'name'    => isset($plugin_data['Name']) ? $plugin_data['Name'] : basename($plugin_key),
            'version' => isset($plugin_data['Version']) ? (string) $plugin_data['Version'] : '',
            'path'    => WPMU_PLUGIN_DIR . '/' . $plugin_key,
            'activation' => 'always_active',
        );
    }

    return $packages;
}


function twmcd_local_scope_blog_id($context)
{
    if (!is_multisite() || empty($context['migration']['local_is_multisite'])) {
        return get_current_blog_id();
    }

    $local_is_source = !empty($context['migration']['local_source']);
    $two_multisites = !empty($context['migration']['two_multisites']);
    $key = $two_multisites && !$local_is_source ? 'destination_subsite' : 'selected_subsite';

    return !empty($context['multisite_tools'][$key])
        ? absint($context['multisite_tools'][$key])
        : 0;
}

function twmcd_local_plugin_activation_state($plugin_key, $context)
{
    if (!is_multisite()) {
        return is_plugin_active($plugin_key) ? 'site_active' : 'inactive';
    }

    if (is_plugin_active_for_network($plugin_key)) {
        return 'network_active';
    }

    $blog_id = twmcd_local_scope_blog_id($context);
    if ($blog_id) {
        $active_plugins = (array) get_blog_option($blog_id, 'active_plugins', array());
        return in_array($plugin_key, $active_plugins, true) ? 'site_active' : 'inactive';
    }

    foreach (get_sites(array('fields' => 'ids')) as $site_id) {
        if (in_array($plugin_key, (array) get_blog_option($site_id, 'active_plugins', array()), true)) {
            return 'active_in_network';
        }
    }

    return 'inactive';
}

function twmcd_local_theme_activation_state($theme_key, $context)
{
    if (!is_multisite()) {
        return get_option('stylesheet') === $theme_key || get_option('template') === $theme_key
            ? 'site_active'
            : 'inactive';
    }

    $blog_id = twmcd_local_scope_blog_id($context);
    if ($blog_id) {
        return get_blog_option($blog_id, 'stylesheet') === $theme_key
            || get_blog_option($blog_id, 'template') === $theme_key
                ? 'site_active'
                : 'inactive';
    }

    foreach (get_sites(array('fields' => 'ids')) as $site_id) {
        if (get_blog_option($site_id, 'stylesheet') === $theme_key || get_blog_option($site_id, 'template') === $theme_key) {
            return 'active_in_network';
        }
    }

    return 'inactive';
}
