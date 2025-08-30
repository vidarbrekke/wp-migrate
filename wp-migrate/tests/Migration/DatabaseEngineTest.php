<?php
/**
 * DatabaseEngine Tests
 *
 * Tests the main database migration orchestrator that coordinates
 * export, import, and URL replacement operations.
 */

namespace WpMigrate\Tests\Migration;

use WpMigrate\Migration\DatabaseEngine;
use WpMigrate\Migration\DatabaseExporter;
use WpMigrate\Migration\DatabaseImporter;
use WpMigrate\Migration\UrlReplacer;
use WpMigrate\Files\ChunkStore;
use WpMigrate\Tests\TestHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DatabaseEngineTest extends TestCase
{
    private DatabaseEngine $dbEngine;
    private string $testJobId;

    /** @var MockObject&DatabaseExporter */
    private MockObject $mockExporter;

    /** @var MockObject&DatabaseImporter */
    private MockObject $mockImporter;

    /** @var MockObject&UrlReplacer */
    private MockObject $mockUrlReplacer;

    /** @var MockObject&ChunkStore */
    private MockObject $mockChunkStore;

    protected function setUp(): void
    {
        parent::setUp();
        TestHelper::reset();

        $this->testJobId = 'test-job-' . uniqid();

        // Create mocks
        $this->mockChunkStore = $this->createMock(ChunkStore::class);
        $this->mockExporter = $this->createMock(DatabaseExporter::class);
        $this->mockImporter = $this->createMock(DatabaseImporter::class);
        $this->mockUrlReplacer = $this->createMock(UrlReplacer::class);

        // Create DatabaseEngine with mocked dependencies
        $this->dbEngine = new DatabaseEngine($this->mockChunkStore);

        // Use reflection to inject mocks
        $reflection = new \ReflectionClass($this->dbEngine);
        $exporterProperty = $reflection->getProperty('exporter');
        $exporterProperty->setAccessible(true);
        $exporterProperty->setValue($this->dbEngine, $this->mockExporter);

        $importerProperty = $reflection->getProperty('importer');
        $importerProperty->setAccessible(true);
        $importerProperty->setValue($this->dbEngine, $this->mockImporter);

        $urlReplacerProperty = $reflection->getProperty('urlReplacer');
        $urlReplacerProperty->setAccessible(true);
        $urlReplacerProperty->setValue($this->dbEngine, $this->mockUrlReplacer);
    }

    protected function tearDown(): void
    {
        TestHelper::reset();
        parent::tearDown();
    }

    /**
     * Test successful database export
     */
    public function test_export_database_success(): void
    {
        $artifact = 'test-export.sql.zst';
        $expectedResult = ['ok' => true, 'method' => 'mysqldump'];

        $this->mockExporter
            ->expects($this->once())
            ->method('export')
            ->with($this->testJobId, $artifact)
            ->willReturn($expectedResult);

        $result = $this->dbEngine->export_database($this->testJobId, $artifact);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test database export failure
     */
    public function test_export_database_failure(): void
    {
        $artifact = 'test-export.sql.zst';
        $expectedResult = ['ok' => false, 'error' => 'Export failed'];

        $this->mockExporter
            ->expects($this->once())
            ->method('export')
            ->with($this->testJobId, $artifact)
            ->willReturn($expectedResult);

        $result = $this->dbEngine->export_database($this->testJobId, $artifact);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['ok']);
        $this->assertEquals('Export failed', $result['error']);
    }

    /**
     * Test successful database import
     */
    public function test_import_database_success(): void
    {
        $artifact = 'test-import.sql.zst';
        $expectedResult = [
            'ok' => true,
            'stats' => ['tables_created' => 5, 'tables_dropped' => 2]
        ];

        $this->mockImporter
            ->expects($this->once())
            ->method('import')
            ->with($this->testJobId, $artifact)
            ->willReturn($expectedResult);

        $result = $this->dbEngine->import_database($this->testJobId, $artifact);

        $this->assertEquals($expectedResult, $result);
        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('stats', $result);
    }

    /**
     * Test database import failure
     */
    public function test_import_database_failure(): void
    {
        $artifact = 'test-import.sql.zst';
        $expectedResult = ['ok' => false, 'error' => 'Import failed'];

        $this->mockImporter
            ->expects($this->once())
            ->method('import')
            ->with($this->testJobId, $artifact)
            ->willReturn($expectedResult);

        $result = $this->dbEngine->import_database($this->testJobId, $artifact);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['ok']);
    }

    /**
     * Test successful URL replacement
     */
    public function test_search_replace_urls_success(): void
    {
        $config = [
            'mode' => 'hybrid',
            'siteurl' => 'https://staging.example.com',
            'from_abs' => 'https://prod.example.com',
            'to_rel' => '/'
        ];

        $expectedResult = [
            'ok' => true,
            'replacements' => 150
        ];

        $this->mockUrlReplacer
            ->expects($this->once())
            ->method('search_replace_urls')
            ->with($this->testJobId, $config)
            ->willReturn($expectedResult);

        $result = $this->dbEngine->search_replace_urls($this->testJobId, $config);

        $this->assertEquals($expectedResult, $result);
        $this->assertTrue($result['ok']);
        $this->assertEquals(150, $result['replacements']);
    }

    /**
     * Test URL replacement failure
     */
    public function test_search_replace_urls_failure(): void
    {
        $config = [
            'mode' => 'absolute',
            'siteurl' => 'https://staging.example.com',
            'from_abs' => 'https://prod.example.com'
        ];

        $expectedResult = [
            'ok' => false,
            'error' => 'URL replacement failed'
        ];

        $this->mockUrlReplacer
            ->expects($this->once())
            ->method('search_replace_urls')
            ->with($this->testJobId, $config)
            ->willReturn($expectedResult);

        $result = $this->dbEngine->search_replace_urls($this->testJobId, $config);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['ok']);
    }

    /**
     * Test default artifact names
     */
    public function test_default_artifact_names(): void
    {
        $defaultArtifact = 'db_dump.sql.zst';

        $this->mockExporter
            ->expects($this->once())
            ->method('export')
            ->with($this->testJobId, $defaultArtifact)
            ->willReturn(['ok' => true, 'method' => 'mysqldump']);

        $result = $this->dbEngine->export_database($this->testJobId);

        $this->assertTrue($result['ok']);
    }

    /**
     * Test that all methods delegate to correct specialized classes
     */
    public function test_method_delegation(): void
    {
        // Test that export delegates to exporter
        $this->mockExporter
            ->expects($this->once())
            ->method('export')
            ->willReturn(['ok' => true]);

        $this->dbEngine->export_database($this->testJobId);

        // Test that import delegates to importer
        $this->mockImporter
            ->expects($this->once())
            ->method('import')
            ->willReturn(['ok' => true]);

        $this->dbEngine->import_database($this->testJobId);

        // Test that URL replacement delegates to url replacer
        $this->mockUrlReplacer
            ->expects($this->once())
            ->method('search_replace_urls')
            ->willReturn(['ok' => true]);

        $this->dbEngine->search_replace_urls($this->testJobId, []);
    }

    /**
     * Test dependency injection in constructor
     */
    public function test_constructor_dependency_injection(): void
    {
        $chunkStore = $this->createMock(ChunkStore::class);
        $dbEngine = new DatabaseEngine($chunkStore);

        $reflection = new \ReflectionClass($dbEngine);

        // Verify chunk store is injected
        $chunkStoreProperty = $reflection->getProperty('chunks');
        $chunkStoreProperty->setAccessible(true);
        $this->assertSame($chunkStore, $chunkStoreProperty->getValue($dbEngine));

        // Verify specialized classes are created
        $exporterProperty = $reflection->getProperty('exporter');
        $exporterProperty->setAccessible(true);
        $this->assertInstanceOf(DatabaseExporter::class, $exporterProperty->getValue($dbEngine));

        $importerProperty = $reflection->getProperty('importer');
        $importerProperty->setAccessible(true);
        $this->assertInstanceOf(DatabaseImporter::class, $importerProperty->getValue($dbEngine));

        $urlReplacerProperty = $reflection->getProperty('urlReplacer');
        $urlReplacerProperty->setAccessible(true);
        $this->assertInstanceOf(UrlReplacer::class, $urlReplacerProperty->getValue($dbEngine));
    }

    /**
     * Test error handling and propagation
     */
    public function test_error_propagation(): void
    {
        $testError = 'Test database error';

        // Test export error propagation
        $this->mockExporter
            ->expects($this->once())
            ->method('export')
            ->willReturn(['ok' => false, 'error' => $testError]);

        $result = $this->dbEngine->export_database($this->testJobId);
        $this->assertEquals($testError, $result['error']);

        // Test import error propagation
        $this->mockImporter
            ->expects($this->once())
            ->method('import')
            ->willReturn(['ok' => false, 'error' => $testError]);

        $result = $this->dbEngine->import_database($this->testJobId);
        $this->assertEquals($testError, $result['error']);

        // Test URL replacement error propagation
        $this->mockUrlReplacer
            ->expects($this->once())
            ->method('search_replace_urls')
            ->willReturn(['ok' => false, 'error' => $testError]);

        $result = $this->dbEngine->search_replace_urls($this->testJobId, []);
        $this->assertEquals($testError, $result['error']);
    }

    /**
     * Test that job ID is passed correctly to all operations
     */
    public function test_job_id_consistency(): void
    {
        $customJobId = 'custom-job-123';

        $this->mockExporter
            ->expects($this->once())
            ->method('export')
            ->with($customJobId)
            ->willReturn(['ok' => true]);

        $this->dbEngine->export_database($customJobId);

        $this->mockImporter
            ->expects($this->once())
            ->method('import')
            ->with($customJobId)
            ->willReturn(['ok' => true]);

        $this->dbEngine->import_database($customJobId);

        $this->mockUrlReplacer
            ->expects($this->once())
            ->method('search_replace_urls')
            ->with($customJobId)
            ->willReturn(['ok' => true]);

        $this->dbEngine->search_replace_urls($customJobId, []);
    }

    /**
     * Test mixed operations (success and failure scenarios)
     */
    public function test_mixed_operation_scenarios(): void
    {
        // Export succeeds, import fails, URL replacement succeeds
        $this->mockExporter
            ->expects($this->once())
            ->method('export')
            ->willReturn(['ok' => true, 'method' => 'mysqldump']);

        $this->mockImporter
            ->expects($this->once())
            ->method('import')
            ->willReturn(['ok' => false, 'error' => 'Import failed']);

        $this->mockUrlReplacer
            ->expects($this->once())
            ->method('search_replace_urls')
            ->willReturn(['ok' => true, 'replacements' => 50]);

        // Test each operation independently
        $exportResult = $this->dbEngine->export_database($this->testJobId);
        $importResult = $this->dbEngine->import_database($this->testJobId);
        $urlResult = $this->dbEngine->search_replace_urls($this->testJobId, []);

        $this->assertTrue($exportResult['ok']);
        $this->assertFalse($importResult['ok']);
        $this->assertTrue($urlResult['ok']);
    }

    /**
     * Test that operations don't interfere with each other
     */
    public function test_operation_isolation(): void
    {
        // Set up mocks to verify they receive correct calls
        $this->mockExporter
            ->expects($this->exactly(2))
            ->method('export')
            ->willReturn(['ok' => true]);

        $this->mockImporter
            ->expects($this->exactly(1))
            ->method('import')
            ->willReturn(['ok' => true]);

        // Perform operations
        $this->dbEngine->export_database($this->testJobId);
        $this->dbEngine->import_database($this->testJobId);
        $this->dbEngine->export_database($this->testJobId . '-2');

        // Verify no cross-contamination
        $this->assertTrue(true); // If we get here without exceptions, test passes
    }
}
