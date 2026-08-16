<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_verify_ajax_request()
{
    check_ajax_referer('twmcd_admin', 'nonce');

    if (!current_user_can(twmcd_admin_capability())) {
        wp_send_json_error(
            array('message' => __('You do not have permission to perform this action.', 'tn-wp-migrate-code-diff')),
            403
        );
    }
}

function twmcd_ajax_prepare_comparison()
{
    twmcd_verify_ajax_request();

    $intent = isset($_POST['intent']) && 'pull' === sanitize_key(wp_unslash($_POST['intent'])) ? 'pull' : 'push';
    $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'code';
    if (!in_array($mode, array('code', 'database', 'options', 'posts'), true)) {
        $mode = 'code';
    }

    if ('database' === $mode && !TWMCD_DATABASE_COMPARISON_ENABLED) {
        wp_send_json_error(
            array('message' => __('Database/Images comparison is temporarily disabled.', 'tn-wp-migrate-code-diff')),
            400
        );
    }

    $connection_info = isset($_POST['connection']) ? sanitize_textarea_field(wp_unslash($_POST['connection'])) : '';
    $connection = twmcd_parse_connection_info($connection_info);

    if (is_wp_error($connection)) {
        wp_send_json_error(array('message' => $connection->get_error_message()), 400);
    }

    $raw_context_json = isset($_POST['context']) ? wp_unslash($_POST['context']) : '';
    $raw_context = json_decode($raw_context_json, true);
    $context = twmcd_sanitize_migration_context($raw_context);
    $context_validation = twmcd_validate_multisite_context($context);

    if (is_wp_error($context_validation)) {
        wp_send_json_error(array('message' => $context_validation->get_error_message()), 400);
    }

    $context['intent'] = $intent;
    $context['connection_info'] = $connection_info;
    $context['connection'] = $connection;
    twmcd_store_automatic_comparison_profile($context, $mode);
    $token = twmcd_store_comparison_context($context);

    wp_send_json_success(
        array(
            'redirect_url' => add_query_arg(
                'twmcd_context',
                $token,
                'database' === $mode
                    ? twmcd_database_admin_page_url()
                    : ('options' === $mode
                        ? twmcd_options_admin_page_url()
                        : ('posts' === $mode ? twmcd_posts_admin_page_url() : twmcd_admin_page_url()))
            ),
        )
    );
}

function twmcd_ajax_compare_code()
{
    twmcd_verify_ajax_request();

    $context_token = isset($_POST['context_token']) ? sanitize_key(wp_unslash($_POST['context_token'])) : '';
    $comparison_token = isset($_POST['comparison_token']) ? sanitize_key(wp_unslash($_POST['comparison_token'])) : '';
    $context = twmcd_get_comparison_context($context_token);
    if (!is_array($context) && $comparison_token) {
        $previous_comparison = twmcd_get_comparison_state($comparison_token);
        $context = is_array($previous_comparison) && !empty($previous_comparison['context'])
            ? $previous_comparison['context']
            : false;
    }

    if (!is_array($context)) {
        wp_send_json_error(
            array('message' => __('The WP Migrate comparison context has expired. Return to Migrate and choose Compare Code again.', 'tn-wp-migrate-code-diff')),
            400
        );
    }

    $intent = $context['intent'];
    $connection = $context['connection'];
    $remote_inventory = twmcd_request_remote_inventory($connection['url'], $connection['key'], $intent);
    if (is_wp_error($remote_inventory)) {
        wp_send_json_error(array('message' => $remote_inventory->get_error_message()), 400);
    }

    $local_inventory = twmcd_local_inventory($context);
    $source_inventory = 'push' === $intent ? $local_inventory : $remote_inventory;
    $destination_inventory = 'push' === $intent ? $remote_inventory : $local_inventory;
    $comparison_groups = twmcd_compare_code_inventories($source_inventory, $destination_inventory);
    $profile_selection = isset($context['profile_selection']) ? $context['profile_selection'] : array('active' => false);
    $comparison_groups = twmcd_apply_loaded_profile_selection($comparison_groups, $profile_selection);
    $comparison_token = twmcd_create_comparison_token($context, $comparison_groups);
    wp_send_json_success(
        array(
            'intent'           => $intent,
            'source_url'       => $source_inventory['url'],
            'destination_url'  => $destination_inventory['url'],
            'profile_name'     => twmcd_default_profile_name($destination_inventory['url']),
            'groups'           => $comparison_groups,
            'comparison_token' => $comparison_token,
            'profile_selection_applied' => !empty($profile_selection['active']),
            'profile_selection_name' => !empty($profile_selection['name']) ? $profile_selection['name'] : '',
            'release_package_available' => 'push' === $intent,
            'scope_label'      => isset($context['migration']['scope_label']) ? $context['migration']['scope_label'] : '',
        )
    );
}

function twmcd_ajax_compare_database()
{
    twmcd_verify_ajax_request();

    if (!TWMCD_DATABASE_COMPARISON_ENABLED) {
        wp_send_json_error(
            array('message' => __('Database comparison is temporarily disabled.', 'tn-wp-migrate-code-diff')),
            400
        );
    }

    $context_token = isset($_POST['context_token']) ? sanitize_key(wp_unslash($_POST['context_token'])) : '';
    $context = twmcd_get_comparison_context($context_token);

    if (!is_array($context)) {
        wp_send_json_error(
            array('message' => __('The WP Migrate comparison context has expired. Return to Migrate and choose Compare Database again.', 'tn-wp-migrate-code-diff')),
            400
        );
    }

    $intent = $context['intent'];
    $connection = $context['connection'];
    $remote_data = twmcd_request_remote_site_data($connection['url'], $connection['key'], $intent);
    if (is_wp_error($remote_data)) {
        wp_send_json_error(array('message' => $remote_data->get_error_message()), 400);
    }

    $remote_inventory = twmcd_normalize_database_images_inventory($remote_data, $context, false);
    $local_inventory = twmcd_local_database_images_inventory($context);
    $source_inventory = 'push' === $intent ? $local_inventory : $remote_inventory;
    $destination_inventory = 'push' === $intent ? $remote_inventory : $local_inventory;
    $comparison = twmcd_compare_database_report($source_inventory, $destination_inventory);

    wp_send_json_success(
        array(
            'intent'          => $intent,
            'source_url'      => $source_inventory['url'],
            'destination_url' => $destination_inventory['url'],
            'groups'          => $comparison,
            'scope_label'     => isset($context['migration']['scope_label']) ? $context['migration']['scope_label'] : '',
        )
    );
}

function twmcd_ajax_compare_options()
{
    twmcd_verify_ajax_request();

    $context_token = isset($_POST['context_token']) ? sanitize_key(wp_unslash($_POST['context_token'])) : '';
    $context = twmcd_get_comparison_context($context_token);
    if (!is_array($context)) {
        wp_send_json_error(
            array('message' => __('The WP Migrate comparison context has expired. Return to Migrate and choose Compare Options again.', 'tn-wp-migrate-code-diff')),
            400
        );
    }

    $intent = $context['intent'];
    $connection = $context['connection'];
    $remote_inventory = twmcd_request_remote_options_inventory($connection['url'], $connection['key'], $intent, $context);
    if (is_wp_error($remote_inventory)) {
        wp_send_json_error(array('message' => $remote_inventory->get_error_message()), 400);
    }

    $local_inventory = twmcd_local_options_inventory($context, true);
    $source_inventory = 'push' === $intent ? $local_inventory : $remote_inventory;
    $destination_inventory = 'push' === $intent ? $remote_inventory : $local_inventory;

    wp_send_json_success(
        array(
            'source_url'       => 'push' === $intent ? home_url() : $connection['url'],
            'destination_url'  => 'push' === $intent ? $connection['url'] : home_url(),
            'tables'           => twmcd_compare_options_inventories($source_inventory, $destination_inventory),
            'scope_label'      => isset($context['migration']['scope_label']) ? $context['migration']['scope_label'] : '',
        )
    );
}

function twmcd_ajax_compare_posts()
{
    twmcd_verify_ajax_request();

    $context_token = isset($_POST['context_token']) ? sanitize_key(wp_unslash($_POST['context_token'])) : '';
    $context = twmcd_get_comparison_context($context_token);
    if (!is_array($context)) {
        wp_send_json_error(
            array('message' => __('The WP Migrate comparison context has expired. Return to Migrate and choose Compare Posts again.', 'tn-wp-migrate-code-diff')),
            400
        );
    }

    $intent = $context['intent'];
    $connection = $context['connection'];
    $remote_inventory = twmcd_request_remote_posts_inventory($connection['url'], $connection['key'], $intent, $context);
    if (is_wp_error($remote_inventory)) {
        wp_send_json_error(array('message' => $remote_inventory->get_error_message()), 400);
    }
    $local_inventory = twmcd_local_posts_inventory($context, true);
    if (!empty($local_inventory['truncated']) || !empty($remote_inventory['truncated'])) {
        wp_send_json_error(
            array('message' => __('Posts comparison currently supports up to 5,000 posts in the selected site scope.', 'tn-wp-migrate-code-diff')),
            400
        );
    }

    $source_inventory = 'push' === $intent ? $local_inventory : $remote_inventory;
    $destination_inventory = 'push' === $intent ? $remote_inventory : $local_inventory;
    $groups = twmcd_compare_posts_inventories($source_inventory, $destination_inventory);
    $comparison_token = twmcd_store_posts_comparison($context, $groups);

    wp_send_json_success(
        array(
            'source_url'        => 'push' === $intent ? home_url() : $connection['url'],
            'destination_url'   => 'push' === $intent ? $connection['url'] : home_url(),
            'groups'            => $groups,
            'comparison_token'  => $comparison_token,
            'package_available' => 'push' === $intent,
            'scope_label'       => isset($context['migration']['scope_label']) ? $context['migration']['scope_label'] : '',
        )
    );
}

function twmcd_ajax_save_profile()
{
    twmcd_verify_ajax_request();

    $selection_json = isset($_POST['selection']) ? wp_unslash($_POST['selection']) : '';
    $selection = json_decode($selection_json, true);
    $comparison_token = isset($_POST['comparison_token'])
        ? sanitize_key(wp_unslash($_POST['comparison_token']))
        : '';

    if (!is_array($selection)) {
        wp_send_json_error(
            array('message' => __('Enter a profile name and select at least one valid package.', 'tn-wp-migrate-code-diff')),
            400
        );
    }

    $comparison_state = twmcd_get_comparison_state($comparison_token);
    $destination_url = is_array($comparison_state) && !empty($comparison_state['context']['intent'])
        && 'push' === $comparison_state['context']['intent']
        ? $comparison_state['context']['connection']['url']
        : home_url();
    $profile_name = twmcd_default_profile_name($destination_url);
    $selection = twmcd_validate_profile_selection($comparison_token, $selection);
    if (is_wp_error($selection) || !$comparison_state) {
        $message = is_wp_error($selection)
            ? $selection->get_error_message()
            : __('The comparison has expired. Run it again before saving.', 'tn-wp-migrate-code-diff');
        wp_send_json_error(array('message' => $message), 400);
    }

    $profile = twmcd_create_code_only_profile($profile_name, $comparison_state['context'], $selection);
    $profile_id = twmcd_store_migration_profile($profile_name, $profile);
    $redirect_url = add_query_arg(
        array(
            'redirect_profile' => $profile_id,
            'saved_profile'    => 1,
        ),
        twmcd_migrate_admin_url()
    );

    wp_send_json_success(
        array(
            'message'      => __('The release profile was updated automatically.', 'tn-wp-migrate-code-diff'),
            'profile_id'   => $profile_id,
            'redirect_url' => $redirect_url,
        )
    );
}
