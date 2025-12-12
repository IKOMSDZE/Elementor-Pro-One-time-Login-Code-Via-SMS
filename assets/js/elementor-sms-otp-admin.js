jQuery(document).ready(function($) {
    'use strict';
    
    // Export Logs
    $('#export-logs').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Exporting...');
        
        // Get current filter values
        const status = $('select[name="status"]').val() || '';
        const date = $('input[name="date"]').val() || '';
        
        $.ajax({
            url: elementorSmsOtpAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'export_sms_logs',
                nonce: elementorSmsOtpAdmin.nonce,
                status: status,
                date: date
            },
            success: function(response) {
                if (response.success) {
                    // Create a temporary link and trigger download
                    const link = document.createElement('a');
                    link.href = response.data.file_url;
                    link.download = '';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    alert(response.data.message);
                } else {
                    alert(response.data.message || 'Export failed');
                }
            },
            error: function() {
                alert('Export failed. Please try again.');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Clear Logs
    $('#clear-logs').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to clear all logs? This action cannot be undone.')) {
            return;
        }
        
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Clearing...');
        
        $.ajax({
            url: elementorSmsOtpAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'clear_sms_logs',
                nonce: elementorSmsOtpAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || 'Clear failed');
                }
            },
            error: function() {
                alert('Clear failed. Please try again.');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Auto-refresh statistics every 30 seconds on settings page
    if ($('.stat-number').length > 0) {
        setInterval(function() {
            location.reload();
        }, 30000);
    }
});