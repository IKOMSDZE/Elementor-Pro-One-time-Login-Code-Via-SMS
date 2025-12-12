<?php
/**
 * Database Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Elementor_SMS_OTP_Database {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'elementor_sms_otp_logs';
    }
    
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            username varchar(255) NOT NULL,
            phone varchar(20) NOT NULL,
            otp_code varchar(10) NOT NULL,
            status varchar(20) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function log_sms($user_id, $username, $phone, $otp_code, $status = 'sent') {
        global $wpdb;
        
        $wpdb->insert(
            $this->table_name,
            [
                'user_id' => $user_id,
                'username' => $username,
                'phone' => $phone,
                'otp_code' => $otp_code,
                'status' => $status,
                'ip_address' => $this->get_user_ip(),
                'user_agent' => $this->get_user_agent(),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    public function update_log_status($log_id, $status) {
        global $wpdb;
        
        return $wpdb->update(
            $this->table_name,
            ['status' => $status],
            ['id' => $log_id],
            ['%s'],
            ['%d']
        );
    }
    
    public function get_logs($args = []) {
        global $wpdb;
        
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'date' => '',
            'user_id' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $where = "WHERE 1=1";
        
        if ($args['status']) {
            $where .= $wpdb->prepare(" AND status = %s", $args['status']);
        }
        
        if ($args['date']) {
            $where .= $wpdb->prepare(" AND DATE(created_at) = %s", $args['date']);
        }
        
        if ($args['user_id']) {
            $where .= $wpdb->prepare(" AND user_id = %d", $args['user_id']);
        }
        
        $order_by = sanitize_sql_orderby($args['order_by'] . ' ' . $args['order']);
        
        $query = "SELECT * FROM {$this->table_name} {$where} ORDER BY {$order_by} LIMIT {$args['per_page']} OFFSET {$offset}";
        
        return $wpdb->get_results($query);
    }
    
    public function get_total_logs($args = []) {
        global $wpdb;
        
        $where = "WHERE 1=1";
        
        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(" AND status = %s", $args['status']);
        }
        
        if (!empty($args['date'])) {
            $where .= $wpdb->prepare(" AND DATE(created_at) = %s", $args['date']);
        }
        
        if (!empty($args['user_id'])) {
            $where .= $wpdb->prepare(" AND user_id = %d", $args['user_id']);
        }
        
        return $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} {$where}");
    }
    
    public function get_statistics() {
        global $wpdb;
        
        $stats = [
            'total_sent' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status IN ('sent', 'verified')"),
            'successful_logins' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'verified'"),
            'failed_attempts' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'"),
            'users_with_phone' => $wpdb->get_var(
                "SELECT COUNT(DISTINCT user_id) 
                FROM {$wpdb->usermeta} 
                WHERE meta_key = 'billing_phone' 
                AND meta_value != ''"
            )
        ];
        
        return $stats;
    }
    
    public function clear_logs() {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }
    
    public function export_logs_csv($args = []) {
        global $wpdb;
        
        $where = "WHERE 1=1";
        
        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(" AND status = %s", $args['status']);
        }
        
        if (!empty($args['date'])) {
            $where .= $wpdb->prepare(" AND DATE(created_at) = %s", $args['date']);
        }
        
        $logs = $wpdb->get_results("SELECT * FROM {$this->table_name} {$where} ORDER BY created_at DESC", ARRAY_A);
        
        return $logs;
    }
    
    private function get_user_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    private function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    }
}