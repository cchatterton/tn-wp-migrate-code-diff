<?php

$script = file_get_contents(dirname(__DIR__) . '/scripts/tn-wp-migrate-code-diff-integration.js');
if (false === $script) {
    fwrite(STDERR, "FAIL: integration script could not be read.\n");
    exit(1);
}

$expectations = array(
    "document.querySelector('#root .migrate-notice.warning')" => 'WP Migrate notice placement',
    "root.querySelector('.wrapper.migrate')" => 'single-site migration-form fallback placement',
    "root.querySelector('.nav-wrap')" => 'multisite navigation anchor',
    'navigation.nextElementSibling' => 'multisite content-panel placement',
    "root.querySelector('.wrapper')" => 'generic WP Migrate content fallback',
    "noticeSlotId = 'twmcd-integration-notice-slot'" => 'dedicated fallback slot',
    'migrationPanel.parentNode.insertBefore(noticeSlot, migrationPanel)' => 'fallback insertion before the migration form',
    'class="button-link twmcd-notice-refresh' => 'always-visible refresh control',
    'function retryConnection()' => 'connection retry handler',
    "document.querySelectorAll('#connect button" => 'native WP Migrate connection control lookup',
    'restartPolling();' => 'WP Migrate state polling restart',
);
foreach ($expectations as $needle => $description) {
    if (false === strpos($script, $needle)) {
        fwrite(STDERR, "FAIL: missing {$description}.\n");
        exit(1);
    }
}

if (preg_match('/if \(!wpMigrateNotice \|\| !wpMigrateNotice\.parentNode\) \{\s*return;\s*\}/', $script)) {
    fwrite(STDERR, "FAIL: integration control still requires the WP Migrate update notice.\n");
    exit(1);
}

echo "PASS: integration notice has update-notice and independent fallback placement.\n";
