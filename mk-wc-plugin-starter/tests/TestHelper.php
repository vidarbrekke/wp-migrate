<?php
namespace WpMigrate\Tests;

use WpMigrate\Security\HmacAuth;
use WP_REST_Request;

class TestHelper
{
    public const TEST_SHARED_KEY = 'test-shared-key-for-hmac-authentication';
    public const TEST_PEER_URL = 'https://staging.example.com';

    /**
     * Create a mock WP_REST_Request with HMAC authentication headers
     */
    public static function createAuthenticatedRequest(
        string $method = 'POST',
        string $path = '/wp-json/migrate/v1/handshake',
        string $body = '',
        ?string $peerUrl = null
    ): WP_REST_Request {
        $timestamp = (string) round(microtime(true) * 1000);
        $nonce = 'test-nonce-' . uniqid();
        $peerUrl = $peerUrl ?? self::TEST_PEER_URL;
        $bodyHash = hash('sha256', $body);

        $payload = $timestamp . "\n" . $nonce . "\n" . $method . "\n" . $path . "\n" . $bodyHash;
        $signature = base64_encode(hash_hmac('sha256', $payload, self::TEST_SHARED_KEY, true));

        $request = new WP_REST_Request();
        $request->set_method($method);
        $request->set_route(str_replace('/wp-json', '', $path));
        $request->set_body($body);

        $request->set_headers([
            'x-mig-timestamp' => $timestamp,
            'x-mig-nonce' => $nonce,
            'x-mig-peer' => $peerUrl,
            'x-mig-signature' => $signature,
        ]);

        return $request;
    }

    /**
     * Create HmacAuth instance with test settings
     */
    public static function createTestHmacAuth(): HmacAuth
    {
        $settingsProvider = function () {
            return [
                'shared_key' => self::TEST_SHARED_KEY,
                'peer_url' => self::TEST_PEER_URL,
            ];
        };

        return new HmacAuth($settingsProvider);
    }

    /**
     * Generate a test job ID
     */
    public static function generateTestJobId(string $prefix = 'test-job'): string
    {
        return $prefix . '-' . uniqid() . '-' . rand(100, 999);
    }

    /**
     * Create a temporary directory for testing
     */
    public static function createTempDir(string $prefix = 'wp-migrate-test'): string
    {
        $tempDir = sys_get_temp_dir() . '/' . $prefix . '-' . uniqid();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        return $tempDir;
    }

    /**
     * Remove a directory recursively
     */
    public static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Mock WordPress functions for testing
     */
    public static function mockWordPressFunctions(): void
    {
        if (!function_exists('wp_mkdir_p')) {
            function wp_mkdir_p($path) {
                return mkdir($path, 0755, true);
            }
        }

        if (!function_exists('wp_upload_dir')) {
            function wp_upload_dir() {
                return [
                    'basedir' => sys_get_temp_dir() . '/wp-uploads',
                    'baseurl' => 'http://example.com/wp-content/uploads',
                    'path' => sys_get_temp_dir() . '/wp-uploads',
                    'url' => 'http://example.com/wp-content/uploads',
                    'subdir' => '',
                    'error' => false
                ];
            }
        }

        if (!function_exists('sanitize_file_name')) {
            function sanitize_file_name($filename) {
                return preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $filename);
            }
        }

        if (!function_exists('get_site_url')) {
            function get_site_url() {
                return 'http://example.com';
            }
        }

        if (!function_exists('get_bloginfo')) {
            function get_bloginfo($info) {
                $info_map = [
                    'version' => '6.8.2',
                    'charset' => 'UTF-8'
                ];
                return $info_map[$info] ?? '';
            }
        }

        if (!function_exists('is_multisite')) {
            function is_multisite() {
                return false;
            }
        }

        if (!function_exists('get_site_option')) {
            function get_site_option($option) {
                return null;
            }
        }

        if (!function_exists('get_option')) {
            function get_option($option, $default = null) {
                static $options = [];
                return $options[$option] ?? $default;
            }
        }

        if (!function_exists('set_transient')) {
            function set_transient($key, $value, $expiration = 0) {
                return true;
            }
        }

        if (!function_exists('get_transient')) {
            function get_transient($key) {
                return false;
            }
        }

        if (!function_exists('is_ssl')) {
            function is_ssl() {
                return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            }
        }

        if (!function_exists('add_action')) {
            function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
                // Mock implementation - do nothing
            }
        }

        if (!function_exists('do_action')) {
            function do_action($hook, ...$args) {
                // Mock implementation - do nothing
            }
        }

        if (!function_exists('register_rest_route')) {
            function register_rest_route($namespace, $route, $args = [], $override = false) {
                // Mock implementation - do nothing
            }
        }

        if (!function_exists('rest_api_init')) {
            function rest_api_init() {
                // Mock implementation - do nothing
            }
        }

        if (!function_exists('load_plugin_textdomain')) {
            function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false) {
                // Mock implementation - do nothing
            }
        }

        if (!function_exists('plugin_dir_path')) {
            function plugin_dir_path($file) {
                return dirname($file) . '/';
            }
        }

        if (!function_exists('plugin_dir_url')) {
            function plugin_dir_url($file) {
                return 'http://example.com/wp-content/plugins/wp-migrate/';
            }
        }
    }
}
