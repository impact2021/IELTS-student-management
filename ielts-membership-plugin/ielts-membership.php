<?php
/**
 * Plugin Name: IELTS Membership System
 * Plugin URI: https://github.com/impact2021/IELTS-student-management
 * Description: A comprehensive membership management system for IELTS student management, replacing Amember
 * Version: 1.0.0
 * Author: IELTS Management Team
 * Author URI: https://github.com/impact2021
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ielts-membership
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IW_PLUGIN_VERSION', '1.0.0');
define('IW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IW_PLUGIN_FILE', __FILE__);

// Include required files
require_once IW_PLUGIN_DIR . 'includes/class-iw-activator.php';
require_once IW_PLUGIN_DIR . 'includes/class-iw-shortcodes.php';
require_once IW_PLUGIN_DIR . 'includes/class-iw-ajax.php';
require_once IW_PLUGIN_DIR . 'includes/class-iw-api-client.php';

/**
 * Plugin activation hook
 */
function iw_activate_plugin() {
    IW_Activator::activate();
}
register_activation_hook(__FILE__, 'iw_activate_plugin');

/**
 * Plugin deactivation hook
 */
function iw_deactivate_plugin() {
    // Clean up if needed
}
register_deactivation_hook(__FILE__, 'iw_deactivate_plugin');

/**
 * Initialize the plugin
 */
function iw_init_plugin() {
    // Initialize shortcodes
    $shortcodes = new IW_Shortcodes();
    $shortcodes->register();
    
    // Initialize AJAX handlers
    $ajax = new IW_AJAX();
    $ajax->register();
    
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', 'iw_enqueue_assets');
}
add_action('plugins_loaded', 'iw_init_plugin');

/**
 * Enqueue plugin assets
 */
function iw_enqueue_assets() {
    // Enqueue CSS
    wp_enqueue_style(
        'iw-membership-styles',
        IW_PLUGIN_URL . 'assets/css/membership-styles.css',
        array(),
        IW_PLUGIN_VERSION
    );
    
    // Enqueue JS
    wp_enqueue_script(
        'iw-membership-script',
        IW_PLUGIN_URL . 'assets/js/membership-script.js',
        array('jquery'),
        IW_PLUGIN_VERSION,
        true
    );
    
    // Localize script with AJAX URL and nonce
    wp_localize_script('iw-membership-script', 'iwMembership', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('iw_membership_nonce'),
        'apiUrl' => get_option('iw_api_url', 'http://localhost:3000/api'),
        'myAccountUrl' => home_url('/my-account/'),
        'loginUrl' => home_url('/login/')
    ));
}

/**
 * Add settings page to admin menu
 */
function iw_add_admin_menu() {
    add_options_page(
        'IELTS Membership Settings',
        'IELTS Membership',
        'manage_options',
        'ielts-membership',
        'iw_render_settings_page'
    );
}
add_action('admin_menu', 'iw_add_admin_menu');

/**
 * Render settings page
 */
function iw_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Save settings if form submitted
    if (isset($_POST['iw_save_settings']) && check_admin_referer('iw_settings_nonce')) {
        update_option('iw_api_url', sanitize_text_field($_POST['iw_api_url']));
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }
    
    $api_url = get_option('iw_api_url', 'http://localhost:3000/api');
    ?>
    <div class="wrap">
        <h1>IELTS Membership Settings</h1>
        <form method="post" action="">
            <?php wp_nonce_field('iw_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="iw_api_url">API URL</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="iw_api_url" 
                               name="iw_api_url" 
                               value="<?php echo esc_attr($api_url); ?>" 
                               class="regular-text"
                               placeholder="http://localhost:3000/api">
                        <p class="description">Enter the base URL for the IELTS Membership API</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" 
                       name="iw_save_settings" 
                       class="button button-primary" 
                       value="Save Settings">
            </p>
        </form>
    </div>
    <?php
}
