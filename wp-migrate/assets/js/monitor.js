/**
 * WP-Migrate Real-time Monitoring JavaScript
 */
(function($) {
    'use strict';

    let wpMigrateMonitor = {
        currentJobId: null,
        pollingInterval: null,
        lastUpdateTimestamp: 0,
        isConnected: false,

        init: function() {
            this.bindEvents();
            this.updateConnectionStatus(false);
        },

        bindEvents: function() {
            // Monitor job button clicks
            $(document).on('click', '.monitor-job-btn', this.startMonitoring.bind(this));

            // Stop monitoring button
            $(document).on('click', '.stop-monitoring-btn', this.stopMonitoring.bind(this));

            // Emergency action buttons
            $(document).on('click', '.emergency-stop-btn', this.emergencyStop.bind(this));
            $(document).on('click', '.emergency-rollback-btn', this.emergencyRollback.bind(this));
        },

        startMonitoring: function(e) {
            e.preventDefault();
            const jobId = $(e.target).data('job-id');

            if (!jobId) {
                this.showError('Job ID not found');
                return;
            }

            this.currentJobId = jobId;
            this.showMonitorInterface(jobId);
            this.startPolling();
        },

        stopMonitoring: function(e) {
            e.preventDefault();
            this.stopPolling();
            this.hideMonitorInterface();
        },

        showMonitorInterface: function(jobId) {
            $('.wp-migrate-active-jobs').hide();
            $('.wp-migrate-job-monitor').show();
            $('.monitored-job-id').text(jobId);

            // Enable emergency buttons
            $('.emergency-stop-btn, .emergency-rollback-btn').prop('disabled', false);
        },

        hideMonitorInterface: function() {
            $('.wp-migrate-job-monitor').hide();
            $('.wp-migrate-active-jobs').show();
            $('.monitored-job-id').text('');

            // Clear current job
            this.currentJobId = null;

            // Disable emergency buttons
            $('.emergency-stop-btn, .emergency-rollback-btn').prop('disabled', true);
            
            // Clean up any pending operations
            this.cleanup();
        },

        cleanup: function() {
            // Stop polling
            this.stopPolling();
            
            // Clear any pending timeouts
            if (this.refreshTimeout) {
                clearTimeout(this.refreshTimeout);
                this.refreshTimeout = null;
            }
            
            // Clear any pending AJAX requests
            if (this.currentAjaxRequest) {
                this.currentAjaxRequest.abort();
                this.currentAjaxRequest = null;
            }
            
            // Reset connection status
            this.updateConnectionStatus(false);
        },

        startPolling: function() {
            // Clear any existing polling
            this.stopPolling();

            // Start polling immediately
            this.pollForUpdates();

            // Set up interval polling (every 3 seconds)
            this.pollingInterval = setInterval(this.pollForUpdates.bind(this), 3000);
        },

        stopPolling: function() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
            }
            this.updateConnectionStatus(false);
        },

        pollForUpdates: function() {
            if (!this.currentJobId) {
                return;
            }

            $.ajax({
                url: wpMigrateMonitor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_migrate_monitor_job',
                    job_id: this.currentJobId,
                    since: this.lastUpdateTimestamp,
                    nonce: wpMigrateMonitor.nonce
                },
                success: this.handleUpdateSuccess.bind(this),
                error: this.handleUpdateError.bind(this)
            });
        },

        handleUpdateSuccess: function(response) {
            if (response.success && response.data) {
                this.updateConnectionStatus(true);
                this.updateMonitorData(response.data);
                this.lastUpdateTimestamp = response.data.timestamp || 0;
            } else {
                this.handleUpdateError();
            }
        },

        handleUpdateError: function() {
            this.updateConnectionStatus(false);
            this.showConnectionError();
        },

        updateMonitorData: function(data) {
            // Update progress
            if (data.job) {
                this.updateProgress(data.job);
            }

            // Update logs
            if (data.logs && data.logs.length > 0) {
                this.updateLogs(data.logs);
            }

            // Update retry statistics
            if (data.retry_stats) {
                this.updateRetryStats(data.retry_stats);
            }
        },

        updateProgress: function(job) {
            $('.state-value').text(job.state || '-');
            $('.progress-fill-large').css('width', (job.progress || 0) + '%');
            $('.progress-text').text((job.progress || 0) + '%');

            // Update progress bar color based on progress
            const color = this.getProgressColor(job.progress || 0);
            $('.progress-fill-large').css('background-color', color);

            // Update emergency button states based on job state
            this.updateEmergencyButtons(job);
        },

        updateLogs: function(logs) {
            $('.no-logs').hide();

            logs.forEach(function(log) {
                const logEntry = this.formatLogEntry(log);
                $('.logs-container').append(logEntry);
            }.bind(this));

            // Auto-scroll to bottom
            const logsContainer = $('.logs-container')[0];
            logsContainer.scrollTop = logsContainer.scrollHeight;
        },

        updateRetryStats: function(stats) {
            $('.total-retries').text(stats.total_retries || 0);
            $('.successful-retries').text(stats.successful_retries || 0);
            $('.failed-retries').text(stats.failed_retries || 0);
            $('.backoff-time').text(this.formatBackoffTime(stats.backoff_time_total || 0));
        },

        updateEmergencyButtons: function(job) {
            const canRollback = job.can_rollback || false;
            const isRunning = !['done', 'rolled_back', 'error'].includes(job.state);

            $('.emergency-stop-btn').prop('disabled', !isRunning);
            $('.emergency-rollback-btn').prop('disabled', !canRollback);
        },

        emergencyStop: function(e) {
            e.preventDefault();

            if (!this.currentJobId) {
                return;
            }

            if (!confirm('Are you sure you want to stop this migration? This action cannot be undone.')) {
                return;
            }

            this.performEmergencyAction('stop');
        },

        emergencyRollback: function(e) {
            e.preventDefault();

            if (!this.currentJobId) {
                return;
            }

            if (!confirm('Are you sure you want to rollback this migration? This will revert database and file changes.')) {
                return;
            }

            this.performEmergencyAction('rollback');
        },

        performEmergencyAction: function(action) {
            $.ajax({
                url: wpMigrateMonitor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_migrate_emergency_' + action,
                    job_id: this.currentJobId,
                    nonce: wpMigrateMonitor.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('Emergency action completed successfully');
                        // Refresh data after a short delay
                        setTimeout(this.pollForUpdates.bind(this), 1000);
                    } else {
                        this.showError(response.data?.message || 'Emergency action failed');
                    }
                }.bind(this),
                error: function() {
                    this.showError('Emergency action failed');
                }.bind(this)
            });
        },

        formatLogEntry: function(log) {
            const timestamp = log.ts ? new Date(log.ts).toLocaleTimeString() : '';
            const level = log.level || 'info';
            const message = log.message || '';

            let levelClass = 'log-info';
            if (level === 'error') levelClass = 'log-error';
            if (level === 'warning') levelClass = 'log-warning';

            return '<div class="log-entry ' + levelClass + '">' +
                   '<span class="log-time">[' + timestamp + ']</span> ' +
                   '<span class="log-level">[' + level.toUpperCase() + ']</span> ' +
                   '<span class="log-message">' + this.escapeHtml(message) + '</span>' +
                   '</div>';
        },

        formatBackoffTime: function(seconds) {
            if (seconds < 60) {
                return seconds + 's';
            } else if (seconds < 3600) {
                return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's';
            } else {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                return hours + 'h ' + minutes + 'm';
            }
        },

        getProgressColor: function(progress) {
            if (progress < 25) return '#dc3545'; // Red
            if (progress < 50) return '#ffc107'; // Yellow
            if (progress < 75) return '#17a2b8'; // Blue
            return '#28a745'; // Green
        },

        updateConnectionStatus: function(connected) {
            this.isConnected = connected;

            const indicator = $('.connection-indicator');
            const text = $('.connection-text');

            if (connected) {
                indicator.css('background-color', '#28a745');
                text.text(wpMigrateMonitor.strings.connected);
            } else {
                indicator.css('background-color', '#ccc');
                text.text(wpMigrateMonitor.strings.disconnected);
            }
        },

        showConnectionError: function() {
            // Could show a more prominent error message
            console.warn('Connection to monitoring endpoint failed');
        },

        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        showError: function(message) {
            this.showNotice(message, 'error');
        },

        showNotice: function(message, type) {
            // Simple notice display - could be enhanced with proper WP notices
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const notice = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';

            $('.wp-migrate-monitor-header').after(notice);

            // Auto-remove after 5 seconds
            setTimeout(function() {
                $('.notice').fadeOut();
            }, 5000);
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        wpMigrateMonitor.init();
    });

    // Also expose globally for debugging
    window.wpMigrateMonitor = wpMigrateMonitor;

})(jQuery);
