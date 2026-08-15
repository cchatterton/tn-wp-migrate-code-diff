<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_compare_code_inventories($source_inventory, $destination_inventory)
{
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
                : 'different';
        }

        $display_package = $source_package ? $source_package : $destination_package;
        $default_selected = 'source_only' === $status
            || ('different' === $status && !twmcd_source_version_is_older($source_package['version'], $destination_package['version']));
        $comparison[] = array(
            'key'                => $package_key,
            'name'               => $display_package['name'],
            'status'             => $status,
            'source_version'     => $source_package ? $source_package['version'] : '',
            'destination_version' => $destination_package ? $destination_package['version'] : '',
            'source_activation' => $source_package && isset($source_package['activation'])
                ? $source_package['activation']
                : 'not_installed',
            'destination_activation' => $destination_package && isset($destination_package['activation'])
                ? $destination_package['activation']
                : 'not_installed',
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
