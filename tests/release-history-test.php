<?php

define('ABSPATH', __DIR__ . '/');

function sanitize_user($value)
{
    return preg_replace('/[^a-z0-9_-]/i', '', (string) $value);
}

function sanitize_text_field($value)
{
    return trim((string) $value);
}

function wp_get_current_user()
{
    return (object) array(
        'ID' => 42,
        'user_login' => 'release.user',
        'display_name' => 'Release User',
    );
}

require dirname(__DIR__) . '/functions/history.php';

$manifest = array(
    'release_id' => 'Release-20260815-1700',
    'source_url' => 'https://source.example',
    'created_at' => '2026-08-15T07:00:00+00:00',
    'packages' => array(
        array('type' => 'plugins', 'name' => 'Added', 'destination' => 'plugins/added', 'from_version' => '', 'version' => '1.0.0'),
        array('type' => 'plugins', 'name' => 'Updated', 'destination' => 'plugins/updated', 'from_version' => '1.0.0', 'version' => '2.0.0'),
    ),
    'remove_packages' => array(
        array('type' => 'plugins', 'name' => 'Removed', 'destination' => 'plugins/removed', 'version' => '3.0.0'),
    ),
);

$changes = twmcd_release_history_changes($manifest);
if ('Added' !== $changes['added'][0]['name']
    || '1.0.0' !== $changes['updated'][0]['from_version']
    || '2.0.0' !== $changes['updated'][0]['to_version']
    || '3.0.0' !== $changes['removed'][0]['from_version']) {
    fwrite(STDERR, "FAIL: release history change summary was incorrect.\n");
    exit(1);
}

$user = twmcd_current_release_user();
if (42 !== $user['id'] || 'Release User' !== $user['display_name']) {
    fwrite(STDERR, "FAIL: release history user metadata was incorrect.\n");
    exit(1);
}

if (64 !== strlen(twmcd_release_history_key($manifest))) {
    fwrite(STDERR, "FAIL: release history key was invalid.\n");
    exit(1);
}

echo "PASS: release history metadata and changes.\n";
