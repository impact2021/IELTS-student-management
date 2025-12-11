<?php
/**
 * AJAX Handlers Class
 * Handles all AJAX requests from the frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class IW_AJAX {
    
    /**
     * Register AJAX actions
     */
    public function register() {
        // Public actions
        add_action('wp_ajax_nopriv_iw_register', array($this, 'handle_register'));
        add_action('wp_ajax_nopriv_iw_login', array($this, 'handle_login'));
        
        // Authenticated actions
        add_action('wp_ajax_iw_get_profile', array($this, 'handle_get_profile'));
        add_action('wp_ajax_iw_get_membership', array($this, 'handle_get_membership'));
        add_action('wp_ajax_iw_subscribe', array($this, 'handle_subscribe'));
        add_action('wp_ajax_iw_logout', array($this, 'handle_logout'));
        add_action('wp_ajax_iw_change_password', array($this, 'handle_change_password'));
        add_action('wp_ajax_iw_extend_membership', array($this, 'handle_extend_membership'));
    }
    
    /**
     * Handle registration
     */
    public function handle_register() {
        check_ajax_referer('iw_membership_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        
        if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
            wp_send_json_error(array('message' => 'All fields are required'));
        }
        
        $api = new IW_API_Client();
        $result = $api->register($email, $password, $first_name, $last_name);
        
        if ($result['success']) {
            // Store token in secure cookie
            if (isset($result['data']['token'])) {
                $secure = is_ssl();
                setcookie('iw_token', $result['data']['token'], time() + (7 * 24 * 60 * 60), '/', '', $secure, true);
            }
            
            wp_send_json_success(array(
                'message' => 'Registration successful!',
                'redirect' => home_url('/partner-dashboard/')
            ));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Handle login
     */
    public function handle_login() {
        check_ajax_referer('iw_membership_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            wp_send_json_error(array('message' => 'Email and password are required'));
        }
        
        $api = new IW_API_Client();
        $result = $api->login($email, $password);
        
        if ($result['success']) {
            // Store token in secure cookie
            if (isset($result['data']['token'])) {
                $secure = is_ssl();
                setcookie('iw_token', $result['data']['token'], time() + (7 * 24 * 60 * 60), '/', '', $secure, true);
            }
            
            wp_send_json_success(array(
                'message' => 'Login successful!',
                'redirect' => home_url('/partner-dashboard/')
            ));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Handle get profile
     */
    public function handle_get_profile() {
        check_ajax_referer('iw_membership_nonce', 'nonce');
        
        $token = isset($_COOKIE['iw_token']) ? $_COOKIE['iw_token'] : '';
        
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }
        
        $api = new IW_API_Client();
        $result = $api->get_profile($token);
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Handle get membership
     */
    public function handle_get_membership() {
        check_ajax_referer('iw_membership_nonce', 'nonce');
        
        $token = isset($_COOKIE['iw_token']) ? $_COOKIE['iw_token'] : '';
        
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }
        
        $api = new IW_API_Client();
        $result = $api->get_active_membership($token);
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Handle subscription
     */
    public function handle_subscribe() {
        check_ajax_referer('iw_membership_nonce', 'nonce');
        
        $token = isset($_COOKIE['iw_token']) ? $_COOKIE['iw_token'] : '';
        $plan_id = intval($_POST['plan_id']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }
        
        if (empty($plan_id)) {
            wp_send_json_error(array('message' => 'Plan ID is required'));
        }
        
        $transaction_id = 'wp_' . wp_generate_password(20, false);
        
        $api = new IW_API_Client();
        $result = $api->subscribe($plan_id, $payment_method, $transaction_id, $token);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Subscription successful!',
                'data' => $result['data']
            ));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Handle logout
     */
    public function handle_logout() {
        check_ajax_referer('iw_membership_nonce', 'nonce');
        
        $secure = is_ssl();
        setcookie('iw_token', '', time() - 3600, '/', '', $secure, true);
        
        wp_send_json_success(array(
            'message' => 'Logged out successfully',
            'redirect' => home_url('/login/')
        ));
    }
    
    /**
     * Handle password change
     */
    public function handle_change_password() {
        check_ajax_referer('iw_membership_nonce', 'nonce');
        
        $token = isset($_COOKIE['iw_token']) ? $_COOKIE['iw_token'] : '';
        $current_password = isset($_POST['current_password']) ? wp_unslash($_POST['current_password']) : '';
        $new_password = isset($_POST['new_password']) ? wp_unslash($_POST['new_password']) : '';
        
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }
        
        if (empty($current_password) || empty($new_password)) {
            wp_send_json_error(array('message' => 'Current and new password are required'));
        }
        
        if (strlen($new_password) < 6) {
            wp_send_json_error(array('message' => 'New password must be at least 6 characters'));
        }
        
        $api = new IW_API_Client();
        $result = $api->change_password($current_password, $new_password, $token);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'Password changed successfully'));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Handle extend membership with code
     */
    public function handle_extend_membership() {
        check_ajax_referer('iw_membership_nonce', 'nonce');
        
        $token = isset($_COOKIE['iw_token']) ? $_COOKIE['iw_token'] : '';
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }
        
        if (empty($code)) {
            wp_send_json_error(array('message' => 'Extension code is required'));
        }
        
        $api = new IW_API_Client();
        $result = $api->extend_membership($code, $token);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Membership extended successfully!',
                'data' => $result['data']
            ));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
}
