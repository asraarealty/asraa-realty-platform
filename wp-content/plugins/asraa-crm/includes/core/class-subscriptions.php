<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Subscription Feature Flags
 *
 * Provides a lightweight feature-gate system to prepare the plugin for
 * SaaS monetisation.  Each site's active plan is stored in a WordPress
 * option (`asraa_crm_plan`).  Features can be toggled per-plan so that
 * functionality is restricted based on subscription tier.
 *
 * Plans (expandable):
 *   free    – basic lead & property management
 *   basic   – + campaigns, follow-ups
 *   premium – + automation, commission tracking, REST API, reports
 *
 * Usage:
 *   if ( Asraa_CRM_Subscriptions::can( 'automation' ) ) { … }
 */
class Asraa_CRM_Subscriptions {

	/** Option key that stores the active plan slug. */
	const OPTION_KEY = 'asraa_crm_plan';

	/** Default plan for new installations. */
	const DEFAULT_PLAN = 'free';

	/**
	 * Feature matrix: plan → feature slugs available on that plan and above.
	 *
	 * Each entry lists the *minimum* plan required to access that feature.
	 * Plans are ranked: free < basic < premium.
	 */
	const FEATURE_PLANS = [
		'leads'           => 'free',
		'properties'      => 'free',
		'notes'           => 'free',
		'followups'       => 'basic',
		'campaigns'       => 'basic',
		'groups'          => 'basic',
		'deals'           => 'basic',
		'activities'      => 'basic',
		'api'             => 'basic',
		'automation'      => 'premium',
		'commissions'     => 'premium',
		'agent_hierarchy' => 'premium',
		'reports'         => 'premium',
	];

	/** Numeric rank for plan comparison. Higher = more features. */
	const PLAN_RANK = [
		'free'    => 0,
		'basic'   => 1,
		'premium' => 2,
	];

	// ── public API ───────────────────────────────────────────────────────────

	/**
	 * Check whether the active plan includes a given feature.
	 *
	 * @param string $feature  Feature slug (see FEATURE_PLANS).
	 * @return bool
	 */
	public static function can( $feature ) {
		/**
		 * Filters whether a feature is enabled.
		 *
		 * Returning a boolean short-circuits the default plan-based check,
		 * allowing third-party integrations (e.g. a licence server) to
		 * override access decisions.
		 *
		 * @param bool|null $override  Return true/false to override, null to use default.
		 * @param string    $feature   Feature slug.
		 * @param string    $plan      Current plan slug.
		 */
		$override = apply_filters( 'asraa_crm_feature_override', null, $feature, self::get_plan() );
		if ( $override !== null ) {
			return (bool) $override;
		}

		$required_plan = self::FEATURE_PLANS[ $feature ] ?? 'premium';
		return self::plan_rank() >= ( self::PLAN_RANK[ $required_plan ] ?? 99 );
	}

	/**
	 * Get the current active plan slug.
	 *
	 * @return string
	 */
	public static function get_plan() {
		return sanitize_key( get_option( self::OPTION_KEY, self::DEFAULT_PLAN ) );
	}

	/**
	 * Persist a plan change.
	 *
	 * @param string $plan  One of the keys from PLAN_RANK.
	 * @return bool
	 */
	public static function set_plan( $plan ) {
		$plan = sanitize_key( $plan );
		if ( ! array_key_exists( $plan, self::PLAN_RANK ) ) {
			return false;
		}
		return update_option( self::OPTION_KEY, $plan, false );
	}

	/**
	 * Return a list of all features available on the active plan.
	 *
	 * @return string[]
	 */
	public static function available_features() {
		return array_keys( array_filter(
			self::FEATURE_PLANS,
			static function ( $required ) {
				return self::plan_rank() >= ( self::PLAN_RANK[ $required ] ?? 99 );
			}
		) );
	}

	// ── private helpers ──────────────────────────────────────────────────────

	private static function plan_rank() {
		return self::PLAN_RANK[ self::get_plan() ] ?? 0;
	}
}
