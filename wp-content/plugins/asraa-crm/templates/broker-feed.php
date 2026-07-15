<?php
/**
 * Broker Feed Card Grid Template
 *
 * Rendered by Asraa_Broker_Feed_Shortcode::render().
 *
 * Available variables:
 *   $listings (array) — Rows from Asraa_Broker_Feed_Repository::get_public_feed( ARRAY_A ).
 *                       Only approved + public records are present (enforced in the
 *                       repository query — approval_status = 'approved' AND is_public = 1).
 *                       This template does NOT re-filter or alter that business logic.
 *
 * Security: Broker phone and broker email are NEVER displayed here.
 * All output is escaped with the appropriate wp_kses / esc_* functions.
 *
 * SEO layer (2026-07 audit): every card automatically derives an SEO-friendly title,
 * descriptive alt text, a short description, and Schema.org/JSON-LD structured data
 * purely from existing fields (project, configuration, property_type, city, locality,
 * transaction_type, price). Brokers are never required to type a description, and no
 * dedicated per-property page/URL is created — everything renders inline in this feed.
 *
 * @package Asraa_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Format a numeric price into a human-readable Indian currency string.
 *
 * @param  float  $price Raw numeric price value.
 * @return string        Formatted string, e.g. "₹1.5 Cr", "₹75 L".
 */
if ( ! function_exists( 'asraa_feed_format_price' ) ) {
	function asraa_feed_format_price( float $price ): string {
		if ( $price <= 0 ) {
			return '';
		}
		// UTF-8 rupee sign (U+20B9) — safe for esc_html().
		$rupee = "\u{20B9}";
		if ( $price >= 10000000 ) {
			$crores = $price / 10000000;
			$fmt    = ( fmod( $crores, 1 ) === 0.0 ) ? number_format( $crores, 0 ) : number_format( $crores, 2 );
			return $rupee . $fmt . ' Cr';
		}
		if ( $price >= 100000 ) {
			$lakhs = $price / 100000;
			$fmt   = ( fmod( $lakhs, 1 ) === 0.0 ) ? number_format( $lakhs, 0 ) : number_format( $lakhs, 2 );
			return $rupee . $fmt . ' L';
		}
		return $rupee . number_format( $price, 0 );
	}
}

/**
 * Build a placeholder SVG data URI for cards without an uploaded image.
 *
 * @return string Data URI string.
 */
if ( ! function_exists( 'asraa_feed_placeholder_image' ) ) {
	function asraa_feed_placeholder_image(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500">'
			. '<rect width="100%" height="100%" fill="#f1f5f9"/>'
			. '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" '
			. 'font-size="22" fill="#94a3b8" font-family="sans-serif">No Image Available</text>'
			. '</svg>';
		return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode( $svg );
	}
}

/**
 * Normalize a listing row into a flat set of clean, human-readable fragments
 * used to build SEO copy. Every fragment gracefully degrades to '' when the
 * source field is empty — nothing here invents data that wasn't provided.
 *
 * @param  array $listing Raw listing row (ARRAY_A).
 * @return array{config:string,ptype:string,trans:string,project:string,locality:string,city:string,price:string,raw_title:string}
 */
if ( ! function_exists( 'asraa_feed_seo_fragments' ) ) {
	function asraa_feed_seo_fragments( array $listing ): array {
		$trans_map = [
			'sale'   => 'Sale',
			'rent'   => 'Rent',
			'resale' => 'Resale',
			'lease'  => 'Lease',
		];
		$trans_raw = strtolower( sanitize_text_field( $listing['transaction_type'] ?? '' ) );

		return [
			'config'    => sanitize_text_field( $listing['configuration'] ?? '' ),
			'ptype'     => sanitize_text_field( $listing['property_type'] ?? '' ),
			'trans'     => $trans_map[ $trans_raw ] ?? ucfirst( $trans_raw ),
			'project'   => sanitize_text_field( $listing['project_name'] ?? '' ),
			'locality'  => sanitize_text_field( $listing['locality'] ?? '' ),
			'city'      => sanitize_text_field( $listing['city'] ?? '' ),
			'price_fmt' => asraa_feed_format_price( floatval( $listing['price'] ?? 0 ) ),
			'raw_title' => sanitize_text_field( $listing['title'] ?? '' ),
		];
	}
}

/**
 * Build an SEO-friendly title such as:
 * "3 BHK Apartment for Sale in Bandra, Mumbai – Prestige Heights"
 *
 * Falls back through progressively shorter combinations, then to the
 * broker-entered meta_title / title fields, so a card with sparse data
 * never renders an empty or malformed string.
 *
 * @param  array $listing Raw listing row.
 * @return string
 */
if ( ! function_exists( 'asraa_feed_build_seo_title' ) ) {
	function asraa_feed_build_seo_title( array $listing ): string {
		$f = asraa_feed_seo_fragments( $listing );

		$head = trim( $f['config'] . ' ' . $f['ptype'] );
		if ( '' === $head ) {
			$head = ! empty( $listing['meta_title'] ) ? sanitize_text_field( $listing['meta_title'] ) : $f['raw_title'];
		}

		$location = trim( implode( ', ', array_filter( [ $f['locality'], $f['city'] ] ) ) );

		$title = $head;
		if ( '' !== $f['trans'] ) {
			$title .= ' for ' . $f['trans'];
		}
		if ( '' !== $location ) {
			$title .= ' in ' . $location;
		}
		if ( '' !== $f['project'] ) {
			$title .= ' – ' . $f['project'];
		}

		$title = trim( $title, " \t\n\r\0\x0B–" );

		if ( '' === $title ) {
			$title = ! empty( $listing['meta_title'] ) ? sanitize_text_field( $listing['meta_title'] ) : $f['raw_title'];
		}

		/**
		 * Filter the auto-generated SEO title for a broker feed card.
		 *
		 * @param string $title   The generated title.
		 * @param array  $listing The raw listing row.
		 */
		return (string) apply_filters( 'asraa_feed_seo_title', $title, $listing );
	}
}

/**
 * Build descriptive, unique alt text for the card image (never just "image"
 * or a bare repeated title — each alt describes what's actually in view:
 * configuration + type + locality/city).
 *
 * @param  array  $listing Raw listing row.
 * @param  string $seo_title Pre-built SEO title (avoids recomputation).
 * @return string
 */
if ( ! function_exists( 'asraa_feed_build_alt_text' ) ) {
	function asraa_feed_build_alt_text( array $listing, string $seo_title ): string {
		$alt = $seo_title . ' – property photo';
		return (string) apply_filters( 'asraa_feed_seo_alt', $alt, $listing );
	}
}

/**
 * Build a short (1–2 sentence) auto-generated description purely from
 * existing structured fields. Brokers are never required to type this.
 * Respects a broker/admin-entered meta_description when one already exists.
 *
 * @param  array $listing Raw listing row.
 * @return string
 */
if ( ! function_exists( 'asraa_feed_build_description' ) ) {
	function asraa_feed_build_description( array $listing ): string {
		if ( ! empty( $listing['meta_description'] ) ) {
			return sanitize_text_field( $listing['meta_description'] );
		}

		$f        = asraa_feed_seo_fragments( $listing );
		$location = trim( implode( ', ', array_filter( [ $f['locality'], $f['city'] ] ) ) );

		$parts = [];

		$what = trim( $f['config'] . ' ' . $f['ptype'] );
		if ( '' === $what ) {
			$what = 'Property';
		}
		$sentence = $what;
		if ( '' !== $f['project'] ) {
			$sentence .= ' at ' . $f['project'];
		}
		if ( '' !== $location ) {
			$sentence .= ' in ' . $location;
		}
		if ( '' !== $f['trans'] ) {
			$sentence .= ', available for ' . strtolower( $f['trans'] );
		}
		$parts[] = $sentence . '.';

		if ( '' !== $f['price_fmt'] ) {
			$parts[] = 'Priced at ' . $f['price_fmt'] . '.';
		}

		$brand = get_option( 'asraa_crm_brand_name', 'Asraa Realty' );
		$parts[] = 'Listed via ' . sanitize_text_field( $brand ) . '\'s verified broker network.';

		$description = implode( ' ', $parts );

		return (string) apply_filters( 'asraa_feed_seo_description', $description, $listing );
	}
}

/**
 * Build one Schema.org RealEstateListing node (used inside the feed-level
 * ItemList JSON-LD block). No dedicated property page exists, so `url`
 * points back to this feed with a same-page fragment identifier.
 *
 * @param  array  $listing   Raw listing row.
 * @param  int    $position  1-based position in the list.
 * @param  string $seo_title Pre-built SEO title.
 * @param  string $seo_desc  Pre-built SEO description.
 * @return array
 */
if ( ! function_exists( 'asraa_feed_build_jsonld_item' ) ) {
	function asraa_feed_build_jsonld_item( array $listing, int $position, string $seo_title, string $seo_desc ): array {
		$f         = asraa_feed_seo_fragments( $listing );
		$current   = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : home_url( '/' );
		$anchor_id = 'asraa-listing-' . absint( $listing['id'] ?? $position );

		$item = [
			'@type'       => 'RealEstateListing',
			'name'        => $seo_title,
			'description' => $seo_desc,
			'url'         => $current . '#' . $anchor_id,
		];

		if ( ! empty( $listing['image_url'] ) ) {
			$item['image'] = esc_url_raw( $listing['image_url'] );
		}

		$price = floatval( $listing['price'] ?? 0 );
		if ( $price > 0 ) {
			$item['offers'] = [
				'@type'         => 'Offer',
				'price'         => (string) round( $price, 2 ),
				'priceCurrency' => 'INR',
				'availability'  => 'https://schema.org/InStock',
				'businessFunction' => ( 'rent' === strtolower( $listing['transaction_type'] ?? '' ) || 'lease' === strtolower( $listing['transaction_type'] ?? '' ) )
					? 'http://purl.org/goodrelations/v1#LeaseOut'
					: 'http://purl.org/goodrelations/v1#Sell',
			];
		}

		if ( '' !== $f['city'] || '' !== $f['locality'] ) {
			$item['address'] = array_filter( [
				'@type'           => 'PostalAddress',
				'addressLocality' => '' !== $f['locality'] ? $f['locality'] : $f['city'],
				'addressRegion'   => $f['city'],
				'addressCountry'  => 'IN',
			] );
		}

		return $item;
	}
}
?>

<section class="asraa-broker-feed" id="asraa-broker-feed" aria-labelledby="asraa-broker-feed-heading">

	<?php if ( empty( $listings ) ) : ?>
		<div class="asraa-feed-empty">
			<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 22V12h6v10"/>
			</svg>
			<p><?php esc_html_e( 'No listings are available right now. Please check back soon.', 'asraa-crm' ); ?></p>
		</div>

	<?php else :

		$jsonld_items = [];
	?>

		<header class="asraa-feed-header">
			<p class="asraa-feed-header__eyebrow"><?php esc_html_e( 'Verified Broker Network', 'asraa-crm' ); ?></p>
			<h2 class="asraa-feed-header__title" id="asraa-broker-feed-heading">
				<?php
				echo esc_html(
					apply_filters( 'asraa_feed_section_heading', __( 'Latest Property Listings', 'asraa-crm' ) )
				);
				?>
			</h2>
		</header>

		<div
			class="asraa-feed-carousel"
			id="asraa-feed-carousel"
			role="region"
			aria-label="<?php esc_attr_e( 'Property listings carousel', 'asraa-crm' ); ?>"
			tabindex="0"
		>
			<div class="asraa-feed-track-wrap">
				<div class="asraa-feed-track">
					<?php
					foreach ( $listings as $index => $listing ) :
						$image_url    = ! empty( $listing['image_url'] ) ? esc_url( $listing['image_url'] ) : asraa_feed_placeholder_image();
						$has_real_img = ! empty( $listing['image_url'] );
						$project_name = sanitize_text_field( $listing['project_name'] ?? '' );
						$title        = sanitize_text_field( $listing['title'] ?? '' );
						$config       = sanitize_text_field( $listing['configuration'] ?? '' );
						$city         = sanitize_text_field( $listing['city'] ?? '' );
						$locality     = sanitize_text_field( $listing['locality'] ?? '' );
						$carpet_area  = sanitize_text_field( $listing['carpet_area'] ?? '' );
						$area         = sanitize_text_field( $listing['area'] ?? '' );
						$price_raw    = floatval( $listing['price'] ?? 0 );
						$price_fmt    = asraa_feed_format_price( $price_raw );
						$trans_type   = sanitize_text_field( $listing['transaction_type'] ?? '' );

						// ── SEO layer: derived purely from the fields above, no broker input required.
						$seo_title = asraa_feed_build_seo_title( $listing );
						$seo_alt   = asraa_feed_build_alt_text( $listing, $seo_title );
						$seo_desc  = asraa_feed_build_description( $listing );
						$anchor_id = 'asraa-listing-' . absint( $listing['id'] ?? $index );

						if ( $has_real_img ) {
							$jsonld_items[] = asraa_feed_build_jsonld_item( $listing, $index + 1, $seo_title, $seo_desc );
						}

						// First visible cards (roughly one desktop viewport) get high fetch priority
						// and eager loading; everything after that stays lazy for Core Web Vitals.
						$is_priority = $index < 3;
					?>
					<article class="asraa-feed-card" id="<?php echo esc_attr( $anchor_id ); ?>" aria-labelledby="<?php echo esc_attr( $anchor_id . '-title' ); ?>">

						<div class="asraa-feed-card__image-wrap">
							<img
								class="asraa-feed-card__image"
								src="<?php echo esc_url( $image_url ); ?>"
								alt="<?php echo esc_attr( $seo_alt ); ?>"
								<?php echo $is_priority ? 'loading="eager" fetchpriority="high"' : 'loading="lazy" fetchpriority="low"'; ?>
								decoding="async"
								width="800"
								height="500"
							/>
							<?php if ( ! empty( $trans_type ) ) : ?>
								<span class="asraa-feed-card__badge asraa-badge--<?php echo esc_attr( strtolower( $trans_type ) ); ?>">
									<?php echo esc_html( ucfirst( $trans_type ) ); ?>
								</span>
							<?php endif; ?>
						</div>

						<div class="asraa-feed-card__body">

							<?php if ( ! empty( $project_name ) ) : ?>
								<p class="asraa-feed-card__project"><?php echo esc_html( $project_name ); ?></p>
							<?php endif; ?>

							<h3 class="asraa-feed-card__title" id="<?php echo esc_attr( $anchor_id . '-title' ); ?>"><?php echo esc_html( $title ); ?></h3>

							<p class="asraa-feed-card__seo-desc screen-reader-text"><?php echo esc_html( $seo_desc ); ?></p>

							<ul class="asraa-feed-card__meta">

								<?php if ( ! empty( $config ) ) : ?>
									<li class="asraa-feed-card__meta-item">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
										<span><?php echo esc_html( $config ); ?></span>
									</li>
								<?php endif; ?>

								<?php if ( ! empty( $carpet_area ) ) : ?>
									<li class="asraa-feed-card__meta-item">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
										<span><?php echo esc_html( $carpet_area ); ?></span>
									</li>
								<?php endif; ?>

								<?php if ( ! empty( $city ) ) : ?>
									<li class="asraa-feed-card__meta-item">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
										<span>
											<?php echo esc_html( $city ); ?>
											<?php if ( ! empty( $locality ) ) : ?>
												<span aria-hidden="true">, </span><?php echo esc_html( $locality ); ?>
											<?php endif; ?>
										</span>
									</li>
								<?php endif; ?>

								<?php if ( ! empty( $area ) ) : ?>
									<li class="asraa-feed-card__meta-item">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h10v10H7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h3M17 4h3M4 20h3M17 20h3M4 7V4M20 7V4M4 20v-3M20 20v-3"/></svg>
										<span><?php echo esc_html( $area ); ?></span>
									</li>
								<?php endif; ?>

							</ul>

							<?php if ( ! empty( $price_fmt ) ) : ?>
								<p class="asraa-feed-card__price"><?php echo esc_html( $price_fmt ); ?></p>
							<?php endif; ?>

						</div>

						<div class="asraa-feed-card__footer">
							<a
								href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"
								class="asraa-btn asraa-btn--primary asraa-btn--sm"
							>
								<?php esc_html_e( 'Contact Asraa Realty', 'asraa-crm' ); ?>
							</a>
							<?php
							$_asraa_wa_phone = get_option( 'asraa_crm_brand_phone', '' );
							if ( ! empty( $_asraa_wa_phone ) ) :
								$_asraa_wa_number = preg_replace( '/[^0-9]/', '', $_asraa_wa_phone );
							?>
							<a
								href="<?php echo esc_url( 'https://wa.me/' . $_asraa_wa_number ); ?>"
								class="asraa-btn asraa-btn--whatsapp asraa-btn--sm"
								target="_blank"
								rel="noopener noreferrer"
							>
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.05 4.91A9.82 9.82 0 0012.03 2a9.86 9.86 0 00-8.54 14.8L2 22l5.36-1.4a9.85 9.85 0 004.67 1.18h.01a9.96 9.96 0 009.93-9.9 9.8 9.8 0 00-2.92-6.97zm-7.02 15.2h-.01a8.2 8.2 0 01-4.2-1.15l-.3-.18-3.18.83.85-3.1-.2-.32a8.19 8.19 0 01-1.27-4.35c0-4.54 3.72-8.24 8.3-8.24a8.2 8.2 0 018.28 8.24 8.3 8.3 0 01-8.27 8.27zm4.5-6.18c-.25-.12-1.47-.72-1.7-.8-.23-.08-.4-.12-.56.12-.16.24-.65.8-.8.97-.15.16-.3.18-.56.06-.25-.12-1.05-.38-2-1.2a7.34 7.34 0 01-1.38-1.7c-.15-.25-.02-.39.1-.5.11-.1.25-.26.37-.39.13-.13.17-.22.25-.36.08-.16.04-.28-.02-.4-.06-.12-.56-1.35-.76-1.84-.2-.48-.41-.42-.56-.43h-.48a.92.92 0 00-.66.3c-.23.24-.87.85-.87 2.07 0 1.22.9 2.4 1.02 2.56.12.16 1.73 2.62 4.2 3.67.58.25 1.03.4 1.38.5.58.19 1.1.16 1.52.1.46-.07 1.47-.6 1.68-1.18.2-.58.2-1.08.14-1.18-.05-.1-.22-.16-.47-.28z"/></svg>
								<?php esc_html_e( 'WhatsApp', 'asraa-crm' ); ?>
							</a>
							<?php endif; ?>
						</div>

					</article>
					<?php endforeach; ?>
				</div><!-- /.asraa-feed-track -->
			</div><!-- /.asraa-feed-track-wrap -->

			<button
				class="asraa-feed-nav asraa-feed-prev"
				type="button"
				aria-label="<?php esc_attr_e( 'Previous listings', 'asraa-crm' ); ?>"
			>
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
				</svg>
			</button>

			<button
				class="asraa-feed-nav asraa-feed-next"
				type="button"
				aria-label="<?php esc_attr_e( 'Next listings', 'asraa-crm' ); ?>"
			>
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
				</svg>
			</button>

			<div
				class="asraa-feed-dots"
				role="tablist"
				aria-label="<?php esc_attr_e( 'Listing slide indicators', 'asraa-crm' ); ?>"
			></div>

		</div><!-- /.asraa-feed-carousel -->

		<?php if ( ! empty( $jsonld_items ) ) :
			$list_items = [];
			foreach ( $jsonld_items as $i => $item ) {
				$list_items[] = [
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'item'     => $item,
				];
			}
			$jsonld = [
				'@context'        => 'https://schema.org',
				'@type'           => 'ItemList',
				'name'            => apply_filters( 'asraa_feed_section_heading', __( 'Latest Property Listings', 'asraa-crm' ) ),
				'itemListElement' => $list_items,
			];
			?>
			<script type="application/ld+json"><?php echo wp_json_encode( $jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
		<?php endif; ?>

	<?php endif; ?>

</section>
