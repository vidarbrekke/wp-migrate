<?php
namespace MK\WcPluginStarter\Assets;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use MK\WcPluginStarter\Contracts\Registrable;

final class Frontend implements Registrable {
    public function register(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
    }

    public function enqueue(): void {
        // Example: only on single product pages (adjust as needed).
        if ( function_exists('is_product') && is_product() ) {
            $handle = 'mk-wcps-product';
            wp_enqueue_style(
                $handle,
                MK_WCPS_URL . 'assets/css/frontend.css',
                [],
                MK_WCPS_VERSION
            );
            wp_enqueue_script(
                $handle,
                MK_WCPS_URL . 'assets/js/product.js',
                [ 'jquery' ],
                MK_WCPS_VERSION,
                true
            );
            wp_localize_script( $handle, 'MK_WCPS', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mk_wcps' ),
                'version' => MK_WCPS_VERSION,
            ] );
        }
    }
}
