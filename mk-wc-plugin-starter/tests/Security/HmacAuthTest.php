<?php
/**
 * HMAC Authentication Tests
 *
 * Tests the critical security component that protects all API endpoints.
 * These tests ensure authentication works correctly and security is maintained.
 */

namespace MK\WcPluginStarter\Tests\Security;

use MK\WcPluginStarter\Security\HmacAuth;
use MK\WcPluginStarter\Tests\TestHelper;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_REST_Request;

class HmacAuthTest extends TestCase
{
    private HmacAuth $auth;
    private string $sharedKey;
    private string $peerUrl;

    protected function setUp(): void
    {
        parent::setUp();
        TestHelper::reset();

        $this->sharedKey = 'test-shared-key-12345';
        $this->peerUrl = 'https://test.example.com';

        $this->auth = new HmacAuth(TestHelper::createMockSettingsProvider($this->sharedKey, $this->peerUrl));
    }

    protected function tearDown(): void
    {
        TestHelper::reset();
        parent::tearDown();
    }

    /**
     * Test successful authentication with valid headers
     */
    public function test_verify_request_success(): void
    {
        $body = '{"job_id":"test-123","capabilities":{"rsync":true}}';
        $headers = TestHelper::generateValidHmacHeaders(
            $this->sharedKey,
            'POST',
            '/wp-json/migrate/v1/handshake',
            $body,
            $this->peerUrl
        );

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers, $body);

        $result = $this->auth->verify_request($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ts', $result);
        $this->assertArrayHasKey('nonce', $result);
        $this->assertArrayHasKey('peer', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertEquals('/wp-json/migrate/v1/handshake', $result['path']);
    }

    /**
     * Test authentication failure with missing shared key
     */
    public function test_verify_request_missing_shared_key(): void
    {
        $auth = new HmacAuth(function() {
            return ['shared_key' => '', 'peer_url' => $this->peerUrl];
        });

        $request = TestHelper::createMockRequest();

        $result = $auth->verify_request($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('EAUTH', $result->get_error_code());
        $this->assertEquals(401, $result->get_error_data()['status']);
    }

    /**
     * Test authentication failure with missing headers
     */
    public function test_verify_request_missing_headers(): void
    {
        $request = TestHelper::createMockRequest();

        $result = $this->auth->verify_request($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('EAUTH', $result->get_error_code());
        		$this->assertStringContainsString('Missing auth headers', $result->get_error_message());
    }

    /**
     * Test authentication failure with invalid timestamp (too old)
     */
    public function test_verify_request_timestamp_skew(): void
    {
        $oldTimestamp = (int) (microtime(true) * 1000) - (10 * 60 * 1000); // 10 minutes ago
        $headers = TestHelper::generateValidHmacHeaders(
            $this->sharedKey,
            'POST',
            '/wp-json/migrate/v1/handshake',
            '',
            $this->peerUrl,
            $oldTimestamp
        );

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);

        $result = $this->auth->verify_request($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('ETS_SKEW', $result->get_error_code());
    }

    /**
     * Test authentication failure with invalid timestamp (future)
     */
    public function test_verify_request_future_timestamp(): void
    {
        $futureTimestamp = (int) (microtime(true) * 1000) + (10 * 60 * 1000); // 10 minutes in future
        $headers = TestHelper::generateValidHmacHeaders(
            $this->sharedKey,
            'POST',
            '/wp-json/migrate/v1/handshake',
            '',
            $this->peerUrl,
            $futureTimestamp
        );

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);

        $result = $this->auth->verify_request($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('ETS_SKEW', $result->get_error_code());
    }

    /**
     * Test nonce replay protection
     */
    public function test_verify_request_nonce_replay(): void
    {
        $headers = TestHelper::generateValidHmacHeaders($this->sharedKey);

        // First request should succeed
        $request1 = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);
        $result1 = $this->auth->verify_request($request1);
        $this->assertIsArray($result1);

        // Second request with same nonce should fail
        $request2 = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);
        $result2 = $this->auth->verify_request($request2);
        $this->assertInstanceOf(WP_Error::class, $result2);
        $this->assertEquals('ENONCE_REPLAY', $result2->get_error_code());
    }

    /**
     * Test authentication failure with invalid signature
     */
    public function test_verify_request_invalid_signature(): void
    {
        $headers = TestHelper::generateValidHmacHeaders($this->sharedKey);
        $headers['x-mig-signature'] = 'invalid-signature-123'; // Tamper with signature

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);

        $result = $this->auth->verify_request($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('EAUTH', $result->get_error_code());
        		$this->assertStringContainsString('Invalid signature', $result->get_error_message());
    }

    /**
     * Test peer URL validation
     */
    public function test_verify_request_peer_mismatch(): void
    {
        $headers = TestHelper::generateValidHmacHeaders(
            $this->sharedKey,
            'POST',
            '/wp-json/migrate/v1/handshake',
            '',
            'https://wrong-peer.com' // Different peer
        );

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);

        $result = $this->auth->verify_request($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('EAUTH', $result->get_error_code());
        		$this->assertStringContainsString('Peer mismatch', $result->get_error_message());
    }

    /**
     * Test peer URL validation with trailing slashes
     */
    public function test_verify_request_peer_normalization(): void
    {
        $headers = TestHelper::generateValidHmacHeaders(
            $this->sharedKey,
            'POST',
            '/wp-json/migrate/v1/handshake',
            '',
            'https://test.example.com/' // With trailing slash
        );

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);

        $result = $this->auth->verify_request($request);

        $this->assertIsArray($result); // Should succeed due to normalization
    }

    /**
     * Test TLS requirement
     */
    public function test_verify_request_tls_required(): void
    {
        TestHelper::set_ssl(false); // Disable SSL

        // Mock $_SERVER for non-SSL request
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
        $_SERVER['HTTP_X_FORWARDED_SSL'] = 'off';

        $headers = TestHelper::generateValidHmacHeaders($this->sharedKey);
        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);

        $result = $this->auth->verify_request($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('EUPGRADE_REQUIRED', $result->get_error_code());
        $this->assertEquals(426, $result->get_error_data()['status']);
    }

    /**
     * Test proxy SSL detection
     */
    public function test_verify_request_proxy_ssl_detection(): void
    {
        TestHelper::set_ssl(false); // Disable direct SSL

        // Mock proxy headers indicating SSL
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['HTTP_X_FORWARDED_SSL'] = 'on';

        $headers = TestHelper::generateValidHmacHeaders($this->sharedKey);
        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);

        $result = $this->auth->verify_request($request);

        $this->assertIsArray($result); // Should succeed with proxy SSL
    }

    /**
     * Test different HTTP methods
     */
    public function test_verify_request_different_methods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($methods as $method) {
            $headers = TestHelper::generateValidHmacHeaders(
                $this->sharedKey,
                $method,
                '/wp-json/migrate/v1/handshake'
            );

            $request = TestHelper::createMockRequest($method, '/migrate/v1/handshake', $headers);

            $result = $this->auth->verify_request($request);

            $this->assertIsArray($result, "Authentication should work for $method method");
        }
    }

    /**
     * Test path normalization
     */
    public function test_verify_request_path_normalization(): void
    {
        $headers = TestHelper::generateValidHmacHeaders(
            $this->sharedKey,
            'POST',
            '/wp-json/migrate/v1/handshake',
            '',
            $this->peerUrl
        );

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);

        $result = $this->auth->verify_request($request);

        $this->assertIsArray($result);
        $this->assertEquals('/wp-json/migrate/v1/handshake', $result['path']);
    }

    /**
     * Test empty body handling
     */
    public function test_verify_request_empty_body(): void
    {
        $headers = TestHelper::generateValidHmacHeaders(
            $this->sharedKey,
            'POST',
            '/wp-json/migrate/v1/handshake',
            '' // Empty body
        );

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers, '');

        $result = $this->auth->verify_request($request);

        $this->assertIsArray($result);
    }

    /**
     * Test large body handling
     */
    public function test_verify_request_large_body(): void
    {
        $largeBody = str_repeat('a', 100000); // 100KB body
        $headers = TestHelper::generateValidHmacHeaders(
            $this->sharedKey,
            'POST',
            '/wp-json/migrate/v1/handshake',
            $largeBody
        );

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers, $largeBody);

        $result = $this->auth->verify_request($request);

        $this->assertIsArray($result);
    }

    /**
     * Test concurrent requests with different nonces
     */
    public function test_verify_request_concurrent_different_nonces(): void
    {
        $results = [];

        // Simulate 5 concurrent requests
        for ($i = 0; $i < 5; $i++) {
            $headers = TestHelper::generateValidHmacHeaders($this->sharedKey);
            $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);
            $results[] = $this->auth->verify_request($request);
        }

        // All should succeed
        foreach ($results as $result) {
            $this->assertIsArray($result);
        }
    }

    /**
     * Test malformed headers
     */
    public function test_verify_request_malformed_headers(): void
    {
        $headers = TestHelper::generateValidHmacHeaders($this->sharedKey);
        $headers['x-mig-timestamp'] = 'invalid-timestamp'; // Non-numeric timestamp

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $headers);

        $result = $this->auth->verify_request($request);

        $this->assertInstanceOf(WP_Error::class, $result);
    }

    /**
     * Test header case insensitivity
     */
    public function test_verify_request_header_case_insensitivity(): void
    {
        $headers = TestHelper::generateValidHmacHeaders($this->sharedKey);

        // Convert headers to uppercase
        $upperHeaders = [];
        foreach ($headers as $key => $value) {
            $upperHeaders[strtoupper($key)] = $value;
        }

        $request = TestHelper::createMockRequest('POST', '/migrate/v1/handshake', $upperHeaders);

        $result = $this->auth->verify_request($request);

        $this->assertIsArray($result); // Should work despite case differences
    }
}
