<?php
/**
 * Audit Logger
 *
 * Logs all admin AI actions to a custom database table for audit trail.
 *
 * @package WyvernCSS
 * @subpackage Admin
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Audit Logger Class
 *
 * Creates and manages audit log table for admin AI actions.
 * Logs include user ID, action, details (JSON), IP address, and timestamp.
 *
 * @since 1.0.0
 */
class Audit_Logger {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	private string $table_name = 'wyverncss_audit_log';

	/**
	 * Full table name (with prefix).
	 *
	 * @var string
	 */
	private string $full_table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->full_table_name = $wpdb->prefix . $this->table_name;

		// Create table if it doesn't exist.
		$this->maybe_create_table();
	}

	/**
	 * Create audit log table if it doesn't exist.
	 *
	 * @return void
	 */
	private function maybe_create_table(): void {
		global $wpdb;

		$table_name      = $this->full_table_name;
		$charset_collate = $wpdb->get_charset_collate();

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking table existence.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( $table_exists === $table_name ) {
			return;
		}

		// Create table.
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			action varchar(100) NOT NULL,
			details longtext NOT NULL,
			ip_address varchar(45) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY action (action),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Log an admin AI action.
	 *
	 * @param string               $action  Action identifier (e.g., 'post_bulk_update_categories').
	 * @param array<string, mixed> $details Additional details as associative array.
	 * @return bool True on success, false on failure.
	 */
	public function log( string $action, array $details ): bool {
		global $wpdb;

		$user_id    = get_current_user_id();
		$ip_address = $this->get_client_ip();
		$created_at = current_time( 'mysql' );

		// Convert details to JSON.
		$details_json = wp_json_encode( $details );
		if ( false === $details_json ) {
			return false;
		}

		// Insert log entry.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert.
		$result = $wpdb->insert(
			$this->full_table_name,
			array(
				'user_id'    => $user_id,
				'action'     => sanitize_text_field( $action ),
				'details'    => $details_json,
				'ip_address' => sanitize_text_field( $ip_address ),
				'created_at' => $created_at,
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		return false !== $result;
	}

	/**
	 * Get audit logs with pagination and filtering.
	 *
	 * @param array<string, mixed> $filters Filters (user_id, action, date_from, date_to, limit, offset).
	 * @return array<int, array<string, mixed>> Array of log entries.
	 */
	public function get_logs( array $filters = array() ): array {
		global $wpdb;

		$where_clauses = array( '1=1' );
		$where_values  = array();

		// Filter by user ID.
		if ( ! empty( $filters['user_id'] ) && is_int( $filters['user_id'] ) ) {
			$where_clauses[] = 'user_id = %d';
			$where_values[]  = $filters['user_id'];
		}

		// Filter by action.
		if ( ! empty( $filters['action'] ) && is_string( $filters['action'] ) ) {
			$where_clauses[] = 'action = %s';
			$where_values[]  = sanitize_text_field( $filters['action'] );
		}

		// Filter by date range.
		if ( ! empty( $filters['date_from'] ) && is_string( $filters['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = sanitize_text_field( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) && is_string( $filters['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = sanitize_text_field( $filters['date_to'] );
		}

		// Build WHERE clause.
		$where_sql = implode( ' AND ', $where_clauses );

		// Pagination.
		$limit  = isset( $filters['limit'] ) && is_int( $filters['limit'] ) ? $filters['limit'] : 50;
		$offset = isset( $filters['offset'] ) && is_int( $filters['offset'] ) ? $filters['offset'] : 0;

		// Build query.
		$query = "SELECT * FROM {$this->full_table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		// Add limit and offset to values.
		$where_values[] = $limit;
		$where_values[] = $offset;

		// Prepare and execute query.
		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is properly prepared with placeholders.
			$prepared_query = $wpdb->prepare( $query, $where_values );
		} else {
			$prepared_query = $query;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table query with prepared statement.
		$results = $wpdb->get_results( $prepared_query, ARRAY_A );

		if ( ! is_array( $results ) ) {
			return array();
		}

		// Decode JSON details.
		foreach ( $results as &$result ) {
			if ( isset( $result['details'] ) && is_string( $result['details'] ) ) {
				$decoded           = json_decode( $result['details'], true );
				$result['details'] = is_array( $decoded ) ? $decoded : array();
			}
		}

		return $results;
	}

	/**
	 * Get total count of logs matching filters.
	 *
	 * @param array<string, mixed> $filters Filters (user_id, action, date_from, date_to).
	 * @return int Total count.
	 */
	public function get_logs_count( array $filters = array() ): int {
		global $wpdb;

		$where_clauses = array( '1=1' );
		$where_values  = array();

		// Filter by user ID.
		if ( ! empty( $filters['user_id'] ) && is_int( $filters['user_id'] ) ) {
			$where_clauses[] = 'user_id = %d';
			$where_values[]  = $filters['user_id'];
		}

		// Filter by action.
		if ( ! empty( $filters['action'] ) && is_string( $filters['action'] ) ) {
			$where_clauses[] = 'action = %s';
			$where_values[]  = sanitize_text_field( $filters['action'] );
		}

		// Filter by date range.
		if ( ! empty( $filters['date_from'] ) && is_string( $filters['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = sanitize_text_field( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) && is_string( $filters['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = sanitize_text_field( $filters['date_to'] );
		}

		// Build WHERE clause.
		$where_sql = implode( ' AND ', $where_clauses );

		// Build query.
		$query = "SELECT COUNT(*) FROM {$this->full_table_name} WHERE {$where_sql}";

		// Prepare and execute query.
		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is properly prepared with placeholders.
			$prepared_query = $wpdb->prepare( $query, $where_values );
		} else {
			$prepared_query = $query;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table query with prepared statement.
		$count = $wpdb->get_var( $prepared_query );

		return is_numeric( $count ) ? (int) $count : 0;
	}

	/**
	 * Delete old logs (cleanup).
	 *
	 * @param int $days_old Delete logs older than this many days.
	 * @return int Number of rows deleted.
	 */
	public function delete_old_logs( int $days_old = 90 ): int {
		global $wpdb;

		$date_threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

		// Build DELETE query with table name from class property.
		// Table name cannot be prepared as a placeholder per WordPress standards.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->full_table_name} WHERE created_at < %s",
				$date_threshold
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Get client IP address.
	 *
	 * Handles various proxy scenarios.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',  // Proxy.
			'HTTP_X_REAL_IP',        // Nginx proxy.
			'REMOTE_ADDR',           // Direct connection.
		);

		foreach ( $ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) && filter_var( wp_unslash( $_SERVER[ $key ] ), FILTER_VALIDATE_IP ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (from X-Forwarded-For).
				if ( str_contains( $ip, ',' ) ) {
					$ip_list = explode( ',', $ip );
					$ip      = trim( $ip_list[0] );
				}
				return $ip;
			}
		}

		return '0.0.0.0';
	}
}
