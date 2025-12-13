<?php
/**
 * Plugin Name: Elementor SMS OTP Login
 * Plugin URI: https://iko.ge
 * Description: Adds one-time password login via SMS (Smsoffice.ge) to Elementor Pro login forms for Georgian users
 * Version: 1.0.4
 * Author: iko
 * Author URI: https://iko.ge
 * Text Domain: elementor-sms-otp
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ELEMENTOR_SMS_OTP_VERSION', '1.0.4');
define('ELEMENTOR_SMS_OTP_PATH', plugin_dir_path(__FILE__));
define('ELEMENTOR_SMS_OTP_URL', plugin_dir_url(__FILE__));

// Includes
require_once ELEMENTOR_SMS_OTP_PATH . 'includes/class-logger.php';
require_once ELEMENTOR_SMS_OTP_PATH . 'includes/class-sms-sender.php';
require_once ELEMENTOR_SMS_OTP_PATH . 'includes/class-ajax-handler.php';
require_once ELEMENTOR_SMS_OTP_PATH . 'admin/class-settings-page.php';
require_once ELEMENTOR_SMS_OTP_PATH . 'admin/class-logs-page.php';

class Elementor_SMS_OTP_Login {

    private static $instance = null;
    private $logger;
    private $sms_sender;
    private $ajax_handler;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->logger       = new Elementor_SMS_OTP_Logger();
        $this->sms_sender   = new Elementor_SMS_OTP_SMS_Sender();
        $this->ajax_handler = new Elementor_SMS_OTP_Ajax_Handler($this->logger, $this->sms_sender);

        add_action('init',               [$this, 'init']);
        add_action('admin_menu',         [$this, 'add_admin_menu']);
        add_action('admin_init',         [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('show_user_profile',  [$this, 'user_profile_fields']);
        add_action('edit_user_profile',  [$this, 'user_profile_fields']);
        add_action('personal_options_update',  [$this, 'save_user_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_profile_fields']);

        register_activation_hook(__FILE__, [$this, 'activate']);
    }

    public function activate() {
        // Set default options
        if (!get_option('elementor_sms_otp_sender')) {
            update_option('elementor_sms_otp_sender', 'SMSOFFICE');
        }
        if (!get_option('elementor_sms_otp_code_expiry')) {
            update_option('elementor_sms_otp_code_expiry', 5);
        }
        if (!get_option('elementor_sms_otp_rate_limit')) {
            update_option('elementor_sms_otp_rate_limit', 3);
        }
        if (get_option('elementor_sms_otp_otp_only', null) === null) {
            update_option('elementor_sms_otp_otp_only', 0);
        }
        if (!get_option('elementor_sms_otp_sms_template')) {
            update_option('elementor_sms_otp_sms_template', 'Your login code is {code}');
        }
    }

    public function init() {
        load_plugin_textdomain(
            'elementor-sms-otp',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    public function add_admin_menu() {
        add_menu_page(
            __('SMS OTP Login', 'elementor-sms-otp'),
            __('SMS OTP', 'elementor-sms-otp'),
            'manage_options',
            'elementor-sms-otp',
            [new Elementor_SMS_OTP_Settings_Page($this->sms_sender, $this->logger), 'render'],
            'dashicons-smartphone',
            65
        );

        add_submenu_page(
            'elementor-sms-otp',
            __('SMS Logs', 'elementor-sms-otp'),
            __('SMS Logs', 'elementor-sms-otp'),
            'manage_options',
            'elementor-sms-otp-logs',
            [new Elementor_SMS_OTP_Logs_Page($this->logger), 'render']
        );
    }

    public function register_settings() {
        register_setting('elementor_sms_otp_settings', 'elementor_sms_otp_api_key');
        register_setting('elementor_sms_otp_settings', 'elementor_sms_otp_sender');
        register_setting('elementor_sms_otp_settings', 'elementor_sms_otp_enabled');
        register_setting('elementor_sms_otp_settings', 'elementor_sms_otp_code_expiry');
        register_setting('elementor_sms_otp_settings', 'elementor_sms_otp_rate_limit');

        // OTP-only toggle
        register_setting(
            'elementor_sms_otp_settings',
            'elementor_sms_otp_otp_only',
            [
                'type'              => 'boolean',
                'sanitize_callback' => function($value) {
                    return $value ? 1 : 0;
                },
                'default'           => 0,
            ]
        );

        // Editable text fields + SMS template
        $text_fields = [
            'text_sending',
            'text_sent',
            'text_error',
            'text_invalid_phone',
            'text_btn_login',
            'text_btn_resend',
            'text_btn_verify',
            'text_placeholder_otp',
            'text_msg_enter_username',
            'text_msg_enter_valid_otp',
            'text_msg_session_expired',
            'text_msg_verify_error',
            'text_msg_invalid_code',
            'text_verifying',
            'sms_template',
        ];

        foreach ($text_fields as $field) {
            register_setting(
                'elementor_sms_otp_settings',
                'elementor_sms_otp_' . $field,
                [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'default'           => '',
                ]
            );
        }
    }

    public function enqueue_scripts() {
        if (is_user_logged_in()) {
            return;
        }

        $otp_only    = (bool) get_option('elementor_sms_otp_otp_only', 0);
        $resend_wait = 60;

        // Build strings from options (with sensible defaults)
        $strings = [
            'sending'            => get_option('elementor_sms_otp_text_sending',       __('Sending code...', 'elementor-sms-otp')),
            'sent'               => get_option('elementor_sms_otp_text_sent',          __('Code sent! Check your phone.', 'elementor-sms-otp')),
            'error'              => get_option('elementor_sms_otp_text_error',         __('Error sending code. Please try again.', 'elementor-sms-otp')),
            'invalid_phone'      => get_option('elementor_sms_otp_text_invalid_phone', __('Please enter a valid Georgian phone number.', 'elementor-sms-otp')),
            'btn_login'          => get_option('elementor_sms_otp_text_btn_login',     __('Login with SMS Code', 'elementor-sms-otp')),
            'btn_resend'         => get_option('elementor_sms_otp_text_btn_resend',    __('Resend code', 'elementor-sms-otp')),
            'btn_verify'         => get_option('elementor_sms_otp_text_btn_verify',    __('Verify Code', 'elementor-sms-otp')),
            'placeholder_otp'    => get_option('elementor_sms_otp_text_placeholder_otp', __('Enter 6-digit code', 'elementor-sms-otp')),
            'msg_enter_username' => get_option('elementor_sms_otp_text_msg_enter_username', __('Please enter your username or email', 'elementor-sms-otp')),
            'msg_enter_valid_otp'=> get_option('elementor_sms_otp_text_msg_enter_valid_otp', __('Please enter a valid 6-digit code', 'elementor-sms-otp')),
            'msg_session_expired'=> get_option('elementor_sms_otp_text_msg_session_expired', __('Session expired. Please request a new code.', 'elementor-sms-otp')),
            'msg_verify_error'   => get_option('elementor_sms_otp_text_msg_verify_error', __('Verification failed. Please try again.', 'elementor-sms-otp')),
            'msg_invalid_code'   => get_option('elementor_sms_otp_text_msg_invalid_code', __('Invalid code', 'elementor-sms-otp')),
            'verifying'          => get_option('elementor_sms_otp_text_verifying',     __('Verifying...', 'elementor-sms-otp')),
        ];

        wp_enqueue_script(
            'elementor-sms-otp',
            ELEMENTOR_SMS_OTP_URL . 'assets/js/elementor-sms-otp.js',
            ['jquery'],
            ELEMENTOR_SMS_OTP_VERSION,
            true
        );

        wp_localize_script(
            'elementor-sms-otp',
            'elementorSmsOtp',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('elementor_sms_otp_nonce'),
                'strings'  => $strings,
                'settings' => [
                    'otp_only'    => $otp_only,
                    'resend_wait' => $resend_wait,
                ],
            ]
        );

        wp_enqueue_style(
            'elementor-sms-otp',
            ELEMENTOR_SMS_OTP_URL . 'assets/css/elementor-sms-otp.css',
            [],
            ELEMENTOR_SMS_OTP_VERSION
        );
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'elementor-sms-otp') === false) {
            return;
        }

        wp_enqueue_script(
            'elementor-sms-otp-admin',
            ELEMENTOR_SMS_OTP_URL . 'assets/js/elementor-sms-otp-admin.js',
            ['jquery'],
            ELEMENTOR_SMS_OTP_VERSION,
            true
        );

        wp_localize_script(
            'elementor-sms-otp-admin',
            'elementorSmsOtpAdmin',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('elementor_sms_otp_admin_nonce'),
            ]
        );
    }

    public function user_profile_fields($user) {
        ?>
        <h3><?php _e('SMS OTP Login', 'elementor-sms-otp'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="billing_phone"><?php _e('Phone Number (Georgian)', 'elementor-sms-otp'); ?></label></th>
                <td>
                    <input
                        type="text"
                        name="billing_phone"
                        id="billing_phone"
                        value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_phone', true)); ?>"
                        class="regular-text"
                        placeholder="5XXXXXXXX"
                    />
                    <p class="description">
                        <?php _e('Enter your Georgian mobile number (9 digits starting with 5)', 'elementor-sms-otp'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_user_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        if (isset($_POST['billing_phone'])) {
            update_user_meta(
                $user_id,
                'billing_phone',
                sanitize_text_field($_POST['billing_phone'])
            );
        }
    }
}

function elementor_sms_otp_init() {
    return Elementor_SMS_OTP_Login::get_instance();
}
add_action('plugins_loaded', 'elementor_sms_otp_init');