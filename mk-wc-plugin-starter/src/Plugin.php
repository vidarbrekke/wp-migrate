<?php
namespace MK\WcPluginStarter;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use MK\WcPluginStarter\Contracts\Registrable;
use MK\WcPluginStarter\Admin\SettingsPage;
use MK\WcPluginStarter\Assets\Frontend;
use MK\WcPluginStarter\Rest\Api;
use MK\WcPluginStarter\Security\HmacAuth;

final class Plugin {
    /** @var array<int, Registrable> */
    private array $services = [];

    public function boot(): void {
        $this->load_textdomain();
        $this->register_services();
        \do_action( 'mk_wcps_booted' );
    }

    private function load_textdomain(): void {
        \load_plugin_textdomain(
            'mk-wc-plugin-starter',
            false,
            dirname( \plugin_basename( MK_WCPS_FILE ) ) . '/languages'
        );
    }

    private function register_services(): void {
        // Core services
        $auth = new HmacAuth( function () {
            $settings = new SettingsPage();
            $opts = $settings->get_settings();
            return [
                'shared_key' => isset( $opts['shared_key'] ) ? (string) $opts['shared_key'] : '',
                'peer_url'   => isset( $opts['peer_url'] ) ? (string) $opts['peer_url'] : '',
            ];
        } );

        $this->services = [
            new Frontend(),
            $settings,
            new Api( $auth ),
        ];

        foreach ( $this->services as $service ) {
            $service->register();
        }

        /**
         * Action: mk_wcps_services_registered
         * Fires after core services have registered. Third-parties may hook here.
         *
         * @param Plugin $this
         */
        \do_action( 'mk_wcps_services_registered', $this );
    }
}
