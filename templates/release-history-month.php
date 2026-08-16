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
$change_labels = array(
    'added'   => __('Added', 'tn-wp-migrate-code-diff'),
    'updated' => __('Updated', 'tn-wp-migrate-code-diff'),
    'removed' => __('Removed', 'tn-wp-migrate-code-diff'),
);
?>
<?php if (!$history_rows) : ?>
    <p><?php esc_html_e('No release activity was found for this month.', 'tn-wp-migrate-code-diff'); ?></p>
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
                    $changes = array(
                        'added'   => json_decode($row['added'], true),
                        'updated' => json_decode($row['updated'], true),
                        'removed' => json_decode($row['removed'], true),
                    );
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
                            <?php foreach ($changes as $change_type => $group_changes) : ?>
                                <?php foreach (is_array($group_changes) ? $group_changes : array() as $change) : ?>
                                    <div class="twmcd-history-change">
                                        <strong><?php echo esc_html($change_labels[$change_type]); ?>:</strong>
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
