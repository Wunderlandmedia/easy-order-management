<?php
/**
 * Logger class for WunderBestellung
 *
 * @package Wunder_Bestellung
 */

class WB_Logger {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wb_logs';
        $this->create_logs_table();
    }

    /**
     * Create the logs table if it doesn't exist
     */
    private function create_logs_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(255) NOT NULL,
            details text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add a new log entry
     *
     * @param string $action The order ID or action performed
     * @param string $details Details about the action
     * @return bool|int False on failure, log ID on success
     */
    public function log($action, $details) {
        global $wpdb;
        
        if (!is_user_logged_in()) {
            return false;
        }

        return $wpdb->insert(
            $this->table_name,
            array(
                'user_id' => get_current_user_id(),
                'action' => $action,
                'details' => $details
            ),
            array('%d', '%s', '%s')
        );
    }

    /**
     * Get logs with pagination
     *
     * @param int $page Current page number
     * @param int $per_page Number of items per page
     * @return array Array of log entries
     */
    public function get_logs($page = 1, $per_page = 20) {
        if (!current_user_can('administrator')) {
            return array();
        }

        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, u.display_name as username 
                FROM {$this->table_name} l 
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
                ORDER BY l.created_at DESC 
                LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
    }

    /**
     * Get total number of logs
     *
     * @return int Total number of logs
     */
    public function get_total_logs() {
        if (!current_user_can('administrator')) {
            return 0;
        }

        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }
} 