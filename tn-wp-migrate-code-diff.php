<?php
/**
 * Plugin Name: WP Migrate - Release Management
 * Plugin URI: https://github.com/cchatterton/tn-wp-migrate-code-diff/releases/latest
 * Description: Compares connected WordPress code and content, and creates or installs selective offline releases.
 * Version: 0.10.0
 * Requires at least: 5.2
 * Requires PHP: 5.6
 * Update URI: https://github.com/cchatterton/tn-wp-migrate-code-diff
 * Author: Techn
 * Author URI: https://techn.com.au
 * Text Domain: tn-wp-migrate-code-diff
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TWMCD_VERSION', '0.10.0');
define('TWMCD_DATABASE_COMPARISON_ENABLED', true);
define('TWMCD_PLUGIN_FILE', __FILE__);
define('TWMCD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TWMCD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TWMCD_PAGE_SLUG', 'tn-wp-migrate-code-diff');
define('TWMCD_DATABASE_PAGE_SLUG', 'tn-wp-migrate-database-diff');
define('TWMCD_OPTIONS_PAGE_SLUG', 'tn-wp-migrate-options-diff');
define('TWMCD_POSTS_PAGE_SLUG', 'tn-wp-migrate-posts-diff');
define('TWMCD_UPLOAD_PAGE_SLUG', 'tn-wp-migrate-upload-release');
define('TWMCD_HISTORY_PAGE_SLUG', 'tn-wp-migrate-release-notes');
define('TWMCD_GITHUB_OWNER', 'cchatterton');
define('TWMCD_GITHUB_REPOSITORY', 'tn-wp-migrate-code-diff');
define('TWMCD_GITHUB_ASSET', 'tn-wp-migrate-code-diff.zip');
define('TWMCD_GITHUB_RELEASE_TRANSIENT', 'twmcd_github_latest_release');
define('TWMCD_GITHUB_ERROR_TRANSIENT', 'twmcd_github_latest_release_error');

require_once TWMCD_PLUGIN_DIR . 'functions/helpers.php';
require_once TWMCD_PLUGIN_DIR . 'functions/history.php';
require_once TWMCD_PLUGIN_DIR . 'functions/comparison.php';
require_once TWMCD_PLUGIN_DIR . 'functions/options-comparison.php';
require_once TWMCD_PLUGIN_DIR . 'functions/posts-comparison.php';
require_once TWMCD_PLUGIN_DIR . 'functions/profile.php';
require_once TWMCD_PLUGIN_DIR . 'functions/release-package.php';
require_once TWMCD_PLUGIN_DIR . 'functions/post-release.php';
require_once TWMCD_PLUGIN_DIR . 'functions/release-installer.php';
require_once TWMCD_PLUGIN_DIR . 'functions/upload-release.php';
require_once TWMCD_PLUGIN_DIR . 'functions/ajax.php';
require_once TWMCD_PLUGIN_DIR . 'functions/assets.php';
require_once TWMCD_PLUGIN_DIR . 'functions/admin.php';
require_once TWMCD_PLUGIN_DIR . 'functions/updater.php';
require_once TWMCD_PLUGIN_DIR . 'functions/setup.php';
