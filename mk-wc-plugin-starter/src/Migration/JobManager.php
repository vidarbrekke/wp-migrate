<?php
namespace MK\WcPluginStarter\Migration;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use MK\WcPluginStarter\State\StateStore;

final class JobManager {
	private StateStore $store;

	public function __construct( StateStore $store ) {
		$this->store = $store;
	}

	/** @return array<string,mixed> */
	public function get_or_create( string $jobId ): array {
		$job = $this->store->get_job( $jobId );
		if ( empty( $job ) ) {
			$job = [ 'job_id' => $jobId, 'state' => 'created', 'steps' => [] ];
			$this->store->put_job( $jobId, $job );
		}
		return $job;
	}

	/** @return array<string,mixed> */
	public function set_state( string $jobId, string $state, array $notes = [] ): array {
		$job = $this->get_or_create( $jobId );
		$job['state'] = $state;
		$job['notes'] = $notes;
		$this->store->put_job( $jobId, $job );
		return $job;
	}

	/** @return array<string,mixed> */
	public function get_progress( string $jobId ): array {
		return $this->get_or_create( $jobId );
	}
}


