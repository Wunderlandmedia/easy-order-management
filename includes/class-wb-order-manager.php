<?php
/**
 * Order Manager Class
 *
 * Handles all order-related functionality including:
 * - Retrieving orders with filtering and pagination
 * - Searching orders
 * - Updating order status
 *
 * @package Wunder_Bestellung
 * @since 1.0.0
 */

class WB_Order_Manager {
    /**
     * Get orders with filtering and pagination support.
     *
     * @since 1.0.0
     * @param array $args {
     *     Optional. Arguments to filter and paginate orders.
     *     
     *     @type int          $limit      Number of orders per page. Default 20.
     *     @type int          $paged      Current page number. Default 1.
     *     @type string       $orderby    Order by field. Default 'date'.
     *     @type string       $order      Order direction. Default 'DESC'.
     *     @type array|string $status     Order status(es). Default array('on-hold', 'processing', 'completed').
     *     @type string       $search     Search term. Default empty.
     *     @type string       $date_from  Start date in Y-m-d format. Default empty.
     *     @type string       $date_to    End date in Y-m-d format. Default empty.
     *     @type string       $type       Order type. Default 'shop_order'.
     * }
     * @return array Array of WC_Order objects.
     */
    public function get_orders(array $args = array()): array {
        $default_args = array(
            'limit' => 20,
            'paged' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => array('on-hold', 'processing', 'completed'),
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'type' => 'shop_order'
        );

        $args = wp_parse_args($args, $default_args);
        
        // Direct order lookup for numeric search
        if (!empty($args['search']) && is_numeric($args['search'])) {
            $order = wc_get_order(intval($args['search']));
            if ($order instanceof WC_Order && $order->get_type() === 'shop_order') {
                return array($order);
            }
        }

        // Build WooCommerce query args
        $query_args = array(
            'limit' => $args['limit'],
            'paged' => $args['paged'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
            'status' => $args['status'],
            'type' => $args['type']
        );

        // Add date filtering
        $date_query = array();
        
        if (!empty($args['date_from'])) {
            $date_query['after'] = sanitize_text_field($args['date_from']);
        }
        
        if (!empty($args['date_to'])) {
            $date_query['before'] = sanitize_text_field($args['date_to']) . ' 23:59:59';
        }
        
        if (!empty($date_query)) {
            $date_query['inclusive'] = true;
            $query_args['date_query'] = $date_query;
        }

        // Add search functionality
        if (!empty($args['search'])) {
            add_action('pre_get_posts', array($this, 'extend_order_search'));
            $query_args['s'] = sanitize_text_field($args['search']);
        }

        $orders = wc_get_orders($query_args);

        if (!empty($args['search'])) {
            remove_action('pre_get_posts', array($this, 'extend_order_search'));
        }

        return is_array($orders) ? $orders : array();
    }

    /**
     * Get total number of orders matching the given criteria.
     *
     * @since 1.0.0
     * @param array $args Same arguments as get_orders().
     * @return int Total number of orders.
     */
    public function get_total_orders(array $args = array()): int {
        unset($args['paged']);
        $args['limit'] = -1;
        $orders = $this->get_orders($args);
        return count($orders);
    }

    /**
     * Extend WP_Query search to include order meta fields.
     *
     * @since 1.0.0
     * @param WP_Query $query The WP_Query instance.
     * @return void
     */
    public function extend_order_search(WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $search_term = $query->get('s');
        if (empty($search_term)) {
            return;
        }

        // Remove the original search
        $query->set('s', '');

        // Add our custom search
        $meta_query = array('relation' => 'OR');

        // Search in order meta (both with and without underscore prefix)
        $search_fields = array(
            'billing_first_name',
            'billing_last_name',
            'billing_email',
            '_billing_first_name',
            '_billing_last_name',
            '_billing_email'
        );

        foreach ($search_fields as $field) {
            $meta_query[] = array(
                'key' => $field,
                'value' => sanitize_text_field($search_term),
                'compare' => 'LIKE'
            );
        }

        // Search in post ID if numeric
        if (is_numeric($search_term)) {
            $query->set('post__in', array(intval($search_term)));
        }

        // Add search in post title (order number)
        $meta_query[] = array(
            'key' => '_order_number',
            'value' => sanitize_text_field($search_term),
            'compare' => '='
        );

        // Combine with existing meta query
        $existing_meta_query = $query->get('meta_query');
        if (!empty($existing_meta_query)) {
            $meta_query = array_merge(array('relation' => 'AND'), array($existing_meta_query, $meta_query));
        }

        $query->set('meta_query', $meta_query);
    }

    /**
     * Update order status via AJAX.
     *
     * @since 1.0.0
     * @return void
     */
    public function update_order_status(): void {
        check_ajax_referer('wb_update_status', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(-1);
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if ($order_id && $new_status) {
            $order = wc_get_order($order_id);
            if ($order instanceof WC_Order && $order->get_type() === 'shop_order') {
                $order->update_status($new_status);
                wp_send_json_success();
            }
        }
        wp_send_json_error();
    }
} 