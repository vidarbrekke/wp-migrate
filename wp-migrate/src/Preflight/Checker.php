<?php
namespace WpMigrate\Preflight;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Checker {
	/** @return array{ok:bool,errors:array<int,array{code:string,message:string}>,capabilities:array<string,bool>} */
	public function run(): array {
		$errors = [];
		$cap = [
			'rsync' => $this->binary_exists( 'rsync' ),
			'zstd'  => $this->binary_exists( 'zstd' ) || $this->binary_exists( 'pzstd' ),
			'wp_cli'=> \defined( 'WP_CLI' ) && \WP_CLI,
		];

		// TLS recommended, but server endpoints are under HTTPS assumption
		if ( ! \function_exists( 'mysqli_connect' ) ) {
			$errors[] = [ 'code' => 'EDB_PRIVS', 'message' => 'MySQLi not available' ];
		}
		global $wpdb;
		if ( isset( $wpdb->use_mysqli ) && ! $wpdb->use_mysqli ) {
			$errors[] = [ 'code' => 'EDB_PRIVS', 'message' => 'wpdb is not using MySQLi' ];
		}
		// Only MySQL supported
		$errors = $errors;
		return [ 'ok' => empty( $errors ), 'errors' => $errors, 'capabilities' => $cap ];
	}

	private function binary_exists( string $bin ): bool {
		$which = \escapeshellcmd( 'command -v ' . $bin ) . ' 2>/dev/null';
		$path = \trim( (string) \shell_exec( $which ) );
		return $path !== '' && \file_exists( $path );
	}
}


