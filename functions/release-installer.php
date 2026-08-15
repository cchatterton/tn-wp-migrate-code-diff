<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_install_release_archive($zip_path)
{
    if (!class_exists('ZipArchive')) {
        return new WP_Error('twmcd_zip_unavailable', __('The PHP Zip extension is required to install a release.', 'tn-wp-migrate-code-diff'));
    }
    if (!is_file($zip_path) || !is_readable($zip_path)) {
        return new WP_Error('twmcd_zip_missing', __('The uploaded release ZIP could not be read.', 'tn-wp-migrate-code-diff'));
    }

    $archive = new ZipArchive();
    if (true !== $archive->open($zip_path)) {
        return new WP_Error('twmcd_zip_invalid', __('The uploaded file is not a readable ZIP archive.', 'tn-wp-migrate-code-diff'));
    }

    $validation = twmcd_validate_release_archive($archive);
    $archive->close();
    if (is_wp_error($validation)) {
        return $validation;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    if (!WP_Filesystem()) {
        return new WP_Error(
            'twmcd_filesystem_unavailable',
            __('WordPress could not obtain direct filesystem access. Configure filesystem access and try again.', 'tn-wp-migrate-code-diff')
        );
    }

    $workspace = trailingslashit(WP_CONTENT_DIR) . 'upgrade/tn-code-release-' . wp_generate_uuid4();
    if (!wp_mkdir_p($workspace)) {
        return new WP_Error('twmcd_workspace_failed', __('WordPress could not create the temporary installation directory.', 'tn-wp-migrate-code-diff'));
    }

    $extracted = unzip_file($zip_path, $workspace);
    if (is_wp_error($extracted)) {
        twmcd_delete_workspace($workspace);
        return new WP_Error('twmcd_extract_failed', $extracted->get_error_message());
    }

    $checksum_validation = twmcd_validate_extracted_files($workspace, $validation['manifest']);
    if (is_wp_error($checksum_validation)) {
        twmcd_delete_workspace($workspace);
        return $checksum_validation;
    }

    $installation = twmcd_install_manifest_packages($workspace, $validation['manifest']);
    twmcd_delete_workspace($workspace);

    if (is_wp_error($installation)) {
        return $installation;
    }

    return array(
        'message'  => sprintf(
            __('Release “%1$s” installed successfully. %2$d code packages were updated.', 'tn-wp-migrate-code-diff'),
            $validation['manifest']['release_id'],
            count($installation)
        ),
        'packages' => $installation,
    );
}

function twmcd_validate_release_archive($archive)
{
    $maximum_entries = 50000;
    $maximum_uncompressed_size = min(2 * GB_IN_BYTES, max(100 * MB_IN_BYTES, wp_max_upload_size() * 20));
    $uncompressed_size = 0;
    $payload_files = array();

    if ($archive->numFiles < 2 || $archive->numFiles > $maximum_entries) {
        return new WP_Error('twmcd_archive_entries', __('The release contains an invalid number of files.', 'tn-wp-migrate-code-diff'));
    }

    for ($index = 0; $index < $archive->numFiles; $index++) {
        $entry = $archive->statIndex($index);
        if (!is_array($entry) || empty($entry['name']) || !twmcd_is_safe_archive_path($entry['name'])) {
            return new WP_Error('twmcd_archive_path', __('The release contains an unsafe archive path.', 'tn-wp-migrate-code-diff'));
        }
        if (twmcd_zip_entry_is_link($archive, $index)) {
            return new WP_Error('twmcd_archive_link', __('The release contains a symbolic link, which is not permitted.', 'tn-wp-migrate-code-diff'));
        }

        $uncompressed_size += isset($entry['size']) ? (int) $entry['size'] : 0;
        if ($uncompressed_size > $maximum_uncompressed_size) {
            return new WP_Error('twmcd_archive_size', __('The expanded release is larger than the permitted safety limit.', 'tn-wp-migrate-code-diff'));
        }

        $entry_name = rtrim($entry['name'], '/');
        if ('manifest.json' !== $entry_name && 0 !== strpos($entry_name, 'payload/')) {
            return new WP_Error('twmcd_archive_structure', __('The release contains files outside its manifest and payload.', 'tn-wp-migrate-code-diff'));
        }
        if ('/' !== substr($entry['name'], -1) && 0 === strpos($entry_name, 'payload/')) {
            $payload_files[] = $entry_name;
        }
    }

    $manifest_json = $archive->getFromName('manifest.json');
    $manifest = json_decode($manifest_json, true);
    $manifest_validation = twmcd_validate_manifest($manifest, $payload_files);
    if (is_wp_error($manifest_validation)) {
        return $manifest_validation;
    }

    return array('manifest' => $manifest);
}

function twmcd_validate_manifest($manifest, $payload_files)
{
    if (!is_array($manifest)
        || empty($manifest['format'])
        || 'tn-code-release/v1' !== $manifest['format']
        || empty($manifest['release_id'])
        || empty($manifest['packages'])
        || !is_array($manifest['packages'])
        || empty($manifest['files'])
        || !is_array($manifest['files'])) {
        return new WP_Error('twmcd_manifest_invalid', __('The release manifest is missing or invalid.', 'tn-wp-migrate-code-diff'));
    }

    $manifest_files = array_keys($manifest['files']);
    sort($manifest_files);
    sort($payload_files);
    if ($manifest_files !== $payload_files) {
        return new WP_Error('twmcd_manifest_files', __('The release payload does not match the files declared in its manifest.', 'tn-wp-migrate-code-diff'));
    }

    $destinations = array();
    foreach ($manifest['packages'] as $package) {
        $package_validation = twmcd_validate_manifest_package($package);
        if (is_wp_error($package_validation)) {
            return $package_validation;
        }
        if (isset($destinations[$package['destination']])) {
            return new WP_Error('twmcd_duplicate_target', __('The release declares the same destination more than once.', 'tn-wp-migrate-code-diff'));
        }
        $destinations[$package['destination']] = true;
    }

    foreach ($manifest['files'] as $file_path => $checksum) {
        if (!twmcd_is_safe_archive_path($file_path)
            || 0 !== strpos($file_path, 'payload/')
            || !preg_match('/^[a-f0-9]{64}$/', (string) $checksum)) {
            return new WP_Error('twmcd_manifest_checksum', __('The release contains an invalid file checksum declaration.', 'tn-wp-migrate-code-diff'));
        }
    }

    return true;
}

function twmcd_validate_manifest_package($package)
{
    if (!is_array($package)
        || empty($package['type'])
        || !in_array($package['type'], array('plugins', 'themes', 'muplugins'), true)
        || empty($package['name'])
        || empty($package['archive_path'])
        || empty($package['destination'])
        || 'payload/' . $package['destination'] !== $package['archive_path']
        || !twmcd_is_safe_archive_path($package['archive_path'])
        || !twmcd_is_safe_destination($package['type'], $package['destination'])) {
        return new WP_Error('twmcd_manifest_package', __('The release contains an invalid package destination.', 'tn-wp-migrate-code-diff'));
    }

    $installer_directory = dirname(plugin_basename(TWMCD_PLUGIN_FILE));
    if ('plugins' === $package['type'] && 'plugins/' . $installer_directory === $package['destination']) {
        return new WP_Error('twmcd_self_update', __('A manual release cannot replace the active release installer itself.', 'tn-wp-migrate-code-diff'));
    }

    return true;
}

function twmcd_is_safe_archive_path($path)
{
    if (!is_string($path) || '' === $path || false !== strpos($path, "\0") || false !== strpos($path, '\\')) {
        return false;
    }
    if ('/' === $path[0] || preg_match('/^[A-Za-z]:/', $path)) {
        return false;
    }

    foreach (explode('/', trim($path, '/')) as $segment) {
        if ('' === $segment || '.' === $segment || '..' === $segment) {
            return false;
        }
    }

    return true;
}

function twmcd_is_safe_destination($type, $destination)
{
    $roots = array(
        'plugins'  => 'plugins',
        'themes'   => 'themes',
        'muplugins' => 'mu-plugins',
    );
    if (!isset($roots[$type]) || !twmcd_is_safe_archive_path($destination)) {
        return false;
    }

    $segments = explode('/', $destination);
    if ($roots[$type] !== $segments[0] || count($segments) < 2) {
        return false;
    }

    if (in_array($type, array('plugins', 'themes'), true) && 2 !== count($segments)) {
        return false;
    }

    foreach (array_slice($segments, 1) as $segment) {
        if ($segment !== sanitize_file_name($segment)) {
            return false;
        }
    }

    return true;
}

function twmcd_zip_entry_is_link($archive, $index)
{
    if (!method_exists($archive, 'getExternalAttributesIndex')) {
        return false;
    }

    $operating_system = 0;
    $attributes = 0;
    if (!$archive->getExternalAttributesIndex($index, $operating_system, $attributes)) {
        return false;
    }

    return 0120000 === (($attributes >> 16) & 0170000);
}

function twmcd_validate_extracted_files($workspace, $manifest)
{
    $workspace = trailingslashit(wp_normalize_path($workspace));
    foreach ($manifest['files'] as $relative_path => $expected_checksum) {
        $file_path = wp_normalize_path($workspace . $relative_path);
        if (0 !== strpos($file_path, $workspace)
            || !is_file($file_path)
            || !hash_equals((string) $expected_checksum, (string) hash_file('sha256', $file_path))) {
            return new WP_Error(
                'twmcd_checksum_failed',
                sprintf(__('Checksum validation failed for %s. No code was installed.', 'tn-wp-migrate-code-diff'), $relative_path)
            );
        }
    }

    return true;
}

function twmcd_install_manifest_packages($workspace, $manifest)
{
    global $wp_filesystem;

    foreach ($manifest['packages'] as $package) {
        if ('themes' === $package['type'] && !current_user_can('update_themes')) {
            return new WP_Error('twmcd_theme_capability', __('You do not have permission to update themes in this release.', 'tn-wp-migrate-code-diff'));
        }
    }

    $installed = array();
    $operations = array();
    $backup_root = trailingslashit($workspace) . 'backups';
    $wp_filesystem->mkdir($backup_root, FS_CHMOD_DIR);

    foreach ($manifest['packages'] as $package) {
        $source = trailingslashit($workspace) . $package['archive_path'];
        $target = twmcd_package_destination_path($package['type'], $package['destination']);
        if (is_wp_error($target) || !$wp_filesystem->exists($source)) {
            twmcd_rollback_operations($operations);
            return is_wp_error($target)
                ? $target
                : new WP_Error('twmcd_staged_package_missing', __('A package declared in the release is missing from the staged payload.', 'tn-wp-migrate-code-diff'));
        }
        $target_parent = dirname($target);
        if (!$wp_filesystem->exists($target_parent)) {
            $wp_filesystem->mkdir($target_parent, FS_CHMOD_DIR);
        }
        if (!$wp_filesystem->is_writable($target_parent)) {
            twmcd_rollback_operations($operations);
            return new WP_Error(
                'twmcd_target_not_writable',
                sprintf(__('The destination for %s is not writable.', 'tn-wp-migrate-code-diff'), $package['name'])
            );
        }

        $backup = trailingslashit($backup_root) . md5($package['destination']);
        $had_existing = $wp_filesystem->exists($target);
        if ($had_existing && !$wp_filesystem->move($target, $backup, true)) {
            twmcd_rollback_operations($operations);
            return new WP_Error(
                'twmcd_backup_failed',
                sprintf(__('The existing files for %s could not be prepared for rollback.', 'tn-wp-migrate-code-diff'), $package['name'])
            );
        }

        $operation = array('target' => $target, 'backup' => $backup, 'had_existing' => $had_existing);
        $operations[] = $operation;
        if (!$wp_filesystem->move($source, $target, true)) {
            twmcd_rollback_operations($operations);
            return new WP_Error(
                'twmcd_install_failed',
                sprintf(__('The files for %s could not be installed. Earlier changes were rolled back.', 'tn-wp-migrate-code-diff'), $package['name'])
            );
        }

        $installed[] = sprintf('%1$s (%2$s)', $package['name'], $package['version'] ? $package['version'] : __('unknown version', 'tn-wp-migrate-code-diff'));
    }

    return $installed;
}

function twmcd_package_destination_path($type, $destination)
{
    $relative_path = substr($destination, strpos($destination, '/') + 1);
    if ('plugins' === $type) {
        $root = WP_PLUGIN_DIR;
    } elseif ('themes' === $type) {
        $root = get_theme_root();
    } elseif ('muplugins' === $type) {
        $root = WPMU_PLUGIN_DIR;
    } else {
        return new WP_Error('twmcd_unknown_package_type', __('The release contains an unknown package type.', 'tn-wp-migrate-code-diff'));
    }

    return trailingslashit($root) . $relative_path;
}

function twmcd_rollback_operations($operations)
{
    global $wp_filesystem;

    foreach (array_reverse($operations) as $operation) {
        if ($wp_filesystem->exists($operation['target'])) {
            $wp_filesystem->delete($operation['target'], true);
        }
        if ($operation['had_existing'] && $wp_filesystem->exists($operation['backup'])) {
            $wp_filesystem->move($operation['backup'], $operation['target'], true);
        }
    }
}

function twmcd_delete_workspace($workspace)
{
    global $wp_filesystem;

    if ($wp_filesystem && $wp_filesystem->exists($workspace)) {
        $wp_filesystem->delete($workspace, true);
    }
}
