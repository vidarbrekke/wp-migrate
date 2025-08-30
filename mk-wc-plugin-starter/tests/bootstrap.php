<?php
/**
 * PHPUnit bootstrap file for WP-Migrate plugin tests
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_MIGRATE_DIR')) {
    define('WP_MIGRATE_DIR', dirname(__DIR__) . '/');
}

if (!defined('WP_MIGRATE_FILE')) {
    define('WP_MIGRATE_FILE', WP_MIGRATE_DIR . 'wp-migrate.php');
}

if (!defined('WP_MIGRATE_URL')) {
    define('WP_MIGRATE_URL', 'http://example.com/wp-content/plugins/wp-migrate/');
}

// Mock WordPress core functions
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($path) {
        return mkdir($path, 0755, true);
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        // Check if test has set a mock upload directory
        if (isset($GLOBALS['mock_upload_dir'])) {
            return $GLOBALS['mock_upload_dir'];
        }

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

// Mock WordPress Error and REST classes for testing
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct($code, $message, $data = null) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $method = 'GET';
        private $headers = [];
        private $body = '';
        private $route = '';
        private $params = [];

        public function set_method($method) {
            $this->method = $method;
        }

        public function get_method() {
            return $this->method;
        }

        public function set_headers($headers) {
            $this->headers = $headers;
        }

        public function get_headers() {
            return $this->headers;
        }

        public function set_body($body) {
            $this->body = $body;
        }

        public function get_body() {
            return $this->body;
        }

        public function set_route($route) {
            $this->route = $route;
        }

        public function get_route() {
            return $this->route;
        }

        public function set_param($key, $value) {
            $this->params[$key] = $value;
        }

        public function get_param($key) {
            return $this->params[$key] ?? null;
        }

        public function get_json_params() {
            return json_decode($this->body, true) ?: [];
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private $status;
        private $headers;

        public function __construct($data = null, $status = 200, $headers = []) {
            $this->data = $data;
            $this->status = $status;
            $this->headers = $headers;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }

        public function set_status($status) {
            $this->status = $status;
        }

        public function get_headers() {
            return $this->headers;
        }

        public function set_header($key, $value) {
            $this->headers[$key] = $value;
        }
    }
}

// Load Composer autoloader
$composer_autoload = WP_MIGRATE_DIR . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

// Simple PSR-4 fallback loader for development
spl_autoload_register(function ($class) {
    if (strpos($class, 'WpMigrate\\') !== 0) {
        return;
    }

    $rel = str_replace('WpMigrate\\', '', $class);
    $rel = str_replace('\\', '/', $rel);

    // Check src directory
    $file = WP_MIGRATE_DIR . 'src/' . $rel . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }

    // Check tests directory
    $file = WP_MIGRATE_DIR . 'tests/' . $rel . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }
});

// Set up test environment
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Create temporary directories for tests
$temp_dir = sys_get_temp_dir() . '/wp-migrate-tests';
if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

$upload_dir = sys_get_temp_dir() . '/wp-uploads';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
