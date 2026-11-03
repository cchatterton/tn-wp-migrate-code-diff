<?php

$assets = file_get_contents(dirname(__DIR__) . '/functions/assets.php');
$script = file_get_contents(dirname(__DIR__) . '/scripts/tn-wp-migrate-posts-diff.js');

if (false === $assets || false === $script) {
    fwrite(STDERR, "FAIL: Content comparison assets could not be read.\n");
    exit(1);
}

$expected_labels = array(
    "'releaseActions'   => __('Release Actions'" => 'release-action column heading',
    "'same'             => __('No Change'" => 'same-content action',
    "'different'        => __('Update in Destination'" => 'changed-content action',
    "'sourceOnly'       => __('Add to Destination'" => 'source-only action',
    "'destinationOnly'  => __('Remove from Destination'" => 'destination-only action',
);

foreach ($expected_labels as $needle => $description) {
    if (false === strpos($assets, $needle)) {
        fwrite(STDERR, "FAIL: missing Content {$description}.\n");
        exit(1);
    }
}

if (false === strpos($script, 'TWMCD_POSTS_ADMIN.labels.releaseActions')
    || false !== strpos($script, '<th>Status</th>')) {
    fwrite(STDERR, "FAIL: Content comparison did not render the Release Actions heading.\n");
    exit(1);
}

echo "PASS: Content comparison presents destination release actions.\n";
