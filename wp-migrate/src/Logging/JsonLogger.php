<?php
namespace WpMigrate\Logging;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class JsonLogger {
	private string $jobId;
	private string $filePath;

	public function __construct( string $jobId ) {
		$this->jobId = $jobId;
		$upload = \wp_upload_dir();
		$dir = rtrim( (string) $upload['basedir'], '/' ) . '/mk-migrate-logs';
		if ( ! \file_exists( $dir ) ) {
			\wp_mkdir_p( $dir );
		}
		$this->filePath = $dir . '/' . \sanitize_file_name( $jobId ) . '.jsonl';
	}

	/** @param array<string,mixed> $fields */
	public function log( string $step, string $level, string $message, array $fields = [] ): void {
		$entry = [
			'ts' => gmdate( 'c' ),
			'job_id' => $this->jobId,
			'step' => $step,
			'level' => $level,
			'msg' => $this->redact( $message ),
		] + $this->redact_array( $fields );
		\file_put_contents( $this->filePath, json_encode( $entry ) . "\n", FILE_APPEND );
	}

	public function tail( int $n ): array {
		if ( ! \file_exists( $this->filePath ) ) return [];
		$lines = @\file( $this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ?: [];
		return array_slice( $lines, -$n );
	}

	private function redact( string $s ): string {
		// Remove obvious secrets
		$s = preg_replace( '/[A-Za-z0-9]{24,}/', '***', $s );
		return (string) $s;
	}

	/** @param array<string,mixed> $a */
	private function redact_array( array $a ): array {
		foreach ( $a as $k => $v ) {
			if ( is_string( $v ) ) {
				$a[$k] = $this->redact( $v );
			}
		}
		return $a;
	}
}


