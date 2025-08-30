<?php
namespace MK\WcPluginStarter\Security;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use \WP_Error;
use \WP_REST_Request;

final class HmacAuth {
	const HDR_TS = 'x-mig-timestamp';
	const HDR_NONCE = 'x-mig-nonce';
	const HDR_PEER = 'x-mig-peer';
	const HDR_SIG = 'x-mig-signature';
	const MAX_SKEW_MS = 5 * 60 * 1000; // 5 minutes
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

		if ( ! \is_ssl() ) {
			return new WP_Error( 'EUPGRADE_REQUIRED', 'TLS required', [ 'status' => 426 ] );
		}

		$headers = $this->lowercase_headers( $request->get_headers() );
		$tsStr = $headers[ self::HDR_TS ][0] ?? '';
		$nonce = $headers[ self::HDR_NONCE ][0] ?? '';
		$peer = $headers[ self::HDR_PEER ][0] ?? '';
		$sig = $headers[ self::HDR_SIG ][0] ?? '';

		if ( $tsStr === '' || $nonce === '' || $sig === '' ) {
			return new WP_Error( 'EAUTH', 'Missing auth headers', [ 'status' => 401 ] );
		}

		// Time skew
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
			$out[ strtolower( (string) $k ) ] = $v;
		}
		return $out;
	}

	private function is_nonce_used( string $nonce ): bool {
		$key = 'mk_mig_nonce_' . md5( $nonce );
		return (bool) \get_transient( $key );
	}

	private function mark_nonce_used( string $nonce ): void {
		$key = 'mk_mig_nonce_' . md5( $nonce );
		\set_transient( $key, 1, self::NONCE_TTL );
	}
}


