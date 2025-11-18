<?php
/**
 * Security class for Easy Order Management
 *
 * Handles all security-related functionality including user permissions,
 * rate limiting, input validation, and security logging.
 *
 * @package Easy_Order_Management
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WB_Security
 *
 * Provides comprehensive security measures for the plugin including
 * authentication, authorization, rate limiting, and input sanitization.
 *
 * @since 1.0.0
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
        // Set up our security measures
        add_filter('wp_headers', [$this, 'add_security_headers']);
        add_action('admin_init', [$this, 'verify_user_capabilities']);
    }

    /**
     * Adds security headers to WordPress responses
     * Helps prevent common attacks like XSS, clickjacking, etc.
     * 
     * @param array $headers Existing headers
     * @return array Modified headers
     */
    public function add_security_headers($headers): array {
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['X-Frame-Options'] = 'SAMEORIGIN';
        $headers['X-XSS-Protection'] = '1; mode=block';
        $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        
        return $headers;
    }

    /**
     * Makes sure users can only access what they're supposed to
     * Kicks out anyone trying to be sneaky
     *
     * @return void
     */
    public function verify_user_capabilities(): void {
        // Only check our plugin pages
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        if (!empty($page) && strpos($page, 'easy-order-management') === 0) {
            // Get the VIP list (allowed roles)
            $settings = get_option('wb_order_management_settings', []);
            $role_access = $settings['role_access'] ?? ['administrator' => true, 'shop_manager' => true];

            // Show them the door if they're not on the list
            if (!$this->user_has_access($role_access)) {
                wp_die(
                    esc_html__('You do not have sufficient permissions to access this page.', 'easy-order-management'),
                    403
                );
            }
        }
    }

    /**
     * Checks if a user is cool to access the page
     * Admins always get in, others need to be on the guest list
     */
    public function user_has_access(array $role_access): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        
        // Admins get the VIP treatment
        if (in_array('administrator', $user->roles, true)) {
            return true;
        }

        // Check if they're on the list
        foreach ($user->roles as $role) {
            if (isset($role_access[$role]) && $role_access[$role]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stops users from hammering our AJAX endpoints
     * 
     * @param string $action What they're trying to do
     * @param int    $limit  How many times they can do it
     * @param int    $window Time window in seconds
     * @return bool|WP_Error True if they're good, WP_Error if they need to chill
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

        // Tell them to slow down if they're going too fast
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

        // Start or update their counter
        if ($current_count === 0) {
            set_transient($transient_key, 1, $window);
        } else {
            set_transient($transient_key, $current_count + 1, $window);
        }

        return true;
    }

    /**
     * Keeps track of suspicious activity
     * Only logs if WP_DEBUG is on - we're not trying to fill up error logs
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
     * Figures out where the request is coming from
     * Handles proxies and forwards - tries to get the real IP
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
     * Cleans up user input to keep things safe
     * Different types get different treatment
     *
     * @param mixed  $data The data to validate
     * @param string $type The type of validation to perform
     * @return mixed Validated data
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
            default:
                return sanitize_text_field($data);
        }
    }

    /**
     * Creates a nonce for forms and AJAX calls
     * Because timing attacks are no joke
     */
    public function generate_nonce(string $action): array {
        return [
            'nonce' => wp_create_nonce($action),
            'action' => $action
        ];
    }

    /**
     * Makes sure the nonce is legit
     * If it's not, logs it and shows them the door
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