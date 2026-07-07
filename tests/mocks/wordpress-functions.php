<?php
/**
 * WordPress Mock Functions for Testing
 *
 * Provides minimal WordPress function stubs for unit tests
 * that don't require full WordPress installation.
 *
 * @package BSO\Survival\Tests
 */

if (!function_exists('add_action')) {
    /**
     * Register a function to run at a specific hook
     */
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $wp_actions;
        if (!isset($wp_actions)) {
            $wp_actions = [];
        }
        if (!isset($wp_actions[$hook])) {
            $wp_actions[$hook] = [];
        }
        $wp_actions[$hook][$priority][] = [
            'callback' => $callback,
            'accepted_args' => $accepted_args
        ];
        return true;
    }
}

if (!function_exists('do_action')) {
    /**
     * Execute functions registered to a specific action hook
     */
    function do_action($hook, ...$args) {
        global $wp_actions;
        if (!isset($wp_actions[$hook])) {
            return;
        }
        
        // Sort by priority (lower = earlier)
        ksort($wp_actions[$hook]);
        
        foreach ($wp_actions[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $callback_data) {
                $callback = $callback_data['callback'];
                $num_args = $callback_data['accepted_args'];
                call_user_func_array($callback, array_slice($args, 0, $num_args));
            }
        }
    }
}

if (!function_exists('apply_filters')) {
    /**
     * Apply filters to a value
     */
    function apply_filters($hook, $value, ...$args) {
        global $wp_filters;
        if (!isset($wp_filters[$hook])) {
            return $value;
        }
        
        ksort($wp_filters[$hook]);
        
        foreach ($wp_filters[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $callback_data) {
                $callback = $callback_data['callback'];
                $num_args = $callback_data['accepted_args'];
                $value = call_user_func_array($callback, array_merge([$value], array_slice($args, 0, $num_args - 1)));
            }
        }
        
        return $value;
    }
}

if (!function_exists('add_filter')) {
    /**
     * Register a filter callback
     */
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $wp_filters;
        if (!isset($wp_filters)) {
            $wp_filters = [];
        }
        if (!isset($wp_filters[$hook])) {
            $wp_filters[$hook] = [];
        }
        if (!isset($wp_filters[$hook][$priority])) {
            $wp_filters[$hook][$priority] = [];
        }
        $wp_filters[$hook][$priority][] = [
            'callback' => $callback,
            'accepted_args' => $accepted_args
        ];
        return true;
    }
}

if (!function_exists('shortcode_atts')) {
    /**
     * Merge shortcode attributes.
     */
    function shortcode_atts($pairs, $atts, $shortcode = '') {
        return array_merge($pairs, is_array($atts) ? $atts : []);
    }
}

if (!function_exists('__')) {
    /**
     * Translation stub.
     */
    function __($text, $domain = null) {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    /**
     * Escape HTML for output.
     */
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html_e')) {
    /**
     * Escape and echo translation.
     */
    function esc_html_e($text, $domain = null) {
        echo esc_html($text);
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
        return true;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
        return true;
    }
}

if (!function_exists('wp_mail')) {
    /**
     * Send mail
     */
    function wp_mail($to, $subject = '', $message = '', $headers = '', $attachments = []) {
        // Mock: just return true (success)
        return true;
    }
}

if (!function_exists('current_user_can')) {
    /**
     * Check user capabilities
     */
    function current_user_can($capability) {
        // Mock: assume all capabilities during testing
        return true;
    }
}

if (!function_exists('error_log')) {
    /**
     * Log errors
     */
    function error_log($message, $message_type = 0, $destination = null, $extra_headers = null) {
        global $test_error_log;
        if (!isset($test_error_log)) {
            $test_error_log = [];
        }
        $test_error_log[] = $message;
        // Also print for test debugging
        echo "[ERROR LOG] $message\n";
    }
}

if (!function_exists('get_test_error_log')) {
    /**
     * Get all logged errors (for testing error logging)
     */
    function get_test_error_log() {
        global $test_error_log;
        return $test_error_log ?? [];
    }
}

if (!function_exists('clear_test_error_log')) {
    /**
     * Clear error log
     */
    function clear_test_error_log() {
        global $test_error_log;
        $test_error_log = [];
    }
}
