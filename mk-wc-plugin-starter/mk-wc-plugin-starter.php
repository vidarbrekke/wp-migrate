<?php
/**
 * Plugin Name: WP-Migrate: Production → Staging Migration
 * Description: Secure, resumable WordPress migrations between production and staging environments. HMAC authentication, chunked uploads, and preflight validation.
 * Version: 0.1.0
 * Author: Vidar Brekke
 * Text Domain: mk-wc-plugin-starter
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Network: false
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'MK_WCPS_VERSION', '0.1.0' );
define( 'MK_WCPS_FILE', __FILE__ );
define( 'MK_WCPS_DIR', plugin_dir_path( __FILE__ ) );
define( 'MK_WCPS_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader (run `composer dump-autoload` after first install)
if ( file_exists( MK_WCPS_DIR . 'vendor/autoload.php' ) ) {
    require MK_WCPS_DIR . 'vendor/autoload.php';
} else {
    // Simple PSR-4 fallback loader for dev without Composer (best-effort).
    spl_autoload_register( function ( $class ) {
        if ( strpos( $class, 'MK\\WcPluginStarter\\' ) !== 0 ) return;
        $rel = str_replace( 'MK\\WcPluginStarter\\', '', $class );
        $rel = str_replace( '\\', '/', $rel );
        $file = MK_WCPS_DIR . 'src/' . $rel . '.php';
        if ( file_exists( $file ) ) require $file;
    } );
}

add_action( 'plugins_loaded', function () {
    // Allow plugin to boot with or without WooCommerce, but expose a filter if WC is required.
    $plugin = new MK\WcPluginStarter\Plugin();
    $plugin->boot();
} );
