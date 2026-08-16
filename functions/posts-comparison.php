<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_post_types_for_comparison()
{
    global $wpdb;

    $post_types = get_post_types(array('show_ui' => true), 'names');
    foreach (array('wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles') as $post_type) {
        if (post_type_exists($post_type)) {
            $post_types[$post_type] = $post_type;
        }
    }

    // A selected multisite blog can have site-active plugins that were not loaded
    // for the remote connection request. Include the post types actually stored in
    // that blog so their records do not disappear merely because registration is
    // unavailable in the current request.
    $stored_post_types = $wpdb->get_col(
        "SELECT DISTINCT post_type FROM {$wpdb->posts}
        WHERE post_status NOT IN ('auto-draft','trash','inherit')"
    );
    foreach ((array) $stored_post_types as $stored_post_type) {
        $stored_post_type = sanitize_key($stored_post_type);
        if ('' !== $stored_post_type) {
            $post_types[$stored_post_type] = $stored_post_type;
        }
    }

    $post_types = array_diff(
        array_values(array_unique($post_types)),
        array('attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request')
    );
    sort($post_types);

    return apply_filters('twmcd_post_types_for_comparison', $post_types);
}

function twmcd_with_comparison_blog($context, $is_local, $callback)
{
    $blog_id = is_multisite() ? twmcd_database_inventory_blog_id($context, $is_local, true) : 0;
    $switched = $blog_id > 0 && $blog_id !== get_current_blog_id();
    if ($switched) {
        switch_to_blog($blog_id);
    }

    $result = call_user_func($callback);
    if ($switched) {
        restore_current_blog();
    }

    return $result;
}

function twmcd_post_identity($post)
{
    $uuid = get_post_meta($post->ID, '_twmcd_content_uuid', true);
    if (is_string($uuid) && '' !== trim($uuid)) {
        return array('key' => $post->post_type . ':uuid:' . sanitize_key($uuid), 'portable' => true);
    }

    $post_type = get_post_type_object($post->post_type);
    $path = !empty($post_type->hierarchical) || !empty($post->post_parent)
        ? get_page_uri($post->ID)
        : $post->post_name;
    if (is_string($path) && '' !== trim($path)) {
        return array('key' => $post->post_type . ':path:' . trim($path, '/'), 'portable' => true);
    }

    return array('key' => $post->post_type . ':id:' . (int) $post->ID, 'portable' => false);
}

function twmcd_export_unregistered_post_terms($post_id)
{
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT tt.taxonomy, tt.parent, t.slug, t.name, tt.description
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE tr.object_id = %d",
            $post_id
        )
    );
    $records = array();
    $taxonomies = array();

    foreach ((array) $rows as $row) {
        $assigned = true;
        $seen = array();
        while ($row) {
            $record_key = $row->taxonomy . ':' . $row->slug;
            if (isset($seen[$record_key])) {
                break;
            }
            $seen[$record_key] = true;
            $parent = !empty($row->parent)
                ? $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT tt.taxonomy, tt.parent, t.slug, t.name, tt.description
                        FROM {$wpdb->term_taxonomy} tt
                        INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
                        WHERE tt.term_id = %d AND tt.taxonomy = %s
                        LIMIT 1",
                        $row->parent,
                        $row->taxonomy
                    )
                )
                : false;
            $records[$record_key] = array(
                'taxonomy'    => $row->taxonomy,
                'slug'        => $row->slug,
                'name'        => $row->name,
                'description' => $row->description,
                'parent_slug' => $parent ? $parent->slug : '',
                'assigned'    => $assigned || (!empty($records[$record_key]['assigned'])),
            );
            $taxonomies[$row->taxonomy] = $row->taxonomy;
            $assigned = false;
            $row = $parent;
        }
    }

    return array('terms' => array_values($records), 'taxonomies' => array_values($taxonomies));
}

function twmcd_normalize_content_value($value)
{
    if (!is_array($value)) {
        return $value;
    }

    if (array_keys($value) !== range(0, count($value) - 1)) {
        ksort($value);
    }
    foreach ($value as $key => $item) {
        $value[$key] = twmcd_normalize_content_value($item);
    }

    return $value;
}

function twmcd_export_post_content($post_id)
{
    $post = get_post($post_id);
    if (!$post) {
        return false;
    }

    $identity = twmcd_post_identity($post);
    $fields = array();
    foreach (array(
        'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status',
        'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged',
        'post_content_filtered', 'menu_order', 'post_mime_type', 'post_date', 'post_date_gmt',
    ) as $field) {
        $fields[$field] = isset($post->{$field}) ? $post->{$field} : '';
    }

    $meta = get_post_meta($post->ID);
    foreach (array('_edit_lock', '_edit_last', '_twmcd_content_uuid') as $ignored_key) {
        unset($meta[$ignored_key]);
    }
    foreach ($meta as $meta_key => $values) {
        $meta[$meta_key] = array_map('maybe_unserialize', (array) $values);
    }
    $meta = twmcd_normalize_content_value($meta);

    $term_records = array();
    $taxonomies = get_object_taxonomies($post->post_type, 'names');
    $unregistered_terms = !$taxonomies ? twmcd_export_unregistered_post_terms($post->ID) : null;
    $post_terms = $taxonomies ? wp_get_object_terms($post->ID, $taxonomies) : array();
    if (!is_wp_error($post_terms)) {
        foreach ($post_terms as $term) {
            $assigned = true;
            while ($term && !is_wp_error($term)) {
                $parent = !empty($term->parent) ? get_term($term->parent, $term->taxonomy) : false;
                $record_key = $term->taxonomy . ':' . $term->slug;
                $term_records[$record_key] = array(
                    'taxonomy'    => $term->taxonomy,
                    'slug'        => $term->slug,
                    'name'        => $term->name,
                    'description' => $term->description,
                    'parent_slug' => $parent && !is_wp_error($parent) ? $parent->slug : '',
                    'assigned'    => $assigned || (!empty($term_records[$record_key]['assigned'])),
                );
                $assigned = false;
                $term = $parent && !is_wp_error($parent) ? $parent : false;
            }
        }
    }
    $terms = $unregistered_terms ? $unregistered_terms['terms'] : array_values($term_records);
    if ($unregistered_terms) {
        $taxonomies = $unregistered_terms['taxonomies'];
    }
    usort($terms, function ($left, $right) {
        return strcmp($left['taxonomy'] . ':' . $left['slug'], $right['taxonomy'] . ':' . $right['slug']);
    });

    $parent_identity = '';
    if (!empty($post->post_parent)) {
        $parent = get_post($post->post_parent);
        $parent_identity = $parent ? twmcd_post_identity($parent)['key'] : '';
    }
    $author = get_user_by('id', $post->post_author);

    return array(
        'identity'        => $identity['key'],
        'portable'        => $identity['portable'],
        'source_id'       => (int) $post->ID,
        'post_type'       => $post->post_type,
        'title'           => get_the_title($post),
        'fields'          => $fields,
        'meta'            => $meta,
        'terms'           => $terms,
        'taxonomies'      => array_values($taxonomies),
        'parent_identity' => $parent_identity,
        'author_login'    => $author ? $author->user_login : '',
    );
}

function twmcd_post_content_fingerprint($export)
{
    $fingerprint_data = $export;
    unset(
        $fingerprint_data['source_id'],
        $fingerprint_data['title'],
        $fingerprint_data['portable'],
        $fingerprint_data['fingerprint'],
        $fingerprint_data['from_fingerprint']
    );
    unset($fingerprint_data['taxonomies']);
    $fingerprint_data['terms'] = array_map(
        function ($term) {
            return array(
                'taxonomy' => isset($term['taxonomy']) ? $term['taxonomy'] : '',
                'slug'     => isset($term['slug']) ? $term['slug'] : '',
                'assigned' => !empty($term['assigned']),
            );
        },
        isset($fingerprint_data['terms']) ? (array) $fingerprint_data['terms'] : array()
    );

    return hash('sha256', wp_json_encode(twmcd_normalize_content_value($fingerprint_data)));
}

function twmcd_local_posts_inventory($context, $is_local = true)
{
    return twmcd_with_comparison_blog($context, $is_local, function () {
        global $wpdb;

        $post_types = twmcd_post_types_for_comparison();
        if (!$post_types) {
            return array('posts' => array(), 'total' => 0, 'truncated' => false);
        }

        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $query = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$placeholders}) AND post_status NOT IN ('auto-draft','trash','inherit') ORDER BY ID ASC LIMIT 5001",
            $post_types
        );
        $post_ids = array_map('absint', (array) $wpdb->get_col($query));
        $truncated = count($post_ids) > 5000;
        $post_ids = array_slice($post_ids, 0, 5000);
        $posts = array();

        foreach ($post_ids as $post_id) {
            $export = twmcd_export_post_content($post_id);
            if (!$export) {
                continue;
            }
            $posts[$export['identity']] = array(
                'identity'    => $export['identity'],
                'portable'    => $export['portable'],
                'source_id'   => $export['source_id'],
                'post_type'   => $export['post_type'],
                'title'       => $export['title'],
                'status'      => $export['fields']['post_status'],
                'fingerprint' => twmcd_post_content_fingerprint($export),
            );
        }

        return array('posts' => $posts, 'total' => count($post_ids), 'truncated' => $truncated);
    });
}

function twmcd_sanitize_posts_inventory($inventory)
{
    $posts = array();
    foreach ((array) (isset($inventory['posts']) ? $inventory['posts'] : array()) as $identity => $post) {
        if (!is_array($post)) {
            continue;
        }
        $identity = sanitize_text_field((string) $identity);
        $fingerprint = preg_replace('/[^a-f0-9]/', '', strtolower(isset($post['fingerprint']) ? $post['fingerprint'] : ''));
        if ('' === $identity || 64 !== strlen($fingerprint)) {
            continue;
        }
        $posts[$identity] = array(
            'identity'    => $identity,
            'portable'    => !empty($post['portable']),
            'source_id'   => absint(isset($post['source_id']) ? $post['source_id'] : 0),
            'post_type'   => sanitize_key(isset($post['post_type']) ? $post['post_type'] : ''),
            'title'       => sanitize_text_field(isset($post['title']) ? $post['title'] : ''),
            'status'      => sanitize_key(isset($post['status']) ? $post['status'] : ''),
            'fingerprint' => $fingerprint,
        );
    }

    return array(
        'posts'     => $posts,
        'total'     => absint(isset($inventory['total']) ? $inventory['total'] : count($posts)),
        'truncated' => !empty($inventory['truncated']),
    );
}

function twmcd_request_remote_posts_inventory($remote_url, $secret_key, $intent, $context)
{
    $response = twmcd_request_remote_site_data(
        $remote_url,
        $secret_key,
        $intent,
        array(
            'twmcd_mode'    => 'posts',
            'twmcd_context' => base64_encode(wp_json_encode(twmcd_sanitize_migration_context($context))),
        )
    );
    if (is_wp_error($response)) {
        return $response;
    }
    if (!isset($response['twmcd_posts_inventory']) || !is_array($response['twmcd_posts_inventory'])) {
        return new WP_Error(
            'twmcd_posts_inventory_unavailable',
            __('The remote site did not return its Posts inventory. Install and activate the same current version of this plugin on both sites.', 'tn-wp-migrate-code-diff')
        );
    }

    return twmcd_sanitize_posts_inventory($response['twmcd_posts_inventory']);
}

function twmcd_compare_posts_inventories($source, $destination)
{
    $identities = array_unique(array_merge(array_keys($source['posts']), array_keys($destination['posts'])));
    $groups = array();
    sort($identities);

    foreach ($identities as $identity) {
        $source_post = isset($source['posts'][$identity]) ? $source['posts'][$identity] : null;
        $destination_post = isset($destination['posts'][$identity]) ? $destination['posts'][$identity] : null;
        $display_post = $source_post ? $source_post : $destination_post;
        $status = !$destination_post ? 'source_only' : (!$source_post ? 'destination_only'
            : ($source_post['fingerprint'] === $destination_post['fingerprint'] ? 'same' : 'different'));
        $portable = !empty($display_post['portable']);
        $groups[$display_post['post_type']][] = array(
            'identity'                => $identity,
            'title'                   => $display_post['title'],
            'post_type'               => $display_post['post_type'],
            'status'                  => $status,
            'source_status'           => $source_post ? $source_post['status'] : '',
            'destination_status'      => $destination_post ? $destination_post['status'] : '',
            'source_id'               => $source_post ? $source_post['source_id'] : 0,
            'destination_id'          => $destination_post ? $destination_post['source_id'] : 0,
            'portable'                => $portable,
            'selection'               => $portable ? $identity : '',
            'selection_operation'     => $source_post ? 'upsert' : 'remove',
            'default_selected'        => $portable && in_array($status, array('source_only', 'different'), true),
            'source_fingerprint'      => $source_post ? $source_post['fingerprint'] : '',
            'destination_fingerprint' => $destination_post ? $destination_post['fingerprint'] : '',
        );
    }
    ksort($groups);

    return $groups;
}

function twmcd_posts_comparison_transient_key($token)
{
    return 'twmcd_posts_' . get_current_user_id() . '_' . sanitize_key($token);
}

function twmcd_store_posts_comparison($context, $groups)
{
    $token = wp_generate_password(20, false, false);
    set_site_transient(
        twmcd_posts_comparison_transient_key($token),
        array('context' => $context, 'groups' => $groups),
        15 * MINUTE_IN_SECONDS
    );

    return $token;
}

function twmcd_get_posts_comparison($token)
{
    $state = get_site_transient(twmcd_posts_comparison_transient_key($token));

    return is_array($state) ? $state : false;
}
