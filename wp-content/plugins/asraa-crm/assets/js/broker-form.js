/**
 * Asraa Broker Post Form — Frontend JavaScript
 *
 * Features:
 *   - Live validation (required fields, price format)
 *   - Price shorthand parsing and formatted display (₹)
 *   - Image file validation and instant preview
 *   - Drag-and-drop image upload
 *   - AJAX submission via wp_ajax_asraa_quick_post
 *   - Loading spinner + button lock (prevent double-submit)
 *   - Inline success / error banners
 *
 * Depends on: jQuery (WordPress bundled), wp_localize_script (asraaBrokerFormConfig)
 *
 * @package Asraa_CRM
 * @since   5.1.0
 */

/* global asraaBrokerFormConfig, jQuery */

( function ( $ ) {
	'use strict';

	// Config injected via wp_localize_script.
	var cfg  = window.asraaBrokerFormConfig || {};
	var i18n = cfg.i18n || {};

	// ── Selectors ────────────────────────────────────────────────────────────
	var $form       = $( '#asraa-broker-post-form' );
	var $submit     = $( '#asraa-broker-form-submit' );
	var $success    = $( '#asraa-broker-form-success' );
	var $error      = $( '#asraa-broker-form-error' );
	var $priceInput = $( '#abf-price' );
	var $priceDisp  = $( '#abf-price-display' );
	var $fileInput  = $( '#abf-property-image' );
	var $preview    = $( '#abf-image-preview' );
	var $placeholder = $( '#abf-image-placeholder' );
	var $removeBtn  = $( '#abf-image-remove' );
	var $dropZone   = $( '#abf-image-drop-zone' );
	var $notesArea  = $( '#abf-notes' );
	var $notesCount = $( '#abf-notes-count' );

	if ( ! $form.length ) {
		return; // Shortcode not on this page.
	}

	// ── Price parsing ────────────────────────────────────────────────────────

	/**
	 * Parse shorthand price strings to a numeric value.
	 *
	 * Supports: "1.5cr", "50L", "1,50,000", plain integers.
	 *
	 * @param  {string} raw Raw input string.
	 * @return {number|false} Numeric value or false when invalid.
	 */
	function parsePriceInput( raw ) {
		if ( ! raw ) {
			return false;
		}
		var s = raw.replace( /,/g, '' ).replace( /\s/g, '' ).toLowerCase();
		if ( ! s ) {
			return false;
		}

		var multiplier = 1;
		if ( /crore|crores|cr/.test( s ) ) {
			multiplier = 10000000;
			s = s.replace( /crores?|cr/g, '' );
		} else if ( /lakh|lakhs|lac|l/.test( s ) ) {
			multiplier = 100000;
			s = s.replace( /lakhs?|lac|l/g, '' );
		}

		var num = parseFloat( s );
		if ( isNaN( num ) || num <= 0 ) {
			return false;
		}
		return num * multiplier;
	}

	/**
	 * Format a numeric price to an Indian currency display string.
	 *
	 * @param  {number} value Numeric price.
	 * @return {string}       Formatted string, e.g. "₹1.5 Cr".
	 */
	function formatPriceDisplay( value ) {
		if ( ! value || value <= 0 ) {
			return '';
		}
		if ( value >= 10000000 ) {
			var cr = value / 10000000;
			return '\u20B9' + ( cr % 1 === 0 ? cr.toFixed( 0 ) : cr.toFixed( 2 ) ) + ' Cr';
		}
		if ( value >= 100000 ) {
			var l = value / 100000;
			return '\u20B9' + ( l % 1 === 0 ? l.toFixed( 0 ) : l.toFixed( 2 ) ) + ' L';
		}
		return '\u20B9' + Number( value ).toLocaleString( 'en-IN' );
	}

	// Live price display.
	$priceInput.on( 'input', function () {
		var val   = $( this ).val().trim();
		var num   = parsePriceInput( val );
		var label = num ? formatPriceDisplay( num ) : '';
		$priceDisp.text( label );
		clearFieldError( $( this ) );
	} );

	// ── Image handling ───────────────────────────────────────────────────────

	var MAX_FILE_SIZE  = 5 * 1024 * 1024; // 5 MB
	var ALLOWED_TYPES  = [ 'image/jpeg', 'image/png', 'image/webp' ];

	/**
	 * Validate and preview a selected image file.
	 *
	 * @param  {File} file File object from input or drag event.
	 * @return {boolean}   True if valid.
	 */
	function handleImageFile( file ) {
		clearFieldError( $fileInput );

		if ( file.size > MAX_FILE_SIZE ) {
			showFieldError( $fileInput, '#abf-image-error', i18n.imageTooBig || 'Image too large.' );
			return false;
		}
		if ( ALLOWED_TYPES.indexOf( file.type ) === -1 ) {
			showFieldError( $fileInput, '#abf-image-error', i18n.imageType || 'Invalid file type.' );
			return false;
		}

		var reader = new FileReader();
		reader.onload = function ( e ) {
			$preview.attr( 'src', e.target.result ).show();
			$placeholder.hide();
			$removeBtn.show();
		};
		reader.readAsDataURL( file );
		return true;
	}

	$fileInput.on( 'change', function () {
		if ( this.files && this.files[0] ) {
			handleImageFile( this.files[0] );
		}
	} );

	// Drag-and-drop on the drop zone.
	$dropZone.on( 'dragover dragenter', function ( e ) {
		e.preventDefault();
		$( this ).addClass( 'asraa-drag-over' );
	} ).on( 'dragleave dragexit', function () {
		$( this ).removeClass( 'asraa-drag-over' );
	} ).on( 'drop', function ( e ) {
		e.preventDefault();
		$( this ).removeClass( 'asraa-drag-over' );
		var dt = e.originalEvent.dataTransfer;
		if ( dt && dt.files && dt.files[0] ) {
			handleImageFile( dt.files[0] );
			// Assign file to the actual input (for FormData).
			try {
				var dataTransfer = new DataTransfer();
				dataTransfer.items.add( dt.files[0] );
				$fileInput[0].files = dataTransfer.files;
			} catch ( ex ) {
				// DataTransfer constructor not available in all browsers; no-op.
			}
		}
	} );

	// Keyboard accessibility on the drop zone label.
	$dropZone.on( 'keydown', function ( e ) {
		if ( e.key === 'Enter' || e.key === ' ' ) {
			e.preventDefault();
			$fileInput.trigger( 'click' );
		}
	} );

	// Remove image.
	$removeBtn.on( 'click', function ( e ) {
		e.preventDefault();
		$preview.attr( 'src', '' ).hide();
		$placeholder.show();
		$removeBtn.hide();
		$fileInput.val( '' );
		clearFieldError( $fileInput );
	} );

	// ── Notes character counter ──────────────────────────────────────────────

	$notesArea.on( 'input', function () {
		var len = $( this ).val().length;
		$notesCount.text( len + ' / 2000' );
	} );

	// ── Validation helpers ───────────────────────────────────────────────────

	function showFieldError( $input, errorSelector, message ) {
		$input.addClass( 'asraa-input--invalid' ).attr( 'aria-invalid', 'true' );
		$( errorSelector ).text( message );
	}

	function clearFieldError( $input ) {
		$input.removeClass( 'asraa-input--invalid' ).removeAttr( 'aria-invalid' );
		// Clear error associated with field by nearest ID pattern.
		var id     = $input.attr( 'id' );
		var $errEl = $( '#' + id + '-error' );
		if ( $errEl.length ) {
			$errEl.text( '' );
		}
	}

	/**
	 * Validate all required fields.
	 *
	 * @return {boolean} True if all valid.
	 */
	function validateForm() {
		var valid = true;

		$form.find( '.asraa-required-field' ).each( function () {
			var $el  = $( this );
			var val  = $el.val().trim();
			var id   = $el.attr( 'id' );
			var $err = $( '#' + id + '-error' );

			if ( ! val ) {
				$el.addClass( 'asraa-input--invalid' ).attr( 'aria-invalid', 'true' );
				$err.text( i18n.required || 'This field is required.' );
				if ( valid ) {
					$el.trigger( 'focus' );
				}
				valid = false;
			} else {
				$el.removeClass( 'asraa-input--invalid' ).removeAttr( 'aria-invalid' );
				$err.text( '' );
			}
		} );

		// Price-specific validation.
		var priceVal = $priceInput.val().trim();
		if ( priceVal && ! parsePriceInput( priceVal ) ) {
			showFieldError( $priceInput, '#abf-price-error', i18n.invalidPrice || 'Invalid price format.' );
			valid = false;
		}

		return valid;
	}

	// Validate on blur for each required field.
	$form.on( 'blur', '.asraa-required-field', function () {
		var $el  = $( this );
		var val  = $el.val().trim();
		var id   = $el.attr( 'id' );
		var $err = $( '#' + id + '-error' );

		if ( ! val ) {
			$el.addClass( 'asraa-input--invalid' ).attr( 'aria-invalid', 'true' );
			$err.text( i18n.required || 'This field is required.' );
		} else {
			$el.removeClass( 'asraa-input--invalid' ).removeAttr( 'aria-invalid' );
			$err.text( '' );
		}
	} );

	// ── Spinner / button state ───────────────────────────────────────────────

	function setLoading( loading ) {
		if ( loading ) {
			$submit
				.prop( 'disabled', true )
				.attr( 'aria-disabled', 'true' )
				.addClass( 'asraa-btn--loading' );
		} else {
			$submit
				.prop( 'disabled', false )
				.attr( 'aria-disabled', 'false' )
				.removeClass( 'asraa-btn--loading' );
		}
	}

	// ── Banner helpers ───────────────────────────────────────────────────────

	function showSuccess( message ) {
		$error.hide();
		$success.find( '.asraa-banner__message' ).text( message );
		$success.show();
		$form.hide();
		$( 'html, body' ).animate(
			{ scrollTop: $( '#asraa-broker-form-wrapper' ).offset().top - 80 },
			300
		);
	}

	function showError( message ) {
		$success.hide();
		$error.find( '.asraa-banner__message' ).text( message );
		$error.show();
		$( 'html, body' ).animate(
			{ scrollTop: $error.offset().top - 80 },
			300
		);
	}

	function hideBanners() {
		$success.hide();
		$error.hide();
	}

	// ── AJAX form submission ──────────────────────────────────────────────────

	$form.on( 'submit', function ( e ) {
		e.preventDefault();
		hideBanners();

		if ( ! validateForm() ) {
			return;
		}

		setLoading( true );

		// Build FormData (supports file upload).
		var formData = new FormData( this );

		// Override action for wp-admin-ajax endpoint.
		formData.set( 'action', 'asraa_quick_post' );

		$.ajax( {
			url:         cfg.ajaxUrl,
			type:        'POST',
			data:        formData,
			processData: false,
			contentType: false,
			success: function ( response ) {
				setLoading( false );
				if ( response && response.success ) {
					showSuccess( ( response.data && response.data.message ) || i18n.success || 'Submitted successfully.' );
				} else {
					var msg = ( response && response.data && response.data.message )
						? response.data.message
						: ( i18n.error || 'Submission failed.' );
					showError( msg );
				}
			},
			error: function () {
				setLoading( false );
				showError( i18n.error || 'A network error occurred. Please try again.' );
			}
		} );
	} );

} )( jQuery );
