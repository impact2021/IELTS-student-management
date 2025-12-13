<?php
/**
 * Extend Membership Template
 */
if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$user_id = get_current_user_id();

// Get user's expiry date
$expiry_ts = intval(get_user_meta($user_id, '_iw_user_expiry', true));
$now = time();
$days_remaining = 0;
$is_expired = true;

if ($expiry_ts > 0) {
    $days_remaining = max(0, ceil(($expiry_ts - $now) / DAY_IN_SECONDS));
    $is_expired = ($expiry_ts <= $now);
}

// Determine the message to display
if ($is_expired) {
    $status_message = 'Your access has now expired. To add more time to your account, please enter an extension code below.';
} else {
    $status_message = 'You have ' . $days_remaining . ' day' . ($days_remaining != 1 ? 's' : '') . ' left. To add more time to your account, please enter an extension code below.';
}
?>

<style>
.iw-table { width:100%; max-width:720px; border-collapse:collapse; margin-bottom:1em; font-family: Arial, sans-serif; }
.iw-table thead th { background:#f8f9fa; color:#333333; padding:14px; text-align:left; font-size:18px; border:1px solid #e6e6e6; }
.iw-table td, .iw-table th { padding:12px; border:1px solid #e6e6e6; vertical-align:middle; }
.iw-table td:nth-child(even) { background:#f7f7f7; }
.iw-table td:first-child, .iw-table th:first-child { background:#f8f9fa; color:#333333; font-weight:600; width:35%; }
.iw-input { width:100%; max-width:480px; padding:10px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; height:40px; }
.iw-submit { background:#0073aa; color:#fff; border:none; padding:10px 16px; border-radius:4px; cursor:pointer; }
.iw-message { padding:12px; margin:10px 0; border-radius:4px; }
.iw-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.iw-error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
.iw-info { background:#d1ecf1; color:#0c5460; border:1px solid #bee5eb; }
</style>

<div class="iw-extend-membership-wrapper">
    <h2>Extend Your Membership</h2>
    
    <div class="iw-message iw-info">
        <p><strong>Welcome, <?php echo esc_html($current_user->display_name); ?>!</strong></p>
        <p><?php echo esc_html($status_message); ?></p>
        <p>If you don't have an extension code, please contact your partner admin to request one.</p>
    </div>
    
    <table class="iw-table" role="presentation">
        <thead>
            <tr><th colspan="2">Enter Extension Code</th></tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="2">
                    <form id="iw-extend-membership-form">
                        <div style="margin-bottom:15px;">
                            <label style="display:block;margin-bottom:5px;font-weight:600;">Extension Code</label>
                            <input type="text" name="extension_code" class="iw-input" required placeholder="Enter your extension code" />
                            <p style="color:#666;margin:6px 0 0;font-size:13px;">This is a single-use code provided by your partner admin.</p>
                        </div>
                        <div id="iw-extend-message"></div>
                        <button type="submit" class="iw-submit">Extend Membership</button>
                    </form>
                </td>
            </tr>
        </tbody>
    </table>
    
    <div id="iw-extend-result" style="display: none;">
        <div class="iw-message iw-success">
            <h3>Membership Extended Successfully!</h3>
            <p>Your membership has been extended. You now have full access to all courses.</p>
            <p><a href="<?php echo esc_url(home_url('/')); ?>" class="iw-submit">Go to Home</a></p>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#iw-extend-membership-form').on('submit', function(e) {
        e.preventDefault();
        
        var code = $('input[name="extension_code"]').val().trim();
        var messageDiv = $('#iw-extend-message');
        
        if (!code) {
            messageDiv.html('<div class="iw-message iw-error">Please enter an extension code</div>');
            return;
        }
        
        messageDiv.html('<span style="color:#666;">Processing your extension code...</span>');
        
        $.ajax({
            url: iwMembership.ajaxUrl,
            type: 'POST',
            data: {
                action: 'iw_extend_membership',
                nonce: iwMembership.nonce,
                code: code
            },
            success: function(response) {
                if (response.success) {
                    $('#iw-extend-membership-form').hide();
                    messageDiv.html('');
                    $('#iw-extend-result').show();
                    
                    // Redirect after 2 seconds
                    setTimeout(function() {
                        window.location.href = '<?php echo esc_url(home_url('/')); ?>';
                    }, 2000);
                } else {
                    messageDiv.html('<div class="iw-message iw-error">' + (response.data.message || 'Extension failed') + '</div>');
                }
            },
            error: function() {
                messageDiv.html('<div class="iw-message iw-error">An error occurred. Please try again.</div>');
            }
        });
    });
});
</script>
