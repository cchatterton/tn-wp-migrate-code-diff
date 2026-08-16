<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap twmcd-admin-wrap">
    <h1><?php esc_html_e('Options comparison', 'tn-wp-migrate-code-diff'); ?></h1>
    <p class="twmcd-page-actions">
        <a href="<?php echo esc_url($migrate_url); ?>">&larr; <?php esc_html_e('Back to WP Migrate', 'tn-wp-migrate-code-diff'); ?></a>
        <button id="twmcd-options-refresh" class="button" type="button"><?php esc_html_e('Refresh comparison', 'tn-wp-migrate-code-diff'); ?></button>
    </p>

    <?php if (!$wp_migrate_available) : ?>
        <div class="notice notice-error inline"><p><?php esc_html_e('WP Migrate Pro must be installed and active to compare Options.', 'tn-wp-migrate-code-diff'); ?></p></div>
    <?php endif; ?>

    <section id="twmcd-options-loading-card" class="twmcd-card" aria-labelledby="twmcd-options-title">
        <h2 id="twmcd-options-title"><?php esc_html_e('Comparing WordPress options', 'tn-wp-migrate-code-diff'); ?></h2>
        <p><?php esc_html_e('Reading option names and value fingerprints through the authenticated WP Migrate connection…', 'tn-wp-migrate-code-diff'); ?></p>
        <span id="twmcd-options-loading" class="spinner is-active" aria-hidden="true"></span>
        <div id="twmcd-options-message" class="twmcd-message" role="status" aria-live="polite"></div>
    </section>

    <div id="twmcd-options-results" hidden>
        <section id="twmcd-options-summary" class="twmcd-summary" aria-label="<?php esc_attr_e('Options comparison summary', 'tn-wp-migrate-code-diff'); ?>"></section>
        <div id="twmcd-options-groups"></div>
    </div>
</div>
