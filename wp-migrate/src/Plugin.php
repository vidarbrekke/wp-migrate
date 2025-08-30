<?php
namespace WpMigrate;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use WpMigrate\Contracts\Registrable;
use WpMigrate\Admin\SettingsPage;
use WpMigrate\Rest\Api;
use WpMigrate\Security\HmacAuth;

final class Plugin {
    /** @var array<int, Registrable> */
    private array $services = [];

    public function boot(): void {
        $this->load_textdomain();
        $this->register_services();
        \do_action( 'wp_migrate_booted' );
    }

    private function load_textdomain(): void {
        \load_plugin_textdomain(
            'wp-migrate',
            false,
            dirname( \plugin_basename( WP_MIGRATE_FILE ) ) . '/languages'
        );
    }

    private function register_services(): void {
        // Core services
        $settings = new SettingsPage();
        
        $auth = new HmacAuth( function () use ( $settings ) {
            $opts = $settings->get_settings();
            return [
                'shared_key' => isset( $opts['shared_key'] ) ? (string) $opts['shared_key'] : '',
                'peer_url'   => isset( $opts['peer_url'] ) ? (string) $opts['peer_url'] : '',
            ];
        } );

        $this->services = [
            $settings,
            new Api( $auth ),
        ];

        foreach ( $this->services as $service ) {
            $service->register();
        }

        /**
         * Action: wp_migrate_services_registered
         * Fires after core services have registered. Third-parties may hook here.
         *
         * @param Plugin $this
         */
        \do_action( 'wp_migrate_services_registered', $this );
    }
}
