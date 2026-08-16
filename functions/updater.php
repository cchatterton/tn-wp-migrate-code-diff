<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_github_repository_url()
{
    return 'https://github.com/' . TWMCD_GITHUB_OWNER . '/' . TWMCD_GITHUB_REPOSITORY;
}

function twmcd_clear_github_update_cache()
{
    delete_site_transient(TWMCD_GITHUB_RELEASE_TRANSIENT);
    delete_site_transient(TWMCD_GITHUB_ERROR_TRANSIENT);
}

function twmcd_is_forced_update_check()
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

function twmcd_get_github_release($force = false)
{
    if ($force) {
        twmcd_clear_github_update_cache();
    } else {
        $cached_release = get_site_transient(TWMCD_GITHUB_RELEASE_TRANSIENT);
        if (is_array($cached_release) && !empty($cached_release['version']) && !empty($cached_release['package'])) {
            return $cached_release;
        }
    }

    $api_url = 'https://api.github.com/repos/' . TWMCD_GITHUB_OWNER . '/' . TWMCD_GITHUB_REPOSITORY . '/releases/latest';
    $response = wp_remote_get(
        $api_url,
        array(
            'timeout' => 10,
            'headers' => array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'TN-WP-Migrate-Code-Diff/' . TWMCD_VERSION,
            ),
        )
    );

    if (is_wp_error($response)) {
        twmcd_store_github_update_error('wp_error', 0, $response->get_error_message(), '');
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    if (200 !== $response_code) {
        twmcd_store_github_update_error(
            'http_error',
            $response_code,
            wp_remote_retrieve_response_message($response),
            substr((string) $response_body, 0, 500)
        );
        return false;
    }

    $release_data = json_decode($response_body, true);
    if (!is_array($release_data) || empty($release_data['tag_name'])) {
        twmcd_store_github_update_error('json_error', $response_code, __('The latest GitHub release could not be parsed.', 'tn-wp-migrate-code-diff'), '');
        return false;
    }

    $version = ltrim(sanitize_text_field($release_data['tag_name']), 'vV');
    $package = twmcd_find_github_release_asset($release_data);
    if ('' === $version || !$package) {
        twmcd_store_github_update_error('release_error', $response_code, __('The latest GitHub release is missing a valid version or plugin ZIP asset.', 'tn-wp-migrate-code-diff'), '');
        return false;
    }

    $release = array(
        'version'     => $version,
        'tag'         => sanitize_text_field($release_data['tag_name']),
        'release_url' => isset($release_data['html_url']) ? esc_url_raw($release_data['html_url']) : twmcd_github_repository_url() . '/releases/latest',
        'package'     => $package,
        'body'        => isset($release_data['body']) ? sanitize_textarea_field($release_data['body']) : '',
    );
    $cache_duration = version_compare($version, TWMCD_VERSION, '>') ? 6 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS;

    set_site_transient(TWMCD_GITHUB_RELEASE_TRANSIENT, $release, $cache_duration);
    delete_site_transient(TWMCD_GITHUB_ERROR_TRANSIENT);

    return $release;
}

function twmcd_find_github_release_asset($release_data)
{
    if (empty($release_data['assets']) || !is_array($release_data['assets'])) {
        return false;
    }

    foreach ($release_data['assets'] as $asset) {
        if (is_array($asset)
            && isset($asset['name'], $asset['browser_download_url'])
            && TWMCD_GITHUB_ASSET === $asset['name']) {
            return esc_url_raw($asset['browser_download_url']);
        }
    }

    return false;
}

function twmcd_store_github_update_error($type, $code, $message, $body)
{
    delete_site_transient(TWMCD_GITHUB_RELEASE_TRANSIENT);
    set_site_transient(
        TWMCD_GITHUB_ERROR_TRANSIENT,
        array(
            'type'       => sanitize_key($type),
            'code'       => absint($code),
            'message'    => sanitize_text_field($message),
            'body'       => sanitize_textarea_field($body),
            'checked_at' => time(),
        ),
        10 * MINUTE_IN_SECONDS
    );
}

function twmcd_add_github_update_data($transient)
{
    if (!is_object($transient)) {
        return $transient;
    }

    $plugin_file = plugin_basename(TWMCD_PLUGIN_FILE);
    $transient->response = isset($transient->response) && is_array($transient->response) ? $transient->response : array();
    $transient->no_update = isset($transient->no_update) && is_array($transient->no_update) ? $transient->no_update : array();
    unset($transient->response[$plugin_file], $transient->no_update[$plugin_file]);

    $release = twmcd_get_github_release(twmcd_is_forced_update_check());
    if (!$release || !version_compare($release['version'], TWMCD_VERSION, '>')) {
        return $transient;
    }

    $transient->response[$plugin_file] = (object) array(
        'id'           => twmcd_github_repository_url(),
        'slug'         => TWMCD_PAGE_SLUG,
        'plugin'       => $plugin_file,
        'new_version'  => $release['version'],
        'url'          => $release['release_url'],
        'package'      => $release['package'],
        'requires'     => '5.2',
        'requires_php' => '5.6',
    );

    return $transient;
}

function twmcd_github_plugin_information($result, $action, $args)
{
    if ('plugin_information' !== $action || !is_object($args) || empty($args->slug) || TWMCD_PAGE_SLUG !== $args->slug) {
        return $result;
    }

    $release = twmcd_get_github_release(false);
    if (!$release) {
        return $result;
    }

    return (object) array(
        'name'          => 'WP Migrate - Release Management',
        'slug'          => TWMCD_PAGE_SLUG,
        'version'       => $release['version'],
        'author'        => '<a href="https://techn.com.au">Techn</a>',
        'homepage'      => twmcd_github_repository_url(),
        'download_link' => $release['package'],
        'requires'      => '5.2',
        'requires_php'  => '5.6',
        'sections'      => array(
            'description' => __('Compares top-level code packages through WP Migrate and creates selective code-only migration profiles.', 'tn-wp-migrate-code-diff'),
            'changelog'   => nl2br(esc_html($release['body'])),
        ),
    );
}

function twmcd_add_github_plugin_row_meta($links, $plugin_file)
{
    if (plugin_basename(TWMCD_PLUGIN_FILE) !== $plugin_file || !current_user_can('update_plugins')) {
        return $links;
    }

    $plugins_url = is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php');
    $check_url = wp_nonce_url(add_query_arg('twmcd_check_updates', '1', $plugins_url), 'twmcd_check_updates');
    $links[] = '<a href="' . esc_url(twmcd_github_repository_url()) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('GitHub', 'tn-wp-migrate-code-diff') . '</a>';
    $links[] = '<a href="' . esc_url($check_url) . '">' . esc_html__('Check for updates', 'tn-wp-migrate-code-diff') . '</a>';

    return $links;
}

function twmcd_handle_manual_update_check()
{
    if (!isset($_GET['twmcd_check_updates'])) {
        return;
    }

    check_admin_referer('twmcd_check_updates');
    if (!current_user_can('update_plugins')) {
        wp_die(esc_html__('You do not have permission to check for plugin updates.', 'tn-wp-migrate-code-diff'));
    }

    twmcd_clear_github_update_cache();
    delete_site_transient('update_plugins');
    wp_update_plugins();

    $plugins_url = is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php');
    wp_safe_redirect(add_query_arg('twmcd_update_check', 'complete', $plugins_url));
    exit;
}

function twmcd_clear_github_cache_after_upgrade($upgrader, $options)
{
    if (!is_array($options)
        || !isset($options['type'], $options['action'])
        || 'plugin' !== $options['type']
        || 'update' !== $options['action']) {
        return;
    }

    $updated_plugins = isset($options['plugins']) && is_array($options['plugins']) ? $options['plugins'] : array();
    if (in_array(plugin_basename(TWMCD_PLUGIN_FILE), $updated_plugins, true)) {
        twmcd_clear_github_update_cache();
    }
}
