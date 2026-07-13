<?php
/**
 * Asraa Broker Feed Repository
 *
 * Enforces strict WordPress database standard formatting, explicit internal query 
 * parameterization via placeholders, and full PHP 8.3 execution compatibility rules.
 * Serves as the isolated data layer in the Repository -> Service -> Controller architecture.
 *
 * @package    Asraa_CRM
 * @subpackage Repositories
 * @category   Core
 * @version    3.2.5
 * @since      2026-07-10
 * @author     Asraa Realty Architecture Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly to prevent tracking exploration.
}

if ( ! class_exists( 'Asraa_Broker_Feed_Repository' ) ) {

	/**
	 * Class Asraa_Broker_Feed_Repository
	 *
	 * Manages isolated custom table database mutations for the broker properties pipeline.
	 */
	class Asraa_Broker_Feed_Repository {

		/**
		 * Database table name pointer storage string.
		 *
		 * @var string
		 */
		private string $table;
		private const PUBLIC_FEED_CACHE_PREFIX = 'asraa_broker_public_feed_';

		/**
		 * Constructor initializes internal parameters via global database instance interface.
		 */
		public function __construct() {
			global $wpdb;
			$this->table = $wpdb->prefix . 'asraa_broker_feed';
		}

		/**
		 * Clear cached public feed query results.
		 *
		 * @return void
		 */
		private function invalidate_public_feed_cache(): void {
			foreach ( array( 24, 60, 120 ) as $limit ) {
				delete_transient( self::PUBLIC_FEED_CACHE_PREFIX . $limit );
			}
		}

		/**
		 * Check whether the targeted custom feed table storage matrix layer structure exists.
		 *
		 * @access private
		 * @return bool True if table configuration matches, false otherwise.
		 */
		private function table_exists(): bool {
			global $wpdb;
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table )
			);
			return ! empty( $exists );
		}

		/**
		 * Fetch all records matching the repository criteria layer.
		 * Directly resolves the PHP Fatal error: Call to undefined method Asraa_Broker_Feed_Repository::get_all()
		 *
		 * @since  3.2.0
		 * @access public
		 * @param  string $output_type Output structure type object format configuration (OBJECT or ARRAY_A).
		 * @return array Multi-row entity collection array mapped with standard internal rows layout formatting.
		 */
		public function get_all( string $output_type = OBJECT ): array {
			global $wpdb;
			if ( ! $this->table_exists() ) {
				error_log( '[ASRAA BROKER FEED CRITICAL] Database table missing on get_all() invocation.' );
				return array();
			}

			$query   = "SELECT * FROM {$this->table} ORDER BY id DESC";
			$results = $wpdb->get_results( $query, $output_type );
			return is_array( $results ) ? $results : array();
		}

		/**
		 * Persist a freshly created broker submission entry securely.
		 *
		 * @since  3.0.0
		 * @access public
		 * @param  array $data Input dataset mapping coordinates payload.
		 * @return int|bool Database entry row reference identifier, false on failure.
		 */
		public function create( $data ) {
			global $wpdb;
			if ( ! $this->table_exists() ) {
				error_log( '[ASRAA BROKER FEED ERROR] Core configuration storage table missing on database runtime execution context.' );
				return false;
			}

			$insert_data = array(
				'title'              => sanitize_text_field( $data['title'] ?? '' ),
				'project_name'       => sanitize_text_field( $data['project_name'] ?? '' ),
				'property_type'      => sanitize_text_field( $data['property_type'] ?? '' ),
				'transaction_type'   => sanitize_text_field( $data['transaction_type'] ?? 'sale' ),
				'configuration'      => sanitize_text_field( $data['configuration'] ?? '' ),
				'location'           => sanitize_text_field( $data['location'] ?? '' ),
				'city'               => sanitize_text_field( $data['city'] ?? '' ),
				'locality'           => sanitize_text_field( $data['locality'] ?? '' ),
				'area'               => sanitize_text_field( $data['area'] ?? '' ),
				'carpet_area'        => sanitize_text_field( $data['carpet_area'] ?? '' ),
				'available_units'    => absint( $data['available_units'] ?? 1 ),
				'price'              => floatval( $data['price'] ?? 0.00 ),
				'status'             => sanitize_text_field( $data['status'] ?? 'available' ),
				'image_url'          => esc_url_raw( $data['image_url'] ?? '' ),
				'source_agent_id'    => absint( $data['source_agent_id'] ?? 0 ),
				'source_agent_name'  => sanitize_text_field( $data['source_agent_name'] ?? '' ),
				'source_agent_phone' => sanitize_text_field( $data['source_agent_phone'] ?? '' ),
				'source_group'       => sanitize_text_field( $data['source_group'] ?? '' ),
				'raw_message'        => sanitize_textarea_field( $data['raw_message'] ?? '' ),
				'approval_status'    => sanitize_text_field( $data['approval_status'] ?? 'pending' ),
				'is_public'          => isset( $data['is_public'] ) ? absint( $data['is_public'] ) : 0,
				'slug'               => '',
				'meta_title'         => sanitize_text_field( $data['meta_title'] ?? '' ),
				'meta_description'   => sanitize_textarea_field( $data['meta_description'] ?? '' ),
				'notes'              => sanitize_textarea_field( $data['notes'] ?? '' ),
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			);

			if ( ! empty( $insert_data['title'] ) ) {
				$insert_data['slug'] = $this->build_slug( $insert_data['title'], $insert_data['city'], $insert_data['project_name'] );
			}

			if ( empty( $insert_data['meta_title'] ) && ! empty( $insert_data['title'] ) ) {
				$insert_data['meta_title'] = $insert_data['title'];
			}

			$insert_state = $wpdb->insert( $this->table, $insert_data );

			if ( false === $insert_state ) {
				error_log( '[ASRAA CRM REPOSITORY ERROR] Failed to record incoming data configuration context inside base data structure: ' . $wpdb->last_error );
				return false;
			}

			$this->invalidate_public_feed_cache();
			return (int) $wpdb->insert_id;
		}

		/**
		 * Retrieve a specific single feed trace entry record from database context index.
		 * Supports dynamic output types to prevent casting mismatches in views like features_box.php
		 *
		 * @since  3.2.5
		 * @access public
		 * @param  int    $id          Key ID of record entry.
		 * @param  string $output_type Return format type rule (OBJECT by default to preserve legacy view processing).
		 * @return object|array|bool   Result record layout formatting metrics, false if absent.
		 */
		public function get_by_id( int $id, string $output_type = OBJECT ) {
			global $wpdb;
			if ( $id <= 0 ) {
				return false;
			}
			$query  = $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", $id );
			$result = $wpdb->get_row( $query, $output_type );
			return ! empty( $result ) ? $result : false;
		}

		/**
		 * Commit selective data modifications to a specific record.
		 *
		 * @since  3.0.0
		 * @access public
		 * @param  int   $id   Row identification field target pointer.
		 * @param  array $data System array storage configuration schema keys map.
		 * @return bool True if update operations execute successfully, false otherwise.
		 */
		public function update( int $id, array $data ): bool {
			global $wpdb;
			if ( $id <= 0 ) {
				return false;
			}

			$sanitized_data = array();
			foreach ( $data as $key => $value ) {
				switch ( $key ) {
					case 'title':
					case 'project_name':
					case 'property_type':
					case 'transaction_type':
					case 'configuration':
					case 'location':
					case 'city':
					case 'locality':
					case 'area':
					case 'carpet_area':
					case 'status':
					case 'source_group':
					case 'approval_status':
					case 'slug':
					case 'meta_title':
						$sanitized_data[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
						break;
					case 'raw_message':
					case 'notes':
					case 'meta_description':
						$sanitized_data[ $key ] = sanitize_textarea_field( wp_unslash( (string) $value ) );
						break;
					case 'image_url':
						$sanitized_data[ $key ] = esc_url_raw( $value );
						break;
					case 'available_units':
					case 'source_agent_id':
						$sanitized_data[ $key ] = absint( $value );
						break;
					case 'price':
						$sanitized_data[ $key ] = floatval( $value );
						break;
					case 'is_public':
						$sanitized_data[ $key ] = absint( $value );
						break;
					default:
						$sanitized_data[ $key ] = $value;
				}
			}

			$existing = $this->get_by_id( $id, ARRAY_A );
			if ( empty( $existing ) ) {
				return false;
			}

			if ( empty( $sanitized_data['slug'] ) && ! empty( $sanitized_data['title'] ) ) {
				$city = $sanitized_data['city'] ?? ( $existing['city'] ?? '' );
				$project_name = $sanitized_data['project_name'] ?? ( $existing['project_name'] ?? '' );
				$sanitized_data['slug'] = $this->build_slug( $sanitized_data['title'], $city, $project_name, $id );
			}

			if ( empty( $sanitized_data['meta_title'] ) && ! empty( $sanitized_data['title'] ) ) {
				$sanitized_data['meta_title'] = $sanitized_data['title'];
			}

			$sanitized_data['updated_at'] = current_time( 'mysql' );
			$result = $wpdb->update( $this->table, $sanitized_data, array( 'id' => $id ) );
			if ( false !== $result ) {
				$this->invalidate_public_feed_cache();
			}
			return false !== $result;
		}

		/**
		 * Move administrative tracking selection record state parameters to approved profile.
		 *
		 * @since  3.0.0
		 * @access public
		 * @param  int $id Entry database identifier record targeting index.
		 * @return bool True if state shift succeeded, false on pipeline crash.
		 */
		public function approve( $id ): bool {
			global $wpdb;
			$id = absint( $id );
			if ( 0 === $id ) {
				return false;
			}
			$result = $wpdb->update(
				$this->table,
				array(
					'approval_status' => 'approved',
					'is_public'       => 1,
				),
				array( 'id' => $id ),
				array( '%s', '%d' ),
				array( '%d' )
			);
			if ( false !== $result ) {
				$this->invalidate_public_feed_cache();
			}
			return false !== $result;
		}

		/**
		 * Force entry tracking criteria changes to map as rejected parameters.
		 *
		 * @since  3.0.0
		 * @access public
		 * @param  int $id Core entry identity index reference.
		 * @return bool True if record mutation resolves successfully, false otherwise.
		 */
		public function reject( $id ): bool {
			global $wpdb;
			$id = absint( $id );
			if ( 0 === $id ) {
				return false;
			}
			$result = $wpdb->update(
				$this->table,
				array(
					'approval_status' => 'rejected',
					'is_public'       => 0,
				),
				array( 'id' => $id ),
				array( '%s', '%d' ),
				array( '%d' )
			);
			if ( false !== $result ) {
				$this->invalidate_public_feed_cache();
			}
			return false !== $result;
		}

		/**
		 * Erase a record fully from repository system tables.
		 *
		 * @since  3.0.0
		 * @access public
		 * @param  int $id Absolute tracking entry data target map coordinate.
		 * @return bool True on success, false on structural errors.
		 */
		public function delete( $id ): bool {
			global $wpdb;
			$id = absint( $id );
			if ( 0 === $id ) {
				return false;
			}
			$result = $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
			if ( false !== $result ) {
				$this->invalidate_public_feed_cache();
			}
			return false !== $result;
		}

		/**
		 * Process array groupings of multiple row identification integers under structured purge actions.
		 *
		 * @since  3.0.0
		 * @access public
		 * @param  array $ids ID integers mapping matrix listings.
		 * @return bool True if dynamic batch operation logic finalizes safely, false otherwise.
		 */
		public function bulk_delete( array $ids = array() ): bool {
			global $wpdb;
			if ( empty( $ids ) ) {
				return false;
			}
			$sanitized_ids      = array_map( 'absint', $ids );
			$placeholder_string = implode( ',', array_fill( 0, count( $sanitized_ids ), '%d' ) );
			$query              = $wpdb->prepare( "DELETE FROM {$this->table} WHERE id IN ($placeholder_string)", $sanitized_ids );
			$execution_state    = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false !== $execution_state ) {
				$this->invalidate_public_feed_cache();
			}
			return false !== $execution_state;
		}

		/**
		 * Modifies multiple records approval statuses simultaneously inside transactional bulk operations.
		 *
		 * @since  3.2.0
		 * @access public
		 * @param  array  $ids    Array collection composed entirely of unique record integer indexes.
		 * @param  string $status Desired target status state string identifier configuration ('approved', 'rejected').
		 * @return bool True if full array vector committed smoothly, false on system exception bounds.
		 */
		public function bulk_update_status( array $ids, string $status ): bool {
			global $wpdb;
			if ( empty( $ids ) ) {
				return false;
			}

			$sanitized_ids      = array_map( 'absint', $ids );
			$placeholder_string = implode( ',', array_fill( 0, count( $sanitized_ids ), '%d' ) );
			$target_status      = sanitize_key( $status );
			if ( 'approved' === $target_status ) {
				$query = $wpdb->prepare(
					"UPDATE {$this->table} SET approval_status = %s, is_public = %d WHERE id IN ($placeholder_string)",
					array_merge( array( $target_status, 1 ), $sanitized_ids )
				);
			} else {
				$query = $wpdb->prepare(
					"UPDATE {$this->table} SET approval_status = %s, is_public = %d WHERE id IN ($placeholder_string)",
					array_merge( array( $target_status, 0 ), $sanitized_ids )
				);
			}
			$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false !== $result ) {
				$this->invalidate_public_feed_cache();
			}
			return false !== $result;
		}

		public function get_filtered( array $args = array(), string $output_type = OBJECT ): array {
			global $wpdb;
			if ( ! $this->table_exists() ) {
				return array();
			}

			$where = array();
			$values = array();
			$search = sanitize_text_field( $args['search'] ?? '' );
			if ( '' !== $search ) {
				$where[] = '(title LIKE %s OR project_name LIKE %s OR city LIKE %s OR locality LIKE %s OR source_agent_name LIKE %s)';
				$like = '%' . $wpdb->esc_like( $search ) . '%';
				$values[] = $like;
				$values[] = $like;
				$values[] = $like;
				$values[] = $like;
				$values[] = $like;
			}

			$approval_status = sanitize_key( $args['approval_status'] ?? '' );
			if ( '' !== $approval_status ) {
				$where[] = 'approval_status = %s';
				$values[] = $approval_status;
			}

			$is_public = isset( $args['is_public'] ) ? sanitize_key( (string) $args['is_public'] ) : '';
			if ( '' !== $is_public ) {
				$where[] = 'is_public = %d';
				$values[] = ( '1' === $is_public ) ? 1 : 0;
			}

			$property_type = sanitize_text_field( $args['property_type'] ?? '' );
			if ( '' !== $property_type ) {
				$where[] = 'property_type = %s';
				$values[] = $property_type;
			}

			$transaction_type = sanitize_text_field( $args['transaction_type'] ?? '' );
			if ( '' !== $transaction_type ) {
				$where[] = 'transaction_type = %s';
				$values[] = $transaction_type;
			}

			$query = "SELECT * FROM {$this->table}";
			if ( ! empty( $where ) ) {
				$query .= ' WHERE ' . implode( ' AND ', $where );
			}
			$query .= ' ORDER BY id DESC';

			$per_page = isset( $args['per_page'] ) ? max( 1, absint( $args['per_page'] ) ) : 0;
			$offset = isset( $args['offset'] ) ? max( 0, absint( $args['offset'] ) ) : 0;
			if ( $per_page > 0 ) {
				$query .= ' LIMIT %d OFFSET %d';
				$values[] = $per_page;
				$values[] = $offset;
			}

			if ( ! empty( $values ) ) {
				$query = $wpdb->prepare( $query, $values );
			}
			$results = $wpdb->get_results( $query, $output_type );
			return is_array( $results ) ? $results : array();
		}

		public function count_filtered( array $args = array() ): int {
			global $wpdb;
			if ( ! $this->table_exists() ) {
				return 0;
			}

			$where = array();
			$values = array();
			$search = sanitize_text_field( $args['search'] ?? '' );
			if ( '' !== $search ) {
				$where[] = '(title LIKE %s OR project_name LIKE %s OR city LIKE %s OR locality LIKE %s OR source_agent_name LIKE %s)';
				$like = '%' . $wpdb->esc_like( $search ) . '%';
				$values[] = $like;
				$values[] = $like;
				$values[] = $like;
				$values[] = $like;
				$values[] = $like;
			}

			$approval_status = sanitize_key( $args['approval_status'] ?? '' );
			if ( '' !== $approval_status ) {
				$where[] = 'approval_status = %s';
				$values[] = $approval_status;
			}

			$is_public = isset( $args['is_public'] ) ? sanitize_key( (string) $args['is_public'] ) : '';
			if ( '' !== $is_public ) {
				$where[] = 'is_public = %d';
				$values[] = ( '1' === $is_public ) ? 1 : 0;
			}

			$property_type = sanitize_text_field( $args['property_type'] ?? '' );
			if ( '' !== $property_type ) {
				$where[] = 'property_type = %s';
				$values[] = $property_type;
			}

			$transaction_type = sanitize_text_field( $args['transaction_type'] ?? '' );
			if ( '' !== $transaction_type ) {
				$where[] = 'transaction_type = %s';
				$values[] = $transaction_type;
			}

			$query = "SELECT COUNT(*) FROM {$this->table}";
			if ( ! empty( $where ) ) {
				$query .= ' WHERE ' . implode( ' AND ', $where );
			}

			if ( ! empty( $values ) ) {
				$query = $wpdb->prepare( $query, $values );
			}
			$results = $wpdb->get_var( $query );
			return (int) $results;
		}

		public function get_by_slug( string $slug, string $output_type = OBJECT ) {
			global $wpdb;
			$slug = sanitize_title( $slug );
			if ( '' === $slug ) {
				return false;
			}
			$query = $wpdb->prepare( "SELECT * FROM {$this->table} WHERE slug = %s AND approval_status = 'approved' AND is_public = 1 LIMIT 1", $slug );
			$result = $wpdb->get_row( $query, $output_type );
			return ! empty( $result ) ? $result : false;
		}

		public function find_duplicate_submission( array $data = array() ) {
			$title = sanitize_text_field( $data['title'] ?? '' );
			$city = sanitize_text_field( $data['city'] ?? '' );
			$project_name = sanitize_text_field( $data['project_name'] ?? '' );
			if ( '' === $title ) {
				return false;
			}
			$slug = $this->build_slug( $title, $city, $project_name );
			return $this->get_by_slug( $slug, ARRAY_A );
		}

		public function build_slug( string $title, string $city = '', string $project_name = '', int $exclude_id = 0 ): string {
			$base = trim( implode( '-', array_filter( array( sanitize_title( $title ), sanitize_title( $city ), sanitize_title( $project_name ) ) ) ) );
			if ( '' === $base ) {
				$base = 'property';
			}

			$slug = $base;
			$counter = 2;
			while ( $this->slug_exists( $slug, $exclude_id ) ) {
				$slug = $base . '-' . $counter;
				$counter++;
			}
			return $slug;
		}

		public function slug_exists( string $slug, int $exclude_id = 0 ): bool {
			global $wpdb;
			$slug = sanitize_title( $slug );
			if ( '' === $slug ) {
				return false;
			}
			$query = $wpdb->prepare( "SELECT id FROM {$this->table} WHERE slug = %s AND id != %d LIMIT 1", $slug, $exclude_id );
			$result = $wpdb->get_var( $query );
			return ! empty( $result );
		}

		/**
		 * Fetch processing items approved for visibility parameters output metrics.
		 *
		 * @since  3.2.5
		 * @access public
		 * @param  string $output_type Return format structure configuration context default rule (OBJECT).
		 * @return array Multi-row entity database lookup results matrix array.
		 */
		public function get_public_feed( string $output_type = OBJECT, int $limit = 60 ): array {
			global $wpdb;
			if ( ! $this->table_exists() ) {
				return array();
			}

			$limit = max( 1, min( 120, absint( $limit ) ) );
			$cache_key = self::PUBLIC_FEED_CACHE_PREFIX . $limit;

			if ( ARRAY_A === $output_type ) {
				$cached_results = get_transient( $cache_key );
				if ( is_array( $cached_results ) ) {
					return $cached_results;
				}
			}

			$query = $wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE approval_status = %s AND is_public = %d ORDER BY id DESC LIMIT %d",
				'approved',
				1,
				$limit
			);
			$results = $wpdb->get_results( $query, $output_type );
			if ( ARRAY_A === $output_type && is_array( $results ) ) {
				set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );
			}
			return is_array( $results ) ? $results : array();
		}
	}
}
