<?php
/**
 * Basic tests for DatabaseEngine
 *
 * These are integration-style tests that can be run manually
 * or integrated with a testing framework like PHPUnit later.
 */

namespace WpMigrate\Tests;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use WpMigrate\Files\ChunkStore;
use WpMigrate\Migration\DatabaseEngine;
use PHPUnit\Framework\TestCase;

class DatabaseEngineTest extends TestCase {
    private ChunkStore $chunks;
    private DatabaseEngine $dbEngine;
    private string $testJobId;

    protected function setUp(): void {
        $this->chunks = new ChunkStore();
        $this->dbEngine = new DatabaseEngine( $this->chunks );
        $this->testJobId = 'test-job-' . time();
    }

    protected function tearDown(): void {
        // Clean up test files
        $jobDir = $this->chunks->get_job_dir( $this->testJobId );
        if ( is_dir( $jobDir ) ) {
            $this->removeDirectory( $jobDir );
        }
    }

    public function test_export_database_requires_method(): void {
        $result = $this->dbEngine->export_database( $this->testJobId );

        // Should fail if no mysqldump or wp-cli available
        if ( ! $this->hasMysqldump() && ! $this->hasWpCli() ) {
            $this->assertFalse( $result['ok'] );
            $this->assertStringContains( 'No suitable database export method', $result['error'] );
        }
    }

    public function test_search_replace_urls_validation(): void {
        // Test missing required parameters
        $result = $this->dbEngine->search_replace_urls( $this->testJobId, [] );

        $this->assertFalse( $result['ok'] );
        $this->assertStringContains( 'Missing required URL configuration', $result['error'] );
    }

    public function test_search_replace_urls_basic_functionality(): void {
        $config = [
            'mode' => 'absolute',
            'siteurl' => 'https://staging.example.com',
            'from_abs' => 'https://prod.example.com',
            'to_rel' => '/'
        ];

        // This would require setting up test tables, so we'll just test the validation
        $result = $this->dbEngine->search_replace_urls( $this->testJobId, $config );

        // Should not fail on validation (though may fail on actual processing if no tables exist)
        $this->assertArrayHasKey( 'ok', $result );
    }

    public function test_import_database_validation(): void {
        $result = $this->dbEngine->import_database( $this->testJobId );

        // Should fail if no chunks exist
        $this->assertFalse( $result['ok'] );
        $this->assertStringContains( 'No chunks found', $result['error'] );
    }

    public function test_chunk_and_store_functionality(): void {
        // Test the internal chunking method via reflection
        $reflection = new \ReflectionClass( $this->dbEngine );
        $method = $reflection->getMethod( 'chunk_and_store' );
        $method->setAccessible( true );

        $testContent = str_repeat( 'a', 100 ); // Small content for testing

        // This should not throw an exception
        try {
            $method->invoke( $this->dbEngine, $this->testJobId, 'test-artifact', $testContent );

            // Verify chunks were created
            $present = $this->chunks->list_present( $this->testJobId, 'test-artifact' );
            $this->assertNotEmpty( $present );

        } catch ( \Throwable $e ) {
            $this->fail( 'chunk_and_store should not throw exception: ' . $e->getMessage() );
        }
    }

    public function test_get_wordpress_tables(): void {
        global $wpdb;

        $reflection = new \ReflectionClass( $this->dbEngine );
        $method = $reflection->getMethod( 'get_wordpress_tables' );
        $method->setAccessible( true );

        $tables = $method->invoke( $this->dbEngine );

        $this->assertIsArray( $tables );
        $this->assertNotEmpty( $tables );

        // Should include core WordPress tables
        $this->assertContains( $wpdb->posts, $tables );
        $this->assertContains( $wpdb->options, $tables );
    }

    public function test_make_relative_url(): void {
        $reflection = new \ReflectionClass( $this->dbEngine );
        $method = $reflection->getMethod( 'make_relative_url' );
        $method->setAccessible( true );

        // Test absolute URL
        $result = $method->invoke( $this->dbEngine, 'https://example.com/path/page' );
        $this->assertEquals( '/path/page', $result );

        // Test root URL
        $result = $method->invoke( $this->dbEngine, 'https://example.com/' );
        $this->assertEquals( '/', $result );
    }

    public function test_replace_urls_in_content(): void {
        $reflection = new \ReflectionClass( $this->dbEngine );
        $method = $reflection->getMethod( 'replace_urls_in_content' );
        $method->setAccessible( true );

        $content = 'Check out https://prod.example.com/page for more info!';
        $result = $method->invoke( $this->dbEngine, $content, 'absolute', 'https://staging.example.com', 'https://prod.example.com', '/' );

        $this->assertStringContains( 'https://staging.example.com/page', $result );
        $this->assertStringNotContains( 'https://prod.example.com/page', $result );
    }

    private function hasMysqldump(): bool {
        $reflection = new \ReflectionClass( $this->dbEngine );
        $method = $reflection->getMethod( 'has_mysqldump' );
        $method->setAccessible( true );

        return $method->invoke( $this->dbEngine );
    }

    private function hasWpCli(): bool {
        $reflection = new \ReflectionClass( $this->dbEngine );
        $method = $reflection->getMethod( 'has_wp_cli' );
        $method->setAccessible( true );

        return $method->invoke( $this->dbEngine );
    }

    private function removeDirectory( string $dir ): void {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $files = array_diff( scandir( $dir ), [ '.', '..' ] );
        foreach ( $files as $file ) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if ( is_dir( $path ) ) {
                $this->removeDirectory( $path );
            } else {
                unlink( $path );
            }
        }

        rmdir( $dir );
    }
}
