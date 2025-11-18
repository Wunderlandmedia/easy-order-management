<?php
/**
 * Logger class for Easy Order Management
 *
 * Handles logging of order management activities with improved performance
 * and security measures.
 *
 * @package Easy_Order_Management
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WB_Logger
 *
 * Provides comprehensive logging functionality for order management activities.
 *
 * @since 1.0.0
 */
class WB_Logger {
    /**
     * Database table name for logs
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wb_logs';

        // Only create table if it doesn't exist (check transient to avoid repeated checks)
        if (false === get_transient('wb_logs_table_checked')) {
            $this->maybe_create_logs_table();
            set_transient('wb_logs_table_checked', true, WEEK_IN_SECONDS);
        }
    }

    /**
     * Create the logs table if it doesn't exist
     * Only runs when necessary, not on every instantiation
     *
     * @return void
     */
    private function maybe_create_logs_table(): void {
        global $wpdb;

        // Check if table exists first
        $table_exists = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $this->table_name
        ));

        if ($table_exists !== $this->table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$this->table_name} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                action varchar(255) NOT NULL,
                details text NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY created_at (created_at)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
    }

    /**
     * Add a new log entry
     *
     * @param string $action  The order ID or action performed
     * @param string $details Details about the action
     * @return bool|int False on failure, number of rows inserted on success
     */
    public function log(string $action, string $details) {
        global $wpdb;

        if (!is_user_logged_in()) {
            return false;
        }

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'user_id' => get_current_user_id(),
                'action'  => sanitize_text_field($action),
                'details' => wp_kses_post($details),
            ),
            array('%d', '%s', '%s')
        );

        return $result;
    }

    /**
     * Get logs with pagination
     *
     * @param int $page     Current page number
     * @param int $per_page Number of items per page
     * @return array Array of log entries
     */
    public function get_logs(int $page = 1, int $per_page = 20): array {
        if (!current_user_can('manage_woocommerce')) {
            return array();
        }

        global $wpdb;

        $offset = absint(($page - 1) * $per_page);
        $per_page = absint($per_page);

        // Use table name variables for wpdb::prepare compatibility
        $logs_table = $this->table_name;
        $users_table = $wpdb->users;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, u.display_name as username
                FROM {$logs_table} l
                LEFT JOIN {$users_table} u ON l.user_id = u.ID
                ORDER BY l.created_at DESC
                LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        return is_array($results) ? $results : array();
    }

    /**
     * Get total number of logs
     *
     * @return int Total number of logs
     */
    public function get_total_logs(): int {
        if (!current_user_can('manage_woocommerce')) {
            return 0;
        }

        global $wpdb;

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        return absint($count);
    }
} 