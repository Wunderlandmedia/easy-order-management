<?php
class WB_Admin {
    private $order_manager;

    public function __construct() {
        $this->order_manager = new WB_Order_Manager();
        
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_wb_update_order_status', array($this->order_manager, 'update_order_status'));
    }

    public function add_menu_item() {
        add_menu_page(
            esc_html__('Orders Management', 'easy-order-management'),
            esc_html__('Orders', 'easy-order-management'),
            'manage_woocommerce',
            'easy-order-managementen',
            array($this, 'render_orders_page'),
            'dashicons-clipboard',
            56
        );
    }

    public function enqueue_assets($hook) {
        if ('toplevel_page_easy-order-managementen' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'wb-admin-styles',
            WB_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WB_VERSION
        );

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

    public function render_orders_page() {
        include WB_PLUGIN_DIR . 'templates/orders-page.php';
    }
} 