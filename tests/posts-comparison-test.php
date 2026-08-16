<?php

define('ABSPATH', __DIR__ . '/');

function apply_filters($hook, $value)
{
    return $value;
}

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

echo "PASS: Posts comparison grouping and recommendations.\n";
