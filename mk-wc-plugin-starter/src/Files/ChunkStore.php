<?php
namespace MK\WcPluginStarter\Files;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ChunkStore {
	private const MAX_CHUNK_SIZE = 64 * 1024 * 1024; // 64MB
	private string $baseDir;

	public function __construct() {
		$upload = \wp_upload_dir();
		$this->baseDir = rtrim( (string) $upload['basedir'], '/' ) . '/mk-migrate-jobs';
		if ( ! \file_exists( $this->baseDir ) ) {
			\wp_mkdir_p( $this->baseDir );
		}
	}

	public function get_job_dir( string $jobId ): string {
		$dir = $this->baseDir . '/' . \sanitize_file_name( $jobId );
		if ( ! \file_exists( $dir ) ) {
			\wp_mkdir_p( $dir );
		}
		return $dir;
	}

	public function get_chunk_dir( string $jobId ): string {
		$dir = $this->get_job_dir( $jobId ) . '/chunks';
		if ( ! \file_exists( $dir ) ) {
			\wp_mkdir_p( $dir );
		}
		return $dir;
	}

	public function chunk_path( string $jobId, string $artifact, int $index ): string {
		$fname = \sanitize_file_name( $artifact ) . '.' . $index;
		return $this->get_chunk_dir( $jobId ) . '/' . $fname;
	}

	/** @return array<int,int> */
	public function list_present( string $jobId, string $artifact ): array {
		$dir = $this->get_chunk_dir( $jobId );
		$prefix = \sanitize_file_name( $artifact ) . '.';
		$present = [];
		$dh = @\opendir( $dir );
		if ( $dh ) {
			while ( false !== ( $f = readdir( $dh ) ) ) {
				if ( strpos( $f, $prefix ) === 0 ) {
					$idx = (int) substr( $f, strlen( $prefix ) );
					$present[] = $idx;
				}
			}
			\closedir( $dh );
		}
		sort( $present );
		return $present;
	}

	public function save_chunk( string $jobId, string $artifact, int $index, string $rawBytes, string $sha256B64 ): void {
		// Validate chunk size
		if ( strlen( $rawBytes ) > self::MAX_CHUNK_SIZE ) {
			throw new \InvalidArgumentException( 'Chunk exceeds maximum size limit' );
		}

		// Validate path components to prevent directory traversal
		if ( strpos( $jobId, '..' ) !== false || strpos( $artifact, '..' ) !== false ) {
			throw new \InvalidArgumentException( 'Invalid path component detected' );
		}

		// Validate hash
		$calc = base64_encode( \hash( 'sha256', $rawBytes, true ) );
		if ( ! \hash_equals( $calc, $sha256B64 ) ) {
			throw new \RuntimeException( 'Chunk hash mismatch' );
		}

		$path = $this->chunk_path( $jobId, $artifact, $index );
		\file_put_contents( $path, $rawBytes );
	}
}


