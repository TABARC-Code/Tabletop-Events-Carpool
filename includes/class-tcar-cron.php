<?php
/**
 * Daily tidy-up: trashes carpool listings whose event happened a good
 * while ago. The public listings feed already excludes anything past
 * its event's date on its own (see TCAR_Rest — well, actually the
 * event date check lives at submission time; expired listings just
 * sit there quietly otherwise), so this is what stops wp-admin's
 * Carpool list slowly filling up with listings for events long over.
 * A grace period gives a poster a few days to still find and take
 * down their own listing from wp-admin before it's gone for good.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCAR_Cron {

	private static $instance = null;
	const HOOK_CLEANUP = 'tcar_cron_cleanup';
	const GRACE_DAYS   = 7;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( self::HOOK_CLEANUP, array( $this, 'trash_stale_listings' ) );
	}

	public function schedule_events() {
		if ( ! wp_next_scheduled( self::HOOK_CLEANUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_CLEANUP );
		}
	}

	public function unschedule_events() {
		$timestamp = wp_next_scheduled( self::HOOK_CLEANUP );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_CLEANUP );
		}
	}

	public function trash_stale_listings() {
		$cutoff = gmdate( 'Y-m-d', strtotime( '-' . self::GRACE_DAYS . ' days' ) );

		$listings = get_posts(
			array(
				'post_type'      => TCAR_POST_TYPE,
				'post_status'    => array( 'publish', 'pending' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $listings as $listing_id ) {
			$event_id = (int) get_post_meta( $listing_id, '_tcar_event_id', true );
			$event_date = $event_id ? get_post_meta( $event_id, '_tec_date', true ) : '';
			if ( $event_date && $event_date < $cutoff ) {
				wp_trash_post( $listing_id );
			}
		}
	}
}
