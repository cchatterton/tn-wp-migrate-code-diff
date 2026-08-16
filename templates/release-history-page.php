<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap twmcd-admin-wrap twmcd-history-wrap">
    <h1><?php esc_html_e('Release Notes', 'tn-wp-migrate-code-diff'); ?></h1>
    <p><?php esc_html_e('History of rollback creation and manual release installations processed through Upload Release on this site.', 'tn-wp-migrate-code-diff'); ?></p>

    <?php if (!$history_months) : ?>
        <div class="notice notice-info inline"><p><?php esc_html_e('No manual release activity has been recorded yet.', 'tn-wp-migrate-code-diff'); ?></p></div>
    <?php else : ?>
        <div class="twmcd-history-search" role="search">
            <label for="twmcd-history-search"><strong><?php esc_html_e('Search release notes', 'tn-wp-migrate-code-diff'); ?></strong></label>
            <input id="twmcd-history-search" class="regular-text" type="search" maxlength="100" autocomplete="off" placeholder="<?php esc_attr_e('Release, site, user, package, post, or version', 'tn-wp-migrate-code-diff'); ?>">
            <span id="twmcd-history-search-status" class="description" role="status" aria-live="polite"></span>
        </div>

        <div id="twmcd-history-months" class="twmcd-history-months">
            <?php foreach ($history_months as $month_index => $month) : ?>
                <?php $content_id = 'twmcd-history-month-' . str_replace('-', '', $month['key']); ?>
                <section class="twmcd-history-month" data-month="<?php echo esc_attr($month['key']); ?>" data-default-open="<?php echo 0 === $month_index ? '1' : '0'; ?>">
                    <h2 class="twmcd-history-month-heading">
                        <button class="twmcd-history-month-toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr($content_id); ?>">
                            <span class="twmcd-history-month-icon" aria-hidden="true">&#9656;</span>
                            <span><?php echo esc_html($month['label']); ?></span>
                            <span class="twmcd-history-month-total"><?php echo esc_html(sprintf(_n('%d release', '%d releases', $month['total'], 'tn-wp-migrate-code-diff'), $month['total'])); ?></span>
                            <span class="twmcd-history-match-count" hidden></span>
                        </button>
                    </h2>
                    <div id="<?php echo esc_attr($content_id); ?>" class="twmcd-history-month-content" role="region" aria-label="<?php echo esc_attr($month['label']); ?>" hidden></div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
