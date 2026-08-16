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

function twmcd_release_history_effective_date_sql()
{
    return 'COALESCE(installed_at, rollback_created_at, package_created_at, created_at)';
}

function twmcd_release_history_search_sql()
{
    return "CONCAT_WS(' ', release_id, release_type, source_url, destination_url,
        package_user_name, upload_user_name, rollback_user_name, status, added, updated, removed)";
}

function twmcd_sanitize_release_history_month($month)
{
    $month = sanitize_text_field((string) $month);

    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : '';
}

function twmcd_sanitize_release_history_search($search)
{
    return substr(sanitize_text_field((string) $search), 0, 100);
}

function twmcd_get_release_history_months($search = '')
{
    global $wpdb;

    twmcd_maybe_install_release_history_table();
    $table = twmcd_release_history_table();
    $effective_date = twmcd_release_history_effective_date_sql();
    $month_rows = $wpdb->get_results(
        "SELECT DATE_FORMAT({$effective_date}, '%Y-%m') AS month_key, COUNT(*) AS release_count
        FROM {$table}
        GROUP BY month_key
        ORDER BY month_key DESC",
        ARRAY_A
    );
    $matches = array();
    $search = twmcd_sanitize_release_history_search($search);
    if ('' !== $search) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $search_sql = twmcd_release_history_search_sql();
        $match_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT({$effective_date}, '%%Y-%%m') AS month_key, COUNT(*) AS match_count
                FROM {$table}
                WHERE {$search_sql} LIKE %s
                GROUP BY month_key",
                $like
            ),
            ARRAY_A
        );
        foreach ((array) $match_rows as $match_row) {
            $matches[$match_row['month_key']] = (int) $match_row['match_count'];
        }
    }

    $months = array();
    foreach ((array) $month_rows as $month_row) {
        $month_key = twmcd_sanitize_release_history_month($month_row['month_key']);
        if ('' === $month_key) {
            continue;
        }
        $months[] = array(
            'key'     => $month_key,
            'label'   => date_i18n('F Y', strtotime($month_key . '-01 12:00:00 UTC')),
            'total'   => (int) $month_row['release_count'],
            'matches' => isset($matches[$month_key]) ? $matches[$month_key] : 0,
        );
    }

    return $months;
}

function twmcd_get_release_history_month($month)
{
    global $wpdb;

    $month = twmcd_sanitize_release_history_month($month);
    if ('' === $month) {
        return array();
    }

    twmcd_maybe_install_release_history_table();
    $table = twmcd_release_history_table();
    $effective_date = twmcd_release_history_effective_date_sql();
    $start = $month . '-01 00:00:00';
    $end = gmdate('Y-m-d H:i:s', strtotime($start . ' +1 month'));

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE {$effective_date} >= %s AND {$effective_date} < %s
            ORDER BY {$effective_date} DESC, id DESC",
            $start,
            $end
        ),
        ARRAY_A
    );
}

function twmcd_render_release_history_month($history_rows)
{
    ob_start();
    require TWMCD_PLUGIN_DIR . 'templates/release-history-month.php';

    return ob_get_clean();
}

function twmcd_ajax_get_release_history_month()
{
    check_ajax_referer('twmcd_release_history', 'nonce');
    if (!current_user_can(twmcd_release_install_capability())) {
        wp_send_json_error(array('message' => __('You do not have permission to view release notes.', 'tn-wp-migrate-code-diff')), 403);
    }

    $month = isset($_POST['month']) ? twmcd_sanitize_release_history_month(wp_unslash($_POST['month'])) : '';
    if ('' === $month) {
        wp_send_json_error(array('message' => __('The requested release month is invalid.', 'tn-wp-migrate-code-diff')), 400);
    }

    $history_rows = twmcd_get_release_history_month($month);
    wp_send_json_success(array('html' => twmcd_render_release_history_month($history_rows)));
}

function twmcd_ajax_search_release_history()
{
    check_ajax_referer('twmcd_release_history', 'nonce');
    if (!current_user_can(twmcd_release_install_capability())) {
        wp_send_json_error(array('message' => __('You do not have permission to search release notes.', 'tn-wp-migrate-code-diff')), 403);
    }

    $search = isset($_POST['search']) ? twmcd_sanitize_release_history_search(wp_unslash($_POST['search'])) : '';
    $months = twmcd_get_release_history_months($search);
    $matches = array();
    $total = 0;
    foreach ($months as $month) {
        $matches[$month['key']] = $month['matches'];
        $total += $month['matches'];
    }

    wp_send_json_success(array('matches' => $matches, 'total' => $total));
}
