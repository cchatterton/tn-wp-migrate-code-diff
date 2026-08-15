<?php

if (!defined('ABSPATH')) {
    exit;
}

function tncri_github_repository_url()
{
    return 'https://github.com/' . TNCRI_GITHUB_OWNER . '/' . TNCRI_GITHUB_REPOSITORY;
}

function tncri_clear_github_update_cache()
{
    delete_site_transient(TNCRI_GITHUB_RELEASE_TRANSIENT);
    delete_site_transient(TNCRI_GITHUB_ERROR_TRANSIENT);
}

function tncri_is_forced_update_check()
{
    static $forced_check_consumed = false;

    if ($forced_check_consumed || !current_user_can('update_plugins')) {
        return false;
    }

    $request = array_merge($_GET, $_POST);
    $action = isset($request['action']) ? sanitize_key(wp_unslash($request['action'])) : '';
    $forced = isset($request['force-check'])
        || in_array($action, array('update-selected', 'upgrade-plugin', 'do-plugin-upgrade'), true);
    if ($forced) {
        $forced_check_consumed = true;
    }

    return $forced;
}

function tncri_get_github_release($force = false)
{
    if ($force) {
        tncri_clear_github_update_cache();
    } else {
        $cached_release = get_site_transient(TNCRI_GITHUB_RELEASE_TRANSIENT);
        if (is_array($cached_release) && !empty($cached_release['version']) && !empty($cached_release['package'])) {
            return $cached_release;
        }
    }

    $response = wp_remote_get(
        'https://api.github.com/repos/' . TNCRI_GITHUB_OWNER . '/' . TNCRI_GITHUB_REPOSITORY . '/releases/latest',
        array(
            'timeout' => 10,
            'headers' => array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'TN-Code-Release-Installer/' . TNCRI_VERSION,
            ),
        )
    );

    if (is_wp_error($response)) {
        tncri_store_github_update_error('wp_error', 0, $response->get_error_message());
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    if (200 !== $response_code) {
        tncri_store_github_update_error('http_error', $response_code, wp_remote_retrieve_response_message($response));
        return false;
    }

    $release_data = json_decode($response_body, true);
    $package = tncri_find_github_release_asset($release_data);
    if (!is_array($release_data) || empty($release_data['tag_name']) || !$package) {
        tncri_store_github_update_error('release_error', $response_code, __('The latest GitHub release is missing the installer ZIP asset.', 'tn-code-release-installer'));
        return false;
    }

    $version = ltrim(sanitize_text_field($release_data['tag_name']), 'vV');
    $release = array(
        'version'     => $version,
        'release_url' => isset($release_data['html_url']) ? esc_url_raw($release_data['html_url']) : tncri_github_repository_url() . '/releases/latest',
        'package'     => $package,
        'body'        => isset($release_data['body']) ? sanitize_textarea_field($release_data['body']) : '',
    );
    $cache_duration = version_compare($version, TNCRI_VERSION, '>') ? 6 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS;
    set_site_transient(TNCRI_GITHUB_RELEASE_TRANSIENT, $release, $cache_duration);
    delete_site_transient(TNCRI_GITHUB_ERROR_TRANSIENT);

    return $release;
}

function tncri_find_github_release_asset($release_data)
{
    if (empty($release_data['assets']) || !is_array($release_data['assets'])) {
        return false;
    }

    foreach ($release_data['assets'] as $asset) {
        if (is_array($asset)
            && isset($asset['name'], $asset['browser_download_url'])
            && TNCRI_GITHUB_ASSET === $asset['name']) {
            return esc_url_raw($asset['browser_download_url']);
        }
    }

    return false;
}

function tncri_store_github_update_error($type, $code, $message)
{
    delete_site_transient(TNCRI_GITHUB_RELEASE_TRANSIENT);
    set_site_transient(
        TNCRI_GITHUB_ERROR_TRANSIENT,
        array(
            'type'       => sanitize_key($type),
            'code'       => absint($code),
            'message'    => sanitize_text_field($message),
            'checked_at' => time(),
        ),
        10 * MINUTE_IN_SECONDS
    );
}

function tncri_add_github_update_data($transient)
{
    if (!is_object($transient)) {
        return $transient;
    }

    $plugin_file = plugin_basename(TNCRI_PLUGIN_FILE);
    $transient->response = isset($transient->response) && is_array($transient->response) ? $transient->response : array();
    $transient->no_update = isset($transient->no_update) && is_array($transient->no_update) ? $transient->no_update : array();
    unset($transient->response[$plugin_file], $transient->no_update[$plugin_file]);

    $release = tncri_get_github_release(tncri_is_forced_update_check());
    if (!$release || !version_compare($release['version'], TNCRI_VERSION, '>')) {
        return $transient;
    }

    $transient->response[$plugin_file] = (object) array(
        'id'           => tncri_github_repository_url(),
        'slug'         => TNCRI_PAGE_SLUG,
        'plugin'       => $plugin_file,
        'new_version'  => $release['version'],
        'url'          => $release['release_url'],
        'package'      => $release['package'],
        'requires'     => '5.2',
        'requires_php' => '5.6',
    );

    return $transient;
}

function tncri_github_plugin_information($result, $action, $args)
{
    if ('plugin_information' !== $action || !is_object($args) || empty($args->slug) || TNCRI_PAGE_SLUG !== $args->slug) {
        return $result;
    }

    $release = tncri_get_github_release(false);
    if (!$release) {
        return $result;
    }

    return (object) array(
        'name'          => 'TN Code Release Installer',
        'slug'          => TNCRI_PAGE_SLUG,
        'version'       => $release['version'],
        'author'        => '<a href="https://techn.com.au">Techn</a>',
        'homepage'      => tncri_github_repository_url(),
        'download_link' => $release['package'],
        'requires'      => '5.2',
        'requires_php'  => '5.6',
        'sections'      => array(
            'description' => __('Installs validated manual code release packages created by TN WP Migrate Code Diff.', 'tn-code-release-installer'),
            'changelog'   => nl2br(esc_html($release['body'])),
        ),
    );
}

function tncri_add_github_plugin_row_meta($links, $plugin_file)
{
    if (plugin_basename(TNCRI_PLUGIN_FILE) !== $plugin_file || !current_user_can('update_plugins')) {
        return $links;
    }

    $plugins_url = is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php');
    $check_url = wp_nonce_url(add_query_arg('tncri_check_updates', '1', $plugins_url), 'tncri_check_updates');
    $links[] = '<a href="' . esc_url(tncri_github_repository_url()) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('GitHub', 'tn-code-release-installer') . '</a>';
    $links[] = '<a href="' . esc_url($check_url) . '">' . esc_html__('Check for updates', 'tn-code-release-installer') . '</a>';

    return $links;
}

function tncri_handle_manual_update_check()
{
    if (!isset($_GET['tncri_check_updates'])) {
        return;
    }

    check_admin_referer('tncri_check_updates');
    if (!current_user_can('update_plugins')) {
        wp_die(esc_html__('You do not have permission to check for plugin updates.', 'tn-code-release-installer'));
    }

    tncri_clear_github_update_cache();
    delete_site_transient('update_plugins');
    wp_update_plugins();
    $plugins_url = is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php');
    wp_safe_redirect(add_query_arg('tncri_update_check', 'complete', $plugins_url));
    exit;
}

function tncri_clear_github_cache_after_upgrade($upgrader, $options)
{
    if (!is_array($options)
        || empty($options['type'])
        || empty($options['action'])
        || 'plugin' !== $options['type']
        || 'update' !== $options['action']) {
        return;
    }

    $updated_plugins = isset($options['plugins']) && is_array($options['plugins']) ? $options['plugins'] : array();
    if (in_array(plugin_basename(TNCRI_PLUGIN_FILE), $updated_plugins, true)) {
        tncri_clear_github_update_cache();
    }
}
