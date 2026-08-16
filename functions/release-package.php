<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_handle_create_release_package()
{
    check_admin_referer('twmcd_create_release_package', 'twmcd_release_nonce');

    if (!current_user_can(twmcd_admin_capability())) {
        wp_die(esc_html__('You do not have permission to create release packages.', 'tn-wp-migrate-code-diff'), 403);
    }

    twmcd_prepare_long_running_operation();

    $release_name = isset($_POST['release_name'])
        ? sanitize_text_field(wp_unslash($_POST['release_name']))
        : twmcd_default_profile_name();
    $comparison_token = isset($_POST['comparison_token'])
        ? sanitize_key(wp_unslash($_POST['comparison_token']))
        : '';
    $selection_json = isset($_POST['selection']) ? wp_unslash($_POST['selection']) : '';
    $selection = json_decode($selection_json, true);
    $comparison_state = twmcd_get_comparison_state($comparison_token);
    $selection = twmcd_validate_profile_selection($comparison_token, $selection);

    if ('' === $release_name || is_wp_error($selection) || !is_array($comparison_state)) {
        $message = is_wp_error($selection)
            ? $selection->get_error_message()
            : __('The comparison has expired. Run it again before creating a release package.', 'tn-wp-migrate-code-diff');
        wp_die(esc_html($message), esc_html__('Release package could not be created', 'tn-wp-migrate-code-diff'), array('response' => 400));
    }

    if (empty($comparison_state['context']['intent']) || 'push' !== $comparison_state['context']['intent']) {
        wp_die(
            esc_html__('Release packages can only be created when this site is the source of a Push comparison.', 'tn-wp-migrate-code-diff'),
            esc_html__('Release package could not be created', 'tn-wp-migrate-code-diff'),
            array('response' => 400)
        );
    }

    $release_package = twmcd_create_release_package($release_name, $comparison_state, $selection);
    if (is_wp_error($release_package)) {
        wp_die(
            esc_html($release_package->get_error_message()),
            esc_html__('Release package could not be created', 'tn-wp-migrate-code-diff'),
            array('response' => 500)
        );
    }

    twmcd_send_release_package($release_package['path'], $release_package['filename']);
}

function twmcd_ajax_prepare_release_package()
{
    twmcd_verify_ajax_request();
    twmcd_prepare_long_running_operation();

    $release_name = isset($_POST['release_name'])
        ? sanitize_text_field(wp_unslash($_POST['release_name']))
        : twmcd_default_profile_name();
    $comparison_token = isset($_POST['comparison_token'])
        ? sanitize_key(wp_unslash($_POST['comparison_token']))
        : '';
    $selection_json = isset($_POST['selection']) ? wp_unslash($_POST['selection']) : '';
    $selection = json_decode($selection_json, true);
    $comparison_state = twmcd_get_comparison_state($comparison_token);
    $selection = twmcd_validate_profile_selection($comparison_token, $selection);

    if ('' === $release_name || is_wp_error($selection) || !is_array($comparison_state)) {
        $message = is_wp_error($selection)
            ? $selection->get_error_message()
            : __('The comparison has expired. Refresh it before creating a release package.', 'tn-wp-migrate-code-diff');
        wp_send_json_error(array('message' => $message), 400);
    }

    if (empty($comparison_state['context']['intent']) || 'push' !== $comparison_state['context']['intent']) {
        wp_send_json_error(
            array('message' => __('Release packages can only be created from a Push comparison.', 'tn-wp-migrate-code-diff')),
            400
        );
    }

    $release_package = twmcd_create_release_package($release_name, $comparison_state, $selection);
    if (is_wp_error($release_package)) {
        wp_send_json_error(array('message' => $release_package->get_error_message()), 500);
    }

    $download_url = twmcd_prepare_release_download($release_package);
    if (is_wp_error($download_url)) {
        wp_send_json_error(array('message' => $download_url->get_error_message()), 500);
    }

    wp_send_json_success(
        array('download_url' => $download_url)
    );
}

function twmcd_prepare_release_download($release_package)
{
    if (!is_array($release_package)
        || empty($release_package['path'])
        || empty($release_package['filename'])
        || !is_file($release_package['path'])) {
        return new WP_Error(
            'twmcd_invalid_prepared_download',
            __('The prepared release package could not be found.', 'tn-wp-migrate-code-diff')
        );
    }

    $download_token = wp_generate_password(32, false, false);
    set_site_transient(
        twmcd_release_download_transient_key($download_token),
        $release_package,
        10 * MINUTE_IN_SECONDS
    );

    return add_query_arg(
        array(
            'action' => 'twmcd_download_release_package',
            'token'  => $download_token,
        ),
        admin_url('admin-post.php')
    );
}

function twmcd_release_download_transient_key($token)
{
    return 'twmcd_release_download_' . get_current_user_id() . '_' . sanitize_key($token);
}

function twmcd_handle_download_release_package()
{
    $download_token = isset($_GET['token']) ? sanitize_key(wp_unslash($_GET['token'])) : '';

    if (!current_user_can(twmcd_admin_capability())) {
        wp_die(esc_html__('You do not have permission to download release packages.', 'tn-wp-migrate-code-diff'), 403);
    }

    $transient_key = twmcd_release_download_transient_key($download_token);
    $release_package = get_site_transient($transient_key);
    delete_site_transient($transient_key);

    if (!is_array($release_package) || empty($release_package['path']) || empty($release_package['filename'])) {
        wp_die(
            esc_html__('The prepared release download has expired. Create the release package again.', 'tn-wp-migrate-code-diff'),
            esc_html__('Release package unavailable', 'tn-wp-migrate-code-diff'),
            array('response' => 404)
        );
    }

    twmcd_send_release_package($release_package['path'], $release_package['filename']);
}

function twmcd_create_release_package($release_name, $comparison_state, $selection)
{
    if (!class_exists('ZipArchive')) {
        return new WP_Error(
            'twmcd_zip_unavailable',
            __('The PHP Zip extension is required to create a release package.', 'tn-wp-migrate-code-diff')
        );
    }

    $release_operations = twmcd_selected_release_operations($comparison_state, $selection);
    if (is_wp_error($release_operations)) {
        return $release_operations;
    }
    if (!$release_operations['packages'] && !$release_operations['remove_packages']) {
        return new WP_Error(
            'twmcd_empty_release',
            __('Select at least one package operation before creating the release package.', 'tn-wp-migrate-code-diff')
        );
    }

    $zip_path = twmcd_release_tempnam('twmcd-release.zip');
    if (!$zip_path) {
        return new WP_Error('twmcd_temp_file', __('WordPress could not create a temporary release file.', 'tn-wp-migrate-code-diff'));
    }

    $zip = new ZipArchive();
    if (true !== $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        @unlink($zip_path);
        return new WP_Error('twmcd_zip_open', __('The release ZIP could not be opened for writing.', 'tn-wp-migrate-code-diff'));
    }

    $manifest_packages = array();
    $manifest_files = array();
    foreach ($release_operations['packages'] as $package_row) {
        $added_files = twmcd_add_release_path_to_zip(
            $zip,
            $package_row['source_path'],
            $package_row['archive_path']
        );
        if (is_wp_error($added_files)) {
            $zip->close();
            @unlink($zip_path);
            return $added_files;
        }

        $manifest_files = array_merge($manifest_files, $added_files);
        $manifest_packages[] = array(
            'type'         => $package_row['type'],
            'key'          => $package_row['key'],
            'name'         => $package_row['name'],
            'from_version' => $package_row['destination_version'],
            'version'      => $package_row['version'],
            'archive_path' => $package_row['archive_path'],
            'destination'  => $package_row['destination'],
        );
    }

    $manifest = array(
        'format'      => 'tn-code-release/v1',
        'release_id'  => $release_name,
        'created_at'  => gmdate('c'),
        'source_url'  => home_url(),
        'destination_url' => !empty($comparison_state['context']['connection']['url'])
            ? $comparison_state['context']['connection']['url']
            : '',
        'created_by'  => twmcd_current_release_user(),
        'generator'   => array(
            'plugin'  => 'tn-wp-migrate-code-diff',
            'version' => TWMCD_VERSION,
        ),
        'packages'    => $manifest_packages,
        'remove_packages' => $release_operations['remove_packages'],
        'files'       => $manifest_files,
    );

    if (!$zip->addFromString('manifest.json', wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
        $zip->close();
        @unlink($zip_path);
        return new WP_Error('twmcd_manifest_write', __('The release manifest could not be written.', 'tn-wp-migrate-code-diff'));
    }
    $zip->close();

    return array(
        'path'     => $zip_path,
        'filename' => sanitize_file_name($release_name) . '.zip',
    );
}

function twmcd_release_tempnam($filename)
{
    if (!function_exists('wp_tempnam')) {
        $wordpress_file_api = ABSPATH . 'wp-admin/includes/file.php';
        if (is_readable($wordpress_file_api)) {
            require_once $wordpress_file_api;
        }
    }

    return function_exists('wp_tempnam') ? wp_tempnam($filename) : false;
}

function twmcd_selected_release_operations($comparison_state, $selection)
{
    $comparison_groups = isset($comparison_state['packages']) && is_array($comparison_state['packages'])
        ? $comparison_state['packages']
        : array();
    $selected_rows = array();
    $remove_packages = array();
    $seen_paths = array();

    foreach (array('plugins', 'themes', 'muplugins') as $group_key) {
        $selected_paths = isset($selection[$group_key]) ? $selection[$group_key] : array();
        foreach ((array) (isset($comparison_groups[$group_key]) ? $comparison_groups[$group_key] : array()) as $package) {
            if (empty($package['selection']) || !in_array($package['selection'], $selected_paths, true)) {
                continue;
            }

            if ('remove' === (isset($package['selection_operation']) ? $package['selection_operation'] : '')) {
                $removal = twmcd_release_removal_operation($group_key, $package);
                if (is_wp_error($removal)) {
                    return $removal;
                }
                if (isset($seen_paths[$removal['destination']])) {
                    continue;
                }
                $seen_paths[$removal['destination']] = true;
                $remove_packages[] = $removal;
                continue;
            }

            $release_path = twmcd_release_source_and_destination($group_key, $package);
            if (is_wp_error($release_path)) {
                return $release_path;
            }
            if (isset($seen_paths[$release_path['destination']])) {
                continue;
            }

            $seen_paths[$release_path['destination']] = true;
            $selected_rows[] = array_merge(
                $release_path,
                array(
                    'type'    => $group_key,
                    'key'     => sanitize_text_field($package['key']),
                    'name'    => sanitize_text_field($package['name']),
                    'version' => sanitize_text_field($package['source_version']),
                    'destination_version' => sanitize_text_field($package['destination_version']),
                )
            );
        }
    }

    return array(
        'packages'        => $selected_rows,
        'remove_packages' => $remove_packages,
    );
}

function twmcd_release_removal_operation($group_key, $package)
{
    $package_key = trim(str_replace('\\', '/', (string) $package['key']), '/');
    $segments = explode('/', $package_key);
    if ('' === $package_key || array_intersect($segments, array('', '.', '..'))) {
        return new WP_Error('twmcd_invalid_remove_target', __('A selected removal has an invalid destination.', 'tn-wp-migrate-code-diff'));
    }

    if ('plugins' === $group_key || 'muplugins' === $group_key) {
        $package_directory = dirname($package_key);
        $relative_path = '.' === $package_directory ? $package_key : $package_directory;
        $root = 'plugins' === $group_key ? 'plugins' : 'mu-plugins';
    } elseif ('themes' === $group_key) {
        $relative_path = $package_key;
        $root = 'themes';
    } else {
        return new WP_Error('twmcd_invalid_remove_type', __('A selected removal has an invalid package type.', 'tn-wp-migrate-code-diff'));
    }

    return array(
        'type'        => $group_key,
        'key'         => sanitize_text_field((string) $package['key']),
        'name'        => sanitize_text_field((string) $package['name']),
        'version'     => sanitize_text_field((string) $package['destination_version']),
        'destination' => $root . '/' . $relative_path,
    );
}

function twmcd_release_source_and_destination($group_key, $package)
{
    $selected_path = (string) $package['selection'];
    $source_path = $selected_path;
    $root_path = '';
    $destination_root = '';

    if ('plugins' === $group_key) {
        $root_path = WP_PLUGIN_DIR;
        $destination_root = 'plugins';
    } elseif ('themes' === $group_key) {
        $root_path = get_theme_root((string) $package['key']);
        $destination_root = 'themes';
    } elseif ('muplugins' === $group_key) {
        $root_path = WPMU_PLUGIN_DIR;
        $destination_root = 'mu-plugins';
        $mu_directory = dirname((string) $package['key']);
        if ('.' !== $mu_directory) {
            $source_path = trailingslashit(WPMU_PLUGIN_DIR) . $mu_directory;
        }
    }

    $real_root = realpath($root_path);
    $real_source = realpath($source_path);
    if (!$real_root || !$real_source || !twmcd_path_is_within($real_source, $real_root)) {
        return new WP_Error(
            'twmcd_invalid_release_path',
            sprintf(__('The selected package path for %s is unavailable or outside WordPress content.', 'tn-wp-migrate-code-diff'), $package['name'])
        );
    }

    $relative_path = ltrim(substr($real_source, strlen($real_root)), DIRECTORY_SEPARATOR);
    if ('' === $relative_path || false !== strpos(str_replace('\\', '/', $relative_path), '../')) {
        return new WP_Error('twmcd_invalid_release_target', __('A selected package has an invalid destination path.', 'tn-wp-migrate-code-diff'));
    }

    $destination = $destination_root . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative_path);

    return array(
        'source_path' => $real_source,
        'archive_path' => 'payload/' . $destination,
        'destination' => $destination,
    );
}

function twmcd_path_is_within($path, $root)
{
    $path = wp_normalize_path($path);
    $root = untrailingslashit(wp_normalize_path($root));

    return $path === $root || 0 === strpos($path, $root . '/');
}

function twmcd_add_release_path_to_zip($zip, $source_path, $archive_path, $apply_exclusions = true)
{
    $files = array();
    $source_path = wp_normalize_path($source_path);
    $archive_path = trim(str_replace('\\', '/', $archive_path), '/');

    if (is_file($source_path)) {
        if (!$zip->addFile($source_path, $archive_path)) {
            return new WP_Error('twmcd_zip_file', __('A selected package file could not be added to the release ZIP.', 'tn-wp-migrate-code-diff'));
        }
        $files[$archive_path] = hash_file('sha256', $source_path);
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) {
            continue;
        }

        $relative_path = ltrim(substr(wp_normalize_path($file->getPathname()), strlen($source_path)), '/');
        if ($apply_exclusions && twmcd_release_path_is_excluded($relative_path)) {
            continue;
        }

        $zip_path = $archive_path . '/' . $relative_path;
        if (!$zip->addFile($file->getPathname(), $zip_path)) {
            return new WP_Error('twmcd_zip_file', __('A selected package file could not be added to the release ZIP.', 'tn-wp-migrate-code-diff'));
        }
        $files[$zip_path] = hash_file('sha256', $file->getPathname());
    }

    return $files;
}

function twmcd_release_path_is_excluded($relative_path)
{
    $parts = explode('/', str_replace('\\', '/', $relative_path));

    return (bool) array_intersect($parts, array('.git', 'node_modules', '.DS_Store'));
}

function twmcd_send_release_package($zip_path, $filename)
{
    if (!is_file($zip_path)) {
        wp_die(esc_html__('The generated release package could not be found.', 'tn-wp-migrate-code-diff'));
    }

    $buffer_level = ob_get_level();
    while ($buffer_level > 0) {
        @ob_end_clean();
        $buffer_level--;
    }
    nocache_headers();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Content-Length: ' . filesize($zip_path));
    header('X-Content-Type-Options: nosniff');
    readfile($zip_path);
    @unlink($zip_path);
    exit;
}
