<?php

define('ABSPATH', __DIR__ . '/');

$deleted_site_options = array();
$nonce_checked = false;

function check_ajax_referer($action, $field)
{
    global $nonce_checked;
    $nonce_checked = 'twmcd_admin' === $action && 'nonce' === $field;
}

function current_user_can($capability)
{
    return 'export' === $capability;
}

function twmcd_admin_capability()
{
    return 'export';
}

function delete_site_option($name)
{
    global $deleted_site_options;
    $deleted_site_options[] = $name;
    return true;
}

function __($text)
{
    return $text;
}

class TWMCD_Test_Json_Response extends Exception
{
    public $payload;

    public function __construct($payload)
    {
        parent::__construct('JSON response');
        $this->payload = $payload;
    }
}

function wp_send_json_success($data)
{
    throw new TWMCD_Test_Json_Response($data);
}

function wp_send_json_error($data, $status = null)
{
    throw new TWMCD_Test_Json_Response(array('error' => $data, 'status' => $status));
}

require dirname(__DIR__) . '/functions/ajax.php';

try {
    twmcd_ajax_clear_recent_migrations();
    fwrite(STDERR, "FAIL: reset action did not return a JSON response.\n");
    exit(1);
} catch (TWMCD_Test_Json_Response $response) {
    if (!$nonce_checked
        || array('wpmdb_recent_migrations') !== $deleted_site_options
        || 'The unsaved profile history was cleared.' !== $response->payload['message']) {
        fwrite(STDERR, "FAIL: reset action did not securely clear WP Migrate recent migrations.\n");
        exit(1);
    }
}

echo "PASS: authenticated unsaved-profile history reset.\n";
