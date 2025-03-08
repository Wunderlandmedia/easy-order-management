<?php
if (!defined('ABSPATH')) {
    exit;
}

$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$total_logs = $this->logger->get_total_logs();
$total_pages = ceil($total_logs / $per_page);
$logs = $this->logger->get_logs($page, $per_page);
?>

<div class="wrap">
    <h1><?php echo esc_html__('WunderBestellung Logs', 'wunder-bestellung'); ?></h1>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('Date', 'wunder-bestellung'); ?></th>
                <th><?php echo esc_html__('User', 'wunder-bestellung'); ?></th>
                <th><?php echo esc_html__('Order Number', 'wunder-bestellung'); ?></th>
                <th><?php echo esc_html__('Details', 'wunder-bestellung'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)) : ?>
                <tr>
                    <td colspan="4"><?php echo esc_html__('No logs found.', 'wunder-bestellung'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                        <td><?php echo esc_html($log->username); ?></td>
                        <td><?php echo esc_html($log->action); ?></td>
                        <td><?php echo esc_html($log->details); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $page
                ));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div> 