<?php
/**
 * Handles all the admin-side stuff for our order management plugin
 * Sets up the menu, loads assets, and handles order status updates
 */
class WB_Admin {
    private $order_manager;
    private $logger;

    public function __construct() {
        $this->order_manager = new WB_Order_Manager();
        $this->logger = new WB_Logger();
        
        // Hook into WordPress admin
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_wb_update_order_status', array($this->order_manager, 'update_order_status'));
    }

    /**
     * Adds our custom menu items and submenus
     */
    public function add_menu_item() {
        // Main menu page
        add_menu_page(
            esc_html__('Order Management', 'easy-order-management'),
            esc_html__('Order Management', 'easy-order-management'),
            'manage_woocommerce',
            'wb-order-management',
            array($this, 'render_orders_page'),
            'dashicons-clipboard',
            56
        );

        // Orders submenu (same as parent to show as first item)
        add_submenu_page(
            'wb-order-management',
            esc_html__('Orders', 'easy-order-management'),
            esc_html__('Orders', 'easy-order-management'),
            'manage_woocommerce',
            'wb-order-management',
            array($this, 'render_orders_page')
        );

        // Logs submenu
        add_submenu_page(
            'wb-order-management',
            esc_html__('Order Logs', 'easy-order-management'),
            esc_html__('Order Logs', 'easy-order-management'),
            'manage_woocommerce',
            'wb-order-management-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Loads CSS and JS files for the admin
     * Only loads them on our plugin's pages
     */
    public function enqueue_assets($hook) {
        // Check if we're on any of our plugin's pages
        $valid_pages = [
            'toplevel_page_wb-order-management',
            'order-management_page_wb-order-management-logs'
        ];

        if (!in_array($hook, $valid_pages)) {
            return;
        }

        // Load our styles for all plugin pages
        wp_enqueue_style(
            'wb-admin-styles',
            WB_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WB_VERSION
        );

        // Only load order management scripts on the main orders page
        if ($hook === 'toplevel_page_wb-order-management') {
            wp_enqueue_script(
                'wb-admin-scripts',
                WB_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                WB_VERSION,
                true
            );

            wp_localize_script('wb-admin-scripts', 'wbAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wb_update_status'),
                'messages' => array(
                    'success' => esc_html__('Status updated successfully!', 'easy-order-management'),
                    'error' => esc_html__('Error updating status!', 'easy-order-management')
                )
            ));
        }
    }

    /**
     * Shows the main orders page
     * Template handles all the display logic
     */
    public function render_orders_page() {
        include WB_PLUGIN_DIR . 'templates/orders-page.php';
    }

    /**
     * Shows the logs page
     * Template handles all the display logic
     */
    public function render_logs_page() {
        include WB_PLUGIN_DIR . 'templates/logs-page.php';
    }
} 