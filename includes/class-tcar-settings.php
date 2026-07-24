<?php
/**
 * A deliberately small settings surface, same reasoning as the LFG
 * board's own settings page — this plugin does one small job (a
 * lift-share board), so it gets one small settings screen, not a
 * full tabbed page like the core plugin's.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCAR_Settings {

	private static $instance = null;
	const OPTION_KEY = 'tcar_settings';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public static function default_settings() {
		return array(
			'notification_email' => get_option( 'admin_email' ),
		);
	}

	public static function get() {
		$settings = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $settings, self::default_settings() );
	}

	public function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . TEC_POST_TYPE,
			__( 'Carpool — Settings', 'tabletop-events-carpool' ),
			__( 'Carpool Settings', 'tabletop-events-carpool' ),
			'manage_options',
			'tcar-settings',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'tcar_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::default_settings(),
			)
		);
	}

	public function sanitize( $input ) {
		$defaults = self::default_settings();
		return array(
			'notification_email' => sanitize_email( $input['notification_email'] ?? $defaults['notification_email'] ) ?: $defaults['notification_email'],
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = self::get();

		if ( class_exists( 'TEC_Admin' ) ) {
			TEC_Admin::page_header(
				'car',
				__( 'Carpool — Settings', 'tabletop-events-carpool' ),
				__( 'Who hears about new lift-share listings.', 'tabletop-events-carpool' )
			);
		}
		?>
		<div class="wrap">
			<form method="post" action="options.php">
				<?php settings_fields( 'tcar_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="tcar_notif_email"><?php esc_html_e( 'New listing notification email', 'tabletop-events-carpool' ); ?></label></th>
						<td><input type="email" id="tcar_notif_email" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notification_email]" value="<?php echo esc_attr( $settings['notification_email'] ); ?>" class="regular-text"></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
