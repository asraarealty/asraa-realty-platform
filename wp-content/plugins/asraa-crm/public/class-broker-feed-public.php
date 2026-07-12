<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Asraa_Broker_Feed_Public' ) ) {
	class Asraa_Broker_Feed_Public {
		public function __construct() {
			add_shortcode( 'asraa_broker_feed', array( $this, 'render_listing' ) );
			add_action( 'init', array( $this, 'register_rewrite_rules' ) );
			add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
			add_action( 'template_redirect', array( $this, 'maybe_render_detail' ) );
			add_action( 'wp_head', array( $this, 'render_meta_tags' ) );
		}

		public function render_listing( $atts = array() ) {
			if ( ! class_exists( 'Asraa_Broker_Feed_Repository' ) ) {
				return '<p>' . esc_html__( 'Broker feed is unavailable at the moment.', 'asraa-crm' ) . '</p>';
			}

			$repository = new Asraa_Broker_Feed_Repository();
			$records = $repository->get_public_feed();
			ob_start();
			?>
			<div class="asraa-broker-feed-listing">
				<?php if ( empty( $records ) ) : ?>
					<p><?php esc_html_e( 'No listings available right now.', 'asraa-crm' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( $records as $record ) : ?>
							<li>
								<a href="<?php echo esc_url( $this->get_detail_url( $record ) ); ?>">
									<?php echo esc_html( $record['title'] ?? '' ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php
			return ob_get_clean();
		}

		public function register_rewrite_rules() {
			add_rewrite_rule( '^broker-feed/([^/]+)/?$', 'index.php?asraa_broker_feed_slug=$matches[1]', 'top' );
		}

		public function register_query_vars( $vars ) {
			$vars[] = 'asraa_broker_feed_slug';
			return $vars;
		}

		public function maybe_render_detail() {
			if ( ! class_exists( 'Asraa_Broker_Feed_Repository' ) ) {
				status_header( 404 );
				nocache_headers();
				return;
			}

			$slug = get_query_var( 'asraa_broker_feed_slug' );
			if ( empty( $slug ) ) {
				return;
			}

			$repository = new Asraa_Broker_Feed_Repository();
			$record = $repository->get_by_slug( $slug );
			if ( ! $record ) {
				status_header( 404 );
				nocache_headers();
				return;
			}

			status_header( 200 );
			add_filter( 'template_include', function( $template ) {
				return locate_template( array( 'page.php', 'single.php', 'index.php' ) ) ?: $template;
			}, 99 );
			add_filter( 'the_title', function( $title ) use ( $record ) {
				return $record['title'] ?? $title;
			}, 20 );
			add_filter( 'the_content', function( $content ) use ( $record ) {
				return $this->render_detail( $record );
			}, 20 );
		}

		public function render_detail( $record ) {
			ob_start();
			$image_url = $this->get_display_image_url( $record );
			?>
			<div class="asraa-broker-feed-detail">
				<h1><?php echo esc_html( $record['title'] ?? '' ); ?></h1>
				<p><?php echo esc_html( $record['city'] ?? '' ); ?>, <?php echo esc_html( $record['locality'] ?? '' ); ?></p>
				<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $record['title'] ?? '' ); ?>">
				<p><?php echo esc_html( $record['raw_message'] ?? '' ); ?></p>
				<?php if ( ! empty( $record['source_agent_phone'] ) ) : ?>
					<a href="https://wa.me/<?php echo esc_attr( $record['source_agent_phone'] ?? '' ); ?>" class="button">WhatsApp Enquiry</a>
				<?php endif; ?>
			</div>
			<?php
			return ob_get_clean();
		}

		public function render_meta_tags() {
			if ( ! class_exists( 'Asraa_Broker_Feed_Repository' ) ) {
				return;
			}

			$slug = get_query_var( 'asraa_broker_feed_slug' );
			if ( empty( $slug ) ) {
				return;
			}
			$repository = new Asraa_Broker_Feed_Repository();
			$record = $repository->get_by_slug( $slug );
			if ( ! $record ) {
				return;
			}
			if ( ! empty( $record['meta_title'] ) ) {
				echo '<meta property="og:title" content="' . esc_attr( $record['meta_title'] ) . '" />' . PHP_EOL;
			}
			if ( ! empty( $record['meta_description'] ) ) {
				echo '<meta name="description" content="' . esc_attr( $record['meta_description'] ) . '" />' . PHP_EOL;
			}
		}

		private function get_detail_url( array $record ) : string {
			$slug = $record['slug'] ?? '';
			if ( empty( $slug ) ) {
				$slug = sanitize_title( $record['title'] ?? '' );
			}
			return home_url( '/broker-feed/' . rawurlencode( $slug ) . '/' );
		}

		private function get_display_image_url( array $record ) : string {
			$image_url = isset( $record['image_url'] ) ? trim( (string) $record['image_url'] ) : '';
			if ( '' !== $image_url ) {
				return $image_url;
			}

			return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800"><rect width="100%" height="100%" fill="#f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="34" fill="#6b7280">No Property Image</text></svg>' );
		}
	}
}
