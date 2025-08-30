<?php
namespace MK\WcPluginStarter\Migration;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use MK\WcPluginStarter\Files\ChunkStore;
use MK\WcPluginStarter\Logging\JsonLogger;

class DatabaseImporter {
    private ChunkStore $chunks;

    public function __construct( ChunkStore $chunks ) {
        $this->chunks = $chunks;
    }

    public function import( string $jobId, string $artifact = 'db_dump.sql.zst' ): array {
        $logger = new JsonLogger( $jobId );

        try {
            // Assemble chunks into complete dump file
            $dumpPath = $this->assemble_dump_file( $jobId, $artifact );

            if ( ! file_exists( $dumpPath ) ) {
                return [
                    'ok' => false,
                    'error' => 'Database dump file not found after chunk assembly'
                ];
            }

            // Import the database
            $result = $this->perform_import( $dumpPath );

            // Clean up temporary file
            @unlink( $dumpPath );

            return $result;

        } catch ( \Throwable $e ) {
            $logger->log( 'error', 'Database import failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [
                'ok' => false,
                'error' => 'Database import failed: ' . $e->getMessage()
            ];
        }
    }

    private function assemble_dump_file( string $jobId, string $artifact ): string {
        $chunks = $this->chunks->list_present( $jobId, $artifact );

        if ( empty( $chunks ) ) {
            throw new \RuntimeException( 'No chunks found for artifact: ' . $artifact );
        }

        $tempFile = tempnam( sys_get_temp_dir(), 'db_import_' );
        $handle = fopen( $tempFile, 'wb' );

        if ( ! $handle ) {
            throw new \RuntimeException( 'Failed to create temporary file for import' );
        }

        try {
            foreach ( $chunks as $index ) {
                $chunkPath = $this->chunks->chunk_path( $jobId, $artifact, $index );
                $chunkData = file_get_contents( $chunkPath );

                if ( $chunkData === false ) {
                    throw new \RuntimeException( 'Failed to read chunk: ' . $chunkPath );
                }

                fwrite( $handle, $chunkData );
            }
        } finally {
            fclose( $handle );
        }

        return $tempFile;
    }

    private function perform_import( string $dumpPath ): array {
        global $wpdb;

        $stats = [ 'tables_created' => 0, 'tables_dropped' => 0 ];

        // Start transaction
        $wpdb->query( 'START TRANSACTION' );

        try {
            // Read and execute SQL file
            $sql = file_get_contents( $dumpPath );
            if ( $sql === false ) {
                throw new \RuntimeException( 'Failed to read dump file' );
            }

            // Split into individual statements (basic approach)
            $statements = array_filter( array_map( 'trim', explode( ';', $sql ) ) );

            foreach ( $statements as $statement ) {
                if ( empty( $statement ) ) {
                    continue;
                }

                // Skip comments and empty lines
                if ( strpos( $statement, '--' ) === 0 || strpos( $statement, '#' ) === 0 ) {
                    continue;
                }

                $result = $wpdb->query( $statement );

                if ( $result === false ) {
                    throw new \RuntimeException( 'SQL execution failed: ' . $wpdb->last_error );
                }

                // Track basic stats
                if ( stripos( $statement, 'CREATE TABLE' ) === 0 ) {
                    $stats['tables_created']++;
                } elseif ( stripos( $statement, 'DROP TABLE' ) === 0 ) {
                    $stats['tables_dropped']++;
                }
            }

            $wpdb->query( 'COMMIT' );

            $logger = new JsonLogger( 'import_job' ); // Need to get jobId from context
            $logger->log( 'info', 'Database import completed', $stats );

            return [
                'ok' => true,
                'stats' => $stats
            ];

        } catch ( \Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );

            $logger = new JsonLogger( 'import_job' ); // Need to get jobId from context
            $logger->log( 'error', 'Database import failed', [
                'error' => $e->getMessage(),
                'stats' => $stats
            ]);

            throw $e;
        }
    }
}
