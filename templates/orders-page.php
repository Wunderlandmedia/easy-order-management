<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get current page and search parameters
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$search_query = isset($_GET['wb_search']) ? sanitize_text_field($_GET['wb_search']) : '';
$status_filter = isset($_GET['wb_status']) ? sanitize_text_field($_GET['wb_status']) : '';
$date_from = isset($_GET['wb_date_from']) ? sanitize_text_field($_GET['wb_date_from']) : '';
$date_to = isset($_GET['wb_date_to']) ? sanitize_text_field($_GET['wb_date_to']) : '';

// Get settings
$settings = get_option('wb_order_management_settings', []);
$columns = $settings['order_columns'] ?? [];
$per_page = $settings['orders_per_page'] ?? 20;

// Define available columns and their data
$available_columns = [
    'order_number' => [
        'label' => __('Order Number', 'easy-order-management'),
        'value' => function($order) { return $order->get_id(); }
    ],
    'order_date' => [
        'label' => __('Order Date', 'easy-order-management'),
        'value' => function($order) { return $order->get_date_created() ? $order->get_date_created()->date_i18n('d.m.Y') : ''; }
    ],
    'order_status' => [
        'label' => __('Order Status', 'easy-order-management'),
        'value' => function($order) { return wc_get_order_status_name($order->get_status()); }
    ],
    'customer_name' => [
        'label' => __('Customer Name', 'easy-order-management'),
        'value' => function($order) { return $order->get_formatted_billing_full_name(); }
    ],
    'order_total' => [
        'label' => __('Order Total', 'easy-order-management'),
        'value' => function($order) { 
            $total = $order->get_total();
            return wc_price($total);
        },
        'html' => true
    ],
    'shipping_address' => [
        'label' => __('Shipping Address', 'easy-order-management'),
        'value' => function($order) { 
            $address = $order->get_formatted_shipping_address();
            return str_replace('<br/>', ', ', $address); 
        }
    ],
    'payment_method' => [
        'label' => __('Payment Method', 'easy-order-management'),
        'value' => function($order) { return $order->get_payment_method_title(); }
    ],
];

// Add ACF fields to available columns if ACF is active
if (function_exists('acf_get_field_groups')) {
    $field_groups = acf_get_field_groups(['post_type' => 'shop_order']);
    
    foreach ($field_groups as $field_group) {
        $fields = acf_get_fields($field_group);
        if ($fields) {
            foreach ($fields as $field) {
                $available_columns['acf_' . $field['name']] = [
                    'label' => $field['label'],
                    'value' => function($order) use ($field) {
                        $value = get_field($field['name'], $order->get_id());
                        if (is_array($value)) {
                            return implode(', ', $value);
                        }
                        return $value;
                    }
                ];
            }
        }
    }
}
?>

<div class="wrap">
    <h1><?php _e('Orders', 'easy-order-management'); ?></h1>

    <!-- Search and Filters -->
    <div class="wb-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="easy-order-managementen">
            
            <input type="search" 
                   name="wb_search" 
                   value="<?php echo esc_attr($search_query); ?>" 
                   placeholder="<?php _e('Search orders', 'easy-order-management'); ?>"
                   class="wb-search-input">

            <select name="wb_status" class="wb-filter-select">
                <option value=""><?php _e('All Statuses', 'easy-order-management'); ?></option>
                <option value="on-hold" <?php selected($status_filter, 'on-hold'); ?>><?php echo esc_html($settings['status_labels']['on-hold'] ?? __('On Hold', 'easy-order-management')); ?></option>
                <option value="processing" <?php selected($status_filter, 'processing'); ?>><?php echo esc_html($settings['status_labels']['processing'] ?? __('Processing', 'easy-order-management')); ?></option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php echo esc_html($settings['status_labels']['completed'] ?? __('Completed', 'easy-order-management')); ?></option>
            </select>

            <input type="date" 
                   name="wb_date_from" 
                   value="<?php echo esc_attr($date_from); ?>" 
                   class="wb-date-input" 
                   placeholder="<?php _e('From', 'easy-order-management'); ?>">

            <input type="date" 
                   name="wb_date_to" 
                   value="<?php echo esc_attr($date_to); ?>" 
                   class="wb-date-input" 
                   placeholder="<?php _e('To', 'easy-order-management'); ?>">

            <button type="submit" class="button"><?php _e('Filter', 'easy-order-management'); ?></button>
            <a href="?page=easy-order-managementen" class="button"><?php _e('Reset', 'easy-order-management'); ?></a>
        </form>
    </div>

    <table class="wb-orders-table widefat">
        <thead>
            <tr>
                <?php
                // Display enabled columns
                foreach ($columns as $column_key => $enabled) {
                    if ($enabled && isset($available_columns[$column_key])) {
                        echo '<th class="column-' . esc_attr($column_key) . '">' . 
                             esc_html($available_columns[$column_key]['label']) . 
                             '</th>';
                    }
                }
                // Always show status and action columns
                ?>
                <th class="column-status"><?php _e('Status', 'easy-order-management'); ?></th>
                <th class="column-action"><?php _e('Action', 'easy-order-management'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Get filtered and paginated orders
            $args = array(
                'limit' => $per_page,
                'paged' => $current_page,
                'search' => $search_query,
                'status' => $status_filter ? array($status_filter) : array('on-hold', 'processing', 'completed'),
                'date_from' => $date_from,
                'date_to' => $date_to
            );

            $orders = $this->order_manager->get_orders($args);
            $total_orders = $this->order_manager->get_total_orders($args);
            $total_pages = ceil($total_orders / $per_page);

            if (empty($orders)) {
                echo '<tr><td colspan="' . (count(array_filter($columns)) + 2) . '">' . 
                     __('No orders found.', 'easy-order-management') . 
                     '</td></tr>';
            }

            foreach ($orders as $order) {
                // Skip if not a valid shop order
                if (!is_a($order, 'WC_Order') || $order->get_type() !== 'shop_order') {
                    continue;
                }

                try {
                    echo '<tr>';
                    
                    // Display enabled columns
                    foreach ($columns as $column_key => $enabled) {
                        if ($enabled && isset($available_columns[$column_key])) {
                            $value = $available_columns[$column_key]['value']($order);
                            echo '<td class="column-' . esc_attr($column_key) . '">';
                            if (isset($available_columns[$column_key]['html']) && $available_columns[$column_key]['html']) {
                                echo wp_kses_post($value);
                            } else {
                                echo esc_html($value);
                            }
                            echo '</td>';
                        }
                    }

                    // Always show status and action columns
                    $current_status = $order->get_status();
                    ?>
                    <td class="column-status">
                        <select class="wb-status-select" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                            <option value="on-hold" <?php selected($current_status, 'on-hold'); ?>>
                                <?php echo esc_html($settings['status_labels']['on-hold'] ?? __('On Hold', 'easy-order-management')); ?>
                            </option>
                            <option value="processing" <?php selected($current_status, 'processing'); ?>>
                                <?php echo esc_html($settings['status_labels']['processing'] ?? __('Processing', 'easy-order-management')); ?>
                            </option>
                            <option value="completed" <?php selected($current_status, 'completed'); ?>>
                                <?php echo esc_html($settings['status_labels']['completed'] ?? __('Completed', 'easy-order-management')); ?>
                            </option>
                        </select>
                    </td>
                    <td class="column-action">
                        <button class="button wb-update-status" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                            <?php _e('Update', 'easy-order-management'); ?>
                        </button>
                    </td>
                    <?php
                    echo '</tr>';
                } catch (Exception $e) {
                    error_log('Easy Order Management: Error processing order - ' . $e->getMessage());
                    continue;
                }
            }
            ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1) : ?>
    <div class="wb-pagination">
        <?php
        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current_page
        ));
        ?>
    </div>
    <?php endif; ?>
</div> 