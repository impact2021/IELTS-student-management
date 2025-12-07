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
        html += '<p><strong>Name:</strong> ' + data.user.first_name + ' ' + data.user.last_name + '</p>';
        html += '<p><strong>Email:</strong> ' + data.user.email + '</p>';
        html += '<p><strong>Member Since:</strong> ' + new Date(data.user.created_at).toLocaleDateString() + '</p>';
        html += '</div>';
        
        if (data.membership) {
            html += '<div class="iw-membership-info">';
            html += '<h3>Current Membership</h3>';
            html += '<p><strong>Plan:</strong> ' + data.membership.plan_name + '</p>';
            html += '<p><strong>Status:</strong> <span class="iw-status-' + data.membership.status + '">' + data.membership.status.toUpperCase() + '</span></p>';
            html += '<p><strong>Start Date:</strong> ' + new Date(data.membership.start_date).toLocaleDateString() + '</p>';
            html += '<p><strong>Expiry Date:</strong> ' + new Date(data.membership.end_date).toLocaleDateString() + '</p>';
            html += '<p><strong>Payment Status:</strong> ' + data.membership.payment_status + '</p>';
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
        
        $('#iw-account-content').html(html);
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
