<?php

if (!defined('ABSPATH')) {
    exit;
}

$date_format = get_option('date_format') . ' ' . get_option('time_format');
$format_history_date = function ($date) use ($date_format) {
    return $date ? date_i18n($date_format, strtotime($date . ' UTC')) : '—';
};
$status_labels = array(
    'package_created'  => __('Package created', 'tn-wp-migrate-code-diff'),
    'rollback_created' => __('Rollback created', 'tn-wp-migrate-code-diff'),
    'installed'        => __('Installed', 'tn-wp-migrate-code-diff'),
);
$change_groups = array(
    'added'   => array('label' => __('Added', 'tn-wp-migrate-code-diff'), 'changes' => null),
    'updated' => array('label' => __('Updated', 'tn-wp-migrate-code-diff'), 'changes' => null),
    'removed' => array('label' => __('Removed', 'tn-wp-migrate-code-diff'), 'changes' => null),
);
?>
<div class="wrap twmcd-admin-wrap twmcd-history-wrap">
    <h1><?php esc_html_e('Release Notes', 'tn-wp-migrate-code-diff'); ?></h1>
    <p><?php esc_html_e('History of manual code release packages, rollback creation, and installations recorded by this site.', 'tn-wp-migrate-code-diff'); ?></p>

    <?php if (!$history_rows) : ?>
        <div class="notice notice-info inline"><p><?php esc_html_e('No manual release activity has been recorded yet.', 'tn-wp-migrate-code-diff'); ?></p></div>
    <?php else : ?>
        <div class="twmcd-table-scroll">
            <table class="widefat striped twmcd-history-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Release', 'tn-wp-migrate-code-diff'); ?></th>
                        <th scope="col"><?php esc_html_e('Package source', 'tn-wp-migrate-code-diff'); ?></th>
                        <th scope="col"><?php esc_html_e('Destination deployment', 'tn-wp-migrate-code-diff'); ?></th>
                        <th scope="col"><?php esc_html_e('Rollback', 'tn-wp-migrate-code-diff'); ?></th>
                        <th scope="col"><?php esc_html_e('Changes', 'tn-wp-migrate-code-diff'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history_rows as $row) : ?>
                        <?php
                        $added = json_decode($row['added'], true);
                        $updated = json_decode($row['updated'], true);
                        $removed = json_decode($row['removed'], true);
                        $added = is_array($added) ? $added : array();
                        $updated = is_array($updated) ? $updated : array();
                        $removed = is_array($removed) ? $removed : array();
                        $row_change_groups = $change_groups;
                        $row_change_groups['added']['changes'] = $added;
                        $row_change_groups['updated']['changes'] = $updated;
                        $row_change_groups['removed']['changes'] = $removed;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($row['release_id']); ?></strong><br>
                                <span class="twmcd-history-status"><?php echo esc_html(isset($status_labels[$row['status']]) ? $status_labels[$row['status']] : $row['status']); ?></span>
                            </td>
                            <td>
                                <strong><?php echo esc_html($row['package_user_name'] ? $row['package_user_name'] : '—'); ?></strong><br>
                                <?php echo esc_html($row['source_url'] ? $row['source_url'] : '—'); ?><br>
                                <span class="description"><?php echo esc_html($format_history_date($row['package_created_at'])); ?></span>
                            </td>
                            <td>
                                <strong><?php echo esc_html($row['upload_user_name'] ? $row['upload_user_name'] : '—'); ?></strong><br>
                                <?php echo esc_html($row['destination_url'] ? $row['destination_url'] : home_url()); ?><br>
                                <span class="description"><?php echo esc_html($format_history_date($row['installed_at'])); ?></span>
                            </td>
                            <td>
                                <strong><?php echo !empty($row['rollback_created']) ? esc_html__('Yes', 'tn-wp-migrate-code-diff') : esc_html__('No', 'tn-wp-migrate-code-diff'); ?></strong><br>
                                <?php if (!empty($row['rollback_created'])) : ?>
                                    <?php echo esc_html($row['rollback_user_name']); ?><br>
                                    <span class="description"><?php echo esc_html($format_history_date($row['rollback_created_at'])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php foreach ($row_change_groups as $change_type => $change_group) : ?>
                                    <?php foreach ($change_group['changes'] as $change) : ?>
                                        <div class="twmcd-history-change">
                                            <strong><?php echo esc_html($change_group['label']); ?>:</strong>
                                            <?php echo esc_html(isset($change['name']) ? $change['name'] : ''); ?>
                                            <?php if ('updated' === $change_type) : ?>
                                                <span class="description"><?php echo esc_html(($change['from_version'] ? $change['from_version'] : '—') . ' → ' . ($change['to_version'] ? $change['to_version'] : '—')); ?></span>
                                            <?php elseif ('added' === $change_type && !empty($change['to_version'])) : ?>
                                                <span class="description"><?php echo esc_html($change['to_version']); ?></span>
                                            <?php elseif ('removed' === $change_type && !empty($change['from_version'])) : ?>
                                                <span class="description"><?php echo esc_html($change['from_version']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
