<?php
namespace WpMigrate\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use WpMigrate\Contracts\Registrable;

final class SettingsPage implements Registrable {
    private const OPTION = 'wp_migrate_settings';

    public function register(): void {
        \add_action( 'admin_menu', [ $this, 'add_menu' ] );
        \add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_menu(): void {
        \add_options_page(
            \__( 'WP-Migrate Settings', 'wp-migrate' ),
            \__( 'WP-Migrate', 'wp-migrate' ),
            'manage_options',
            'wp_migrate',
            [ $this, 'render_page' ]
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
     * Get migration settings for use by other services.
     */
    public function get_settings(): array {
        return \get_option( self::OPTION, [] );
    }
}
