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
        <h2 id="twmcd-upload-title"><?php esc_html_e('Install a manual code release', 'tn-wp-migrate-code-diff'); ?></h2>
        <p><?php esc_html_e('Upload a release ZIP created by this plugin on the source site. The installer validates its manifest and file checksums, then replaces only the included plugins, themes, and must-use plugins.', 'tn-wp-migrate-code-diff'); ?></p>
        <p class="description"><?php esc_html_e('Database content, media, plugin activation, and theme activation are not changed. Existing package files are held for rollback while installation runs.', 'tn-wp-migrate-code-diff'); ?></p>

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="twmcd_upload_release">
            <?php wp_nonce_field('twmcd_upload_release', 'twmcd_upload_nonce'); ?>
            <label for="twmcd-release-file" class="twmcd-field-label"><?php esc_html_e('Release ZIP', 'tn-wp-migrate-code-diff'); ?></label>
            <input id="twmcd-release-file" name="release_file" type="file" accept=".zip,application/zip" required>
            <p>
                <button type="submit" class="button button-primary"><?php esc_html_e('Upload and install release', 'tn-wp-migrate-code-diff'); ?></button>
            </p>
        </form>
    </section>
</div>
