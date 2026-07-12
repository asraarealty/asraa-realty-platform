<?php
/**
 * Broker Feed Card Grid Template
 *
 * Rendered by Asraa_Broker_Feed_Shortcode::render().
 *
 * Available variables:
 *   $listings (array) — Rows from Asraa_Broker_Feed_Repository::get_public_feed( ARRAY_A ).
 *                       Only approved + public records are present.
 *
 * Security: Broker phone and broker email are NEVER displayed here.
 * All output is escaped with the appropriate wp_kses / esc_* functions.
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
?>

<div class="asraa-broker-feed" id="asraa-broker-feed">

	<?php if ( empty( $listings ) ) : ?>
		<div class="asraa-feed-empty">
			<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 22V12h6v10"/>
			</svg>
			<p><?php esc_html_e( 'No listings are available right now. Please check back soon.', 'asraa-crm' ); ?></p>
		</div>

	<?php else : ?>

		<div class="asraa-feed-grid">
			<?php foreach ( $listings as $listing ) :
				$image_url    = ! empty( $listing['image_url'] ) ? esc_url( $listing['image_url'] ) : asraa_feed_placeholder_image();
				$project_name = sanitize_text_field( $listing['project_name'] ?? '' );
				$title        = sanitize_text_field( $listing['title'] ?? '' );
				$config       = sanitize_text_field( $listing['configuration'] ?? '' );
				$city         = sanitize_text_field( $listing['city'] ?? '' );
				$locality     = sanitize_text_field( $listing['locality'] ?? '' );
				$carpet_area  = sanitize_text_field( $listing['carpet_area'] ?? '' );
				$price_raw    = floatval( $listing['price'] ?? 0 );
				$price_fmt    = asraa_feed_format_price( $price_raw );
				$slug         = sanitize_title( $listing['slug'] ?? $title );
				$detail_url   = home_url( '/broker-feed/' . rawurlencode( $slug ) . '/' );
				$trans_type   = sanitize_text_field( $listing['transaction_type'] ?? '' );
			?>
			<article class="asraa-feed-card" aria-label="<?php echo esc_attr( $title ); ?>">

				<div class="asraa-feed-card__image-wrap">
					<img
						class="asraa-feed-card__image"
						src="<?php echo esc_url( $image_url ); ?>"
						alt="<?php echo esc_attr( $title ); ?>"
						loading="lazy"
						width="800"
						height="500"
					/>
					<?php if ( ! empty( $trans_type ) ) : ?>
						<span class="asraa-feed-card__badge asraa-badge--<?php echo esc_attr( $trans_type ); ?>">
							<?php echo esc_html( ucfirst( $trans_type ) ); ?>
						</span>
					<?php endif; ?>
				</div>

				<div class="asraa-feed-card__body">

					<?php if ( ! empty( $project_name ) ) : ?>
						<p class="asraa-feed-card__project"><?php echo esc_html( $project_name ); ?></p>
					<?php endif; ?>

					<h3 class="asraa-feed-card__title"><?php echo esc_html( $title ); ?></h3>

					<ul class="asraa-feed-card__meta">

						<?php if ( ! empty( $config ) ) : ?>
							<li class="asraa-feed-card__meta-item">
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
								<span><?php echo esc_html( $config ); ?></span>
							</li>
						<?php endif; ?>

						<?php if ( ! empty( $carpet_area ) ) : ?>
							<li class="asraa-feed-card__meta-item">
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
								<span><?php echo esc_html( $carpet_area ); ?></span>
							</li>
						<?php endif; ?>

						<?php if ( ! empty( $city ) ) : ?>
							<li class="asraa-feed-card__meta-item">
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
								<span>
									<?php echo esc_html( $city ); ?>
									<?php if ( ! empty( $locality ) ) : ?>
										<span aria-hidden="true">, </span><?php echo esc_html( $locality ); ?>
									<?php endif; ?>
								</span>
							</li>
						<?php endif; ?>

					</ul>

					<?php if ( ! empty( $price_fmt ) ) : ?>
						<p class="asraa-feed-card__price"><?php echo esc_html( $price_fmt ); ?></p>
					<?php endif; ?>

				</div>

				<div class="asraa-feed-card__footer">
					<a
						href="<?php echo esc_url( $detail_url ); ?>"
						class="asraa-btn asraa-btn--outline asraa-btn--sm"
					>
						<?php esc_html_e( 'View Details', 'asraa-crm' ); ?>
					</a>
					<a
						href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"
						class="asraa-btn asraa-btn--primary asraa-btn--sm"
					>
						<?php esc_html_e( 'Contact Asraa Realty', 'asraa-crm' ); ?>
					</a>
				</div>

			</article>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>

</div>
