<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Custom Roles & Capabilities
 *
 * Roles:
 * asraa_admin
 * asraa_agent
 * asraa_client
 */

class Asraa_CRM_Roles {

	/* ============================================================
	   ADMIN CAPABILITIES
	============================================================ */
	const ADMIN_CAPS = [
		'asraa_manage_crm'            => true,
		'asraa_manage_leads'          => true,
		'asraa_manage_properties'     => true,
		'asraa_manage_campaigns'      => true,
		'asraa_manage_deals'          => true,
		'asraa_manage_commissions'    => true,
		'asraa_manage_automation'     => true,
		'asraa_manage_agent_posts'    => true,
		'asraa_invite_clients'        => true,
		'asraa_assign_clients'        => true,
		'asraa_view_dashboard'        => true,
		'asraa_view_reports'          => true,
		'asraa_client_access'         => true,
		'read'                        => true,
	];

	/* ============================================================
	   AGENT CAPABILITIES
	============================================================ */
	const AGENT_CAPS = [
		'asraa_manage_leads'          => true,
		'asraa_manage_properties'     => true,
		'asraa_manage_agent_posts'    => true,
		'asraa_invite_clients'        => true,
		'asraa_client_access'         => true,
		'asraa_view_dashboard'        => true,
		'read'                        => true,
	];

	/* ============================================================
	   CLIENT CAPABILITIES
	============================================================ */
	const CLIENT_CAPS = [
		'asraa_client_access'         => true,
		'read'                        => true,
	];

	/* ============================================================
	   REGISTER ROLES
	============================================================ */
	public static function register() {

		self::remove_roles();

		add_role(
			'asraa_admin',
			__( 'Asraa Admin', 'asraa-crm' ),
			self::ADMIN_CAPS
		);

		add_role(
			'asraa_agent',
			__( 'Asraa Agent', 'asraa-crm' ),
			self::AGENT_CAPS
		);

		add_role(
			'asraa_client',
			__( 'Asraa Client', 'asraa-crm' ),
			self::CLIENT_CAPS
		);

		/*
		|------------------------------------------------------------
		| Give all CRM caps to WordPress Administrator
		|------------------------------------------------------------
		*/
		$admin = get_role( 'administrator' );

		if ( $admin ) {
			foreach ( self::ADMIN_CAPS as $cap => $grant ) {
				$admin->add_cap( $cap, $grant );
			}
		}
	}

	/* ============================================================
	   REMOVE ROLES
	============================================================ */
	public static function remove() {

		self::remove_roles();

		$admin = get_role( 'administrator' );

		if ( $admin ) {
			foreach ( array_keys( self::ADMIN_CAPS ) as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}

	/* ============================================================
	   PRIVATE HELPERS
	============================================================ */
	private static function remove_roles() {
		$roles = [
			'asraa_admin',
			'asraa_agent',
			'asraa_client'
		];

		foreach ( $roles as $role ) {
			remove_role( $role );
		}
	}
}