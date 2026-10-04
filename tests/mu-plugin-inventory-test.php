<?php

define('ABSPATH', __DIR__ . '/');

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

$extension = file_get_contents(dirname(__DIR__) . '/functions/options-comparison.php');
if (false === strpos($extension, "'twmcd_code_inventory'")
    || false === strpos($extension, "array('code', 'options', 'posts')")) {
    fwrite(STDERR, "FAIL: signed connection response does not expose the enriched Code inventory.\n");
    exit(1);
}

echo "PASS: MU plugins use matching top-level identities and enriched remote versions.\n";
