<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_enqueue_admin_assets($hook_suffix)
{
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    $is_comparison_page = TWMCD_PAGE_SLUG === $page;
    $is_wp_migrate_page = 'wp-migrate-db-pro' === $page
        || false !== strpos((string) $hook_suffix, 'wp-migrate-db-pro');

    if (!$is_comparison_page && !$is_wp_migrate_page) {
        return;
    }

    wp_enqueue_style(
        'twmcd_admin',
        TWMCD_PLUGIN_URL . 'styles/tn-wp-migrate-code-diff.css',
        array(),
        TWMCD_VERSION
    );

    if ($is_wp_migrate_page) {
        wp_enqueue_script(
            'twmcd_integration',
            TWMCD_PLUGIN_URL . 'scripts/tn-wp-migrate-code-diff-integration.js',
            array(),
            TWMCD_VERSION,
            true
        );

        wp_localize_script(
            'twmcd_integration',
            'TWMCD_INTEGRATION',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('twmcd_admin'),
                'labels'  => array(
                    'message'           => __('Code Diff is listening — ready to compare.', 'tn-wp-migrate-code-diff'),
                    'button'            => __('Compare Code', 'tn-wp-migrate-code-diff'),
                    'databaseButton'    => __('Compare Database', 'tn-wp-migrate-code-diff'),
                    'databaseUnavailable' => __('Database comparison is a separate capability and is not available in this code-only release.', 'tn-wp-migrate-code-diff'),
                    'preparing'         => __('Preparing comparison…', 'tn-wp-migrate-code-diff'),
                    'error'             => __('The comparison could not be prepared.', 'tn-wp-migrate-code-diff'),
                    'waitingStore'      => __('Code Diff is listening — waiting for WP Migrate state.', 'tn-wp-migrate-code-diff'),
                    'waitingConnection' => __('Code Diff is listening — waiting for a WP Migrate connection.', 'tn-wp-migrate-code-diff'),
                    'selectSubsite'     => __('Code Diff is listening — connection detected; waiting on subsite selection.', 'tn-wp-migrate-code-diff'),
                ),
            )
        );

        return;
    }

    wp_enqueue_script(
        'twmcd_admin',
        TWMCD_PLUGIN_URL . 'scripts/tn-wp-migrate-code-diff.js',
        array(),
        TWMCD_VERSION,
        true
    );

    wp_localize_script(
        'twmcd_admin',
        'TWMCD_ADMIN',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('twmcd_admin'),
            'contextToken' => isset($_GET['twmcd_context']) ? sanitize_key(wp_unslash($_GET['twmcd_context'])) : '',
            'labels'  => array(
                'comparisonFailed' => __('The code comparison could not be completed.', 'tn-wp-migrate-code-diff'),
                'saveFailed'       => __('The migration profile could not be saved.', 'tn-wp-migrate-code-diff'),
                'same'             => __('Same version', 'tn-wp-migrate-code-diff'),
                'different'        => __('Different version', 'tn-wp-migrate-code-diff'),
                'sourceOnly'       => __('New on source', 'tn-wp-migrate-code-diff'),
                'destinationOnly'  => __('Absent from source', 'tn-wp-migrate-code-diff'),
                'unknownVersion'   => __('Unknown', 'tn-wp-migrate-code-diff'),
                'noInventory'      => __('No package inventory was returned for this group.', 'tn-wp-migrate-code-diff'),
                'differencesFound' => __('top-level code differences found.', 'tn-wp-migrate-code-diff'),
                'activation'       => __('Activation', 'tn-wp-migrate-code-diff'),
                'inactive'         => __('Inactive', 'tn-wp-migrate-code-diff'),
                'siteActive'       => __('Active on selected site', 'tn-wp-migrate-code-diff'),
                'networkActive'    => __('Network active', 'tn-wp-migrate-code-diff'),
                'activeInNetwork'  => __('Active somewhere in network', 'tn-wp-migrate-code-diff'),
                'alwaysActive'     => __('Always active', 'tn-wp-migrate-code-diff'),
                'notInstalled'     => __('Not installed', 'tn-wp-migrate-code-diff'),
                'unknown'          => __('Unknown', 'tn-wp-migrate-code-diff'),
                'selectAll'        => __('Select all', 'tn-wp-migrate-code-diff'),
                'deselectAll'      => __('Deselect all', 'tn-wp-migrate-code-diff'),
            ),
        )
    );
}
