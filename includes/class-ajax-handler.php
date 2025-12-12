<?php
/**
 * AJAX Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Elementor_SMS_OTP_Ajax_Handler {

    private $db;
    private $sms_sender;

    public function __construct($db, $sms_sender) {
        $this->db        = $db;
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
            // Log failed attempt
            $this->db->log_sms(0, $username, '', '', 'failed');
            wp_send_json_error(['message' => __('User not found', 'elementor-sms-otp')]);
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
            $this->db->log_sms($user->ID, $user->user_login, '', '', 'failed');
            wp_send_json_error(['message' => __('Phone number not found for this user', 'elementor-sms-otp')]);
        }

        // Validate and format phone number
        $phone = $this->sms_sender->format_phone($phone);

        if (!$this->sms_sender->validate_phone($phone)) {
            $this->db->log_sms($user->ID, $user->user_login, $phone, '', 'failed');
            wp_send_json_error(['message' => __('Invalid Georgian phone number', 'elementor-sms-otp')]);
        }

        // Generate 6-digit OTP
        $otp_code = sprintf('%06d', mt_rand(0, 999999));

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
            $this->db->log_sms($user->ID, $user->user_login, $phone, $otp_code, 'sent');

            wp_send_json_success([
                'message' => __('OTP code sent successfully', 'elementor-sms-otp'),
                'user_id' => $user->ID,
            ]);
        } else {
            // Log failed SMS send
            $this->db->log_sms($user->ID, $user->user_login, $phone, $otp_code, 'failed');
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

        $stored_otp = get_transient('elementor_otp_' . $user_id);

        global $wpdb;
        $table_name = $wpdb->prefix . 'elementor_sms_otp_logs';

        if ($stored_otp === false) {
            // Mark as expired in logs
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name}
                 SET status = 'expired'
                 WHERE user_id = %d AND status = 'sent'
                 ORDER BY created_at DESC
                 LIMIT 1",
                $user_id
            ));

            wp_send_json_error(['message' => __('OTP code expired', 'elementor-sms-otp')]);
        }

        if ($stored_otp !== $otp_code) {
            // Update to failed status
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name}
                 SET status = 'failed'
                 WHERE user_id = %d AND status = 'sent'
                 ORDER BY created_at DESC
                 LIMIT 1",
                $user_id
            ));

            wp_send_json_error(['message' => __('Invalid OTP code', 'elementor-sms-otp')]);
        }

        // OTP is valid, log the user in
        delete_transient('elementor_otp_' . $user_id);

        // Update to verified status
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name}
             SET status = 'verified'
             WHERE user_id = %d AND status = 'sent'
             ORDER BY created_at DESC
             LIMIT 1",
            $user_id
        ));

        $user = get_user_by('ID', $user_id);

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', $user->user_login, $user);

        wp_send_json_success([
            'message'  => __('Login successful', 'elementor-sms-otp'),
            'redirect' => home_url(),
        ]);
    }

    /**
     * Export logs as CSV
     */
    public function export_logs() {
        check_ajax_referer('elementor_sms_otp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'elementor-sms-otp')]);
        }

        $args = [
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '',
            'date'   => isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '',
        ];

        $logs = $this->db->export_logs_csv($args);

        if (empty($logs)) {
            wp_send_json_error(['message' => __('No logs to export', 'elementor-sms-otp')]);
        }

        $upload_dir = wp_upload_dir();
        $filename   = 'sms-otp-logs-' . date('Y-m-d-H-i-s') . '.csv';
        $filepath   = trailingslashit($upload_dir['path']) . $filename;

        $fp = fopen($filepath, 'w');

        // Add headers
        fputcsv($fp, ['ID', 'User ID', 'Username', 'Phone', 'OTP Code', 'Status', 'IP Address', 'Date']);

        // Add data
        foreach ($logs as $log) {
            fputcsv($fp, [
                $log['id'],
                $log['user_id'],
                $log['username'],
                $log['phone'],
                $log['otp_code'],
                $log['status'],
                $log['ip_address'],
                $log['created_at'],
            ]);
        }

        fclose($fp);

        wp_send_json_success([
            'message'  => __('Export successful', 'elementor-sms-otp'),
            'file_url' => trailingslashit($upload_dir['url']) . $filename,
        ]);
    }

    /**
     * Clear all logs
     */
    public function clear_logs() {
        check_ajax_referer('elementor_sms_otp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'elementor-sms-otp')]);
        }

        $this->db->clear_logs();

        wp_send_json_success(['message' => __('Logs cleared successfully', 'elementor-sms-otp')]);
    }
}
