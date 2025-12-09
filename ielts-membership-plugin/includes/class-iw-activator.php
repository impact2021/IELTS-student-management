<?php
/**
 * Plugin Activator Class
 * Creates required pages with shortcodes when plugin is activated
 */

if (!defined('ABSPATH')) {
    exit;
}

class IW_Activator {
    
    /**
     * Run activation tasks
     */
    public static function activate() {
        self::create_pages();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set default options
        if (!get_option('iw_api_url')) {
            update_option('iw_api_url', 'http://localhost:3000/api');
        }
    }
    
    /**
     * Create required pages with shortcodes
     */
    private static function create_pages() {
        $pages = array(
            array(
                'slug' => 'partner-dashboard',
                'title' => 'Partner Dashboard',
                'shortcode' => '[iw_partner_dashboard]',
                'content' => 'Welcome to your partner dashboard.'
            ),
            array(
                'slug' => 'login',
                'title' => 'Login',
                'shortcode' => '[iw_login]',
                'content' => 'Please log in to access your account.'
            ),
            array(
                'slug' => 'my-account',
                'title' => 'My Account',
                'shortcode' => '[iw_my_expiry]',
                'content' => 'View your membership details and expiration date.'
            ),
            array(
                'slug' => 'register',
                'title' => 'Register',
                'shortcode' => '[iw_register_with_code]',
                'content' => 'Create your account to get started.'
            )
        );
        
        foreach ($pages as $page_data) {
            self::create_page_if_not_exists($page_data);
        }
    }
    
    /**
     * Create a single page if it doesn't exist
     * 
     * @param array $page_data Page configuration
     */
    private static function create_page_if_not_exists($page_data) {
        // Check if page already exists
        $page = get_page_by_path($page_data['slug']);
        
        if ($page) {
            // Page exists, check if it has the shortcode
            $shortcode_tag = str_replace(array('[', ']'), '', $page_data['shortcode']);
            if (!has_shortcode($page->post_content, $shortcode_tag)) {
                // Update page content to include shortcode
                wp_update_post(array(
                    'ID' => $page->ID,
                    'post_content' => $page_data['content'] . "\n\n" . $page_data['shortcode']
                ));
            }
            return;
        }
        
        // Create the page
        $page_id = wp_insert_post(array(
            'post_title' => $page_data['title'],
            'post_name' => $page_data['slug'],
            'post_content' => $page_data['content'] . "\n\n" . $page_data['shortcode'],
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ));
        
        if ($page_id && !is_wp_error($page_id)) {
            // Store page ID in options for future reference
            update_option('iw_page_' . str_replace('-', '_', $page_data['slug']), $page_id);
        }
    }
}
