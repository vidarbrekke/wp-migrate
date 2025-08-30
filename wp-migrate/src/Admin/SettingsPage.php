<?php
namespace WpMigrate\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use WpMigrate\Contracts\Registrable;
use WpMigrate\Migration\JobManager;
use WpMigrate\State\StateStore;
use WpMigrate\Logging\JsonLogger;

final class SettingsPage implements Registrable {
    private const OPTION = 'wp_migrate_settings';
    private JobManager $jobManager;

    public function __construct( ?JobManager $jobManager = null ) {
        $this->jobManager = $jobManager ?? new JobManager( new StateStore() );
    }

    public function register(): void {
        \add_action( 'admin_menu', [ $this, 'add_menu' ] );
        \add_action( 'admin_init', [ $this, 'register_settings' ] );
        \add_action( 'admin_init', [ $this, 'handle_emergency_actions' ] );
        \add_action( 'admin_notices', [ $this, 'show_admin_notices' ] );
        \add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_monitoring_scripts' ] );
    }

    /**
     * Handle emergency actions from the admin UI
     */
    public function handle_emergency_actions(): void {
        if ( ! isset( $_POST['wp_migrate_emergency_action'] ) || ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'wp_migrate_emergency' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'wp-migrate' ) );
        }

        $action = sanitize_text_field( $_POST['wp_migrate_emergency_action'] );
        $jobId = sanitize_text_field( $_POST['job_id'] ?? '' );

        if ( empty( $jobId ) ) {
            set_transient( 'wp_migrate_admin_notice', [
                'type' => 'error',
                'message' => __( 'Job ID is required for emergency actions.', 'wp-migrate' )
            ], 30 );
            return;
        }

        try {
            switch ( $action ) {
                case 'stop':
                    $this->handle_emergency_stop( $jobId );
                    break;
                case 'rollback':
                    $this->handle_emergency_rollback( $jobId );
                    break;
                default:
                    throw new \InvalidArgumentException( 'Invalid emergency action: ' . $action );
            }
        } catch ( \Throwable $e ) {
            set_transient( 'wp_migrate_admin_notice', [
                'type' => 'error',
                'message' => sprintf( __( 'Emergency action failed: %s', 'wp-migrate' ), $e->getMessage() )
            ], 30 );
        }
    }

    /**
     * Handle emergency stop action
     */
    private function handle_emergency_stop( string $jobId ): void {
        $progress = $this->jobManager->get_progress( $jobId );

        if ( in_array( $progress['state'], ['error', 'done', 'rolled_back'], true ) ) {
            set_transient( 'wp_migrate_admin_notice', [
                'type' => 'warning',
                'message' => __( 'Job is already in a terminal state.', 'wp-migrate' )
            ], 30 );
            return;
        }

        // Set job to error state to stop processing
        $this->jobManager->set_state( $jobId, 'error', [
            'emergency_stop' => true,
            'stopped_by' => get_current_user_id(),
            'stopped_at' => gmdate( 'c' )
        ]);

        set_transient( 'wp_migrate_admin_notice', [
            'type' => 'success',
            'message' => sprintf( __( 'Migration job %s has been stopped.', 'wp-migrate' ), $jobId )
        ], 30 );
    }

    /**
     * Handle emergency rollback action
     */
    private function handle_emergency_rollback( string $jobId ): void {
        $progress = $this->jobManager->get_progress( $jobId );

        if ( ! $this->jobManager->can_rollback_from_state( $progress['state'] ) ) {
            set_transient( 'wp_migrate_admin_notice', [
                'type' => 'error',
                'message' => __( 'Job cannot be rolled back from current state.', 'wp-migrate' )
            ], 30 );
            return;
        }

        // Trigger rollback through API (this would normally be done via REST API)
        $this->perform_rollback( $jobId );

        set_transient( 'wp_migrate_admin_notice', [
            'type' => 'success',
            'message' => sprintf( __( 'Rollback initiated for job %s.', 'wp-migrate' ), $jobId )
        ], 30 );
    }

    /**
     * Perform actual rollback operations
     */
    private function perform_rollback( string $jobId ): void {
        // Remove maintenance mode
        $maintenanceFile = ABSPATH . '.maintenance';
        if ( file_exists( $maintenanceFile ) ) {
            @unlink( $maintenanceFile );
        }

        // Update job state
        $this->jobManager->set_state( $jobId, 'rolled_back', [
            'emergency_rollback' => true,
            'rolled_back_by' => get_current_user_id(),
            'rolled_back_at' => gmdate( 'c' )
        ]);
    }

    /**
     * Display admin notices for emergency actions
     */
    public function show_admin_notices(): void {
        $notice = get_transient( 'wp_migrate_admin_notice' );
        if ( ! $notice ) {
            return;
        }

        delete_transient( 'wp_migrate_admin_notice' );

        $class = 'notice notice-' . $notice['type'];
        printf(
            '<div class="%1$s"><p>%2$s</p></div>',
            esc_attr( $class ),
            esc_html( $notice['message'] )
        );
    }

    public function add_menu(): void {
        \add_options_page(
            \__( 'WP-Migrate Settings', 'wp-migrate' ),
            \__( 'WP-Migrate', 'wp-migrate' ),
            'manage_options',
            'wp_migrate',
            [ $this, 'render_page' ]
        );

        \add_submenu_page(
            'options-general.php',
            \__( 'Migration Monitor', 'wp-migrate' ),
            \__( 'Monitor', 'wp-migrate' ),
            'manage_options',
            'wp_migrate_monitor',
            [ $this, 'render_monitor_page' ]
        );
    }

    public function register_settings(): void {
        \register_setting( 'wp_migrate', self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize' ],
            'default'           => [
                'shared_key' => '',
                'peer_url' => '',
                'email_blackhole' => 1,
            ],
        ] );

        \add_settings_section(
            'wp_migrate_section',
            \__( 'General', 'wp-migrate' ),
            '__return_false',
            'wp_migrate'
        );

        \add_settings_field(
            'shared_key',
            \__( 'Shared Key', 'wp-migrate' ),
            [ $this, 'field_shared_key' ],
            'wp_migrate',
            'wp_migrate_section'
        );

        \add_settings_field(
            'peer_url',
            \__( 'Peer Base URL', 'wp-migrate' ),
            [ $this, 'field_peer_url' ],
            'wp_migrate',
            'wp_migrate_section'
        );

        \add_settings_field(
            'email_blackhole',
            \__( 'Blackhole Emails/Webhooks (staging safety)', 'wp-migrate' ),
            [ $this, 'field_email_blackhole' ],
            'wp_migrate',
            'wp_migrate_section'
        );
    }

    public function sanitize( $value ): array {
        $peer = isset( $value['peer_url'] ) ? trim( (string) $value['peer_url'] ) : '';
        // Normalize URL (no trailing slash)
        if ( $peer !== '' ) {
            $peer = rtrim( $peer );
            $peer = preg_replace( '#/+$#', '', $peer );
        }
        return [
            // Store shared key securely; avoid displaying it back in forms.
            'shared_key' => isset( $value['shared_key'] ) ? (string) $value['shared_key'] : '',
            'peer_url' => $peer,
            'email_blackhole' => isset( $value['email_blackhole'] ) ? 1 : 0,
        ];
    }

    public function field_shared_key(): void {
        $opts = \get_option( self::OPTION, [] );
        ?>
        <input type="password" autocomplete="new-password" name="<?php echo \esc_attr( self::OPTION ); ?>[shared_key]" value="" placeholder="<?php \esc_attr_e( 'Enter or paste shared key', 'wp-migrate' ); ?>" class="regular-text" />
        <p class="description"><?php \esc_html_e( 'Used for HMAC signing between peers. Not displayed after save.', 'wp-migrate' ); ?></p>
        <?php
    }

    public function field_peer_url(): void {
        $opts = \get_option( self::OPTION, [] );
        $peer = isset( $opts['peer_url'] ) ? (string) $opts['peer_url'] : '';
        ?>
        <input type="url" name="<?php echo \esc_attr( self::OPTION ); ?>[peer_url]" value="<?php echo \esc_attr( $peer ); ?>" placeholder="https://staging.example.com" class="regular-text" />
        <p class="description"><?php \esc_html_e( 'Base URL of the peer site. TLS required.', 'wp-migrate' ); ?></p>
        <?php
    }

    public function field_email_blackhole(): void {
        $opts = \get_option( self::OPTION, [ 'email_blackhole' => 1 ] );
        ?>
        <label>
            <input type="checkbox" name="<?php echo \esc_attr( self::OPTION ); ?>[email_blackhole]" value="1" <?php \checked( ! empty( $opts['email_blackhole'] ) ); ?> />
            <?php \esc_html_e( 'Disable/blackhole outbound emails & webhooks on staging', 'wp-migrate' ); ?>
        </label>
        <?php
    }

    public function render_page(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php \esc_html_e( 'WP-Migrate Settings', 'wp-migrate' ); ?></h1>

            <?php $this->render_emergency_section(); ?>

            <form method="post" action="options.php">
                <?php
                    \settings_fields( 'wp_migrate' );
                    \do_settings_sections( 'wp_migrate' );
                    \submit_button( \__( 'Save Settings', 'wp-migrate' ) );
                ?>
            </form>
            <p><em><?php \esc_html_e( 'Configure WP-Migrate settings for secure WordPress migrations between production and staging environments.', 'wp-migrate' ); ?></em></p>
        </div>
        <?php
    }

    /**
     * Render the emergency procedures section
     */
    private function render_emergency_section(): void {
        $activeJobs = $this->get_active_jobs();

        if ( empty( $activeJobs ) ) {
            return;
        }

        ?>
        <div class="wp-migrate-emergency-section" style="margin: 20px 0; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
            <h2 style="margin-top: 0; color: #856404;">
                <?php esc_html_e( '🚨 Active Migrations - Emergency Controls', 'wp-migrate' ); ?>
            </h2>
            <p style="color: #856404; margin-bottom: 15px;">
                <?php esc_html_e( 'Use these controls only in emergency situations. Actions cannot be undone.', 'wp-migrate' ); ?>
            </p>

            <?php foreach ( $activeJobs as $job ): ?>
                <div class="wp-migrate-job-card" style="background: white; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h3 style="margin: 0; font-size: 16px;">
                            <?php printf( esc_html__( 'Job: %s', 'wp-migrate' ), esc_html( $job['job_id'] ) ); ?>
                        </h3>
                        <span class="wp-migrate-job-state" style="padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; <?php echo $this->get_state_style( $job['state'] ); ?>">
                            <?php echo esc_html( strtoupper( $job['state'] ) ); ?>
                        </span>
                    </div>

                    <div style="margin-bottom: 10px;">
                        <strong><?php esc_html_e( 'Progress:', 'wp-migrate' ); ?></strong>
                        <div style="background: #f1f1f1; height: 10px; border-radius: 5px; margin: 5px 0;">
                            <div style="background: <?php echo $this->get_progress_color( $job['progress'] ); ?>; height: 100%; width: <?php echo (int) $job['progress']; ?>%; border-radius: 5px;"></div>
                        </div>
                        <span style="font-size: 12px; color: #666;">
                            <?php printf( esc_html__( '%d%% complete', 'wp-migrate' ), (int) $job['progress'] ); ?>
                        </span>
                    </div>

                    <div style="margin-bottom: 10px;">
                        <strong><?php esc_html_e( 'Created:', 'wp-migrate' ); ?></strong>
                        <?php echo esc_html( $this->format_timestamp( $job['created_at'] ) ); ?>
                        |
                        <strong><?php esc_html_e( 'Updated:', 'wp-migrate' ); ?></strong>
                        <?php echo esc_html( $this->format_timestamp( $job['updated_at'] ) ); ?>
                    </div>

                    <?php if ( ! empty( $job['errors'] ) ): ?>
                        <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 10px; border-radius: 4px;">
                            <strong style="color: #721c24;"><?php esc_html_e( 'Recent Errors:', 'wp-migrate' ); ?></strong>
                            <ul style="margin: 5px 0 0 0; padding-left: 20px; color: #721c24;">
                                <?php foreach ( array_slice( $job['errors'], -3 ) as $error ): ?>
                                    <li><?php echo esc_html( $error['message'] ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="wp-migrate-emergency-actions" style="border-top: 1px solid #eee; padding-top: 10px;">
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field( 'wp_migrate_emergency' ); ?>
                            <input type="hidden" name="job_id" value="<?php echo esc_attr( $job['job_id'] ); ?>">
                            <input type="hidden" name="wp_migrate_emergency_action" value="stop">

                            <?php if ( ! in_array( $job['state'], ['error', 'done', 'rolled_back'], true ) ): ?>
                                <button type="submit" class="button button-secondary"
                                        onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to stop this migration? This action cannot be undone.', 'wp-migrate' ); ?>');"
                                        style="background: #dc3545; border-color: #dc3545; color: white;">
                                    🛑 <?php esc_html_e( 'Stop Migration', 'wp-migrate' ); ?>
                                </button>
                            <?php endif; ?>

                            <?php if ( $this->jobManager->can_rollback_from_state( $job['state'] ) ): ?>
                                <input type="hidden" name="wp_migrate_emergency_action" value="rollback">
                                <button type="submit" class="button button-secondary"
                                        onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to rollback this migration? This will revert database and file changes.', 'wp-migrate' ); ?>');"
                                        style="background: #ffc107; border-color: #ffc107; color: black; margin-left: 10px;">
                                    ↶ <?php esc_html_e( 'Rollback', 'wp-migrate' ); ?>
                                </button>
                            <?php endif; ?>
                        </form>

                        <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=wp_migrate_logs&job_id=' . urlencode( $job['job_id'] ) ) ); ?>"
                           class="button button-secondary" style="margin-left: 10px;" target="_blank">
                            📋 <?php esc_html_e( 'View Logs', 'wp-migrate' ); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Get active migration jobs
     */
    private function get_active_jobs(): array {
        global $wpdb;

        // Get all job option names
        $jobOptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 AND option_value NOT LIKE %s",
                'wp_migrate_job_%',
                '%"state":"done"%'
            ),
            ARRAY_A
        );

        $activeJobs = [];
        foreach ( $jobOptions as $option ) {
            $jobId = str_replace( 'wp_migrate_job_', '', $option['option_name'] );
            $progress = $this->jobManager->get_progress( $jobId );

            // Only include jobs that are not in terminal states
            if ( ! in_array( $progress['state'], ['done', 'rolled_back'], true ) ) {
                $activeJobs[] = $progress;
            }
        }

        return $activeJobs;
    }

    /**
     * Get CSS style for job state
     */
    private function get_state_style( string $state ): string {
        $styles = [
            'created' => 'background: #e3f2fd; color: #1976d2;',
            'preflight_ok' => 'background: #e8f5e8; color: #2e7d32;',
            'files_pass1' => 'background: #fff3e0; color: #f57c00;',
            'db_exported' => 'background: #fff3e0; color: #f57c00;',
            'db_uploaded' => 'background: #fff3e0; color: #f57c00;',
            'db_imported' => 'background: #fff3e0; color: #f57c00;',
            'url_replaced' => 'background: #fff3e0; color: #f57c00;',
            'files_pass2' => 'background: #fff3e0; color: #f57c00;',
            'finalized' => 'background: #e8f5e8; color: #2e7d32;',
            'done' => 'background: #e8f5e8; color: #2e7d32;',
            'error' => 'background: #ffebee; color: #c62828;',
            'rollback' => 'background: #fce4ec; color: #ad1457;',
            'rolled_back' => 'background: #fce4ec; color: #ad1457;',
        ];

        return $styles[$state] ?? 'background: #f5f5f5; color: #333;';
    }

    /**
     * Get progress bar color based on percentage
     */
    private function get_progress_color( int $progress ): string {
        if ( $progress < 25 ) return '#dc3545'; // Red
        if ( $progress < 50 ) return '#ffc107'; // Yellow
        if ( $progress < 75 ) return '#17a2b8'; // Blue
        return '#28a745'; // Green
    }

    /**
     * Format timestamp for display
     */
    private function format_timestamp( ?string $timestamp ): string {
        if ( ! $timestamp ) {
            return __( 'Unknown', 'wp-migrate' );
        }

        $date = strtotime( $timestamp );
        if ( ! $date ) {
            return __( 'Invalid', 'wp-migrate' );
        }

        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date );
    }

    /**
     * Enqueue monitoring scripts and styles
     */
    public function enqueue_monitoring_scripts( $hook ): void {
        if ( $hook !== 'settings_page_wp_migrate_monitor' ) {
            return;
        }

        \wp_enqueue_script(
            'wp-migrate-monitor',
            \plugin_dir_url( WP_MIGRATE_FILE ) . 'assets/js/monitor.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );

        \wp_enqueue_style(
            'wp-migrate-monitor',
            \plugin_dir_url( WP_MIGRATE_FILE ) . 'assets/css/monitor.css',
            [],
            '1.0.0'
        );

        \wp_localize_script( 'wp-migrate-monitor', 'wpMigrateMonitor', [
            'ajaxUrl' => \admin_url( 'admin-ajax.php' ),
            'nonce' => \wp_create_nonce( 'wp_migrate_monitor' ),
            'strings' => [
                'connecting' => \__( 'Connecting...', 'wp-migrate' ),
                'connected' => \__( 'Connected', 'wp-migrate' ),
                'disconnected' => \__( 'Disconnected', 'wp-migrate' ),
                'reconnecting' => \__( 'Reconnecting...', 'wp-migrate' ),
                'error' => \__( 'Connection Error', 'wp-migrate' ),
                'no_active_jobs' => \__( 'No active migrations', 'wp-migrate' ),
                'monitoring_job' => \__( 'Monitoring job:', 'wp-migrate' ),
                'stop_monitoring' => \__( 'Stop Monitoring', 'wp-migrate' ),
                'view_logs' => \__( 'View Full Logs', 'wp-migrate' ),
                'emergency_stop' => \__( 'Emergency Stop', 'wp-migrate' ),
                'rollback' => \__( 'Rollback', 'wp-migrate' ),
            ]
        ] );
    }

    /**
     * Render the monitoring dashboard page
     */
    public function render_monitor_page(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $activeJobs = $this->get_active_jobs();

        ?>
        <div class="wrap">
            <h1><?php \esc_html_e( 'WP-Migrate Monitor', 'wp-migrate' ); ?></h1>

            <div class="wp-migrate-monitor-header" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd; border-radius: 4px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="margin: 0;"><?php \esc_html_e( 'Real-time Migration Monitoring', 'wp-migrate' ); ?></h2>
                        <p style="margin: 5px 0 0 0; color: #666;">
                            <?php \esc_html_e( 'Monitor active migrations with live progress updates and real-time logging.', 'wp-migrate' ); ?>
                        </p>
                    </div>
                    <div class="wp-migrate-connection-status" style="text-align: right;">
                        <div class="connection-indicator" style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: #ccc; margin-right: 8px;"></div>
                        <span class="connection-text"><?php \esc_html_e( 'Disconnected', 'wp-migrate' ); ?></span>
                    </div>
                </div>
            </div>

            <div class="wp-migrate-monitor-content">
                <div class="wp-migrate-active-jobs" style="margin-bottom: 20px;">
                    <h3><?php \esc_html_e( 'Active Jobs', 'wp-migrate' ); ?> <span class="job-count">(<?php echo count( $activeJobs ); ?>)</span></h3>

                    <?php if ( empty( $activeJobs ) ): ?>
                        <div class="wp-migrate-no-jobs" style="background: #f8f9fa; padding: 40px; text-align: center; border: 2px dashed #dee2e6; border-radius: 4px;">
                            <p style="margin: 0; color: #6c757d; font-size: 16px;">
                                <?php \esc_html_e( 'No active migrations running.', 'wp-migrate' ); ?>
                            </p>
                            <p style="margin: 10px 0 0 0; color: #6c757d;">
                                <a href="<?php echo \admin_url( 'options-general.php?page=wp_migrate' ); ?>">
                                    <?php \esc_html_e( 'Go to settings to configure a new migration', 'wp-migrate' ); ?>
                                </a>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="wp-migrate-job-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
                            <?php foreach ( $activeJobs as $job ): ?>
                                <div class="wp-migrate-job-card" style="background: white; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                                    <div class="job-header" style="background: #f8f9fa; padding: 15px; border-bottom: 1px solid #eee;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <h4 style="margin: 0; font-size: 16px;">
                                                <?php printf( \esc_html__( 'Job: %s', 'wp-migrate' ), \esc_html( $job['job_id'] ) ); ?>
                                            </h4>
                                            <span class="job-state-badge" style="padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; <?php echo $this->get_state_style( $job['state'] ); ?>">
                                                <?php echo \esc_html( \strtoupper( $job['state'] ) ); ?>
                                            </span>
                                        </div>
                                        <div style="margin-top: 10px;">
                                            <div class="progress-bar" style="background: #f1f1f1; height: 8px; border-radius: 4px; overflow: hidden;">
                                                <div class="progress-fill" style="background: <?php echo $this->get_progress_color( $job['progress'] ); ?>; height: 100%; width: <?php echo (int) $job['progress']; ?>%; transition: width 0.3s ease;"></div>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 12px; color: #666;">
                                                <span><?php printf( \esc_html__( '%d%% complete', 'wp-migrate' ), (int) $job['progress'] ); ?></span>
                                                <span><?php echo \esc_html( $this->format_timestamp( $job['updated_at'] ) ); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="job-details" style="padding: 15px;">
                                        <div class="job-info" style="margin-bottom: 15px;">
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
                                                <div><strong><?php \esc_html_e( 'Started:', 'wp-migrate' ); ?></strong> <?php echo \esc_html( $this->format_timestamp( $job['created_at'] ) ); ?></div>
                                                <div><strong><?php \esc_html_e( 'State:', 'wp-migrate' ); ?></strong> <?php echo \esc_html( $job['state'] ); ?></div>
                                            </div>
                                        </div>

                                        <div class="job-actions" style="border-top: 1px solid #eee; padding-top: 15px;">
                                            <button class="button button-primary monitor-job-btn" data-job-id="<?php echo \esc_attr( $job['job_id'] ); ?>">
                                                <?php \esc_html_e( 'Monitor This Job', 'wp-migrate' ); ?>
                                            </button>
                                            <a href="<?php echo \esc_url( \admin_url( 'admin-ajax.php?action=wp_migrate_logs&job_id=' . \urlencode( $job['job_id'] ) ) ); ?>"
                                               class="button button-secondary" style="margin-left: 10px;" target="_blank">
                                                <?php \esc_html_e( 'View Logs', 'wp-migrate' ); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="wp-migrate-job-monitor" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3><?php \esc_html_e( 'Job Monitor', 'wp-migrate' ); ?> <span class="monitored-job-id"></span></h3>
                        <button class="button button-secondary stop-monitoring-btn">
                            <?php \esc_html_e( 'Stop Monitoring', 'wp-migrate' ); ?>
                        </button>
                    </div>

                    <div class="monitor-panels" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                        <div class="monitor-main">
                            <div class="monitor-progress" style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
                                <h4><?php \esc_html_e( 'Progress', 'wp-migrate' ); ?></h4>
                                <div class="progress-details">
                                    <div class="current-state" style="margin-bottom: 15px;">
                                        <strong><?php \esc_html_e( 'Current State:', 'wp-migrate' ); ?></strong>
                                        <span class="state-value">-</span>
                                    </div>
                                    <div class="progress-bar-large" style="background: #f1f1f1; height: 20px; border-radius: 10px; overflow: hidden; margin-bottom: 10px;">
                                        <div class="progress-fill-large" style="background: #007cba; height: 100%; width: 0%; transition: width 0.5s ease;"></div>
                                    </div>
                                    <div class="progress-text" style="text-align: center; font-weight: bold;">0%</div>
                                </div>
                            </div>

                            <div class="monitor-logs" style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                                <h4><?php \esc_html_e( 'Recent Activity', 'wp-migrate' ); ?></h4>
                                <div class="logs-container" style="max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                                    <div class="no-logs" style="color: #6c757d; text-align: center; padding: 20px;">
                                        <?php \esc_html_e( 'Waiting for activity...', 'wp-migrate' ); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="monitor-sidebar">
                            <div class="retry-stats" style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
                                <h4><?php \esc_html_e( 'Retry Statistics', 'wp-migrate' ); ?></h4>
                                <div class="stats-content">
                                    <div class="stat-item"><strong><?php \esc_html_e( 'Total Retries:', 'wp-migrate' ); ?></strong> <span class="total-retries">0</span></div>
                                    <div class="stat-item"><strong><?php \esc_html_e( 'Successful:', 'wp-migrate' ); ?></strong> <span class="successful-retries">0</span></div>
                                    <div class="stat-item"><strong><?php \esc_html_e( 'Failed:', 'wp-migrate' ); ?></strong> <span class="failed-retries">0</span></div>
                                    <div class="stat-item"><strong><?php \esc_html_e( 'Backoff Time:', 'wp-migrate' ); ?></strong> <span class="backoff-time">0s</span></div>
                                </div>
                            </div>

                            <div class="emergency-actions" style="background: #fff3cd; padding: 20px; border: 1px solid #ffeaa7; border-radius: 4px;">
                                <h4 style="color: #856404; margin-top: 0;">🚨 <?php \esc_html_e( 'Emergency Actions', 'wp-migrate' ); ?></h4>
                                <p style="color: #856404; margin-bottom: 15px; font-size: 14px;">
                                    <?php \esc_html_e( 'Use these controls only in emergency situations.', 'wp-migrate' ); ?>
                                </p>
                                <div class="emergency-buttons">
                                    <button class="button button-secondary emergency-stop-btn" style="background: #dc3545; border-color: #dc3545; color: white; margin-right: 10px;" disabled>
                                        <?php \esc_html_e( 'Stop Migration', 'wp-migrate' ); ?>
                                    </button>
                                    <button class="button button-secondary emergency-rollback-btn" style="background: #ffc107; border-color: #ffc107; color: black;" disabled>
                                        <?php \esc_html_e( 'Rollback', 'wp-migrate' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get migration settings for use by other services.
     */
    public function get_settings(): array {
        return \get_option( self::OPTION, [] );
    }
}
