<?php
namespace WpMigrate;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use WpMigrate\Contracts\Registrable;
use WpMigrate\Admin\SettingsPage;
use WpMigrate\Rest\Api;
use WpMigrate\Security\HmacAuth;
use WpMigrate\State\StateStore;
use WpMigrate\Migration\JobManager;

final class Plugin {
    /** @var array<int, Registrable> */
    private array $services = [];

    public function boot(): void {
        $this->load_textdomain();
        $this->register_services();
        $this->register_ajax_handlers();
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
        $stateStore = new StateStore();
        $errorRecovery = new \WpMigrate\Migration\ErrorRecovery();
        $jobManager = new JobManager( $stateStore, $errorRecovery );
        $settings = new SettingsPage( $jobManager );
        
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

    /**
     * Register AJAX handlers for monitoring functionality
     */
    private function register_ajax_handlers(): void {
        \add_action( 'wp_ajax_wp_migrate_monitor_job', [ $this, 'ajax_monitor_job' ] );
        \add_action( 'wp_ajax_wp_migrate_emergency_stop', [ $this, 'ajax_emergency_stop' ] );
        \add_action( 'wp_ajax_wp_migrate_emergency_rollback', [ $this, 'ajax_emergency_rollback' ] );
    }

    /**
     * AJAX handler for monitoring job updates
     */
    public function ajax_monitor_job(): void {
        try {
            // Verify nonce
            if ( ! isset( $_POST['nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ), 'wp_migrate_monitor' ) ) {
                throw new \Exception( 'Invalid nonce' );
            }

            // Check permissions
            if ( ! \current_user_can( 'manage_options' ) ) {
                throw new \Exception( 'Insufficient permissions' );
            }

            $job_id = isset( $_POST['job_id'] ) ? \sanitize_text_field( \wp_unslash( $_POST['job_id'] ) ) : '';
            if ( empty( $job_id ) ) {
                throw new \Exception( 'Job ID is required' );
            }

            // Get the API instance to access monitoring functionality
            $api = $this->get_api_instance();
            if ( ! $api ) {
                throw new \Exception( 'API instance not available' );
            }

            // Prepare request data for monitoring
            $request_data = [
                'job_id' => $job_id,
                'since' => isset( $_POST['since'] ) ? (int) $_POST['since'] : 0,
            ];

            // Create a mock request object
            $request = $this->create_mock_request( $request_data );

            // Call the monitor method
            $response = $api->monitor( $request );

            if ( \is_wp_error( $response ) ) {
                throw new \Exception( $response->get_error_message() );
            }

            \wp_send_json_success( $response->get_data() );

        } catch ( \Exception $e ) {
            \wp_send_json_error( [
                'message' => $e->getMessage(),
                'code' => 'monitor_error'
            ] );
        }
    }

    /**
     * AJAX handler for emergency stop
     */
    public function ajax_emergency_stop(): void {
        $this->handle_emergency_action( 'stop' );
    }

    /**
     * AJAX handler for emergency rollback
     */
    public function ajax_emergency_rollback(): void {
        $this->handle_emergency_action( 'rollback' );
    }

    /**
     * Generic emergency action handler
     */
    private function handle_emergency_action( string $action ): void {
        try {
            // Verify nonce
            if ( ! isset( $_POST['nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ), 'wp_migrate_monitor' ) ) {
                throw new \Exception( 'Invalid nonce' );
            }

            // Check permissions
            if ( ! \current_user_can( 'manage_options' ) ) {
                throw new \Exception( 'Insufficient permissions' );
            }

            $job_id = isset( $_POST['job_id'] ) ? \sanitize_text_field( \wp_unslash( $_POST['job_id'] ) ) : '';
            if ( empty( $job_id ) ) {
                throw new \Exception( 'Job ID is required' );
            }

            // Get the settings instance to handle emergency actions
            $settings = $this->get_settings_instance();
            if ( ! $settings ) {
                throw new \Exception( 'Settings instance not available' );
            }

            // Simulate POST request for emergency action
            $_POST['wp_migrate_emergency_action'] = $action;
            $_POST['job_id'] = $job_id;
            $_POST['_wpnonce'] = \wp_create_nonce( 'wp_migrate_emergency' );

            $settings->handle_emergency_actions();

            \wp_send_json_success( [
                'message' => \sprintf(
                    'Emergency %s action initiated for job %s',
                    $action,
                    $job_id
                )
            ] );

        } catch ( \Exception $e ) {
            \wp_send_json_error( [
                'message' => $e->getMessage(),
                'code' => 'emergency_error'
            ] );
        }
    }

    /**
     * Get API instance from services
     */
    private function get_api_instance() {
        foreach ( $this->services as $service ) {
            if ( $service instanceof \WpMigrate\Rest\Api ) {
                return $service;
            }
        }
        return null;
    }

    /**
     * Get Settings instance from services
     */
    private function get_settings_instance() {
        foreach ( $this->services as $service ) {
            if ( $service instanceof \WpMigrate\Admin\SettingsPage ) {
                return $service;
            }
        }
        return null;
    }

    /**
     * Create a mock WP_REST_Request object
     */
    private function create_mock_request( array $params ): \WP_REST_Request {
        $request = new \WP_REST_Request();

        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        return $request;
    }
}
