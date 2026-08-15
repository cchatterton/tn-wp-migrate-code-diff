<?php

define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/tncri-content-' . uniqid('', true));
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
define('WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins');
define('MB_IN_BYTES', 1048576);
define('GB_IN_BYTES', 1073741824);
define('FS_CHMOD_DIR', 0755);
define('TWMCD_PLUGIN_FILE', WP_PLUGIN_DIR . '/tn-wp-migrate-code-diff/tn-wp-migrate-code-diff.php');
define('TWMCD_VERSION', 'test');

class WP_Error
{
    private $message;

    public function __construct($code, $message)
    {
        $this->message = $message;
    }

    public function get_error_message()
    {
        return $this->message;
    }
}

function is_wp_error($value)
{
    return $value instanceof WP_Error;
}

function __($message)
{
    return $message;
}

function wp_normalize_path($path)
{
    return str_replace('\\', '/', $path);
}

function wp_max_upload_size()
{
    return 64 * MB_IN_BYTES;
}

function sanitize_file_name($name)
{
    return preg_replace('/[^A-Za-z0-9._-]/', '', $name);
}

function sanitize_text_field($value)
{
    return trim((string) $value);
}

function plugin_basename($path)
{
    return 'tn-wp-migrate-code-diff/tn-wp-migrate-code-diff.php';
}

function trailingslashit($path)
{
    return rtrim($path, '/\\') . '/';
}

function get_theme_root()
{
    return WP_CONTENT_DIR . '/themes';
}

function current_user_can()
{
    return true;
}

function wp_tempnam($filename)
{
    return tempnam(sys_get_temp_dir(), 'twmcd-');
}

function wp_delete_file($path)
{
    return !file_exists($path) || unlink($path);
}

function wp_json_encode($value, $flags = 0)
{
    return json_encode($value, $flags);
}

function home_url()
{
    return 'https://destination.example';
}

class TWMCD_Test_Filesystem
{
    public function exists($path)
    {
        return file_exists($path);
    }

    public function mkdir($path, $permissions = false)
    {
        return is_dir($path) || mkdir($path, $permissions ? $permissions : 0777, true);
    }

    public function is_writable($path)
    {
        return is_writable($path);
    }

    public function move($source, $destination, $overwrite = false)
    {
        if ($overwrite && file_exists($destination)) {
            $this->delete($destination, true);
        }
        if (!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0777, true);
        }
        return rename($source, $destination);
    }

    public function delete($path, $recursive = false)
    {
        if (is_file($path) || is_link($path)) {
            return unlink($path);
        }
        if (!is_dir($path)) {
            return true;
        }
        foreach (array_diff(scandir($path), array('.', '..')) as $entry) {
            $this->delete($path . '/' . $entry, true);
        }
        return rmdir($path);
    }
}

require dirname(__DIR__) . '/functions/release-package.php';
require dirname(__DIR__) . '/functions/release-installer.php';

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "SKIP: ZipArchive is unavailable.\n");
    exit(0);
}

$selected_operations = twmcd_selected_release_operations(
    array(
        'packages' => array(
            'plugins' => array(
                array(
                    'key' => 'obsolete-plugin/obsolete-plugin.php',
                    'name' => 'Obsolete',
                    'destination_version' => '1.0.0',
                    'selection' => 'twmcd-remove:plugins:obsolete-plugin%2Fobsolete-plugin.php',
                    'selection_operation' => 'remove',
                ),
            ),
        ),
    ),
    array(
        'plugins' => array('twmcd-remove:plugins:obsolete-plugin%2Fobsolete-plugin.php'),
        'themes' => array(),
        'muplugins' => array(),
    )
);
if (is_wp_error($selected_operations)
    || 'plugins/obsolete-plugin' !== $selected_operations['remove_packages'][0]['destination']) {
    fwrite(STDERR, "FAIL: selected destination-only package was not converted to a removal operation.\n");
    exit(1);
}

$temporary_directory = sys_get_temp_dir() . '/tncri-test-' . uniqid('', true);
$plugin_directory = $temporary_directory . '/sample-plugin';
mkdir($plugin_directory . '/assets', 0777, true);
mkdir($plugin_directory . '/node_modules', 0777, true);
file_put_contents($plugin_directory . '/sample-plugin.php', "<?php\n/* Plugin Name: Sample */\n");
file_put_contents($plugin_directory . '/assets/example.js', "console.log('sample');\n");
file_put_contents($plugin_directory . '/node_modules/excluded.js', "excluded\n");
$new_plugin_directory = $temporary_directory . '/new-plugin';
mkdir($new_plugin_directory, 0777, true);
file_put_contents($new_plugin_directory . '/new-plugin.php', "<?php\n/* Plugin Name: New */\n");

$zip_path = $temporary_directory . '/release.zip';
$zip = new ZipArchive();
$zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$files = twmcd_add_release_path_to_zip($zip, $plugin_directory, 'payload/plugins/sample-plugin');
$files = array_merge(
    $files,
    twmcd_add_release_path_to_zip($zip, $new_plugin_directory, 'payload/plugins/new-plugin')
);
$manifest = array(
    'format'     => 'tn-code-release/v1',
    'release_id' => 'Release-Test',
    'packages'   => array(
        array(
            'type'         => 'plugins',
            'name'         => 'Sample',
            'version'      => '1.0.0',
            'archive_path' => 'payload/plugins/sample-plugin',
            'destination'  => 'plugins/sample-plugin',
        ),
        array(
            'type'         => 'plugins',
            'name'         => 'New',
            'version'      => '1.0.0',
            'archive_path' => 'payload/plugins/new-plugin',
            'destination'  => 'plugins/new-plugin',
        ),
    ),
    'remove_packages' => array(
        array(
            'type'        => 'plugins',
            'key'         => 'obsolete-plugin/obsolete-plugin.php',
            'name'        => 'Obsolete',
            'version'     => '1.0.0',
            'destination' => 'plugins/obsolete-plugin',
        ),
    ),
    'files' => $files,
);
$zip->addFromString('manifest.json', json_encode($manifest));
$zip->close();

$zip = new ZipArchive();
$zip->open($zip_path);
$validation = twmcd_validate_release_archive($zip);
$zip->close();
if (is_wp_error($validation)) {
    fwrite(STDERR, 'FAIL: ' . $validation->get_error_message() . "\n");
    exit(1);
}
if (isset($files['payload/plugins/sample-plugin/node_modules/excluded.js'])) {
    fwrite(STDERR, "FAIL: excluded dependency was packaged.\n");
    exit(1);
}

$workspace = $temporary_directory . '/workspace';
mkdir($workspace, 0777, true);
$zip = new ZipArchive();
$zip->open($zip_path);
$zip->extractTo($workspace);
$zip->close();
if (is_wp_error(twmcd_validate_extracted_files($workspace, $manifest))) {
    fwrite(STDERR, "FAIL: valid extracted checksums were rejected.\n");
    exit(1);
}

mkdir(WP_PLUGIN_DIR, 0777, true);
mkdir(WP_CONTENT_DIR . '/themes', 0777, true);
mkdir(WPMU_PLUGIN_DIR, 0777, true);
mkdir(WP_PLUGIN_DIR . '/sample-plugin', 0777, true);
file_put_contents(WP_PLUGIN_DIR . '/sample-plugin/old.php', "old\n");
mkdir(WP_PLUGIN_DIR . '/obsolete-plugin', 0777, true);
file_put_contents(WP_PLUGIN_DIR . '/obsolete-plugin/obsolete-plugin.php', "obsolete\n");
$GLOBALS['wp_filesystem'] = new TWMCD_Test_Filesystem();

$rollback_package = twmcd_create_rollback_release_package($manifest);
if (is_wp_error($rollback_package) || !is_array($rollback_package)) {
    fwrite(STDERR, "FAIL: rollback release package was not created.\n");
    exit(1);
}
$rollback_zip = new ZipArchive();
$rollback_zip->open($rollback_package['path']);
$rollback_validation = twmcd_validate_release_archive($rollback_zip);
$rollback_zip->close();
if (is_wp_error($rollback_validation)
    || 'Release-Test-rollback' !== $rollback_validation['manifest']['release_id']
    || 'plugins/new-plugin' !== $rollback_validation['manifest']['remove_packages'][0]['destination']
    || !isset($rollback_validation['manifest']['files']['payload/plugins/sample-plugin/old.php'])
    || !isset($rollback_validation['manifest']['files']['payload/plugins/obsolete-plugin/obsolete-plugin.php'])) {
    fwrite(STDERR, "FAIL: rollback release did not preserve replacements and record additions.\n");
    exit(1);
}

$installed = twmcd_install_manifest_packages($workspace, $manifest);
if (is_wp_error($installed)
    || !is_file(WP_PLUGIN_DIR . '/sample-plugin/sample-plugin.php')
    || is_file(WP_PLUGIN_DIR . '/sample-plugin/old.php')
    || !is_file(WP_PLUGIN_DIR . '/new-plugin/new-plugin.php')
    || file_exists(WP_PLUGIN_DIR . '/obsolete-plugin')) {
    fwrite(STDERR, "FAIL: validated package was not installed over the existing plugin.\n");
    exit(1);
}

$rollback_workspace = $temporary_directory . '/rollback-workspace';
mkdir($rollback_workspace, 0777, true);
$rollback_zip->open($rollback_package['path']);
$rollback_zip->extractTo($rollback_workspace);
$rollback_zip->close();
$rolled_back = twmcd_install_manifest_packages($rollback_workspace, $rollback_validation['manifest']);
if (is_wp_error($rolled_back)
    || !is_file(WP_PLUGIN_DIR . '/sample-plugin/old.php')
    || is_file(WP_PLUGIN_DIR . '/sample-plugin/sample-plugin.php')
    || file_exists(WP_PLUGIN_DIR . '/new-plugin')
    || !is_file(WP_PLUGIN_DIR . '/obsolete-plugin/obsolete-plugin.php')) {
    fwrite(STDERR, "FAIL: rollback release did not restore replacements and remove additions.\n");
    exit(1);
}
wp_delete_file($rollback_package['path']);

if (false !== twmcd_create_rollback_release_package($rollback_validation['manifest'])) {
    fwrite(STDERR, "FAIL: a rollback release attempted to create another rollback.\n");
    exit(1);
}

$remove_only_manifest = array(
    'format' => 'tn-code-release/v1',
    'release_id' => 'Release-Remove-rollback',
    'packages' => array(),
    'remove_packages' => array(
        array(
            'type' => 'plugins',
            'name' => 'New',
            'destination' => 'plugins/new-plugin',
        ),
    ),
    'files' => array(),
);
if (is_wp_error(twmcd_validate_manifest($remove_only_manifest, array()))) {
    fwrite(STDERR, "FAIL: a valid removal-only rollback release was rejected.\n");
    exit(1);
}
$remove_only_manifest['release_id'] = 'Release-Remove';
if (is_wp_error(twmcd_validate_manifest($remove_only_manifest, array()))) {
    fwrite(STDERR, "FAIL: a valid forward removal-only release was rejected.\n");
    exit(1);
}
$remove_only_manifest['remove_packages'][0]['destination'] = 'plugins/../unsafe';
if (!is_wp_error(twmcd_validate_manifest($remove_only_manifest, array()))) {
    fwrite(STDERR, "FAIL: an unsafe package removal destination was accepted.\n");
    exit(1);
}

$unsafe_zip_path = $temporary_directory . '/unsafe.zip';
$zip = new ZipArchive();
$zip->open($unsafe_zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('manifest.json', '{}');
$zip->addFromString('../unsafe.php', '<?php');
$zip->close();
$zip->open($unsafe_zip_path);
$unsafe_validation = twmcd_validate_release_archive($zip);
$zip->close();
if (!is_wp_error($unsafe_validation)) {
    fwrite(STDERR, "FAIL: unsafe archive path was accepted.\n");
    exit(1);
}

$GLOBALS['wp_filesystem']->delete($temporary_directory, true);
$GLOBALS['wp_filesystem']->delete(WP_CONTENT_DIR, true);
echo "PASS: release format and unsafe path validation.\n";
