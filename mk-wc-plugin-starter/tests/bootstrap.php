<?php
/**
 * WordPress Plugin Testing Bootstrap
 *
 * Sets up the testing environment for WP-Migrate plugin tests.
 * This file provides WordPress function mocks and testing utilities.
 */

namespace MK\WcPluginStarter\Tests;

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Define essential WordPress constants for testing
if ( ! defined( 'WPINC' ) ) {
    define( 'WPINC', 'wp-includes' );
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
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
        return TestHelper::get_transient( $key );
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $expiration = 0 ) {
        return TestHelper::set_transient( $key, $value, $expiration );
    }
}

if ( ! function_exists( 'is_ssl' ) ) {
    function is_ssl() {
        return TestHelper::is_ssl();
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

// Autoload test classes
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'MK\\WcPluginStarter\\Tests\\' ) === 0 ) {
        $file = str_replace( '\\', '/', substr( $class, 23 ) );
        $path = __DIR__ . '/' . $file . '.php';
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
});

// Include test helper
require_once __DIR__ . '/TestHelper.php';
