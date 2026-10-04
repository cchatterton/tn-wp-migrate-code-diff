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
    'function injectRecentProfilesReset()' => 'unsaved-profile reset link injection',
    "action: 'twmcd_clear_recent_migrations'" => 'unsaved-profile reset request',
    'window.location.reload();' => 'Profiles screen refresh after reset',
    'function isMigrationScreen()' => 'Migrate-route visibility guard',
    '/^#\\/?migrate(?:$|[/?])/' => 'Migrate hash-route detection',
    'if (!isMigrationScreen())' => 'comparison bar suppression outside Migrate',
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

$styles = file_get_contents(dirname(__DIR__) . '/styles/tn-wp-migrate-code-diff.css');
if (false === strpos($styles, '.twmcd-reset-recent-profiles')
    || false === strpos($styles, 'color: #d58a00;')
    || false === strpos($styles, 'text-decoration: none !important;')) {
    fwrite(STDERR, "FAIL: reset-link alignment or refresh-icon presentation is missing.\n");
    exit(1);
}

echo "PASS: integration controls, placement, profile reset, and refresh styling.\n";
