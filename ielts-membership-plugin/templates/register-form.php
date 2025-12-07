<?php
/**
 * Registration Form Template
 */
if (!defined('ABSPATH')) exit;
?>

<div class="iw-register-form-wrapper">
    <div class="iw-form-container">
        <h2>Create Your Account</h2>
        
        <?php if (!empty($atts['code'])): ?>
            <div class="iw-registration-code">
                <p>Registration Code: <strong><?php echo esc_html($atts['code']); ?></strong></p>
            </div>
        <?php endif; ?>
        
        <form id="iw-register-form" class="iw-form">
            <div class="iw-form-row">
                <div class="iw-form-group iw-form-half">
                    <label for="register_first_name">First Name</label>
                    <input type="text" id="register_first_name" name="first_name" required placeholder="John">
                </div>
                
                <div class="iw-form-group iw-form-half">
                    <label for="register_last_name">Last Name</label>
                    <input type="text" id="register_last_name" name="last_name" required placeholder="Doe">
                </div>
            </div>
            
            <div class="iw-form-group">
                <label for="register_email">Email Address</label>
                <input type="email" id="register_email" name="email" required placeholder="your@email.com">
            </div>
            
            <div class="iw-form-group">
                <label for="register_password">Password</label>
                <input type="password" id="register_password" name="password" required placeholder="••••••••" minlength="6">
                <small>Minimum 6 characters</small>
            </div>
            
            <div class="iw-form-group">
                <label for="register_password_confirm">Confirm Password</label>
                <input type="password" id="register_password_confirm" name="password_confirm" required placeholder="••••••••">
            </div>
            
            <?php if (!empty($atts['code'])): ?>
                <input type="hidden" name="registration_code" value="<?php echo esc_attr($atts['code']); ?>">
            <?php endif; ?>
            
            <?php if (!empty($atts['plan_id'])): ?>
                <input type="hidden" name="plan_id" value="<?php echo esc_attr($atts['plan_id']); ?>">
            <?php endif; ?>
            
            <div class="iw-form-group">
                <label class="iw-checkbox">
                    <input type="checkbox" name="terms" required>
                    <span>I agree to the Terms and Conditions</span>
                </label>
            </div>
            
            <div class="iw-form-group">
                <button type="submit" class="iw-btn iw-btn-primary">
                    <span class="iw-btn-text">Create Account</span>
                    <span class="iw-spinner" style="display:none;">Creating account...</span>
                </button>
            </div>
            
            <div class="iw-form-message"></div>
        </form>
        
        <div class="iw-form-footer">
            <p>Already have an account? <a href="<?php echo home_url('/login/'); ?>">Login here</a></p>
        </div>
    </div>
</div>
