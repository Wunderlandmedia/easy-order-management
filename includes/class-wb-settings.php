<?php
/**
 * The control center for our plugin
 * 
 * This is where all the settings magic happens. Handles everything from
 * saving your preferences to making the admin interface look pretty.
 * 
 * Pro tip: Most of the plugin's behavior can be tweaked from here!
 */

// Keep the hackers out
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WB_Settings
 * Handles all plugin settings and options
 */
class WB_Settings {
    /**
     * Where we store all our settings in wp_options
     */
    const OPTION_KEY = 'wb_order_management_settings';

    /**
     * Constructor
     */
    public function __construct() {
        // Hook into WordPress admin
        add_action('admin_menu', [$this, 'add_settings_menu'], 99);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Adds our settings page under WooCommerce menu
     * We use priority 99 to stick it near the bottom
     */
    public function add_settings_menu(): void {
        add_submenu_page(
            'woocommerce',
            __('Easy Order Management Settings', 'easy-order-management'),
            __('Order Management', 'easy-order-management'),
            'manage_woocommerce',
            'wb-order-management-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Sets up all our settings fields and sections
     * This is where we tell WordPress what we want to save
     */
    public function register_settings(): void {
        register_setting(
            'wb_order_management_settings',
            self::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->get_default_settings(),
            ]
        );

        // General stuff - the basics everyone needs
        add_settings_section(
            'wb_general_settings',
            __('General Settings', 'easy-order-management'),
            [$this, 'render_general_settings_section'],
            'wb-order-management-settings'
        );

        // Add the basic fields
        add_settings_field(
            'order_columns',
            __('Order Table Columns', 'easy-order-management'),
            [$this, 'render_order_columns_field'],
            'wb-order-management-settings',
            'wb_general_settings'
        );

        add_settings_field(
            'orders_per_page',
            __('Orders Per Page', 'easy-order-management'),
            [$this, 'render_orders_per_page_field'],
            'wb-order-management-settings',
            'wb_general_settings'
        );

        // Advanced configuration - for the power users
        add_settings_section(
            'wb_field_config',
            __('Field Configuration', 'easy-order-management'),
            [$this, 'render_field_config_section'],
            'wb-order-management-settings'
        );

        add_settings_field(
            'status_labels',
            __('Custom Status Labels', 'easy-order-management'),
            [$this, 'render_status_labels_field'],
            'wb-order-management-settings',
            'wb_field_config'
        );

        add_settings_field(
            'role_access',
            __('Role Access Control', 'easy-order-management'),
            [$this, 'render_role_access_field'],
            'wb-order-management-settings',
            'wb_field_config'
        );
    }

    /**
     * The factory settings - what you get out of the box
     * Feel free to override these with the filter hook
     */
    public function get_default_settings(): array {
        return [
            'order_columns' => [
                'order_number' => true,
                'order_date' => true,
                'order_status' => true,
                'customer_name' => true,
                'order_total' => true,
            ],
            'orders_per_page' => 20,
            'status_labels' => [
                'on-hold' => __('On Hold', 'easy-order-management'),
                'processing' => __('Processing', 'easy-order-management'),
                'completed' => __('Completed', 'easy-order-management'),
            ],
            'role_access' => [
                'shop_manager' => true,
                'administrator' => true,
            ],
        ];
    }

    /**
     * Keeps the bad data out
     * Makes sure users can't save anything nasty
     */
    public function sanitize_settings(array $input): array {
        $sanitized = [];
        
        // Clean up the column settings
        if (isset($input['order_columns']) && is_array($input['order_columns'])) {
            $sanitized['order_columns'] = array_map('rest_sanitize_boolean', $input['order_columns']);
        }

        // Make sure orders per page is a sensible number
        $sanitized['orders_per_page'] = isset($input['orders_per_page']) 
            ? absint($input['orders_per_page']) 
            : 20;

        // Clean up status label text
        if (isset($input['status_labels']) && is_array($input['status_labels'])) {
            $sanitized['status_labels'] = array_map('sanitize_text_field', $input['status_labels']);
        }

        // Make sure role access is boolean
        if (isset($input['role_access']) && is_array($input['role_access'])) {
            $sanitized['role_access'] = array_map('rest_sanitize_boolean', $input['role_access']);
        }

        return $sanitized;
    }

    /**
     * The main settings page layout
     * Tabs, forms, and all that good stuff
     */
    public function render_settings_page(): void {
        // Make sure they belong here
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'easy-order-management'));
        }

        // Figure out which tab we're on
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        $tabs = [
            'general' => __('General Settings', 'easy-order-management'),
            'fields' => __('Field Configuration', 'easy-order-management'),
        ];

        // Handle form submission
        $nonce_verified = false;
        if (isset($_POST['_wpnonce'])) {
            $nonce_verified = wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'wb_order_management_settings-options');
        }

        if ($nonce_verified && isset($_POST['submit'])) {
            check_admin_referer('wb_order_management_settings-options');
        }
        ?>
        <div class="wrap wb-settings-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <?php
                foreach ($tabs as $tab_key => $tab_label) {
                    $active = $current_tab === $tab_key ? 'nav-tab-active' : '';
                    $url = add_query_arg('tab', $tab_key);
                    echo '<a href="' . esc_url($url) . '" class="nav-tab ' . esc_attr($active) . '">' . esc_html($tab_label) . '</a>';
                }
                ?>
            </nav>

            <form action="options.php" method="post">
                <?php
                settings_fields('wb_order_management_settings');
                
                if ($current_tab === 'general') {
                    $this->render_general_settings_tab();
                } else {
                    $this->render_field_config_tab();
                }
                
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * The basic settings tab
     * Simple stuff that everyone needs to configure
     */
    private function render_general_settings_tab(): void {
        ?>
        <div id="general-settings" class="settings-tab">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Orders Per Page', 'easy-order-management'); ?></th>
                    <td><?php $this->render_orders_per_page_field(); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Role Access Control', 'easy-order-management'); ?></th>
                    <td><?php $this->render_role_access_field(); ?></td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * The power user settings tab
     * More advanced stuff for customizing the plugin
     */
    private function render_field_config_tab(): void {
        ?>
        <div id="field-config" class="settings-tab">
            <h2><?php esc_html_e('Column Management', 'easy-order-management'); ?></h2>
            <div class="column-management">
                <?php $this->render_column_management_field(); ?>
            </div>

            <h2><?php esc_html_e('Status Labels', 'easy-order-management'); ?></h2>
            <div class="status-labels-section">
                <?php $this->render_status_labels_field(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * The drag-and-drop column manager
     * Let users pick what they want to see
     */
    public function render_column_management_field(): void {
        $options = get_option(self::OPTION_KEY, $this->get_default_settings());
        $columns = $options['order_columns'] ?? [];

        // The columns we offer out of the box
        $available_columns = [
            'order_number' => __('Order Number', 'easy-order-management'),
            'order_date' => __('Order Date', 'easy-order-management'),
            'order_status' => __('Order Status', 'easy-order-management'),
            'customer_name' => __('Customer Name', 'easy-order-management'),
            'order_total' => __('Order Total', 'easy-order-management'),
            'shipping_address' => __('Shipping Address', 'easy-order-management'),
            'payment_method' => __('Payment Method', 'easy-order-management'),
        ];

        // Add ACF fields if they exist
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups(['post_type' => 'shop_order']);
            
            foreach ($field_groups as $field_group) {
                $fields = acf_get_fields($field_group);
                if ($fields) {
                    foreach ($fields as $field) {
                        $available_columns['acf_' . $field['name']] = $field['label'];
                    }
                }
            }
        }
        ?>
        <div class="columns-container">
            <div class="columns-list sortable">
                <?php 
                foreach ($columns as $key => $enabled) {
                    if ($enabled && isset($available_columns[$key])) {
                        $this->render_column_item($key, $available_columns[$key]);
                    }
                }
                ?>
            </div>
            <div class="add-column-section">
                <select id="available-columns">
                    <option value=""><?php esc_html_e('Select a column to add', 'easy-order-management'); ?></option>
                    <?php
                    foreach ($available_columns as $key => $label) {
                        if (!isset($columns[$key]) || !$columns[$key]) {
                            echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
                        }
                    }
                    ?>
                </select>
                <button type="button" class="button add-column"><?php esc_html_e('Add Column', 'easy-order-management'); ?></button>
            </div>
        </div>

        <script type="text/template" id="column-template">
            <?php $this->render_column_item('{{key}}', '{{label}}'); ?>
        </script>
        <?php
    }

    /**
     * A single column in the manager
     * Shows the label and delete button
     */
    private function render_column_item(string $key, string $label): void {
        ?>
        <div class="column-item" data-key="<?php echo esc_attr($key); ?>">
            <span class="dashicons dashicons-menu handle"></span>
            <span class="column-label"><?php echo esc_html($label); ?></span>
            <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[order_columns][<?php echo esc_attr($key); ?>]" value="1">
            <button type="button" class="button remove-column">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <?php
    }

    /**
     * Intro text for general settings
     * Just a friendly explanation
     */
    public function render_general_settings_section(): void {
        echo '<p>' . esc_html__('Configure the general settings for the order management interface.', 'easy-order-management') . '</p>';
    }

    /**
     * The column picker checkboxes
     * Old school but gets the job done
     */
    public function render_order_columns_field(): void {
        $options = get_option(self::OPTION_KEY, $this->get_default_settings());
        $columns = $options['order_columns'] ?? [];
        $available_columns = [
            'order_number' => __('Order Number', 'easy-order-management'),
            'order_date' => __('Order Date', 'easy-order-management'),
            'order_status' => __('Order Status', 'easy-order-management'),
            'customer_name' => __('Customer Name', 'easy-order-management'),
            'order_total' => __('Order Total', 'easy-order-management'),
            'shipping_address' => __('Shipping Address', 'easy-order-management'),
            'payment_method' => __('Payment Method', 'easy-order-management'),
        ];

        foreach ($available_columns as $key => $label) {
            $checked = isset($columns[$key]) && $columns[$key] ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[order_columns][' . esc_attr($key) . ']" ' . esc_attr($checked) . ' value="1">';
            echo ' ' . esc_html($label);
            echo '</label>';
        }
    }

    /**
     * How many orders to show per page
     * Simple number input with some limits
     */
    public function render_orders_per_page_field(): void {
        $options = get_option(self::OPTION_KEY, $this->get_default_settings());
        $value = $options['orders_per_page'] ?? 20;
        echo '<input type="number" name="' . esc_attr(self::OPTION_KEY) . '[orders_per_page]" value="' . esc_attr($value) . '" min="1" max="100" step="1">';
    }

    /**
     * Intro text for field config
     * Explains the more advanced options
     */
    public function render_field_config_section(): void {
        echo '<p>' . esc_html__('Configure custom fields, status labels, and access control for the order management interface.', 'easy-order-management') . '</p>';
    }

    /**
     * Custom labels for order statuses
     * Let users speak their language
     */
    public function render_status_labels_field(): void {
        $options = get_option(self::OPTION_KEY, $this->get_default_settings());
        $status_labels = $options['status_labels'] ?? [];
        $default_statuses = ['on-hold', 'processing', 'completed'];

        foreach ($default_statuses as $status) {
            $value = $status_labels[$status] ?? ucfirst(str_replace('-', ' ', $status));
            ?>
            <div class="status-label-row">
                <label>
                    <?php echo esc_html(ucfirst(str_replace('-', ' ', $status))); ?>:
                    <input type="text" 
                           name="<?php echo esc_attr(self::OPTION_KEY); ?>[status_labels][<?php echo esc_attr($status); ?>]" 
                           value="<?php echo esc_attr($value); ?>">
                </label>
            </div>
            <?php
        }
    }

    /**
     * Who gets to use this plugin
     * Set up the VIP list
     */
    public function render_role_access_field(): void {
        $options = get_option(self::OPTION_KEY, $this->get_default_settings());
        $role_access = $options['role_access'] ?? [];
        $roles = wp_roles()->get_names();

        foreach ($roles as $role_key => $role_name) {
            if (in_array($role_key, ['administrator', 'shop_manager'], true)) {
                $checked = isset($role_access[$role_key]) ? $role_access[$role_key] : true;
                ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" 
                           name="<?php echo esc_attr(self::OPTION_KEY); ?>[role_access][<?php echo esc_attr($role_key); ?>]" 
                           value="1" 
                           <?php checked($checked, true); ?>>
                    <?php echo esc_html($role_name); ?>
                </label>
                <?php
            }
        }
        echo '<p class="description">' . esc_html__('Select which user roles can access the order management interface.', 'easy-order-management') . '</p>';
    }

    /**
     * Loads our CSS and JS
     * But only when we need it
     */
    public function enqueue_admin_scripts(string $hook): void {
        if ('woocommerce_page_wb-order-management-settings' !== $hook) {
            return;
        }

        // Make it pretty
        wp_enqueue_style(
            'wb-admin-settings',
            WB_PLUGIN_URL . 'assets/css/admin-settings.css',
            [],
            WB_VERSION
        );

        // Need this for drag and drop
        wp_enqueue_script(
            'jquery-ui-sortable'
        );

        // Our custom JS
        wp_enqueue_script(
            'wb-admin-settings',
            WB_PLUGIN_URL . 'assets/js/admin-settings.js',
            ['jquery', 'jquery-ui-sortable'],
            WB_VERSION,
            true
        );

        // Translations for JS
        wp_localize_script(
            'wb-admin-settings',
            'wb_settings',
            [
                'i18n' => [
                    'min_columns_required' => __('At least one column must be selected.', 'easy-order-management'),
                    'custom_field_required' => __('Custom field key and label are required.', 'easy-order-management'),
                    'field_key' => __('Field Key', 'easy-order-management'),
                    'field_label' => __('Field Label', 'easy-order-management'),
                    'text' => __('Text', 'easy-order-management'),
                    'number' => __('Number', 'easy-order-management'),
                    'date' => __('Date', 'easy-order-management'),
                    'select' => __('Select', 'easy-order-management'),
                    'required' => __('Required', 'easy-order-management'),
                    'remove' => __('Remove', 'easy-order-management'),
                ],
            ]
        );
    }
} 