<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap twmcd-admin-wrap">
    <h1><?php esc_html_e('Database / Images comparison', 'tn-wp-migrate-code-diff'); ?></h1>
    <p><a href="<?php echo esc_url($migrate_url); ?>">&larr; <?php esc_html_e('Back to WP Migrate', 'tn-wp-migrate-code-diff'); ?></a></p>

    <?php if (!$wp_migrate_available) : ?>
        <div class="notice notice-error inline">
            <p><?php esc_html_e('WP Migrate Pro must be installed and active to use Database / Images Diff.', 'tn-wp-migrate-code-diff'); ?></p>
        </div>
    <?php endif; ?>

    <section id="twmcd-database-loading-card" class="twmcd-card" aria-labelledby="twmcd-database-title">
        <h2 id="twmcd-database-title"><?php esc_html_e('Comparing database and image migration state', 'tn-wp-migrate-code-diff'); ?></h2>
        <p><?php esc_html_e('Reading table metrics and Media capability through the connection configured in WP Migrate…', 'tn-wp-migrate-code-diff'); ?></p>
        <span id="twmcd-database-loading" class="spinner is-active" aria-hidden="true"></span>
        <div id="twmcd-database-message" class="twmcd-message" role="status" aria-live="polite"></div>
    </section>

    <div id="twmcd-database-results" hidden>
        <section id="twmcd-database-summary" class="twmcd-summary" aria-label="<?php esc_attr_e('Database and images comparison summary', 'tn-wp-migrate-code-diff'); ?>"></section>
        <section class="twmcd-card" aria-labelledby="twmcd-tables-title">
            <h2 id="twmcd-tables-title"><button type="button" class="button-link twmcd-accordion-toggle" aria-expanded="true" aria-controls="twmcd-database-tables"><span class="twmcd-accordion-icon" aria-hidden="true">&#9662;</span> <?php esc_html_e('Database tables', 'tn-wp-migrate-code-diff'); ?></button></h2>
            <div id="twmcd-database-tables" class="twmcd-table-scroll twmcd-accordion-content"></div>
        </section>
        <section class="twmcd-card" aria-labelledby="twmcd-images-title">
            <h2 id="twmcd-images-title"><button type="button" class="button-link twmcd-accordion-toggle" aria-expanded="true" aria-controls="twmcd-images-report"><span class="twmcd-accordion-icon" aria-hidden="true">&#9662;</span> <?php esc_html_e('Images / Media Uploads', 'tn-wp-migrate-code-diff'); ?></button></h2>
            <div id="twmcd-images-report" class="twmcd-accordion-content"></div>
        </section>
    </div>
</div>
