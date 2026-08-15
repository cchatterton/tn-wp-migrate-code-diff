<?php
/**
 * Plugin Name: TN Code Release Installer
 * Plugin URI: https://github.com/cchatterton/tn-wp-migrate-code-diff/releases/latest
 * Description: Installs manual code release packages created by TN WP Migrate Code Diff.
 * Version: 0.4.1
 * Requires at least: 5.2
 * Requires PHP: 5.6
 * Update URI: https://github.com/cchatterton/tn-wp-migrate-code-diff
 * Author: Techn
 * Author URI: https://techn.com.au
 * Text Domain: tn-code-release-installer
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TNCRI_VERSION', '0.4.1');
define('TNCRI_PLUGIN_FILE', __FILE__);
define('TNCRI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TNCRI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TNCRI_PAGE_SLUG', 'tn-code-release-installer');
define('TNCRI_GITHUB_OWNER', 'cchatterton');
define('TNCRI_GITHUB_REPOSITORY', 'tn-wp-migrate-code-diff');
define('TNCRI_GITHUB_ASSET', 'tn-code-release-installer.zip');
define('TNCRI_GITHUB_RELEASE_TRANSIENT', 'tncri_github_latest_release');
define('TNCRI_GITHUB_ERROR_TRANSIENT', 'tncri_github_latest_release_error');

require_once TNCRI_PLUGIN_DIR . 'functions/installer.php';
require_once TNCRI_PLUGIN_DIR . 'functions/admin.php';
require_once TNCRI_PLUGIN_DIR . 'functions/updater.php';
require_once TNCRI_PLUGIN_DIR . 'functions/setup.php';
