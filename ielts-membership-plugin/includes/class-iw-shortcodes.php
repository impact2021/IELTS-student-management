<?php
/**
 * Shortcodes Class
 * Handles all plugin shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

class IW_Shortcodes {
    
    /**
     * Register all shortcodes
     */
    public function register() {
        add_shortcode('iw_partner_dashboard', array($this, 'partner_dashboard'));
        add_shortcode('iw_login', array($this, 'login_form'));
        add_shortcode('iw_my_expiry', array($this, 'my_account'));
        add_shortcode('iw_register_with_code', array($this, 'register_form'));
        add_shortcode('extend-membership', array($this, 'extend_membership'));
    }
    
    /**
     * Partner Dashboard Shortcode
     */
    public function partner_dashboard($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view your dashboard.</p>';
        }
        
        ob_start();
        include IW_PLUGIN_DIR . 'templates/partner-dashboard.php';
        return ob_get_clean();
    }
    
    /**
     * Login Form Shortcode
     */
    public function login_form($atts) {
        // Prevent caching of login page to avoid showing cached login form after successful login
        if ( ! headers_sent() ) {
            nocache_headers();
        }
        
        if (is_user_logged_in()) {
            // If user is already logged in, redirect them to the intended destination
            $redirect = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
            
            if (empty($redirect)) {
                $redirect = get_option('iw_page_partner_dashboard') 
                    ? get_permalink(get_option('iw_page_partner_dashboard')) 
                    : home_url();
            }
            
            // Validate redirect to prevent open redirect vulnerabilities
            $redirect = wp_validate_redirect($redirect, home_url());
            wp_safe_redirect($redirect);
            exit;
        }
        
        ob_start();
        include IW_PLUGIN_DIR . 'templates/login-form.php';
        return ob_get_clean();
    }
    
    /**
     * My Account / Expiry Shortcode
     */
    public function my_account($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view your account.</p>';
        }
        
        ob_start();
        include IW_PLUGIN_DIR . 'templates/my-account.php';
        return ob_get_clean();
    }
    
    /**
     * Register Form Shortcode
     */
    public function register_form($atts) {
        if (is_user_logged_in()) {
            return '<p>You are already registered. <a href="' . home_url('/my-account/') . '">Go to My Account</a></p>';
        }
        
        $atts = shortcode_atts(array(
            'code' => '',
            'plan_id' => ''
        ), $atts);
        
        ob_start();
        include IW_PLUGIN_DIR . 'templates/register-form.php';
        return ob_get_clean();
    }
    
    /**
     * Extend Membership Shortcode
     */
    public function extend_membership($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to extend your membership.</p>';
        }
        
        ob_start();
        include IW_PLUGIN_DIR . 'templates/extend-membership.php';
        return ob_get_clean();
    }
}
