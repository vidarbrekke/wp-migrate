<?php
/**
 * End-to-End Migration Workflow Tests
 *
 * Tests the complete migration workflow from start to finish,
 * validating state machine transitions, error handling, and rollback.
 */

namespace MK\WcPluginStarter\Tests\Migration;

use MK\WcPluginStarter\Migration\JobManager;
use MK\WcPluginStarter\State\StateStore;
use MK\WcPluginStarter\Tests\TestHelper;
use PHPUnit\Framework\TestCase;

class MigrationWorkflowTest extends TestCase
{
    private JobManager $jobManager;
    private string $testJobId;

    protected function setUp(): void
    {
        parent::setUp();
        TestHelper::reset();

        $this->jobManager = new JobManager(new StateStore());
        $this->testJobId = 'e2e-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        TestHelper::reset();
        parent::tearDown();
    }

    /**
     * Test complete successful migration workflow
     */
    public function test_complete_migration_workflow(): void
    {
        // 1. Job creation
        $job = $this->jobManager->get_or_create($this->testJobId);
        $this->assertEquals('created', $job['state']);
        $this->assertEquals(0, $job['progress']);
        $this->assertArrayHasKey('created_at', $job);
        $this->assertArrayHasKey('steps', $job);

        // 2. Preflight check
        $job = $this->jobManager->set_state($this->testJobId, 'preflight_ok', [
            'wp_version' => '6.4.0',
            'capabilities' => ['rsync' => true, 'zstd' => true]
        ]);
        $this->assertEquals('preflight_ok', $job['state']);
        $this->assertEquals(10, $job['progress']);
        $this->assertCount(1, $job['steps']);

        // 3. File synchronization (first pass)
        $job = $this->jobManager->set_state($this->testJobId, 'files_pass1', [
            'files_synced' => 1250,
            'total_size' => '2.3GB'
        ]);
        $this->assertEquals('files_pass1', $job['state']);
        $this->assertEquals(25, $job['progress']);

        // 4. Database export
        $job = $this->jobManager->set_state($this->testJobId, 'db_exported', [
            'method' => 'mysqldump',
            'exported_at' => gmdate('c'),
            'compressed_size' => '45MB'
        ]);
        $this->assertEquals('db_exported', $job['state']);
        $this->assertEquals(40, $job['progress']);

        // 5. Database upload
        $job = $this->jobManager->set_state($this->testJobId, 'db_uploaded', [
            'chunks_uploaded' => 12,
            'upload_speed' => '5.2MB/s'
        ]);
        $this->assertEquals('db_uploaded', $job['state']);
        $this->assertEquals(55, $job['progress']);

        // 6. Database import
        $job = $this->jobManager->set_state($this->testJobId, 'db_imported', [
            'tables_created' => 25,
            'tables_updated' => 8,
            'import_time' => '45s'
        ]);
        $this->assertEquals('db_imported', $job['state']);
        $this->assertEquals(70, $job['progress']);

        // 7. URL replacement
        $job = $this->jobManager->set_state($this->testJobId, 'url_replaced', [
            'replacements' => 1250,
            'from_url' => 'https://prod.example.com',
            'to_url' => 'https://staging.example.com'
        ]);
        $this->assertEquals('url_replaced', $job['state']);
        $this->assertEquals(85, $job['progress']);

        // 8. File synchronization (second pass)
        $job = $this->jobManager->set_state($this->testJobId, 'files_pass2', [
            'files_synced' => 45,
            'total_size' => '120MB'
        ]);
        $this->assertEquals('files_pass2', $job['state']);
        $this->assertEquals(95, $job['progress']);

        // 9. Finalization
        $job = $this->jobManager->set_state($this->testJobId, 'finalized', [
            'cleanup_completed' => true,
            'maintenance_mode_removed' => true
        ]);
        $this->assertEquals('finalized', $job['state']);
        $this->assertEquals(98, $job['progress']);

        // 10. Completion
        $job = $this->jobManager->set_state($this->testJobId, 'done', [
            'total_migration_time' => '12m 30s',
            'final_status' => 'success'
        ]);
        $this->assertEquals('done', $job['state']);
        $this->assertEquals(100, $job['progress']);

        // Validate final state
        $progress = $this->jobManager->get_progress($this->testJobId);
        $this->assertEquals('done', $progress['state']);
        $this->assertEquals(100, $progress['progress']);
        $this->assertTrue($progress['is_valid']);
        $this->assertFalse($progress['can_rollback']); // Terminal state
        $this->assertCount(9, $progress['steps']); // 9 transitions from created to done
    }

    /**
     * Test workflow with error recovery
     */
    public function test_workflow_with_error_recovery(): void
    {
        // Start migration
        $this->jobManager->set_state($this->testJobId, 'preflight_ok');
        $this->jobManager->set_state($this->testJobId, 'files_pass1');
        $this->jobManager->set_state($this->testJobId, 'db_exported');
        $this->jobManager->set_state($this->testJobId, 'db_uploaded'); // Need this state for db_imported

        // Encounter error
        $this->jobManager->set_state($this->testJobId, 'error', [
            'error_code' => 'EDB_CONNECTION_FAILED',
            'error_message' => 'Database connection timeout'
        ]);

        // Validate error state
        $progress = $this->jobManager->get_progress($this->testJobId);
        $this->assertEquals('error', $progress['state']);
        $this->assertTrue($progress['can_rollback']);

        // Record additional error details
        $this->jobManager->record_error($this->testJobId, 'Connection timeout after 30 seconds', [
            'retry_count' => 3,
            'last_attempt' => gmdate('c')
        ]);

        // Check that error was recorded
        $progress = $this->jobManager->get_progress($this->testJobId);
        $this->assertCount(1, $progress['errors']);
        $this->assertEquals('Connection timeout after 30 seconds', $progress['errors'][0]['message']);

        // Initiate rollback
        $this->jobManager->set_state($this->testJobId, 'rollback', [
            'rollback_reason' => 'Database connection failure'
        ]);

        // Complete rollback
        $this->jobManager->set_state($this->testJobId, 'rolled_back', [
            'files_restored' => 1250,
            'database_rolled_back' => true
        ]);

        // Restart migration
        $this->jobManager->set_state($this->testJobId, 'created', [
            'restart_reason' => 'After successful rollback'
        ]);

        // Validate restart
        $progress = $this->jobManager->get_progress($this->testJobId);
        $this->assertEquals('created', $progress['state']);
        $this->assertEquals(0, $progress['progress']);
        $this->assertCount(8, $progress['steps']); // All transitions recorded
    }

    /**
     * Test invalid state transitions are rejected
     */
    public function test_invalid_state_transitions_rejected(): void
    {
        // Start with valid transition
        $this->jobManager->set_state($this->testJobId, 'preflight_ok');

        // Try invalid transition (skip required intermediate states)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid state transition from 'preflight_ok' to 'done'");

        $this->jobManager->set_state($this->testJobId, 'done');
    }

    /**
     * Test rollback validation
     */
    public function test_rollback_validation(): void
    {
        // Test states that should allow rollback
        $rollbackStates = ['db_imported', 'url_replaced', 'files_pass2', 'finalized', 'error'];

        foreach ($rollbackStates as $state) {
            $this->assertTrue(
                $this->jobManager->can_rollback_from_state($state),
                "State '{$state}' should allow rollback"
            );
        }

        // Test states that should NOT allow rollback
        $noRollbackStates = ['created', 'preflight_ok', 'files_pass1', 'db_exported', 'done'];

        foreach ($noRollbackStates as $state) {
            $this->assertFalse(
                $this->jobManager->can_rollback_from_state($state),
                "State '{$state}' should NOT allow rollback"
            );
        }
    }

    /**
     * Test progress calculation accuracy
     */
    public function test_progress_calculation(): void
    {
        // Test basic progress calculation with a simple transition
        $testJobId = 'progress-test-' . uniqid();
        $this->jobManager->get_or_create($testJobId);
        $this->jobManager->set_state($testJobId, 'preflight_ok');

        $progress = $this->jobManager->get_progress($testJobId);
        $this->assertEquals(10, $progress['progress'], 'Progress should be 10% for preflight_ok state');

        // Test done state
        $this->jobManager->set_state($testJobId, 'files_pass1');
        $this->jobManager->set_state($testJobId, 'db_exported');
        $this->jobManager->set_state($testJobId, 'db_uploaded');
        $this->jobManager->set_state($testJobId, 'db_imported');
        $this->jobManager->set_state($testJobId, 'url_replaced');
        $this->jobManager->set_state($testJobId, 'files_pass2');
        $this->jobManager->set_state($testJobId, 'finalized');
        $this->jobManager->set_state($testJobId, 'done');

        $progress = $this->jobManager->get_progress($testJobId);
        $this->assertEquals(100, $progress['progress'], 'Progress should be 100% for done state');
    }

    /**
     * Test metadata accumulation
     */
    public function test_metadata_accumulation(): void
    {
        // Set initial metadata
        $this->jobManager->set_state($this->testJobId, 'preflight_ok', [
            'wp_version' => '6.4.0',
            'php_version' => '8.2.0'
        ]);

        // Add more metadata in subsequent state
        $this->jobManager->set_state($this->testJobId, 'files_pass1', [
            'total_files' => 1250,
            'total_size' => '2.3GB'
        ]);

        // Add even more metadata
        $this->jobManager->set_state($this->testJobId, 'db_exported', [
            'export_method' => 'mysqldump',
            'compression' => 'zstd'
        ]);

        $progress = $this->jobManager->get_progress($this->testJobId);

        // Verify all metadata is preserved
        $this->assertEquals('6.4.0', $progress['metadata']['wp_version']);
        $this->assertEquals('8.2.0', $progress['metadata']['php_version']);
        $this->assertEquals(1250, $progress['metadata']['total_files']);
        $this->assertEquals('2.3GB', $progress['metadata']['total_size']);
        $this->assertEquals('mysqldump', $progress['metadata']['export_method']);
        $this->assertEquals('zstd', $progress['metadata']['compression']);
    }
}
