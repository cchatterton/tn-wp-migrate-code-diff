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

    twmcd_prepare_long_running_operation();

    $operation = isset($_POST['release_operation'])
        ? sanitize_key(wp_unslash($_POST['release_operation']))
        : 'install';

    if ('create_rollback' === $operation) {
        $result = twmcd_receive_and_create_rollback();
        if (!is_wp_error($result)) {
            twmcd_send_release_package($result['path'], $result['filename']);
        }

        $stored_result = array(
            'success'  => false,
            'message'  => $result->get_error_message(),
            'packages' => array(),
        );
        set_site_transient('twmcd_install_result_' . get_current_user_id(), $stored_result, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(twmcd_upload_release_page_url());
        exit;
    }

    if ('install' !== $operation) {
        $result = new WP_Error('twmcd_invalid_release_operation', __('Choose a valid release operation.', 'tn-wp-migrate-code-diff'));
    } else {
        $result = twmcd_receive_and_install_release(false);
    }
    $stored_result = is_wp_error($result)
        ? array('success' => false, 'message' => $result->get_error_message(), 'packages' => array())
        : array('success' => true, 'message' => $result['message'], 'packages' => $result['packages']);

    set_site_transient('twmcd_install_result_' . get_current_user_id(), $stored_result, 5 * MINUTE_IN_SECONDS);

    wp_safe_redirect(twmcd_upload_release_page_url());
    exit;
}

function twmcd_receive_and_create_rollback()
{
    $uploaded_file = twmcd_receive_release_upload();
    if (is_wp_error($uploaded_file)) {
        return $uploaded_file;
    }

    $validation = twmcd_validate_release_zip_path($uploaded_file);
    if (is_wp_error($validation)) {
        wp_delete_file($uploaded_file);
        return $validation;
    }

    $rollback_package = twmcd_create_rollback_release_package($validation['manifest']);
    wp_delete_file($uploaded_file);

    if (false === $rollback_package) {
        return new WP_Error(
            'twmcd_rollback_not_available',
            __('A rollback package cannot be created from a rollback release.', 'tn-wp-migrate-code-diff')
        );
    }

    twmcd_record_release_history($validation['manifest'], 'rollback_created');

    return $rollback_package;
}

function twmcd_receive_and_install_release($create_rollback = false)
{
    $uploaded_file = twmcd_receive_release_upload();
    if (is_wp_error($uploaded_file)) {
        return $uploaded_file;
    }

    $result = twmcd_install_release_archive($uploaded_file, $create_rollback);
    wp_delete_file($uploaded_file);

    if (!is_wp_error($result) && !empty($result['manifest'])) {
        twmcd_record_release_history($result['manifest'], 'installed');
    }

    return $result;
}

function twmcd_receive_release_upload()
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

    return $uploaded_file['file'];
}

function twmcd_validate_release_zip_path($zip_path)
{
    if (!class_exists('ZipArchive')) {
        return new WP_Error('twmcd_zip_unavailable', __('The PHP Zip extension is required to read a release.', 'tn-wp-migrate-code-diff'));
    }

    $archive = new ZipArchive();
    if (true !== $archive->open($zip_path)) {
        return new WP_Error('twmcd_zip_invalid', __('The uploaded file is not a readable ZIP archive.', 'tn-wp-migrate-code-diff'));
    }

    $validation = twmcd_validate_release_archive($archive);
    $archive->close();

    return $validation;
}
