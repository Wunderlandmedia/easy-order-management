<?php
/**
 * Security management class
 *
 * @package Easy_Order_Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WB_Security
 * Handles security-related functionality
 */
class WB_Security {
    /**
     * Rate limiting transient prefix
     */
    const RATE_LIMIT_PREFIX = 'wb_rate_limit_';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'init_security_headers']);
        add_action('admin_init', [$this, 'verify_user_capabilities']);
    }

    /**
     * Initialize security headers
     */
    public function init_security_headers(): void {
        // Set security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Verify user capabilities
     */
    public function verify_user_capabilities(): void {
        global $pagenow;

        // Check if we're on our plugin's pages
        if (!empty($_GET['page']) && strpos($_GET['page'], 'easy-order-management') === 0) {
            // Get allowed roles from settings
            $settings = get_option('wb_order_management_settings', []);
            $role_access = $settings['role_access'] ?? ['administrator' => true, 'shop_manager' => true];

            // Check if user has permission
            if (!$this->user_has_access($role_access)) {
                wp_die(
                    esc_html__('You do not have sufficient permissions to access this page.', 'easy-order-management'),
                    403
                );
            }
        }
    }

    /**
     * Check if user has access based on role settings
     *
     * @param array $role_access Array of roles and their access status
     * @return bool
     */
    public function user_has_access(array $role_access): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        
        // Always allow administrators
        if (in_array('administrator', $user->roles, true)) {
            return true;
        }

        // Check each of the user's roles against allowed roles
        foreach ($user->roles as $role) {
            if (isset($role_access[$role]) && $role_access[$role]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rate limit check for AJAX requests
     *
     * @param string $action The action being rate limited
     * @param int    $limit The number of allowed requests
     * @param int    $window The time window in seconds
     * @return bool|WP_Error
     */
    public function check_rate_limit(string $action, int $limit = 10, int $window = 60) {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'not_logged_in',
                __('You must be logged in to perform this action.', 'easy-order-management')
            );
        }

        $user_id = get_current_user_id();
        $transient_key = self::RATE_LIMIT_PREFIX . $action . '_' . $user_id;
        $current_count = (int) get_transient($transient_key);

        if ($current_count >= $limit) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    /* translators: %d: number of seconds */
                    __('Rate limit exceeded. Please wait %d seconds before trying again.', 'easy-order-management'),
                    $window
                )
            );
        }

        if ($current_count === 0) {
            set_transient($transient_key, 1, $window);
        } else {
            set_transient($transient_key, $current_count + 1, $window);
        }

        return true;
    }

    /**
     * Log security events
     *
     * @param string $event The event to log
     * @param array  $data Additional data to log
     * @return void
     */
    public function log_security_event(string $event, array $data = []): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $username = $user ? $user->user_login : 'unknown';
        $ip = $this->get_client_ip();

        $log_entry = sprintf(
            '[%s] Security Event: %s | User: %s (ID: %d) | IP: %s | Data: %s',
            current_time('mysql'),
            $event,
            $username,
            $user_id,
            $ip,
            json_encode($data)
        );

        error_log($log_entry);
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip(): string {
        $ip_headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = filter_var($_SERVER[$header], FILTER_VALIDATE_IP);
                if ($ip !== false) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Validate and sanitize input data
     *
     * @param mixed  $data The data to validate
     * @param string $type The type of data
     * @return mixed
     */
    public function validate_data($data, string $type) {
        switch ($type) {
            case 'int':
                return filter_var($data, FILTER_VALIDATE_INT);
            case 'float':
                return filter_var($data, FILTER_VALIDATE_FLOAT);
            case 'email':
                return filter_var($data, FILTER_VALIDATE_EMAIL);
            case 'url':
                return filter_var($data, FILTER_VALIDATE_URL);
            case 'bool':
                return filter_var($data, FILTER_VALIDATE_BOOLEAN);
            case 'ip':
                return filter_var($data, FILTER_VALIDATE_IP);
            case 'text':
                return sanitize_text_field($data);
            case 'html':
                return wp_kses_post($data);
            case 'sql':
                global $wpdb;
                return $wpdb->prepare('%s', $data);
            default:
                return sanitize_text_field($data);
        }
    }

    /**
     * Generate nonce field and action
     *
     * @param string $action The nonce action
     * @return array
     */
    public function generate_nonce(string $action): array {
        return [
            'nonce' => wp_create_nonce($action),
            'action' => $action
        ];
    }

    /**
     * Verify nonce
     *
     * @param string $nonce The nonce to verify
     * @param string $action The nonce action
     * @return bool|WP_Error
     */
    public function verify_nonce(string $nonce, string $action) {
        if (!wp_verify_nonce($nonce, $action)) {
            $this->log_security_event('invalid_nonce', [
                'action' => $action,
                'nonce' => $nonce
            ]);
            return new WP_Error(
                'invalid_nonce',
                __('Security check failed. Please refresh the page and try again.', 'easy-order-management')
            );
        }
        return true;
    }
} 