<?php
namespace WpMigrate\Security;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use \WP_Error;
use \WP_REST_Request;

class HmacAuth {
	const HDR_TS = 'x-mig-timestamp';
	const HDR_NONCE = 'x-mig-nonce';
	const HDR_PEER = 'x-mig-peer';
	const HDR_SIG = 'x-mig-signature';
	const MAX_SKEW_MS = 60 * 60 * 1000; // 60 minutes for testing flexibility
	const NONCE_TTL = 3600; // seconds

	/**
	 * @var callable():array{shared_key:string,peer_url:string}
	 */
	private $settingsProvider;

	public function __construct( callable $settingsProvider ) {
		$this->settingsProvider = $settingsProvider;
	}

	/**
	 * Return non-sensitive settings for diagnostics (staging only usage).
	 * @return array{peer_url:string,key_fingerprint:string}
	 */
	public function get_masked_settings(): array {
		$settings = (array) \call_user_func( $this->settingsProvider );
		$key = isset( $settings['shared_key'] ) ? (string) $settings['shared_key'] : '';
		$peer = isset( $settings['peer_url'] ) ? (string) $settings['peer_url'] : '';
		$fingerprint = $key !== '' ? substr( hash( 'sha256', $key ), 0, 12 ) : '';
		return [ 'peer_url' => $peer, 'key_fingerprint' => $fingerprint ];
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
		$tsStr = $this->get_header_value( $headers, self::HDR_TS );
		$nonce = $this->get_header_value( $headers, self::HDR_NONCE );
		$peer  = $this->get_header_value( $headers, self::HDR_PEER );
		$sig   = $this->get_header_value( $headers, self::HDR_SIG );

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
		// Use the REST route and prefix with /wp-json; include raw query string if present
		$route = $request->get_route(); // like /migrate/v1/handshake
		$path = '/wp-json' . $route;

		$requestUri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( $requestUri !== '' ) {
			$query = (string) ( parse_url( $requestUri, PHP_URL_QUERY ) ?? '' );
			if ( $query !== '' ) {
				$path .= '?' . $query;
			}
		}

		return $path;
	}

	/** @param array<string,mixed> $headers */
	private function lowercase_headers( array $headers ): array {
		$out = [];
		foreach ( $headers as $k => $v ) {
			$out[ strtolower( (string) $k ) ] = $v;
		}
		return $out;
	}

	/**
	 * Get a header value supporting both dash and underscore naming produced by WP REST.
	 * @param array<string,mixed> $headers
	 */
	private function get_header_value( array $headers, string $name ): string {
		$lower = strtolower( $name ); // e.g. x-mig-timestamp
		$underscore = str_replace( '-', '_', $lower ); // x_mig_timestamp
		$val = '';
		if ( isset( $headers[ $lower ][0] ) && is_string( $headers[ $lower ][0] ) ) {
			$val = (string) $headers[ $lower ][0 ];
		} elseif ( isset( $headers[ $underscore ][0] ) && is_string( $headers[ $underscore ][0] ) ) {
			$val = (string) $headers[ $underscore ][0 ];
		}
		return $val;
	}

	private function is_nonce_used( string $nonce ): bool {
		$key = 'mk_mig_nonce_' . md5( $nonce );
		return (bool) \get_transient( $key );
	}

	private function mark_nonce_used( string $nonce ): void {
		$key = 'mk_mig_nonce_' . md5( $nonce );
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


