<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap twmcd-admin-wrap">
    <h1><?php esc_html_e('Code comparison', 'tn-wp-migrate-code-diff'); ?></h1>
    <p class="twmcd-page-actions">
        <a href="<?php echo esc_url($migrate_url); ?>">&larr; <?php esc_html_e('Back to WP Migrate', 'tn-wp-migrate-code-diff'); ?></a>
        <button id="twmcd-refresh-comparison" class="button" type="button"><?php esc_html_e('Refresh comparison', 'tn-wp-migrate-code-diff'); ?></button>
    </p>

    <?php if (!$wp_migrate_available) : ?>
        <div class="notice notice-error inline">
            <p><?php esc_html_e('WP Migrate Pro must be installed and active to use Code Diff.', 'tn-wp-migrate-code-diff'); ?></p>
        </div>
    <?php endif; ?>

    <section id="twmcd-loading-card" class="twmcd-card" aria-labelledby="twmcd-compare-title">
        <h2 id="twmcd-compare-title"><?php esc_html_e('Comparing code packages', 'tn-wp-migrate-code-diff'); ?></h2>
        <p><?php esc_html_e('Reading plugins, themes, and must-use plugins through the connection configured in WP Migrate…', 'tn-wp-migrate-code-diff'); ?></p>
        <span id="twmcd-loading" class="spinner is-active" aria-hidden="true"></span>
        <div id="twmcd-message" class="twmcd-message" role="status" aria-live="polite"></div>
    </section>

    <div id="twmcd-results" hidden>
        <section id="twmcd-summary" class="twmcd-summary" aria-label="<?php esc_attr_e('Comparison summary', 'tn-wp-migrate-code-diff'); ?>"></section>
        <div id="twmcd-groups"></div>

        <section class="twmcd-card" aria-labelledby="twmcd-profile-title">
            <h2 id="twmcd-profile-title"><?php esc_html_e('Create a code-only migration profile', 'tn-wp-migrate-code-diff'); ?></h2>
            <p id="twmcd-release-selection-count" class="twmcd-release-selection-count" aria-live="polite"></p>
            <label class="twmcd-field-label" for="twmcd-profile-name"><?php esc_html_e('Profile name', 'tn-wp-migrate-code-diff'); ?></label>
            <input id="twmcd-profile-name" class="regular-text" value="<?php echo esc_attr(twmcd_default_profile_name()); ?>">
            <button id="twmcd-save-profile" class="button button-primary" type="button"><?php esc_html_e('Save release profile', 'tn-wp-migrate-code-diff'); ?></button>
            <button id="twmcd-open-profile" class="button" type="button" disabled><?php esc_html_e('Open profile', 'tn-wp-migrate-code-diff'); ?></button>
            <button id="twmcd-create-release-package" class="button" type="button"><?php esc_html_e('Create release package', 'tn-wp-migrate-code-diff'); ?></button>
            <div id="twmcd-profile-message" class="twmcd-message" role="status" aria-live="polite"></div>
            <form id="twmcd-release-package-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" hidden>
                <input type="hidden" name="action" value="twmcd_create_release_package">
                <?php wp_nonce_field('twmcd_create_release_package', 'twmcd_release_nonce'); ?>
                <input type="hidden" name="release_name" value="">
                <input type="hidden" name="comparison_token" value="">
                <input type="hidden" name="selection" value="">
            </form>
        </section>
    </div>
</div>
