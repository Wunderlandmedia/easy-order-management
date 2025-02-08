<?php
/**
 * Easy Order Management
 *
 * A simple yet powerful order management system for WooCommerce.
 *
 * @package   Easy_Order_Management
 * @author    Wunderlandmedia
 * @license   MIT
 * @link      https://wunderlandmedia.com
 * @copyright 2024 Wunderlandmedia
 *
 * @wordpress-plugin
 * Plugin Name: Easy Order Management
 * Plugin URI:  https://wunderlandmedia.com
 * Description: Simple and secure order management for WooCommerce with custom field support and role-based access control.
 * Version:     1.0.0
 * Author:      Wunderlandmedia
 * Author URI:  https://wunderlandmedia.com
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: easy-order-management
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 *
 * MIT License
 *
 * Copyright (c) 2024 Wunderlandmedia
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * Usage Examples:
 * 
 * 1. Basic setup:
 * - Install and activate the plugin
 * - Go to WooCommerce > Order Management to configure settings
 * - Select which columns to display
 * - Configure custom status labels
 * - Set up role access permissions
 *
 * 2. Adding custom columns:
 * - Create ACF fields for orders
 * - Go to Column Management
 * - Add your ACF fields to the display
 *
 * 3. Extending the plugin:
 * - Use provided action and filter hooks
 * - Override templates in your theme
 * - Extend core classes
 *
 * Available Hooks:
 * - 'wb_before_order_list': Before orders table
 * - 'wb_after_order_list': After orders table
 * - 'wb_order_columns': Filter order columns
 * - 'wb_order_data': Filter order data
 * - 'wb_status_labels': Filter status labels
 * - 'wb_role_access': Filter role access
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WB_VERSION', '1.0.0');
define('WB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WB_MINIMUM_WP_VERSION', '5.0');
define('WB_MINIMUM_PHP_VERSION', '7.4');
define('WB_MINIMUM_WC_VERSION', '6.0');

/**
 * Load plugin text domain
 */
function wb_load_textdomain(): void {
    load_plugin_textdomain(
        'easy-order-management',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('init', 'wb_load_textdomain');

/**
 * Plugin activation check
 *
 * Verifies that the server meets the minimum requirements.
 *
 * @since 1.0.0
 * @return void
 */
function wb_activation_check(): void {
    if (version_compare(PHP_VERSION, WB_MINIMUM_PHP_VERSION, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                /* translators: %s: Minimum PHP version */
                esc_html__('Easy Order Management requires PHP version %s or higher.', 'easy-order-management'),
                WB_MINIMUM_PHP_VERSION
            )
        );
    }

    if (version_compare($GLOBALS['wp_version'], WB_MINIMUM_WP_VERSION, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                /* translators: %s: Minimum WordPress version */
                esc_html__('Easy Order Management requires WordPress version %s or higher.', 'easy-order-management'),
                WB_MINIMUM_WP_VERSION
            )
        );
    }

    // Create necessary database tables and options
    wb_install();
}
register_activation_hook(__FILE__, 'wb_activation_check');

/**
 * Plugin installation
 *
 * Sets up the initial plugin configuration.
 *
 * @since 1.0.0
 * @return void
 */
function wb_install(): void {
    // Set default options if they don't exist
    if (!get_option('wb_order_management_settings')) {
        $default_settings = [
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
        update_option('wb_order_management_settings', $default_settings);
    }

    // Trigger action for additional installation tasks
    do_action('wb_install');
}

/**
 * Plugin uninstallation
 *
 * Cleans up plugin data when uninstalled.
 *
 * @since 1.0.0
 * @return void
 */
function wb_uninstall(): void {
    // Only run if WordPress core uninstall function exists
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        return;
    }

    // Clean up options
    delete_option('wb_order_management_settings');

    // Trigger action for additional cleanup
    do_action('wb_uninstall');
}
register_uninstall_hook(__FILE__, 'wb_uninstall');

// Autoload classes
spl_autoload_register(function (string $class): void {
    $prefix = 'WB_';
    $base_dir = WB_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $class)) . '.php';

    if (file_exists($file)) {
        require_once $file;
        if (!class_exists($class)) {
            error_log(sprintf('Class %s not found in file %s', $class, $file));
        }
    } else {
        error_log(sprintf('File not found: %s for class %s', $file, $class));
    }
});

/**
 * Initialize the plugin
 *
 * @since 1.0.0
 * @return void
 */
function wb_init(): void {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function(): void {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    printf(
                        /* translators: %s: WooCommerce version */
                        esc_html__('Easy Order Management requires WooCommerce version %s or higher to be installed and activated.', 'easy-order-management'),
                        WB_MINIMUM_WC_VERSION
                    );
                    ?>
                </p>
            </div>
            <?php
        });
        return;
    }

    // Check WooCommerce version
    if (defined('WC_VERSION') && version_compare(WC_VERSION, WB_MINIMUM_WC_VERSION, '<')) {
        add_action('admin_notices', function(): void {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    printf(
                        /* translators: %s: WooCommerce version */
                        esc_html__('Easy Order Management requires WooCommerce version %s or higher.', 'easy-order-management'),
                        WB_MINIMUM_WC_VERSION
                    );
                    ?>
                </p>
            </div>
            <?php
        });
        return;
    }

    try {
        // Initialize security
        $security = new WB_Security();

        // Initialize admin
        if (is_admin()) {
            new WB_Admin();
            new WB_Settings();
        }

        // Set up error handling
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno)) {
                return false;
            }

            $error_message = sprintf(
                'Error [%d]: %s in %s on line %d',
                $errno,
                $errstr,
                $errfile,
                $errline
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log($error_message);
            }

            if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            }

            return true;
        });

    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Easy Order Management Error: ' . $e->getMessage());
        }

        add_action('admin_notices', function() use ($e): void {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('An error occurred while initializing Easy Order Management.', 'easy-order-management'); ?></p>
                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                    <p><code><?php echo esc_html($e->getMessage()); ?></code></p>
                <?php endif; ?>
            </div>
            <?php
        });
    }
}
add_action('plugins_loaded', 'wb_init'); 