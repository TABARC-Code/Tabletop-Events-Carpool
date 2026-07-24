<?php
/**
 * Self-service listing management via a magic link — same pattern as
 * the LFG board's class-tlfg-manage.php. A poster can edit details or
 * take their listing down once a lift's sorted, with no account
 * needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCAR_Manage {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'save_post_' . TCAR_POST_TYPE, array( $this, 'ensure_manage_token' ) );

		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'template_include', array( $this, 'template_include' ) );

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function add_rewrite_rules() {
		add_rewrite_rule( '^manage-carpool/?$', 'index.php?tcar_manage_page=1', 'top' );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'tcar_manage_page';
		return $vars;
	}

	public function template_include( $template ) {
		if ( ! get_query_var( 'tcar_manage_page' ) ) {
			return $template;
		}
		return TCAR_PLUGIN_DIR . 'templates/manage-carpool.php';
	}

	public function ensure_manage_token( $post_id ) {
		if ( ! get_post_meta( $post_id, '_tcar_manage_token', true ) ) {
			update_post_meta( $post_id, '_tcar_manage_token', wp_generate_password( 40, false, false ) );
		}
	}

	public function get_manage_url( $post_id ) {
		$token = get_post_meta( $post_id, '_tcar_manage_token', true );
		return add_query_arg(
			array( 'post' => $post_id, 'token' => $token ),
			home_url( '/manage-carpool/' )
		);
	}

	public function send_confirmation_email( $post_id ) {
		$to = get_post_meta( $post_id, '_tcar_email', true );
		if ( ! is_email( $to ) ) {
			return;
		}
		wp_mail(
			$to,
			sprintf( '[%s] Your carpool listing is up', get_bloginfo( 'name' ) ),
			sprintf(
				"Thanks — your listing is now awaiting review.\n\nSorted a lift already, or need to change something? Use this link any time to edit it or take it down:\n%s\n\nNo account or password needed — keep this link safe, it's what lets you manage this listing.\n",
				$this->get_manage_url( $post_id )
			)
		);
	}

	public function register_routes() {
		register_rest_route(
			'tcar/v1',
			'/manage/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_managed_listing' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'tcar/v1',
			'/manage/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_managed_listing' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'tcar/v1',
			'/manage/(?P<id>\d+)/sorted',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'mark_sorted' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private function verify_token( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$token   = (string) $request->get_param( 'token' );
		$post    = get_post( $post_id );

		if ( ! $post || TCAR_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'tcar_not_found', __( 'Listing not found.', 'tabletop-events-carpool' ), array( 'status' => 404 ) );
		}
		$real_token = get_post_meta( $post_id, '_tcar_manage_token', true );
		if ( ! $token || ! $real_token || ! hash_equals( $real_token, $token ) ) {
			return new WP_Error( 'tcar_invalid_token', __( 'Invalid or expired management link.', 'tabletop-events-carpool' ), array( 'status' => 403 ) );
		}
		return $post;
	}

	public function get_managed_listing( WP_REST_Request $request ) {
		$post = $this->verify_token( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return rest_ensure_response(
			array(
				'id'     => $post->ID,
				'status' => $post->post_status,
				'type'   => get_post_meta( $post->ID, '_tcar_type', true ),
				'area'   => get_post_meta( $post->ID, '_tcar_area', true ),
				'seats'  => (int) get_post_meta( $post->ID, '_tcar_seats', true ),
				'notes'  => get_post_meta( $post->ID, '_tcar_notes', true ),
				'name'   => get_post_meta( $post->ID, '_tcar_name', true ),
			)
		);
	}

	public function update_managed_listing( WP_REST_Request $request ) {
		$post = $this->verify_token( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$params = $request->get_json_params() ?: $request->get_body_params();

		if ( isset( $params['area'] ) ) {
			update_post_meta( $post->ID, '_tcar_area', sanitize_text_field( $params['area'] ) );
		}
		if ( isset( $params['seats'] ) ) {
			update_post_meta( $post->ID, '_tcar_seats', max( 1, (int) $params['seats'] ) );
		}
		if ( isset( $params['notes'] ) ) {
			update_post_meta( $post->ID, '_tcar_notes', sanitize_textarea_field( $params['notes'] ) );
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Saved.', 'tabletop-events-carpool' ) ) );
	}

	/**
	 * "Sorted" rather than a plain delete: the lift's been arranged, the
	 * listing's done its job, and trashing it makes it disappear from
	 * the board and REST feed in one go — same reasoning as the LFG
	 * board's "mark filled" action.
	 */
	public function mark_sorted( WP_REST_Request $request ) {
		$post = $this->verify_token( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		wp_trash_post( $post->ID );
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Taken down — glad it got sorted!', 'tabletop-events-carpool' ) ) );
	}
}
