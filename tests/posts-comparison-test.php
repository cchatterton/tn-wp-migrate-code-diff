<?php

define('ABSPATH', __DIR__ . '/');

function apply_filters($hook, $value)
{
    return $value;
}

function wp_get_upload_dir() { return array('baseurl' => 'https://source.example/wp-content/uploads', 'basedir' => '/nas/content/live/source/wp-content/uploads'); }
function content_url() { return 'https://source.example/wp-content'; }
function site_url() { return 'https://source.example'; }
function home_url() { return 'https://source.example'; }
function wp_normalize_path($path) { return str_replace('\\', '/', $path); }
function wp_json_encode($value) { return json_encode($value); }

require dirname(__DIR__) . '/functions/posts-comparison.php';

$source = array(
    'posts' => array(
        'page:path:about' => array(
            'identity' => 'page:path:about', 'portable' => true, 'source_id' => 10,
            'post_type' => 'page', 'title' => 'About', 'status' => 'publish', 'fingerprint' => str_repeat('a', 64),
        ),
        'post:path:new' => array(
            'identity' => 'post:path:new', 'portable' => true, 'source_id' => 11,
            'post_type' => 'post', 'title' => 'New', 'status' => 'publish', 'fingerprint' => str_repeat('b', 64),
        ),
        'page:id:99' => array(
            'identity' => 'page:id:99', 'portable' => false, 'source_id' => 99,
            'post_type' => 'page', 'title' => 'No slug', 'status' => 'draft', 'fingerprint' => str_repeat('c', 64),
        ),
    ),
);
$destination = array(
    'posts' => array(
        'page:path:about' => array(
            'identity' => 'page:path:about', 'portable' => true, 'source_id' => 20,
            'post_type' => 'page', 'title' => 'About', 'status' => 'publish', 'fingerprint' => str_repeat('d', 64),
        ),
        'post:path:old' => array(
            'identity' => 'post:path:old', 'portable' => true, 'source_id' => 21,
            'post_type' => 'post', 'title' => 'Old', 'status' => 'publish', 'fingerprint' => str_repeat('e', 64),
        ),
    ),
);

$groups = twmcd_compare_posts_inventories($source, $destination);
$by_identity = array();
foreach ($groups as $posts) {
    foreach ($posts as $post) {
        $by_identity[$post['identity']] = $post;
    }
}
if ('different' !== $by_identity['page:path:about']['status'] || empty($by_identity['page:path:about']['default_selected'])) {
    fwrite(STDERR, "FAIL: changed portable page was not recommended.\n");
    exit(1);
}
if ('source_only' !== $by_identity['post:path:new']['status'] || empty($by_identity['post:path:new']['default_selected'])) {
    fwrite(STDERR, "FAIL: source-only post was not recommended.\n");
    exit(1);
}
if ('destination_only' !== $by_identity['post:path:old']['status'] || !empty($by_identity['post:path:old']['default_selected'])) {
    fwrite(STDERR, "FAIL: destination-only post removal was recommended.\n");
    exit(1);
}
if (!empty($by_identity['page:id:99']['selection']) || !empty($by_identity['page:id:99']['default_selected'])) {
    fwrite(STDERR, "FAIL: non-portable ID-only content was selectable.\n");
    exit(1);
}

$source_environment = array(
    'https://source.example/wp-content/uploads' => '{{TWMCD_UPLOADS_URL}}',
    'https:\\/\\/source.example\\/wp-content\\/uploads' => '{{TWMCD_UPLOADS_URL}}',
    'https%3A%2F%2Fsource.example%2Fwp-content%2Fuploads' => '{{TWMCD_UPLOADS_URL}}',
    '/nas/content/live/source/wp-content/uploads' => '{{TWMCD_UPLOADS_PATH}}',
    'https://source.example' => '{{TWMCD_SITE_URL}}',
);
$destination_environment = array(
    'https://destination.example/wp-content/uploads' => '{{TWMCD_UPLOADS_URL}}',
    'https:\\/\\/destination.example\\/wp-content\\/uploads' => '{{TWMCD_UPLOADS_URL}}',
    'https%3A%2F%2Fdestination.example%2Fwp-content%2Fuploads' => '{{TWMCD_UPLOADS_URL}}',
    '/nas/content/live/destination/wp-content/uploads' => '{{TWMCD_UPLOADS_PATH}}',
    'https://destination.example' => '{{TWMCD_SITE_URL}}',
);
$source_clone_value = array(
    'content' => '<img src="https://source.example/wp-content/uploads/2026/08/image.jpg">',
    'meta' => array(
        'json' => '{"url":"https:\\/\\/source.example\\/wp-content\\/uploads\\/2026\\/08\\/image.jpg"}',
        'encoded' => 'https%3A%2F%2Fsource.example%2Fwp-content%2Fuploads%2F2026%2F08%2Fimage.jpg',
        'path' => '/nas/content/live/source/wp-content/uploads/2026/08/image.jpg',
    ),
);
$destination_clone_value = array(
    'content' => '<img src="https://destination.example/wp-content/uploads/2026/08/image.jpg">',
    'meta' => array(
        'json' => '{"url":"https:\\/\\/destination.example\\/wp-content\\/uploads\\/2026\\/08\\/image.jpg"}',
        'encoded' => 'https%3A%2F%2Fdestination.example%2Fwp-content%2Fuploads%2F2026%2F08%2Fimage.jpg',
        'path' => '/nas/content/live/destination/wp-content/uploads/2026/08/image.jpg',
    ),
);
$normalized_source = twmcd_normalize_post_environment_values($source_clone_value, $source_environment);
$normalized_destination = twmcd_normalize_post_environment_values($destination_clone_value, $destination_environment);
if ($normalized_source !== $normalized_destination) {
    fwrite(STDERR, "FAIL: cloned environment URLs and paths did not normalize to the same comparison value.\n");
    exit(1);
}
$destination_clone_value['meta']['business_value'] = 'A genuinely changed value';
if ($normalized_source === twmcd_normalize_post_environment_values($destination_clone_value, $destination_environment)) {
    fwrite(STDERR, "FAIL: environment normalization concealed a genuine post content difference.\n");
    exit(1);
}

$source_export = array(
    'identity' => 'card:path:accordion', 'source_id' => 10, 'title' => 'Accordion',
    'portable' => true, 'author_login' => 'source_editor',
    'fields' => array('post_title' => 'Accordion', 'post_content' => 'Updated content'),
    'meta' => array('business_setting' => array('value')),
    'terms' => array(), 'taxonomies' => array(),
);
$destination_export = $source_export;
$destination_export['source_id'] = 99;
$destination_export['author_login'] = 'destination_editor';
if (twmcd_post_content_fingerprint($source_export) !== twmcd_post_content_fingerprint($destination_export)) {
    fwrite(STDERR, "FAIL: destination-local author kept a released post perpetually different.\n");
    exit(1);
}
$destination_export['fields']['post_content'] = 'Different content';
if (twmcd_post_content_fingerprint($source_export) === twmcd_post_content_fingerprint($destination_export)) {
    fwrite(STDERR, "FAIL: genuine content changes were ignored.\n");
    exit(1);
}
$source_parts = twmcd_post_content_component_fingerprints($source_export);
$destination_parts = twmcd_post_content_component_fingerprints($destination_export);
if ($source_parts['fields'] === $destination_parts['fields'] || $source_parts['meta'] !== $destination_parts['meta']) {
    fwrite(STDERR, "FAIL: comparison did not identify the changed post fields.\n");
    exit(1);
}
$diagnostic_groups = twmcd_compare_posts_inventories(
    array('posts' => array('card:path:accordion' => array('post_type' => 'card', 'title' => 'Accordion', 'portable' => true, 'source_id' => 10, 'status' => 'publish', 'fingerprint' => twmcd_post_content_fingerprint($source_export), 'components' => $source_parts))),
    array('posts' => array('card:path:accordion' => array('post_type' => 'card', 'title' => 'Accordion', 'portable' => true, 'source_id' => 99, 'status' => 'publish', 'fingerprint' => twmcd_post_content_fingerprint($destination_export), 'components' => $destination_parts)))
);
if ($diagnostic_groups['card'][0]['difference_parts'] !== array('fields')) {
    fwrite(STDERR, "FAIL: comparison did not show which part of the post differs.\n");
    exit(1);
}
foreach (array('_wp_old_slug', '_wp_old_date') as $key) {
    if (!in_array($key, twmcd_local_post_meta_keys(), true)) {
        fwrite(STDERR, "FAIL: WordPress local history metadata was included in releases.\n");
        exit(1);
    }
}

echo "PASS: Posts comparison grouping, recommendations, and clone normalization.\n";
