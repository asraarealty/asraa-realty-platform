<?php
/**
 * Agent Custom Post Type
 *
 * Registers the `agent` CPT, meta boxes, custom admin columns,
 * and the `agent_specialty` taxonomy for Asraa Realty.
 *
 * @package Asraa_CRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Asraa_Agent_Post_Type {

	/** @var string Post-type slug */
	const POST_TYPE = 'agent';

	/** @var string Taxonomy slug */
	const TAXONOMY = 'agent_specialty';

	/** @var string Meta-box nonce action */
	const NONCE_ACTION = 'asraa_agent_meta_save';

	/** @var string Meta-box nonce field */
	const NONCE_FIELD = 'asraa_agent_meta_nonce';

	/** Meta-field keys */
	const FIELDS = [
		'agent_position'       => [ 'label' => 'Position / Title',      'type' => 'text',  'sanitize' => 'sanitize_text_field' ],
		'agent_phone'          => [ 'label' => 'Phone',                  'type' => 'tel',   'sanitize' => 'sanitize_text_field' ],
		'agent_email'          => [ 'label' => 'Agent Email',            'type' => 'email', 'sanitize' => 'sanitize_email' ],
		'agent_whatsapp'       => [ 'label' => 'WhatsApp Number',        'type' => 'tel',   'sanitize' => 'sanitize_text_field' ],
		'agent_license'        => [ 'label' => 'License / DLD Number',   'type' => 'text',  'sanitize' => 'sanitize_text_field' ],
		'agent_languages'      => [ 'label' => 'Languages Spoken',       'type' => 'text',  'sanitize' => 'sanitize_text_field' ],
		'agent_specialization' => [ 'label' => 'Specialization',         'type' => 'text',  'sanitize' => 'sanitize_text_field' ],
		'agent_experience'     => [ 'label' => 'Years of Experience',    'type' => 'number','sanitize' => 'absint' ],
		'agent_facebook'       => [ 'label' => 'Facebook URL',           'type' => 'url',   'sanitize' => 'esc_url_raw' ],
		'agent_instagram'      => [ 'label' => 'Instagram URL',          'type' => 'url',   'sanitize' => 'esc_url_raw' ],
		'agent_linkedin'       => [ 'label' => 'LinkedIn URL',           'type' => 'url',   'sanitize' => 'esc_url_raw' ],
		'agent_twitter'        => [ 'label' => 'X / Twitter URL',        'type' => 'url',   'sanitize' => 'esc_url_raw' ],
	];

	public function __construct() {
		add_action( 'init',                  [ $this, 'register_post_type' ] );
		add_action( 'init',                  [ $this, 'register_taxonomy' ] );
		add_action( 'add_meta_boxes',        [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_meta' ] );

		// Admin columns
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns',       [ $this, 'admin_columns' ] );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'admin_column_values' ], 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', [ $this, 'sortable_columns' ] );
		add_action( 'pre_get_posts',         [ $this, 'orderby_handler' ] );

		// Admin scripts / styles
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
	}

	/* ----------------------------------------------------------------
	   Post-type & taxonomy registration
	---------------------------------------------------------------- */

	public function register_post_type() {
		$labels = [
			'name'               => _x( 'Agents',             'post type general name', 'asraa-crm' ),
			'singular_name'      => _x( 'Agent',              'post type singular name', 'asraa-crm' ),
			'add_new'            => __( 'Add New Agent',       'asraa-crm' ),
			'add_new_item'       => __( 'Add New Agent',       'asraa-crm' ),
			'edit_item'          => __( 'Edit Agent',          'asraa-crm' ),
			'new_item'           => __( 'New Agent',           'asraa-crm' ),
			'view_item'          => __( 'View Agent',          'asraa-crm' ),
			'view_items'         => __( 'View Agents',         'asraa-crm' ),
			'search_items'       => __( 'Search Agents',       'asraa-crm' ),
			'not_found'          => __( 'No agents found.',    'asraa-crm' ),
			'not_found_in_trash' => __( 'No agents found in Trash.', 'asraa-crm' ),
			'all_items'          => __( 'All Agents',          'asraa-crm' ),
			'archives'           => __( 'Agent Archives',      'asraa-crm' ),
			'menu_name'          => __( 'Agents',              'asraa-crm' ),
		];

		register_post_type( self::POST_TYPE, [
			'labels'             => $labels,
			'public'             => true,
			'has_archive'        => true,
			'rewrite'            => [ 'slug' => 'agents', 'with_front' => false ],
			'menu_icon'          => 'dashicons-id-alt',
			'menu_position'      => 26,
			'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ],
			'show_in_rest'       => true,
			'taxonomies'         => [ self::TAXONOMY ],
			'capability_type'    => 'post',
		] );
	}

	public function register_taxonomy() {
		$labels = [
			'name'              => _x( 'Specialties', 'taxonomy general name', 'asraa-crm' ),
			'singular_name'     => _x( 'Specialty',   'taxonomy singular name', 'asraa-crm' ),
			'search_items'      => __( 'Search Specialties', 'asraa-crm' ),
			'all_items'         => __( 'All Specialties',    'asraa-crm' ),
			'parent_item'       => __( 'Parent Specialty',   'asraa-crm' ),
			'parent_item_colon' => __( 'Parent Specialty:',  'asraa-crm' ),
			'edit_item'         => __( 'Edit Specialty',     'asraa-crm' ),
			'update_item'       => __( 'Update Specialty',   'asraa-crm' ),
			'add_new_item'      => __( 'Add New Specialty',  'asraa-crm' ),
			'new_item_name'     => __( 'New Specialty Name', 'asraa-crm' ),
			'menu_name'         => __( 'Specialties',        'asraa-crm' ),
		];

		register_taxonomy( self::TAXONOMY, [ self::POST_TYPE ], [
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'agent-specialty' ],
		] );
	}

	/* ----------------------------------------------------------------
	   Meta boxes
	---------------------------------------------------------------- */

	public function add_meta_boxes() {
		add_meta_box(
			'asraa_agent_details',
			__( 'Agent Details', 'asraa-crm' ),
			[ $this, 'render_meta_box' ],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$contact_fields = [
			'agent_position', 'agent_phone', 'agent_email',
			'agent_whatsapp', 'agent_license', 'agent_languages',
			'agent_specialization', 'agent_experience',
		];
		$social_fields = [
			'agent_facebook', 'agent_instagram',
			'agent_linkedin', 'agent_twitter',
		];

		echo '<div class="asraa-agent-metabox">';

		echo '<h4 class="asraa-agent-metabox__section-title">' . esc_html__( 'Contact & Professional Info', 'asraa-crm' ) . '</h4>';
		echo '<div class="asraa-agent-metabox__grid">';
		foreach ( $contact_fields as $key ) {
			$cfg   = self::FIELDS[ $key ];
			$value = get_post_meta( $post->ID, $key, true );
			echo '<div class="asraa-agent-metabox__field">';
			echo '<label for="' . esc_attr( $key ) . '">' . esc_html( $cfg['label'] ) . '</label>';
			echo '<input type="' . esc_attr( $cfg['type'] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
			echo '</div>';
		}
		echo '</div>';

		echo '<h4 class="asraa-agent-metabox__section-title">' . esc_html__( 'Social Links', 'asraa-crm' ) . '</h4>';
		echo '<div class="asraa-agent-metabox__grid">';
		foreach ( $social_fields as $key ) {
			$cfg   = self::FIELDS[ $key ];
			$value = get_post_meta( $post->ID, $key, true );
			echo '<div class="asraa-agent-metabox__field">';
			echo '<label for="' . esc_attr( $key ) . '">' . esc_html( $cfg['label'] ) . '</label>';
			echo '<input type="' . esc_attr( $cfg['type'] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" placeholder="https://">';
			echo '</div>';
		}
		echo '</div>';

		echo '</div>';
	}

	public function save_meta( $post_id ) {
		// Nonce verification
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		// Nonce verification (wp_unslash only – sanitize_text_field must not
		// alter the nonce value before it is verified).
		if ( ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_FIELD ] ), self::NONCE_ACTION ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}

		// Skip auto-saves and revisions
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( self::FIELDS as $key => $cfg ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				delete_post_meta( $post_id, $key );
				continue;
			}
			$raw   = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean = $this->sanitize_field( $cfg['sanitize'], $raw );
			update_post_meta( $post_id, $key, $clean );
		}
	}

	/**
	 * Sanitize a field value using an explicit allowlist of safe functions.
	 * This prevents arbitrary function execution if the FIELDS config is ever
	 * modified unexpectedly.
	 *
	 * @param string $sanitizer Sanitizer key from self::FIELDS.
	 * @param mixed  $value     Raw value to sanitize.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_field( $sanitizer, $value ) {
		$allowed = [
			'sanitize_text_field' => 'sanitize_text_field',
			'sanitize_email'      => 'sanitize_email',
			'esc_url_raw'         => 'esc_url_raw',
			'absint'              => 'absint',
		];
		if ( isset( $allowed[ $sanitizer ] ) ) {
			return $allowed[ $sanitizer ]( $value );
		}
		// Fallback: plain text sanitization.
		return sanitize_text_field( $value );
	}

	/* ----------------------------------------------------------------
	   Admin columns
	---------------------------------------------------------------- */

	public function admin_columns( $columns ) {
		$new = [];
		foreach ( $columns as $key => $label ) {
			if ( $key === 'title' ) {
				$new['thumbnail'] = __( 'Photo', 'asraa-crm' );
			}
			$new[ $key ] = $label;
		}
		$new['agent_position'] = __( 'Position', 'asraa-crm' );
		$new['agent_phone']    = __( 'Phone', 'asraa-crm' );
		$new['agent_email']    = __( 'Email', 'asraa-crm' );
		$new['agent_license']  = __( 'License No.', 'asraa-crm' );

		// Remove 'date' and move it to end
		unset( $new['date'] );
		$new['date'] = __( 'Date', 'asraa-crm' );

		return $new;
	}

	public function admin_column_values( $column, $post_id ) {
		switch ( $column ) {
			case 'thumbnail':
				if ( has_post_thumbnail( $post_id ) ) {
					echo '<a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">';
					echo get_the_post_thumbnail( $post_id, [ 60, 60 ], [ 'style' => 'border-radius:50%;object-fit:cover;width:60px;height:60px;' ] );
					echo '</a>';
				} else {
					echo '<span class="asraa-agent-no-photo" title="' . esc_attr__( 'No photo set', 'asraa-crm' ) . '">&#128100;</span>';
				}
				break;

			case 'agent_position':
				$val = get_post_meta( $post_id, 'agent_position', true );
				echo $val ? '<span class="asraa-agent-badge">' . esc_html( $val ) . '</span>' : '<span class="asraa-agent-empty">—</span>';
				break;

			case 'agent_phone':
				$val = get_post_meta( $post_id, 'agent_phone', true );
				if ( $val ) {
					echo '<a href="tel:' . esc_attr( $val ) . '">' . esc_html( $val ) . '</a>';
				} else {
					echo '<span class="asraa-agent-empty">—</span>';
				}
				break;

			case 'agent_email':
				$val = get_post_meta( $post_id, 'agent_email', true );
				if ( $val ) {
					echo '<a href="mailto:' . esc_attr( $val ) . '">' . esc_html( $val ) . '</a>';
				} else {
					echo '<span class="asraa-agent-empty">—</span>';
				}
				break;

			case 'agent_license':
				$val = get_post_meta( $post_id, 'agent_license', true );
				echo $val ? esc_html( $val ) : '<span class="asraa-agent-empty">—</span>';
				break;
		}
	}

	public function sortable_columns( $columns ) {
		$columns['agent_position'] = 'agent_position';
		return $columns;
	}

	public function orderby_handler( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'agent_position' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', 'agent_position' );
			$query->set( 'orderby',  'meta_value' );
		}
	}

	/* ----------------------------------------------------------------
	   Admin assets
	---------------------------------------------------------------- */

	public function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== self::POST_TYPE ) {
			return;
		}
		wp_enqueue_style(
			'asraa-agent-admin',
			ASRAA_CRM_URL . 'assets/css/agent-admin.css',
			[],
			ASRAA_CRM_VERSION
		);
	}
}

new Asraa_Agent_Post_Type();
