<?php
/**
 * The brains behind order operations
 * Handles fetching, searching, and updating WooCommerce orders
 * 
 * This is where all the heavy lifting happens for order management.
 * If you need to modify order handling, this is your guy.
 */
class WB_Order_Manager {
    // Our bouncer - handles security checks
    private $security;

    public function __construct() {
        $this->security = new WB_Security();
    }

    /**
     * Gets a list of orders with all the bells and whistles
     * 
     * @param array $args {
     *     The good stuff you can filter by:
     *     
     *     @type int    $limit      Orders per page (default: 20)
     *     @type int    $paged      Which page we're on (default: 1)
     *     @type string $orderby    Sort field (default: 'date')
     *     @type string $order      ASC or DESC (default: 'DESC')
     *     @type array  $status     Which statuses to include (default: on-hold, processing, completed)
     *     @type string $search     Looking for something? (default: empty)
     *     @type string $date_from  Start date YYYY-MM-DD (default: empty)
     *     @type string $date_to    End date YYYY-MM-DD (default: empty)
     * }
     * @return array List of WC_Order objects
     */
    public function get_orders(array $args = array()): array {
        // Make sure they have permission to view orders
        if (!current_user_can('manage_woocommerce')) {
            $this->security->log_security_event('unauthorized_order_access', [
                'args' => $args
            ]);
            return array();
        }

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

        // Clean up the input
        $args = array_map([$this->security, 'validate_data'], $args, array_fill(0, count($args), 'text'));
        $args = wp_parse_args($args, $default_args);
        
        // Quick shortcut: if searching for order number, try direct lookup first
        if (!empty($args['search']) && is_numeric($args['search'])) {
            $order = wc_get_order(intval($args['search']));
            if ($order instanceof WC_Order && $order->get_type() === 'shop_order') {
                return array($order);
            }
        }

        // Set up the main query args
        $query_args = array(
            'limit' => $args['limit'],
            'paged' => $args['paged'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
            'status' => $args['status'],
            'type' => $args['type']
        );

        // Handle date filtering
        $date_query = array();
        
        if (!empty($args['date_from'])) {
            $date_query['after'] = $this->security->validate_data($args['date_from'], 'text');
        }
        
        if (!empty($args['date_to'])) {
            // Include the whole day by setting time to end of day
            $date_query['before'] = $this->security->validate_data($args['date_to'], 'text') . ' 23:59:59';
        }
        
        if (!empty($date_query)) {
            $date_query['inclusive'] = true;
            $query_args['date_query'] = $date_query;
        }

        // Hook in our extended search if needed
        if (!empty($args['search'])) {
            add_action('pre_get_posts', array($this, 'extend_order_search'));
            $query_args['s'] = $this->security->validate_data($args['search'], 'text');
        }

        $orders = wc_get_orders($query_args);

        // Clean up our search hook
        if (!empty($args['search'])) {
            remove_action('pre_get_posts', array($this, 'extend_order_search'));
        }

        return is_array($orders) ? $orders : array();
    }

    /**
     * Counts total orders matching the filters
     * Useful for pagination calculations
     */
    public function get_total_orders(array $args = array()): int {
        // Make sure they have permission
        if (!current_user_can('manage_woocommerce')) {
            return 0;
        }

        unset($args['paged']);
        $args['limit'] = -1;
        $orders = $this->get_orders($args);
        return count($orders);
    }

    /**
     * Makes the order search actually useful
     * 
     * By default, WP only searches in title/content
     * This adds searching in order meta like customer name, email, etc.
     */
    public function extend_order_search(WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $search_term = $query->get('s');
        if (empty($search_term)) {
            return;
        }

        // Ditch the original search
        $query->set('s', '');

        // Build our beefed-up search
        $meta_query = array('relation' => 'OR');

        // Fields we want to search in
        $search_fields = array(
            'billing_first_name',
            'billing_last_name',
            'billing_email',
            '_billing_first_name',
            '_billing_last_name',
            '_billing_email'
        );

        $search_term = $this->security->validate_data($search_term, 'text');

        foreach ($search_fields as $field) {
            $meta_query[] = array(
                'key' => $field,
                'value' => $search_term,
                'compare' => 'LIKE'
            );
        }

        // If it looks like an order number, search that too
        if (is_numeric($search_term)) {
            $query->set('post__in', array(intval($search_term)));
        }

        // Also check the actual order number meta
        $meta_query[] = array(
            'key' => '_order_number',
            'value' => $search_term,
            'compare' => '='
        );

        // Merge with any existing meta queries
        $existing_meta_query = $query->get('meta_query');
        if (!empty($existing_meta_query)) {
            $meta_query = array_merge(array('relation' => 'AND'), array($existing_meta_query, $meta_query));
        }

        $query->set('meta_query', $meta_query);
    }

    /**
     * Updates an order's status via AJAX
     * Handles the status update button clicks from the orders table
     */
    public function update_order_status(): void {
        // Check nonce and rate limiting
        $nonce_check = $this->security->verify_nonce(
            isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '',
            'wb_update_status'
        );
        if (is_wp_error($nonce_check)) {
            wp_send_json_error($nonce_check->get_error_message());
            return;
        }

        // Check rate limiting
        $rate_check = $this->security->check_rate_limit('update_order_status', 10, 60);
        if (is_wp_error($rate_check)) {
            wp_send_json_error($rate_check->get_error_message());
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            $this->security->log_security_event('unauthorized_status_update', [
                'order_id' => isset($_POST['order_id']) ? absint($_POST['order_id']) : 0
            ]);
            wp_die(-1);
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $new_status = isset($_POST['status']) ? $this->security->validate_data($_POST['status'], 'text') : '';

        if ($order_id && $new_status) {
            $order = wc_get_order($order_id);
            if ($order instanceof WC_Order && $order->get_type() === 'shop_order') {
                $order->update_status($new_status);
                $this->security->log_security_event('order_status_updated', [
                    'order_id' => $order_id,
                    'new_status' => $new_status
                ]);
                wp_send_json_success();
            }
        }
        wp_send_json_error();
    }
} 