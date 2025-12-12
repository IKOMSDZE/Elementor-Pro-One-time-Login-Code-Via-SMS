<?php
/**
 * Logs Page Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Elementor_SMS_OTP_Logs_Page {
    
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function render() {
        // Pagination
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Filters
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $date_filter = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        
        $args = [
            'per_page' => $per_page,
            'page' => $page,
            'status' => $status_filter,
            'date' => $date_filter
        ];
        
        // Get logs
        $total = $this->db->get_total_logs($args);
        $logs = $this->db->get_logs($args);
        
        $total_pages = ceil($total / $per_page);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('SMS Logs', 'elementor-sms-otp'); ?></h1>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0;">
                <form method="get" action="" style="display: flex; gap: 10px;">
                    <input type="hidden" name="page" value="elementor-sms-otp-logs" />
                    
                    <select name="status" style="padding: 5px;">
                        <option value=""><?php echo esc_html__('All Statuses', 'elementor-sms-otp'); ?></option>
                        <option value="sent" <?php selected($status_filter, 'sent'); ?>><?php echo esc_html__('Sent', 'elementor-sms-otp'); ?></option>
                        <option value="verified" <?php selected($status_filter, 'verified'); ?>><?php echo esc_html__('Verified', 'elementor-sms-otp'); ?></option>
                        <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php echo esc_html__('Failed', 'elementor-sms-otp'); ?></option>
                        <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php echo esc_html__('Expired', 'elementor-sms-otp'); ?></option>
                    </select>
                    
                    <input type="date" name="date" value="<?php echo esc_attr($date_filter); ?>" style="padding: 5px;" />
                    
                    <button type="submit" class="button"><?php echo esc_html__('Filter', 'elementor-sms-otp'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=elementor-sms-otp-logs'); ?>" class="button"><?php echo esc_html__('Reset', 'elementor-sms-otp'); ?></a>
                </form>
                
                <div style="display: flex; gap: 10px;">
                    <button id="export-logs" class="button button-secondary"><?php echo esc_html__('Export CSV', 'elementor-sms-otp'); ?></button>
                    <button id="clear-logs" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear all logs?', 'elementor-sms-otp')); ?>');"><?php echo esc_html__('Clear Logs', 'elementor-sms-otp'); ?></button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;"><?php echo esc_html__('ID', 'elementor-sms-otp'); ?></th>
                        <th><?php echo esc_html__('User', 'elementor-sms-otp'); ?></th>
                        <th><?php echo esc_html__('Phone', 'elementor-sms-otp'); ?></th>
                        <th style="width: 100px;"><?php echo esc_html__('Code', 'elementor-sms-otp'); ?></th>
                        <th style="width: 100px;"><?php echo esc_html__('Status', 'elementor-sms-otp'); ?></th>
                        <th><?php echo esc_html__('IP Address', 'elementor-sms-otp'); ?></th>
                        <th><?php echo esc_html__('Date', 'elementor-sms-otp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <?php echo esc_html__('No logs found', 'elementor-sms-otp'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->id); ?></td>
                                <td>
                                    <strong><?php echo esc_html($log->username); ?></strong><br>
                                    <small style="color: #666;">ID: <?php echo esc_html($log->user_id); ?></small>
                                </td>
                                <td><?php echo esc_html($log->phone); ?></td>
                                <td><code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($log->otp_code); ?></code></td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'sent' => '#f0b849',
                                        'verified' => '#00a32a',
                                        'failed' => '#d63638',
                                        'expired' => '#999'
                                    ];
                                    $color = $status_colors[$log->status] ?? '#666';
                                    ?>
                                    <span style="color: <?php echo esc_attr($color); ?>; font-weight: bold;">
                                        ● <?php echo esc_html(ucfirst($log->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log->ip_address); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $page
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}