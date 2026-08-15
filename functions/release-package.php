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

function twmcd_create_release_package($release_name, $comparison_state, $selection)
{
    if (!class_exists('ZipArchive')) {
        return new WP_Error(
            'twmcd_zip_unavailable',
            __('The PHP Zip extension is required to create a release package.', 'tn-wp-migrate-code-diff')
        );
    }

    $package_rows = twmcd_selected_release_rows($comparison_state, $selection);
    if (is_wp_error($package_rows)) {
        return $package_rows;
    }
    if (!$package_rows) {
        return new WP_Error(
            'twmcd_empty_release',
            __('Select at least one local source package before creating the release package.', 'tn-wp-migrate-code-diff')
        );
    }

    $zip_path = wp_tempnam('twmcd-release.zip');
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
    foreach ($package_rows as $package_row) {
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
        'generator'   => array(
            'plugin'  => 'tn-wp-migrate-code-diff',
            'version' => TWMCD_VERSION,
        ),
        'packages'    => $manifest_packages,
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

function twmcd_selected_release_rows($comparison_state, $selection)
{
    $comparison_groups = isset($comparison_state['packages']) && is_array($comparison_state['packages'])
        ? $comparison_state['packages']
        : array();
    $selected_rows = array();
    $seen_paths = array();

    foreach (array('plugins', 'themes', 'muplugins') as $group_key) {
        $selected_paths = isset($selection[$group_key]) ? $selection[$group_key] : array();
        foreach ((array) (isset($comparison_groups[$group_key]) ? $comparison_groups[$group_key] : array()) as $package) {
            if (empty($package['selection']) || !in_array($package['selection'], $selected_paths, true)) {
                continue;
            }

            $release_path = twmcd_release_source_and_destination($group_key, $package);
            if (is_wp_error($release_path)) {
                return $release_path;
            }
            if (isset($seen_paths[$release_path['archive_path']])) {
                continue;
            }

            $seen_paths[$release_path['archive_path']] = true;
            $selected_rows[] = array_merge(
                $release_path,
                array(
                    'type'    => $group_key,
                    'key'     => sanitize_text_field($package['key']),
                    'name'    => sanitize_text_field($package['name']),
                    'version' => sanitize_text_field($package['source_version']),
                )
            );
        }
    }

    return $selected_rows;
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

function twmcd_add_release_path_to_zip($zip, $source_path, $archive_path)
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
        if (twmcd_release_path_is_excluded($relative_path)) {
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
