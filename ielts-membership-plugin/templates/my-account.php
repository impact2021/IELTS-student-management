<?php
/**
 * My Account / Membership Expiry Template
 */
if (!defined('ABSPATH')) exit;
?>

<div class="iw-my-account-wrapper">
    <div class="iw-account-container">
        <h2>My Account</h2>
        
        <div id="iw-account-content">
            <div class="iw-loading">Loading your account information...</div>
        </div>
        
        <div class="iw-account-actions">
            <button id="iw-logout-btn" class="iw-btn iw-btn-secondary">Logout</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load account data
    function loadAccountData() {
        $.ajax({
            url: iwMembership.ajaxUrl,
            type: 'POST',
            data: {
                action: 'iw_get_profile',
                nonce: iwMembership.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayAccountData(response.data);
                } else {
                    $('#iw-account-content').html('<div class="iw-error">Error loading account data</div>');
                }
            }
        });
    }
    
    function displayAccountData(data) {
        var html = '<div class="iw-account-info">';
        html += '<h3>Personal Information</h3>';
        html += '<table class="iw-account-table">';
        html += '<tbody>';
        html += '<tr><th>Name:</th><td>' + data.user.first_name + ' ' + data.user.last_name + '</td></tr>';
        html += '<tr><th>Email:</th><td>' + data.user.email + '</td></tr>';
        html += '<tr><th>Member Since:</th><td>' + new Date(data.user.created_at).toLocaleDateString() + '</td></tr>';
        html += '</tbody>';
        html += '</table>';
        html += '</div>';
        
        if (data.membership) {
            html += '<div class="iw-membership-info">';
            html += '<h3>Current Membership</h3>';
            html += '<table class="iw-account-table">';
            html += '<tbody>';
            html += '<tr><th>Plan:</th><td>' + data.membership.plan_name + '</td></tr>';
            html += '<tr><th>Status:</th><td><span class="iw-status-' + data.membership.status + '">' + data.membership.status.toUpperCase() + '</span></td></tr>';
            html += '<tr><th>Start Date:</th><td>' + new Date(data.membership.start_date).toLocaleDateString() + '</td></tr>';
            html += '<tr><th>Expiry Date:</th><td>' + new Date(data.membership.end_date).toLocaleDateString() + '</td></tr>';
            html += '<tr><th>Payment Status:</th><td>' + data.membership.payment_status + '</td></tr>';
            html += '</tbody>';
            html += '</table>';
            html += '</div>';
            
            // Check if membership is expiring soon
            var expiryDate = new Date(data.membership.end_date);
            var today = new Date();
            var daysUntilExpiry = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
            
            if (daysUntilExpiry > 0 && daysUntilExpiry <= 7) {
                html += '<div class="iw-notice iw-notice-warning">';
                html += '<p>Your membership expires in ' + daysUntilExpiry + ' day(s)!</p>';
                html += '</div>';
            } else if (daysUntilExpiry <= 0) {
                html += '<div class="iw-notice iw-notice-error">';
                html += '<p>Your membership has expired. Please renew to continue accessing services.</p>';
                html += '</div>';
            }
        } else {
            html += '<div class="iw-no-membership">';
            html += '<p>You don\'t have an active membership.</p>';
            html += '<a href="' + iwMembership.plansUrl + '" class="iw-btn iw-btn-primary">View Plans</a>';
            html += '</div>';
        }
        
        // Add password change section
        html += '<div class="iw-password-change">';
        html += '<h3>Change Password</h3>';
        html += '<form id="iw-change-password-form">';
        html += '<div class="iw-form-group">';
        html += '<label for="current_password">Current Password</label>';
        html += '<input type="password" id="current_password" name="current_password" required />';
        html += '</div>';
        html += '<div class="iw-form-group">';
        html += '<label for="new_password">New Password</label>';
        html += '<input type="password" id="new_password" name="new_password" required />';
        html += '</div>';
        html += '<div class="iw-form-group">';
        html += '<label for="confirm_password">Confirm New Password</label>';
        html += '<input type="password" id="confirm_password" name="confirm_password" required />';
        html += '</div>';
        html += '<div id="password-change-message"></div>';
        html += '<button type="submit" class="iw-btn iw-btn-primary">Change Password</button>';
        html += '</form>';
        html += '</div>';
        
        $('#iw-account-content').html(html);
        
        // Bind password change form handler
        $('#iw-change-password-form').on('submit', handlePasswordChange);
    }
    
    function handlePasswordChange(e) {
        e.preventDefault();
        
        var currentPassword = $('#current_password').val();
        var newPassword = $('#new_password').val();
        var confirmPassword = $('#confirm_password').val();
        var messageDiv = $('#password-change-message');
        
        // Validate passwords
        if (newPassword !== confirmPassword) {
            messageDiv.html('<div class="iw-error">New passwords do not match</div>');
            return;
        }
        
        if (newPassword.length < 6) {
            messageDiv.html('<div class="iw-error">New password must be at least 6 characters</div>');
            return;
        }
        
        messageDiv.html('<div class="iw-loading">Changing password...</div>');
        
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
                    messageDiv.html('<div class="iw-success">Password changed successfully!</div>');
                    $('#iw-change-password-form')[0].reset();
                } else {
                    messageDiv.html('<div class="iw-error">' + response.data.message + '</div>');
                }
            },
            error: function() {
                messageDiv.html('<div class="iw-error">An error occurred. Please try again.</div>');
            }
        });
    }
    
    // Logout handler
    $('#iw-logout-btn').on('click', function(e) {
        e.preventDefault();
        
        if (confirm('Are you sure you want to logout?')) {
            $.ajax({
                url: iwMembership.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'iw_logout',
                    nonce: iwMembership.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect;
                    }
                }
            });
        }
    });
    
    // Load data on page load
    loadAccountData();
});
</script>
