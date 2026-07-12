<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Inventory Automation Service
 *
 * Wires WordPress action hooks to inventory lifecycle events and
 * executes the corresponding automation rules.
 *
 * Rules:
 *  1. After site visit completed
 *     - Create 24-hour follow-up for the sales agent.
 *     - Fetch WhatsApp template by tag = 'post_visit_brochure' and trigger send.
 *     - Notify the assigned sales agent.
 *
 *  2. If visit outcome = 'token_expected'
 *     - Set unit status = 'blocked'.
 *
 *  3. If booking confirmed
 *     - Set unit status = 'sold'.
 *
 *  4. If booking cancelled
 *     - Reopen unit (status = 'available').
 *     - Find leads interested in same configuration and fire 'lead_reopened' trigger.
 */
class Asraa_CRM_Inventory_Automation_Service {

	private $unit_repo;
	private $visit_repo;
	private $wa_repo;
	private $activity_repo;
	private $followup_table;

	public function __construct() {
		$this->unit_repo     = new Asraa_CRM_Unit_Repository();
		$this->visit_repo    = new Asraa_CRM_Site_Visit_Repository();
		$this->wa_repo       = new Asraa_Whatsapp_Template_Repository();
		$this->activity_repo = new Asraa_CRM_Lead_Activity_Repository();

		global $wpdb;
		$this->followup_table = $wpdb->prefix . 'asraa_crm_followups';

		$this->register_hooks();
	}

	// ── Hook registration ─────────────────────────────────────────────────────

	private function register_hooks() {
		add_action( 'asraa_crm_site_visit_completed', [ $this, 'on_visit_completed' ] );
		add_action( 'asraa_crm_booking_confirmed',    [ $this, 'on_booking_confirmed' ] );
		add_action( 'asraa_crm_booking_cancelled',    [ $this, 'on_booking_cancelled' ] );
	}

	// ── Rule 1 – Visit completed ──────────────────────────────────────────────

	/**
	 * @param array $context { visit_id, data: { lead_id, unit_id, sales_agent, visit_outcome, … } }
	 */
	public function on_visit_completed( array $context ) {
		$data        = $context['data'] ?? [];
		$lead_id     = (int) ( $data['lead_id'] ?? 0 );
		$unit_id     = (int) ( $data['unit_id'] ?? 0 );
		$agent_id    = (int) ( $data['sales_agent'] ?? 0 );
		$outcome     = $data['visit_outcome'] ?? '';

		if ( ! $lead_id ) {
			return;
		}

		// 1a – Create 24h follow-up.
		$this->create_followup( $lead_id, $agent_id, 1, 'Post-visit follow-up (auto)' );
		asraa_crm_debug_log( 'Asraa CRM: 24h follow-up created for lead_id=' . $lead_id );

		// 1b – Send brochure via WhatsApp (template lookup by tag).
		$template = $this->wa_repo->get_by_tag( 'post_visit_brochure' );
		if ( $template ) {
			do_action( 'asraa_crm_automation_whatsapp', $template, [
				'lead_id' => $lead_id,
				'context' => 'post_visit',
			] );
			asraa_crm_debug_log( 'Asraa CRM: WhatsApp brochure dispatched for lead_id=' . $lead_id );
		} else {
			asraa_crm_debug_log( 'Asraa CRM: No WhatsApp template with tag=post_visit_brochure found.' );
		}

		// 1c – Notify the assigned sales agent.
		if ( $agent_id ) {
			do_action( 'asraa_crm_notify_agent', $agent_id, [
				'type'    => 'visit_completed',
				'message' => 'A site visit has been completed. Please follow up with the client.',
				'lead_id' => $lead_id,
			] );
		}

		// Rule 2 – Token expected → block unit.
		if ( $outcome === 'token_expected' && $unit_id ) {
			$this->unit_repo->update( $unit_id, [ 'status' => 'blocked' ] );
			$this->activity_repo->log_activity(
				$lead_id,
				'unit_blocked',
				sprintf( 'Unit #%d blocked — token expected.', $unit_id )
			);
			asraa_crm_fire_trigger( 'unit_blocked', [
				'unit_id' => $unit_id,
				'lead_id' => $lead_id,
			] );
			asraa_crm_debug_log( 'Asraa CRM: unit #' . $unit_id . ' blocked (token_expected)' );
		}
	}

	// ── Rule 3 – Booking confirmed ────────────────────────────────────────────

	/**
	 * @param array $context { lead_id, unit_id }
	 */
	public function on_booking_confirmed( array $context ) {
		$lead_id = (int) ( $context['lead_id'] ?? 0 );
		$unit_id = (int) ( $context['unit_id'] ?? 0 );

		if ( $unit_id ) {
			$this->unit_repo->update( $unit_id, [ 'status' => 'sold' ] );
			asraa_crm_debug_log( 'Asraa CRM: unit #' . $unit_id . ' marked as sold' );
		}

		if ( $lead_id ) {
			$this->activity_repo->log_activity(
				$lead_id,
				'booking_confirmed',
				sprintf( 'Booking confirmed for unit #%d.', $unit_id )
			);
		}
	}

	// ── Rule 4 – Booking cancelled ────────────────────────────────────────────

	/**
	 * @param array $context { lead_id, unit_id, configuration }
	 */
	public function on_booking_cancelled( array $context ) {
		$lead_id       = (int) ( $context['lead_id'] ?? 0 );
		$unit_id       = (int) ( $context['unit_id'] ?? 0 );
		$configuration = sanitize_text_field( $context['configuration'] ?? '' );

		// Reopen the unit.
		if ( $unit_id ) {
			$this->unit_repo->update( $unit_id, [ 'status' => 'available' ] );
			asraa_crm_debug_log( 'Asraa CRM: unit #' . $unit_id . ' reopened after cancellation' );
		}

		if ( $lead_id ) {
			$this->activity_repo->log_activity(
				$lead_id,
				'cancellation_reopened',
				sprintf( 'Booking cancelled — unit #%d reopened.', $unit_id )
			);
		}

		// Notify similar leads interested in the same configuration.
		if ( $configuration ) {
			$map_repo    = new Asraa_CRM_Lead_Unit_Map_Repository();
			$similar     = $map_repo->get_leads_by_configuration( $configuration, $lead_id );
			foreach ( $similar as $row ) {
				do_action( 'asraa_crm_lead_reopened', (int) $row['lead_id'], [
					'unit_id'       => $unit_id,
					'configuration' => $configuration,
				] );
				asraa_crm_debug_log( 'Asraa CRM: reopened lead_id=' . $row['lead_id'] . ' config=' . $configuration );
			}
		}
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Insert a follow-up record $days_from_now days from today.
	 *
	 * @param int    $lead_id
	 * @param int    $agent_id
	 * @param int    $days_from_now
	 * @param string $note
	 */
	private function create_followup( $lead_id, $agent_id, $days_from_now = 1, $note = 'Auto follow-up' ) {
		global $wpdb;
		$wpdb->insert( $this->followup_table, [
			'lead_id'     => (int) $lead_id,
			'agent_id'    => $agent_id ?: get_current_user_id(),
			'follow_date' => wp_date( 'Y-m-d', strtotime( "+{$days_from_now} days" ) ),
			'note'        => sanitize_text_field( $note ),
			'is_done'     => 0,
			'created_at'  => current_time( 'mysql' ),
		] );
	}
}
