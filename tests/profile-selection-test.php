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

echo "PASS: saved profile initial selection.\n";
