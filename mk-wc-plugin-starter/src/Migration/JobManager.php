<?php
namespace WpMigrate\Migration;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use WpMigrate\State\StateStore;
use WpMigrate\Logging\JsonLogger;

final class JobManager {
	private StateStore $store;

	/**
	 * Valid state transitions for the migration workflow
	 */
	private const VALID_TRANSITIONS = [
		'created'       => ['preflight_ok'],
		'preflight_ok'  => ['files_pass1', 'error'],
		'files_pass1'   => ['db_exported', 'error'],
		'db_exported'   => ['db_uploaded', 'error'],
		'db_uploaded'   => ['db_imported', 'error'],
		'db_imported'   => ['url_replaced', 'error'],
		'url_replaced'  => ['files_pass2', 'error'],
		'files_pass2'   => ['finalized', 'error'],
		'finalized'     => ['done', 'error'],
		'error'         => ['rollback', 'created'], // Allow recovery from error
		'rollback'      => ['rolled_back'],
		'rolled_back'   => ['created'], // Allow restart after rollback
		'done'          => [], // Terminal state
	];

	public function __construct( StateStore $store ) {
		$this->store = $store;
	}

	/** @return array<string,mixed> */
	public function get_or_create( string $jobId ): array {
		$job = $this->store->get_job( $jobId );
		if ( empty( $job ) ) {
			$job = [
				'job_id' => $jobId,
				'state' => 'created',
				'steps' => [],
				'created_at' => gmdate( 'c' ),
				'updated_at' => gmdate( 'c' ),
				'progress' => 0,
				'errors' => [],
				'metadata' => []
			];
			$this->store->put_job( $jobId, $job );
		}
		return $job;
	}

	/**
	 * Set job state with validation and enhanced tracking
	 *
	 * @throws \InvalidArgumentException When state transition is invalid
	 */
	public function set_state( string $jobId, string $state, array $metadata = [] ): array {
		$job = $this->get_or_create( $jobId );
		$currentState = $job['state'] ?? 'created';

		// Validate state transition
		if ( ! $this->is_valid_transition( $currentState, $state ) ) {
			throw new \InvalidArgumentException(
				"Invalid state transition from '{$currentState}' to '{$state}'"
			);
		}

		// Update job state
		$job['state'] = $state;
		$job['updated_at'] = gmdate( 'c' );

		// Track state transition
		$job['steps'][] = [
			'from_state' => $currentState,
			'to_state' => $state,
			'timestamp' => gmdate( 'c' ),
			'metadata' => $metadata
		];

		// Update progress percentage
		$job['progress'] = $this->calculate_progress( $state );

		// Merge metadata
		if ( ! empty( $metadata ) ) {
			$job['metadata'] = array_merge( $job['metadata'] ?? [], $metadata );
		}

		$this->store->put_job( $jobId, $job );

		// Log state change
		$logger = new JsonLogger( $jobId );
		$logger->log( 'state_transition', 'info', "State changed from {$currentState} to {$state}", [
			'from' => $currentState,
			'to' => $state,
			'progress' => $job['progress']
		]);

		return $job;
	}

	/**
	 * Record an error in the job
	 */
	public function record_error( string $jobId, string $error, array $context = [] ): void {
		$job = $this->get_or_create( $jobId );

		$job['errors'][] = [
			'message' => $error,
			'context' => $context,
			'timestamp' => gmdate( 'c' ),
			'state' => $job['state'] ?? 'unknown'
		];

		$this->store->put_job( $jobId, $job );

		// Log error
		$logger = new JsonLogger( $jobId );
		$logger->log( 'error_recorded', 'error', "Job error recorded: {$error}", [
			'error' => $error,
			'context' => $context
		]);
	}

	/**
	 * Get detailed job progress with validation
	 */
	public function get_progress( string $jobId ): array {
		$job = $this->get_or_create( $jobId );

		return [
			'job_id' => $jobId,
			'state' => $job['state'] ?? 'created',
			'progress' => $job['progress'] ?? 0,
			'steps' => $job['steps'] ?? [],
			'created_at' => $job['created_at'] ?? null,
			'updated_at' => $job['updated_at'] ?? null,
			'errors' => $job['errors'] ?? [],
			'metadata' => $job['metadata'] ?? [],
			'is_valid' => $this->is_valid_state( $job['state'] ?? 'created' ),
			'can_rollback' => $this->can_rollback_from_state( $job['state'] ?? 'created' )
		];
	}

	/**
	 * Check if current state allows rollback
	 */
	public function can_rollback_from_state( string $state ): bool {
		$rollbackStates = ['db_imported', 'url_replaced', 'files_pass2', 'finalized', 'error'];
		return in_array( $state, $rollbackStates, true );
	}

	/**
	 * Validate if a state transition is allowed
	 */
	private function is_valid_transition( string $from, string $to ): bool {
		$allowed = self::VALID_TRANSITIONS[$from] ?? [];
		return in_array( $to, $allowed, true );
	}

	/**
	 * Check if a state is valid
	 */
	private function is_valid_state( string $state ): bool {
		return array_key_exists( $state, self::VALID_TRANSITIONS );
	}

	/**
	 * Calculate progress percentage based on state
	 */
	protected function calculate_progress( string $state ): int {
		$progressMap = [
			'created' => 0,
			'preflight_ok' => 10,
			'files_pass1' => 25,
			'db_exported' => 40,
			'db_uploaded' => 55,
			'db_imported' => 70,
			'url_replaced' => 85,
			'files_pass2' => 95,
			'finalized' => 98,
			'done' => 100,
			'error' => 0,
			'rollback' => 0,
			'rolled_back' => 0,
		];

		return $progressMap[$state] ?? 0;
	}

	/**
	 * Clean up old jobs (older than specified days)
	 */
	public function cleanup_old_jobs( int $daysOld = 30 ): int {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$daysOld} days" ) );
		$optionPattern = self::OPTION_PREFIX . '%';

		$oldJobs = $wpdb->get_results( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options}
			 WHERE option_name LIKE %s
			 AND option_value LIKE %s",
			$optionPattern,
			'%' . $wpdb->esc_like( $cutoff ) . '%'
		));

		$cleaned = 0;
		foreach ( $oldJobs as $job ) {
			$jobId = str_replace( self::OPTION_PREFIX, '', $job->option_name );
			$this->store->delete_job( $jobId );
			$cleaned++;
		}

		return $cleaned;
	}
}


