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

// Check if the plugin directory exists
$plugin_dir = dirname(__FILE__) . '/ielts-membership-plugin';

if (file_exists($plugin_dir . '/ielts-membership.php')) {
    // Load the actual plugin from the subdirectory
    require_once $plugin_dir . '/ielts-membership.php';
} else {
    // If subdirectory doesn't exist, show an error in admin
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><strong>IELTS Membership System:</strong> Plugin directory structure is incomplete. Please ensure the 'ielts-membership-plugin' directory exists.</p>
        </div>
        <?php
    });
}
