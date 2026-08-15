<?php

define('ABSPATH', __DIR__ . '/');

require dirname(__DIR__) . '/functions/comparison.php';

$groups = array(
    'plugins' => array(
        array('selection' => '/plugins/one', 'default_selected' => true),
        array('selection' => '/plugins/two', 'default_selected' => false),
    ),
    'themes' => array(
        array('selection' => '/themes/one', 'default_selected' => true),
    ),
    'muplugins' => array(),
);

$profile_selection = array(
    'active' => true,
    'groups' => array(
        'plugins' => array('/plugins/two'),
        'themes' => array(),
        'muplugins' => array(),
    ),
);

$result = twmcd_apply_loaded_profile_selection($groups, $profile_selection);
if ($result['plugins'][0]['initial_selected']
    || !$result['plugins'][1]['initial_selected']
    || $result['themes'][0]['initial_selected']) {
    fwrite(STDERR, "FAIL: saved profile selections did not override the initial smart defaults.\n");
    exit(1);
}
if (!$result['plugins'][0]['default_selected'] || $result['plugins'][1]['default_selected']) {
    fwrite(STDERR, "FAIL: Recommended defaults were changed while applying the saved profile.\n");
    exit(1);
}

$without_profile = twmcd_apply_loaded_profile_selection($groups, array('active' => false));
if (isset($without_profile['plugins'][0]['initial_selected'])) {
    fwrite(STDERR, "FAIL: a new migration was treated as a saved profile.\n");
    exit(1);
}

$source_inventory = array(
    'muplugins' => array(
        'example.php' => array(
            'name' => 'Example MU',
            'version' => '2.0.0',
            'activation' => 'always_active',
            'path' => '/mu-plugins/example.php',
        ),
    ),
);
$destination_inventory = array(
    'muplugins' => array(
        'example.php' => array(
            'name' => 'Example MU',
            'version' => '1.0.0',
            'activation' => 'always_active',
            'path' => '/mu-plugins/example.php',
        ),
    ),
);
$mu_comparison = twmcd_compare_package_group($source_inventory, $destination_inventory, 'muplugins');
if (!empty($mu_comparison[0]['default_selected'])) {
    fwrite(STDERR, "FAIL: a must-use plugin was recommended by default.\n");
    exit(1);
}

$version_source = array(
    'plugins' => array(
        'newer/plugin.php' => array(
            'name' => 'Newer Source',
            'version' => '2.0.0',
            'activation' => 'site_active',
            'path' => '/plugins/newer',
        ),
        'older/plugin.php' => array(
            'name' => 'Older Source',
            'version' => '1.0.0',
            'activation' => 'site_active',
            'path' => '/plugins/older',
        ),
    ),
);
$version_destination = array(
    'plugins' => array(
        'newer/plugin.php' => array('name' => 'Newer Source', 'version' => '1.0.0', 'activation' => 'site_active'),
        'older/plugin.php' => array('name' => 'Older Source', 'version' => '2.0.0', 'activation' => 'site_active'),
    ),
);
$version_comparison = twmcd_compare_package_group($version_source, $version_destination, 'plugins');
if ('source_newer' !== $version_comparison[0]['status']
    || empty($version_comparison[0]['default_selected'])
    || 'source_older' !== $version_comparison[1]['status']
    || !empty($version_comparison[1]['default_selected'])) {
    fwrite(STDERR, "FAIL: source version direction or recommendation was incorrect.\n");
    exit(1);
}

$destination_only_comparison = twmcd_compare_package_group(
    array('plugins' => array()),
    array(
        'plugins' => array(
            'active/plugin.php' => array('name' => 'Active Destination', 'version' => '1.0.0', 'activation' => 'site_active'),
            'inactive/plugin.php' => array('name' => 'Inactive Destination', 'version' => '1.0.0', 'activation' => 'inactive'),
        ),
    ),
    'plugins'
);
if ('destination_only' !== $destination_only_comparison[0]['status']
    || empty($destination_only_comparison[0]['selection'])
    || !empty($destination_only_comparison[0]['default_selected'])
    || 'remove' !== $destination_only_comparison[0]['selection_operation']
    || empty($destination_only_comparison[1]['default_selected'])) {
    fwrite(STDERR, "FAIL: destination-only removal defaults were incorrect.\n");
    exit(1);
}

$destination_only_theme = twmcd_compare_package_group(
    array('themes' => array()),
    array(
        'themes' => array(
            'inactive-theme' => array('name' => 'Inactive Theme', 'version' => '1.0.0', 'activation' => 'inactive'),
        ),
    ),
    'themes'
);
if (!empty($destination_only_theme[0]['default_selected'])
    || 'remove' !== $destination_only_theme[0]['selection_operation']) {
    fwrite(STDERR, "FAIL: a destination-only inactive theme was recommended for removal.\n");
    exit(1);
}

echo "PASS: saved profile initial selection.\n";
