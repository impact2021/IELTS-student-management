<?php
/**
 * API Client Class
 * Handles communication with the Node.js REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

class IW_API_Client {
    
    private $api_url;
    
    public function __construct() {
        $this->api_url = get_option('iw_api_url', 'http://localhost:3000/api');
    }
    
    /**
     * Register a new user
     */
    public function register($email, $password, $first_name, $last_name) {
        return $this->post('/auth/register', array(
            'email' => $email,
            'password' => $password,
            'firstName' => $first_name,
            'lastName' => $last_name
        ));
    }
    
    /**
     * Login user
     */
    public function login($email, $password) {
        return $this->post('/auth/login', array(
            'email' => $email,
            'password' => $password
        ));
    }
    
    /**
     * Get user profile
     */
    public function get_profile($token) {
        return $this->get('/auth/profile', array(), $token);
    }
    
    /**
     * Get all membership plans
     */
    public function get_plans($token = '') {
        return $this->get('/membership/plans', array(), $token);
    }
    
    /**
     * Subscribe to a plan
     */
    public function subscribe($plan_id, $payment_method, $transaction_id, $token) {
        return $this->post('/membership/subscribe', array(
            'planId' => $plan_id,
            'paymentMethod' => $payment_method,
            'transactionId' => $transaction_id
        ), $token);
    }
    
    /**
     * Get active membership
     */
    public function get_active_membership($token) {
        return $this->get('/membership/my-membership', array(), $token);
    }
    
    /**
     * Get all user memberships
     */
    public function get_user_memberships($token) {
        return $this->get('/membership/my-memberships', array(), $token);
    }
    
    /**
     * Get payment history
     */
    public function get_payments($token) {
        return $this->get('/membership/my-payments', array(), $token);
    }
    
    /**
     * Make GET request
     */
    private function get($endpoint, $params = array(), $token = '') {
        $url = $this->api_url . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $args = array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        );
        
        if ($token) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }
        
        $response = wp_remote_get($url, $args);
        
        return $this->process_response($response);
    }
    
    /**
     * Make POST request
     */
    private function post($endpoint, $data, $token = '') {
        $url = $this->api_url . $endpoint;
        
        $args = array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data)
        );
        
        if ($token) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }
        
        $response = wp_remote_post($url, $args);
        
        return $this->process_response($response);
    }
    
    /**
     * Process API response
     */
    private function process_response($response) {
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'data' => $data
            );
        } else {
            return array(
                'success' => false,
                'error' => isset($data['error']) ? $data['error'] : 'Unknown error occurred',
                'status_code' => $status_code
            );
        }
    }
}
