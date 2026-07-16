<?php
/**
 * Asraa Agent Quick Post Form View Template
 *
 * Implements a modern, responsive WordPress Admin UI form layout for rapid
 * property configuration submissions. Includes field grouped sections, native 
 * select structures, asset limitations notes, and dynamic real-time image preview layers.
 * Bridges field architectural mismatches to match immutable legacy controller expectations.
 *
 * @package    Asraa_CRM
 * @subpackage Admin/Pages
 * @category   Templates
 * @version    3.1.1
 * @since      2026-07-10
 * @author     Asraa Realty Architecture & UX Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly to prevent execution tracing exploration.
}

Asraa_CRM_Logger::log( 'info', 'AgentQuickPost', 'Rendering enhanced template' );

// Isolate and process active system identity profiles safely from server session contexts.
$current_authenticated_user = wp_get_current_user();
$rendered_broker_display    = sanitize_text_field( $current_authenticated_user->display_name );
$rendered_broker_telephone  = sanitize_text_field( get_user_meta( $current_authenticated_user->ID, 'billing_phone', true ) ?: get_user_meta( $current_authenticated_user->ID, 'phone', true ) );

if ( empty( $rendered_broker_telephone ) ) {
	$rendered_broker_telephone = __( 'Not Provided', 'asraa-crm' );
}
?>

<div class="wrap asraa-crm-form-wrap">
	<?php if ( isset( $_GET['success'] ) && '1' === $_GET['success'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><strong><?php echo esc_html__( 'Success!', 'asraa-crm' ); ?></strong> <?php echo esc_html__( 'Property registration data submitted successfully for administrative queue authentication validation.', 'asraa-crm' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong><?php echo esc_html__( 'Error Encountered:', 'asraa-crm' ); ?></strong>
				<?php
				$captured_error_flag = sanitize_key( $_GET['error'] );
				if ( 'missing_required_fields' === $captured_error_flag || '1' === $_GET['error'] ) {
					echo esc_html__( 'Please populate all mandatory operational mapping configuration criteria fields designated with red asterisks.', 'asraa-crm' );
				} elseif ( 'database_write_failure' === $captured_error_flag ) {
					echo esc_html__( 'Database persistence failure. The repository system processing layer dropped the update sequence. Please trace system errors logs.', 'asraa-crm' );
				} else {
					echo esc_html__( 'Operational execution pipeline exception. Unable to finalize form processing pathways transformations.', 'asraa-crm' );
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<style>
		.asraa-crm-container { margin-top: 24px; max-width: 1100px; width: 100%; display: grid; grid-template-columns: 1fr; gap: 24px; }
		.asraa-crm-panel { background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 3px rgba(0,0,0,0.04); border-radius: 4px; margin-bottom: 24px; overflow: hidden; }
		.asraa-crm-panel-title { font-size: 14px; font-weight: 600; padding: 14px 20px; margin: 0; background: #f6f7f7; border-bottom: 1px solid #ccd0d4; color: #1d2327; text-transform: uppercase; letter-spacing: 0.5px; }
		.asraa-crm-panel-body { padding: 24px; }
		.asraa-crm-row { display: flex; flex-wrap: wrap; align-items: stretch; gap: 20px; margin-bottom: 18px; }
		.asraa-crm-group { flex: 1; min-width: 240px; display: flex; flex-direction: column; }
		.asraa-crm-group-full { width: 100%; display: flex; flex-direction: column; margin-bottom: 18px; }
		.asraa-crm-label { font-weight: 600; font-size: 13px; color: #1d2327; margin-bottom: 6px; display: inline-block; }
		.asraa-crm-label .required-star { color: #d63638; margin-left: 3px; font-weight: bold; }
		.asraa-crm-input-text, .asraa-crm-select, .asraa-crm-textarea { padding: 6px 10px; font-size: 14px; line-height: 1.5; color: #2c3338; background-color: #fff; border: 1px solid #8c8f94; border-radius: 3px; box-shadow: 0 1px 2px rgba(0,0,0,0.05) inset; transition: border-color 0.1s ease-in-out; width: 100%; max-width: 100%; box-sizing: border-box; }
		.asraa-crm-input-text:focus, .asraa-crm-select:focus, .asraa-crm-textarea:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: 2px solid transparent; }
		.asraa-crm-input-readonly { background-color: #f0f0f1; border-color: #dcdcde; color: #646970; cursor: not-allowed; font-weight: 500; }
		.asraa-crm-desc { font-size: 12px; font-style: italic; color: #646970; margin-top: 5px; margin-bottom: 0; line-height: 1.4; }
		.asraa-preview-box { margin-top: 12px; padding: 12px; border: 1px dashed #c3c4c7; border-radius: 4px; background: #fafafa; display: inline-flex; align-items: center; justify-content: center; min-width: 120px; min-height: 120px; max-width: 240px; position: relative; }
		.asraa-preview-box img { max-width: 100%; height: auto; border-radius: 2px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: none; }
		.asraa-preview-placeholder { font-size: 12px; color: #8c8f94; text-align: center; }
		.asraa-submit-zone { background: #f6f7f7; border-top: 1px solid #ccd0d4; padding: 18px 24px; display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 16px; border-radius: 0 0 4px 4px; }
		@media (max-width: 960px) {
			.asraa-crm-panel-body { padding: 16px; }
			.asraa-crm-group { min-width: 0; }
			.asraa-submit-zone { padding: 16px; }
		}
		@media (max-width: 600px) {
			.asraa-crm-row { flex-direction: column; gap: 16px; }
			.asraa-crm-group { width: 100%; }
			.asraa-submit-zone { flex-direction: column-reverse; align-items: stretch; }
			.asraa-submit-zone .button { width: 100%; text-align: center; }
			.asraa-crm-panel-title { font-size: 13px; padding: 12px 14px; }
		}
	</style>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="asraa-crm-container" id="asraaQuickPostForm" novalidate="novalidate">
		
		<input type="hidden" name="action" value="asraa_quick_post">
		<?php wp_nonce_field( 'asraa_quick_post', 'asraa_quick_post_nonce' ); ?>

		<input type="hidden" name="location" id="legacy_location" value="">

		<div class="asraa-crm-panel">
			<h2 class="asraa-crm-panel-title"><?php echo esc_html__( '1. Broker Information Scope Profile', 'asraa-crm' ); ?></h2>
			<div class="asraa-crm-panel-body">
				<div class="asraa-crm-row">
					<div class="asraa-crm-group">
						<label class="asraa-crm-label"><?php echo esc_html__( 'Authenticated Broker Account', 'asraa-crm' ); ?></label>
						<input type="text" class="asraa-crm-input-text asraa-crm-input-readonly" value="<?php echo esc_attr( $rendered_broker_display ); ?>" readonly="readonly" disabled="disabled">
						<p class="asraa-crm-desc"><?php echo esc_html__( 'System profile owner account identity bound to current session context values.', 'asraa-crm' ); ?></p>
					</div>
					<div class="asraa-crm-group">
						<label class="asraa-crm-label"><?php echo esc_html__( 'Broker Phone Number Reference', 'asraa-crm' ); ?></label>
						<input type="text" class="asraa-crm-input-text asraa-crm-input-readonly" value="<?php echo esc_attr( $rendered_broker_telephone ); ?>" readonly="readonly" disabled="disabled">
						<p class="asraa-crm-desc"><?php echo esc_html__( 'Primary telephone validation index used for tracking inbound inquiries routing layout mappings.', 'asraa-crm' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<div class="asraa-crm-panel">
			<h2 class="asraa-crm-panel-title"><?php echo esc_html__( '2. Property Information Configurations', 'asraa-crm' ); ?></h2>
			<div class="asraa-crm-panel-body">
				
				<div class="asraa-crm-group-full">
					<label for="title" class="asraa-crm-label">
						<?php echo esc_html__( 'Property Title', 'asraa-crm' ); ?><span class="required-star">*</span>
					</label>
					<input type="text" name="title" id="title" class="asraa-crm-input-text" required="required" placeholder="<?php echo esc_attr__( 'e.g., Ultra Luxury Sea-Facing 3BHK Penthouse', 'asraa-crm' ); ?>">
					<p class="asraa-crm-desc"><?php echo esc_html__( 'Provide a brief, descriptive public headline name for the property catalog.', 'asraa-crm' ); ?></p>
				</div>

				<div class="asraa-crm-row">
					<div class="asraa-crm-group">
						<label for="project_name" class="asraa-crm-label">
							<?php echo esc_html__( 'Project Name', 'asraa-crm' ); ?><span class="required-star">*</span>
						</label>
						<input type="text" name="project_name" id="project_name" class="asraa-crm-input-text" required="required" placeholder="<?php echo esc_attr__( 'e.g., Asraa Heights Phase II', 'asraa-crm' ); ?>">
						<p class="asraa-crm-desc"><?php echo esc_html__( 'Specific corporate brand township or project structure declaration.', 'asraa-crm' ); ?></p>
					</div>
					<div class="asraa-crm-group">
						<label for="transaction_type" class="asraa-crm-label">
							<?php echo esc_html__( 'Transaction Type', 'asraa-crm' ); ?><span class="required-star">*</span>
						</label>
						<select name="transaction_type" id="transaction_type" class="asraa-crm-select" required="required">
							<option value="sale"><?php echo esc_html__( 'Sale', 'asraa-crm' ); ?></option>
							<option value="rent"><?php echo esc_html__( 'Rent', 'asraa-crm' ); ?></option>
							<option value="resale"><?php echo esc_html__( 'Resale', 'asraa-crm' ); ?></option>
						</select>
						<p class="asraa-crm-desc"><?php echo esc_html__( 'Operational listing deployment strategy category classification.', 'asraa-crm' ); ?></p>
					</div>
				</div>

				<div class="asraa-crm-row">
					<div class="asraa-crm-group">
						<label for="property_type" class="asraa-crm-label">
							<?php echo esc_html__( 'Property Type', 'asraa-crm' ); ?><span class="required-star">*</span>
						</label>
						<select name="property_type" id="property_type" class="asraa-crm-select" required="required">
							<option value="Apartment"><?php echo esc_html__( 'Apartment', 'asraa-crm' ); ?></option>
							<option value="Villa"><?php echo esc_html__( 'Villa', 'asraa-crm' ); ?></option>
							<option value="Penthouse"><?php echo esc_html__( 'Penthouse', 'asraa-crm' ); ?></option>
							<option value="Studio"><?php echo esc_html__( 'Studio', 'asraa-crm' ); ?></option>
							<option value="Row House"><?php echo esc_html__( 'Row House', 'asraa-crm' ); ?></option>
							<option value="Plot"><?php echo esc_html__( 'Plot', 'asraa-crm' ); ?></option>
							<option value="Commercial Office"><?php echo esc_html__( 'Commercial Office', 'asraa-crm' ); ?></option>
							<option value="Shop"><?php echo esc_html__( 'Shop', 'asraa-crm' ); ?></option>
							<option value="Warehouse"><?php echo esc_html__( 'Warehouse', 'asraa-crm' ); ?></option>
							<option value="Industrial"><?php echo esc_html__( 'Industrial', 'asraa-crm' ); ?></option>
							<option value="Bungalow"><?php echo esc_html__( 'Bungalow', 'asraa-crm' ); ?></option>
						</select>
						<p class="asraa-crm-desc"><?php echo esc_html__( 'Physical structure classification profile mapping attribute.', 'asraa-crm' ); ?></p>
					</div>
					<div class="asraa-crm-group">
						<label for="configuration" class="asraa-crm-label">
							<?php echo esc_html__( 'Configuration Layout', 'asraa-crm' ); ?><span class="required-star">*</span>
						</label>
						<select name="configuration" id="configuration" class="asraa-crm-select" required="required">
							<option value="1RK">1RK</option>
							<option value="1BHK">1BHK</option>
							<option value="2BHK">2BHK</option>
							<option value="3BHK">3BHK</option>
							<option value="4BHK">4BHK</option>
							<option value="5BHK">5BHK</option>
							<option value="Villa">Villa</option>
							<option value="Commercial">Commercial</option>
							<option value="Office">Office</option>
							<option value="Shop">Shop</option>
							<option value="Plot">Plot</option>
						</select>
						<p class="asraa-crm-desc"><?php echo esc_html__( 'Specifies interior architectural layout or dimensional asset format.', 'asraa-crm' ); ?></p>
					</div>
				</div>

			</div>
		</div>

		<div class="asraa-crm-panel">
			<h2 class="asraa-crm-panel-title"><?php echo esc_html__( '3. Geographic Location Dimensions Layout', 'asraa-crm' ); ?></h2>
			<div class="asraa-crm-panel-body">
				<div class="asraa-crm-row">
					<div class="asraa-crm-group">
						<label for="city" class="asraa-crm-label">
							<?php echo esc_html__( 'City Target Location', 'asraa-crm' ); ?><span class="required-star">*</span>
						</label>
						<input type="text" name="city" id="city" class="asraa-crm-input-text" required="required" placeholder="<?php echo esc_attr__( 'e.g., Mumbai', 'asraa-crm' ); ?>">
						<p class="asraa-crm-desc"><?php echo esc_html__( 'Primary city territory indexing reference context.', 'asraa-crm' ); ?></p>
					</div>
					<div class="asraa-crm-group">
						<label for="locality" class="asraa-crm-label">
							<?php echo esc_html__( 'Locality Zone Neighborhood', 'asraa-crm' ); ?><span class="required-star">*</span>
						</label>
						<input type="text" name="locality" id="locality" class="asraa-crm-input-text" required="required" placeholder="<?php echo esc_attr__( 'e.g., Mira Road East', 'asraa-crm' ); ?>">
						<p class="asraa-crm-desc"><?php echo esc_html__( 'Specific sub-market zone neighborhood deployment region.', 'asraa-crm' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<div class="asraa-crm-panel">
			<h2 class="asraa-crm-panel-title"><?php echo esc_html__( '4. Financial Appraisals & Physical Metrics Configurations', 'asraa-crm' ); ?></h2>
			<div class="asraa-crm-panel-body">
				<div class="asraa-crm-row">
					<div class="asraa-crm-group">
						<label for="carpet_area" class="asraa-crm-label">
							<?php echo esc_html__( 'Carpet Area Metrics', 'asraa-crm' ); ?><span class="required-star">*</span>
						</label>
						<input type="text" name="carpet_area" id="carpet_area" class="asraa-crm-input-text" required="required" placeholder="<?php echo esc_attr__( 'e.g., 685 Sq.Ft.', 'asraa-crm' ); ?>">
						<p class="asraa-crm-desc"><?php echo esc_html__( 'Exact physical usable area dimensions calculation string syntax formatting.', 'asraa-crm' ); ?></p>
					</div>
					<div class="asraa-crm-group">
						<label for="available_units" class="asraa-crm-label">
							<?php echo esc_html__( 'Total Available Units Pool', 'asraa-crm' ); ?><span class="required-star">*</span>
						</label>
						<input type="number" name="available_units" id="available_units" class="asraa-crm-input-text" min="1" value="1" required="required">
						<p class="asraa-crm-desc"><?php echo esc_html__( 'Integer value counting matching stock assets pools available inside repository profiles.', 'asraa-crm' ); ?></p>
					</div>
				</div>

				<div class="asraa-crm-group-full" style="margin-bottom: 0;">
					<label for="price" class="asraa-crm-label">
						<?php echo esc_html__( 'Financial Valuation Price (Shorthand Allowed)', 'asraa-crm' ); ?><span class="required-star">*</span>
					</label>
					<input type="text" name="price" id="price" class="asraa-crm-input-text" required="required" placeholder="<?php echo esc_attr__( 'e.g., 1.25 CR or 95 Lac or 15000000', 'asraa-crm' ); ?>">
					<p class="asraa-crm-desc">
						<?php echo esc_html__( 'System backend controller processors parse complex shorthand variables parameters natively. Formats recognized include: "60 L", "95 Lac", "1.25 CR", "2 Crore", or raw numeric representations integers values strings patterns.', 'asraa-crm' ); ?>
					</p>
				</div>
			</div>
		</div>

		<div class="asraa-crm-panel">
			<h2 class="asraa-crm-panel-title"><?php echo esc_html__( '5. Media Library Asset Stream Integration', 'asraa-crm' ); ?></h2>
			<div class="asraa-crm-panel-body">
				<div class="asraa-crm-group-full" style="margin-bottom: 0;">
					<label for="property_image" class="asraa-crm-label"><?php echo esc_html__( 'Primary Property Representation Cover Image', 'asraa-crm' ); ?></label>
					<input type="file" name="property_image" id="property_image" accept="image/*" class="asraa-crm-input-text" style="padding: 4px;">
					<p class="asraa-crm-desc">
						<?php echo esc_html__( 'Accepted binary image formats streams definitions: JPG, JPEG, PNG, WEBP.', 'asraa-crm' ); ?> 
						<strong><?php echo esc_html__( 'Maximum recommended processing resolution metric limit configuration file context dimensions criteria scale bounds: 2MB.', 'asraa-crm' ); ?></strong>
					</p>
					
					<div class="asraa-preview-box" id="imagePreviewContainer">
						<div class="asraa-preview-placeholder" id="previewPlaceholderText">
							<?php echo esc_html__( 'Real-time media layout preview pipeline pending target selection stream upload.', 'asraa-crm' ); ?>
						</div>
						<img src="#" alt="<?php echo esc_attr__( 'Property Cover Preview', 'asraa-crm' ); ?>" id="asraaImageRenderElement">
					</div>
				</div>
			</div>
		</div>

		<div class="asraa-crm-panel">
			<h2 class="asraa-crm-panel-title"><?php echo esc_html__( '6. Additional Specifications Details Scope Matrix', 'asraa-crm' ); ?></h2>
			<div class="asraa-crm-panel-body">
				<div class="asraa-crm-group-full" style="margin-bottom: 0;">
					<label for="notes" class="asraa-crm-label"><?php echo esc_html__( 'Internal Specifications Notes & Meta Descriptions', 'asraa-crm' ); ?></label>
					<textarea name="notes" id="notes" class="asraa-crm-textarea" rows="8" placeholder="<?php echo esc_attr__( 'Provide explicit internal parameters configurations definitions properties features details structural descriptions notes contexts metrics markers parameters here...', 'asraa-crm' ); ?>"></textarea>
					<p class="asraa-crm-desc"><?php echo esc_html__( 'This structural block text context writes to the repository records log and compiles data into raw feed message summaries.', 'asraa-crm' ); ?></p>
				</div>
			</div>
		</div>

		<div class="asraa-submit-zone">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=asraa-agent-quick-post' ) ); ?>" class="button button-secondary button-large" style="height: 40px; line-height: 38px; padding: 0 18px;">
				<?php echo esc_html__( 'Reset Form Layout Parameters Actions', 'asraa-crm' ); ?>
			</a>
			<button type="submit" class="button button-primary button-large" style="height: 40px; line-height: 38px; padding: 0 24px; font-weight: 600; font-size: 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
				<?php echo esc_html__( 'Submit Inventory Item Row', 'asraa-crm' ); ?>
			</button>
		</div>

	</form>
</div>

<script>
/**
 * Asraa CRM View Layer User Experience (UX) Enhancement Pipeline Engine Script Actions
 */
document.addEventListener('DOMContentLoaded', function () {
	const fileInputSelector     = document.getElementById('property_image');
	const renderImageElement    = document.getElementById('asraaImageRenderElement');
	const placeholderTextNode   = document.getElementById('previewPlaceholderText');
	const quickPostFormElement  = document.getElementById('asraaQuickPostForm');

	// Map fields to backward-compatible parameters needed by the immutable controller
	const cityInput             = document.getElementById('city');
	const localityInput         = document.getElementById('locality');
	const carpetAreaInput       = document.getElementById('carpet_area');
	const legacyLocationInput   = document.getElementById('legacy_location');

	// Real-time media browser load stream change listener callback handling.
	fileInputSelector?.addEventListener('change', function () {
		const chosenFileDescriptor = this.files[0];
		if (chosenFileDescriptor) {
			const activeFileReaderInstance = new FileReader();
			activeFileReaderInstance.onload = function (readerEventResult) {
				if (renderImageElement && placeholderTextNode) {
					renderImageElement.src             = readerEventResult.target.result;
					renderImageElement.style.display   = 'block';
					placeholderTextNode.style.display  = 'none';
				}
			};
			activeFileReaderInstance.readAsDataURL(chosenFileDescriptor);
		} else {
			if (renderImageElement && placeholderTextNode) {
				renderImageElement.src             = '#';
				renderImageElement.style.display   = 'none';
				placeholderTextNode.style.display  = 'block';
			}
		}
	});

	// Client-side visual state validation and parameter synthesis layout layer transformation engine.
	quickPostFormElement?.addEventListener('submit', function (formSubmissionEvent) {
		// Enforce validation constraints manually across custom components
		const titleInput = document.getElementById('title');
		const projectNameInput = document.getElementById('project_name');
		const priceInput = document.getElementById('price');
		
		let componentValidationStateIsValid = true;

		// Clear previous structural indicators
		[titleInput, projectNameInput, cityInput, localityInput, carpetAreaInput, priceInput].forEach(el => {
			if(el) el.style.borderColor = '';
		});

		if (!titleInput?.value.trim()) { titleInput.style.borderColor = '#d63638'; componentValidationStateIsValid = false; }
		if (!projectNameInput?.value.trim()) { projectNameInput.style.borderColor = '#d63638'; componentValidationStateIsValid = false; }
		if (!cityInput?.value.trim()) { cityInput.style.borderColor = '#d63638'; componentValidationStateIsValid = false; }
		if (!localityInput?.value.trim()) { localityInput.style.borderColor = '#d63638'; componentValidationStateIsValid = false; }
		if (!carpetAreaInput?.value.trim()) { carpetAreaInput.style.borderColor = '#d63638'; componentValidationStateIsValid = false; }
		if (!priceInput?.value.trim()) { priceInput.style.borderColor = '#d63638'; componentValidationStateIsValid = false; }

		if (!componentValidationStateIsValid) {
			formSubmissionEvent.preventDefault();
			alert('Please populate all mandatory fields designated with red asterisks before attempting submission.');
			return;
		}

		// BRIDGE TO IMMUTABLE LEGACY CONTROLLER: Format and inject payload parameters dynamically
		if (legacyLocationInput && cityInput && localityInput) {
			legacyLocationInput.value = localityInput.value.trim() + ', ' + cityInput.value.trim();
		}
	});
});
</script>
