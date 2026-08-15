<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap twmcd-admin-wrap twmcd-upload-wrap">
    <h1><?php esc_html_e('Upload Release', 'tn-wp-migrate-code-diff'); ?></h1>

    <?php if (is_array($result)) : ?>
        <div class="notice <?php echo !empty($result['success']) ? 'notice-success' : 'notice-error'; ?> is-dismissible">
            <p><?php echo esc_html($result['message']); ?></p>
            <?php if (!empty($result['packages'])) : ?>
                <ul class="ul-disc">
                    <?php foreach ($result['packages'] as $package_name) : ?>
                        <li><?php echo esc_html($package_name); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="twmcd-card" aria-labelledby="twmcd-upload-title">
        <h2 id="twmcd-upload-title"><?php esc_html_e('Manual code release', 'tn-wp-migrate-code-diff'); ?></h2>
        <p><?php esc_html_e('Choose a release ZIP created by this plugin. Create Rollback validates the release and downloads the destination’s current files without installing it. Install Release validates and installs the selected package.', 'tn-wp-migrate-code-diff'); ?></p>
        <p class="description"><?php esc_html_e('Database content, media, plugin activation, and theme activation are not changed. The rollback restores replaced or removed packages and removes packages introduced by the release. A rollback release can be installed, but cannot be used to create another rollback.', 'tn-wp-migrate-code-diff'); ?></p>

        <form id="twmcd-upload-release-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="twmcd_upload_release">
            <?php wp_nonce_field('twmcd_upload_release', 'twmcd_upload_nonce'); ?>
            <label for="twmcd-release-file" class="twmcd-field-label"><?php esc_html_e('Release ZIP', 'tn-wp-migrate-code-diff'); ?></label>
            <input id="twmcd-release-file" name="release_file" type="file" accept=".zip,application/zip" required>
            <p>
                <button id="twmcd-create-rollback" type="submit" name="release_operation" value="create_rollback" class="button" data-busy-label="<?php esc_attr_e('Creating rollback…', 'tn-wp-migrate-code-diff'); ?>">
                    <span class="twmcd-release-button-spinner spinner" aria-hidden="true"></span>
                    <span class="twmcd-release-button-label"><?php esc_html_e('Create Rollback', 'tn-wp-migrate-code-diff'); ?></span>
                </button>
                <button id="twmcd-install-release" type="submit" name="release_operation" value="install" class="button button-primary" data-busy-label="<?php esc_attr_e('Installing release…', 'tn-wp-migrate-code-diff'); ?>">
                    <span class="twmcd-release-button-spinner spinner" aria-hidden="true"></span>
                    <span class="twmcd-release-button-label"><?php esc_html_e('Install Release', 'tn-wp-migrate-code-diff'); ?></span>
                </button>
            </p>
            <div id="twmcd-upload-release-message" class="twmcd-message" role="status" aria-live="polite"></div>
        </form>
    </section>
</div>
