<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_validate_post_release_selection($state, $selection)
{
    if (!is_array($state) || !is_array($selection)) {
        return new WP_Error('twmcd_invalid_post_selection', __('The selected posts are invalid.', 'tn-wp-migrate-code-diff'));
    }

    $allowed = array();
    foreach ((array) $state['groups'] as $posts) {
        foreach ((array) $posts as $post) {
            if (!empty($post['selection'])) {
                $allowed[$post['selection']] = $post;
            }
        }
    }

    $selected = array();
    foreach (array_unique(array_map('strval', $selection)) as $identity) {
        if (!isset($allowed[$identity])) {
            return new WP_Error('twmcd_invalid_post_selection', __('A selected post did not match the latest comparison.', 'tn-wp-migrate-code-diff'));
        }
        $selected[] = $allowed[$identity];
    }

    return $selected;
}

function twmcd_ajax_prepare_post_release_package()
{
    twmcd_verify_ajax_request();
    twmcd_prepare_long_running_operation();

    $comparison_token = isset($_POST['comparison_token']) ? sanitize_key(wp_unslash($_POST['comparison_token'])) : '';
    $selection = isset($_POST['selection']) ? json_decode(wp_unslash($_POST['selection']), true) : array();
    $state = twmcd_get_posts_comparison($comparison_token);
    $selected = twmcd_validate_post_release_selection($state, $selection);
    if (is_wp_error($selected) || !$selected) {
        $message = is_wp_error($selected)
            ? $selected->get_error_message()
            : __('Select at least one post operation before creating the release package.', 'tn-wp-migrate-code-diff');
        wp_send_json_error(array('message' => $message), 400);
    }
    if (empty($state['context']['intent']) || 'push' !== $state['context']['intent']) {
        wp_send_json_error(array('message' => __('Posts release packages can only be created from a Push comparison.', 'tn-wp-migrate-code-diff')), 400);
    }

    $package = twmcd_create_post_release_package(twmcd_default_release_name(), $state, $selected);
    if (is_wp_error($package)) {
        wp_send_json_error(array('message' => $package->get_error_message()), 500);
    }
    $download_url = twmcd_prepare_release_download($package);
    if (is_wp_error($download_url)) {
        wp_send_json_error(array('message' => $download_url->get_error_message()), 500);
    }

    wp_send_json_success(array('download_url' => $download_url));
}

function twmcd_create_post_release_package($release_name, $state, $selected)
{
    if (!class_exists('ZipArchive')) {
        return new WP_Error('twmcd_zip_unavailable', __('The PHP Zip extension is required to create a release package.', 'tn-wp-migrate-code-diff'));
    }

    $posts = array();
    $remove_posts = array();
    $export_error = null;
    twmcd_with_comparison_blog($state['context'], true, function () use ($selected, &$posts, &$remove_posts, &$export_error) {
        foreach ($selected as $post) {
            if ('remove' === $post['selection_operation']) {
                $remove_posts[] = array(
                    'identity'         => $post['identity'],
                    'post_type'        => $post['post_type'],
                    'title'            => $post['title'],
                    'from_fingerprint' => $post['destination_fingerprint'],
                );
                continue;
            }

            $export = twmcd_export_post_content($post['source_id']);
            if (!$export || $export['identity'] !== $post['identity']) {
                $export_error = new WP_Error('twmcd_post_changed', __('A selected post changed after comparison. Refresh the comparison and try again.', 'tn-wp-migrate-code-diff'));
                return;
            }
            $export['fingerprint'] = twmcd_post_content_fingerprint($export);
            $export['from_fingerprint'] = $post['destination_fingerprint'];
            $posts[] = $export;
        }
    });
    if (is_wp_error($export_error)) {
        return $export_error;
    }

    $manifest = array(
        'format'          => 'tn-code-release/v1',
        'release_id'      => $release_name,
        'created_at'      => gmdate('c'),
        'source_url'      => home_url(),
        'destination_url' => !empty($state['context']['connection']['url']) ? $state['context']['connection']['url'] : '',
        'destination_blog_id' => twmcd_database_inventory_blog_id(
            $state['context'],
            false,
            !empty($state['context']['migration']['remote_is_multisite'])
        ),
        'created_by'      => twmcd_current_release_user(),
        'generator'       => array('plugin' => 'tn-wp-migrate-code-diff', 'version' => TWMCD_VERSION),
        'packages'        => array(),
        'remove_packages' => array(),
        'posts'           => $posts,
        'remove_posts'    => $remove_posts,
        'files'           => array(),
    );

    $zip_path = twmcd_release_tempnam('twmcd-posts-release.zip');
    if (!$zip_path) {
        return new WP_Error('twmcd_temp_file', __('WordPress could not create a temporary release file.', 'tn-wp-migrate-code-diff'));
    }
    $zip = new ZipArchive();
    $opened = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if (true !== $opened) {
        wp_delete_file($zip_path);
        return new WP_Error('twmcd_manifest_write', __('The Posts release manifest could not be written.', 'tn-wp-migrate-code-diff'));
    }
    if (!$zip->addFromString('manifest.json', wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
        $zip->close();
        wp_delete_file($zip_path);
        return new WP_Error('twmcd_manifest_write', __('The Posts release manifest could not be written.', 'tn-wp-migrate-code-diff'));
    }
    $zip->close();

    return array('path' => $zip_path, 'filename' => sanitize_file_name($release_name) . '.zip');
}

function twmcd_find_post_by_identity($identity, $post_type = '')
{
    $identity = (string) $identity;
    if (preg_match('/^([^:]+):uuid:([^:]+)$/', $identity, $matches)) {
        $posts = get_posts(array(
            'post_type' => sanitize_key($matches[1]), 'post_status' => 'any', 'numberposts' => 1,
            'meta_key' => '_twmcd_content_uuid', 'meta_value' => sanitize_key($matches[2]), 'fields' => 'ids',
        ));
        return $posts ? (int) $posts[0] : 0;
    }
    if (preg_match('/^([^:]+):path:(.+)$/', $identity, $matches)) {
        $type = sanitize_key($matches[1]);
        $path = trim((string) $matches[2], '/');
        $object = get_page_by_path($path, OBJECT, $type);
        return $object ? (int) $object->ID : 0;
    }

    return 0;
}

function twmcd_with_manifest_blog($manifest, $callback)
{
    $blog_id = !empty($manifest['destination_blog_id']) ? absint($manifest['destination_blog_id']) : 0;
    if ($blog_id && is_multisite() && !get_site($blog_id)) {
        return new WP_Error('twmcd_destination_site_missing', __('The destination subsite declared by the release does not exist.', 'tn-wp-migrate-code-diff'));
    }
    $switched = $blog_id && is_multisite() && $blog_id !== get_current_blog_id();
    if ($switched) {
        switch_to_blog($blog_id);
    }
    $result = call_user_func($callback);
    if ($switched) {
        restore_current_blog();
    }

    return $result;
}

function twmcd_validate_manifest_post($post)
{
    if (!is_array($post)
        || empty($post['identity'])
        || strlen($post['identity']) > 500
        || empty($post['post_type'])
        || !is_array(isset($post['fields']) ? $post['fields'] : null)
        || !is_array(isset($post['meta']) ? $post['meta'] : null)
        || !is_array(isset($post['terms']) ? $post['terms'] : null)
        || (isset($post['taxonomies']) && !is_array($post['taxonomies']))
        || empty($post['fingerprint'])
        || !preg_match('/^[a-f0-9]{64}$/', $post['fingerprint'])) {
        return new WP_Error('twmcd_manifest_post', __('The release contains an invalid post operation.', 'tn-wp-migrate-code-diff'));
    }
    if (0 !== strpos($post['identity'], sanitize_key($post['post_type']) . ':')) {
        return new WP_Error('twmcd_manifest_post_identity', __('The release contains an invalid post identity.', 'tn-wp-migrate-code-diff'));
    }

    return true;
}

function twmcd_validate_remove_manifest_post($post)
{
    if (!is_array($post) || empty($post['identity']) || strlen($post['identity']) > 500 || empty($post['post_type'])) {
        return new WP_Error('twmcd_manifest_remove_post', __('The release contains an invalid post removal.', 'tn-wp-migrate-code-diff'));
    }

    return true;
}

function twmcd_ensure_release_term($term)
{
    $taxonomy = sanitize_key(isset($term['taxonomy']) ? $term['taxonomy'] : '');
    $slug = sanitize_title(isset($term['slug']) ? $term['slug'] : '');
    if (!$taxonomy || !$slug || !taxonomy_exists($taxonomy)) {
        return 0;
    }
    $existing = get_term_by('slug', $slug, $taxonomy);
    if ($existing) {
        return (int) $existing->term_id;
    }

    $parent_id = 0;
    if (!empty($term['parent_slug'])) {
        $parent = get_term_by('slug', sanitize_title($term['parent_slug']), $taxonomy);
        if (!$parent) {
            return 0;
        }
        $parent_id = (int) $parent->term_id;
    }
    $created = wp_insert_term(
        sanitize_text_field(isset($term['name']) ? $term['name'] : $slug),
        $taxonomy,
        array(
            'slug'        => $slug,
            'description' => isset($term['description']) ? wp_kses_post($term['description']) : '',
            'parent'      => $parent_id,
        )
    );

    return is_wp_error($created) ? 0 : (int) $created['term_id'];
}

function twmcd_replace_content_environment_values($value, $manifest)
{
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = twmcd_replace_content_environment_values($item, $manifest);
        }
        return $value;
    }
    if (!is_string($value) || empty($manifest['source_url'])) {
        return $value;
    }

    return str_replace(
        untrailingslashit($manifest['source_url']),
        untrailingslashit(home_url()),
        $value
    );
}

function twmcd_install_manifest_posts($manifest)
{
    if (!current_user_can('edit_posts')) {
        return new WP_Error('twmcd_post_capability', __('You do not have permission to install posts in this release.', 'tn-wp-migrate-code-diff'));
    }

    $posts = isset($manifest['posts']) ? (array) $manifest['posts'] : array();
    $remove_posts = isset($manifest['remove_posts']) ? (array) $manifest['remove_posts'] : array();
    $identity_map = array();
    $installed = array();

    foreach ($posts as $post) {
        $existing_id = twmcd_find_post_by_identity($post['identity'], $post['post_type']);
        $post_type_object = get_post_type_object($post['post_type']);
        $create_capability = $post_type_object && !empty($post_type_object->cap->create_posts)
            ? $post_type_object->cap->create_posts
            : 'edit_posts';
        $can_edit_existing = !$existing_id
            || ($post_type_object
                ? current_user_can('edit_post', $existing_id)
                : current_user_can('edit_posts'));
        if (!$can_edit_existing
            || (!$existing_id && !current_user_can($create_capability))) {
            return new WP_Error('twmcd_post_edit_capability', sprintf(__('You do not have permission to install “%s”.', 'tn-wp-migrate-code-diff'), $post['title']));
        }
        $publish_capability = $post_type_object && !empty($post_type_object->cap->publish_posts)
            ? $post_type_object->cap->publish_posts
            : 'publish_posts';
        if (!empty($post['fields']['post_status']) && 'publish' === $post['fields']['post_status']
            && !current_user_can($publish_capability)) {
            return new WP_Error('twmcd_post_publish_capability', sprintf(__('You do not have permission to publish “%s”.', 'tn-wp-migrate-code-diff'), $post['title']));
        }
    }
    foreach ($remove_posts as $post) {
        $post_id = twmcd_find_post_by_identity($post['identity'], $post['post_type']);
        $post_type_object = get_post_type_object($post['post_type']);
        $can_delete = !$post_id
            || ($post_type_object
                ? current_user_can('delete_post', $post_id)
                : current_user_can('delete_posts'));
        if (!$can_delete) {
            return new WP_Error('twmcd_post_delete_capability', sprintf(__('You do not have permission to remove “%s”.', 'tn-wp-migrate-code-diff'), $post['title']));
        }
    }

    foreach ($posts as $post) {
        $existing_id = twmcd_find_post_by_identity($post['identity'], $post['post_type']);
        $fields = array_intersect_key(
            $post['fields'],
            array_flip(array('post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_content_filtered', 'menu_order', 'post_mime_type', 'post_date', 'post_date_gmt'))
        );
        $fields = twmcd_replace_content_environment_values($fields, $manifest);
        $fields['post_type'] = sanitize_key($post['post_type']);
        if ($existing_id) {
            $fields['ID'] = $existing_id;
        } else {
            $author = !empty($post['author_login']) ? get_user_by('login', sanitize_user($post['author_login'])) : false;
            $fields['post_author'] = $author ? (int) $author->ID : get_current_user_id();
        }
        $post_id = wp_insert_post(wp_slash($fields), true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        $identity_map[$post['identity']] = (int) $post_id;

        foreach (array_keys(get_post_meta($post_id)) as $meta_key) {
            if (!in_array($meta_key, array('_edit_lock', '_edit_last', '_twmcd_content_uuid'), true)) {
                delete_post_meta($post_id, $meta_key);
            }
        }
        foreach ($post['meta'] as $meta_key => $values) {
            $meta_key = sanitize_text_field((string) $meta_key);
            if (!$meta_key || strlen($meta_key) > 255 || preg_match('/[\x00-\x1F\x7F]/', $meta_key)
                || in_array($meta_key, array('_edit_lock', '_edit_last', '_twmcd_content_uuid'), true)) {
                continue;
            }
            foreach ((array) $values as $value) {
                add_post_meta($post_id, $meta_key, twmcd_replace_content_environment_values($value, $manifest));
            }
        }
        if (preg_match('/^[^:]+:uuid:([^:]+)$/', $post['identity'], $uuid_match)) {
            update_post_meta($post_id, '_twmcd_content_uuid', sanitize_key($uuid_match[1]));
        }

        $pending_terms = array_values($post['terms']);
        do {
            $progress = false;
            foreach ($pending_terms as $term_index => $term) {
                if (twmcd_ensure_release_term($term)) {
                    unset($pending_terms[$term_index]);
                    $progress = true;
                }
            }
        } while ($pending_terms && $progress);

        $term_ids = array();
        foreach ($post['terms'] as $term) {
            if (empty($term['assigned'])) {
                continue;
            }
            $existing_term = get_term_by('slug', sanitize_title($term['slug']), sanitize_key($term['taxonomy']));
            if ($existing_term) {
                $term_ids[sanitize_key($term['taxonomy'])][] = (int) $existing_term->term_id;
            }
        }
        foreach ((array) (isset($post['taxonomies']) ? $post['taxonomies'] : array_keys($term_ids)) as $taxonomy) {
            $taxonomy = sanitize_key($taxonomy);
            if (taxonomy_exists($taxonomy)) {
                $assigned = wp_set_object_terms(
                    $post_id,
                    isset($term_ids[$taxonomy]) ? array_values(array_unique($term_ids[$taxonomy])) : array(),
                    $taxonomy,
                    false
                );
                if (is_wp_error($assigned)) {
                    return $assigned;
                }
            }
        }
        $installed[] = sprintf(__('%1$s (%2$s)', 'tn-wp-migrate-code-diff'), $post['title'], $post['post_type']);
    }

    foreach ($posts as $post) {
        if (empty($post['parent_identity']) || empty($identity_map[$post['identity']])) {
            continue;
        }
        $parent_id = isset($identity_map[$post['parent_identity']])
            ? $identity_map[$post['parent_identity']]
            : twmcd_find_post_by_identity($post['parent_identity']);
        if ($parent_id) {
            $parent_update = wp_update_post(
                array('ID' => $identity_map[$post['identity']], 'post_parent' => $parent_id),
                true
            );
            if (is_wp_error($parent_update)) {
                return $parent_update;
            }
        }
    }

    foreach ($remove_posts as $post) {
        $post_id = twmcd_find_post_by_identity($post['identity'], $post['post_type']);
        if ($post_id && !wp_delete_post($post_id, true)) {
            return new WP_Error('twmcd_post_remove_failed', sprintf(__('The post “%s” could not be removed.', 'tn-wp-migrate-code-diff'), $post['title']));
        }
        $installed[] = sprintf(__('%s (removed)', 'tn-wp-migrate-code-diff'), $post['title']);
    }

    return $installed;
}
