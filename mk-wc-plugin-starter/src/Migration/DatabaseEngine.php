<?php
namespace MK\WcPluginStarter\Migration;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use MK\WcPluginStarter\Files\ChunkStore;
use \WP_Error;

final class DatabaseEngine {
    private ChunkStore $chunks;
    private DatabaseExporter $exporter;
    private DatabaseImporter $importer;
    private UrlReplacer $urlReplacer;

    public function __construct( ChunkStore $chunks ) {
        $this->chunks = $chunks;
        $this->exporter = new DatabaseExporter( $chunks );
        $this->importer = new DatabaseImporter( $chunks );
        $this->urlReplacer = new UrlReplacer();
    }

    /**
     * Export database using mysqldump or wp-cli as fallback
     *
     * @param string $jobId Job identifier
     * @param string $artifact Name for the exported artifact
     * @return array{ok: bool, error?: string, method?: string}
     */
    public function export_database( string $jobId, string $artifact = 'db_dump.sql.zst' ): array {
        return $this->exporter->export( $jobId, $artifact );
    }

    /**
     * Import database from uploaded chunks
     *
     * @param string $jobId Job identifier
     * @param string $artifact Name of the artifact to import
     * @return array{ok: bool, error?: string, stats?: array}
     */
    public function import_database( string $jobId, string $artifact = 'db_dump.sql.zst' ): array {
        return $this->importer->import( $jobId, $artifact );
    }

    /**
     * Perform serializer-safe URL search and replace
     *
     * @param string $jobId Job identifier
     * @param array $config Search/replace configuration
     * @return array{ok: bool, error?: string, replacements?: int}
     */
    public function search_replace_urls( string $jobId, array $config ): array {
        return $this->urlReplacer->search_replace_urls( $jobId, $config );
    }

}
