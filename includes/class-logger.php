<?php
/**
 * File Logger Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Elementor_SMS_OTP_Logger {
    
    private $log_file;
    private $logs_dir;
    
    public function __construct() {
        $this->logs_dir = ELEMENTOR_SMS_OTP_PATH . 'logs';
        $this->log_file = $this->logs_dir . '/sms.log';
        
        // Create logs directory if it doesn't exist
        if (!file_exists($this->logs_dir)) {
            wp_mkdir_p($this->logs_dir);
            
            // Add .htaccess to protect logs directory
            $htaccess = $this->logs_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }
            
            // Add index.php to prevent directory listing
            $index = $this->logs_dir . '/index.php';
            if (!file_exists($index)) {
                file_put_contents($index, "<?php\n// Silence is golden.\n");
            }
        }
    }
    
    /**
     * Log SMS activity to file
     */
    public function log_sms($user_id, $username, $phone, $otp_code, $status = 'sent') {
        $timestamp = current_time('Y-m-d H:i:s');
        $ip = $this->get_user_ip();
        $user_agent = $this->get_user_agent();
        
        $log_entry = sprintf(
            "[%s] User: %s (ID: %d) | Phone: %s | OTP: %s | Status: %s | IP: %s | Agent: %s\n",
            $timestamp,
            $username,
            $user_id,
            $phone,
            $otp_code,
            strtoupper($status),
            $ip,
            $user_agent
        );
        
        // Append to log file
        error_log($log_entry, 3, $this->log_file);
        
        return true;
    }
    
    /**
     * Get recent logs (last N lines)
     */
    public function get_recent_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $file = new SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $last_line = $file->key();
        
        $logs = [];
        $start = max(0, $last_line - $lines);
        
        $file->seek($start);
        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $logs[] = $line;
            }
            $file->next();
        }
        
        return array_reverse($logs);
    }
    
    /**
     * Clear all logs
     */
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            return unlink($this->log_file);
        }
        return true;
    }
    
    /**
     * Get log file path
     */
    public function get_log_file_path() {
        return $this->log_file;
    }
    
    /**
     * Get log file size
     */
    public function get_log_file_size() {
        if (file_exists($this->log_file)) {
            return filesize($this->log_file);
        }
        return 0;
    }
    
    /**
     * Export logs
     */
    public function export_logs() {
        if (!file_exists($this->log_file)) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $filename = 'sms-otp-logs-' . date('Y-m-d-H-i-s') . '.txt';
        $filepath = trailingslashit($upload_dir['path']) . $filename;
        
        if (copy($this->log_file, $filepath)) {
            return trailingslashit($upload_dir['url']) . $filename;
        }
        
        return false;
    }
    
    private function get_user_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        return sanitize_text_field($ip);
    }
    
    private function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : 'Unknown';
    }
}