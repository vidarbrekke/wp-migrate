<?php
namespace MK\WcPluginStarter\Migration;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use MK\WcPluginStarter\Logging\JsonLogger;

final class UrlReplacer {
    public function search_replace_urls( string $jobId, array $config ): array {
        $logger = new JsonLogger( $jobId );

        try {
            $mode = $config['mode'] ?? 'hybrid';
            $siteurl = $config['siteurl'] ?? '';
            $fromAbs = $config['from_abs'] ?? '';
            $toRel = $config['to_rel'] ?? '/';

            if ( empty( $siteurl ) || empty( $fromAbs ) ) {
                return [
                    'ok' => false,
                    'error' => 'Missing required URL configuration'
                ];
            }

            // Get all WordPress tables
            $tables = $this->get_wordpress_tables();

            $totalReplacements = 0;

            foreach ( $tables as $table ) {
                $replacements = $this->process_table_urls( $table, $mode, $siteurl, $fromAbs, $toRel );
                $totalReplacements += $replacements;

                $logger->log( 'info', 'URL replacements completed', [
                    'table' => $table,
                    'replacements' => $replacements
                ]);
            }

            return [
                'ok' => true,
                'replacements' => $totalReplacements
            ];

        } catch ( \Throwable $e ) {
            $logger->log( 'error', 'URL replacement failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'ok' => false,
                'error' => 'URL replacement failed: ' . $e->getMessage()
            ];
        }
    }

    private function process_table_urls( string $table, string $mode, string $siteurl, string $fromAbs, string $toRel ): int {
        $replacements = 0;

        // Get columns that might contain URLs (common suspects)
        $columns = $this->get_url_columns( $table );

        foreach ( $columns as $column ) {
            $replacements += $this->replace_column_urls( $table, $column, $mode, $siteurl, $fromAbs, $toRel );
        }

        return $replacements;
    }

    private function get_wordpress_tables(): array {
        global $wpdb;

        // Core WordPress tables that commonly contain URLs
        $coreTables = [
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->options,
            $wpdb->usermeta,
        ];

        // Add any custom tables that start with wp_ prefix
        $allTables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );

        return array_unique( array_merge( $coreTables, $allTables ) );
    }

    private function get_url_columns( string $table ): array {
        global $wpdb;

        $columns = [];

        // Common URL-containing columns
        $commonColumns = [
            'guid', 'post_content', 'post_excerpt',
            'meta_value', 'option_value',
            'user_url'
        ];

        $tableColumns = $wpdb->get_col( "DESCRIBE `{$table}`", 0 );

        foreach ( $tableColumns as $column ) {
            if ( in_array( $column, $commonColumns, true ) ||
                 strpos( $column, 'url' ) !== false ||
                 strpos( $column, 'link' ) !== false ) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    private function replace_column_urls( string $table, string $column, string $mode, string $siteurl, string $fromAbs, string $toRel ): int {
        global $wpdb;

        $replacements = 0;

        // Get rows that contain the old URL
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `{$column}` LIKE %s",
            '%' . $wpdb->esc_like( $fromAbs ) . '%'
        ));

        foreach ( $rows as $row ) {
            $oldValue = $row->{$column};
            $newValue = $this->replace_urls_in_content( $oldValue, $mode, $siteurl, $fromAbs, $toRel );

            if ( $oldValue !== $newValue ) {
                $wpdb->update(
                    $table,
                    [ $column => $newValue ],
                    [ $wpdb->get_primary_key( $table ) => $row->{$wpdb->get_primary_key( $table )} ]
                );
                $replacements++;
            }
        }

        return $replacements;
    }

    private function replace_urls_in_content( string $content, string $mode, string $siteurl, string $fromAbs, string $toRel ): string {
        // Handle different replacement modes
        switch ( $mode ) {
            case 'absolute':
                // Replace all instances of from_abs with siteurl
                return str_replace( $fromAbs, $siteurl, $content );

            case 'relative':
                // Convert absolute URLs to relative
                $relativeUrl = $this->make_relative_url( $siteurl, $toRel );
                return str_replace( $fromAbs, $relativeUrl, $content );

            case 'hybrid':
            default:
                // Smart replacement: absolute for content, relative for structure
                $content = $this->replace_serialized_urls( $content, $fromAbs, $siteurl );
                return $this->replace_unserialized_urls( $content, $fromAbs, $siteurl );
        }
    }

    private function replace_serialized_urls( string $content, string $fromAbs, string $siteurl ): string {
        // Basic approach: look for common serialized patterns
        $patterns = [
            '/s:\d+:"' . preg_quote( $fromAbs, '/' ) . '"/',
            '/s:\d+:"[^"]*' . preg_quote( $fromAbs, '/' ) . '[^"]*"/',
        ];

        foreach ( $patterns as $pattern ) {
            $content = preg_replace_callback( $pattern, function( $matches ) use ( $fromAbs, $siteurl ) {
                return str_replace( $fromAbs, $siteurl, $matches[0] );
            }, $content );
        }

        return $content;
    }

    private function replace_unserialized_urls( string $content, string $fromAbs, string $siteurl ): string {
        return str_replace( $fromAbs, $siteurl, $content );
    }

    private function make_relative_url( string $absoluteUrl, string $relativeBase = '/' ): string {
        $parsed = parse_url( $absoluteUrl );
        if ( ! $parsed ) {
            return $absoluteUrl;
        }

        $path = $parsed['path'] ?? '/';
        return $relativeBase . ltrim( $path, '/' );
    }
}
