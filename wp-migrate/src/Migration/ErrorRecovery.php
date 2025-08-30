<?php
namespace WpMigrate\Migration;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use WpMigrate\Logging\JsonLogger;

final class ErrorRecovery {
    private const MAX_RETRIES = 3;
    private const BASE_BACKOFF_SECONDS = 30; // 30 seconds
    private const MAX_BACKOFF_SECONDS = 900; // 15 minutes

    /**
     * Error patterns that are recoverable
     */
    private const RECOVERABLE_ERRORS = [
        'timeout',
        'connection',
        'temporary',
        'lock_wait',
        'deadlock',
        'network',
        'chunk_upload_failed',
        'database_connection_lost'
    ];

    /**
     * Check if an error is recoverable
     */
    public function is_recoverable_error( string $errorMessage, array $context = [] ): bool {
        $errorLower = strtolower( $errorMessage );

        foreach ( self::RECOVERABLE_ERRORS as $pattern ) {
            if ( strpos( $errorLower, $pattern ) !== false ) {
                return true;
            }
        }

        // Check context for recoverable conditions
        if ( isset( $context['http_code'] ) && in_array( $context['http_code'], [408, 429, 500, 502, 503, 504], true ) ) {
            return true;
        }

        if ( isset( $context['db_error_code'] ) && in_array( $context['db_error_code'], ['1213', '1205'], true ) ) {
            return true; // Deadlock and lock wait timeout
        }

        return false;
    }

    /**
     * Execute operation with retry logic
     *
     * @param callable $operation Function to retry
     * @param string $jobId Job identifier for logging
     * @param string $operationName Name of the operation for logging
     * @return mixed Result of the operation
     * @throws \Throwable Last exception if all retries fail
     */
    public function execute_with_retry( callable $operation, string $jobId, string $operationName ) {
        $logger = new JsonLogger( $jobId );
        $attempt = 0;
        $lastException = null;

        while ( $attempt < self::MAX_RETRIES ) {
            try {
                $logger->log( 'retry_attempt', 'info', "Attempting {$operationName} (attempt " . ($attempt + 1) . ")", [
                    'attempt' => $attempt + 1,
                    'max_attempts' => self::MAX_RETRIES
                ]);

                $result = $operation();

                if ( $attempt > 0 ) {
                    $logger->log( 'retry_success', 'info', "{$operationName} succeeded on retry", [
                        'attempt' => $attempt + 1,
                        'total_attempts' => $attempt + 1
                    ]);
                }

                return $result;

            } catch ( \Throwable $e ) {
                $lastException = $e;
                $attempt++;

                $isRecoverable = $this->is_recoverable_error( $e->getMessage(), [
                    'exception_type' => get_class( $e ),
                    'http_code' => $e->getCode() ?? null
                ]);

                $logger->log( 'retry_error', $isRecoverable ? 'warning' : 'error', "Error in {$operationName}: " . $e->getMessage(), [
                    'attempt' => $attempt,
                    'max_attempts' => self::MAX_RETRIES,
                    'is_recoverable' => $isRecoverable,
                    'error_type' => get_class( $e ),
                    'error_code' => $e->getCode()
                ]);

                if ( ! $isRecoverable || $attempt >= self::MAX_RETRIES ) {
                    break;
                }

                // Calculate backoff delay with exponential backoff
                $backoffSeconds = $this->calculate_backoff_delay( $attempt );

                $logger->log( 'retry_backoff', 'info', "Waiting {$backoffSeconds} seconds before retry", [
                    'backoff_seconds' => $backoffSeconds,
                    'next_attempt' => $attempt + 1
                ]);

                sleep( $backoffSeconds );
            }
        }

        // All retries exhausted
        $logger->log( 'retry_exhausted', 'error', "{$operationName} failed after " . self::MAX_RETRIES . " attempts", [
            'total_attempts' => self::MAX_RETRIES,
            'final_error' => $lastException ? $lastException->getMessage() : 'Unknown error'
        ]);

        throw $lastException;
    }

    /**
     * Calculate backoff delay using exponential backoff with jitter
     */
    protected function calculate_backoff_delay( int $attempt ): int {
        $exponentialDelay = self::BASE_BACKOFF_SECONDS * (2 ** ($attempt - 1));

        // Add jitter to prevent thundering herd
        $jitter = random_int( 0, (int) ($exponentialDelay * 0.1) );

        $delay = $exponentialDelay + $jitter;

        return min( $delay, self::MAX_BACKOFF_SECONDS );
    }

    /**
     * Get retry statistics for a job
     */
    public function get_retry_stats( string $jobId ): array {
        $logger = new JsonLogger( $jobId );
        $logs = $logger->tail( 1000 ); // Get recent logs

        $stats = [
            'total_retries' => 0,
            'successful_retries' => 0,
            'failed_retries' => 0,
            'backoff_time_total' => 0,
            'error_types' => []
        ];

        foreach ( $logs as $log ) {
            if ( isset( $log['phase'] ) && $log['phase'] === 'retry_attempt' ) {
                $stats['total_retries']++;
            }

            if ( isset( $log['phase'] ) && $log['phase'] === 'retry_success' ) {
                $stats['successful_retries']++;
            }

            if ( isset( $log['phase'] ) && $log['phase'] === 'retry_exhausted' ) {
                $stats['failed_retries']++;
            }

            if ( isset( $log['phase'] ) && $log['phase'] === 'retry_backoff' ) {
                $stats['backoff_time_total'] += $log['backoff_seconds'] ?? 0;
            }

            if ( isset( $log['phase'] ) && $log['phase'] === 'retry_error' ) {
                $errorType = $log['error_type'] ?? 'unknown';
                if ( ! isset( $stats['error_types'][$errorType] ) ) {
                    $stats['error_types'][$errorType] = 0;
                }
                $stats['error_types'][$errorType]++;
            }
        }

        return $stats;
    }

    /**
     * Check if a job should be retried based on its error history
     */
    public function should_retry_job( string $jobId, array $job ): bool {
        if ( ! isset( $job['errors'] ) || empty( $job['errors'] ) ) {
            return false;
        }

        $recentErrors = array_slice( $job['errors'], -5 ); // Last 5 errors
        $recoverableCount = 0;

        foreach ( $recentErrors as $error ) {
            if ( $this->is_recoverable_error( $error['message'] ?? '', $error['context'] ?? [] ) ) {
                $recoverableCount++;
            }
        }

        // Retry if at least 60% of recent errors are recoverable
        return ( $recoverableCount / count( $recentErrors ) ) >= 0.6;
    }

    /**
     * Schedule a job retry with appropriate delay
     */
    public function schedule_retry( string $jobId, array $job ): int {
        $errorCount = count( $job['errors'] ?? [] );
        $delay = $this->calculate_backoff_delay( min( $errorCount, self::MAX_RETRIES ) );

        $logger = new JsonLogger( $jobId );
        $logger->log( 'retry_scheduled', 'info', "Scheduling job retry in {$delay} seconds", [
            'delay_seconds' => $delay,
            'error_count' => $errorCount
        ]);

        return $delay;
    }
}
