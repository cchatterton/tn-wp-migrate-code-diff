<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap twmcd-admin-wrap">
    <h1><?php esc_html_e('Posts comparison', 'tn-wp-migrate-code-diff'); ?></h1>
    <p class="twmcd-page-actions">
        <a href="<?php echo esc_url($migrate_url); ?>">&larr; <?php esc_html_e('Back to WP Migrate', 'tn-wp-migrate-code-diff'); ?></a>
        <button id="twmcd-posts-refresh" class="button" type="button"><?php esc_html_e('Refresh comparison', 'tn-wp-migrate-code-diff'); ?></button>
    </p>

    <?php if (!$wp_migrate_available) : ?>
        <div class="notice notice-error inline"><p><?php esc_html_e('WP Migrate Pro must be installed and active to compare posts.', 'tn-wp-migrate-code-diff'); ?></p></div>
    <?php endif; ?>

    <section id="twmcd-posts-loading-card" class="twmcd-card" aria-labelledby="twmcd-posts-title">
        <h2 id="twmcd-posts-title"><?php esc_html_e('Comparing posts and related content', 'tn-wp-migrate-code-diff'); ?></h2>
        <p><?php esc_html_e('Reading posts, post meta, terms, and taxonomy relationships through the authenticated WP Migrate connection…', 'tn-wp-migrate-code-diff'); ?></p>
        <span id="twmcd-posts-loading" class="spinner is-active" aria-hidden="true"></span>
        <div id="twmcd-posts-message" class="twmcd-message" role="status" aria-live="polite"></div>
    </section>

    <div id="twmcd-posts-results" hidden>
        <section id="twmcd-posts-summary" class="twmcd-summary" aria-label="<?php esc_attr_e('Posts comparison summary', 'tn-wp-migrate-code-diff'); ?>"></section>
        <div id="twmcd-posts-groups"></div>
        <section class="twmcd-card" aria-labelledby="twmcd-posts-release-title">
            <h2 id="twmcd-posts-release-title"><?php esc_html_e('Create a Posts release package', 'tn-wp-migrate-code-diff'); ?></h2>
            <p id="twmcd-posts-selection-count" class="twmcd-release-selection-count" aria-live="polite"></p>
            <button id="twmcd-create-posts-package" class="button button-primary" type="button">
                <span class="twmcd-release-button-spinner spinner" aria-hidden="true"></span>
                <span class="twmcd-release-button-label"><?php esc_html_e('Create release package', 'tn-wp-migrate-code-diff'); ?></span>
            </button>
            <div id="twmcd-posts-package-message" class="twmcd-message" role="status" aria-live="polite"></div>
        </section>
    </div>
</div>
