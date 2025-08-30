<?php

namespace WpMigrate\Tests\Migration;

use PHPUnit\Framework\TestCase;
use WpMigrate\Migration\ErrorRecovery;

class ErrorRecoveryTest extends TestCase {
    private ErrorRecovery $errorRecovery;

    protected function setUp(): void {
        $this->errorRecovery = new ErrorRecovery();
    }

    public function testIsRecoverableErrorDetectsTimeoutErrors(): void {
        $this->assertTrue(
            $this->errorRecovery->is_recoverable_error('Connection timeout occurred'),
            'Should detect timeout as recoverable'
        );
    }

    public function testIsRecoverableErrorDetectsConnectionErrors(): void {
        $this->assertTrue(
            $this->errorRecovery->is_recoverable_error('Connection failed'),
            'Should detect connection errors as recoverable'
        );
    }

    public function testIsRecoverableErrorDetectsNetworkErrors(): void {
        $this->assertTrue(
            $this->errorRecovery->is_recoverable_error('Network is unreachable'),
            'Should detect network errors as recoverable'
        );
    }

    public function testIsRecoverableErrorDetectsHttpTimeout(): void {
        $this->assertTrue(
            $this->errorRecovery->is_recoverable_error('Request timeout', ['http_code' => 408]),
            'Should detect HTTP timeout as recoverable'
        );
    }

    public function testIsRecoverableErrorDetectsServerErrors(): void {
        $this->assertTrue(
            $this->errorRecovery->is_recoverable_error('Internal server error', ['http_code' => 500]),
            'Should detect 5xx server errors as recoverable'
        );
    }

    public function testIsRecoverableErrorDetectsDatabaseDeadlock(): void {
        $this->assertTrue(
            $this->errorRecovery->is_recoverable_error('Deadlock found', ['db_error_code' => '1213']),
            'Should detect database deadlock as recoverable'
        );
    }

    public function testIsRecoverableErrorRejectsNonRecoverableErrors(): void {
        $this->assertFalse(
            $this->errorRecovery->is_recoverable_error('Invalid credentials'),
            'Should reject authentication errors as non-recoverable'
        );
    }

    public function testIsRecoverableErrorRejectsSyntaxErrors(): void {
        $this->assertFalse(
            $this->errorRecovery->is_recoverable_error('Syntax error in SQL'),
            'Should reject syntax errors as non-recoverable'
        );
    }

    public function testCalculateBackoffDelayIncreasesWithAttempts(): void {
        $reflection = new \ReflectionClass($this->errorRecovery);
        $method = $reflection->getMethod('calculate_backoff_delay');
        $method->setAccessible(true);

        $firstDelay = $method->invoke($this->errorRecovery, 1);
        $secondDelay = $method->invoke($this->errorRecovery, 2);
        $thirdDelay = $method->invoke($this->errorRecovery, 3);

        $this->assertGreaterThan($firstDelay, $secondDelay, 'Backoff should increase with attempts');
        $this->assertGreaterThan($secondDelay, $thirdDelay, 'Backoff should continue increasing');
    }

    public function testCalculateBackoffDelayRespectsMaximum(): void {
        $reflection = new \ReflectionClass($this->errorRecovery);
        $method = $reflection->getMethod('calculate_backoff_delay');
        $method->setAccessible(true);

        // Test with a high attempt number
        $delay = $method->invoke($this->errorRecovery, 10);

        $this->assertLessThanOrEqual(900, $delay, 'Backoff should not exceed maximum');
    }

    public function testExecuteWithRetrySucceedsOnFirstAttempt(): void {
        $callCount = 0;
        $operation = function () use (&$callCount) {
            $callCount++;
            return 'success';
        };

        $result = $this->errorRecovery->execute_with_retry($operation, 'test-job', 'test-operation');

        $this->assertEquals('success', $result);
        $this->assertEquals(1, $callCount, 'Operation should only be called once on success');
    }

    public function testExecuteWithRetryRetriesRecoverableErrors(): void {
        $callCount = 0;
        $operation = function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new \RuntimeException('Connection timeout');
            }
            return 'success';
        };

        $result = $this->errorRecovery->execute_with_retry($operation, 'test-job', 'test-operation');

        $this->assertEquals('success', $result);
        $this->assertEquals(2, $callCount, 'Operation should be retried once on recoverable error');
    }

    public function testExecuteWithRetryFailsAfterMaxRetries(): void {
        $callCount = 0;
        $operation = function () use (&$callCount) {
            $callCount++;
            throw new \RuntimeException('Persistent connection timeout');
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Persistent connection timeout');

        try {
            $this->errorRecovery->execute_with_retry($operation, 'test-job', 'test-operation');
        } catch (\Throwable $e) {
            $this->assertEquals(3, $callCount, 'Operation should be attempted max retries times');
            throw $e;
        }
    }

    public function testExecuteWithRetryDoesNotRetryNonRecoverableErrors(): void {
        $callCount = 0;
        $operation = function () use (&$callCount) {
            $callCount++;
            throw new \RuntimeException('Invalid syntax in SQL query');
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid syntax in SQL query');

        try {
            $this->errorRecovery->execute_with_retry($operation, 'test-job', 'test-operation');
        } catch (\Throwable $e) {
            $this->assertEquals(1, $callCount, 'Non-recoverable errors should not be retried');
            throw $e;
        }
    }

    public function testShouldRetryJobWithMostlyRecoverableErrors(): void {
        $job = [
            'errors' => [
                ['message' => 'Connection timeout', 'context' => []],
                ['message' => 'Connection timeout', 'context' => []],
                ['message' => 'Connection timeout', 'context' => []],
                ['message' => 'Connection timeout', 'context' => []],
                ['message' => 'Invalid credentials', 'context' => []], // Non-recoverable
            ]
        ];

        $this->assertTrue(
            $this->errorRecovery->should_retry_job('test-job', $job),
            'Should retry when 80% of errors are recoverable'
        );
    }

    public function testShouldNotRetryJobWithMostlyNonRecoverableErrors(): void {
        $job = [
            'errors' => [
                ['message' => 'Invalid credentials', 'context' => []],
                ['message' => 'Invalid credentials', 'context' => []],
                ['message' => 'Invalid credentials', 'context' => []],
                ['message' => 'Connection timeout', 'context' => []], // Recoverable
                ['message' => 'Invalid credentials', 'context' => []],
            ]
        ];

        $this->assertFalse(
            $this->errorRecovery->should_retry_job('test-job', $job),
            'Should not retry when <60% of errors are recoverable'
        );
    }

    public function testScheduleRetryReturnsPositiveDelay(): void {
        $job = [
            'errors' => [
                ['message' => 'Connection timeout', 'context' => []],
            ]
        ];

        $delay = $this->errorRecovery->schedule_retry('test-job', $job);

        $this->assertGreaterThan(0, $delay, 'Retry delay should be positive');
        $this->assertLessThanOrEqual(900, $delay, 'Retry delay should not exceed maximum');
    }
}
