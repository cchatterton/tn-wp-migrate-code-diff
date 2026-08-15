<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_compare_code_inventories($source_inventory, $destination_inventory)
{
    $plugin_file = plugin_basename(TWMCD_PLUGIN_FILE);
    unset($source_inventory['plugins'][$plugin_file], $destination_inventory['plugins'][$plugin_file]);

    return array(
        'plugins'   => twmcd_compare_package_group($source_inventory, $destination_inventory, 'plugins'),
        'themes'    => twmcd_compare_package_group($source_inventory, $destination_inventory, 'themes'),
        'muplugins' => twmcd_compare_package_group($source_inventory, $destination_inventory, 'muplugins'),
    );
}

function twmcd_compare_package_group($source_inventory, $destination_inventory, $group_key)
{
    $source_packages = isset($source_inventory[$group_key]) && is_array($source_inventory[$group_key])
        ? $source_inventory[$group_key]
        : array();
    $destination_packages = isset($destination_inventory[$group_key]) && is_array($destination_inventory[$group_key])
        ? $destination_inventory[$group_key]
        : array();
    $package_keys = array_unique(array_merge(array_keys($source_packages), array_keys($destination_packages)));
    $comparison = array();

    sort($package_keys);

    foreach ($package_keys as $package_key) {
        $source_package = isset($source_packages[$package_key]) ? $source_packages[$package_key] : null;
        $destination_package = isset($destination_packages[$package_key]) ? $destination_packages[$package_key] : null;

        if (!$destination_package) {
            $status = 'source_only';
        } elseif (!$source_package) {
            $status = 'destination_only';
        } else {
            $status = (string) $source_package['version'] === (string) $destination_package['version']
                ? 'same'
                : (twmcd_source_version_is_older($source_package['version'], $destination_package['version'])
                    ? 'source_older'
                    : 'source_newer');
        }

        $source_activation = $source_package && isset($source_package['activation'])
            ? $source_package['activation']
            : 'not_installed';
        $destination_activation = $destination_package && isset($destination_package['activation'])
            ? $destination_package['activation']
            : 'not_installed';
        $display_package = $source_package ? $source_package : $destination_package;
        $default_selected = 'source_only' === $status
            || 'source_newer' === $status;
        if (in_array($status, array('source_newer', 'source_older', 'source_only'), true) && 'inactive' === $source_activation) {
            $default_selected = false;
        }
        if ('muplugins' === $group_key) {
            $default_selected = false;
        }
        $comparison[] = array(
            'key'                => $package_key,
            'name'               => $display_package['name'],
            'status'             => $status,
            'source_version'     => $source_package ? $source_package['version'] : '',
            'destination_version' => $destination_package ? $destination_package['version'] : '',
            'source_activation' => $source_activation,
            'destination_activation' => $destination_activation,
            'selection'          => $source_package ? $source_package['path'] : '',
            'default_selected'   => $default_selected,
        );
    }

    return $comparison;
}

function twmcd_source_version_is_older($source_version, $destination_version)
{
    $source_version = trim((string) $source_version);
    $destination_version = trim((string) $destination_version);

    if ('' === $source_version || '' === $destination_version) {
        return false;
    }

    return version_compare($source_version, $destination_version, '<');
}

function twmcd_apply_loaded_profile_selection($comparison_groups, $profile_selection)
{
    if (empty($profile_selection['active']) || empty($profile_selection['groups'])) {
        return $comparison_groups;
    }

    foreach (array('plugins', 'themes', 'muplugins') as $group_key) {
        $selected_paths = isset($profile_selection['groups'][$group_key])
            ? (array) $profile_selection['groups'][$group_key]
            : array();
        foreach ($comparison_groups[$group_key] as $index => $package) {
            $comparison_groups[$group_key][$index]['initial_selected'] = !empty($package['selection'])
                && in_array($package['selection'], $selected_paths, true);
        }
    }

    return $comparison_groups;
}

function twmcd_local_database_images_inventory($context)
{
    global $wpdb;

    $table_sizes = array();
    $table_rows = array();
    $tables = array();
    $statuses = $wpdb->get_results('SHOW TABLE STATUS', ARRAY_A);

    foreach ((array) $statuses as $status) {
        if (empty($status['Name'])) {
            continue;
        }
        $table_name = (string) $status['Name'];
        $tables[] = $table_name;
        $table_rows[$table_name] = isset($status['Rows']) ? (int) $status['Rows'] : 0;
        $table_sizes[$table_name] = (int) round(
            ((int) $status['Data_length'] + (int) $status['Index_length']) / 1024
        );
    }

    $uploads = wp_get_upload_dir();
    $media_version = '';
    foreach (array('wp-migrate-db-pro-media-files', 'wp-migrate-db-pro') as $slug) {
        if (!empty($GLOBALS['wpmdb_meta'][$slug]['version'])) {
            $media_version = (string) $GLOBALS['wpmdb_meta'][$slug]['version'];
            break;
        }
    }

    return twmcd_normalize_database_images_inventory(
        array(
            'url'                   => home_url(),
            'prefix'                => $wpdb->base_prefix,
            'tables'                => $tables,
            'table_sizes'           => $table_sizes,
            'table_rows'            => $table_rows,
            'uploads_dir'           => isset($uploads['basedir']) ? $uploads['basedir'] : '',
            'media_files_available' => isset($GLOBALS['wpmdb_meta']['wp-migrate-db-pro-media-files']) || isset($GLOBALS['wpmdb_meta']['wp-migrate-db-pro']),
            'media_files_version'   => $media_version,
            'mf_is_licensed'        => '1',
            'site_details'          => array('is_multisite' => is_multisite() ? 'true' : 'false'),
        ),
        $context,
        true
    );
}

function twmcd_normalize_database_images_inventory($data, $context, $is_local)
{
    $site_details = isset($data['site_details']) && is_array($data['site_details']) ? $data['site_details'] : array();
    $is_multisite_site = !empty($site_details['is_multisite']) && twmcd_sanitize_boolean($site_details['is_multisite']);
    $base_prefix = isset($data['prefix']) ? (string) $data['prefix'] : '';
    $blog_id = twmcd_database_inventory_blog_id($context, $is_local, $is_multisite_site);
    $scope_prefix = $blog_id > 1 ? $base_prefix . $blog_id . '_' : $base_prefix;
    $table_sizes = isset($data['table_sizes']) && is_array($data['table_sizes']) ? $data['table_sizes'] : array();
    $table_rows = isset($data['table_rows']) && is_array($data['table_rows']) ? $data['table_rows'] : array();
    $normalized_tables = array();

    foreach ((array) (isset($data['tables']) ? $data['tables'] : array()) as $table_name) {
        $table_name = (string) $table_name;
        if ('' === $table_name) {
            continue;
        }

        if ($blog_id > 0 && 0 !== strpos($table_name, $scope_prefix)
            && !in_array($table_name, array($base_prefix . 'users', $base_prefix . 'usermeta'), true)) {
            continue;
        }

        $logical_name = $table_name;
        if ('' !== $scope_prefix && 0 === strpos($logical_name, $scope_prefix)) {
            $logical_name = substr($logical_name, strlen($scope_prefix));
        } elseif ('' !== $base_prefix && 0 === strpos($logical_name, $base_prefix)) {
            $logical_name = substr($logical_name, strlen($base_prefix));
        }

        $normalized_tables[$logical_name] = array(
            'logical_name' => $logical_name,
            'table_name'   => $table_name,
            'rows'         => isset($table_rows[$table_name]) ? (int) $table_rows[$table_name] : 0,
            'size_kb'      => isset($table_sizes[$table_name]) ? (int) $table_sizes[$table_name] : 0,
        );
    }

    ksort($normalized_tables);

    return array(
        'url'        => isset($data['url']) ? esc_url_raw($data['url']) : '',
        'tables'     => $normalized_tables,
        'images'     => array(
            'uploads_dir' => isset($data['uploads_dir']) ? sanitize_text_field((string) $data['uploads_dir']) : '',
            'available'   => !empty($data['media_files_available']) && twmcd_sanitize_boolean($data['media_files_available']),
            'licensed'    => !empty($data['mf_is_licensed']) && twmcd_sanitize_boolean($data['mf_is_licensed']),
            'version'     => isset($data['media_files_version']) ? sanitize_text_field((string) $data['media_files_version']) : '',
        ),
    );
}

function twmcd_database_inventory_blog_id($context, $is_local, $is_multisite_site)
{
    if (!$is_multisite_site || empty($context['multisite_tools']['enabled'])) {
        return 0;
    }

    $local_is_source = !empty($context['migration']['local_source']);
    $site_is_source = $is_local ? $local_is_source : !$local_is_source;
    $two_multisites = !empty($context['migration']['two_multisites']);
    $key = $two_multisites && !$site_is_source ? 'destination_subsite' : 'selected_subsite';

    return !empty($context['multisite_tools'][$key]) ? absint($context['multisite_tools'][$key]) : 0;
}

function twmcd_compare_database_images_inventories($source, $destination)
{
    $table_keys = array_unique(array_merge(array_keys($source['tables']), array_keys($destination['tables'])));
    $tables = array();
    sort($table_keys);

    foreach ($table_keys as $table_key) {
        $source_table = isset($source['tables'][$table_key]) ? $source['tables'][$table_key] : null;
        $destination_table = isset($destination['tables'][$table_key]) ? $destination['tables'][$table_key] : null;
        if (!$destination_table) {
            $status = 'source_only';
        } elseif (!$source_table) {
            $status = 'destination_only';
        } else {
            $status = $source_table['rows'] === $destination_table['rows']
                && $source_table['size_kb'] === $destination_table['size_kb'] ? 'same' : 'different';
        }

        $tables[] = array(
            'name'             => $table_key,
            'status'           => $status,
            'source_table'     => $source_table ? $source_table['table_name'] : '',
            'destination_table' => $destination_table ? $destination_table['table_name'] : '',
            'source_rows'      => $source_table ? $source_table['rows'] : null,
            'destination_rows' => $destination_table ? $destination_table['rows'] : null,
            'source_size_kb'   => $source_table ? $source_table['size_kb'] : null,
            'destination_size_kb' => $destination_table ? $destination_table['size_kb'] : null,
        );
    }

    return array(
        'tables' => $tables,
        'images' => array(
            'source'      => $source['images'],
            'destination' => $destination['images'],
        ),
    );
}
