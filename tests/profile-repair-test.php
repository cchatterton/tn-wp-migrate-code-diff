<?php

define('ABSPATH', __DIR__ . '/');

$test_options = array(
    'wpmdb_saved_profiles' => array(
        array(
            'name' => 'Release-20260815-13:37',
            'value' => json_encode(
                array(
                    'current_migration' => array(
                        'connected' => false,
                        'databaseEnabled' => false,
                    ),
                    'connection_info' => array(
                        'connection_state' => array('value' => 'connection'),
                    ),
                    'media_files' => array('enabled' => false),
                    'theme_plugin_files' => array('plugins_option' => 'selected'),
                    'multisite_tools' => array(
                        'enabled' => true,
                        'selected_subsite' => 7,
                    ),
                )
            ),
        ),
        array(
            'name' => 'Release-20260815-1400',
            'value' => json_encode(
                array(
                    'current_migration' => array('databaseEnabled' => true),
                    'connection_info' => array('connection_state' => array('value' => 'connection')),
                    'media_files' => array('enabled' => false),
                    'theme_plugin_files' => array('plugins_option' => 'selected'),
                )
            ),
        ),
    ),
);

function get_site_option($key, $default = false)
{
    global $test_options;
    return array_key_exists($key, $test_options) ? $test_options[$key] : $default;
}

function update_site_option($key, $value)
{
    global $test_options;
    $test_options[$key] = $value;
    return true;
}

function wp_json_encode($value)
{
    return json_encode($value);
}

function sanitize_text_field($value)
{
    return trim((string) $value);
}

function wp_generate_uuid4()
{
    return '00000000-0000-4000-8000-000000000001';
}

require dirname(__DIR__) . '/functions/profile.php';

$profile_paths = twmcd_sanitize_profile_paths(array('/plugins/keep', 'twmcd-remove:plugins:obsolete%2Fplugin.php'));
if (array('/plugins/keep') !== $profile_paths) {
    fwrite(STDERR, "FAIL: manual release removals leaked into a WP Migrate profile.\n");
    exit(1);
}

twmcd_repair_legacy_release_profiles();

$repaired = json_decode($test_options['wpmdb_saved_profiles'][0]['value'], true);
$untouched = json_decode($test_options['wpmdb_saved_profiles'][1]['value'], true);

if (empty($repaired['current_migration']['connected'])
    || empty($repaired['multisite_tools']['available'])
    || empty($repaired['multisite_tools']['is_licensed'])
    || 7 !== $repaired['multisite_tools']['selected_subsite']
    || !isset($repaired['multisite_tools']['message'])
    || 'all' !== $repaired['media_files']['option']
    || empty($repaired['media_files']['date'])
    || !isset($repaired['search_replace']['standard_search_replace']['domain'])
    || empty($repaired['search_replace']['custom_search_replace'])
    || 2 !== $repaired['_twmcd']['profile_schema']) {
    fwrite(STDERR, "FAIL: legacy release profile was not repaired.\n");
    exit(1);
}

if (isset($untouched['_twmcd'])) {
    fwrite(STDERR, "FAIL: unrelated release-named profile was modified.\n");
    exit(1);
}

if (2 !== $test_options['twmcd_profile_schema_version']) {
    fwrite(STDERR, "FAIL: profile repair schema version was not recorded.\n");
    exit(1);
}

echo "PASS: legacy release profile repair.\n";
