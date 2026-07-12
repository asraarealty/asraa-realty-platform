<?php
/**
 * Broker Property Submission Form Template
 *
 * Rendered by Asraa_Broker_Form_Shortcode::render().
 *
 * Available variables (resolved server-side from the current WP session):
 *   $broker_id    (int)     Current user ID.
 *   $broker_name  (string)  Current user display name.
 *   $broker_phone (string)  Current user phone number.
 *   $current_user (WP_User) Full user object.
 *
 * NOTE: Hidden broker fields are for progressive-enhancement only.
 * The controller always re-fetches these values from the authenticated session.
 *
 * @package Asraa_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="asraa-broker-form-wrapper" id="asraa-broker-form-wrapper">
	<div class="asraa-broker-form-card">

		<div class="asraa-broker-form-header">
			<h2 class="asraa-broker-form-title">
				<?php esc_html_e( 'Submit a Property Listing', 'asraa-crm' ); ?>
			</h2>
			<p class="asraa-broker-form-subtitle">
				<?php esc_html_e( 'Fill in the details below. Your listing will appear after admin review.', 'asraa-crm' ); ?>
			</p>
		</div>

		<!-- Success / error banners (shown by JS) -->
		<div class="asraa-banner asraa-banner--success" id="asraa-broker-form-success" style="display:none;" role="alert" aria-live="polite">
			<span class="asraa-banner__icon" aria-hidden="true">&#10003;</span>
			<span class="asraa-banner__message"></span>
		</div>
		<div class="asraa-banner asraa-banner--error" id="asraa-broker-form-error" style="display:none;" role="alert" aria-live="assertive">
			<span class="asraa-banner__icon" aria-hidden="true">&#9888;</span>
			<span class="asraa-banner__message"></span>
		</div>

		<form
			id="asraa-broker-post-form"
			class="asraa-broker-form"
			method="post"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			enctype="multipart/form-data"
			novalidate
		>
			<?php wp_nonce_field( 'asraa_quick_post', 'asraa_quick_post_nonce' ); ?>
			<input type="hidden" name="action" value="asraa_quick_post" />
			<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ); ?>" />

			<!--
				Broker hidden fields — displayed for progressive-enhancement / no-JS fallback.
				The server controller ALWAYS overwrites these from the authenticated session.
			-->
			<input type="hidden" name="broker_id"       value="<?php echo esc_attr( (string) $broker_id ); ?>" />
			<input type="hidden" name="broker_name"     value="<?php echo esc_attr( $broker_name ); ?>" />
			<input type="hidden" name="broker_phone"    value="<?php echo esc_attr( $broker_phone ); ?>" />
			<input type="hidden" name="current_user_id" value="<?php echo esc_attr( (string) $broker_id ); ?>" />

			<!-- ── Section 1 : Project & Property ──────────────────────── -->
			<div class="asraa-form-section">
				<h3 class="asraa-form-section-title">
					<?php esc_html_e( 'Project &amp; Property', 'asraa-crm' ); ?>
				</h3>
				<div class="asraa-form-grid">

					<div class="asraa-form-field">
						<label for="abf-project-name" class="asraa-form-label">
							<?php esc_html_e( 'Project Name', 'asraa-crm' ); ?>
							<span class="asraa-required" aria-hidden="true">*</span>
						</label>
						<input
							type="text"
							id="abf-project-name"
							name="project_name"
							class="asraa-form-input asraa-required-field"
							placeholder="<?php esc_attr_e( 'e.g. Prestige Lakeside', 'asraa-crm' ); ?>"
							maxlength="255"
							autocomplete="off"
						/>
						<span class="asraa-field-error" id="abf-project-name-error" role="alert"></span>
					</div>

					<div class="asraa-form-field">
						<label for="abf-title" class="asraa-form-label">
							<?php esc_html_e( 'Property Title', 'asraa-crm' ); ?>
							<span class="asraa-required" aria-hidden="true">*</span>
						</label>
						<input
							type="text"
							id="abf-title"
							name="title"
							class="asraa-form-input asraa-required-field"
							placeholder="<?php esc_attr_e( 'e.g. 3 BHK Luxury Apartment', 'asraa-crm' ); ?>"
							maxlength="255"
							autocomplete="off"
						/>
						<span class="asraa-field-error" id="abf-title-error" role="alert"></span>
					</div>

					<div class="asraa-form-field">
						<label for="abf-transaction-type" class="asraa-form-label">
							<?php esc_html_e( 'Transaction Type', 'asraa-crm' ); ?>
							<span class="asraa-required" aria-hidden="true">*</span>
						</label>
						<select
							id="abf-transaction-type"
							name="transaction_type"
							class="asraa-form-select asraa-required-field"
						>
							<option value=""><?php esc_html_e( '— Select —', 'asraa-crm' ); ?></option>
							<option value="sale"><?php esc_html_e( 'Sale', 'asraa-crm' ); ?></option>
							<option value="rent"><?php esc_html_e( 'Rent / Lease', 'asraa-crm' ); ?></option>
							<option value="resale"><?php esc_html_e( 'Resale', 'asraa-crm' ); ?></option>
						</select>
						<span class="asraa-field-error" id="abf-transaction-type-error" role="alert"></span>
					</div>

					<div class="asraa-form-field">
						<label for="abf-property-type" class="asraa-form-label">
							<?php esc_html_e( 'Property Type', 'asraa-crm' ); ?>
							<span class="asraa-required" aria-hidden="true">*</span>
						</label>
						<select
							id="abf-property-type"
							name="property_type"
							class="asraa-form-select asraa-required-field"
						>
							<option value=""><?php esc_html_e( '— Select —', 'asraa-crm' ); ?></option>
							<option value="Apartment"><?php esc_html_e( 'Apartment', 'asraa-crm' ); ?></option>
							<option value="Villa"><?php esc_html_e( 'Villa', 'asraa-crm' ); ?></option>
							<option value="Plot"><?php esc_html_e( 'Plot / Land', 'asraa-crm' ); ?></option>
							<option value="Office"><?php esc_html_e( 'Office Space', 'asraa-crm' ); ?></option>
							<option value="Shop"><?php esc_html_e( 'Shop / Retail', 'asraa-crm' ); ?></option>
							<option value="Warehouse"><?php esc_html_e( 'Warehouse', 'asraa-crm' ); ?></option>
							<option value="Other"><?php esc_html_e( 'Other', 'asraa-crm' ); ?></option>
						</select>
						<span class="asraa-field-error" id="abf-property-type-error" role="alert"></span>
					</div>

					<div class="asraa-form-field">
						<label for="abf-configuration" class="asraa-form-label">
							<?php esc_html_e( 'Configuration', 'asraa-crm' ); ?>
						</label>
						<input
							type="text"
							id="abf-configuration"
							name="configuration"
							class="asraa-form-input"
							placeholder="<?php esc_attr_e( 'e.g. 2 BHK, 3 BHK + Study', 'asraa-crm' ); ?>"
							maxlength="100"
						/>
					</div>

				</div>
			</div><!-- /.asraa-form-section -->

			<!-- ── Section 2 : Location ─────────────────────────────────── -->
			<div class="asraa-form-section">
				<h3 class="asraa-form-section-title">
					<?php esc_html_e( 'Location', 'asraa-crm' ); ?>
				</h3>
				<div class="asraa-form-grid">

					<div class="asraa-form-field">
						<label for="abf-city" class="asraa-form-label">
							<?php esc_html_e( 'City', 'asraa-crm' ); ?>
							<span class="asraa-required" aria-hidden="true">*</span>
						</label>
						<input
							type="text"
							id="abf-city"
							name="city"
							class="asraa-form-input asraa-required-field"
							placeholder="<?php esc_attr_e( 'e.g. Bengaluru', 'asraa-crm' ); ?>"
							maxlength="100"
						/>
						<span class="asraa-field-error" id="abf-city-error" role="alert"></span>
					</div>

					<div class="asraa-form-field">
						<label for="abf-locality" class="asraa-form-label">
							<?php esc_html_e( 'Locality', 'asraa-crm' ); ?>
						</label>
						<input
							type="text"
							id="abf-locality"
							name="locality"
							class="asraa-form-input"
							placeholder="<?php esc_attr_e( 'e.g. Whitefield', 'asraa-crm' ); ?>"
							maxlength="150"
						/>
					</div>

					<div class="asraa-form-field asraa-form-field--full">
						<label for="abf-location" class="asraa-form-label">
							<?php esc_html_e( 'Location / Landmark', 'asraa-crm' ); ?>
						</label>
						<input
							type="text"
							id="abf-location"
							name="location"
							class="asraa-form-input"
							placeholder="<?php esc_attr_e( 'e.g. Near Forum Mall, ITPL Main Road', 'asraa-crm' ); ?>"
							maxlength="255"
						/>
					</div>

				</div>
			</div><!-- /.asraa-form-section -->

			<!-- ── Section 3 : Pricing & Size ───────────────────────────── -->
			<div class="asraa-form-section">
				<h3 class="asraa-form-section-title">
					<?php esc_html_e( 'Pricing &amp; Size', 'asraa-crm' ); ?>
				</h3>
				<div class="asraa-form-grid">

					<div class="asraa-form-field">
						<label for="abf-carpet-area" class="asraa-form-label">
							<?php esc_html_e( 'Carpet Area', 'asraa-crm' ); ?>
						</label>
						<input
							type="text"
							id="abf-carpet-area"
							name="carpet_area"
							class="asraa-form-input"
							placeholder="<?php esc_attr_e( 'e.g. 1,200 sq.ft', 'asraa-crm' ); ?>"
							maxlength="100"
						/>
					</div>

					<div class="asraa-form-field">
						<label for="abf-available-units" class="asraa-form-label">
							<?php esc_html_e( 'Available Units', 'asraa-crm' ); ?>
						</label>
						<input
							type="number"
							id="abf-available-units"
							name="available_units"
							class="asraa-form-input"
							value="1"
							min="1"
							max="9999"
						/>
					</div>

					<div class="asraa-form-field">
						<label for="abf-price" class="asraa-form-label">
							<?php esc_html_e( 'Price', 'asraa-crm' ); ?>
							<span class="asraa-required" aria-hidden="true">*</span>
						</label>
						<input
							type="text"
							id="abf-price"
							name="price"
							class="asraa-form-input asraa-required-field asraa-price-field"
							placeholder="<?php esc_attr_e( 'e.g. 1.5Cr, 50L, 2500000', 'asraa-crm' ); ?>"
							maxlength="50"
						/>
						<span class="asraa-price-display" id="abf-price-display" aria-live="polite"></span>
						<span class="asraa-field-error" id="abf-price-error" role="alert"></span>
					</div>

				</div>
			</div><!-- /.asraa-form-section -->

			<!-- ── Section 4 : Media & Notes ────────────────────────────── -->
			<div class="asraa-form-section">
				<h3 class="asraa-form-section-title">
					<?php esc_html_e( 'Media &amp; Notes', 'asraa-crm' ); ?>
				</h3>
				<div class="asraa-form-grid">

					<div class="asraa-form-field asraa-form-field--full">
						<label for="abf-property-image" class="asraa-form-label">
							<?php esc_html_e( 'Property Image', 'asraa-crm' ); ?>
						</label>
						<div class="asraa-image-upload-wrapper">
							<label
								for="abf-property-image"
								class="asraa-image-upload-label"
								id="abf-image-drop-zone"
								tabindex="0"
								role="button"
								aria-label="<?php esc_attr_e( 'Upload property image', 'asraa-crm' ); ?>"
							>
								<div class="asraa-image-placeholder" id="abf-image-placeholder">
									<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4-4 4 4 4-6 4 6M4 20h16M8 10a2 2 0 100-4 2 2 0 000 4z"/>
									</svg>
									<span><?php esc_html_e( 'Click to upload or drag &amp; drop', 'asraa-crm' ); ?></span>
									<small><?php esc_html_e( 'JPG, PNG, WebP — max 5\u00a0MB', 'asraa-crm' ); ?></small>
								</div>
								<img
									id="abf-image-preview"
									class="asraa-image-preview"
									src=""
									alt="<?php esc_attr_e( 'Property image preview', 'asraa-crm' ); ?>"
									style="display:none;"
								/>
							</label>
							<input
								type="file"
								id="abf-property-image"
								name="property_image"
								class="asraa-file-input"
								accept="image/jpeg,image/png,image/webp"
								aria-describedby="abf-image-error"
							/>
							<button type="button" class="asraa-image-remove" id="abf-image-remove" style="display:none;" aria-label="<?php esc_attr_e( 'Remove image', 'asraa-crm' ); ?>">
								&#10005;
							</button>
						</div>
						<span class="asraa-field-error" id="abf-image-error" role="alert"></span>
					</div>

					<div class="asraa-form-field asraa-form-field--full">
						<label for="abf-notes" class="asraa-form-label">
							<?php esc_html_e( 'Notes', 'asraa-crm' ); ?>
						</label>
						<textarea
							id="abf-notes"
							name="notes"
							class="asraa-form-textarea"
							rows="4"
							placeholder="<?php esc_attr_e( 'Any additional details about the property\u2026', 'asraa-crm' ); ?>"
							maxlength="2000"
						></textarea>
						<span class="asraa-char-count" id="abf-notes-count" aria-live="polite">0 / 2000</span>
					</div>

				</div>
			</div><!-- /.asraa-form-section -->

			<!-- ── Footer / Submit ──────────────────────────────────────── -->
			<div class="asraa-form-footer">
				<button
					type="submit"
					id="asraa-broker-form-submit"
					class="asraa-btn asraa-btn--primary"
				>
					<span class="asraa-btn__text"><?php esc_html_e( 'Submit Property', 'asraa-crm' ); ?></span>
					<span class="asraa-btn__spinner asraa-form-spinner" aria-hidden="true"></span>
				</button>
			</div>

		</form>
	</div>
</div>
