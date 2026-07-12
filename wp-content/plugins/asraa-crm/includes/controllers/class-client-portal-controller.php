<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Client Portal Controller (legacy redirect layer)
 *
 * The client-facing dashboard is now served entirely by the Homeo theme's
 * native /dashboard/ page.  The [asraa_dashboard] shortcode (class-frontend-
 * dashboard.php) injects the "My Property Journey" CRM section into that page
 * via the homeo_after_dashboard_content hook.
 *
 * This controller now:
 *   • Registers stub shortcodes for the old portal codes so any page that
 *     still contains [asraa_client_login] / [asraa_client_dashboard] (and the
 *     apas_ aliases) redirects the visitor to /dashboard/ instead of erroring.
 *   • Issues 301 server-side redirects for the /client-dashboard/ and
 *     /client-login/ page slugs.
 *   • Keeps the reschedule-visit AJAX handler which is still used by the
 *     site-visit detail panel inside the [asraa_dashboard] shortcode.
 */
class Asraa_CRM_Client_Portal_Controller {

	public function __construct() {
		// ── Old portal shortcodes disabled: use /dashboard/ (Homeo) instead. ─
		// The canonical client journey is now injected into the Homeo dashboard
		// via the [asraa_dashboard] shortcode and the homeo_after_dashboard_content
		// hook in homeo-child/functions.php.  The shortcodes below are kept as
		// no-op stubs so existing page content does not throw PHP errors, but
		// they output nothing and redirect to /dashboard/.

		add_shortcode( 'asraa_client_login',     [ $this, 'redirect_to_dashboard' ] );
		add_shortcode( 'asraa_client_dashboard', [ $this, 'redirect_to_dashboard' ] );
		add_shortcode( 'apas_client_login',      [ $this, 'redirect_to_dashboard' ] );
		add_shortcode( 'apas_client_dashboard',  [ $this, 'redirect_to_dashboard' ] );

		// Redirect legacy portal URLs to the canonical /dashboard/ page.
		add_action( 'template_redirect', [ $this, 'redirect_legacy_urls' ] );

		add_action( 'wp_ajax_nopriv_asraa_client_login_submit', [ $this, 'ajax_login' ] );
		add_action( 'wp_ajax_asraa_client_login_submit',        [ $this, 'ajax_login' ] );
		add_action( 'wp_ajax_asraa_reschedule_visit',           [ $this, 'ajax_reschedule_visit' ] );
	}

	// ── Stub shortcode: redirect to /dashboard/ ───────────────────────────────

	/**
	 * Silently redirect the browser to the canonical /dashboard/ page.
	 * Used as the callback for all deprecated portal shortcodes.
	 *
	 * @return string  Empty string (shortcodes must return, not echo).
	 */
	public function redirect_to_dashboard( $atts = [] ) {
		$dashboard_url = esc_url( home_url( '/dashboard/' ) );
		// JS redirect for browsers with scripting enabled; noscript meta-refresh fallback.
		return '<script>window.location.replace("' . $dashboard_url . '");</script>'
			. '<noscript><meta http-equiv="refresh" content="0;url=' . $dashboard_url . '"></noscript>';
	}

	// ── Template redirect for legacy portal page slugs ────────────────────────

	/**
	 * Server-side redirect for /client-dashboard/ and /client-login/ slugs.
	 * Fires on template_redirect so the response is a proper 301.
	 */
	public function redirect_legacy_urls() {
		if ( ! is_page() ) {
			return;
		}
		global $post;
		if ( ! $post ) {
			return;
		}
		$slug = $post->post_name;
		if ( in_array( $slug, [ 'client-dashboard', 'client-login', 'apas-client-dashboard', 'apas-client-login' ], true ) ) {
			wp_safe_redirect( home_url( '/dashboard/' ), 301 );
			exit;
		}
	}

	// ── Login AJAX handler ────────────────────────────────────────────────────

	public function ajax_login() {
		check_ajax_referer( 'asraa_crm_nonce', 'nonce' );

		$username = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
		$password = sanitize_text_field( wp_unslash( $_POST['password'] ?? '' ) );

		if ( empty( $username ) || empty( $password ) ) {
			wp_send_json_error( [ 'message' => 'Please enter your credentials.' ] );
		}

		$user = wp_authenticate( $username, $password );
		if ( is_wp_error( $user ) ) {
			error_log( 'Asraa CRM: client login failed for ' . $username );
			wp_send_json_error( [ 'message' => 'Invalid credentials. Please try again.' ] );
		}

		wp_set_auth_cookie( $user->ID, false );
		do_action( 'wp_login', $user->user_login, $user );

		wp_send_json_success( [ 'redirect' => home_url( '/dashboard/' ) ] );
	}

	// ── Reschedule visit AJAX ─────────────────────────────────────────────────

	public function ajax_reschedule_visit() {
		check_ajax_referer( 'asraa_crm_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'Not logged in.' ] );
		}

		$visit_id = (int) ( $_POST['visit_id'] ?? 0 );
		$new_date = sanitize_text_field( wp_unslash( $_POST['new_date'] ?? '' ) );

		if ( ! $visit_id || ! $new_date ) {
			wp_send_json_error( [ 'message' => 'Invalid request.' ] );
		}

		$visit_repo = new Asraa_CRM_Site_Visit_Repository();
		$visit      = $visit_repo->get_by_id( $visit_id );

		if ( ! $visit ) {
			wp_send_json_error( [ 'message' => 'Visit not found.' ] );
		}

		// Verify the current client owns this visit by checking their CRM lead.
		$user  = wp_get_current_user();
		$lead  = $this->resolve_lead_by_user( $user );
		if ( $lead && (int) $visit['lead_id'] !== (int) $lead['id'] ) {
			wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
		}

		$visit_repo->update( $visit_id, [ 'visit_date' => $new_date ] );
		wp_send_json_success( [ 'message' => 'Visit rescheduled.' ] );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Resolve the CRM lead for a WP user by user_id or email.
	 * Used only by the reschedule-visit AJAX handler.
	 *
	 * @param WP_User $user
	 * @return array|null
	 */
	private function resolve_lead_by_user( WP_User $user ) {
		global $wpdb;
		$table = $wpdb->prefix . 'asraa_crm_leads';

		// Try direct user_id match first (v4.2.0+).
		$lead = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND is_deleted = 0 LIMIT 1",
				$user->ID
			),
			ARRAY_A
		);
		if ( $lead ) {
			return $lead;
		}

		// Fall back to email match.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE email = %s AND is_deleted = 0 LIMIT 1",
				$user->user_email
			),
			ARRAY_A
		) ?: null;
	}
}
