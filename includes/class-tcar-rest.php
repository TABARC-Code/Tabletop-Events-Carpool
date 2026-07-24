<?php
/**
 * /wp-json/tcar/v1/* — one event's live listings, submitting a new
 * one, and a "get in touch" relay. No caching layer, same reasoning
 * as the LFG board's and Reviews' own REST classes: this is low-churn,
 * low-volume traffic, so a live query is plenty.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCAR_Rest {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'tcar/v1',
			'/event/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_event_listings' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'tcar/v1',
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'tcar/v1',
			'/contact/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'contact_poster' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @return WP_REST_Response Every live listing for this event — no
	 *         poster email or manage token ever appears here, same
	 *         guarantee as the LFG board's public feed.
	 */
	public function get_event_listings( WP_REST_Request $request ) {
		$event_id = (int) $request->get_param( 'id' );

		$posts = get_posts(
			array(
				'post_type'      => TCAR_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'meta_query'     => array(
					array( 'key' => '_tcar_event_id', 'value' => $event_id, 'compare' => '=' ),
				),
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			$out[] = array(
				'id'    => $post->ID,
				'type'  => get_post_meta( $post->ID, '_tcar_type', true ),
				'name'  => get_post_meta( $post->ID, '_tcar_name', true ),
				'area'  => get_post_meta( $post->ID, '_tcar_area', true ),
				'seats' => (int) get_post_meta( $post->ID, '_tcar_seats', true ),
				'notes' => get_post_meta( $post->ID, '_tcar_notes', true ),
			);
		}

		return rest_ensure_response( $out );
	}

	public function submit( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: $request->get_body_params();

		if ( ! empty( $params['website'] ) ) {
			return rest_ensure_response( array( 'success' => true ) ); // Honeypot.
		}

		$event_id = (int) ( $params['event_id'] ?? 0 );
		$event    = get_post( $event_id );
		if ( ! $event || TEC_POST_TYPE !== $event->post_type || 'publish' !== $event->post_status ) {
			return new WP_Error( 'tcar_no_event', __( 'That event could not be found.', 'tabletop-events-carpool' ), array( 'status' => 404 ) );
		}

		$event_date = get_post_meta( $event_id, '_tec_date', true );
		if ( $event_date && $event_date < current_time( 'Y-m-d' ) ) {
			return new WP_Error( 'tcar_event_over', __( "This event's already happened, so a lift-share listing wouldn't do much good now.", 'tabletop-events-carpool' ), array( 'status' => 400 ) );
		}

		$type  = sanitize_key( $params['type'] ?? '' );
		$name  = sanitize_text_field( $params['name'] ?? '' );
		$email = sanitize_email( $params['email'] ?? '' );
		$area  = sanitize_text_field( $params['area'] ?? '' );
		$seats = (int) ( $params['seats'] ?? 0 );
		$notes = sanitize_textarea_field( $params['notes'] ?? '' );

		if ( ! in_array( $type, array( 'offer', 'request' ), true ) ) {
			return new WP_Error( 'tcar_invalid', __( "Please say whether you're offering a lift or looking for one.", 'tabletop-events-carpool' ), array( 'status' => 400 ) );
		}
		if ( ! $name || ! is_email( $email ) ) {
			return new WP_Error( 'tcar_invalid', __( 'Please enter your name and a valid email address.', 'tabletop-events-carpool' ), array( 'status' => 400 ) );
		}
		if ( ! $area ) {
			return new WP_Error( 'tcar_invalid', __( 'Please add a rough departure area or town.', 'tabletop-events-carpool' ), array( 'status' => 400 ) );
		}
		if ( $seats < 1 ) {
			return new WP_Error( 'tcar_invalid', __( 'Please say how many seats are offered or needed.', 'tabletop-events-carpool' ), array( 'status' => 400 ) );
		}

		$title = 'offer' === $type
			? sprintf( __( '%1$s is offering a lift to %2$s', 'tabletop-events-carpool' ), $name, get_the_title( $event ) )
			: sprintf( __( '%1$s is looking for a lift to %2$s', 'tabletop-events-carpool' ), $name, get_the_title( $event ) );

		$listing_id = wp_insert_post(
			array(
				'post_type'   => TCAR_POST_TYPE,
				'post_status' => 'pending',
				'post_title'  => sanitize_text_field( $title ),
			),
			true
		);
		if ( is_wp_error( $listing_id ) ) {
			return $listing_id;
		}

		update_post_meta( $listing_id, '_tcar_event_id', $event_id );
		update_post_meta( $listing_id, '_tcar_type', $type );
		update_post_meta( $listing_id, '_tcar_name', $name );
		update_post_meta( $listing_id, '_tcar_email', $email );
		update_post_meta( $listing_id, '_tcar_area', $area );
		update_post_meta( $listing_id, '_tcar_seats', $seats );
		update_post_meta( $listing_id, '_tcar_notes', $notes );

		$this->notify_admin( $listing_id, $title );
		if ( class_exists( 'TCAR_Manage' ) ) {
			TCAR_Manage::instance()->send_confirmation_email( $listing_id );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( "Thanks — we'll review your listing shortly. Check your email for a link to edit it or take it down once you've sorted a lift.", 'tabletop-events-carpool' ),
			)
		);
	}

	private function notify_admin( $listing_id, $title ) {
		$to = class_exists( 'TCAR_Settings' ) ? TCAR_Settings::get()['notification_email'] : get_option( 'admin_email' );
		if ( ! $to ) {
			return;
		}
		wp_mail(
			$to,
			sprintf( '[%s] New carpool listing: %s', get_bloginfo( 'name' ), $title ),
			sprintf(
				"A new carpool listing is awaiting review:\n\n%s\n\nReview it here:\n%s\n",
				$title,
				admin_url( 'post.php?post=' . $listing_id . '&action=edit' )
			)
		);
	}

	/**
	 * "Get in touch" relay — same reasoning as the LFG board's contact
	 * relay: the visitor never sees the poster's real address, the
	 * Reply-To header means the poster can just hit reply.
	 */
	public function contact_poster( WP_REST_Request $request ) {
		$listing_id = (int) $request->get_param( 'id' );
		$listing    = get_post( $listing_id );
		if ( ! $listing || TCAR_POST_TYPE !== $listing->post_type || 'publish' !== $listing->post_status ) {
			return new WP_Error( 'tcar_not_found', __( 'Listing not found.', 'tabletop-events-carpool' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params() ?: $request->get_body_params();
		if ( ! empty( $params['website'] ) ) {
			return rest_ensure_response( array( 'success' => true ) ); // Honeypot.
		}

		$from_name  = sanitize_text_field( $params['name'] ?? '' );
		$from_email = sanitize_email( $params['email'] ?? '' );
		$message    = sanitize_textarea_field( $params['message'] ?? '' );
		if ( ! $from_name || ! is_email( $from_email ) || ! $message ) {
			return new WP_Error( 'tcar_invalid', __( 'Please fill in your name, email, and a message.', 'tabletop-events-carpool' ), array( 'status' => 400 ) );
		}

		$to = get_post_meta( $listing_id, '_tcar_email', true );
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'tcar_no_contact', __( "This poster can't be reached right now.", 'tabletop-events-carpool' ), array( 'status' => 404 ) );
		}

		wp_mail(
			$to,
			sprintf( '[%s] Someone replied to your carpool listing', get_bloginfo( 'name' ) ),
			sprintf(
				"%s (%s) replied to \"%s\":\n\n%s\n\nJust hit reply to get back to them.\n",
				$from_name,
				$from_email,
				get_the_title( $listing ),
				$message
			),
			array( 'Reply-To: ' . $from_name . ' <' . $from_email . '>' )
		);

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Message sent!', 'tabletop-events-carpool' ) ) );
	}
}
