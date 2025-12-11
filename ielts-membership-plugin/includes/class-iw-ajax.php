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
        add_action('wp_ajax_iw_update_profile', array($this, 'handle_update_profile'));
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
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }
        
        $user_id = get_current_user_id();
        $current_password = isset($_POST['current_password']) ? wp_unslash($_POST['current_password']) : '';
        $new_password = isset($_POST['new_password']) ? wp_unslash($_POST['new_password']) : '';
        
        if (empty($current_password) || empty($new_password)) {
            wp_send_json_error(array('message' => 'Current and new password are required'));
        }
        
        if (strlen($new_password) < 8) {
            wp_send_json_error(array('message' => 'New password must be at least 8 characters'));
        }
        
        // Verify current password
        $user = get_user_by('id', $user_id);
        if (!$user || !wp_check_password($current_password, $user->user_pass, $user_id)) {
            wp_send_json_error(array('message' => 'Current password is incorrect'));
        }
        
        // Update password
        wp_set_password($new_password, $user_id);
        
        wp_send_json_success(array('message' => 'Password changed successfully'));
    }
    
    /**
     * Handle profile update
     */
    public function handle_update_profile() {
        check_ajax_referer('iw_membership_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }
        
        $user_id = get_current_user_id();
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';
        
        if (empty($field) || empty($value)) {
            wp_send_json_error(array('message' => 'Field and value are required'));
        }
        
        // Allowed fields to update
        $allowed_fields = array('first_name', 'last_name', 'user_email');
        
        if (!in_array($field, $allowed_fields)) {
            wp_send_json_error(array('message' => 'Invalid field'));
        }
        
        // For email, check if it's already in use
        if ($field === 'user_email') {
            $value = sanitize_email($value);
            if (!is_email($value)) {
                wp_send_json_error(array('message' => 'Invalid email address'));
            }
            
            $email_exists = email_exists($value);
            if ($email_exists && $email_exists != $user_id) {
                wp_send_json_error(array('message' => 'Email already in use'));
            }
            
            // Update user email
            $result = wp_update_user(array(
                'ID' => $user_id,
                'user_email' => $value
            ));
        } else {
            // Update user meta for first_name and last_name
            $result = update_user_meta($user_id, $field, $value);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => 'Profile updated successfully'));
    }
    
    /**
     * Handle extend membership with code
     */
    public function handle_extend_membership() {
        check_ajax_referer('iw_membership_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated. Please log in first.'));
        }
        
        $user_id = get_current_user_id();
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        
        if (empty($code)) {
            wp_send_json_error(array('message' => 'Extension code is required'));
        }
        
        // Find invite post by code
        $posts = get_posts(array(
            'post_type'      => 'iw_invite',
            'meta_key'       => '_iw_invite_code',
            'meta_value'     => $code,
            'posts_per_page' => 1,
        ));
        
        if (empty($posts)) {
            wp_send_json_error(array('message' => 'Invalid extension code'));
        }
        
        $invite = $posts[0];
        $used = get_post_meta($invite->ID, '_iw_invite_used', true);
        
        if ($used) {
            wp_send_json_error(array('message' => 'This extension code has already been used'));
        }
        
        // Get default days from settings
        $options = get_option('iw_student_management_options', array());
        $default_days = isset($options['default_days']) ? intval($options['default_days']) : 30;
        
        // Calculate new expiry
        $new_expiry = time() + ($default_days * DAY_IN_SECONDS);
        
        // Update user
        $user = new WP_User($user_id);
        $user->set_role('subscriber');
        
        // Update user meta
        $manager_id = intval(get_post_meta($invite->ID, '_iw_invite_manager', true));
        update_user_meta($user_id, '_iw_user_manager', $manager_id ?: 0);
        update_user_meta($user_id, '_iw_user_expiry', $new_expiry);
        delete_user_meta($user_id, '_iw_expiry_notice_sent');
        
        // Mark invite as used
        update_post_meta($invite->ID, '_iw_invite_used', 1);
        update_post_meta($invite->ID, '_iw_invite_used_by', $user_id);
        update_post_meta($invite->ID, '_iw_invite_used_at', time());
        
        // Enroll in all LearnDash courses
        $this->enroll_user_in_all_courses($user_id);
        
        // Notify partner admin
        if ($manager_id) {
            $this->notify_partner_membership_extended($manager_id, $user_id, $code, $new_expiry);
        }
        
        wp_send_json_success(array(
            'message' => 'Membership extended successfully!',
            'expiry' => date_i18n('d/m/Y', $new_expiry)
        ));
    }
    
    /**
     * Enroll user in all LearnDash courses
     */
    private function enroll_user_in_all_courses($user_id) {
        $posts = get_posts(array('post_type' => 'sfwd-courses', 'posts_per_page' => -1));
        if (empty($posts)) {
            return;
        }
        foreach ($posts as $p) {
            $course_id = $p->ID;
            if (function_exists('ld_update_course_access')) {
                ld_update_course_access($user_id, $course_id);
            } elseif (function_exists('learndash_enroll_user')) {
                learndash_enroll_user($user_id, $course_id);
            } else {
                $key = 'course_' . $course_id . '_access';
                update_user_meta($user_id, $key, time());
            }
        }
    }
    
    /**
     * Notify partner when membership is extended
     */
    private function notify_partner_membership_extended($partner_id, $user_id, $code, $new_expiry) {
        $partner = get_userdata($partner_id);
        $user = get_userdata($user_id);
        if (!$partner || !$user) {
            return;
        }
        $to = $partner->user_email;
        if (empty($to)) {
            return;
        }
        $subject = sprintf('Membership extended: %s', $user->user_login);
        $expiry_text = date_i18n('d/m/Y', $new_expiry);
        $message = "Hello " . $partner->display_name . ",\n\n";
        $message .= sprintf("User %s (%s) has extended their membership using code %s.\n\n", $user->user_login, $user->user_email, $code);
        $message .= "New expiry date: " . $expiry_text . "\n\n";
        $message .= "Regards,\nImpact Websites";
        wp_mail($to, $subject, $message);
    }
}
