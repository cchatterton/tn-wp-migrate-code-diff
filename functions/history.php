<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_release_history_table()
{
    global $wpdb;

    return $wpdb->base_prefix . 'twmcd_release_history';
}

function twmcd_install_release_history_table()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = twmcd_release_history_table();
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        release_key char(64) NOT NULL,
        release_id varchar(191) NOT NULL,
        release_type varchar(20) NOT NULL DEFAULT 'release',
        source_url varchar(255) NOT NULL DEFAULT '',
        destination_url varchar(255) NOT NULL DEFAULT '',
        package_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        package_user_name varchar(191) NOT NULL DEFAULT '',
        upload_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        upload_user_name varchar(191) NOT NULL DEFAULT '',
        rollback_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        rollback_user_name varchar(191) NOT NULL DEFAULT '',
        package_created_at datetime DEFAULT NULL,
        rollback_created_at datetime DEFAULT NULL,
        installed_at datetime DEFAULT NULL,
        rollback_created tinyint(1) NOT NULL DEFAULT 0,
        status varchar(32) NOT NULL DEFAULT 'package_created',
        added longtext NOT NULL,
        updated longtext NOT NULL,
        removed longtext NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY release_key (release_key),
        KEY release_id (release_id(100)),
        KEY installed_at (installed_at)
    ) {$charset_collate};";

    dbDelta($sql);
    update_site_option('twmcd_history_schema_version', 1);
}

function twmcd_maybe_install_release_history_table()
{
    if (1 > (int) get_site_option('twmcd_history_schema_version', 0)) {
        twmcd_install_release_history_table();
    }
}

function twmcd_current_release_user()
{
    $user = wp_get_current_user();

    return array(
        'id'           => isset($user->ID) ? (int) $user->ID : 0,
        'login'        => isset($user->user_login) ? sanitize_user($user->user_login) : '',
        'display_name' => isset($user->display_name) ? sanitize_text_field($user->display_name) : '',
    );
}

function twmcd_release_history_user_name($user)
{
    if (!is_array($user)) {
        return '';
    }
    if (!empty($user['display_name'])) {
        return sanitize_text_field($user['display_name']);
    }

    return isset($user['login']) ? sanitize_user($user['login']) : '';
}

function twmcd_release_history_key($manifest)
{
    return hash(
        'sha256',
        (isset($manifest['release_id']) ? $manifest['release_id'] : '') . '|' .
        (isset($manifest['source_url']) ? $manifest['source_url'] : '') . '|' .
        (isset($manifest['created_at']) ? $manifest['created_at'] : '')
    );
}

function twmcd_release_history_changes($manifest)
{
    $changes = array('added' => array(), 'updated' => array(), 'removed' => array());

    foreach ((array) (isset($manifest['packages']) ? $manifest['packages'] : array()) as $package) {
        $from_version = isset($package['from_version']) ? (string) $package['from_version'] : '';
        $to_version = isset($package['version']) ? (string) $package['version'] : '';
        $change = array(
            'type'         => isset($package['type']) ? $package['type'] : '',
            'name'         => isset($package['name']) ? $package['name'] : '',
            'destination'  => isset($package['destination']) ? $package['destination'] : '',
            'from_version' => $from_version,
            'to_version'   => $to_version,
        );
        $changes['' === $from_version ? 'added' : 'updated'][] = $change;
    }

    foreach ((array) (isset($manifest['remove_packages']) ? $manifest['remove_packages'] : array()) as $package) {
        $changes['removed'][] = array(
            'type'         => isset($package['type']) ? $package['type'] : '',
            'name'         => isset($package['name']) ? $package['name'] : '',
            'destination'  => isset($package['destination']) ? $package['destination'] : '',
            'from_version' => isset($package['version']) ? (string) $package['version'] : '',
            'to_version'   => '',
        );
    }

    foreach ((array) (isset($manifest['posts']) ? $manifest['posts'] : array()) as $post) {
        $from_fingerprint = isset($post['from_fingerprint']) ? (string) $post['from_fingerprint'] : '';
        $change = array(
            'type'         => 'post:' . (isset($post['post_type']) ? $post['post_type'] : ''),
            'name'         => isset($post['title']) ? $post['title'] : '',
            'destination'  => isset($post['identity']) ? $post['identity'] : '',
            'from_version' => $from_fingerprint ? substr($from_fingerprint, 0, 12) : '',
            'to_version'   => !empty($post['fingerprint']) ? substr($post['fingerprint'], 0, 12) : '',
        );
        $changes['' === $from_fingerprint ? 'added' : 'updated'][] = $change;
    }

    foreach ((array) (isset($manifest['remove_posts']) ? $manifest['remove_posts'] : array()) as $post) {
        $changes['removed'][] = array(
            'type'         => 'post:' . (isset($post['post_type']) ? $post['post_type'] : ''),
            'name'         => isset($post['title']) ? $post['title'] : '',
            'destination'  => isset($post['identity']) ? $post['identity'] : '',
            'from_version' => !empty($post['from_fingerprint']) ? substr($post['from_fingerprint'], 0, 12) : '',
            'to_version'   => '',
        );
    }

    return $changes;
}

function twmcd_record_release_history($manifest, $event)
{
    global $wpdb;

    if (!is_array($manifest) || empty($manifest['release_id'])) {
        return false;
    }

    twmcd_maybe_install_release_history_table();
    $table = twmcd_release_history_table();
    $release_key = twmcd_release_history_key($manifest);
    $existing = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE release_key = %s LIMIT 1", $release_key),
        ARRAY_A
    );
    $now = current_time('mysql', true);
    $package_user = isset($manifest['created_by']) && is_array($manifest['created_by'])
        ? $manifest['created_by']
        : array();
    $event_user = twmcd_current_release_user();
    $changes = twmcd_release_history_changes($manifest);
    $data = array(
        'release_key'       => $release_key,
        'release_id'        => sanitize_text_field($manifest['release_id']),
        'release_type'      => twmcd_release_is_rollback($manifest['release_id']) ? 'rollback' : 'release',
        'source_url'        => isset($manifest['source_url']) ? esc_url_raw($manifest['source_url']) : '',
        'destination_url'   => isset($manifest['destination_url']) ? esc_url_raw($manifest['destination_url']) : '',
        'package_user_id'   => isset($package_user['id']) ? absint($package_user['id']) : 0,
        'package_user_name' => twmcd_release_history_user_name($package_user),
        'package_created_at' => !empty($manifest['created_at']) ? gmdate('Y-m-d H:i:s', strtotime($manifest['created_at'])) : $now,
        'status'            => sanitize_key($event),
        'added'             => wp_json_encode($changes['added']),
        'updated'           => wp_json_encode($changes['updated']),
        'removed'           => wp_json_encode($changes['removed']),
        'updated_at'        => $now,
    );

    if ('rollback_created' === $event) {
        $data['rollback_created'] = 1;
        $data['rollback_created_at'] = $now;
        $data['rollback_user_id'] = $event_user['id'];
        $data['rollback_user_name'] = twmcd_release_history_user_name($event_user);
    } elseif ('installed' === $event) {
        $data['installed_at'] = $now;
        $data['upload_user_id'] = $event_user['id'];
        $data['upload_user_name'] = twmcd_release_history_user_name($event_user);
    }

    if ($existing) {
        if (!empty($existing['rollback_created'])) {
            unset($data['rollback_created'], $data['rollback_created_at'], $data['rollback_user_id'], $data['rollback_user_name']);
        }
        return false !== $wpdb->update($table, $data, array('id' => (int) $existing['id']));
    }

    $data = array_merge(
        array(
            'upload_user_id'     => 0,
            'upload_user_name'   => '',
            'rollback_user_id'   => 0,
            'rollback_user_name' => '',
            'rollback_created'   => 0,
            'rollback_created_at' => null,
            'installed_at'       => null,
            'created_at'         => $now,
        ),
        $data
    );

    return false !== $wpdb->insert($table, $data);
}

function twmcd_get_release_history($limit = 200)
{
    global $wpdb;

    twmcd_maybe_install_release_history_table();
    $table = twmcd_release_history_table();
    $limit = max(1, min(500, (int) $limit));

    return $wpdb->get_results("SELECT * FROM {$table} ORDER BY COALESCE(installed_at, rollback_created_at, package_created_at, created_at) DESC, id DESC LIMIT {$limit}", ARRAY_A);
}
