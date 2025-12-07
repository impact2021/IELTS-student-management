/**
 * IELTS Membership Plugin JavaScript
 */

jQuery(document).ready(function($) {
    
    /**
     * Handle Login Form
     */
    $('#iw-login-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var $message = $form.find('.iw-form-message');
        
        // Disable button and show loading
        $button.addClass('loading').prop('disabled', true);
        $message.hide().removeClass('success error');
        
        var formData = {
            action: 'iw_login',
            nonce: iwMembership.nonce,
            email: $('#login_email').val(),
            password: $('#login_password').val()
        };
        
        $.ajax({
            url: iwMembership.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $message.addClass('success').text(response.data.message).show();
                    setTimeout(function() {
                        window.location.href = response.data.redirect;
                    }, 1000);
                } else {
                    $message.addClass('error').text(response.data.message).show();
                    $button.removeClass('loading').prop('disabled', false);
                }
            },
            error: function() {
                $message.addClass('error').text('An error occurred. Please try again.').show();
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    /**
     * Handle Registration Form
     */
    $('#iw-register-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var $message = $form.find('.iw-form-message');
        
        // Validate passwords match
        var password = $('#register_password').val();
        var passwordConfirm = $('#register_password_confirm').val();
        
        if (password !== passwordConfirm) {
            $message.addClass('error').text('Passwords do not match').show();
            return;
        }
        
        // Disable button and show loading
        $button.addClass('loading').prop('disabled', true);
        $message.hide().removeClass('success error');
        
        var formData = {
            action: 'iw_register',
            nonce: iwMembership.nonce,
            email: $('#register_email').val(),
            password: password,
            first_name: $('#register_first_name').val(),
            last_name: $('#register_last_name').val()
        };
        
        $.ajax({
            url: iwMembership.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $message.addClass('success').text(response.data.message).show();
                    $form[0].reset();
                    setTimeout(function() {
                        window.location.href = response.data.redirect;
                    }, 1000);
                } else {
                    $message.addClass('error').text(response.data.message).show();
                    $button.removeClass('loading').prop('disabled', false);
                }
            },
            error: function() {
                $message.addClass('error').text('An error occurred. Please try again.').show();
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    /**
     * Handle Subscribe Button
     */
    $(document).on('click', '.iw-subscribe-btn', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var planId = $button.data('plan-id');
        var paymentMethod = 'credit_card'; // Default payment method
        
        if (!confirm('Subscribe to this plan?')) {
            return;
        }
        
        $button.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: iwMembership.ajaxUrl,
            type: 'POST',
            data: {
                action: 'iw_subscribe',
                nonce: iwMembership.nonce,
                plan_id: planId,
                payment_method: paymentMethod
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message);
                    $button.prop('disabled', false).text('Subscribe');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $button.prop('disabled', false).text('Subscribe');
            }
        });
    });
    
    /**
     * Form Validation Helpers
     */
    
    // Email validation
    function isValidEmail(email) {
        var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }
    
    // Real-time email validation
    $('input[type="email"]').on('blur', function() {
        var $input = $(this);
        var email = $input.val();
        
        if (email && !isValidEmail(email)) {
            $input.css('border-color', '#dc3545');
            if (!$input.next('.iw-validation-error').length) {
                $input.after('<small class="iw-validation-error" style="color:#dc3545;">Please enter a valid email address</small>');
            }
        } else {
            $input.css('border-color', '');
            $input.next('.iw-validation-error').remove();
        }
    });
    
    // Password strength indicator
    $('#register_password').on('input', function() {
        var password = $(this).val();
        var strength = 0;
        
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        
        var $indicator = $(this).next('.iw-password-strength');
        if (!$indicator.length) {
            $indicator = $('<small class="iw-password-strength"></small>');
            $(this).after($indicator);
        }
        
        var strengthText = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
        var strengthColor = ['#dc3545', '#ffc107', '#17a2b8', '#28a745', '#28a745'];
        
        if (password.length === 0) {
            $indicator.text('').hide();
        } else {
            $indicator.text('Password strength: ' + strengthText[strength - 1])
                      .css('color', strengthColor[strength - 1])
                      .show();
        }
    });
    
    // Confirm password validation
    $('#register_password_confirm').on('input', function() {
        var password = $('#register_password').val();
        var confirmPassword = $(this).val();
        var $indicator = $(this).next('.iw-password-match');
        
        if (!$indicator.length) {
            $indicator = $('<small class="iw-password-match"></small>');
            $(this).after($indicator);
        }
        
        if (confirmPassword.length === 0) {
            $indicator.hide();
        } else if (password === confirmPassword) {
            $indicator.text('Passwords match')
                      .css('color', '#28a745')
                      .show();
        } else {
            $indicator.text('Passwords do not match')
                      .css('color', '#dc3545')
                      .show();
        }
    });
});
