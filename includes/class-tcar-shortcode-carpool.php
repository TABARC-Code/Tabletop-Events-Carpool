<?php
/**
 * [tabletop_event_carpool event="123"] — the public lift-share widget
 * for one event: live listings plus a "post a listing" form. Reuses
 * the core plugin's submission-form.css wholesale, same reasoning as
 * every other plugin in this family.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCAR_Shortcode_Carpool {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_shortcode( 'tabletop_event_carpool', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array( 'event' => 0 ), $atts, 'tabletop_event_carpool' );
		$event_id = (int) $atts['event'];
		if ( ! $event_id ) {
			return '';
		}

		wp_enqueue_style( 'tec-submit', TEC_PLUGIN_URL . 'assets/css/submission-form.css', array(), TEC_VERSION );
		wp_enqueue_style( 'tcar-carpool', TCAR_PLUGIN_URL . 'assets/css/carpool.css', array(), TCAR_VERSION );
		wp_enqueue_script( 'tcar-carpool', TCAR_PLUGIN_URL . 'assets/js/carpool-board.js', array(), TCAR_VERSION, true );

		// wp_head()'s print pass has usually already run by the time a
		// shortcode renders inside the_content() — print explicitly or
		// these never make it onto the page at all.
		wp_print_styles( 'tec-submit' );
		wp_print_styles( 'tcar-carpool' );

		wp_localize_script(
			'tcar-carpool',
			'TCAR_CARPOOL',
			array(
				'restUrl' => esc_url_raw( rest_url( 'tcar/v1' ) ),
				'eventId' => $event_id,
			)
		);

		return '<div class="tcar-carpool-root" data-tcar-carpool></div>';
	}
}
