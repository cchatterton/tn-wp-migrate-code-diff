<?php

define('ABSPATH', __DIR__ . '/');
define('TWMCD_PLUGIN_FILE', '/plugins/tn-wp-migrate-code-diff/tn-wp-migrate-code-diff.php');

function plugin_basename($value)
{
    return 'tn-wp-migrate-code-diff/tn-wp-migrate-code-diff.php';
}

function esc_url_raw($value)
{
    return (string) $value;
}

function wp_strip_all_tags($value)
{
    return strip_tags((string) $value);
}

function sanitize_text_field($value)
{
    return trim((string) $value);
}

require dirname(__DIR__) . '/functions/helpers.php';
require dirname(__DIR__) . '/functions/comparison.php';

$remote = twmcd_normalize_remote_inventory(
    array(
        'url' => 'https://destination.example',
        'site_details' => array(
            'is_multisite' => 'true',
            'plugins' => array(),
            'themes' => array(),
            'muplugins' => array(
                'wpengine-common' => array(array('name' => 'wpengine-common', 'path' => '/remote/mu-plugins/wpengine-common')),
            ),
        ),
        'twmcd_code_inventory' => array(
            'muplugins' => array(
                'wpengine-common' => array(
                    'name' => 'wpengine-common',
                    'version' => '',
                    'path' => '/remote/mu-plugins/wpengine-common',
                ),
                'wpengine-system.php' => array(
                    'name' => 'WP Engine System',
                    'version' => '6.6.3',
                    'path' => '/remote/mu-plugins/wpengine-system.php',
                ),
            ),
        ),
    )
);

if (array('wpengine-common', 'wpengine-system.php') !== array_keys($remote['muplugins'])
    || 'WP Engine System' !== $remote['muplugins']['wpengine-system.php']['name']
    || '6.6.3' !== $remote['muplugins']['wpengine-system.php']['version']) {
    fwrite(STDERR, "FAIL: enriched remote MU-plugin inventory was not normalized by top-level entry.\n");
    exit(1);
}

$source = array('muplugins' => $remote['muplugins']);
$comparison = twmcd_compare_package_group($source, array('muplugins' => $remote['muplugins']), 'muplugins');
if (2 !== count($comparison)
    || 'same' !== $comparison[0]['status']
    || 'same' !== $comparison[1]['status']) {
    fwrite(STDERR, "FAIL: equivalent MU-plugin entries did not match across sites.\n");
    exit(1);
}

$unknown_version = twmcd_compare_package_group(
    array(
        'muplugins' => array(
            'wpengine-system.php' => array(
                'name' => 'WP Engine System',
                'version' => '6.6.3',
                'path' => '/local/mu-plugins/wpengine-system.php',
                'activation' => 'always_active',
            ),
        ),
    ),
    array(
        'muplugins' => array(
            'wpengine-system.php' => array(
                'name' => 'wpengine-system.php',
                'version' => '',
                'path' => '/remote/mu-plugins/wpengine-system.php',
                'activation' => 'always_active',
            ),
        ),
    ),
    'muplugins'
);
if ('unknown' !== $unknown_version[0]['status'] || !empty($unknown_version[0]['default_selected'])) {
    fwrite(STDERR, "FAIL: missing remote MU-plugin version was reported as an upgrade.\n");
    exit(1);
}

$collapsed = twmcd_compare_code_inventories(
    array(
        'plugins' => array(
            'wp-migrate-db-pro/wp-migrate-db-pro.php' => array(
                'name' => 'WP Migrate',
                'version' => '2.7.4',
                'path' => '/local/plugins/wp-migrate-db-pro',
                'activation' => 'site_active',
            ),
        ),
        'themes' => array(),
        'muplugins' => array(
            'force-strong-passwords' => array(
                'name' => 'force-strong-passwords',
                'version' => '',
                'path' => '/local/mu-plugins/force-strong-passwords',
                'activation' => 'always_active',
                'entry_type' => 'directory',
            ),
            'force-strong-passwords.php' => array(
                'name' => 'Force Strong Passwords - WPE Edition',
                'version' => '1.8.0',
                'path' => '/local/mu-plugins/force-strong-passwords.php',
                'activation' => 'always_active',
                'entry_type' => 'file',
            ),
        ),
    ),
    array(
        'plugins' => array(
            'different-key.php' => array(
                'name' => 'WP Migrate',
                'version' => '2.7.4',
                'path' => '/remote/plugins/wp-migrate-db-pro/wp-migrate-db-pro.php',
                'activation' => 'site_active',
            ),
        ),
        'themes' => array(),
        'muplugins' => array(
            'force-strong-passwords' => array(
                'name' => 'force-strong-passwords',
                'version' => '',
                'path' => '/remote/mu-plugins/force-strong-passwords',
                'activation' => 'always_active',
                'entry_type' => 'directory',
            ),
            'force-strong-passwords.php' => array(
                'name' => 'Force Strong Passwords - WPE Edition',
                'version' => '1.8.0',
                'path' => '/remote/mu-plugins/force-strong-passwords.php',
                'activation' => 'always_active',
                'entry_type' => 'file',
            ),
        ),
    )
);

if (!empty($collapsed['plugins'])
    || 1 !== count($collapsed['muplugins'])
    || 'Force Strong Passwords - WPE Edition' !== $collapsed['muplugins'][0]['name']
    || 'same' !== $collapsed['muplugins'][0]['status']
    || 2 !== count($collapsed['muplugins'][0]['selection_paths'])
    || 2 !== count($collapsed['muplugins'][0]['removal_keys'])) {
    fwrite(STDERR, "FAIL: transport plugins were shown or MU loader/support components were not collapsed.\n");
    exit(1);
}

$extension = file_get_contents(dirname(__DIR__) . '/functions/options-comparison.php');
if (false === strpos($extension, "'twmcd_code_inventory'")
    || false === strpos($extension, "array('code', 'options', 'posts')")) {
    fwrite(STDERR, "FAIL: signed connection response does not expose the enriched Code inventory.\n");
    exit(1);
}

echo "PASS: MU plugins use logical identities, collapse loader/support pairs, and omit transport plugins.\n";
