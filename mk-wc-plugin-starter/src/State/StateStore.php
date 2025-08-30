<?php
namespace WpMigrate\State;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class StateStore {
	private const OPTION_PREFIX = 'mig_job_';

	/** @return array<string,mixed> */
	public function get_job( string $jobId ): array {
		$raw = \get_option( self::OPTION_PREFIX . $jobId, [] );
		return is_array( $raw ) ? $raw : [];
	}

	/** @param array<string,mixed> $job */
	public function put_job( string $jobId, array $job ): void {
		\update_option( self::OPTION_PREFIX . $jobId, $job, false );
	}

	public function delete_job( string $jobId ): void {
		\delete_option( self::OPTION_PREFIX . $jobId );
	}
}


