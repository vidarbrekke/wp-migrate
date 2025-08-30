<?php

namespace WpMigrate\Tests\Admin;

use PHPUnit\Framework\TestCase;
use WpMigrate\Admin\SettingsPage;
use WpMigrate\Migration\JobManager;
use WpMigrate\State\StateStore;
use WpMigrate\Migration\ErrorRecovery;

class SettingsPageTest extends TestCase {
    private SettingsPage $settingsPage;
    private JobManager $jobManager;
    private StateStore $stateStore;

    protected function setUp(): void {
        // Use real instances instead of mocks since StateStore is final
        $this->stateStore = new StateStore();
        $this->jobManager = new JobManager($this->stateStore, new ErrorRecovery());
        $this->settingsPage = new SettingsPage($this->jobManager);
    }

    public function testSettingsPageConstructor(): void {
        // Test that SettingsPage can be created with JobManager
        $this->assertInstanceOf(SettingsPage::class, $this->settingsPage);
    }

    public function testSettingsPageConstructorWithoutJobManager(): void {
        // Test that SettingsPage can be created without JobManager (should create its own)
        $settingsPage = new SettingsPage();
        $this->assertInstanceOf(SettingsPage::class, $settingsPage);
    }

    public function testGetStateStyle(): void {
        $reflection = new \ReflectionClass($this->settingsPage);
        $method = $reflection->getMethod('get_state_style');
        $method->setAccessible(true);

        // Test various states
        $this->assertEquals('background: #e3f2fd; color: #1976d2;', $method->invoke($this->settingsPage, 'created'));
        $this->assertEquals('background: #e8f5e8; color: #2e7d32;', $method->invoke($this->settingsPage, 'preflight_ok'));
        $this->assertEquals('background: #fff3e0; color: #f57c00;', $method->invoke($this->settingsPage, 'files_pass1'));
        $this->assertEquals('background: #ffebee; color: #c62828;', $method->invoke($this->settingsPage, 'error'));
        $this->assertEquals('background: #fce4ec; color: #ad1457;', $method->invoke($this->settingsPage, 'rollback'));
        $this->assertEquals('background: #e8f5e8; color: #2e7d32;', $method->invoke($this->settingsPage, 'done'));
        $this->assertEquals('background: #f5f5f5; color: #333;', $method->invoke($this->settingsPage, 'unknown_state'));
    }

    public function testGetProgressColor(): void {
        $reflection = new \ReflectionClass($this->settingsPage);
        $method = $reflection->getMethod('get_progress_color');
        $method->setAccessible(true);

        // Test progress color logic
        $this->assertEquals('#dc3545', $method->invoke($this->settingsPage, 10)); // Red for < 25%
        $this->assertEquals('#dc3545', $method->invoke($this->settingsPage, 20)); // Red for < 25%
        $this->assertEquals('#ffc107', $method->invoke($this->settingsPage, 30)); // Yellow for 25-50%
        $this->assertEquals('#ffc107', $method->invoke($this->settingsPage, 40)); // Yellow for 25-50%
        $this->assertEquals('#17a2b8', $method->invoke($this->settingsPage, 60)); // Blue for 50-75%
        $this->assertEquals('#17a2b8', $method->invoke($this->settingsPage, 70)); // Blue for 50-75%
        $this->assertEquals('#28a745', $method->invoke($this->settingsPage, 90)); // Green for > 75%
        $this->assertEquals('#28a745', $method->invoke($this->settingsPage, 100)); // Green for > 75%
    }

    public function testFormatTimestamp(): void {
        // Skip this test in non-WordPress environment since it depends on WordPress functions
        $this->markTestSkipped('Test requires WordPress environment for date formatting functions');

        // Note: This test would work in a WordPress environment with proper function mocks
        // but we're testing outside of WordPress context for simplicity
    }

    public function testGetSettingsReturnsArray(): void {
        $settings = $this->settingsPage->get_settings();
        $this->assertIsArray($settings);
    }

    public function testJobManagerIntegration(): void {
        // Test that the JobManager is properly accessible
        $reflection = new \ReflectionClass($this->settingsPage);
        $property = $reflection->getProperty('jobManager');
        $property->setAccessible(true);

        $jobManager = $property->getValue($this->settingsPage);
        $this->assertInstanceOf(JobManager::class, $jobManager);
    }

    public function testCanRollbackFromState(): void {
        // Test JobManager's can_rollback_from_state method through SettingsPage
        $reflection = new \ReflectionClass($this->settingsPage);
        $property = $reflection->getProperty('jobManager');
        $property->setAccessible(true);
        $jobManager = $property->getValue($this->settingsPage);

        // Test rollbackable states
        $this->assertTrue($jobManager->can_rollback_from_state('db_imported'));
        $this->assertTrue($jobManager->can_rollback_from_state('url_replaced'));
        $this->assertTrue($jobManager->can_rollback_from_state('files_pass2'));
        $this->assertTrue($jobManager->can_rollback_from_state('finalized'));
        $this->assertTrue($jobManager->can_rollback_from_state('error'));

        // Test non-rollbackable states
        $this->assertFalse($jobManager->can_rollback_from_state('created'));
        $this->assertFalse($jobManager->can_rollback_from_state('preflight_ok'));
        $this->assertFalse($jobManager->can_rollback_from_state('files_pass1'));
        $this->assertFalse($jobManager->can_rollback_from_state('done'));
    }

    public function testErrorRecoveryIntegration(): void {
        // Test that ErrorRecovery is properly integrated
        $reflection = new \ReflectionClass($this->settingsPage);
        $property = $reflection->getProperty('jobManager');
        $property->setAccessible(true);
        $jobManager = $property->getValue($this->settingsPage);

        $jobManagerReflection = new \ReflectionClass($jobManager);
        $errorRecoveryProperty = $jobManagerReflection->getProperty('errorRecovery');
        $errorRecoveryProperty->setAccessible(true);

        $errorRecovery = $errorRecoveryProperty->getValue($jobManager);
        $this->assertInstanceOf(ErrorRecovery::class, $errorRecovery);
    }

    public function testIsRecoverableErrorDetection(): void {
        // Test ErrorRecovery's is_recoverable_error method
        $reflection = new \ReflectionClass($this->settingsPage);
        $property = $reflection->getProperty('jobManager');
        $property->setAccessible(true);
        $jobManager = $property->getValue($this->settingsPage);

        $jobManagerReflection = new \ReflectionClass($jobManager);
        $errorRecoveryProperty = $jobManagerReflection->getProperty('errorRecovery');
        $errorRecoveryProperty->setAccessible(true);
        $errorRecovery = $errorRecoveryProperty->getValue($jobManager);

        // Test recoverable errors
        $this->assertTrue($errorRecovery->is_recoverable_error('Connection timeout occurred'));
        $this->assertTrue($errorRecovery->is_recoverable_error('Connection failed'));
        $this->assertTrue($errorRecovery->is_recoverable_error('Network is unreachable'));
        $this->assertTrue($errorRecovery->is_recoverable_error('Lock wait timeout', ['db_error_code' => '1205']));
        $this->assertTrue($errorRecovery->is_recoverable_error('Deadlock found', ['db_error_code' => '1213']));
        $this->assertTrue($errorRecovery->is_recoverable_error('Internal server error', ['http_code' => 500]));
        $this->assertTrue($errorRecovery->is_recoverable_error('Request timeout', ['http_code' => 408]));

        // Test non-recoverable errors
        $this->assertFalse($errorRecovery->is_recoverable_error('Invalid credentials'));
        $this->assertFalse($errorRecovery->is_recoverable_error('Syntax error in SQL'));
        $this->assertFalse($errorRecovery->is_recoverable_error('Access denied'));
    }

    public function testExecuteWithRetryMethod(): void {
        // Test that JobManager has the execute_with_retry method
        $reflection = new \ReflectionClass($this->settingsPage);
        $property = $reflection->getProperty('jobManager');
        $property->setAccessible(true);
        $jobManager = $property->getValue($this->settingsPage);

        $this->assertTrue(method_exists($jobManager, 'execute_with_retry'));
        $this->assertTrue(method_exists($jobManager, 'should_retry_job'));
        $this->assertTrue(method_exists($jobManager, 'schedule_automatic_retry'));
        $this->assertTrue(method_exists($jobManager, 'get_retry_stats'));
    }
}
