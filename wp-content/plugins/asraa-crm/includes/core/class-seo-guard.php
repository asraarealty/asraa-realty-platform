<?php
/**
 * Asraa CRM SEO Guard
 *
 * Keeps the broker-post-form ([asraa_broker_post_form]) and client-portal
 * ([asraa_dashboard]) pages out of search indexes and sitemaps. These are
 * private, tool-like pages (submission forms / logged-in dashboards), not
 * content meant to be discovered via search.
 *
 * Deliberately does NOT cover [asraa_broker_feed] — that shortcode is the
 * public listings landing page and must stay fully indexed and crawlable.
 *
 * @package    Asraa_CRM
 * @subpackage Core
 * @since      5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Asraa_CRM_Seo_Guard' ) ) {

	class Asraa_CRM_Seo_Guard {

		const TRANSIENT_KEY = 'asraa_crm_seo_excluded_ids';

		/** @var string[] Shortcode tags whose host pages must be excluded from search/sitemaps. */
		private static $guarded_tags = array( 'asraa_broker_post_form', 'asraa_dashboard' );

		public function __construct() {
			add_action( 'wp', array( $this, 'maybe_noindex' ) );
			add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_from_core_sitemap' ), 10, 2 );
			add_action( 'pre_get_posts', array( $this, 'exclude_from_growth_engine_sitemap' ) );
			add_action( 'save_post', array( $this, 'invalidate_cache' ) );
		}

		/**
		 * Add a noindex directive when the current singular page hosts a guarded shortcode.
		 */
		public function maybe_noindex() {
			if ( ! is_singular() ) {
				return;
			}
			global $post;
			if ( ! ( $post instanceof WP_Post ) ) {
				return;
			}
			if ( in_array( $post->ID, self::get_excluded_post_ids(), true ) ) {
				add_filter( 'wp_robots', 'wp_robots_noindex' );
			}
		}

		/**
		 * Exclude guarded pages from WP core's native XML sitemap.
		 *
		 * @param array  $args      Query args passed to WP_Query for the sitemap.
		 * @param string $post_type Post type being queried.
		 * @return array
		 */
		public function exclude_from_core_sitemap( $args, $post_type ) {
			if ( 'page' !== $post_type ) {
				return $args;
			}
			$excluded = self::get_excluded_post_ids();
			if ( $excluded ) {
				$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $excluded );
			}
			return $args;
		}

		/**
		 * Exclude guarded pages from the Growth Engine plugin's custom sitemap, if active.
		 * No-op whenever the `asraa_sitemap` query var isn't set (i.e. that plugin/route
		 * isn't part of the current request).
		 *
		 * @param WP_Query $query
		 */
		public function exclude_from_growth_engine_sitemap( $query ) {
			if ( $query->is_main_query() || 1 != get_query_var( 'asraa_sitemap' ) ) {
				return;
			}
			$excluded = self::get_excluded_post_ids();
			if ( $excluded ) {
				$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), $excluded ) );
			}
		}

		/**
		 * Invalidate the cached ID list whenever any post is saved, so edits
		 * (adding/removing a guarded shortcode) reflect promptly.
		 */
		public function invalidate_cache() {
			delete_transient( self::TRANSIENT_KEY );
		}

		/**
		 * Resolve (and cache) the post IDs that host a guarded shortcode, either in
		 * raw post_content or in Elementor's stored page-builder JSON meta.
		 *
		 * @return int[]
		 */
		public static function get_excluded_post_ids() {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( false !== $cached ) {
				return $cached;
			}

			$ids = array();

			try {
				global $wpdb;

				$like_content = array();
				foreach ( self::$guarded_tags as $tag ) {
					$like_content[] = $wpdb->prepare( 'post_content LIKE %s', '%[' . $wpdb->esc_like( $tag ) . '%' );
				}
				$ids = $wpdb->get_col(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND (" . implode( ' OR ', $like_content ) . ')'
				);

				foreach ( self::$guarded_tags as $tag ) {
					$meta_ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_elementor_data' AND meta_value LIKE %s",
							'%' . $wpdb->esc_like( $tag ) . '%'
						)
					);
					$ids = array_merge( $ids, $meta_ids );
				}

				$ids = array_unique( array_map( 'intval', $ids ) );
			} catch ( \Throwable $e ) {
				if ( class_exists( 'Asraa_CRM_Logger' ) ) {
					Asraa_CRM_Logger::log( 'warning', 'SeoGuard', 'Failed to resolve excluded post IDs: ' . $e->getMessage(), __FILE__, __LINE__ );
				}
				$ids = array();
			}

			set_transient( self::TRANSIENT_KEY, $ids, HOUR_IN_SECONDS );
			return $ids;
		}
	}
}
