<?php
namespace MK\WcPluginStarter\Rest;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use MK\WcPluginStarter\Contracts\Registrable;
use MK\WcPluginStarter\Security\HmacAuth;
use MK\WcPluginStarter\Files\ChunkStore;
use MK\WcPluginStarter\State\StateStore;
use MK\WcPluginStarter\Migration\JobManager;
use MK\WcPluginStarter\Logging\JsonLogger;
use \WP_Error;
use \WP_REST_Request;
use \WP_REST_Response;

final class Api implements Registrable {
    private HmacAuth $auth;
    private ChunkStore $chunks;
    private JobManager $jobs;

    public function __construct( HmacAuth $auth ) {
        $this->auth = $auth;
        $this->chunks = new ChunkStore();
        $this->jobs = new JobManager( new StateStore() );
    }

    public function register(): void {
        \add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        \register_rest_route( 'migrate/v1', '/handshake', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handshake' ],
            'permission_callback' => '__return_true',
        ] );

        \register_rest_route( 'migrate/v1', '/command', [
            'methods'  => 'POST',
            'callback' => [ $this, 'command' ],
            'permission_callback' => '__return_true',
        ] );

        \register_rest_route( 'migrate/v1', '/chunk', [
            'methods'  => [ 'POST', 'GET' ],
            'callback' => [ $this, 'chunk' ],
            'permission_callback' => '__return_true',
            'args' => [
                'job_id' => [ 'required' => true, 'type' => 'string' ],
                'artifact' => [ 'required' => true, 'type' => 'string' ],
                'index' => [ 'required' => false, 'type' => 'integer', 'minimum' => 0 ],
                'sha256' => [ 'required' => false, 'type' => 'string' ],
            ],
        ] );

        \register_rest_route( 'migrate/v1', '/progress', [
            'methods'  => 'GET',
            'callback' => [ $this, 'progress' ],
            'permission_callback' => '__return_true',
            'args' => [
                'job_id' => [ 'required' => true, 'type' => 'string' ],
            ],
        ] );

        \register_rest_route( 'migrate/v1', '/logs/tail', [
            'methods'  => 'GET',
            'callback' => [ $this, 'logs_tail' ],
            'permission_callback' => '__return_true',
            'args' => [
                'job_id' => [ 'required' => true, 'type' => 'string' ],
                'n' => [ 'required' => false, 'type' => 'integer', 'default' => 200, 'minimum' => 1, 'maximum' => 1000 ],
            ],
        ] );
    }

    public function handshake( WP_REST_Request $request ) {
        return $this->with_auth( $request, function () {
            $cap = [
                'rsync'  => $this->has_rsync(),
                'zstd'   => $this->has_zstd(),
                'wp_cli' => $this->has_wp_cli(),
            ];
            $site = [
                'url'    => \get_site_url(),
                'wp'     => \get_bloginfo( 'version' ),
                'db'     => 'mysql',
                'prefix' => \is_multisite() ? \get_site_option( 'base_prefix' ) : $GLOBALS['wpdb']->prefix,
                'charset'=> $GLOBALS['wpdb']->charset,
            ];
            return new WP_REST_Response( [ 'ok' => true, 'site' => $site, 'capabilities' => $cap ] );
        } );
    }

    public function command( WP_REST_Request $request ) {
        return $this->with_auth( $request, function () {
            // TODO: Implement stateful actions per spec
            return new WP_REST_Response( [ 'ok' => false, 'code' => 'ENOT_IMPLEMENTED', 'message' => 'Command not implemented yet' ], 501 );
        } );
    }

    public function chunk( WP_REST_Request $request ) {
        return $this->with_auth( $request, function () use ( $request ) {
            $jobId = (string) ( $request->get_param( 'job_id' ) ?? '' );
            $artifact = (string) ( $request->get_param( 'artifact' ) ?? '' );
            if ( $jobId === '' || $artifact === '' ) {
                return new WP_REST_Response( [ 'ok' => false, 'code' => 'EBAD_REQUEST', 'message' => 'job_id and artifact required' ], 400 );
            }
            if ( $request->get_method() === 'GET' ) {
                $present = $this->chunks->list_present( $jobId, $artifact );
                $next = 0;
                if ( ! empty( $present ) ) {
                    $next = max( $present ) + 1;
                }
                return new WP_REST_Response( [ 'present' => $present, 'next' => $next ] );
            }
            $index = (int) ( $request->get_param( 'index' ) ?? 0 );
            $sha  = (string) ( $request->get_param( 'sha256' ) ?? '' );
            $raw  = (string) $request->get_body();
            try {
                $this->chunks->save_chunk( $jobId, $artifact, $index, $raw, $sha );
            } catch ( \Throwable $e ) {
                return new WP_REST_Response( [ 'ok' => false, 'code' => 'EBAD_CHUNK', 'message' => 'Chunk validation failed' ], 400 );
            }
            return new WP_REST_Response( [ 'ok' => true, 'received' => [ 'index' => $index ] ] );
        } );
    }

    public function progress( WP_REST_Request $request ) {
        return $this->with_auth( $request, function () use ( $request ) {
            $jobId = (string) ( $request->get_param( 'job_id' ) ?? '' );
            $job = $this->jobs->get_progress( $jobId );
            return new WP_REST_Response( [ 'job_id' => $jobId, 'state' => (string) ( $job['state'] ?? 'created' ), 'steps' => (array) ( $job['steps'] ?? [] ) ] );
        } );
    }

    public function logs_tail( WP_REST_Request $request ) {
        return $this->with_auth( $request, function () use ( $request ) {
            $jobId = (string) ( $request->get_param( 'job_id' ) ?? '' );
            $n = (int) ( $request->get_param( 'n' ) ?? 200 );
            $log = new JsonLogger( $jobId );
            return new WP_REST_Response( [ 'lines' => $log->tail( $n ) ] );
        } );
    }

    private function error_to_response( WP_Error $err, string $fallbackCode ) {
        $code = $err->get_error_code() ?: $fallbackCode;
        $msg  = $err->get_error_message();
        $data = (array) $err->get_error_data();
        $status = isset( $data['status'] ) ? (int) $data['status'] : 401;
        return new WP_REST_Response( [ 'ok' => false, 'code' => $code, 'message' => $msg ], $status );
    }

    /**
     * Auth wrapper to enforce HMAC verification and return uniform error responses.
     * @param callable $callback function(): WP_REST_Response
     */
    private function with_auth( WP_REST_Request $request, callable $callback ) {
        $auth = $this->auth->verify_request( $request );
        if ( $auth instanceof WP_Error ) {
            return $this->error_to_response( $auth, 'EAUTH' );
        }
        return $callback();
    }

    private function has_rsync(): bool {
        return $this->binary_exists( 'rsync' );
    }
    private function has_zstd(): bool {
        return $this->binary_exists( 'zstd' ) || $this->binary_exists( 'pzstd' );
    }
    private function has_wp_cli(): bool {
        return \defined( 'WP_CLI' ) && \WP_CLI;
    }
    private function binary_exists( string $bin ): bool {
        $which = \escapeshellcmd( 'command -v ' . $bin ) . ' 2>/dev/null';
        $path = \trim( (string) \shell_exec( $which ) );
        return $path !== '' && \file_exists( $path );
    }
}


