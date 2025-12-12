<?php
/**
 * SMS Sender Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Elementor_SMS_OTP_SMS_Sender {

    private $api_key;
    private $api_secret;

    public function __construct() {
        $this->api_key    = get_option('elementor_sms_otp_api_key', '');
        $this->api_secret = get_option('elementor_sms_otp_api_secret', '');
    }

    /**
     * Send SMS via smsoffice.ge
     *
     * @param string $phone   Local Georgian phone without country code (e.g. 5XXXXXXXX)
     * @param string $message Full SMS text to send (already templated)
     *
     * NOTE: Unlike before, this method now treats $message as the FINAL content.
     * It no longer prepends "Your login code is:".
     */
    public function send($phone, $message) {
        if (empty($this->api_key)) {
            error_log('SMS OTP Error: API key not configured');
            return false;
        }

        // If somehow empty message is passed, bail early
        $message = trim((string) $message);
        if ($message === '') {
            error_log('SMS OTP Error: Empty SMS content');
            return false;
        }

        // Format phone number for Georgian international format (995 prefix)
        $destination = '995' . $phone;

        // Get sender from settings (default: SMSOFFICE)
        $sender = get_option('elementor_sms_otp_sender', 'SMSOFFICE');

        // Smsoffice.ge API endpoint - using GET method as per documentation
        $api_url = 'https://smsoffice.ge/api/v2/send/';

        // Build query parameters
        $params = [
            'key'        => $this->api_key,
            'destination'=> $destination,
            'sender'     => $sender,
            'content'    => $message, // 🔹 Use the message AS-IS (already templated)
        ];

        // Build URL with parameters
        $url = $api_url . '?' . http_build_query($params);

        $args = [
            'method'    => 'GET',
            'timeout'   => 30,
            'sslverify' => true,
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            error_log('SMS OTP Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Check for successful response based on Smsoffice.ge API documentation
        if (isset($body['Success']) && $body['Success'] === true) {
            return true;
        }

        // Log error if present
        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            $error_messages = [
                10   => 'Destination contains non-Georgian numbers',
                20   => 'Insufficient balance',
                40   => 'Text exceeds 160 characters',
                60   => 'Missing content parameter',
                70   => 'Missing phone numbers',
                75   => 'All numbers in stop list',
                76   => 'All numbers in wrong format',
                77   => 'All numbers in stop list or wrong format',
                80   => 'API key not found',
                110  => 'Invalid sender parameter',
                120  => 'API usage not enabled in profile',
                150  => 'Sender not found in system',
                500  => 'Missing key parameter',
                600  => 'Missing destination parameter',
                700  => 'Missing sender parameter',
                800  => 'Missing content parameter',
                -100 => 'Temporary delay',
            ];

            $error_msg = $error_messages[$body['ErrorCode']] ?? 'Unknown error';
            error_log('SMS OTP API Error Code ' . $body['ErrorCode'] . ': ' . $error_msg);
        }

        return false;
    }

    public function validate_phone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Validate Georgian phone number (format: 5XXXXXXXX - 9 digits starting with 5)
        return preg_match('/^5\d{8}$/', $phone);
    }

    public function format_phone($phone) {
        // Remove all non-numeric characters
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
