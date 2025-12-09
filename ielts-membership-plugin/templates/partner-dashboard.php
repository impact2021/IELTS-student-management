<?php
/**
 * Partner Dashboard Template
 */
if (!defined('ABSPATH')) exit;
?>

<div class="iw-dashboard-wrapper">
    <div class="iw-dashboard-container">
        <h2>Welcome to Your Dashboard</h2>
        
        <div id="iw-dashboard-content">
            <div class="iw-loading">Loading your dashboard...</div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function loadDashboard() {
        $.ajax({
            url: iwMembership.ajaxUrl,
            type: 'POST',
            data: {
                action: 'iw_get_profile',
                nonce: iwMembership.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayDashboard(response.data);
                } else {
                    $('#iw-dashboard-content').html('<div class="iw-error">Error loading dashboard</div>');
                }
            }
        });
    }
    
    function displayDashboard(data) {
        var html = '<div class="iw-dashboard-grid">';
        
        // Welcome section
        html += '<div class="iw-dashboard-card iw-welcome-card">';
        html += '<h3>Welcome back, ' + data.user.first_name + '!</h3>';
        html += '<p>Manage your IELTS learning journey from here.</p>';
        html += '</div>';
        
        // Membership status card
        html += '<div class="iw-dashboard-card iw-membership-card">';
        html += '<h3>Membership Status</h3>';
        if (data.membership) {
            html += '<div class="iw-membership-status">';
            html += '<p class="iw-plan-name">' + data.membership.plan_name + '</p>';
            html += '<p class="iw-status-badge iw-status-' + data.membership.status + '">' + data.membership.status.toUpperCase() + '</p>';
            html += '<p class="iw-expiry">Expires: ' + new Date(data.membership.end_date).toLocaleDateString() + '</p>';
            html += '<a href="' + iwMembership.myAccountUrl + '" class="iw-btn iw-btn-sm">View Details</a>';
            html += '</div>';
        } else {
            html += '<p>No active membership</p>';
            html += '<a href="#" class="iw-btn iw-btn-primary">Browse Plans</a>';
        }
        html += '</div>';
        
        // Quick actions card
        html += '<div class="iw-dashboard-card iw-actions-card">';
        html += '<h3>Quick Actions</h3>';
        html += '<ul class="iw-action-list">';
        html += '<li><a href="' + iwMembership.myAccountUrl + '">View My Account</a></li>';
        html += '<li><a href="#">Browse Materials</a></li>';
        html += '<li><a href="#">Practice Tests</a></li>';
        html += '<li><a href="#">Contact Support</a></li>';
        html += '</ul>';
        html += '</div>';
        
        html += '</div>';
        
        $('#iw-dashboard-content').html(html);
    }
    
    loadDashboard();
});
</script>
