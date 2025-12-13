<?php
/**
 * Logs Page Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Elementor_SMS_OTP_Logs_Page {
    
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    public function render() {
        // Get logs
        $per_page = 100;
        $logs = $this->logger->get_recent_logs($per_page);
        $log_file_path = $this->logger->get_log_file_path();
        $log_size = $this->logger->get_log_file_size();
        ?>
        <div class="wrap" style="max-width: none;">
            <h1><?php echo esc_html__('SMS Logs', 'elementor-sms-otp'); ?></h1>
            
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin: 20px 0; gap: 20px;">
                <div style="flex: 1;">
                    <div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                        <p style="margin: 0 0 8px 0;">
                            <strong><?php echo esc_html__('Log File:', 'elementor-sms-otp'); ?></strong> 
                            <code style="background: #f5f5f5; padding: 3px 8px; border-radius: 3px;"><?php echo esc_html($log_file_path); ?></code>
                        </p>
                        <p style="margin: 0 0 8px 0;">
                            <strong><?php echo esc_html__('File Size:', 'elementor-sms-otp'); ?></strong> 
                            <span style="color: #2271b1; font-weight: 600;"><?php echo esc_html(size_format($log_size, 2)); ?></span>
                        </p>
                        <p style="margin: 0; color: #666;">
                            <?php echo esc_html__('Showing last', 'elementor-sms-otp'); ?> <strong><?php echo count($logs); ?></strong> <?php echo esc_html__('entries', 'elementor-sms-otp'); ?>
                        </p>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; flex-shrink: 0;">
                    <button id="export-logs" class="button button-secondary">
                        <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                        <?php echo esc_html__('Export Logs', 'elementor-sms-otp'); ?>
                    </button>
                    <button id="clear-logs" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear all logs? This action cannot be undone.', 'elementor-sms-otp')); ?>');">
                        <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
                        <?php echo esc_html__('Clear Logs', 'elementor-sms-otp'); ?>
                    </button>
                    <button id="refresh-logs" class="button button-primary">
                        <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                        <?php echo esc_html__('Refresh', 'elementor-sms-otp'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Legend -->
            <div style="margin-bottom: 15px; padding: 12px 20px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 3px;">
                <strong style="margin-right: 15px;"><?php echo esc_html__('Status Legend:', 'elementor-sms-otp'); ?></strong>
                <span style="margin-right: 15px;">
                    <span style="display: inline-block; width: 10px; height: 10px; background: #f0b849; border-radius: 50%; margin-right: 5px;"></span>
                    <strong>SENT</strong>
                </span>
                <span style="margin-right: 15px;">
                    <span style="display: inline-block; width: 10px; height: 10px; background: #00a32a; border-radius: 50%; margin-right: 5px;"></span>
                    <strong>VERIFIED</strong>
                </span>
                <span style="margin-right: 15px;">
                    <span style="display: inline-block; width: 10px; height: 10px; background: #d63638; border-radius: 50%; margin-right: 5px;"></span>
                    <strong>FAILED</strong>
                </span>
                <span>
                    <span style="display: inline-block; width: 10px; height: 10px; background: #999; border-radius: 50%; margin-right: 5px;"></span>
                    <strong>EXPIRED</strong>
                </span>
            </div>
            
            <!-- Log Viewer -->
            <div class="card" style="padding: 0; margin: 0;">
                <div style="overflow-x: auto; background: #1e1e1e; color: #d4d4d4; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 13px; line-height: 1.5; padding: 20px;">
                    <?php if (empty($logs)): ?>
                        <div style="text-align: center; padding: 60px 20px; color: #888;">
                            <span class="dashicons dashicons-info" style="font-size: 48px; width: 48px; height: 48px; margin-bottom: 15px;"></span>
                            <p style="font-size: 16px; margin: 0;"><?php echo esc_html__('No logs found', 'elementor-sms-otp'); ?></p>
                            <p style="font-size: 13px; margin: 10px 0 0 0; opacity: 0.7;"><?php echo esc_html__('Logs will appear here when SMS OTP codes are sent', 'elementor-sms-otp'); ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            // Determine status color
                            $border_color = '#666';
                            $bg_color = '#252525';
                            
                            if (strpos($log, 'VERIFIED') !== false) {
                                $border_color = '#00a32a';
                                $bg_color = '#1a2e1a';
                            } elseif (strpos($log, 'SENT') !== false) {
                                $border_color = '#f0b849';
                                $bg_color = '#2e2816';
                            } elseif (strpos($log, 'FAILED') !== false) {
                                $border_color = '#d63638';
                                $bg_color = '#2e1a1a';
                            } elseif (strpos($log, 'EXPIRED') !== false) {
                                $border_color = '#999';
                                $bg_color = '#222';
                            }
                            ?>
                            <div style="margin-bottom: 8px; padding: 10px 15px; background: <?php echo esc_attr($bg_color); ?>; border-left: 4px solid <?php echo esc_attr($border_color); ?>; border-radius: 3px; word-wrap: break-word; overflow-wrap: break-word; white-space: pre-wrap;">
                                <?php echo esc_html($log); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Log Format Info -->
            <div style="margin-top: 20px; padding: 15px 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                <h3 style="margin-top: 0; margin-bottom: 10px; color: #2271b1; font-size: 14px;">
                    <?php echo esc_html__('Log Entry Format', 'elementor-sms-otp'); ?>
                </h3>
                <p style="margin: 0; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 12px; color: #666; background: #f5f5f5; padding: 10px; border-radius: 3px;">
                    [Timestamp] User: username (ID: user_id) | Phone: phone | OTP: code | Status: STATUS | IP: ip_address | Agent: user_agent
                </p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#refresh-logs').on('click', function(e) {
                e.preventDefault();
                location.reload();
            });
        });
        </script>
        <?php
    }
}