<?php
namespace MK\WcPluginStarter\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use MK\WcPluginStarter\Contracts\Registrable;

final class SettingsPage implements Registrable {
    public const OPTION = 'mk_wcps_settings';

    public function register(): void {
        \add_action( 'admin_menu', [ $this, 'add_menu' ] );
        \add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_menu(): void {
        \add_options_page(
            \__( 'MK WC Plugin Starter', 'mk-wc-plugin-starter' ),
            \__( 'MK WC Starter', 'mk-wc-plugin-starter' ),
            'manage_options',
            'mk-wcps',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        \register_setting( 'mk_wcps', self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize' ],
            'default'           => [
                'enabled' => 1,
                'shared_key' => '',
                'peer_url' => '',
                'email_blackhole' => 1,
            ],
        ] );

        \add_settings_section(
            'mk_wcps_section',
            \__( 'General', 'mk-wc-plugin-starter' ),
            '__return_false',
            'mk_wcps'
        );

        \add_settings_field(
            'enabled',
            \__( 'Enable features', 'mk-wc-plugin-starter' ),
            [ $this, 'field_enabled' ],
            'mk_wcps',
            'mk_wcps_section'
        );

        \add_settings_field(
            'shared_key',
            \__( 'Shared Key', 'mk-wc-plugin-starter' ),
            [ $this, 'field_shared_key' ],
            'mk_wcps',
            'mk_wcps_section'
        );

        \add_settings_field(
            'peer_url',
            \__( 'Peer Base URL', 'mk-wc-plugin-starter' ),
            [ $this, 'field_peer_url' ],
            'mk_wcps',
            'mk_wcps_section'
        );

        \add_settings_field(
            'email_blackhole',
            \__( 'Blackhole Emails/Webhooks (staging safety)', 'mk-wc-plugin-starter' ),
            [ $this, 'field_email_blackhole' ],
            'mk_wcps',
            'mk_wcps_section'
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
            'enabled' => isset( $value['enabled'] ) ? 1 : 0,
            // Store shared key securely; avoid displaying it back in forms.
            'shared_key' => isset( $value['shared_key'] ) ? (string) $value['shared_key'] : '',
            'peer_url' => $peer,
            'email_blackhole' => isset( $value['email_blackhole'] ) ? 1 : 0,
        ];
    }

    public function field_enabled(): void {
        $opts = \get_option( self::OPTION, [ 'enabled' => 1 ] );
        ?>
        <label>
            <input type="checkbox" name="<?php echo \esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php \checked( ! empty( $opts['enabled'] ) ); ?> />
            <?php \esc_html_e( 'Turn on starter features', 'mk-wc-plugin-starter' ); ?>
        </label>
        <?php
    }

    public function field_shared_key(): void {
        $opts = \get_option( self::OPTION, [] );
        ?>
        <input type="password" autocomplete="new-password" name="<?php echo \esc_attr( self::OPTION ); ?>[shared_key]" value="" placeholder="<?php \esc_attr_e( 'Enter or paste shared key', 'mk-wc-plugin-starter' ); ?>" class="regular-text" />
        <p class="description"><?php \esc_html_e( 'Used for HMAC signing between peers. Not displayed after save.', 'mk-wc-plugin-starter' ); ?></p>
        <?php
    }

    public function field_peer_url(): void {
        $opts = \get_option( self::OPTION, [] );
        $peer = isset( $opts['peer_url'] ) ? (string) $opts['peer_url'] : '';
        ?>
        <input type="url" name="<?php echo \esc_attr( self::OPTION ); ?>[peer_url]" value="<?php echo \esc_attr( $peer ); ?>" placeholder="https://staging.example.com" class="regular-text" />
        <p class="description"><?php \esc_html_e( 'Base URL of the peer site. TLS required.', 'mk-wc-plugin-starter' ); ?></p>
        <?php
    }

    public function field_email_blackhole(): void {
        $opts = \get_option( self::OPTION, [ 'email_blackhole' => 1 ] );
        ?>
        <label>
            <input type="checkbox" name="<?php echo \esc_attr( self::OPTION ); ?>[email_blackhole]" value="1" <?php \checked( ! empty( $opts['email_blackhole'] ) ); ?> />
            <?php \esc_html_e( 'Disable/blackhole outbound emails & webhooks on staging', 'mk-wc-plugin-starter' ); ?>
        </label>
        <?php
    }

    public function render_page(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php \esc_html_e( 'MK WC Plugin Starter', 'mk-wc-plugin-starter' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                    \settings_fields( 'mk_wcps' );
                    \do_settings_sections( 'mk_wcps' );
                    \submit_button( \__( 'Save Settings', 'mk-wc-plugin-starter' ) );
                ?>
            </form>
            <p><em><?php \esc_html_e( 'This is a minimal, hooks-first, class-based starter. Extend by adding services under src/.', 'mk-wc-plugin-starter' ); ?></em></p>
        </div>
        <?php
    }
}
