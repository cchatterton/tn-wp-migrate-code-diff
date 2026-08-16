<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_add_remote_comparison_key_rules($rules)
{
    $rules['twmcd_mode'] = 'key';
    $rules['twmcd_context'] = 'string';

    return $rules;
}

function twmcd_extend_remote_connection_data($data)
{
    $mode = isset($_POST['twmcd_mode']) ? sanitize_key(wp_unslash($_POST['twmcd_mode'])) : '';
    if (!in_array($mode, array('options', 'posts'), true)) {
        return $data;
    }

    $context_encoded = isset($_POST['twmcd_context']) ? wp_unslash($_POST['twmcd_context']) : '';
    $context_json = base64_decode((string) $context_encoded, true);
    $context_json = false === $context_json ? '' : $context_json;
    $context = twmcd_sanitize_migration_context(json_decode($context_json, true));
    if ('posts' === $mode) {
        $data['twmcd_posts_inventory'] = twmcd_local_posts_inventory($context, false);
    } else {
        $data['twmcd_options_inventory'] = twmcd_local_options_inventory($context, false);
    }

    return $data;
}

function twmcd_request_remote_options_inventory($remote_url, $secret_key, $intent, $context)
{
    $response = twmcd_request_remote_site_data(
        $remote_url,
        $secret_key,
        $intent,
        array(
            'twmcd_mode'    => 'options',
            'twmcd_context' => base64_encode(wp_json_encode(twmcd_sanitize_migration_context($context))),
        )
    );

    if (is_wp_error($response)) {
        return $response;
    }

    if (!isset($response['twmcd_options_inventory']) || !is_array($response['twmcd_options_inventory'])) {
        return new WP_Error(
            'twmcd_options_inventory_unavailable',
            __('The remote site did not return its Options inventory. Install and activate the same current version of this plugin on both sites.', 'tn-wp-migrate-code-diff')
        );
    }

    return twmcd_sanitize_options_inventory($response['twmcd_options_inventory']);
}

function twmcd_options_table_map($context, $is_local)
{
    global $wpdb;

    if (!is_multisite()) {
        return array('options' => $wpdb->options);
    }

    $blog_id = twmcd_database_inventory_blog_id($context, $is_local, true);
    if ($blog_id > 0) {
        $switched = $blog_id !== get_current_blog_id();
        if ($switched) {
            switch_to_blog($blog_id);
        }
        $table_name = $wpdb->options;
        if ($switched) {
            restore_current_blog();
        }

        return array('options' => $table_name);
    }

    $tables = array();
    $like = $wpdb->esc_like($wpdb->base_prefix) . '%options';
    foreach ((array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like)) as $table_name) {
        $logical_name = substr((string) $table_name, strlen($wpdb->base_prefix));
        $tables[$logical_name] = (string) $table_name;
    }
    if (!empty($wpdb->sitemeta)) {
        $tables['sitemeta'] = $wpdb->sitemeta;
    }
    ksort($tables);

    return $tables;
}

function twmcd_local_options_inventory($context, $is_local = true)
{
    global $wpdb;

    $inventory = array();
    foreach (twmcd_options_table_map($context, $is_local) as $logical_name => $table_name) {
        $is_sitemeta = 'sitemeta' === $logical_name;
        $name_column = $is_sitemeta ? 'meta_key' : 'option_name';
        $value_column = $is_sitemeta ? 'meta_value' : 'option_value';
        $safe_table = str_replace('`', '', (string) $table_name);
        $rows = $wpdb->get_results(
            "SELECT `{$name_column}` AS option_name, `{$value_column}` AS option_value FROM `{$safe_table}`",
            ARRAY_A
        );
        $options = array();

        foreach ((array) $rows as $row) {
            $option_name = isset($row['option_name']) ? (string) $row['option_name'] : '';
            if ('' === $option_name || twmcd_is_transient_option($option_name)) {
                continue;
            }
            $options[$option_name] = hash('sha256', isset($row['option_value']) ? (string) $row['option_value'] : '');
        }
        ksort($options);
        $inventory[$logical_name] = $options;
    }

    return $inventory;
}

function twmcd_sanitize_options_inventory($inventory)
{
    $sanitized = array();
    foreach ((array) $inventory as $table_name => $options) {
        $table_name = sanitize_text_field((string) $table_name);
        if ('' === $table_name || !is_array($options)) {
            continue;
        }
        foreach ($options as $option_name => $fingerprint) {
            $option_name = sanitize_text_field((string) $option_name);
            $fingerprint = preg_replace('/[^a-f0-9]/', '', strtolower((string) $fingerprint));
            if ('' !== $option_name && 64 === strlen($fingerprint) && !twmcd_is_transient_option($option_name)) {
                $sanitized[$table_name][$option_name] = $fingerprint;
            }
        }
        if (isset($sanitized[$table_name])) {
            ksort($sanitized[$table_name]);
        }
    }
    ksort($sanitized);

    return $sanitized;
}

function twmcd_is_transient_option($option_name)
{
    return 0 === strpos($option_name, '_transient_')
        || 0 === strpos($option_name, '_site_transient_');
}

function twmcd_ignored_option_names()
{
    return apply_filters(
        'twmcd_ignored_option_names',
        array(
            'siteurl',
            'home',
            'upload_path',
            'upload_url_path',
            'active_plugins',
            'recently_activated',
            'auto_update_plugins',
            'auto_update_themes',
            'rewrite_rules',
            'cron',
        )
    );
}

function twmcd_option_is_ignored($option_name)
{
    if (in_array($option_name, twmcd_ignored_option_names(), true)) {
        return true;
    }

    return '_user_roles' === substr((string) $option_name, -11);
}

function twmcd_compare_options_inventories($source, $destination)
{
    $table_names = array_unique(array_merge(array_keys((array) $source), array_keys((array) $destination)));
    $tables = array();
    sort($table_names);

    foreach ($table_names as $table_name) {
        $source_options = isset($source[$table_name]) ? $source[$table_name] : array();
        $destination_options = isset($destination[$table_name]) ? $destination[$table_name] : array();
        $option_names = array_unique(array_merge(array_keys($source_options), array_keys($destination_options)));
        $differences = array();
        sort($option_names);

        foreach ($option_names as $option_name) {
            $has_source = array_key_exists($option_name, $source_options);
            $has_destination = array_key_exists($option_name, $destination_options);
            if ($has_source && $has_destination && $source_options[$option_name] === $destination_options[$option_name]) {
                continue;
            }

            $status = !$has_destination ? 'source_only' : (!$has_source ? 'destination_only' : 'different');
            $ignored = twmcd_option_is_ignored($option_name);
            $differences[] = array(
                'name'             => $option_name,
                'status'           => $status,
                'ignored'          => $ignored,
                'default_selected' => !$ignored && in_array($status, array('source_only', 'different'), true),
            );
        }
        $tables[] = array('name' => $table_name, 'options' => $differences);
    }

    return $tables;
}
