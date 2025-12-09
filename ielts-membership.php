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
 * Domain Path: /ielts-membership-plugin/languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Prevent loading if the subdirectory plugin is already active
// This avoids duplicate functionality when both are detected by WordPress
if (defined('IW_PLUGIN_VERSION')) {
    return;
}

// Check if the plugin directory exists
$iw_plugin_subdir = dirname(__FILE__) . '/ielts-membership-plugin';
$iw_plugin_file = $iw_plugin_subdir . '/ielts-membership.php';

if (file_exists($iw_plugin_file)) {
    // Load the actual plugin from the subdirectory
    require_once $iw_plugin_file;
} else {
    // If subdirectory doesn't exist, show an error in admin
    add_action('admin_notices', function() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="notice notice-error">
            <p><strong>IELTS Membership System:</strong> Plugin directory structure is incomplete. Please ensure the 'ielts-membership-plugin' directory exists within the plugin folder.</p>
        </div>
        <?php
    });
}
