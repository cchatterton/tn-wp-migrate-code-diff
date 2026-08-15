<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap tncri-admin-wrap">
    <h1><?php esc_html_e('Upload Release', 'tn-code-release-installer'); ?></h1>

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

    <section class="tncri-card" aria-labelledby="tncri-upload-title">
        <h2 id="tncri-upload-title"><?php esc_html_e('Install a manual code release', 'tn-code-release-installer'); ?></h2>
        <p><?php esc_html_e('Upload a release ZIP created on the source site by TN WP Migrate Code Diff. The installer validates its manifest and file checksums, then replaces only the included plugins, themes, and must-use plugins.', 'tn-code-release-installer'); ?></p>
        <p class="description"><?php esc_html_e('Database content, media, plugin activation, and theme activation are not changed. Existing package files are held for rollback while installation runs.', 'tn-code-release-installer'); ?></p>

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="tncri_upload_release">
            <?php wp_nonce_field('tncri_upload_release', 'tncri_nonce'); ?>
            <label for="tncri-release-file" class="tncri-field-label"><?php esc_html_e('Release ZIP', 'tn-code-release-installer'); ?></label>
            <input id="tncri-release-file" name="release_file" type="file" accept=".zip,application/zip" required>
            <p>
                <button type="submit" class="button button-primary"><?php esc_html_e('Upload and install release', 'tn-code-release-installer'); ?></button>
            </p>
        </form>
    </section>
</div>
