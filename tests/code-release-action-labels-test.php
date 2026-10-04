<?php

$assets = file_get_contents(dirname(__DIR__) . '/functions/assets.php');
$script = file_get_contents(dirname(__DIR__) . '/scripts/tn-wp-migrate-code-diff.js');

if (false === $assets || false === $script) {
    fwrite(STDERR, "FAIL: code comparison assets could not be read.\n");
    exit(1);
}

$expected_labels = array(
    "'releaseActions'   => __('Release Actions'" => 'release-action column heading',
    "'same'             => __('No Change'" => 'same-version action',
    "'sourceNewer'      => __('Upgrade in Destination'" => 'source-upgrade action',
    "'sourceOlder'      => __('Downgrade in Destination'" => 'source-downgrade action',
    "'sourceOnly'       => __('Add to Destination'" => 'source-only action',
    "'destinationOnly'  => __('Remove from Destination'" => 'destination-only action',
    "'unknownVersion'   => __('Review Manually'" => 'unknown-version action',
);

foreach ($expected_labels as $needle => $description) {
    if (false === strpos($assets, $needle)) {
        fwrite(STDERR, "FAIL: missing {$description}.\n");
        exit(1);
    }
}

if (false === strpos($script, 'TWMCD_ADMIN.labels.releaseActions')
    || false !== strpos($script, '<th scope="col">Version status</th>')) {
    fwrite(STDERR, "FAIL: code comparison did not render the Release Actions heading.\n");
    exit(1);
}

echo "PASS: code comparison presents destination release actions.\n";
