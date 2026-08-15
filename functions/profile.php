<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_create_code_only_profile($profile_name, $context, $selection)
{
    $intent = $context['intent'];
    $connection_info = $context['connection_info'];
    $connection = $context['connection'];
    $multisite_tools = isset($context['multisite_tools']) && is_array($context['multisite_tools'])
        ? $context['multisite_tools']
        : array('enabled' => false);
    $migration_context = isset($context['migration']) && is_array($context['migration'])
        ? $context['migration']
        : array();
    $plugins = twmcd_sanitize_profile_paths(isset($selection['plugins']) ? $selection['plugins'] : array());
    $themes = twmcd_sanitize_profile_paths(isset($selection['themes']) ? $selection['themes'] : array());
    $mu_plugins = twmcd_sanitize_profile_paths(isset($selection['muplugins']) ? $selection['muplugins'] : array());
    $default_exclusions = ".DS_Store\n.git\nnode_modules";

    return array(
        'current_migration' => array(
            'connected'                 => false,
            'intent'                    => $intent,
            'tables_option'             => 'all',
            'tables_selected'           => array(),
            'backup_option'             => 'none',
            'backup_tables_selected'    => array(),
            'post_types_option'         => 'all',
            'post_types_selected'       => array(),
            'advanced_options_selected' => array(),
            'profile_name'              => $profile_name,
            'migration_enabled'         => false,
            'databaseEnabled'           => false,
            'localSource'               => !empty($migration_context['local_source']),
            'twoMultisites'             => !empty($migration_context['two_multisites']),
        ),
        'connection_info' => array(
            'connection_state' => array(
                'value' => $connection_info,
                'url'   => $connection['url'],
                'key'   => $connection['key'],
            ),
        ),
        'search_replace' => array(
            'custom_search_replace'     => array(),
            'standard_search_visible'   => true,
            'standard_options_enabled'  => array('domain', 'path'),
        ),
        'media_files' => array(
            'enabled' => false,
        ),
        'theme_plugin_files' => array(
            'available'          => true,
            'theme_files'        => array('enabled' => !empty($themes)),
            'themes_option'      => 'selected',
            'themes_selected'    => $themes,
            'themes_excluded'    => array(),
            'themes_excludes'    => $default_exclusions,
            'plugin_files'       => array('enabled' => !empty($plugins)),
            'plugins_option'     => 'selected',
            'plugins_selected'   => $plugins,
            'plugins_excluded'   => array(),
            'plugins_excludes'   => $default_exclusions,
            'muplugin_files'     => array('enabled' => !empty($mu_plugins)),
            'muplugins_option'   => 'selected',
            'muplugins_selected' => $mu_plugins,
            'muplugins_excludes' => $default_exclusions,
            'other_files'        => array('enabled' => false),
            'others_option'      => 'selected',
            'others_selected'    => array(),
            'others_excludes'    => $default_exclusions,
            'core_files'         => array('enabled' => false),
            'core_option'        => 'all',
            'core_selected'      => array(),
            'core_excludes'      => $default_exclusions,
        ),
        'multisite_tools' => $multisite_tools,
    );
}

function twmcd_sanitize_profile_paths($paths)
{
    if (!is_array($paths)) {
        return array();
    }

    return array_values(array_filter(array_map('sanitize_text_field', $paths), 'strlen'));
}

function twmcd_create_comparison_token($context, $comparison_groups)
{
    $token = wp_generate_password(20, false, false);
    $allowed_paths = array(
        'plugins'   => array(),
        'themes'    => array(),
        'muplugins' => array(),
    );

    foreach ($allowed_paths as $group_key => $unused) {
        $group_rows = isset($comparison_groups[$group_key]) && is_array($comparison_groups[$group_key])
            ? $comparison_groups[$group_key]
            : array();

        foreach ($group_rows as $row) {
            if (!empty($row['selection'])) {
                $allowed_paths[$group_key][] = (string) $row['selection'];
            }
        }
    }

    set_site_transient(
        twmcd_comparison_transient_key($token),
        array(
            'context'       => $context,
            'allowed_paths' => $allowed_paths,
        ),
        15 * MINUTE_IN_SECONDS
    );

    return $token;
}

function twmcd_comparison_transient_key($token)
{
    return 'twmcd_cmp_' . get_current_user_id() . '_' . sanitize_key($token);
}

function twmcd_validate_profile_selection($token, $selection)
{
    $comparison_state = get_site_transient(twmcd_comparison_transient_key($token));

    if (!is_array($comparison_state) || empty($comparison_state['context'])) {
        return new WP_Error(
            'twmcd_expired_comparison',
            __('The comparison has expired. Run the code comparison again before saving.', 'tn-wp-migrate-code-diff')
        );
    }

    $validated_selection = array();
    foreach (array('plugins', 'themes', 'muplugins') as $group_key) {
        $submitted_paths = isset($selection[$group_key]) && is_array($selection[$group_key])
            ? array_map('strval', $selection[$group_key])
            : array();
        $allowed_paths = isset($comparison_state['allowed_paths'][$group_key])
            ? $comparison_state['allowed_paths'][$group_key]
            : array();

        $validated_selection[$group_key] = array_values(array_intersect($submitted_paths, $allowed_paths));

        if (count($validated_selection[$group_key]) !== count($submitted_paths)) {
            return new WP_Error(
                'twmcd_invalid_selection',
                __('The selected packages did not match the latest comparison. Run the comparison again.', 'tn-wp-migrate-code-diff')
            );
        }
    }

    return $validated_selection;
}

function twmcd_get_comparison_state($token)
{
    $comparison_state = get_site_transient(twmcd_comparison_transient_key($token));

    return is_array($comparison_state) ? $comparison_state : false;
}

function twmcd_store_migration_profile($profile_name, $profile)
{
    $profiles = get_site_option('wpmdb_saved_profiles');
    $profiles = is_array($profiles) ? $profiles : array();
    $profiles[] = array(
        'name'  => $profile_name,
        'value' => wp_json_encode($profile),
        'guid'  => wp_generate_uuid4(),
        'date'  => current_time('timestamp'),
    );

    update_site_option('wpmdb_saved_profiles', $profiles);

    return max(array_keys($profiles));
}
