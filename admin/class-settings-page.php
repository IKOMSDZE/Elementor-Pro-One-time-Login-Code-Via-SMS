<?php
/**
 * Settings Page Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Elementor_SMS_OTP_Settings_Page {

    private $sms_sender;
    private $db;

    public function __construct($sms_sender = null, $db = null) {
        $this->sms_sender = $sms_sender ? $sms_sender : new Elementor_SMS_OTP_SMS_Sender();
        $this->db         = $db ? $db : new Elementor_SMS_OTP_Database();
    }

    public function render() {
        $api_key = get_option('elementor_sms_otp_api_key', '');

        // Handle test SMS
        $test_result = '';
        if (isset($_POST['test_sms_submit']) && check_admin_referer('elementor_sms_otp_test_sms')) {
            $test_phone = sanitize_text_field($_POST['test_phone']);
            $test_code  = sprintf('%06d', mt_rand(0, 999999));

            $sms_sender = new Elementor_SMS_OTP_SMS_Sender();
            if ($sms_sender->send($test_phone, $test_code)) {
                $test_result = '<div class="notice notice-success"><p>' . __('Test SMS sent successfully!', 'elementor-sms-otp') . '</p></div>';
            } else {
                $test_result = '<div class="notice notice-error"><p>' . __('Failed to send test SMS. Please check your API credentials.', 'elementor-sms-otp') . '</p></div>';
            }
        }

        // Get statistics
        $db    = new Elementor_SMS_OTP_Database();
        $stats = $db->get_statistics();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('SMS OTP Login Settings', 'elementor-sms-otp'); ?></h1>

            <?php echo $test_result; ?>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Main Settings -->
                <div>
                    <div class="card" style="padding: 20px;">
                        <h2 style="margin-top: 0;"><?php echo esc_html__('Configuration', 'elementor-sms-otp'); ?></h2>
                        <form method="post" action="options.php">
                            <?php
                            settings_fields('elementor_sms_otp_settings');
                            do_settings_sections('elementor_sms_otp_settings');
                            ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_enabled"><?php echo esc_html__('Enable SMS OTP', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <label class="switch">
                                            <input type="checkbox" id="elementor_sms_otp_enabled" name="elementor_sms_otp_enabled" value="1" <?php checked(get_option('elementor_sms_otp_enabled'), 1); ?> />
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description"><?php echo esc_html__('Enable or disable SMS OTP login functionality', 'elementor-sms-otp'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_otp_only">
                                            <?php esc_html_e( 'Disable password login (OTP only)', 'elementor-sms-otp' ); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <?php $otp_only = get_option( 'elementor_sms_otp_otp_only', 0 ); ?>
                                        <label>
                                            <input
                                                type="checkbox"
                                                id="elementor_sms_otp_otp_only"
                                                name="elementor_sms_otp_otp_only"
                                                value="1"
                                                <?php checked( $otp_only, 1 ); ?>
                                            />
                                            <?php esc_html_e( 'Users can log in only via SMS OTP (hide password field and login button)', 'elementor-sms-otp' ); ?>
                                        </label>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_api_key"><?php echo esc_html__('Smsoffice.ge API Key', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_api_key" name="elementor_sms_otp_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                                        <p class="description"><?php echo esc_html__('Enter your Smsoffice.ge API key from profile page', 'elementor-sms-otp'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_sender"><?php echo esc_html__('SMS Sender Name', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_sender" name="elementor_sms_otp_sender" value="<?php echo esc_attr(get_option('elementor_sms_otp_sender', 'SMSOFFICE')); ?>" class="regular-text" maxlength="11" />
                                        <p class="description"><?php echo esc_html__('Sender name (max 11 characters). Must be registered at smsoffice.ge', 'elementor-sms-otp'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_code_expiry"><?php echo esc_html__('Code Expiry Time', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="elementor_sms_otp_code_expiry" name="elementor_sms_otp_code_expiry" value="<?php echo esc_attr(get_option('elementor_sms_otp_code_expiry', 5)); ?>" min="1" max="30" style="width: 80px;" /> <?php echo esc_html__('minutes', 'elementor-sms-otp'); ?>
                                        <p class="description"><?php echo esc_html__('How long the OTP code remains valid (1-30 minutes)', 'elementor-sms-otp'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_rate_limit"><?php echo esc_html__('Rate Limit', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="elementor_sms_otp_rate_limit" name="elementor_sms_otp_rate_limit" value="<?php echo esc_attr(get_option('elementor_sms_otp_rate_limit', 3)); ?>" min="1" max="10" style="width: 80px;" /> <?php echo esc_html__('requests per hour', 'elementor-sms-otp'); ?>
                                        <p class="description"><?php echo esc_html__('Maximum OTP requests per user per hour', 'elementor-sms-otp'); ?></p>
                                    </td>
                                </tr>

                                <!-- Front-end texts -->
                                <tr>
                                    <th colspan="2">
                                        <h2 style="margin-top:30px;"><?php echo esc_html__('Front-end Texts', 'elementor-sms-otp'); ?></h2>
                                    </th>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_btn_login"><?php echo esc_html__('Button: Login with SMS', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_btn_login" name="elementor_sms_otp_text_btn_login"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_btn_login', 'Login with SMS Code')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_btn_resend"><?php echo esc_html__('Button: Resend code', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_btn_resend" name="elementor_sms_otp_text_btn_resend"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_btn_resend', 'Resend code')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_btn_verify"><?php echo esc_html__('Button: Verify code', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_btn_verify" name="elementor_sms_otp_text_btn_verify"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_btn_verify', 'Verify Code')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_placeholder_otp"><?php echo esc_html__('OTP field placeholder', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_placeholder_otp" name="elementor_sms_otp_text_placeholder_otp"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_placeholder_otp', 'Enter 6-digit code')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_msg_enter_username"><?php echo esc_html__('Message: enter username/email', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_msg_enter_username" name="elementor_sms_otp_text_msg_enter_username"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_msg_enter_username', 'Please enter your username or email')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_msg_enter_valid_otp"><?php echo esc_html__('Message: enter valid OTP', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_msg_enter_valid_otp" name="elementor_sms_otp_text_msg_enter_valid_otp"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_msg_enter_valid_otp', 'Please enter a valid 6-digit code')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_msg_session_expired"><?php echo esc_html__('Message: session expired', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_msg_session_expired" name="elementor_sms_otp_text_msg_session_expired"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_msg_session_expired', 'Session expired. Please request a new code.')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_msg_verify_error"><?php echo esc_html__('Message: verify error', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_msg_verify_error" name="elementor_sms_otp_text_msg_verify_error"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_msg_verify_error', 'Verification failed. Please try again.')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_msg_invalid_code"><?php echo esc_html__('Message: invalid code', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_msg_invalid_code" name="elementor_sms_otp_text_msg_invalid_code"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_msg_invalid_code', 'Invalid code')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_sending"><?php echo esc_html__('Label: Sending state', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_sending" name="elementor_sms_otp_text_sending"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_sending', 'Sending code...')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_sent"><?php echo esc_html__('Label: Sent message', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_sent" name="elementor_sms_otp_text_sent"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_sent', 'Code sent! Check your phone.')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_error"><?php echo esc_html__('Label: Error message', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_error" name="elementor_sms_otp_text_error"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_error', 'Error sending code. Please try again.')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_invalid_phone"><?php echo esc_html__('Label: Invalid phone', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_invalid_phone" name="elementor_sms_otp_text_invalid_phone"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_invalid_phone', 'Please enter a valid Georgian phone number.')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_text_verifying"><?php echo esc_html__('Label: Verifying...', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="elementor_sms_otp_text_verifying" name="elementor_sms_otp_text_verifying"
                                               value="<?php echo esc_attr(get_option('elementor_sms_otp_text_verifying', 'Verifying...')); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="elementor_sms_otp_sms_template"><?php echo esc_html__('SMS Text Template', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="elementor_sms_otp_sms_template" name="elementor_sms_otp_sms_template"
                                                  rows="3" cols="50"><?php
                                            echo esc_textarea(get_option('elementor_sms_otp_sms_template', 'Your login code is {code}'));
                                        ?></textarea>
                                        <p class="description">
                                            <?php echo esc_html__('Use {code} placeholder for the OTP code.', 'elementor-sms-otp'); ?>
                                        </p>
                                    </td>
                                </tr>

                            </table>
                            <?php submit_button(__('Save Settings', 'elementor-sms-otp')); ?>
                        </form>
                    </div>

                    <!-- Test SMS Section -->
                    <div class="card" style="padding: 20px; margin-top: 20px;">
                        <h2 style="margin-top: 0;"><?php echo esc_html__('Test SMS', 'elementor-sms-otp'); ?></h2>
                        <form method="post" action="">
                            <?php wp_nonce_field('elementor_sms_otp_test_sms'); ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="test_phone"><?php echo esc_html__('Phone Number', 'elementor-sms-otp'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="test_phone" name="test_phone" value="" class="regular-text" placeholder="5XXXXXXXX" />
                                        <p class="description"><?php echo esc_html__('Enter a Georgian phone number to test SMS delivery', 'elementor-sms-otp'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <button type="submit" name="test_sms_submit" class="button button-secondary"><?php echo esc_html__('Send Test SMS', 'elementor-sms-otp'); ?></button>
                        </form>
                    </div>
                </div>

                <!-- Sidebar with Stats and Info -->
                <div>
                    <!-- Statistics -->
                    <div class="card" style="padding: 20px;">
                        <h2 style="margin-top: 0;"><?php echo esc_html__('Statistics', 'elementor-sms-otp'); ?></h2>
                        <div style="display: grid; gap: 15px;">
                            <div class="stat-box">
                                <div class="stat-number" style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($stats['total_sent']); ?></div>
                                <div class="stat-label" style="color: #666; font-size: 14px;"><?php echo esc_html__('Total SMS Sent', 'elementor-sms-otp'); ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-number" style="font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo esc_html($stats['successful_logins']); ?></div>
                                <div class="stat-label" style="color: #666; font-size: 14px;"><?php echo esc_html__('Successful Logins', 'elementor-sms-otp'); ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-number" style="font-size: 32px; font-weight: bold; color: #d63638;"><?php echo esc_html($stats['failed_attempts']); ?></div>
                                <div class="stat-label" style="color: #666; font-size: 14px;"><?php echo esc_html__('Failed Attempts', 'elementor-sms-otp'); ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-number" style="font-size: 32px; font-weight: bold; color: #f0b849;"><?php echo esc_html($stats['users_with_phone']); ?></div>
                                <div class="stat-label" style="color: #666; font-size: 14px;"><?php echo esc_html__('Users with Phone', 'elementor-sms-otp'); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="card" style="padding: 20px; margin-top: 20px;">
                        <h2 style="margin-top: 0;"><?php echo esc_html__('Status', 'elementor-sms-otp'); ?></h2>
                        <table class="widefat striped">
                            <tbody>
                                <tr>
                                    <td><strong><?php echo esc_html__('Plugin Status', 'elementor-sms-otp'); ?></strong></td>
                                    <td>
                                        <?php if (get_option('elementor_sms_otp_enabled')): ?>
                                            <span style="color: #00a32a;">● <?php echo esc_html__('Enabled', 'elementor-sms-otp'); ?></span>
                                        <?php else: ?>
                                            <span style="color: #d63638;">● <?php echo esc_html__('Disabled', 'elementor-sms-otp'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('API Configured', 'elementor-sms-otp'); ?></strong></td>
                                    <td>
                                        <?php if (!empty($api_key)): ?>
                                            <span style="color: #00a32a;">✓ <?php echo esc_html__('Yes', 'elementor-sms-otp'); ?></span>
                                        <?php else: ?>
                                            <span style="color: #d63638;">✗ <?php echo esc_html__('No', 'elementor-sms-otp'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Elementor Pro', 'elementor-sms-otp'); ?></strong></td>
                                    <td>
                                        <?php if (defined('ELEMENTOR_PRO_VERSION')): ?>
                                            <span style="color: #00a32a;">✓ <?php echo esc_html__('Active', 'elementor-sms-otp'); ?></span>
                                        <?php else: ?>
                                            <span style="color: #f0b849;">⚠ <?php echo esc_html__('Not Detected', 'elementor-sms-otp'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Quick Links -->
                    <div class="card" style="padding: 20px; margin-top: 20px;">
                        <h2 style="margin-top: 0;"><?php echo esc_html__('Quick Links', 'elementor-sms-otp'); ?></h2>
                        <ul style="margin: 0; padding-left: 20px;">
                            <li><a href="<?php echo admin_url('admin.php?page=elementor-sms-otp-logs'); ?>"><?php echo esc_html__('View SMS Logs', 'elementor-sms-otp'); ?></a></li>
                            <li><a href="https://smsoffice.ge" target="_blank"><?php echo esc_html__('Smsoffice.ge Dashboard', 'elementor-sms-otp'); ?></a></li>
                            <li><a href="<?php echo admin_url('users.php'); ?>"><?php echo esc_html__('Manage Users', 'elementor-sms-otp'); ?></a></li>
                        </ul>
                    </div>

                    <!-- Instructions -->
                    <div class="card" style="padding: 20px; margin-top: 20px; background: #f0f6fc;">
                        <h3 style="margin-top: 0; color: #2271b1;"><?php echo esc_html__('Setup Instructions', 'elementor-sms-otp'); ?></h3>
                        <ol style="margin: 0; padding-left: 20px; line-height: 1.8;">
                            <li><?php echo esc_html__('Get API key from Smsoffice.ge profile page', 'elementor-sms-otp'); ?></li>
                            <li><?php echo esc_html__('Register your sender name at Smsoffice.ge', 'elementor-sms-otp'); ?></li>
                            <li><?php echo esc_html__('Enter API key and sender name above', 'elementor-sms-otp'); ?></li>
                            <li><?php echo esc_html__('Enable the plugin', 'elementor-sms-otp'); ?></li>
                            <li><?php echo esc_html__('Test SMS functionality', 'elementor-sms-otp'); ?></li>
                            <li><?php echo esc_html__('Users add phone numbers in their profile', 'elementor-sms-otp'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>

            <style>
                .switch {
                    position: relative;
                    display: inline-block;
                    width: 50px;
                    height: 24px;
                }
                .switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }
                .slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #ccc;
                    transition: .4s;
                    border-radius: 24px;
                }
                .slider:before {
                    position: absolute;
                    content: "";
                    height: 18px;
                    width: 18px;
                    left: 3px;
                    bottom: 3px;
                    background-color: white;
                    transition: .4s;
                    border-radius: 50%;
                }
                input:checked + .slider {
                    background-color: #2271b1;
                }
                input:checked + .slider:before {
                    transform: translateX(26px);
                }
            </style>
        </div>
        <?php
    }
}
