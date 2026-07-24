<?php
/**
 * The tcar_listing CPT: a single lift offer or lift request, anchored
 * to one tec_event. Not public — same treatment as tevr_review — a
 * listing is only ever seen through this plugin's own REST endpoint
 * and shortcode, never a theme-rendered single/archive page, so there's
 * nothing for WordPress's default templating to do with it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCAR_Post_Type {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . TCAR_POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
	}

	public function register_post_type() {
		register_post_type(
			TCAR_POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Carpool Listings', 'tabletop-events-carpool' ),
					'singular_name' => __( 'Carpool Listing', 'tabletop-events-carpool' ),
					'add_new_item'  => __( 'Add New Listing', 'tabletop-events-carpool' ),
					'edit_item'     => __( 'Edit Listing', 'tabletop-events-carpool' ),
					'all_items'     => __( 'Carpool', 'tabletop-events-carpool' ),
					'search_items'  => __( 'Search Listings', 'tabletop-events-carpool' ),
					'not_found'     => __( 'No carpool listings found.', 'tabletop-events-carpool' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . TEC_POST_TYPE,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title' ),
				'show_in_rest'        => false, // Vetted through /tcar/v1/, not the default REST controller.
			)
		);
	}

	public function add_meta_box() {
		add_meta_box(
			'tcar_listing_meta',
			__( 'Listing Details', 'tabletop-events-carpool' ),
			array( $this, 'render_meta_box' ),
			TCAR_POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'tcar_save_listing_meta', 'tcar_listing_meta_nonce' );

		$event_id = (int) get_post_meta( $post->ID, '_tcar_event_id', true );
		$type     = get_post_meta( $post->ID, '_tcar_type', true ) ?: 'offer';
		$name     = get_post_meta( $post->ID, '_tcar_name', true );
		$email    = get_post_meta( $post->ID, '_tcar_email', true );
		$area     = get_post_meta( $post->ID, '_tcar_area', true );
		$seats    = (int) get_post_meta( $post->ID, '_tcar_seats', true ) ?: 1;
		$notes    = get_post_meta( $post->ID, '_tcar_notes', true );
		?>
		<style>
			.tcar-meta-row { display: flex; gap: 20px; margin-bottom: 14px; flex-wrap: wrap; }
			.tcar-meta-field { flex: 1; min-width: 220px; }
			.tcar-meta-field label { display: block; font-weight: 600; margin-bottom: 4px; }
			.tcar-meta-field input, .tcar-meta-field select, .tcar-meta-field textarea { width: 100%; }
			.tcar-meta-box h3 { margin: 0 0 10px; padding-top: 14px; border-top: 1px solid #dcdcde; }
			.tcar-meta-box h3:first-child { padding-top: 0; border-top: none; }
		</style>
		<div class="tcar-meta-box">
			<h3><?php esc_html_e( 'Listing', 'tabletop-events-carpool' ); ?></h3>
			<div class="tcar-meta-row">
				<div class="tcar-meta-field">
					<label for="tcar_event_id"><?php esc_html_e( 'Event ID', 'tabletop-events-carpool' ); ?></label>
					<input type="number" min="1" name="tcar_event_id" id="tcar_event_id" value="<?php echo esc_attr( $event_id ); ?>">
					<p class="description"><?php echo $event_id ? '<a href="' . esc_url( get_edit_post_link( $event_id ) ) . '">' . esc_html( get_the_title( $event_id ) ) . '</a>' : ''; ?></p>
				</div>
				<div class="tcar-meta-field">
					<label for="tcar_type"><?php esc_html_e( 'Type', 'tabletop-events-carpool' ); ?></label>
					<select name="tcar_type" id="tcar_type">
						<option value="offer" <?php selected( $type, 'offer' ); ?>><?php esc_html_e( 'Offering a lift', 'tabletop-events-carpool' ); ?></option>
						<option value="request" <?php selected( $type, 'request' ); ?>><?php esc_html_e( 'Looking for a lift', 'tabletop-events-carpool' ); ?></option>
					</select>
				</div>
			</div>
			<div class="tcar-meta-row">
				<div class="tcar-meta-field">
					<label for="tcar_area"><?php esc_html_e( 'Departure area / town', 'tabletop-events-carpool' ); ?></label>
					<input type="text" name="tcar_area" id="tcar_area" value="<?php echo esc_attr( $area ); ?>">
				</div>
				<div class="tcar-meta-field">
					<label for="tcar_seats"><?php esc_html_e( 'Seats offered / needed', 'tabletop-events-carpool' ); ?></label>
					<input type="number" min="1" name="tcar_seats" id="tcar_seats" value="<?php echo esc_attr( $seats ); ?>">
				</div>
			</div>
			<div class="tcar-meta-row">
				<div class="tcar-meta-field" style="flex-basis:100%;">
					<label for="tcar_notes"><?php esc_html_e( 'Notes (route, timing, fuel costs, etc.)', 'tabletop-events-carpool' ); ?></label>
					<textarea name="tcar_notes" id="tcar_notes" rows="3"><?php echo esc_textarea( $notes ); ?></textarea>
				</div>
			</div>

			<h3><?php esc_html_e( 'Poster (private)', 'tabletop-events-carpool' ); ?></h3>
			<div class="tcar-meta-row">
				<div class="tcar-meta-field">
					<label for="tcar_name"><?php esc_html_e( 'Display name', 'tabletop-events-carpool' ); ?></label>
					<input type="text" name="tcar_name" id="tcar_name" value="<?php echo esc_attr( $name ); ?>">
				</div>
				<div class="tcar-meta-field">
					<label for="tcar_email"><?php esc_html_e( 'Email (never shown publicly — used only for the "get in touch" relay and their manage link)', 'tabletop-events-carpool' ); ?></label>
					<input type="email" name="tcar_email" id="tcar_email" value="<?php echo esc_attr( $email ); ?>">
				</div>
			</div>
		</div>
		<?php
	}

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['tcar_listing_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tcar_listing_meta_nonce'] ) ), 'tcar_save_listing_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['tcar_event_id'] ) ) {
			update_post_meta( $post_id, '_tcar_event_id', (int) $_POST['tcar_event_id'] );
		}
		if ( isset( $_POST['tcar_type'] ) ) {
			$type = sanitize_key( wp_unslash( $_POST['tcar_type'] ) );
			update_post_meta( $post_id, '_tcar_type', in_array( $type, array( 'offer', 'request' ), true ) ? $type : 'offer' );
		}
		if ( isset( $_POST['tcar_area'] ) ) {
			update_post_meta( $post_id, '_tcar_area', sanitize_text_field( wp_unslash( $_POST['tcar_area'] ) ) );
		}
		if ( isset( $_POST['tcar_seats'] ) ) {
			update_post_meta( $post_id, '_tcar_seats', max( 1, (int) $_POST['tcar_seats'] ) );
		}
		if ( isset( $_POST['tcar_notes'] ) ) {
			update_post_meta( $post_id, '_tcar_notes', sanitize_textarea_field( wp_unslash( $_POST['tcar_notes'] ) ) );
		}
		if ( isset( $_POST['tcar_name'] ) ) {
			update_post_meta( $post_id, '_tcar_name', sanitize_text_field( wp_unslash( $_POST['tcar_name'] ) ) );
		}
		if ( isset( $_POST['tcar_email'] ) ) {
			update_post_meta( $post_id, '_tcar_email', sanitize_email( wp_unslash( $_POST['tcar_email'] ) ) );
		}
	}
}
