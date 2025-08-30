<?php
namespace WpMigrate\Tests\Unit\Migration;

use WpMigrate\Migration\JobManager;
use WpMigrate\State\StateStore;
use PHPUnit\Framework\TestCase;

class JobManagerTest extends TestCase
{
    private JobManager $jobManager;
    private StateStore $stateStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateStore = $this->createMock(StateStore::class);
        $this->jobManager = new JobManager($this->stateStore);
    }

    public function testGetOrCreateCreatesNewJob(): void
    {
        $jobId = 'test-job-123';

        $this->stateStore->expects($this->once())
            ->method('get_job')
            ->with($jobId)
            ->willReturn([]); // Empty array means job doesn't exist

        $this->stateStore->expects($this->once())
            ->method('put_job')
            ->with($jobId, $this->callback(function ($job) use ($jobId) {
                return $job['job_id'] === $jobId
                    && $job['state'] === 'created'
                    && isset($job['created_at'])
                    && isset($job['updated_at']);
            }));

        $job = $this->jobManager->get_or_create($jobId);

        $this->assertEquals($jobId, $job['job_id']);
        $this->assertEquals('created', $job['state']);
        $this->assertIsArray($job['steps']);
        $this->assertIsArray($job['errors']);
        $this->assertIsArray($job['metadata']);
    }

    public function testGetOrCreateReturnsExistingJob(): void
    {
        $jobId = 'existing-job-456';
        $existingJob = [
            'job_id' => $jobId,
            'state' => 'preflight_ok',
            'steps' => [],
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
            'progress' => 10,
            'errors' => [],
            'metadata' => ['test' => 'data']
        ];

        $this->stateStore->expects($this->once())
            ->method('get_job')
            ->with($jobId)
            ->willReturn($existingJob);

        $this->stateStore->expects($this->never())
            ->method('put_job');

        $job = $this->jobManager->get_or_create($jobId);

        $this->assertEquals($existingJob, $job);
    }

    public function testSetStateValidTransition(): void
    {
        $jobId = 'transition-job-789';
        $currentJob = [
            'job_id' => $jobId,
            'state' => 'created',
            'steps' => [],
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
            'progress' => 0,
            'errors' => [],
            'metadata' => []
        ];

        $this->stateStore->expects($this->once())
            ->method('get_job')
            ->with($jobId)
            ->willReturn($currentJob);

        $this->stateStore->expects($this->once())
            ->method('put_job')
            ->with($jobId, $this->callback(function ($job) use ($jobId) {
                return $job['state'] === 'preflight_ok'
                    && count($job['steps']) === 1
                    && $job['progress'] === 10
                    && isset($job['metadata']['test']);
            }));

        $result = $this->jobManager->set_state($jobId, 'preflight_ok', ['test' => 'metadata']);

        $this->assertEquals($jobId, $result['job_id']);
        $this->assertEquals('preflight_ok', $result['state']);
        $this->assertEquals(10, $result['progress']);
    }

    public function testSetStateInvalidTransition(): void
    {
        $jobId = 'invalid-transition-job';
        $currentJob = [
            'job_id' => $jobId,
            'state' => 'created',
            'steps' => [],
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
            'progress' => 0,
            'errors' => [],
            'metadata' => []
        ];

        $this->stateStore->expects($this->once())
            ->method('get_job')
            ->with($jobId)
            ->willReturn($currentJob);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid state transition from \'created\' to \'done\'');

        $this->jobManager->set_state($jobId, 'done');
    }

    public function testGetProgress(): void
    {
        $jobId = 'progress-job-101';
        $job = [
            'job_id' => $jobId,
            'state' => 'db_imported',
            'steps' => [
                ['from_state' => 'created', 'to_state' => 'preflight_ok', 'timestamp' => '2025-01-01T00:00:00Z']
            ],
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
            'progress' => 70,
            'errors' => [],
            'metadata' => ['imported_rows' => 1000]
        ];

        $this->stateStore->expects($this->once())
            ->method('get_job')
            ->with($jobId)
            ->willReturn($job);

        $progress = $this->jobManager->get_progress($jobId);

        $this->assertEquals($jobId, $progress['job_id']);
        $this->assertEquals('db_imported', $progress['state']);
        $this->assertEquals(70, $progress['progress']);
        $this->assertCount(1, $progress['steps']);
        $this->assertTrue($progress['is_valid']);
        $this->assertTrue($progress['can_rollback']);
    }

    public function testRecordError(): void
    {
        $jobId = 'error-job-202';
        $currentJob = [
            'job_id' => $jobId,
            'state' => 'files_pass1',
            'steps' => [],
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
            'progress' => 25,
            'errors' => [],
            'metadata' => []
        ];

        $this->stateStore->expects($this->once())
            ->method('get_job')
            ->with($jobId)
            ->willReturn($currentJob);

        $this->stateStore->expects($this->once())
            ->method('put_job')
            ->with($jobId, $this->callback(function ($job) {
                return count($job['errors']) === 1
                    && $job['errors'][0]['message'] === 'Database connection failed'
                    && isset($job['errors'][0]['timestamp']);
            }));

        $this->jobManager->record_error($jobId, 'Database connection failed', ['host' => 'localhost']);
    }

    public function testCanRollbackFromState(): void
    {
        $this->assertTrue($this->jobManager->can_rollback_from_state('db_imported'));
        $this->assertTrue($this->jobManager->can_rollback_from_state('error'));
        $this->assertFalse($this->jobManager->can_rollback_from_state('created'));
        $this->assertFalse($this->jobManager->can_rollback_from_state('done'));
    }

    public function testCalculateProgress(): void
    {
        // Test various states and their expected progress values
        $testCases = [
            'created' => 0,
            'preflight_ok' => 10,
            'files_pass1' => 25,
            'db_exported' => 40,
            'db_uploaded' => 55,
            'db_imported' => 70,
            'url_replaced' => 85,
            'files_pass2' => 95,
            'finalized' => 98,
            'done' => 100,
            'error' => 0,
            'rollback' => 0,
            'rolled_back' => 0,
        ];

        // Test progress calculation for each state using reflection to access protected method
        $reflection = new \ReflectionClass($this->jobManager);
        $calculateProgressMethod = $reflection->getMethod('calculate_progress');
        $calculateProgressMethod->setAccessible(true);

        foreach ($testCases as $state => $expectedProgress) {
            $calculatedProgress = $calculateProgressMethod->invoke($this->jobManager, $state);
            $this->assertEquals($expectedProgress, $calculatedProgress,
                "Progress for state '{$state}' should be {$expectedProgress}, got {$calculatedProgress}");
        }
    }
}
