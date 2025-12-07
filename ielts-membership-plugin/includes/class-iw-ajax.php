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
            // Store token in user session
            if (isset($result['data']['token'])) {
                setcookie('iw_token', $result['data']['token'], time() + (7 * 24 * 60 * 60), '/');
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
            // Store token in cookie
            if (isset($result['data']['token'])) {
                setcookie('iw_token', $result['data']['token'], time() + (7 * 24 * 60 * 60), '/');
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
        
        $transaction_id = 'wp_' . uniqid();
        
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
        
        setcookie('iw_token', '', time() - 3600, '/');
        
        wp_send_json_success(array(
            'message' => 'Logged out successfully',
            'redirect' => home_url('/login/')
        ));
    }
}
