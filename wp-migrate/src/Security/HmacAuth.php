<?php
namespace WpMigrate\Security;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use \WP_Error;
use \WP_REST_Request;

class HmacAuth {
	/**
	 * Header constants for HMAC authentication
	 * Note: WordPress normalizes HTTP headers by replacing hyphens with underscores
	 * So 'X-MIG-Timestamp' becomes 'x_mig_timestamp' in the headers array
	 */
	const HDR_TS = 'x_mig_timestamp';
	const HDR_NONCE = 'x_mig_nonce';
	const HDR_PEER = 'x_mig_peer';
	const HDR_SIG = 'x_mig_signature';
	const MAX_SKEW_MS = 5 * 60 * 1000; // 5 minutes as per specification
	const NONCE_TTL = 3600; // seconds

	/**
	 * @var callable():array{shared_key:string,peer_url:string}
	 */
	private $settingsProvider;

	public function __construct( callable $settingsProvider ) {
		$this->settingsProvider = $settingsProvider;
	}

	/**
	 * Verify HMAC headers per contract. Returns array of normalized headers on success, WP_Error on failure.
	 */
	public function verify_request( WP_REST_Request $request ) {
		$settings = (array) \call_user_func( $this->settingsProvider );
		$key = isset( $settings['shared_key'] ) ? (string) $settings['shared_key'] : '';
		$expectedPeer = isset( $settings['peer_url'] ) ? (string) $settings['peer_url'] : '';

		if ( empty( $key ) ) {
			return new WP_Error( 'EAUTH', 'Shared key is not configured', [ 'status' => 401 ] );
		}

		if ( ! $this->is_secure_request() ) {
			return new WP_Error( 'EUPGRADE_REQUIRED', 'TLS required', [ 'status' => 426 ] );
		}

		$headers = $this->lowercase_headers( $request->get_headers() );
		$tsStr = $this->extract_header_value( $headers, self::HDR_TS );
		$nonce = $this->extract_header_value( $headers, self::HDR_NONCE );
		$peer = $this->extract_header_value( $headers, self::HDR_PEER );
		$sig = $this->extract_header_value( $headers, self::HDR_SIG );

		if ( $tsStr === '' || $nonce === '' || $sig === '' ) {
			return new WP_Error( 'EAUTH', 'Missing auth headers', [ 'status' => 401 ] );
		}

		// Time skew validation
		$nowMs = (int) \round( microtime( true ) * 1000 );
		$ts = (int) $tsStr;
		if ( \abs( $nowMs - $ts ) > self::MAX_SKEW_MS ) {
			return new WP_Error( 'ETS_SKEW', 'Timestamp skew too large', [ 'status' => 401 ] );
		}

		// Nonce replay check (1h window)
		if ( $this->is_nonce_used( $nonce ) ) {
			return new WP_Error( 'ENONCE_REPLAY', 'Nonce has already been used', [ 'status' => 401 ] );
		}

		// Peer URL check if configured
		if ( $expectedPeer !== '' && $peer !== '' ) {
			$normExpected = rtrim( $expectedPeer, '/' );
			$normPeer = rtrim( $peer, '/' );
			if ( \strcasecmp( $normExpected, $normPeer ) !== 0 ) {
				return new WP_Error( 'EAUTH', 'Peer mismatch', [ 'status' => 401 ] );
			}
		}

		$body = (string) $request->get_body();
		$bodyHash = \hash( 'sha256', $body );
		$method = strtoupper( (string) $request->get_method() );
		$path = $this->normalize_path( $request );
		$payload = $ts . "\n" . $nonce . "\n" . $method . "\n" . $path . "\n" . $bodyHash;
		$calc = base64_encode( \hash_hmac( 'sha256', $payload, $key, true ) );
		if ( ! \hash_equals( $calc, $sig ) ) {
			return new WP_Error( 'EAUTH', 'Invalid signature', [ 'status' => 401 ] );
		}

		$this->mark_nonce_used( $nonce );
		return [ 'ts' => $ts, 'nonce' => $nonce, 'peer' => $peer, 'path' => $path ];
	}

	private function normalize_path( WP_REST_Request $request ): string {
		// Use the REST route and prefix with /wp-json
		$route = $request->get_route(); // like /migrate/v1/handshake
		$path = '/wp-json' . $route;
		return $path;
	}

	/** @param array<string,mixed> $headers */
	private function lowercase_headers( array $headers ): array {
		$out = [];
		foreach ( $headers as $k => $v ) {
			// WordPress normalizes header names by replacing hyphens with underscores
			// So 'X-MIG-Timestamp' becomes 'x_mig_timestamp' in the headers array
			$normalizedKey = strtolower( (string) $k );
			$out[ $normalizedKey ] = $v;
		}
		return $out;
	}

	private function extract_header_value( array $headers, string $key ): string {
		$value = $headers[ $key ] ?? '';
		if ( is_array( $value ) ) {
			return $value[0] ?? '';
		}
		return (string) $value;
	}

	private function is_nonce_used( string $nonce ): bool {
		$key = 'wp_migrate_nonce_' . md5( $nonce );
		return (bool) \get_transient( $key );
	}

	private function mark_nonce_used( string $nonce ): void {
		$key = 'wp_migrate_nonce_' . md5( $nonce );
		\set_transient( $key, 1, self::NONCE_TTL );
	}

	/**
	 * Check if request is secure (HTTPS), including proxy scenarios.
	 */
	private function is_secure_request(): bool {
		if ( \is_ssl() ) {
			return true;
		}

		// Check common proxy headers
		$proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
		$https = $_SERVER['HTTP_X_FORWARDED_SSL'] ?? $_SERVER['HTTP_FRONT_END_HTTPS'] ?? '';
		
		return $proto === 'https' || $https === 'on';
	}
}


