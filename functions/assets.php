<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_enqueue_admin_assets($hook_suffix)
{
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    $is_comparison_page = TWMCD_PAGE_SLUG === $page;
    $is_database_page = TWMCD_DATABASE_PAGE_SLUG === $page;
    $is_options_page = TWMCD_OPTIONS_PAGE_SLUG === $page;
    $is_posts_page = TWMCD_POSTS_PAGE_SLUG === $page;
    $is_upload_page = TWMCD_UPLOAD_PAGE_SLUG === $page;
    $is_history_page = TWMCD_HISTORY_PAGE_SLUG === $page;
    $is_wp_migrate_page = 'wp-migrate-db-pro' === $page
        || false !== strpos((string) $hook_suffix, 'wp-migrate-db-pro');

    if (!$is_comparison_page && !$is_database_page && !$is_options_page && !$is_posts_page && !$is_upload_page && !$is_history_page && !$is_wp_migrate_page) {
        return;
    }

    wp_enqueue_style(
        'twmcd_admin',
        TWMCD_PLUGIN_URL . 'styles/tn-wp-migrate-code-diff.css',
        array(),
        TWMCD_VERSION
    );

    if ($is_upload_page) {
        wp_enqueue_script(
            'twmcd_upload_release',
            TWMCD_PLUGIN_URL . 'scripts/tn-wp-migrate-upload-release.js',
            array(),
            TWMCD_VERSION,
            true
        );
        wp_localize_script(
            'twmcd_upload_release',
            'TWMCD_UPLOAD',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('twmcd_upload_release'),
                'labels'  => array(
                    'rollbackFailed' => __('The rollback package could not be created.', 'tn-wp-migrate-code-diff'),
                ),
            )
        );
        return;
    }

    if ($is_history_page) {
        return;
    }

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
                    'message'           => __('Site comparison is ready.', 'tn-wp-migrate-code-diff'),
                    'button'            => __('Compare Code', 'tn-wp-migrate-code-diff'),
                    'databaseButton'    => __('Compare Database', 'tn-wp-migrate-code-diff'),
                    'optionsButton'     => __('Compare Options', 'tn-wp-migrate-code-diff'),
                    'postsButton'       => __('Compare Posts', 'tn-wp-migrate-code-diff'),
                    'preparing'         => __('Preparing comparison…', 'tn-wp-migrate-code-diff'),
                    'error'             => __('The comparison could not be prepared.', 'tn-wp-migrate-code-diff'),
                    'waitingStore'      => __('Site comparison is listening — waiting for WP Migrate state.', 'tn-wp-migrate-code-diff'),
                    'waitingConnection' => __('Site comparison is listening — waiting for a WP Migrate connection.', 'tn-wp-migrate-code-diff'),
                    'selectSubsite'     => __('Site comparison is listening — connection detected; waiting on subsite selection.', 'tn-wp-migrate-code-diff'),
                    'waitingProfile'    => __('Site comparison is listening — waiting for the saved profile selections.', 'tn-wp-migrate-code-diff'),
                ),
            )
        );

        return;
    }

    if ($is_database_page) {
        wp_enqueue_script(
            'twmcd_database_admin',
            TWMCD_PLUGIN_URL . 'scripts/tn-wp-migrate-database-diff.js',
            array(),
            TWMCD_VERSION,
            true
        );

        wp_localize_script(
            'twmcd_database_admin',
            'TWMCD_DATABASE_ADMIN',
            array(
                'ajaxUrl'      => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('twmcd_admin'),
                'contextToken' => isset($_GET['twmcd_context']) ? sanitize_key(wp_unslash($_GET['twmcd_context'])) : '',
                'labels'       => array(
                    'comparisonFailed' => __('The database comparison could not be completed.', 'tn-wp-migrate-code-diff'),
                    'same'             => __('Same metrics', 'tn-wp-migrate-code-diff'),
                    'different'        => __('Different metrics', 'tn-wp-migrate-code-diff'),
                    'sourceOnly'       => __('Only on source', 'tn-wp-migrate-code-diff'),
                    'destinationOnly'  => __('Only on destination', 'tn-wp-migrate-code-diff'),
                ),
            )
        );

        return;
    }


    if ($is_options_page) {
        wp_enqueue_script(
            'twmcd_options_admin',
            TWMCD_PLUGIN_URL . 'scripts/tn-wp-migrate-options-diff.js',
            array(),
            TWMCD_VERSION,
            true
        );
        wp_localize_script(
            'twmcd_options_admin',
            'TWMCD_OPTIONS_ADMIN',
            array(
                'ajaxUrl'      => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('twmcd_admin'),
                'contextToken' => isset($_GET['twmcd_context']) ? sanitize_key(wp_unslash($_GET['twmcd_context'])) : '',
                'labels'       => array(
                    'comparisonFailed' => __('The Options comparison could not be completed.', 'tn-wp-migrate-code-diff'),
                    'different'        => __('Different value', 'tn-wp-migrate-code-diff'),
                    'sourceOnly'       => __('Absent on Destination', 'tn-wp-migrate-code-diff'),
                    'destinationOnly'  => __('Absent from source', 'tn-wp-migrate-code-diff'),
                    'ignored'          => __('Ignored', 'tn-wp-migrate-code-diff'),
                ),
            )
        );
        return;
    }

    if ($is_posts_page) {
        wp_enqueue_script(
            'twmcd_posts_admin',
            TWMCD_PLUGIN_URL . 'scripts/tn-wp-migrate-posts-diff.js',
            array(),
            TWMCD_VERSION,
            true
        );
        wp_localize_script(
            'twmcd_posts_admin',
            'TWMCD_POSTS_ADMIN',
            array(
                'ajaxUrl'      => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('twmcd_admin'),
                'contextToken' => isset($_GET['twmcd_context']) ? sanitize_key(wp_unslash($_GET['twmcd_context'])) : '',
                'labels'       => array(
                    'comparisonFailed' => __('The Posts comparison could not be completed.', 'tn-wp-migrate-code-diff'),
                    'same'             => __('Same', 'tn-wp-migrate-code-diff'),
                    'different'        => __('Different', 'tn-wp-migrate-code-diff'),
                    'sourceOnly'       => __('Absent on Destination', 'tn-wp-migrate-code-diff'),
                    'destinationOnly'  => __('Absent from source', 'tn-wp-migrate-code-diff'),
                    'creating'         => __('Creating release package…', 'tn-wp-migrate-code-diff'),
                    'create'           => __('Create release package', 'tn-wp-migrate-code-diff'),
                    'packageFailed'    => __('The Posts release package could not be created.', 'tn-wp-migrate-code-diff'),
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
            'comparisonToken' => isset($_GET['twmcd_comparison']) ? sanitize_key(wp_unslash($_GET['twmcd_comparison'])) : '',
            'labels'  => array(
                'comparisonFailed' => __('The code comparison could not be completed.', 'tn-wp-migrate-code-diff'),
                'saveFailed'       => __('The migration profile could not be saved.', 'tn-wp-migrate-code-diff'),
                'profileSaved'     => __('The release profile was updated automatically.', 'tn-wp-migrate-code-diff'),
                'releasePackageEmpty' => __('Select at least one package operation before creating the release package.', 'tn-wp-migrate-code-diff'),
                'releasePackageCreating' => __('Creating release package…', 'tn-wp-migrate-code-diff'),
                'releasePackageFailed' => __('The release package could not be created.', 'tn-wp-migrate-code-diff'),
                'releasePackageButton' => __('Create release package', 'tn-wp-migrate-code-diff'),
                'profileSelectionApplied' => __('Initial selection loaded from saved profile: %s', 'tn-wp-migrate-code-diff'),
                'savedProfile'       => __('Saved profile', 'tn-wp-migrate-code-diff'),
                'same'             => __('Same version', 'tn-wp-migrate-code-diff'),
                'sourceNewer'      => __('Source is Newer', 'tn-wp-migrate-code-diff'),
                'sourceOlder'      => __('Source is Older', 'tn-wp-migrate-code-diff'),
                'sourceOnly'       => __('Absent on Destination', 'tn-wp-migrate-code-diff'),
                'destinationOnly'  => __('Absent from source', 'tn-wp-migrate-code-diff'),
                'unknownVersion'   => __('Unknown', 'tn-wp-migrate-code-diff'),
                'noInventory'      => __('No package inventory was returned for this group.', 'tn-wp-migrate-code-diff'),
                'differencesFound' => __('top-level code differences found.', 'tn-wp-migrate-code-diff'),
                'activation'       => __('Activation', 'tn-wp-migrate-code-diff'),
                'inactive'         => __('Inactive', 'tn-wp-migrate-code-diff'),
                'siteActive'       => __('Active', 'tn-wp-migrate-code-diff'),
                'networkActive'    => __('Active', 'tn-wp-migrate-code-diff'),
                'activeInNetwork'  => __('Active', 'tn-wp-migrate-code-diff'),
                'alwaysActive'     => __('Always active', 'tn-wp-migrate-code-diff'),
                'notInstalled'     => __('Not installed', 'tn-wp-migrate-code-diff'),
                'unknown'          => __('Unknown', 'tn-wp-migrate-code-diff'),
                'selectAll'        => __('Select all', 'tn-wp-migrate-code-diff'),
                'deselectAll'      => __('Deselect all', 'tn-wp-migrate-code-diff'),
                'recommended'      => __('Recommended', 'tn-wp-migrate-code-diff'),
                'selectedCount'    => __('%d selected', 'tn-wp-migrate-code-diff'),
                'selectedForRelease' => __('Selected for release: %d items', 'tn-wp-migrate-code-diff'),
            ),
        )
    );
}
