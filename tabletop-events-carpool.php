<?php
/**
 * Plugin Name:       Tabletop Events Calendar — Carpool
 * Plugin URI:        https://github.com/TABARC-Code/Tabletop-Events-Carpool
 * Description:       A lift-share board for Tabletop Events Calendar events — offer a seat or ask for one, anchored to a specific event. Requires the Tabletop Events Calendar plugin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  tabletop-events-calendar
 * Author:            TABARC-Code
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tabletop-events-carpool
 *
 * A listing is just a name, a rough departure area, a seat count and
 * some notes, anchored to one event ID — the same "anchor on data
 * that already exists" approach as every other plugin in this family,
 * rather than a second identity system or its own account model.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'TCAR_VERSION', '1.0.0' );
define( 'TCAR_PLUGIN_FILE', __FILE__ );
define( 'TCAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TCAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TCAR_POST_TYPE', 'tcar_listing' );

spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'TCAR_' ) !== 0 ) {
			return;
		}
		$slug = strtolower( str_replace( '_', '-', $class ) );
		$path = TCAR_PLUGIN_DIR . 'includes/class-' . $slug . '.php';
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

function tcar_init() {
	if ( ! tcar_dependency_met() ) {
		add_action( 'admin_notices', 'tcar_missing_dependency_notice' );
		return;
	}

	load_plugin_textdomain( 'tabletop-events-carpool', false, dirname( plugin_basename( TCAR_PLUGIN_FILE ) ) . '/languages' );

	TCAR_Post_Type::instance();
	TCAR_Settings::instance();
	TCAR_Rest::instance();
	TCAR_Manage::instance();
	TCAR_Cron::instance();
	TCAR_Shortcode_Carpool::instance();

	tcar_maybe_upgrade();
}
add_action( 'plugins_loaded', 'tcar_init', 20 );

function tcar_dependency_met() {
	return defined( 'TEC_POST_TYPE' ) && class_exists( 'TEC_Admin' );
}

function tcar_missing_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Tabletop Events Calendar — Carpool requires the Tabletop Events Calendar plugin to be installed and active.', 'tabletop-events-carpool' ) .
		'</p></div>';
}

/**
 * Deferred to 'init' for the same reason as the core plugin's own
 * upgrade routine — flush_rewrite_rules() needs $wp_rewrite, which
 * doesn't exist yet on plugins_loaded.
 */
function tcar_maybe_upgrade() {
	add_action( 'init', 'tcar_run_upgrade', 20 );
}
function tcar_run_upgrade() {
	$installed = get_option( 'tcar_plugin_version' );
	if ( $installed === TCAR_VERSION ) {
		return;
	}
	flush_rewrite_rules();
	if ( class_exists( 'TCAR_Cron' ) ) {
		TCAR_Cron::instance()->schedule_events();
	}
	update_option( 'tcar_plugin_version', TCAR_VERSION, false );
}

function tcar_activate() {
	if ( ! tcar_dependency_met() ) {
		// Best-effort: WordPress activates one plugin at a time, so the
		// core plugin's own plugins_loaded hook may not have run yet
		// even if it's active. The admin notice above catches a
		// genuinely missing dependency.
		flush_rewrite_rules();
		update_option( 'tcar_plugin_version', TCAR_VERSION, false );
		return;
	}

	require_once TCAR_PLUGIN_DIR . 'includes/class-tcar-post-type.php';
	TCAR_Post_Type::instance()->register_post_type();

	require_once TCAR_PLUGIN_DIR . 'includes/class-tcar-manage.php';
	TCAR_Manage::instance()->add_rewrite_rules();

	require_once TCAR_PLUGIN_DIR . 'includes/class-tcar-cron.php';
	TCAR_Cron::instance()->schedule_events();

	flush_rewrite_rules();
	update_option( 'tcar_plugin_version', TCAR_VERSION, false );
}
register_activation_hook( TCAR_PLUGIN_FILE, 'tcar_activate' );

function tcar_deactivate() {
	if ( class_exists( 'TCAR_Cron' ) ) {
		TCAR_Cron::instance()->unschedule_events();
	}
	flush_rewrite_rules();
}
register_deactivation_hook( TCAR_PLUGIN_FILE, 'tcar_deactivate' );
