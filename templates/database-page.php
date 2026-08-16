<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap twmcd-admin-wrap">
    <h1><?php esc_html_e('Database comparison', 'tn-wp-migrate-code-diff'); ?></h1>
    <p class="twmcd-page-actions">
        <a href="<?php echo esc_url($migrate_url); ?>">&larr; <?php esc_html_e('Back to WP Migrate', 'tn-wp-migrate-code-diff'); ?></a>
        <button id="twmcd-database-refresh" class="button" type="button"><?php esc_html_e('Refresh comparison', 'tn-wp-migrate-code-diff'); ?></button>
    </p>

    <?php if (!$wp_migrate_available) : ?>
        <div class="notice notice-error inline"><p><?php esc_html_e('WP Migrate Pro must be installed and active to compare databases.', 'tn-wp-migrate-code-diff'); ?></p></div>
    <?php endif; ?>

    <section id="twmcd-database-loading-card" class="twmcd-card" aria-labelledby="twmcd-database-title">
        <h2 id="twmcd-database-title"><?php esc_html_e('Comparing database tables', 'tn-wp-migrate-code-diff'); ?></h2>
        <p><?php esc_html_e('Reading table presence, estimated row counts, and table sizes through the WP Migrate connection…', 'tn-wp-migrate-code-diff'); ?></p>
        <span id="twmcd-database-loading" class="spinner is-active" aria-hidden="true"></span>
        <div id="twmcd-database-message" class="twmcd-message" role="status" aria-live="polite"></div>
    </section>

    <div id="twmcd-database-results" hidden>
        <section id="twmcd-database-summary" class="twmcd-summary" aria-label="<?php esc_attr_e('Database comparison summary', 'tn-wp-migrate-code-diff'); ?>"></section>
        <div id="twmcd-database-groups"></div>
    </div>
</div>
