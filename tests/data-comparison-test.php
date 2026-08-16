<?php

define('ABSPATH', __DIR__ . '/');

function apply_filters($hook, $value)
{
    return $value;
}

require dirname(__DIR__) . '/functions/comparison.php';
require dirname(__DIR__) . '/functions/options-comparison.php';

$source = array(
    'tables' => array(
        'posts' => array('table_name' => 'wp_posts', 'rows' => 10, 'size_kb' => 5),
        'options' => array('table_name' => 'wp_options', 'rows' => 5, 'size_kb' => 2),
        'custom' => array('table_name' => 'wp_custom', 'rows' => 2, 'size_kb' => 1),
    ),
    'images' => array(),
);
$destination = array(
    'tables' => array(
        'posts' => array('table_name' => 'wp_posts', 'rows' => 9, 'size_kb' => 5),
        'options' => array('table_name' => 'wp_options', 'rows' => 6, 'size_kb' => 2),
    ),
    'images' => array(),
);
$database = twmcd_compare_database_report($source, $destination);
if (1 !== count($database['native']) || empty($database['native'][0]['default_selected'])) {
    fwrite(STDERR, "FAIL: changed native table was not recommended.\n");
    exit(1);
}
if (1 !== count($database['custom']) || !empty($database['custom'][0]['default_selected'])) {
    fwrite(STDERR, "FAIL: custom table recommendation was incorrect.\n");
    exit(1);
}

$options = twmcd_compare_options_inventories(
    array('options' => array('same' => 'a', 'changed' => 'b', 'source' => 'c', 'home' => 'd')),
    array('options' => array('same' => 'a', 'changed' => 'x', 'destination' => 'y', 'home' => 'z'))
);
if (4 !== count($options[0]['options'])) {
    fwrite(STDERR, "FAIL: Options comparison did not return only differences.\n");
    exit(1);
}
$by_name = array();
foreach ($options[0]['options'] as $option) {
    $by_name[$option['name']] = $option;
}
if (empty($by_name['changed']['default_selected']) || empty($by_name['source']['default_selected'])) {
    fwrite(STDERR, "FAIL: source Options differences were not recommended.\n");
    exit(1);
}
if (!empty($by_name['destination']['default_selected']) || empty($by_name['home']['ignored']) || !empty($by_name['home']['default_selected'])) {
    fwrite(STDERR, "FAIL: Options recommendation rules were incorrect.\n");
    exit(1);
}
if (!twmcd_is_transient_option('_transient_example') || !twmcd_is_transient_option('_site_transient_timeout_example')) {
    fwrite(STDERR, "FAIL: transient exclusion was incorrect.\n");
    exit(1);
}

echo "PASS: database and Options report logic.\n";
