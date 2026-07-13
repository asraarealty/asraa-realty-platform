<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Asraa_Broker_Feed_Public' ) ) {
	class Asraa_Broker_Feed_Public {
		public function __construct() {
			add_action( 'init', array( $this, 'register_rewrite_rules' ) );
			add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
			add_action( 'template_redirect', array( $this, 'maybe_render_detail' ) );
		}

		public function register_rewrite_rules() {
			add_rewrite_rule( '^broker-feed/([^/]+)/?$', 'index.php?asraa_broker_feed_slug=$matches[1]', 'top' );
		}

		public function register_query_vars( $vars ) {
			$vars[] = 'asraa_broker_feed_slug';
			return $vars;
		}

		public function maybe_render_detail() {
			$slug = get_query_var( 'asraa_broker_feed_slug' );
			if ( empty( $slug ) ) {
				return;
			}

			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
}
