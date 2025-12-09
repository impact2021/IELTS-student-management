<?php
/**
 * Login Form Template
 */
if (!defined('ABSPATH')) exit;
?>

<div class="iw-login-form-wrapper">
    <div class="iw-form-container">
        <h2>Login to Your Account</h2>
        
        <form id="iw-login-form" class="iw-form">
            <div class="iw-form-group">
                <label for="login_email">Email Address</label>
                <input type="email" id="login_email" name="email" required placeholder="your@email.com">
            </div>
            
            <div class="iw-form-group">
                <label for="login_password">Password</label>
                <input type="password" id="login_password" name="password" required placeholder="••••••••">
            </div>
            
            <div class="iw-form-group">
                <button type="submit" class="iw-btn iw-btn-primary">
                    <span class="iw-btn-text">Login</span>
                    <span class="iw-spinner" style="display:none;">Logging in...</span>
                </button>
            </div>
            
            <div class="iw-form-message"></div>
        </form>
        
        <div class="iw-form-footer">
            <p>Don't have an account? <a href="<?php echo home_url('/register/'); ?>">Register here</a></p>
        </div>
    </div>
</div>
