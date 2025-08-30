<?php
/**
 * Test Helper Class
 *
 * Provides utilities for testing WP-Migrate functionality.
 * Includes mocks, fixtures, and test data generation.
 */

namespace MK\WcPluginStarter\Tests;

use PHPUnit\Framework\TestCase;

class TestHelper
{
    /** @var array<string,mixed> */
    private static array $transients = [];

    /** @var array<string,mixed> */
    private static array $options = [];

    /** @var bool */
    private static bool $isSSL = true;

    /** @var array<string,mixed> */
    private static array $mockWpdb = [];

    /**
     * Reset all test state between tests
     */
    public static function reset(): void
    {
        self::$transients = [];
        self::$options = [];
        self::$isSSL = true;
        self::$mockWpdb = [];
    }

    /**
     * Mock WordPress transient functions
     */
    public static function get_transient(string $key): mixed
    {
        return self::$transients[$key] ?? false;
    }

    public static function set_transient(string $key, mixed $value, int $expiration = 0): bool
    {
        self::$transients[$key] = $value;
        return true;
    }

    /**
     * Mock WordPress option functions
     */
    public static function get_option(string $key, mixed $default = false): mixed
    {
        return self::$options[$key] ?? $default;
    }

    public static function update_option(string $key, mixed $value, bool $autoload = true): bool
    {
        self::$options[$key] = $value;
        return true;
    }

    /**
     * Control SSL state for testing
     */
    public static function set_ssl(bool $isSSL): void
    {
        self::$isSSL = $isSSL;
    }

    public static function is_ssl(): bool
    {
        return self::$isSSL;
    }

    /**
     * Create mock REST request
     */
    public static function createMockRequest(
        string $method = 'POST',
        string $route = '/migrate/v1/handshake',
        array $headers = [],
        string $body = '',
        array $params = []
    ): \WP_REST_Request {
        $request = new \WP_REST_Request($method, $route);

        // Set headers
        foreach ($headers as $key => $value) {
            $request->set_header($key, $value);
        }

        // Set body and parse JSON if it's JSON
        if (!empty($body)) {
            $request->set_body($body);
            // If body looks like JSON, parse it for get_json_params()
            if (self::isJson($body)) {
                $jsonParams = json_decode($body, true);
                if (is_array($jsonParams)) {
                    foreach ($jsonParams as $key => $value) {
                        $request->set_param($key, $value);
                    }
                }
            }
        }

        // Set query parameters (these override JSON params if they conflict)
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        return $request;
    }

    /**
     * Check if string is valid JSON
     */
    private static function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Create mock settings provider
     */
    public static function createMockSettingsProvider(
        string $sharedKey = 'test-key-123',
        string $peerUrl = 'https://test.example.com'
    ): callable {
        return function () use ($sharedKey, $peerUrl) {
            return [
                'shared_key' => $sharedKey,
                'peer_url' => $peerUrl,
            ];
        };
    }

    /**
     * Generate valid HMAC headers for testing
     */
    public static function generateValidHmacHeaders(
        string $sharedKey,
        string $method = 'POST',
        string $path = '/wp-json/migrate/v1/handshake',
        string $body = '',
        string $peer = 'https://test.example.com',
        ?int $timestamp = null
    ): array {
        // Generate timestamp that's guaranteed to be current and within skew tolerance
        // Use server time to avoid skew issues in staging/production
        if ($timestamp === null) {
            // Get current server time in milliseconds
            $timestamp = (int) (time() * 1000);
        }
        
        $nonce = bin2hex(random_bytes(8));
        $bodyHash = hash('sha256', $body);

        $payload = $timestamp . "\n" . $nonce . "\n" . $method . "\n" . $path . "\n" . $bodyHash;
        $signature = base64_encode(hash_hmac('sha256', $payload, $sharedKey, true));

        return [
            'x-mig-timestamp' => (string) $timestamp,
            'x-mig-nonce' => $nonce,
            'x-mig-peer' => $peer,
            'x-mig-signature' => $signature,
        ];
    }

    /**
     * Generate HMAC headers with current timestamp for live testing
     */
    public static function generateLiveHmacHeaders(
        string $sharedKey,
        string $method = 'POST',
        string $path = '/wp-json/migrate/v1/handshake',
        string $body = '',
        string $peer = 'https://test.example.com'
    ): array {
        // Always use current server time for live testing
        $timestamp = (int) (time() * 1000);
        
        $nonce = bin2hex(random_bytes(8));
        $bodyHash = hash('sha256', $body);

        $payload = $timestamp . "\n" . $nonce . "\n" . $method . "\n" . $path . "\n" . $bodyHash;
        $signature = base64_encode(hash_hmac('sha256', $payload, $sharedKey, true));

        return [
            'x-mig-timestamp' => (string) $timestamp,
            'x-mig-nonce' => $nonce,
            'x-mig-peer' => $peer,
            'x-mig-signature' => $signature,
        ];
    }

    /**
     * Create test database configuration
     */
    public static function createTestDbConfig(): array
    {
        return [
            'host' => 'localhost',
            'user' => 'test_user',
            'password' => 'test_password',
            'name' => 'test_database',
            'port' => '3306',
        ];
    }

    /**
     * Create mock WordPress database object
     */
    public static function createMockWpdb(array $tables = []): object
    {
        return new class($tables) {
            public string $prefix = 'wp_';
            public string $charset = 'utf8mb4';
            private array $tables;

            public function __construct(array $tables) {
                $this->tables = $tables;
            }

            public function get_col(string $query): array {
                if (strpos($query, "SHOW TABLES LIKE") !== false) {
                    return $this->tables;
                }
                return [];
            }

            public function prepare(string $query, ...$args): string {
                return vsprintf($query, $args);
            }

            public function query(string $query): bool|int {
                // Mock successful query execution
                return 1;
            }

            public function esc_like(string $text): string {
                return addcslashes($text, '_%\\');
            }

            public function get_results(string $query, int $output = OBJECT): array {
                // Return mock results for URL replacement tests
                if (strpos($query, 'LIKE') !== false) {
                    return [
                        (object) ['guid' => 'https://old.example.com/page', 'ID' => 1],
                        (object) ['post_content' => 'Content with https://old.example.com/link', 'ID' => 2],
                    ];
                }
                return [];
            }

            public function update(string $table, array $data, array $where): int {
                return 1; // Mock successful update
            }
        };
    }

    /**
     * Create temporary directory for testing
     */
    public static function createTempDirectory(): string
    {
        $tempDir = sys_get_temp_dir() . '/wp-migrate-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        return $tempDir;
    }

    /**
     * Clean up temporary directory
     */
    public static function cleanupTempDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                self::cleanupTempDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Generate test SQL dump content
     */
    public static function generateTestSqlDump(): string
    {
        return "-- Test SQL Dump
CREATE TABLE wp_posts (
    ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    post_content longtext,
    PRIMARY KEY (ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO wp_posts VALUES (1, 'Test content');

-- End of dump";
    }

    /**
     * Assert that two arrays are equal ignoring key order
     */
    public static function assertArraysEqualIgnoringOrder(array $expected, array $actual, string $message = ''): void
    {
        sort($expected);
        sort($actual);
        TestCase::assertEquals($expected, $actual, $message);
    }
}
