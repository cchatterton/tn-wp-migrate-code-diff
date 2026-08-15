<?php

if (!defined('ABSPATH')) {
    exit;
}

function tncri_admin_capability()
{
    return 'update_plugins';
}

function tncri_admin_page_url()
{
    return is_multisite()
        ? network_admin_url('settings.php?page=' . TNCRI_PAGE_SLUG)
        : admin_url('options-general.php?page=' . TNCRI_PAGE_SLUG);
}

function tncri_register_admin_page()
{
    if (is_multisite()) {
        add_submenu_page(
            'settings.php',
            __('Upload Release', 'tn-code-release-installer'),
            __('Upload Release', 'tn-code-release-installer'),
            tncri_admin_capability(),
            TNCRI_PAGE_SLUG,
            'tncri_render_admin_page'
        );
        return;
    }

    add_options_page(
        __('Upload Release', 'tn-code-release-installer'),
        __('Upload Release', 'tn-code-release-installer'),
        tncri_admin_capability(),
        TNCRI_PAGE_SLUG,
        'tncri_render_admin_page'
    );
}

function tncri_render_admin_page()
{
    if (!current_user_can(tncri_admin_capability())) {
        wp_die(esc_html__('You do not have permission to install releases.', 'tn-code-release-installer'));
    }

    $result = get_site_transient('tncri_install_result_' . get_current_user_id());
    delete_site_transient('tncri_install_result_' . get_current_user_id());
    require TNCRI_PLUGIN_DIR . 'templates/upload-page.php';
}

function tncri_enqueue_admin_assets()
{
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if (TNCRI_PAGE_SLUG !== $page) {
        return;
    }

    wp_enqueue_style(
        'tncri_admin',
        TNCRI_PLUGIN_URL . 'styles/tn-code-release-installer.css',
        array(),
        TNCRI_VERSION
    );
}

function tncri_add_plugin_action_link($links)
{
    array_unshift(
        $links,
        '<a href="' . esc_url(tncri_admin_page_url()) . '">' . esc_html__('Upload Release', 'tn-code-release-installer') . '</a>'
    );

    return $links;
}

function tncri_handle_release_upload()
{
    check_admin_referer('tncri_upload_release', 'tncri_nonce');

    if (!current_user_can(tncri_admin_capability())) {
        wp_die(esc_html__('You do not have permission to install releases.', 'tn-code-release-installer'), 403);
    }

    $result = tncri_receive_and_install_release();
    $stored_result = is_wp_error($result)
        ? array('success' => false, 'message' => $result->get_error_message(), 'packages' => array())
        : array('success' => true, 'message' => $result['message'], 'packages' => $result['packages']);

    set_site_transient('tncri_install_result_' . get_current_user_id(), $stored_result, 5 * MINUTE_IN_SECONDS);
    wp_safe_redirect(tncri_admin_page_url());
    exit;
}

function tncri_receive_and_install_release()
{
    if (empty($_FILES['release_file']) || !is_array($_FILES['release_file'])) {
        return new WP_Error('tncri_missing_upload', __('Choose a TN code release ZIP to upload.', 'tn-code-release-installer'));
    }

    $uploaded_name = isset($_FILES['release_file']['name'])
        ? sanitize_file_name(wp_unslash($_FILES['release_file']['name']))
        : '';
    if ('zip' !== strtolower(pathinfo($uploaded_name, PATHINFO_EXTENSION))) {
        return new WP_Error('tncri_invalid_extension', __('The release file must use the .zip extension.', 'tn-code-release-installer'));
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
        return new WP_Error('tncri_upload_failed', sanitize_text_field($uploaded_file['error']));
    }
    if (empty($uploaded_file['file'])) {
        return new WP_Error('tncri_upload_missing', __('WordPress did not retain the uploaded release file.', 'tn-code-release-installer'));
    }

    if ('zip' !== strtolower(pathinfo($uploaded_file['file'], PATHINFO_EXTENSION))) {
        wp_delete_file($uploaded_file['file']);
        return new WP_Error('tncri_invalid_uploaded_extension', __('WordPress stored the release with an invalid file extension.', 'tn-code-release-installer'));
    }

    $result = tncri_install_release_archive($uploaded_file['file']);
    wp_delete_file($uploaded_file['file']);

    return $result;
}
