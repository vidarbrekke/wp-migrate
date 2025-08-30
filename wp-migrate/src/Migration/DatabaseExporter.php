<?php
namespace WpMigrate\Migration;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use WpMigrate\Files\ChunkStore;
use WpMigrate\Logging\JsonLogger;

class DatabaseExporter {
    private ChunkStore $chunks;

    public function __construct( ChunkStore $chunks ) {
        $this->chunks = $chunks;
    }

    public function export( string $jobId, string $artifact = 'db_dump.sql.zst' ): array {
        $logger = new JsonLogger( $jobId );

        // Try mysqldump first (more reliable for large databases)
        if ( $this->has_mysqldump() ) {
            $result = $this->export_with_mysqldump( $jobId, $artifact );
            if ( $result['ok'] ) {
                return $result;
            }
        }

        // Fallback to wp-cli if available
        if ( $this->has_wp_cli() ) {
            $result = $this->export_with_wp_cli( $jobId, $artifact );
            if ( $result['ok'] ) {
                return $result;
            }
        }

        return [
            'ok' => false,
            'error' => 'No suitable database export method available (mysqldump or wp-cli required)'
        ];
    }

    private function export_with_mysqldump( string $jobId, string $artifact ): array {
        try {
            $dbConfig = $this->get_db_config();
            $logger = new JsonLogger( $jobId );

            // Build mysqldump command with security
            $cmd = $this->build_secure_mysqldump_command( $dbConfig );

            // Execute export
            $output = $this->execute_command( $cmd );
            if ( $output === null ) {
                throw new \RuntimeException( 'mysqldump command failed' );
            }

            // Split into chunks and store
            $this->chunk_and_store( $jobId, $artifact, $output );

            $logger->log( 'info', 'Database exported with mysqldump', [
                'method' => 'mysqldump',
                'compressed' => $this->has_zstd() || $this->has_gzip()
            ]);

            return [ 'ok' => true, 'method' => 'mysqldump' ];

        } catch ( \Throwable $e ) {
            $logger = new JsonLogger( $jobId );
            $logger->log( 'error', 'mysqldump export failed', [
                'error' => $e->getMessage()
            ]);

            return [ 'ok' => false, 'error' => $e->getMessage() ];
        }
    }

    private function export_with_wp_cli( string $jobId, string $artifact ): array {
        try {
            $logger = new JsonLogger( $jobId );
            $tempFile = tempnam( sys_get_temp_dir(), 'wp_export_' );

            // Build wp-cli command
            $cmd = sprintf( 'wp db export %s --porcelain', escapeshellarg( $tempFile ) );

            exec( $cmd . ' 2>&1', $output, $returnCode );

            if ( $returnCode !== 0 ) {
                throw new \RuntimeException( 'wp-cli db export failed: ' . implode( "\n", $output ) );
            }

            if ( ! file_exists( $tempFile ) ) {
                throw new \RuntimeException( 'wp-cli export file not created' );
            }

            $content = file_get_contents( $tempFile );
            if ( $content === false ) {
                throw new \RuntimeException( 'Failed to read wp-cli export file' );
            }

            // Clean up temp file
            @unlink( $tempFile );

            // Split into chunks and store
            $this->chunk_and_store( $jobId, $artifact, $content );

            $logger->log( 'info', 'Database exported with wp-cli', [
                'method' => 'wp-cli'
            ]);

            return [ 'ok' => true, 'method' => 'wp-cli' ];

        } catch ( \Throwable $e ) {
            $logger = new JsonLogger( $jobId );
            $logger->log( 'error', 'wp-cli export failed', [
                'error' => $e->getMessage()
            ]);

            return [ 'ok' => false, 'error' => $e->getMessage() ];
        }
    }

    private function build_secure_mysqldump_command( array $dbConfig ): string {
        // Build mysqldump command with security
        $cmd = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --port=%s %s --single-transaction --quick --lock-tables=false',
            escapeshellarg( $dbConfig['host'] ),
            escapeshellarg( $dbConfig['user'] ),
            escapeshellarg( $dbConfig['password'] ),
            escapeshellarg( $dbConfig['port'] ),
            escapeshellarg( $dbConfig['name'] )
        );

        // Add compression if available
        if ( $this->has_zstd() ) {
            $cmd .= ' | zstd -T0 -19';
        } elseif ( $this->has_gzip() ) {
            $cmd .= ' | gzip -9';
        }

        return $cmd;
    }

    private function execute_command( string $cmd ): ?string {
        $output = shell_exec( $cmd . ' 2>&1' );
        return $output;
    }

    private function chunk_and_store( string $jobId, string $artifact, string $content ): void {
        $chunkSize = 64 * 1024 * 1024; // 64MB
        $chunks = str_split( $content, $chunkSize );

        foreach ( $chunks as $index => $chunk ) {
            $hash = base64_encode( hash( 'sha256', $chunk, true ) );
            $this->chunks->save_chunk( $jobId, $artifact, $index, $chunk, $hash );
        }

        $logger = new JsonLogger( $jobId );
        $logger->log( 'info', 'Content chunked and stored', [
            'artifact' => $artifact,
            'chunks' => count( $chunks ),
            'total_size' => strlen( $content )
        ]);
    }

    private function get_db_config(): array {
        return [
            'host' => \DB_HOST,
            'user' => \DB_USER,
            'password' => \DB_PASSWORD,
            'name' => \DB_NAME,
            'port' => '3306',
        ];
    }

    private function has_mysqldump(): bool {
        $output = shell_exec( 'command -v mysqldump 2>/dev/null' );
        return ! empty( trim( (string) $output ) );
    }

    private function has_wp_cli(): bool {
        return \defined( 'WP_CLI' ) && \WP_CLI;
    }

    private function has_zstd(): bool {
        $output = shell_exec( 'command -v zstd 2>/dev/null' );
        return ! empty( trim( (string) $output ) );
    }

    private function has_gzip(): bool {
        $output = shell_exec( 'command -v gzip 2>/dev/null' );
        return ! empty( trim( (string) $output ) );
    }
}
