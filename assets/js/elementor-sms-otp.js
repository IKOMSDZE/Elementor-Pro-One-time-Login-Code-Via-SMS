jQuery(document).ready(function($) {
    'use strict';

    // Safe getter for strings with fallback
    function t(key, fallback) {
        if (window.elementorSmsOtp && elementorSmsOtp.strings && elementorSmsOtp.strings[key]) {
            return elementorSmsOtp.strings[key];
        }
        return fallback;
    }

    // Read settings from localized object (with safe fallbacks)
    const otpOnlyMode = !!(window.elementorSmsOtp && elementorSmsOtp.settings && elementorSmsOtp.settings.otp_only);
    const resendWait  = (window.elementorSmsOtp && elementorSmsOtp.settings && elementorSmsOtp.settings.resend_wait) || 60;

    // Add OTP button to Elementor login forms
    function addOtpButton() {
        const loginForms = $('.elementor-login .elementor-form, .elementor-widget-login .elementor-form');

        if (loginForms.length === 0) {
            return;
        }

        loginForms.each(function() {
            const form = $(this);

            // Check if OTP button already exists
            if (form.find('.elementor-otp-btn').length > 0) {
                return;
            }

            const passwordField = form.find('input[type="password"]');
            const submitButton  = form.find('.elementor-button[type="submit"]');

            // Hide "Remember me" checkbox if present
            const rememberMe = form.find('input[name="rememberme"]');
            if (rememberMe.length) {
                rememberMe.closest('label, .elementor-field-group, .elementor-field-subgroup').hide();
            }

            // Hide "Lost your password?" link if present
            const lostPassLink = form.find('a[href*="lostpassword"]');
            if (lostPassLink.length) {
                lostPassLink.hide();
            }

            if (passwordField.length === 0) {
                return;
            }

            // OTP-only mode (controlled from admin)
            if (otpOnlyMode) {
                passwordField.closest('.elementor-field-group').hide();
                passwordField.prop('required', false);

                if (submitButton.length) {
                    submitButton.hide();
                }

                form.on('submit.elementorSmsOtp', function(e) {
                    e.preventDefault();
                });
            }

            // Create OTP login button
            const otpButton = $('<button>', {
                type: 'button',
                class: 'elementor-button elementor-otp-btn',
                text: t('btn_login', 'Login with SMS Code'),
                css: {
                    'margin-top': '10px'
                }
            });

            // Insert OTP button after submit button
            if (submitButton.length) {
                submitButton.after(otpButton);
            } else {
                form.append(otpButton);
            }

            // Create OTP input container (hidden by default)
            const otpContainer = $('<div>', {
                class: 'elementor-otp-container',
                css: {
                    'display': 'none'
                }
            });

            const otpInput = $('<input>', {
                type: 'text',
                class: 'elementor-field elementor-otp-input',
                placeholder: t('placeholder_otp', 'Enter 6-digit code'),
                maxlength: 6,
                pattern: '[0-9]{6}'
            });

            const verifyButton = $('<button>', {
                type: 'button',
                class: 'elementor-button elementor-otp-verify',
                text: t('btn_verify', 'Verify Code'),
                css: {}
            });

            // 🔻 Message container – this will go BELOW the fields (before submit group)
            const otpMessage = $('<div>', {
                class: 'elementor-otp-message',
                css: {
                    'margin-top': '5px',
                    'padding': '5px',
					'width':'100%',
                    'display': 'none'
                }
            });

            // OTP container only holds OTP input + verify button
            otpContainer.append(otpInput, verifyButton);
            otpButton.after(otpContainer);

            // Find the submit field-group and inject the message just before it
            const submitGroup = form.find('.elementor-field-group.elementor-field-type-submit').first();
            if (submitGroup.length) {
                submitGroup.before(otpMessage);
            } else {
                // Fallback: if no submit group found, append at end of form
                form.append(otpMessage);
            }

            // State for verification + resend countdown
            let currentUserId    = null;
            let resendTimer      = null;
            let remainingSeconds = resendWait;

            // Start / restart resend countdown on the OTP button
            function startResendTimer() {
                otpButton.show();

                remainingSeconds = resendWait;
                otpButton.prop('disabled', true)
                    .text(t('btn_resend', 'Resend code') + ' (' + remainingSeconds + 's)');

                if (resendTimer) {
                    clearInterval(resendTimer);
                }

                resendTimer = setInterval(function() {
                    remainingSeconds--;
                    if (remainingSeconds > 0) {
                        otpButton.text(t('btn_resend', 'Resend code') + ' (' + remainingSeconds + 's)');
                    } else {
                        clearInterval(resendTimer);
                        resendTimer = null;
                        otpButton.prop('disabled', false)
                            .text(t('btn_resend', 'Resend code'));
                    }
                }, 1000);
            }

            // Handle OTP button click (send / resend code)
            otpButton.on('click', function(e) {
                e.preventDefault();

                if (otpButton.prop('disabled')) {
                    return;
                }

                // Elementor login uses "log" for username
                const usernameField = form.find('input[name="log"], input[name="username"], input[name="email"], input[type="text"]').first();
                const username      = usernameField.val().trim();

                if (!username) {
                    showMessage(t('msg_enter_username', 'Please enter your username or email'), 'error');
                    return;
                }

                otpButton.prop('disabled', true).text(t('sending', 'Sending code...'));

                $.ajax({
                    url: elementorSmsOtp.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'send_otp_code',
                        nonce: elementorSmsOtp.nonce,
                        username: username
                    },
                    success: function(response) {
                        if (response.success) {
                            currentUserId = response.data.user_id;

                            otpContainer.show();
                            otpInput.focus();

                            if (otpOnlyMode) {
                                passwordField.closest('.elementor-field-group').hide();
                                submitButton.hide();
                            }

                            showMessage(t('sent', 'Code sent! Check your phone.'), 'success');

                            startResendTimer();
                        } else {
                            showMessage(response.data.message || t('error', 'Error sending code. Please try again.'), 'error');
                            otpButton.prop('disabled', false).text(t('btn_login', 'Login with SMS Code'));
                        }
                    },
                    error: function() {
                        showMessage(t('error', 'Error sending code. Please try again.'), 'error');
                        otpButton.prop('disabled', false).text(t('btn_login', 'Login with SMS Code'));
                    }
                });
            });

            // Handle verify button click
            verifyButton.on('click', function(e) {
                e.preventDefault();

                const otpCode = otpInput.val().trim();

                if (!otpCode || otpCode.length !== 6) {
                    showMessage(t('msg_enter_valid_otp', 'Please enter a valid 6-digit code'), 'error');
                    return;
                }

                if (!currentUserId) {
                    showMessage(t('msg_session_expired', 'Session expired. Please request a new code.'), 'error');
                    return;
                }

                verifyButton.prop('disabled', true).text(t('verifying', 'Verifying...'));

                $.ajax({
                    url: elementorSmsOtp.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'verify_otp_login',
                        nonce: elementorSmsOtp.nonce,
                        user_id: currentUserId,
                        otp_code: otpCode
                    },
                    success: function(response) {
                        if (response.success) {
                            showMessage(response.data.message, 'success');

                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1000);
                        } else {
                            showMessage(response.data.message || t('msg_invalid_code', 'Invalid code'), 'error');
                            verifyButton.prop('disabled', false).text(t('btn_verify', 'Verify Code'));
                        }
                    },
                    error: function() {
                        showMessage(t('msg_verify_error', 'Verification failed. Please try again.'), 'error');
                        verifyButton.prop('disabled', false).text(t('btn_verify', 'Verify Code'));
                    }
                });
            });

            otpInput.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    verifyButton.click();
                }
            });

            // Show message below fields (above submit)
            function showMessage(message, type) {
                otpMessage.removeClass('success error')
                    .addClass(type)
                    .text(message)
                    .css({
                        'background-color': type === 'success' ? '#d4edda' : '#f8d7da',
                        'color': type === 'success' ? '#155724' : '#721c24',
                        'border': '1px solid ' + (type === 'success' ? '#c3e6cb' : '#f5c6cb')
                    })
                    .show();

                setTimeout(function() {
                    otpMessage.fadeOut();
                }, 5000);
            }
        });
    }

    addOtpButton();

    $(document).on('elementor/popup/show', function() {
        setTimeout(addOtpButton, 100);
    });
});
