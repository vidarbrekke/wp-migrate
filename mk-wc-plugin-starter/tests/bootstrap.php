<?php
/**
 * WordPress Plugin Testing Bootstrap
 *
 * Sets up the testing environment for WP-Migrate plugin tests.
 * This file provides WordPress function mocks and testing utilities.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) { 
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// Define essential WordPress constants for testing
if ( ! defined( 'WPINC' ) ) {
    define( 'WPINC', 'wp-includes' );
}

// Mock essential WordPress functions
if ( ! function_exists( 'wp_upload_dir' ) ) {
    function wp_upload_dir() {
        return [
            'path' => sys_get_temp_dir() . '/wp-uploads',
            'url' => 'http://test.example.com/wp-uploads',
            'subdir' => '',
            'basedir' => sys_get_temp_dir() . '/wp-uploads',
            'baseurl' => 'http://test.example.com/wp-uploads',
            'error' => false,
        ];
    }
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
    function wp_mkdir_p( $path ) {
        return mkdir( $path, 0755, true );
    }
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
    function sanitize_file_name( $filename ) {
        return preg_replace( '/[^a-zA-Z0-9\-_\.]/', '', $filename );
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        return \MK\WcPluginStarter\Tests\TestHelper::get_transient( $key );
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $expiration = 0 ) {
        return \MK\WcPluginStarter\Tests\TestHelper::set_transient( $key, $value, $expiration );
    }
}

if ( ! function_exists( 'is_ssl' ) ) {
    function is_ssl() {
        return \MK\WcPluginStarter\Tests\TestHelper::is_ssl();
    }
}

if ( ! function_exists( 'get_site_url' ) ) {
    function get_site_url( $blog_id = null, $path = '', $scheme = null ) {
        return 'https://test.example.com';
    }
}

if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '', $filter = 'raw' ) {
        switch ( $show ) {
            case 'name':
                return 'Test Site';
            case 'url':
                return 'https://test.example.com';
            case 'description':
                return 'Test site description';
            default:
                return 'Test Site';
        }
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        return $default;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data ) {
        return json_encode( $data );
    }
}

if ( ! function_exists( 'wp_json_decode' ) ) {
    function wp_json_decode( $data, $assoc = false ) {
        return json_decode( $data, $assoc );
    }
}

// Mock database constants
if ( ! defined( 'DB_HOST' ) ) {
    define( 'DB_HOST', 'localhost' );
}
if ( ! defined( 'DB_USER' ) ) {
    define( 'DB_USER', 'test_user' );
}
if ( ! defined( 'DB_PASSWORD' ) ) {
    define( 'DB_PASSWORD', 'test_pass' );
}
if ( ! defined( 'DB_NAME' ) ) {
    define( 'DB_NAME', 'test_db' );
}
if ( ! defined( 'WP_CLI' ) ) {
    define( 'WP_CLI', false );
}

// Mock WordPress classes if they don't exist
if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        private string $method = 'GET';
        private string $route = '';
        private array $headers = [];
        private string $body = '';
        private array $params = [];

        public function __construct( string $method = 'GET', string $route = '' ) {
            $this->method = $method;
            $this->route = $route;
        }

        public function set_header( string $key, string $value ): void {
            $this->headers[$key] = $value;
        }

        public function get_header( string $key ): ?string {
            return $this->headers[$key] ?? null;
        }

        public function get_headers(): array {
            return $this->headers;
        }

        public function set_body( string $body ): void {
            $this->body = $body;
        }

        public function get_body(): string {
            return $this->body;
        }

        public function set_param( string $key, mixed $value ): void {
            $this->params[$key] = $value;
        }

        public function get_param( string $key ): mixed {
            return $this->params[$key] ?? null;
        }

        public function get_method(): string {
            return $this->method;
        }

        public function get_route(): string {
            return $this->route;
        }
    }
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        private mixed $data;
        private int $status = 200;
        private array $headers = [];

        public function __construct( mixed $data = null, int $status = 200 ) {
            $this->data = $data;
            $this->status = $status;
        }

        public function set_data( mixed $data ): WP_REST_Response {
            $this->data = $data;
            return $this;
        }

        public function get_data(): mixed {
            return $this->data;
        }

        public function set_status( int $status ): WP_REST_Response {
            $this->status = $status;
            return $this;
        }

        public function get_status(): int {
            return $this->status;
        }

        public function set_header( string $key, string $value ): WP_REST_Response {
            $this->headers[$key] = $value;
            return $this;
        }

        public function get_headers(): array {
            return $this->headers;
        }
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private string $code;
        private string $message;
        private array $data;

        public function __construct( string $code = '', string $message = '', mixed $data = null ) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data ? ( is_array( $data ) ? $data : [ 'status' => $data ] ) : [];
        }

        public function get_error_code(): string {
            return $this->code;
        }

        public function get_error_message(): string {
            return $this->message;
        }

        public function get_error_data( string $key = '' ): mixed {
            if ( empty( $key ) ) {
                return $this->data;
            }
            return $this->data[$key] ?? null;
        }
    }
}

// Include test helper
require_once __DIR__ . '/TestHelper.php';
