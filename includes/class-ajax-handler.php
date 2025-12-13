<?php
/**
 * AJAX Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Elementor_SMS_OTP_Ajax_Handler {

    private $logger;
    private $sms_sender;

    public function __construct($logger, $sms_sender) {
        $this->logger     = $logger;
        $this->sms_sender = $sms_sender;

        // Frontend AJAX handlers
        add_action('wp_ajax_nopriv_send_otp_code',   [$this, 'send_otp']);
        add_action('wp_ajax_nopriv_verify_otp_login', [$this, 'verify_otp']);

        // Admin AJAX handlers
        add_action('wp_ajax_export_sms_logs', [$this, 'export_logs']);
        add_action('wp_ajax_clear_sms_logs',  [$this, 'clear_logs']);
    }

    /**
     * Handle sending OTP via AJAX
     */
    public function send_otp() {
        check_ajax_referer('elementor_sms_otp_nonce', 'nonce');

        $username = sanitize_text_field($_POST['username'] ?? '');

        if (empty($username)) {
            wp_send_json_error(['message' => __('Username is required', 'elementor-sms-otp')]);
        }

        // Get user by username or email
        $user = get_user_by('login', $username);
        if (!$user) {
            $user = get_user_by('email', $username);
        }

        if (!$user) {
    		$this->logger->log_sms(0, $username, '', '', 'failed');
    		wp_send_json_error(['message' => __('Invalid username or phone number not configured', 'elementor-sms-otp')]);
		}

		// Also combine with phone check
		if (empty($phone)) {
    		$this->logger->log_sms($user->ID, $user->user_login, '', '', 'failed');
    		wp_send_json_error(['message' => __('Invalid username or phone number not configured', 'elementor-sms-otp')]);
		}

        // Check rate limit
        $rate_limit    = (int) get_option('elementor_sms_otp_rate_limit', 3);
        $rate_limit    = $rate_limit > 0 ? $rate_limit : 3;
        $rate_limit_key = 'elementor_otp_rate_' . $user->ID;
        $attempts      = get_transient($rate_limit_key);

        if ($attempts !== false && $attempts >= $rate_limit) {
            wp_send_json_error([
                'message' => __('Too many requests. Please try again later.', 'elementor-sms-otp'),
            ]);
        }

        // Get phone number from user meta
        $phone = get_user_meta($user->ID, 'billing_phone', true);

        if (empty($phone)) {
            $this->logger->log_sms($user->ID, $user->user_login, '', '', 'failed');
            wp_send_json_error(['message' => __('Phone number not found for this user', 'elementor-sms-otp')]);
        }

        // Validate and format phone number
        $phone = $this->sms_sender->format_phone($phone);

        if (!$this->sms_sender->validate_phone($phone)) {
            $this->logger->log_sms($user->ID, $user->user_login, $phone, '', 'failed');
            wp_send_json_error(['message' => __('Invalid Georgian phone number', 'elementor-sms-otp')]);
        }

        // Generate 6-digit OTP
		$otp_code = sprintf('%06d', random_int(100000, 999999));


        // Store OTP with expiry
        $expiry_minutes = (int) get_option('elementor_sms_otp_code_expiry', 5);
        $expiry_minutes = $expiry_minutes > 0 ? $expiry_minutes : 5;

        set_transient(
            'elementor_otp_' . $user->ID,
            $otp_code,
            $expiry_minutes * 60
        );

        // Increment rate limit counter
        set_transient(
            $rate_limit_key,
            ($attempts === false ? 1 : $attempts + 1),
            HOUR_IN_SECONDS
        );

        // Build SMS message from template
        $template = get_option(
            'elementor_sms_otp_sms_template',
            __('Your login code is {code}', 'elementor-sms-otp')
        );

        // Replace {code} placeholder with actual OTP
        $message = str_replace('{code}', $otp_code, $template);

        // Send SMS via Smsoffice.ge
        $sms_sent = $this->sms_sender->send($phone, $message);

        if ($sms_sent) {
            // Log successful SMS send
            $this->logger->log_sms($user->ID, $user->user_login, $phone, $otp_code, 'sent');

            wp_send_json_success([
                'message' => __('OTP code sent successfully', 'elementor-sms-otp'),
                'user_id' => $user->ID,
            ]);
        } else {
            // Log failed SMS send
            $this->logger->log_sms($user->ID, $user->user_login, $phone, $otp_code, 'failed');
            wp_send_json_error(['message' => __('Failed to send SMS', 'elementor-sms-otp')]);
        }
    }

    /**
     * Handle verifying OTP + logging user in
     */
    public function verify_otp() {
    check_ajax_referer('elementor_sms_otp_nonce', 'nonce');

    $user_id  = intval($_POST['user_id'] ?? 0);
    $otp_code = sanitize_text_field($_POST['otp_code'] ?? '');

    if (empty($user_id) || empty($otp_code)) {
        wp_send_json_error(['message' => __('Invalid request', 'elementor-sms-otp')]);
    }

    // NEW: Check verification attempts
    $attempts_key = 'elementor_otp_attempts_' . $user_id;
    $attempts = get_transient($attempts_key);
    
    if ($attempts !== false && $attempts >= 5) {
        wp_send_json_error(['message' => __('Too many verification attempts. Please request a new code.', 'elementor-sms-otp')]);
    }

    $stored_otp = get_transient('elementor_otp_' . $user_id);
    $user = get_user_by('ID', $user_id);

    if ($stored_otp === false) {
        $this->logger->log_sms($user_id, $user->user_login, '', $otp_code, 'expired');
        wp_send_json_error(['message' => __('OTP code expired', 'elementor-sms-otp')]);
    }

    if ($stored_otp !== $otp_code) {
        // NEW: Increment attempts
        set_transient($attempts_key, ($attempts === false ? 1 : $attempts + 1), 300); // 5 minutes
        
        $this->logger->log_sms($user_id, $user->user_login, '', $otp_code, 'failed');
        wp_send_json_error(['message' => __('Invalid OTP code', 'elementor-sms-otp')]);
    }

    	// OTP is valid, clear attempts counter
    	delete_transient($attempts_key);
    	delete_transient('elementor_otp_' . $user_id);
    
    
		// Log successful verification
        $this->logger->log_sms($user_id, $user->user_login, '', $otp_code, 'verified');

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', $user->user_login, $user);

        wp_send_json_success([
            'message'  => __('Login successful', 'elementor-sms-otp'),
            'redirect' => home_url(),
        ]);
    }

    /**
     * Export logs
     */
    public function export_logs() {
        check_ajax_referer('elementor_sms_otp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'elementor-sms-otp')]);
        }

        $file_url = $this->logger->export_logs();

        if ($file_url) {
            wp_send_json_success([
                'message'  => __('Export successful', 'elementor-sms-otp'),
                'file_url' => $file_url,
            ]);
        } else {
            wp_send_json_error(['message' => __('Export failed. No logs found.', 'elementor-sms-otp')]);
        }
    }

    /**
     * Clear all logs
     */
    public function clear_logs() {
        check_ajax_referer('elementor_sms_otp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'elementor-sms-otp')]);
        }

        $this->logger->clear_logs();

        wp_send_json_success(['message' => __('Logs cleared successfully', 'elementor-sms-otp')]);
    }
}