<?php
namespace MK\WcPluginStarter\Rest;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use MK\WcPluginStarter\Contracts\Registrable;
use MK\WcPluginStarter\Security\HmacAuth;
use MK\WcPluginStarter\Files\ChunkStore;
use MK\WcPluginStarter\State\StateStore;
use MK\WcPluginStarter\Migration\JobManager;
use MK\WcPluginStarter\Migration\DatabaseEngine;
use MK\WcPluginStarter\Logging\JsonLogger;
use MK\WcPluginStarter\Preflight\Checker;
use \WP_Error;
use \WP_REST_Request;
use \WP_REST_Response;

final class Api implements Registrable {
    private HmacAuth $auth;
    private ChunkStore $chunks;
    private JobManager $jobs;
    private DatabaseEngine $dbEngine;

    public function __construct( HmacAuth $auth ) {
        $this->auth = $auth;
        $this->chunks = new ChunkStore();
        $this->jobs = new JobManager( new StateStore() );
        $this->dbEngine = new DatabaseEngine( $this->chunks );
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

        \register_rest_route( 'migrate/v1', '/db/export', [
            'methods'  => 'POST',
            'callback' => [ $this, 'db_export' ],
            'permission_callback' => '__return_true',
            'args' => [
                'job_id' => [ 'required' => true, 'type' => 'string' ],
                'artifact' => [ 'required' => false, 'type' => 'string', 'default' => 'db_dump.sql.zst' ],
            ],
        ] );
    }

    public function handshake( WP_REST_Request $request ) {
        return $this->with_auth( $request, function () {
            $checker = new Checker();
            $preflight = $checker->run();
            
            if ( ! $preflight['ok'] ) {
                return new WP_REST_Response( [ 
                    'ok' => false, 
                    'code' => 'EPREFLIGHT_FAILED',
                    'errors' => $preflight['errors'] 
                ], 400 );
            }
            
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
        return $this->with_auth( $request, function () use ( $request ) {
            $action = (string) ( $request->get_param( 'action' ) ?? '' );
            $jobId = (string) ( $request->get_param( 'job_id' ) ?? '' );
            
            switch ( $action ) {
                case 'health':
                    return $this->handle_health( $jobId );
                case 'prepare':
                    return $this->handle_prepare( $jobId, $request );
                case 'db_import':
                    return $this->handle_db_import( $jobId, $request );
                case 'search_replace':
                    return $this->handle_search_replace( $jobId, $request );
                case 'finalize':
                    return $this->handle_finalize( $jobId, $request );
                case 'rollback':
                    return $this->handle_rollback( $jobId, $request );
                default:
                    return new WP_REST_Response( [ 'ok' => false, 'code' => 'EUNKNOWN_ACTION', 'message' => 'Unknown action: ' . $action ], 400 );
            }
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

    public function db_export( WP_REST_Request $request ) {
        return $this->with_auth( $request, function () use ( $request ) {
            $jobId = (string) ( $request->get_param( 'job_id' ) ?? '' );
            $artifact = (string) ( $request->get_param( 'artifact' ) ?? 'db_dump.sql.zst' );

            if ( $jobId === '' ) {
                return new WP_REST_Response( [
                    'ok' => false,
                    'code' => 'EBAD_REQUEST',
                    'message' => 'job_id is required'
                ], 400 );
            }

            $result = $this->dbEngine->export_database( $jobId, $artifact );

            if ( ! $result['ok'] ) {
                return new WP_REST_Response( [
                    'ok' => false,
                    'code' => 'EDB_EXPORT_FAILED',
                    'message' => $result['error'] ?? 'Database export failed'
                ], 500 );
            }

            // Update job state
            $this->jobs->set_state( $jobId, 'db_exported', [
                'export_method' => $result['method'] ?? 'unknown',
                'exported_at' => gmdate( 'c' )
            ]);

            return new WP_REST_Response( [
                'ok' => true,
                'method' => $result['method'] ?? 'unknown',
                'artifact' => $artifact,
                'notes' => [ 'Database export completed successfully' ]
            ] );
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

    private function handle_health( string $jobId ): WP_REST_Response {
        $job = $this->jobs->get_progress( $jobId );
        return new WP_REST_Response( [ 
            'ok' => true, 
            'state' => (string) ( $job['state'] ?? 'created' ),
            'job_id' => $jobId 
        ] );
    }

    private function handle_prepare( string $jobId, WP_REST_Request $request ): WP_REST_Response {
        $params = $request->get_json_params() ?? [];
        $mode = (string) ( $params['mode'] ?? 'reset' );
        $emailBlackhole = (bool) ( $params['email_blackhole'] ?? true );
        
        // Create or update job state
        $job = $this->jobs->set_state( $jobId, 'preflight_ok', [
            'mode' => $mode,
            'email_blackhole' => $emailBlackhole,
            'prepared_at' => gmdate( 'c' )
        ] );
        
        return new WP_REST_Response( [
            'ok' => true,
            'state' => 'preflight_ok',
            'notes' => [ 'Job prepared successfully' ]
        ] );
    }

    private function handle_db_import( string $jobId, WP_REST_Request $request ): WP_REST_Response {
        $params = $request->get_json_params() ?? [];
        $artifact = (string) ( $params['artifact'] ?? 'db_dump.sql.zst' );

        $result = $this->dbEngine->import_database( $jobId, $artifact );

        if ( ! $result['ok'] ) {
            return new WP_REST_Response( [
                'ok' => false,
                'code' => 'EDB_IMPORT_FAILED',
                'message' => $result['error'] ?? 'Database import failed'
            ], 500 );
        }

        // Update job state
        $this->jobs->set_state( $jobId, 'db_imported', [
            'import_stats' => $result['stats'] ?? [],
            'imported_at' => gmdate( 'c' )
        ]);

        return new WP_REST_Response( [
            'ok' => true,
            'state' => 'db_imported',
            'stats' => $result['stats'] ?? [],
            'notes' => [ 'Database import completed successfully' ]
        ] );
    }

    private function handle_search_replace( string $jobId, WP_REST_Request $request ): WP_REST_Response {
        $params = $request->get_json_params() ?? [];

        $result = $this->dbEngine->search_replace_urls( $jobId, $params );

        if ( ! $result['ok'] ) {
            return new WP_REST_Response( [
                'ok' => false,
                'code' => 'ESEARCH_REPLACE_FAILED',
                'message' => $result['error'] ?? 'URL replacement failed'
            ], 500 );
        }

        // Update job state
        $this->jobs->set_state( $jobId, 'url_replaced', [
            'replacements' => $result['replacements'] ?? 0,
            'replaced_at' => gmdate( 'c' )
        ]);

        return new WP_REST_Response( [
            'ok' => true,
            'state' => 'url_replaced',
            'replacements' => $result['replacements'] ?? 0,
            'notes' => [ 'URL replacement completed successfully' ]
        ] );
    }

    private function handle_finalize( string $jobId, WP_REST_Request $request ): WP_REST_Response {
        try {
            // Remove maintenance mode if it was set
            $this->remove_maintenance_mode();

            // Clear any remaining temporary files
            $this->cleanup_temp_files( $jobId );

            // Update job state to completed
            $this->jobs->set_state( $jobId, 'done', [
                'finalized_at' => gmdate( 'c' ),
                'cleanup_completed' => true
            ]);

            return new WP_REST_Response( [
                'ok' => true,
                'state' => 'done',
                'notes' => [ 'Migration completed successfully' ]
            ] );

        } catch ( \Throwable $e ) {
            return new WP_REST_Response( [
                'ok' => false,
                'code' => 'EFINALIZE_FAILED',
                'message' => 'Finalization failed: ' . $e->getMessage()
            ], 500 );
        }
    }

    private function handle_rollback( string $jobId, WP_REST_Request $request ): WP_REST_Response {
        try {
            // Basic rollback implementation - can be enhanced with snapshots
            $this->remove_maintenance_mode();

            // Update job state to indicate rollback
            $this->jobs->set_state( $jobId, 'rolled_back', [
                'rolled_back_at' => gmdate( 'c' ),
                'rollback_reason' => 'User initiated'
            ]);

            return new WP_REST_Response( [
                'ok' => true,
                'state' => 'rolled_back',
                'notes' => [ 'Rollback completed successfully' ]
            ] );

        } catch ( \Throwable $e ) {
            return new WP_REST_Response( [
                'ok' => false,
                'code' => 'EROLLBACK_FAILED',
                'message' => 'Rollback failed: ' . $e->getMessage()
            ], 500 );
        }
    }

    private function remove_maintenance_mode(): void {
        $maintenanceFile = \ABSPATH . '.maintenance';
        if ( file_exists( $maintenanceFile ) ) {
            @unlink( $maintenanceFile );
        }
    }

    private function cleanup_temp_files( string $jobId ): void {
        $jobDir = $this->chunks->get_job_dir( $jobId );
        if ( is_dir( $jobDir ) ) {
            // Remove old chunk files (keep for 24 hours as backup)
            $files = glob( $jobDir . '/chunks/*' );
            if ( $files ) {
                foreach ( $files as $file ) {
                    if ( filemtime( $file ) < ( time() - 86400 ) ) { // 24 hours ago
                        @unlink( $file );
                    }
                }
            }
        }
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


