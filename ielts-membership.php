<?php
/**
 * Plugin Name: Impact Websites Student Management
 * Description: Partner-admin invite system for LearnDash. Shared partner dashboard (global pool) so multiple partner admins see the same codes and users. Single-use invite codes, auto-enrol in ALL LearnDash courses, site-wide login enforcement with public registration.
 * Version: 0.7.1
 * Author: Impact Websites
 * License: GPLv2 or later
 *
 * Change in 0.7.1:
 * - Partner dashboard is now GLOBAL: all partner admins see the same invites and managed users (shared company pool).
 * - Revoke action now allows any partner admin (with capability) to revoke any managed student (as long as the student is managed by a partner).
 *
 * Install/Upgrade:
 * - Replace the existing plugin file at:
 *     wp-content/plugins/impact-websites-student-management/impact-websites-student-management.php
 *   then (re)activate the plugin.
 *
 * Notes:
 * - The registration page must be configured in Partnership area -> Settings -> Registration page URL and must be publicly accessible.
 * - The Login page URL must be configured too.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Prevent loading if the subdirectory plugin is already active
// This avoids duplicate functionality when both are detected by WordPress
if (class_exists('Impact_Websites_Student_Management')) {
    return;
}

// Check if the plugin directory exists
$iw_plugin_subdir = __DIR__ . '/ielts-membership-plugin';
$iw_plugin_file = $iw_plugin_subdir . '/ielts-membership.php';

if (file_exists($iw_plugin_file)) {
    // Load the actual plugin from the subdirectory
    require_once $iw_plugin_file;
} else {
    // If subdirectory doesn't exist, show an error in admin
    add_action('admin_notices', 'iw_wrapper_missing_directory_notice');
}

/**
 * Display admin notice when plugin subdirectory is missing
 */
function iw_wrapper_missing_directory_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="notice notice-error">
        <p><strong>Impact Websites Student Management:</strong> Plugin directory structure is incomplete. Please ensure the 'ielts-membership-plugin' directory exists within the plugin folder.</p>
    </div>
    <?php
}
