/**
 * WP-Migrate Dry Run Modal Functionality
 * Handles the pre-flight check modal dialog and AJAX interactions
 */

jQuery(document).ready(function($) {
    'use strict';

    // Modal functionality
    function openDryRunModal() {
        $('#wp-migrate-dry-run-modal').show();
        $('#wp-migrate-dry-run-progress').show();
        $('#wp-migrate-dry-run-results').hide();
        runDryRunTest();
    }

    function closeDryRunModal() {
        $('#wp-migrate-dry-run-modal').hide();
    }

    // Event handlers
    $('#wp-migrate-dry-run-btn').on('click', function() {
        openDryRunModal();
    });

    $('.wp-migrate-modal-close').on('click', function() {
        closeDryRunModal();
    });

    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) { // ESC key
            closeDryRunModal();
        }
    });

    // Prevent modal close when clicking inside modal content
    $('.wp-migrate-modal-content').on('click', function(e) {
        e.stopPropagation();
    });

    // AJAX dry-run test
    function runDryRunTest() {
        $.ajax({
            url: wpMigrateDryRun.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_migrate_dry_run',
                nonce: wpMigrateDryRun.nonce
            },
            success: function(response) {
                $('#wp-migrate-dry-run-progress').hide();

                if (response.success) {
                    showDryRunResults(response.data);
                } else {
                    showDryRunError(response.data.message);
                }
            },
            error: function(xhr, status, error) {
                $('#wp-migrate-dry-run-progress').hide();
                showDryRunError('AJAX request failed: ' + error);
            }
        });
    }

    function showDryRunResults(data) {
        var statusDiv = $('#wp-migrate-dry-run-status');
        var detailsDiv = $('#wp-migrate-dry-run-details');

        // Set status
        var statusClass = data.status === 'success' ? 'success' : 'error';
        statusDiv.removeClass('success error').addClass(statusClass);
        statusDiv.html('<strong>' + data.message + '</strong>');

        // Build results
        var resultsHtml = '';
        if (data.results && data.results.length > 0) {
            $.each(data.results, function(index, result) {
                var resultClass = 'wp-migrate-test-result ' + result.status;
                resultsHtml += '<div class="' + resultClass + '">';
                resultsHtml += '<strong>' + result.step + ':</strong> ' + result.message;
                resultsHtml += '</div>';
            });
        }

        detailsDiv.html(resultsHtml);
        $('#wp-migrate-dry-run-results').show();
    }

    function showDryRunError(message) {
        var statusDiv = $('#wp-migrate-dry-run-status');
        var detailsDiv = $('#wp-migrate-dry-run-details');

        statusDiv.removeClass('success').addClass('error');
        statusDiv.html('<strong>❌ Error:</strong> ' + message);

        detailsDiv.html('');
        $('#wp-migrate-dry-run-results').show();
    }
});
