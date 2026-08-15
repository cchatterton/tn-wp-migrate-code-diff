<?php

define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/tncri-content-' . uniqid('', true));
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
define('WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins');
define('MB_IN_BYTES', 1048576);
define('GB_IN_BYTES', 1073741824);
define('FS_CHMOD_DIR', 0755);
define('TNCRI_PLUGIN_FILE', WP_PLUGIN_DIR . '/tn-code-release-installer/tn-code-release-installer.php');

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

function plugin_basename($path)
{
    return 'tn-code-release-installer/tn-code-release-installer.php';
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

class TNCRI_Test_Filesystem
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
require dirname(__DIR__) . '/release-installer/tn-code-release-installer/functions/installer.php';

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "SKIP: ZipArchive is unavailable.\n");
    exit(0);
}

$temporary_directory = sys_get_temp_dir() . '/tncri-test-' . uniqid('', true);
$plugin_directory = $temporary_directory . '/sample-plugin';
mkdir($plugin_directory . '/assets', 0777, true);
mkdir($plugin_directory . '/node_modules', 0777, true);
file_put_contents($plugin_directory . '/sample-plugin.php', "<?php\n/* Plugin Name: Sample */\n");
file_put_contents($plugin_directory . '/assets/example.js', "console.log('sample');\n");
file_put_contents($plugin_directory . '/node_modules/excluded.js', "excluded\n");

$zip_path = $temporary_directory . '/release.zip';
$zip = new ZipArchive();
$zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$files = twmcd_add_release_path_to_zip($zip, $plugin_directory, 'payload/plugins/sample-plugin');
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
    ),
    'files' => $files,
);
$zip->addFromString('manifest.json', json_encode($manifest));
$zip->close();

$zip = new ZipArchive();
$zip->open($zip_path);
$validation = tncri_validate_release_archive($zip);
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
if (is_wp_error(tncri_validate_extracted_files($workspace, $manifest))) {
    fwrite(STDERR, "FAIL: valid extracted checksums were rejected.\n");
    exit(1);
}

mkdir(WP_PLUGIN_DIR, 0777, true);
mkdir(WP_CONTENT_DIR . '/themes', 0777, true);
mkdir(WPMU_PLUGIN_DIR, 0777, true);
mkdir(WP_PLUGIN_DIR . '/sample-plugin', 0777, true);
file_put_contents(WP_PLUGIN_DIR . '/sample-plugin/old.php', "old\n");
$GLOBALS['wp_filesystem'] = new TNCRI_Test_Filesystem();
$installed = tncri_install_manifest_packages($workspace, $manifest);
if (is_wp_error($installed)
    || !is_file(WP_PLUGIN_DIR . '/sample-plugin/sample-plugin.php')
    || is_file(WP_PLUGIN_DIR . '/sample-plugin/old.php')) {
    fwrite(STDERR, "FAIL: validated package was not installed over the existing plugin.\n");
    exit(1);
}

$unsafe_zip_path = $temporary_directory . '/unsafe.zip';
$zip = new ZipArchive();
$zip->open($unsafe_zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('manifest.json', '{}');
$zip->addFromString('../unsafe.php', '<?php');
$zip->close();
$zip->open($unsafe_zip_path);
$unsafe_validation = tncri_validate_release_archive($zip);
$zip->close();
if (!is_wp_error($unsafe_validation)) {
    fwrite(STDERR, "FAIL: unsafe archive path was accepted.\n");
    exit(1);
}

$GLOBALS['wp_filesystem']->delete($temporary_directory, true);
$GLOBALS['wp_filesystem']->delete(WP_CONTENT_DIR, true);
echo "PASS: release format and unsafe path validation.\n";
