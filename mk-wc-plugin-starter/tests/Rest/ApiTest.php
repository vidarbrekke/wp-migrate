<?php
/**
 * REST API Tests
 *
 * Tests the critical API endpoints that handle migration operations.
 * These tests ensure proper authentication, request handling, and response formatting.
 */

namespace MK\WcPluginStarter\Tests\Rest;

use MK\WcPluginStarter\Rest\Api;
use MK\WcPluginStarter\Security\HmacAuth;
use MK\WcPluginStarter\Migration\DatabaseEngine;
use MK\WcPluginStarter\Files\ChunkStore;
use MK\WcPluginStarter\State\StateStore;
use MK\WcPluginStarter\Migration\JobManager;
use MK\WcPluginStarter\Tests\TestHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ApiTest extends TestCase
{
    private Api $api;
    private string $sharedKey;
    private string $peerUrl;
    private string $testJobId;

    /** @var MockObject&HmacAuth */
    private MockObject $mockAuth;

    /** @var MockObject&DatabaseEngine */
    private MockObject $mockDbEngine;

    /** @var MockObject&ChunkStore */
    private MockObject $mockChunkStore;

    protected function setUp(): void
    {
        parent::setUp();
        TestHelper::reset();

        $this->sharedKey = 'test-api-key-12345';
        $this->peerUrl = 'https://test.example.com';
        $this->testJobId = 'test-api-job-' . uniqid();

        // Create mocks
        $this->mockAuth = $this->createMock(HmacAuth::class);
        $this->mockDbEngine = $this->createMock(DatabaseEngine::class);
        $this->mockChunkStore = $this->createMock(ChunkStore::class);

        // Create API instance with mocks
        $this->api = new Api($this->mockAuth, $this->mockChunkStore, $this->mockDbEngine);

        // Inject JobManager with proper state store
        $reflection = new \ReflectionClass($this->api);
        $jobManagerProperty = $reflection->getProperty('jobs');
        $jobManagerProperty->setAccessible(true);
        $jobManagerProperty->setValue($this->api, new JobManager(new StateStore()));
    }

    protected function tearDown(): void
    {
        TestHelper::reset();
        parent::tearDown();
    }

    /**
     * Test successful handshake with valid authentication
     */
    public function test_handshake_success(): void
    {
        $body = '{"job_id":"test-123","capabilities":{"rsync":true}}';
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'POST', '/wp-json/migrate/v1/handshake', $body, $this->peerUrl);

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers, $body);

        // Mock successful authentication
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->with($this->equalTo($request))
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $result = $this->api->handshake($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(200, $result->get_status());

        $data = $result->get_data();
        $this->assertTrue($data['ok']);
        $this->assertArrayHasKey('site', $data);
        $this->assertArrayHasKey('capabilities', $data);
    }

    /**
     * Test handshake authentication failure
     */
    public function test_handshake_authentication_failure(): void
    {
        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake');

        // Mock authentication failure
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(new WP_Error('EAUTH', 'Authentication failed', ['status' => 401]));

        $result = $this->api->handshake($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(401, $result->get_status());

        $data = $result->get_data();
        $this->assertFalse($data['ok']);
        $this->assertEquals('EAUTH', $data['code']);
    }

    /**
     * Test successful database export command
     */
    public function test_db_export_success(): void
    {
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'POST', '/wp-json/migrate/v1/db/export', '', $this->peerUrl);

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/db/export', $headers);
        $request->set_param('job_id', $this->testJobId);
        $request->set_param('artifact', 'custom-export.sql.zst');

        // Mock authentication and database export
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $this->mockDbEngine
            ->expects($this->once())
            ->method('export_database')
            ->with($this->testJobId, 'custom-export.sql.zst')
            ->willReturn(['ok' => true, 'method' => 'mysqldump']);

        $result = $this->api->db_export($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(200, $result->get_status());

        $data = $result->get_data();
        $this->assertTrue($data['ok']);
        $this->assertEquals('mysqldump', $data['method']);
        $this->assertEquals('custom-export.sql.zst', $data['artifact']);
    }

    /**
     * Test database export failure
     */
    public function test_db_export_failure(): void
    {
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'POST', '/wp-json/migrate/v1/db/export', '', $this->peerUrl);

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/db/export', $headers);
        $request->set_param('job_id', $this->testJobId);

        // Mock authentication and database export failure
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $this->mockDbEngine
            ->expects($this->once())
            ->method('export_database')
            ->willReturn(['ok' => false, 'error' => 'Export failed']);

        $result = $this->api->db_export($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(500, $result->get_status());

        $data = $result->get_data();
        $this->assertFalse($data['ok']);
        $this->assertEquals('EDB_EXPORT_FAILED', $data['code']);
        $this->assertEquals('Export failed', $data['message']);
    }

    /**
     * Test database export with missing job_id
     */
    public function test_db_export_missing_job_id(): void
    {
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'POST', '/wp-json/migrate/v1/db/export', '', $this->peerUrl);

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/db/export', $headers);
        // Don't set job_id parameter

        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $result = $this->api->db_export($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(400, $result->get_status());

        $data = $result->get_data();
        $this->assertFalse($data['ok']);
        $this->assertEquals('EBAD_REQUEST', $data['code']);
        $this->assertEquals('job_id is required', $data['message']);
    }

    /**
     * Test command handling for db_import action
     */
    public function test_command_db_import_success(): void
    {
        $params = ['action' => 'db_import', 'job_id' => $this->testJobId, 'params' => ['artifact' => 'test-import.sql.zst']];
        $body = wp_json_encode($params);
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'POST', '/wp-json/migrate/v1/command', $body, $this->peerUrl);

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/command', $headers, $body);

        // Mock authentication and database import
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $this->mockDbEngine
            ->expects($this->once())
            ->method('import_database')
            ->with($this->testJobId, 'test-import.sql.zst')
            ->willReturn(['ok' => true, 'stats' => ['tables_created' => 10, 'tables_dropped' => 0]]);

        $result = $this->api->command($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(200, $result->get_status());

        $data = $result->get_data();
        $this->assertTrue($data['ok']);
        $this->assertEquals('db_imported', $data['state']);
        $this->assertArrayHasKey('stats', $data);
    }

    /**
     * Test command handling for search_replace action
     */
    public function test_command_search_replace_success(): void
    {
        $config = [
            'mode' => 'hybrid',
            'siteurl' => 'https://staging.example.com',
            'from_abs' => 'https://prod.example.com',
            'to_rel' => '/'
        ];

        $params = ['action' => 'search_replace', 'job_id' => $this->testJobId, 'params' => $config];
        $body = wp_json_encode($params);
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'POST', '/wp-json/migrate/v1/command', $body, $this->peerUrl);

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/command', $headers, $body);

        // Mock authentication and URL replacement
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $this->mockDbEngine
            ->expects($this->once())
            ->method('search_replace_urls')
            ->with($this->testJobId, $config)
            ->willReturn(['ok' => true, 'replacements' => 150]);

        $result = $this->api->command($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(200, $result->get_status());

        $data = $result->get_data();
        $this->assertTrue($data['ok']);
        $this->assertEquals('url_replaced', $data['state']);
        $this->assertEquals(150, $data['replacements']);
    }

    /**
     * Test command handling for finalize action
     */
    public function test_command_finalize_success(): void
    {
        // Use the existing JobManager instance from the API
        $jobManager = $this->api->get_job_manager();

        // Set the job through proper state transitions for finalization testing
        $jobManager->set_state($this->testJobId, 'preflight_ok', []);
        $jobManager->set_state($this->testJobId, 'files_pass1', []);
        $jobManager->set_state($this->testJobId, 'db_exported', []);
        $jobManager->set_state($this->testJobId, 'db_uploaded', []);
        $jobManager->set_state($this->testJobId, 'db_imported', []);
        $jobManager->set_state($this->testJobId, 'url_replaced', []);
        $jobManager->set_state($this->testJobId, 'files_pass2', []);
        $jobManager->set_state($this->testJobId, 'finalized', [
            'files_pass2_completed' => true,
            'url_replacement_completed' => true
        ]);

        $params = ['action' => 'finalize', 'job_id' => $this->testJobId, 'params' => []];
        $body = wp_json_encode($params);
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'POST', '/wp-json/migrate/v1/command', $body, $this->peerUrl);

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/command', $headers, $body);

        // Mock authentication
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $result = $this->api->command($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(200, $result->get_status());

        $data = $result->get_data();
        $this->assertTrue($data['ok']);
        $this->assertEquals('done', $data['state']);
        $this->assertStringContainsString('Migration completed successfully', $data['notes'][0]);
    }

    /**
     * Test command handling for unknown action
     */
    public function test_command_unknown_action(): void
    {
        $params = ['action' => 'unknown_action', 'job_id' => $this->testJobId, 'params' => []];
        $body = wp_json_encode($params);
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'POST', '/wp-json/migrate/v1/command', $body, $this->peerUrl);

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/command', $headers, $body);

        // Mock authentication
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $result = $this->api->command($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(400, $result->get_status());

        $data = $result->get_data();
        $this->assertFalse($data['ok']);
        $this->assertEquals('EUNKNOWN_ACTION', $data['code']);
        		$this->assertStringContainsString('Unknown action: unknown_action', $data['message']);
    }

    /**
     * Test chunk upload success
     */
    public function test_chunk_upload_success(): void
    {
        $chunkData = 'Test chunk data';
        $hash = base64_encode(hash('sha256', $chunkData, true));

        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'POST', '/wp-json/migrate/v1/chunk', $chunkData, $this->peerUrl);

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/chunk', $headers, $chunkData);
        $request->set_param('job_id', $this->testJobId);
        $request->set_param('artifact', 'test-chunk.txt');
        $request->set_param('index', 0);
        $request->set_param('sha256', $hash);

        // Mock authentication and chunk storage
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $this->mockChunkStore
            ->expects($this->once())
            ->method('save_chunk')
            ->with($this->testJobId, 'test-chunk.txt', 0, $chunkData, $hash);

        $result = $this->api->chunk($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(200, $result->get_status());

        $data = $result->get_data();
        $this->assertTrue($data['ok']);
        $this->assertEquals(['index' => 0], $data['received']);
    }

    /**
     * Test chunk listing
     */
    public function test_chunk_listing(): void
    {
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'GET', '/wp-json/migrate/v1/chunk', '', $this->peerUrl);

        $request = TestHelper::createMockRequest('GET', '/migrate/v1/chunk', $headers);
        $request->set_param('job_id', $this->testJobId);
        $request->set_param('artifact', 'test-chunks.txt');

        // Mock authentication and chunk listing
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $this->mockChunkStore
            ->expects($this->once())
            ->method('list_present')
            ->with($this->testJobId, 'test-chunks.txt')
            ->willReturn([0, 2, 5]);

        $result = $this->api->chunk($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(200, $result->get_status());

        $data = $result->get_data();
        $this->assertEquals([0, 2, 5], $data['present']);
        $this->assertEquals(6, $data['next']); // Next expected chunk
    }

    /**
     * Test progress endpoint
     */
    public function test_progress_endpoint(): void
    {
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'GET', '/wp-json/migrate/v1/progress', '', $this->peerUrl);

        $request = TestHelper::createMockRequest('GET', '/migrate/v1/progress', $headers);
        $request->set_param('job_id', $this->testJobId);

        // Mock authentication
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $result = $this->api->progress($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(200, $result->get_status());

        $data = $result->get_data();
        $this->assertArrayHasKey('job_id', $data);
        $this->assertArrayHasKey('state', $data);
        $this->assertArrayHasKey('steps', $data);
    }

    /**
     * Test logs tail endpoint
     */
    public function test_logs_tail_endpoint(): void
    {
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'GET', '/wp-json/migrate/v1/logs/tail', '', $this->peerUrl);

        $request = TestHelper::createMockRequest('GET', '/migrate/v1/logs/tail', $headers);
        $request->set_param('job_id', $this->testJobId);
        $request->set_param('n', 50);

        // Mock authentication
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $result = $this->api->logs_tail($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(200, $result->get_status());

        $data = $result->get_data();
        $this->assertArrayHasKey('lines', $data);
    }

    /**
     * Test error handling in authentication wrapper
     */
    public function test_authentication_wrapper_error_handling(): void
    {
        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake');

        // Mock authentication error
        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(new WP_Error('ETEST', 'Test authentication error', ['status' => 403]));

        $result = $this->api->handshake($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(403, $result->get_status());

        $data = $result->get_data();
        $this->assertFalse($data['ok']);
        $this->assertEquals('ETEST', $data['code']);
        $this->assertEquals('Test authentication error', $data['message']);
    }

    /**
     * Test different HTTP methods
     */
    public function test_different_http_methods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE'];

        // Set up mock to expect multiple calls
        $this->mockAuth
            ->expects($this->exactly(4))
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        foreach ($methods as $method) {
            $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, $method, '/wp-json/migrate/v1/handshake', '', $this->peerUrl);

            $request = TestHelper::createMockRequest($method, '/migrate/v1/handshake', $headers);

            $result = $this->api->handshake($request);

            $this->assertInstanceOf(WP_REST_Response::class, $result,
                "API should handle $method method properly");
        }
    }

    /**
     * Test concurrent requests
     */
    public function test_concurrent_requests(): void
    {
        $results = [];

        // Simulate 5 concurrent requests
        for ($i = 0; $i < 5; $i++) {
            $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'POST', '/wp-json/migrate/v1/handshake', '', $this->peerUrl);
            $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);

            $this->mockAuth
                ->expects($this->any())
                ->method('verify_request')
                ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce-' . $i, 'peer' => $this->peerUrl]);

            $results[] = $this->api->handshake($request);
        }

        // All should succeed
        foreach ($results as $result) {
            $this->assertInstanceOf(WP_REST_Response::class, $result);
            $data = $result->get_data();
            $this->assertTrue($data['ok']);
        }
    }

    /**
     * Test malformed JSON in request body
     */
    public function test_malformed_json_handling(): void
    {
        $malformedBody = '{"invalid": json content}';
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'POST', '/wp-json/migrate/v1/command', $malformedBody, $this->peerUrl);

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/command', $headers, $malformedBody);

        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $result = $this->api->command($request);

        // Should handle gracefully (may return unknown action or parse error)
        $this->assertInstanceOf(WP_REST_Response::class, $result);
    }

    /**
     * Test large request body handling
     */
    public function test_large_request_body(): void
    {
        $largeBody = str_repeat('a', 50000); // 50KB body
        $headers = TestHelper::generateLiveHmacHeaders($this->sharedKey, 'POST', '/wp-json/migrate/v1/handshake', $largeBody, $this->peerUrl);

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers, $largeBody);

        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(['ts' => time() * 1000, 'nonce' => 'test-nonce', 'peer' => $this->peerUrl]);

        $result = $this->api->handshake($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(200, $result->get_status());
    }

    /**
     * Test rate limiting simulation (nonce replay)
     */
    public function test_rate_limiting_simulation(): void
    {
        // This would require more complex setup with actual nonce storage
        // For now, we test that the API properly handles authentication errors

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake');

        $this->mockAuth
            ->expects($this->once())
            ->method('verify_request')
            ->willReturn(new WP_Error('ENONCE_REPLAY', 'Nonce replay detected', ['status' => 401]));

        $result = $this->api->handshake($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(401, $result->get_status());

        $data = $result->get_data();
        $this->assertFalse($data['ok']);
        $this->assertEquals('ENONCE_REPLAY', $data['code']);
    }
}
