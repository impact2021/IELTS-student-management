<?php
/**
 * Extend Membership Template
 */
if (!defined('ABSPATH')) exit;
?>

<div class="iw-extend-membership-wrapper">
    <div class="iw-extend-container">
        <h2>Extend Your Membership</h2>
        <p>Enter your extension code below to extend your membership access.</p>
        
        <form id="iw-extend-membership-form">
            <div class="iw-form-group">
                <label for="extension_code">Extension Code</label>
                <input type="text" id="extension_code" name="extension_code" required placeholder="Enter your extension code" />
            </div>
            
            <div id="iw-extend-message"></div>
            
            <button type="submit" class="iw-btn iw-btn-primary">Extend Membership</button>
        </form>
        
        <div id="iw-extend-result" style="display: none;">
            <div class="iw-success-message">
                <h3>Membership Extended Successfully!</h3>
                <p>Your membership has been extended. You can now access the site.</p>
                <a href="<?php echo home_url('/'); ?>" class="iw-btn iw-btn-primary">Go to Home</a>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#iw-extend-membership-form').on('submit', function(e) {
        e.preventDefault();
        
        var code = $('#extension_code').val().trim();
        var messageDiv = $('#iw-extend-message');
        
        if (!code) {
            messageDiv.html('<div class="iw-error">Please enter an extension code</div>');
            return;
        }
        
        messageDiv.html('<div class="iw-loading">Processing your extension code...</div>');
        
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
                } else {
                    messageDiv.html('<div class="iw-error">' + response.data.message + '</div>');
                }
            },
            error: function() {
                messageDiv.html('<div class="iw-error">An error occurred. Please try again.</div>');
            }
        });
    });
});
</script>
