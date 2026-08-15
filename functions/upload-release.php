<?php

if (!defined('ABSPATH')) {
    exit;
}

function twmcd_handle_release_upload()
{
    check_admin_referer('twmcd_upload_release', 'twmcd_upload_nonce');

    if (!current_user_can(twmcd_release_install_capability())) {
        wp_die(esc_html__('You do not have permission to install releases.', 'tn-wp-migrate-code-diff'), 403);
    }

    $result = twmcd_receive_and_install_release();
    $stored_result = is_wp_error($result)
        ? array('success' => false, 'message' => $result->get_error_message(), 'packages' => array())
        : array('success' => true, 'message' => $result['message'], 'packages' => $result['packages']);

    set_site_transient('twmcd_install_result_' . get_current_user_id(), $stored_result, 5 * MINUTE_IN_SECONDS);

    if (!is_wp_error($result)
        && !empty($result['rollback_package']['path'])
        && !empty($result['rollback_package']['filename'])) {
        twmcd_send_release_package(
            $result['rollback_package']['path'],
            $result['rollback_package']['filename']
        );
    }

    wp_safe_redirect(twmcd_upload_release_page_url());
    exit;
}

function twmcd_receive_and_install_release()
{
    if (empty($_FILES['release_file']) || !is_array($_FILES['release_file'])) {
        return new WP_Error('twmcd_missing_upload', __('Choose a TN code release ZIP to upload.', 'tn-wp-migrate-code-diff'));
    }

    $uploaded_name = isset($_FILES['release_file']['name'])
        ? sanitize_file_name(wp_unslash($_FILES['release_file']['name']))
        : '';
    if ('zip' !== strtolower(pathinfo($uploaded_name, PATHINFO_EXTENSION))) {
        return new WP_Error('twmcd_invalid_extension', __('The release file must use the .zip extension.', 'tn-wp-migrate-code-diff'));
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded_file = wp_handle_upload(
        $_FILES['release_file'],
        array(
            'test_form' => false,
            // Hosts identify valid ZIPs with several incompatible MIME values.
            // The archive is opened and fully validated before any payload is extracted.
            'test_type' => false,
        )
    );

    if (!empty($uploaded_file['error'])) {
        return new WP_Error('twmcd_upload_failed', sanitize_text_field($uploaded_file['error']));
    }
    if (empty($uploaded_file['file'])) {
        return new WP_Error('twmcd_upload_missing', __('WordPress did not retain the uploaded release file.', 'tn-wp-migrate-code-diff'));
    }
    if ('zip' !== strtolower(pathinfo($uploaded_file['file'], PATHINFO_EXTENSION))) {
        wp_delete_file($uploaded_file['file']);
        return new WP_Error('twmcd_invalid_uploaded_extension', __('WordPress stored the release with an invalid file extension.', 'tn-wp-migrate-code-diff'));
    }

    $result = twmcd_install_release_archive($uploaded_file['file']);
    wp_delete_file($uploaded_file['file']);

    return $result;
}
