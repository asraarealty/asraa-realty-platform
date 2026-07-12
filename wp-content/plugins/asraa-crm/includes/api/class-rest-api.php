<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Asraa_CRM_REST_API {

	const API_NAMESPACE = 'asraa/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {

		/* ========================= LEADS ========================= */

		register_rest_route( self::API_NAMESPACE, '/leads', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_leads' ],
				'permission_callback' => [ $this, 'require_manage_leads' ],
			],
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'create_lead' ],
				'permission_callback' => [ $this, 'require_manage_leads' ],
				'args' => $this->lead_schema(),
			],
		] );

		register_rest_route( self::API_NAMESPACE, '/leads/(?P<id>\d+)', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_lead' ],
				'permission_callback' => [ $this, 'require_manage_leads' ],
			],
			[
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => [ $this, 'update_lead' ],
				'permission_callback' => [ $this, 'require_manage_leads' ],
				'args' => $this->lead_schema(false),
			],
			[
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => [ $this, 'delete_lead' ],
				'permission_callback' => [ $this, 'require_manage_crm' ],
			],
		] );

		/* ========================= CRM PROPERTIES ========================= */

		register_rest_route( self::API_NAMESPACE, '/properties', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_properties' ],
				'permission_callback' => [ $this, 'require_auth' ],
			],
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'create_property' ],
				'permission_callback' => [ $this, 'require_manage_crm' ],
				'args' => $this->property_schema(),
			],
		] );

		register_rest_route( self::API_NAMESPACE, '/properties/(?P<id>\d+)', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_property' ],
				'permission_callback' => [ $this, 'require_auth' ],
			],
			[
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => [ $this, 'update_property' ],
				'permission_callback' => [ $this, 'require_manage_crm' ],
				'args' => $this->property_schema(false),
			],
			[
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => [ $this, 'delete_property' ],
				'permission_callback' => [ $this, 'require_manage_crm' ],
			],
		] );

		/* ========================= PUBLIC BROKER INVENTORY ========================= */

		register_rest_route( self::API_NAMESPACE, '/broker-properties', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_public_broker_properties' ],
				'permission_callback' => '__return_true',
			],
		] );
	}

	/* ========================= LEADS ========================= */

	public function get_leads() {
		$repo = new Asraa_CRM_Lead_Repository();
		return rest_ensure_response( $repo->get_all() );
	}

	public function get_lead( WP_REST_Request $request ) {
		$repo = new Asraa_CRM_Lead_Repository();
		return rest_ensure_response(
			$repo->get_by_id( (int) $request['id'] )
		);
	}

	public function create_lead( WP_REST_Request $request ) {
		$repo = new Asraa_CRM_Lead_Repository();

		$data = $this->sanitize_lead_data( $request );

		$data['lead_score'] = asraa_crm_calculate_ai_lead_score( $data );

		$id = $repo->create(
			array_merge(
				$data,
				[
					'created_at' => current_time('mysql'),
					'last_activity' => current_time('mysql')
				]
			)
		);

		return rest_ensure_response([ 'id' => $id ]);
	}

	public function update_lead( WP_REST_Request $request ) {
		$repo = new Asraa_CRM_Lead_Repository();

		$repo->update(
			(int) $request['id'],
			$this->sanitize_lead_data( $request )
		);

		return rest_ensure_response([ 'updated' => true ]);
	}

	public function delete_lead( WP_REST_Request $request ) {
		$repo = new Asraa_CRM_Lead_Repository();
		$repo->soft_delete( (int) $request['id'] );

		return rest_ensure_response([ 'deleted' => true ]);
	}

	/* ========================= PROPERTIES ========================= */

	public function get_properties() {
		$repo = new Asraa_Property_Repository();
		return rest_ensure_response( $repo->get_all() );
	}

	public function get_property( WP_REST_Request $request ) {
		$repo = new Asraa_Property_Repository();
		return rest_ensure_response(
			$repo->get_by_id( (int) $request['id'] )
		);
	}

	public function create_property( WP_REST_Request $request ) {
		$repo = new Asraa_Property_Repository();

		$id = $repo->create(
			$this->sanitize_property_data( $request )
		);

		return rest_ensure_response([ 'id' => $id ]);
	}

	public function update_property( WP_REST_Request $request ) {
		$repo = new Asraa_Property_Repository();

		$repo->update(
			(int) $request['id'],
			$this->sanitize_property_data( $request )
		);

		return rest_ensure_response([ 'updated' => true ]);
	}

	public function delete_property( WP_REST_Request $request ) {
		$repo = new Asraa_Property_Repository();
		$repo->delete( (int) $request['id'] );

		return rest_ensure_response([ 'deleted' => true ]);
	}

	/* ========================= PUBLIC BROKER API ========================= */

	public function get_public_broker_properties() {
		$repo = new Asraa_Property_Repository();

		$properties = $repo->get_all();

		return rest_ensure_response( $properties );
	}

	/* ========================= PERMISSIONS ========================= */

	public function require_auth() {
		return is_user_logged_in();
	}

	public function require_manage_leads() {
		return current_user_can('asraa_manage_leads') || current_user_can('manage_options');
	}

	public function require_manage_crm() {
		return current_user_can('asraa_manage_crm') || current_user_can('manage_options');
	}

	/* ========================= SANITIZE ========================= */

	private function sanitize_lead_data( WP_REST_Request $request ) {
		return array_filter([
			'name' => sanitize_text_field( $request->get_param('name') ),
			'email' => sanitize_email( $request->get_param('email') ),
			'phone' => sanitize_text_field( $request->get_param('phone') ),
			'intent' => sanitize_text_field( $request->get_param('intent') ),
			'location' => sanitize_text_field( $request->get_param('location') ),
			'budget' => asraa_crm_parse_budget_value( $request->get_param('budget') ),
			'lead_stage' => sanitize_text_field( $request->get_param('lead_stage') ),
			'property_type' => sanitize_text_field( $request->get_param('property_type') ),
			'source' => sanitize_text_field( $request->get_param('source') ),
		]);
	}

	private function sanitize_property_data( WP_REST_Request $request ) {
		return array_filter([
			'title' => sanitize_text_field( $request->get_param('title') ),
			'transaction_type' => sanitize_text_field( $request->get_param('transaction_type') ),
			'property_type' => sanitize_text_field( $request->get_param('property_type') ),
			'builder_name' => sanitize_text_field( $request->get_param('builder_name') ),
			'city' => sanitize_text_field( $request->get_param('city') ),
			'area' => sanitize_text_field( $request->get_param('area') ),
			'location' => sanitize_text_field( $request->get_param('location') ),
			'price' => (float) $request->get_param('price'),
			'status' => sanitize_text_field( $request->get_param('status') ),
			'image_url' => esc_url_raw( $request->get_param('image_url') ),
		]);
	}

	/* ========================= SCHEMAS ========================= */

	private function lead_schema( $required = true ) {
		return [];
	}

	private function property_schema( $required = true ) {
		return [];
	}
}