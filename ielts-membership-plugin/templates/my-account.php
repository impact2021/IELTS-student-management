<?php
/**
 * My Account / Membership Expiry Template
 */
if (!defined('ABSPATH')) exit;

// Get current user data
$current_user = wp_get_current_user();
$user_id = get_current_user_id();
$expiry_ts = intval(get_user_meta($user_id, '_iw_user_expiry', true));
$now = time();
$is_expired = ($expiry_ts && $expiry_ts <= $now);
?>

<style>
.iw-table { width:100%; max-width:920px; border-collapse:collapse; margin-bottom:1em; font-family: Arial, sans-serif; }
.iw-table thead th { background:#f8f9fa; color:#333333; padding:14px; text-align:left; font-size:16px; border:1px solid #e6e6e6; }
.iw-table td, .iw-table th { padding:12px; border:1px solid #e6e6e6; vertical-align:middle; }
.iw-table td:nth-child(even) { background:#f7f7f7; }
.iw-table td:first-child, .iw-table th:first-child { background:#f8f9fa; color:#333333; font-weight:600; width:35%; }
.iw-input { width:100%; max-width:520px; padding:10px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; height:40px; }
.iw-submit { background:#0073aa; color:#fff; border:none; padding:10px 16px; border-radius:4px; cursor:pointer; }
.iw-message { padding:12px; margin:10px 0; border-radius:4px; }
.iw-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.iw-error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
.iw-expiry-notice { padding:15px; margin:20px 0; border-radius:4px; background:#fff3cd; color:#856404; border:1px solid #ffeeba; }
.iw-expired { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
</style>

<div class="iw-my-account-wrapper">
    <h2>My Account</h2>
    
    <!-- Personal Information Table -->
    <form id="iw-profile-update-form">
        <table class="iw-table" role="presentation">
            <thead>
                <tr><th colspan="3">Personal Information</th></tr>
            </thead>
            <tbody>
                <?php if ($expiry_ts): ?>
                <tr>
                    <th>Membership Expiry</th>
                    <td colspan="2">
                        <?php 
                        echo esc_html(date_i18n('d/m/Y', $expiry_ts));
                        if (!$is_expired) {
                            $days_left = ceil(($expiry_ts - $now) / DAY_IN_SECONDS);
                            echo ' (' . intval($days_left) . ' day' . ($days_left != 1 ? 's' : '') . ' remaining)';
                        } else {
                            echo ' <span style="color:red;font-weight:bold;">(EXPIRED)</span>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Name</th>
                    <td style="width:32.5%;">
                        <input type="text" name="first_name" value="<?php echo esc_attr($current_user->first_name); ?>" class="iw-input" placeholder="First Name" required />
                    </td>
                    <td style="width:32.5%;">
                        <input type="text" name="last_name" value="<?php echo esc_attr($current_user->last_name); ?>" class="iw-input" placeholder="Last Name" required />
                    </td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td colspan="2">
                        <input type="email" name="user_email" value="<?php echo esc_attr($current_user->user_email); ?>" class="iw-input" required />
                    </td>
                </tr>
                <tr>
                    <th>Username</th>
                    <td colspan="2"><?php echo esc_html($current_user->user_login); ?> <em>(cannot be changed)</em></td>
                </tr>
            </tbody>
        </table>
        <div id="profile-update-message"></div>
        <button type="submit" class="iw-submit">Update</button>
    </form>
    
    <!-- Change Password Table -->
    <table class="iw-table" role="presentation">
        <thead>
            <tr><th colspan="2">Change Password</th></tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="2">
                    <form id="iw-change-password-form">
                        <div style="margin-bottom:15px;">
                            <label style="display:block;margin-bottom:5px;font-weight:600;">Current Password</label>
                            <input type="password" name="current_password" class="iw-input" required />
                        </div>
                        <div style="margin-bottom:15px;">
                            <label style="display:block;margin-bottom:5px;font-weight:600;">New Password</label>
                            <input type="password" name="new_password" class="iw-input" required />
                        </div>
                        <div style="margin-bottom:15px;">
                            <label style="display:block;margin-bottom:5px;font-weight:600;">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="iw-input" required />
                        </div>
                        <div id="password-change-message"></div>
                        <button type="submit" class="iw-submit">Change Password</button>
                    </form>
                </td>
            </tr>
        </tbody>
    </table>
    
    <?php if ($is_expired): ?>
    <div class="iw-expiry-notice iw-expired">
        <p><strong>Your membership has expired.</strong> Please contact your partner admin or extend your membership to regain access.</p>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle profile update form
    $('#iw-profile-update-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var messageDiv = $('#profile-update-message');
        
        var firstName = form.find('input[name="first_name"]').val();
        var lastName = form.find('input[name="last_name"]').val();
        var email = form.find('input[name="user_email"]').val();
        
        messageDiv.html('<div class="iw-message" style="background:#f0f0f0; color:#666; padding:12px; margin:10px 0; border-radius:4px;">Updating...</div>');
        
        $.ajax({
            url: iwMembership.ajaxUrl,
            type: 'POST',
            data: {
                action: 'iw_update_profile_bulk',
                nonce: iwMembership.nonce,
                first_name: firstName,
                last_name: lastName,
                user_email: email
            },
            success: function(response) {
                if (response.success) {
                    messageDiv.html('<div class="iw-message iw-success">Profile updated successfully!</div>');
                    setTimeout(function() { messageDiv.html(''); }, 3000);
                } else {
                    messageDiv.html('<div class="iw-message iw-error">' + (response.data.message || 'Update failed') + '</div>');
                }
            },
            error: function() {
                messageDiv.html('<div class="iw-message iw-error">Error: Could not update profile</div>');
            }
        });
    });
    
    // Handle password change
    $('#iw-change-password-form').on('submit', function(e) {
        e.preventDefault();
        
        var currentPassword = $('input[name="current_password"]').val();
        var newPassword = $('input[name="new_password"]').val();
        var confirmPassword = $('input[name="confirm_password"]').val();
        var messageDiv = $('#password-change-message');
        
        // Validate passwords
        if (newPassword !== confirmPassword) {
            messageDiv.html('<div class="iw-message iw-error">New passwords do not match</div>');
            return;
        }
        
        if (newPassword.length < 8) {
            messageDiv.html('<div class="iw-message iw-error">New password must be at least 8 characters</div>');
            return;
        }
        
        messageDiv.html('<span style="color:#666;">Changing password...</span>');
        
        $.ajax({
            url: iwMembership.ajaxUrl,
            type: 'POST',
            data: {
                action: 'iw_change_password',
                nonce: iwMembership.nonce,
                current_password: currentPassword,
                new_password: newPassword
            },
            success: function(response) {
                if (response.success) {
                    messageDiv.html('<div class="iw-message iw-success">Password changed successfully!</div>');
                    $('#iw-change-password-form')[0].reset();
                } else {
                    messageDiv.html('<div class="iw-message iw-error">' + (response.data.message || 'Password change failed') + '</div>');
                }
            },
            error: function() {
                messageDiv.html('<div class="iw-message iw-error">An error occurred. Please try again.</div>');
            }
        });
    });
});
</script>
