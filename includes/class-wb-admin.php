<?php
/**
 * Admin class for Easy Order Management
 *
 * Handles all admin-side functionality including menu items, asset loading,
 * and AJAX handlers for the order management interface.
 *
 * @package Easy_Order_Management
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WB_Admin
 *
 * Manages the WordPress admin interface for the plugin.
 *
 * @since 1.0.0
 */
class WB_Admin {
    /**
     * Order manager instance
     *
     * @var WB_Order_Manager
     */
    private $order_manager;

    /**
     * Logger instance
     *
     * @var WB_Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * Initializes the admin interface and sets up WordPress hooks.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->order_manager = new WB_Order_Manager();
        $this->logger = new WB_Logger();

        // Hook into WordPress admin
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_wb_update_order_status', array($this->order_manager, 'update_order_status'));
    }

    /**
     * Adds custom menu items and submenus to WordPress admin
     *
     * Creates the main Order Management menu and its subpages.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_menu_item(): void {
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
     *
     * Only enqueues assets on our plugin's pages to avoid conflicts
     * and improve performance.
     *
     * @since 1.0.0
     * @param string $hook The current admin page hook
     * @return void
     */
    public function enqueue_assets(string $hook): void {
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
     * Renders the main orders page
     *
     * Loads the orders page template file.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_orders_page(): void {
        include WB_PLUGIN_DIR . 'templates/orders-page.php';
    }

    /**
     * Renders the logs page
     *
     * Loads the logs page template file.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_logs_page(): void {
        include WB_PLUGIN_DIR . 'templates/logs-page.php';
    }
} 