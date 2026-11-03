<?php

define('ABSPATH', __DIR__ . '/');
define('OBJECT', 'OBJECT');

class WP_Error
{
    private $code;
    public function __construct($code, $message) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}

class WP_Hook
{
    public $callbacks = array();
}

class WP_Post_Lock_Functionality
{
    public function prevent_post_edit($data, $postarr)
    {
        if (!empty($postarr['ID']) && get_post_meta($postarr['ID'], '_locked_by_user', true) !== get_current_user_id()) {
            throw new RuntimeException('Post Lock blocked the update.');
        }
        return $data;
    }
}

$wp_filter = array();
$allow_release = true;
$unrelated_calls = 0;

function add_filter($hook, $handler, $priority = 10, $accepted_args = 1)
{
    global $wp_filter;
    if (!isset($wp_filter[$hook])) { $wp_filter[$hook] = new WP_Hook(); }
    $id = is_array($handler) ? spl_object_hash($handler[0]) . $handler[1] : $handler;
    $wp_filter[$hook]->callbacks[$priority][$id] = array('function' => $handler, 'accepted_args' => $accepted_args);
}

function remove_filter($hook, $handler, $priority = 10)
{
    global $wp_filter;
    $id = is_array($handler) ? spl_object_hash($handler[0]) . $handler[1] : $handler;
    unset($wp_filter[$hook]->callbacks[$priority][$id]);
}

function apply_filters($hook, $value, $postarr)
{
    global $wp_filter;
    foreach ($wp_filter[$hook]->callbacks as $callbacks) {
        foreach ($callbacks as $callback) {
            $value = call_user_func($callback['function'], $value, $postarr);
        }
    }
    return $value;
}

function is_wp_error($value) { return $value instanceof WP_Error; }
function __($text) { return $text; }
function current_user_can($capability) { global $allow_release; return $capability !== 'update_plugins' || $allow_release; }
function twmcd_release_install_capability() { return 'update_plugins'; }
function get_current_user_id() { return 7; }
function get_post_type_object() { return false; }
function sanitize_key($value) { return $value; }
function sanitize_text_field($value) { return $value; }
function get_page_by_path($path) { return (object) array('ID' => $path === 'locked' ? 21 : 22); }
function get_post($post_id) { return (object) array('ID' => $post_id, 'post_type' => 'page', 'post_name' => 'locked', 'post_parent' => 0, 'post_author' => 7); }
function get_the_title() { return 'Locked page'; }
function get_user_by() { return (object) array('user_login' => 'editor'); }
function get_object_taxonomies() { return array('category'); }
function wp_get_object_terms() { return array(); }
function maybe_unserialize($value) { return $value; }
function get_post_meta($post_id, $key = '', $single = false) {
    return $key === '_locked_by_user' ? 12 : array(
        '_locked_by_user' => array(12),
        '_best_before_date' => array('2026-12-01'),
        '_best_before_action' => array('unlock'),
        'business_meta' => array('keep in release'),
    );
}
function delete_post_meta($post_id, $key) { if ($key !== 'business_meta') { throw new RuntimeException('Destination lock metadata was deleted: ' . $key); } }
function add_post_meta($post_id, $key) { if ($key !== 'business_meta') { throw new RuntimeException('Source lock metadata was imported: ' . $key); } }
function wp_slash($value) { return $value; }
function wp_insert_post($fields) { apply_filters('wp_insert_post_data', $fields, $fields); return $fields['ID']; }
function wp_update_post($fields) { apply_filters('wp_insert_post_data', $fields, $fields); return $fields['ID']; }
function taxonomy_exists() { return false; }
function unrelated_post_filter($data) { global $unrelated_calls; ++$unrelated_calls; return $data; }

require dirname(__DIR__) . '/functions/posts-comparison.php';
require dirname(__DIR__) . '/functions/post-release.php';

$export = twmcd_export_post_content(21);
if (array_keys($export['meta']) !== array('business_meta')) {
    fwrite(STDERR, "FAIL: local lock metadata was included in the release export.\n");
    exit(1);
}

$lock = new WP_Post_Lock_Functionality();
add_filter('wp_insert_post_data', array($lock, 'prevent_post_edit'), 10, 2);
add_filter('wp_insert_post_data', 'unrelated_post_filter', 11, 2);

$post = array(
    'identity' => 'page:path:locked', 'post_type' => 'page', 'title' => 'Locked page',
    'fields' => array('post_title' => 'Updated title'),
    'meta' => array('_locked_by_user' => array(99), '_best_before_date' => array('2027-01-01'), 'business_meta' => array('updated')),
    'terms' => array(),
    'parent_identity' => 'page:path:parent',
);

$result = twmcd_install_manifest_posts(array('posts' => array($post)));
if (is_wp_error($result) || count($result) !== 1 || $unrelated_calls !== 2) {
    fwrite(STDERR, "FAIL: release could not update the locked post and its parent.\n");
    exit(1);
}

try {
    wp_insert_post(array('ID' => 21));
    fwrite(STDERR, "FAIL: ordinary editing bypassed the restored lock.\n");
    exit(1);
} catch (RuntimeException $exception) {
    // The lock remains active after the release.
}

try {
    twmcd_with_post_lock_bypass(function () { throw new RuntimeException('Write failed.'); });
} catch (RuntimeException $exception) {
    // The callback must also be restored when a write fails.
}

try {
    wp_insert_post(array('ID' => 21));
    fwrite(STDERR, "FAIL: lock was not restored after a failed write.\n");
    exit(1);
} catch (RuntimeException $exception) {
}

$allow_release = false;
$denied = twmcd_with_post_lock_bypass(function () { return 21; });
if (!is_wp_error($denied) || $denied->get_error_code() !== 'twmcd_release_capability') {
    fwrite(STDERR, "FAIL: release lock bypass was available without release permission.\n");
    exit(1);
}

echo "PASS: Content release bypasses Post Lock only during authorised writes.\n";
