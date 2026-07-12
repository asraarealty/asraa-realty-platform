<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Frontend Client Dashboard
 *
 * Renders the [asraa_dashboard] shortcode.
 *
 * Features:
 *   • Client login gate (redirects to wp-login when not logged in)
 *   • KPI summary cards (properties linked to the client, pending follow-ups)
 *   • Property portfolio with status badge
 *   • Basic ROI summary (deal value vs purchase price)
 *   • Activity timeline (calls, meetings, notes)
 *   • Notifications (upcoming follow-ups, recent activities)
 *
 * The "client lead" is resolved by matching the logged-in user's e-mail
 * against the CRM leads table.  If a lead is found, all linked data is
 * scoped to that lead.  Admins and agents see an aggregate view.
 */
class Asraa_Frontend_Dashboard {

	public function __construct() {
		add_shortcode( 'asraa_dashboard', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	// ── Asset enqueue ─────────────────────────────────────────────────────────

	public function enqueue_assets() {
		// Only enqueue when the shortcode is likely present on this page.
		if ( ! $this->page_has_shortcode() ) {
			return;
		}
		wp_enqueue_style(
			'asraa-dashboard-css',
			ASRAA_CRM_URL . 'public/css/dashboard.css',
			[],
			ASRAA_CRM_VERSION
		);
	}

	// ── Shortcode callback ────────────────────────────────────────────────────

	/**
	 * Main render callback for [asraa_dashboard].
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Inner content (unused).
	 * @return string         HTML output.
	 */
	public function render( $atts = [], $content = '' ) {
		$atts = shortcode_atts( [
			'title'        => __( 'My Property Dashboard', 'asraa-crm' ),
			'redirect_url' => wp_login_url( get_permalink() ),
		], $atts, 'asraa_dashboard' );

		// ── Login gate ────────────────────────────────────────────────────────
		if ( ! is_user_logged_in() ) {
			return $this->render_login_panel( $atts );
		}

		// ── Resolve client context ────────────────────────────────────────────
		$user    = wp_get_current_user();
		$lead    = $this->resolve_client_lead( $user );
		$lead_id = $lead ? (int) $lead['id'] : 0;

		// ── Gather data ───────────────────────────────────────────────────────
		$properties  = $this->get_client_properties( $lead_id, $user );
		$activities  = $this->get_client_activities( $lead_id );
		$followups   = $this->get_upcoming_followups( $lead_id, $user );
		$roi_rows    = $this->compute_roi( $properties, $lead_id );
		$plan        = Asraa_CRM_Subscriptions::get_plan();

		// ── Build output ──────────────────────────────────────────────────────
		ob_start();
		$this->render_dashboard(
			$atts,
			$user,
			$lead,
			$properties,
			$activities,
			$followups,
			$roi_rows,
			$plan
		);
		return ob_get_clean();
	}

	// ── Sub-renderers ─────────────────────────────────────────────────────────

	private function render_login_panel( array $atts ) {
		ob_start();
		?>
		<div class="asraa-dashboard">
			<div class="asraa-dashboard__login">
				<h3><?php esc_html_e( 'Client Portal', 'asraa-crm' ); ?></h3>
				<p><?php esc_html_e( 'Please log in to view your property dashboard.', 'asraa-crm' ); ?></p>
				<a class="button" href="<?php echo esc_url( $atts['redirect_url'] ); ?>">
					<?php esc_html_e( 'Login', 'asraa-crm' ); ?>
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_dashboard(
		array $atts,
		WP_User $user,
		?array $lead,
		array $properties,
		array $activities,
		array $followups,
		array $roi_rows,
		string $plan
	) {
		?>
		<div class="asraa-dashboard">

			<!-- Header -->
			<div class="asraa-dashboard__header">
				<h2>
					<?php echo esc_html( $atts['title'] ); ?>
					<span style="font-size:14px;font-weight:400;color:#6b7280;">
						<?php
						printf(
							/* translators: %s: display name */
							esc_html__( '– %s', 'asraa-crm' ),
							esc_html( $user->display_name )
						);
						?>
					</span>
				</h2>
				<span class="asraa-dashboard__plan-badge">
					<?php echo esc_html( strtoupper( $plan ) ); ?>
				</span>
			</div>

			<!-- KPI stats -->
			<div class="asraa-dashboard__stats">
				<?php $this->render_stat( __( 'Properties', 'asraa-crm' ), count( $properties ), 'accent' ); ?>
				<?php $this->render_stat( __( 'Upcoming Follow-ups', 'asraa-crm' ), count( $followups ), '' ); ?>
				<?php $this->render_stat( __( 'Activities', 'asraa-crm' ), count( $activities ), '' ); ?>
				<?php
				$total_value = array_sum( array_column( $roi_rows, 'deal_value' ) );
				$this->render_stat( __( 'Deal Value', 'asraa-crm' ), '₹' . number_format( $total_value ), 'success' );
				?>
			</div>

			<!-- Property Portfolio -->
			<div class="asraa-dashboard__section">
				<div class="asraa-dashboard__section-header">🏢 <?php esc_html_e( 'Property Portfolio', 'asraa-crm' ); ?></div>
				<div class="asraa-dashboard__section-body">
					<?php if ( empty( $properties ) ) : ?>
						<div class="asraa-empty">
							<div class="asraa-empty__icon">🏠</div>
							<?php esc_html_e( 'No properties linked to your account yet.', 'asraa-crm' ); ?>
						</div>
					<?php else : ?>
						<div class="asraa-property-grid">
							<?php foreach ( $properties as $prop ) : ?>
								<?php $this->render_property_card( $prop ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- ROI Summary -->
			<?php if ( ! empty( $roi_rows ) ) : ?>
			<div class="asraa-dashboard__section">
				<div class="asraa-dashboard__section-header">📈 <?php esc_html_e( 'ROI Summary', 'asraa-crm' ); ?></div>
				<div class="asraa-dashboard__section-body">
					<table class="asraa-roi-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Property', 'asraa-crm' ); ?></th>
								<th><?php esc_html_e( 'Purchase Price', 'asraa-crm' ); ?></th>
								<th><?php esc_html_e( 'Deal Value', 'asraa-crm' ); ?></th>
								<th><?php esc_html_e( 'ROI', 'asraa-crm' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $roi_rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['title'] ); ?></td>
								<td class="neutral">₹<?php echo esc_html( number_format( $row['price'] ) ); ?></td>
								<td class="amount">₹<?php echo esc_html( number_format( $row['deal_value'] ) ); ?></td>
								<td class="amount">
									<?php
									$roi = $row['price'] > 0
										? round( ( ( $row['deal_value'] - $row['price'] ) / $row['price'] ) * 100, 1 )
										: 0;
									echo esc_html( $roi . '%' );
									?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php endif; ?>

			<!-- Activity Timeline -->
			<?php if ( Asraa_CRM_Subscriptions::can( 'activities' ) ) : ?>
			<div class="asraa-dashboard__section">
				<div class="asraa-dashboard__section-header">📋 <?php esc_html_e( 'Activity Timeline', 'asraa-crm' ); ?></div>
				<div class="asraa-dashboard__section-body">
					<?php if ( empty( $activities ) ) : ?>
						<div class="asraa-empty">
							<div class="asraa-empty__icon">📝</div>
							<?php esc_html_e( 'No activities recorded yet.', 'asraa-crm' ); ?>
						</div>
					<?php else : ?>
						<ul class="asraa-timeline">
							<?php foreach ( $activities as $act ) : ?>
								<?php $this->render_timeline_item( $act ); ?>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Notifications -->
			<?php if ( ! empty( $followups ) ) : ?>
			<div class="asraa-dashboard__section">
				<div class="asraa-dashboard__section-header">🔔 <?php esc_html_e( 'Upcoming Follow-ups', 'asraa-crm' ); ?></div>
				<div class="asraa-dashboard__section-body">
					<ul class="asraa-notifications">
						<?php foreach ( $followups as $fu ) : ?>
						<li class="asraa-notifications__item">
							<span class="asraa-notifications__icon">📅</span>
							<div class="asraa-notifications__text">
								<div><?php echo esc_html( $fu['note'] ?? __( 'Follow-up scheduled', 'asraa-crm' ) ); ?></div>
								<div class="asraa-notifications__time"><?php echo esc_html( $fu['follow_date'] ); ?></div>
							</div>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
			<?php endif; ?>

		</div><!-- .asraa-dashboard -->
		<?php
	}

	private function render_stat( $label, $value, $accent_class = '' ) {
		?>
		<div class="asraa-stat-card">
			<div class="asraa-stat-card__label"><?php echo esc_html( $label ); ?></div>
			<div class="asraa-stat-card__value <?php echo esc_attr( $accent_class ); ?>"><?php echo esc_html( $value ); ?></div>
		</div>
		<?php
	}

	private function render_property_card( array $prop ) {
		$status  = $prop['status'] ?? 'available';
		$price   = (float) ( $prop['price'] ?? 0 );
		$img_url = $prop['image_url'] ?? '';
		?>
		<div class="asraa-property-card">
			<?php if ( $img_url ) : ?>
				<img class="asraa-property-card__image"
				     src="<?php echo esc_url( $img_url ); ?>"
				     alt="<?php echo esc_attr( $prop['title'] ?? '' ); ?>">
			<?php else : ?>
				<div class="asraa-property-card__image placeholder">🏢</div>
			<?php endif; ?>
			<div class="asraa-property-card__body">
				<div class="asraa-property-card__title"><?php echo esc_html( $prop['title'] ?? '-' ); ?></div>
				<div class="asraa-property-card__meta">
					<?php echo esc_html( $prop['city'] ?? '' ); ?>
					<?php if ( ! empty( $prop['property_type'] ) ) : ?>
						· <?php echo esc_html( $prop['property_type'] ); ?>
					<?php endif; ?>
				</div>
				<div class="asraa-property-card__price">₹<?php echo esc_html( number_format( $price ) ); ?></div>
				<div style="margin-top:8px;">
					<span class="asraa-badge asraa-badge--<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( ucfirst( $status ) ); ?>
					</span>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_timeline_item( array $act ) {
		$type     = $act['type'] ?? 'note';
		$icons    = [
			'call'         => '📞',
			'meeting'      => '🤝',
			'note'         => '📝',
			'email'        => '📧',
			'whatsapp'     => '💬',
			'stage_change' => '🔄',
			'system'       => '⚙️',
		];
		$icon = $icons[ $type ] ?? '📌';
		?>
		<li class="asraa-timeline__item">
			<div class="asraa-timeline__dot type-<?php echo esc_attr( $type ); ?>"><?php echo $icon; // phpcs:ignore ?></div>
			<?php if ( ! empty( $act['subject'] ) ) : ?>
				<div class="asraa-timeline__subject"><?php echo esc_html( $act['subject'] ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $act['body'] ) ) : ?>
				<div class="asraa-timeline__body"><?php echo esc_html( $act['body'] ); ?></div>
			<?php endif; ?>
			<div class="asraa-timeline__meta"><?php echo esc_html( asraa_crm_format_date( $act['created_at'] ?? '' ) ); ?></div>
		</li>
		<?php
	}

	// ── Data helpers ──────────────────────────────────────────────────────────

	/**
	 * Find the CRM lead that corresponds to the current WP user.
	 * Matches on email address; admins/agents return null (no personal lead).
	 *
	 * @param WP_User $user
	 * @return array|null
	 */
	private function resolve_client_lead( WP_User $user ) {
		// Admins and agents see an aggregate view; no personal lead needed.
		if (
			current_user_can( 'asraa_manage_leads' ) ||
			current_user_can( 'manage_options' )
		) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'asraa_crm_leads';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE email = %s AND is_deleted = 0 LIMIT 1",
				$user->user_email
			),
			ARRAY_A
		) ?: null;
	}

	/**
	 * Return properties for the client.
	 * If a lead is found, returns properties linked via deals.
	 * Admins/agents see all available properties.
	 *
	 * @param int     $lead_id
	 * @param WP_User $user
	 * @return array[]
	 */
	private function get_client_properties( $lead_id, WP_User $user ) {
		$repo = new Asraa_Property_Repository();

		if ( ! $lead_id ) {
			// Admin/agent: return all.
			return $repo->get_all() ?: [];
		}

		// Client: return properties linked via deals table.
		global $wpdb;
		$deals_table      = $wpdb->prefix . 'asraa_crm_deals';
		$properties_table = $wpdb->prefix . 'asraa_crm_properties';

		$properties = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.* FROM {$properties_table} p
				 INNER JOIN {$deals_table} d ON d.property_id = p.id
				 WHERE d.lead_id = %d
				 ORDER BY p.id DESC",
				$lead_id
			),
			ARRAY_A
		) ?: [];

		// Fallback: if no deals yet, still show all available properties
		// so the portal is not empty on first visit.
		if ( empty( $properties ) ) {
			$all = $repo->get_all() ?: [];
			return array_slice( $all, 0, 6 );
		}

		return $properties;
	}

	/**
	 * Return activities for the client's lead.
	 *
	 * @param int $lead_id
	 * @return array[]
	 */
	private function get_client_activities( $lead_id ) {
		if ( ! Asraa_CRM_Subscriptions::can( 'activities' ) ) {
			return [];
		}
		if ( ! $lead_id ) {
			$repo = new Asraa_CRM_Activity_Repository();
			return $repo->get_recent( 10 );
		}
		$repo = new Asraa_CRM_Activity_Repository();
		return $repo->get_for_lead( $lead_id, 20 );
	}

	/**
	 * Return upcoming follow-ups.
	 *
	 * @param int     $lead_id
	 * @param WP_User $user
	 * @return array[]
	 */
	private function get_upcoming_followups( $lead_id, WP_User $user ) {
		if ( ! Asraa_CRM_Subscriptions::can( 'followups' ) ) {
			return [];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'asraa_crm_followups';

		if ( $lead_id ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					 WHERE lead_id = %d AND is_done = 0 AND follow_date >= CURDATE()
					 ORDER BY follow_date ASC LIMIT 5",
					$lead_id
				),
				ARRAY_A
			) ?: [];
		}

		// Agent or admin: show their own upcoming follow-ups.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE agent_id = %d AND is_done = 0 AND follow_date >= CURDATE()
				 ORDER BY follow_date ASC LIMIT 5",
				$user->ID
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Build ROI rows from properties.
	 * Pairs each property with the most recent deal linking the lead.
	 *
	 * @param array[] $properties
	 * @param int     $lead_id
	 * @return array[]
	 */
	private function compute_roi( array $properties, $lead_id ) {
		if ( empty( $properties ) ) {
			return [];
		}

		global $wpdb;
		$deals_table = $wpdb->prefix . 'asraa_crm_deals';
		$rows        = [];

		foreach ( $properties as $prop ) {
			$prop_id = (int) ( $prop['id'] ?? 0 );
			$price   = (float) ( $prop['price'] ?? 0 );

			$deal_value = 0.0;
			if ( $lead_id && $prop_id ) {
				$deal_value = (float) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT deal_value FROM {$deals_table}
						 WHERE lead_id = %d AND property_id = %d
						 ORDER BY id DESC LIMIT 1",
						$lead_id,
						$prop_id
					)
				);
			}

			if ( $deal_value > 0 || $price > 0 ) {
				$rows[] = [
					'title'      => $prop['title'] ?? '-',
					'price'      => $price,
					'deal_value' => $deal_value,
				];
			}
		}

		return $rows;
	}

	// ── Utility ───────────────────────────────────────────────────────────────

	/**
	 * Check whether the current queried page contains the shortcode.
	 * Used to conditionally enqueue CSS only when needed.
	 */
	private function page_has_shortcode() {
		global $post;
		return is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'asraa_dashboard' );
	}
}
