<?php
/**
 * Plugin Name: WP-Migrate: Production → Staging Migration
 * Description: Secure, resumable WordPress migrations between production and staging environments. HMAC authentication, chunked uploads, and preflight validation.
 * Version: 1.0.9
 * Author: Vidar Brekke
 * Text Domain: wp-migrate
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Network: false
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'WP_MIGRATE_VERSION', '1.0.9' );
define( 'WP_MIGRATE_FILE', __FILE__ );
define( 'WP_MIGRATE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_MIGRATE_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader (run `composer dump-autoload` after first install)
if ( file_exists( WP_MIGRATE_DIR . 'vendor/autoload.php' ) ) {
    require WP_MIGRATE_DIR . 'vendor/autoload.php';
} else {
    // Simple PSR-4 fallback loader for dev without Composer (best-effort).
    spl_autoload_register( function ( $class ) {
        if ( strpos( $class, 'WpMigrate\\' ) !== 0 ) return;
        $rel = str_replace( 'WpMigrate\\', '', $class );
        $rel = str_replace( '\\', '/', $rel );
        $file = WP_MIGRATE_DIR . 'src/' . $rel . '.php';
        if ( file_exists( $file ) ) require $file;
    } );
}

add_action( 'plugins_loaded', function () {
    // Initialize the WP-Migrate plugin
    $plugin = new WpMigrate\Plugin();
    $plugin->boot();
} );
