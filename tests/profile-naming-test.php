<?php

define('ABSPATH', __DIR__ . '/');

$test_profiles = array();

function date_i18n($format)
{
    return '202608';
}

function wp_parse_url($url, $component)
{
    return parse_url($url, $component);
}

function absint($value)
{
    return abs((int) $value);
}

function get_site_option($key)
{
    global $test_profiles;
    return 'wpmdb_saved_profiles' === $key ? $test_profiles : null;
}

function update_site_option($key, $value)
{
    global $test_profiles;
    if ('wpmdb_saved_profiles' === $key) {
        $test_profiles = $value;
    }
    return true;
}

function wp_json_encode($value)
{
    return json_encode($value);
}

function wp_generate_uuid4()
{
    static $uuid = 0;
    return 'uuid-' . ++$uuid;
}

function current_time()
{
    return 123456;
}

require dirname(__DIR__) . '/functions/helpers.php';
require dirname(__DIR__) . '/functions/profile.php';

if ('Release-202608-alphasys.com.au' !== twmcd_default_profile_name('https://alphasys.com.au')) {
    fwrite(STDERR, "FAIL: destination profile name was incorrect.\n");
    exit(1);
}
if ('Release-202608-localhost-10365' !== twmcd_default_profile_name('http://localhost:10365')) {
    fwrite(STDERR, "FAIL: destination port was not represented in the profile name.\n");
    exit(1);
}

$first_id = twmcd_store_migration_profile('Release-202608-alphasys.com.au', array('revision' => 1));
$second_id = twmcd_store_migration_profile('Release-202608-alphasys.com.au', array('revision' => 2));
if ($first_id !== $second_id || 1 !== count($test_profiles)) {
    fwrite(STDERR, "FAIL: monthly destination profile was duplicated instead of updated.\n");
    exit(1);
}
$stored = json_decode($test_profiles[$first_id]['value'], true);
if (2 !== $stored['revision'] || 'uuid-1' !== $test_profiles[$first_id]['guid']) {
    fwrite(STDERR, "FAIL: profile update did not replace its value while preserving identity.\n");
    exit(1);
}

echo "PASS: destination profile naming and upsert.\n";
