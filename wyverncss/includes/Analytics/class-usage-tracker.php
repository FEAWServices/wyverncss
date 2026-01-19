<?php
/**
 * Usage Tracker
 *
 * Tracks every API request and pattern match for analytics.
 *
 * @package WyvernCSS
 * @subpackage Analytics
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\Analytics;
use WP_Error;

/**
 * Usage Tracker Class
 *
 * Records usage events to the database for analytics and reporting.
 *
 * @since 1.0.0
 */
class UsageTracker {

	/**
	 * Database table name
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Cost calculator instance
	 *
	 * @var CostCalculator
	 */
	private CostCalculator $cost_calculator;

	/**
	 * Constructor
	 *
	 * @param CostCalculator $cost_calculator Cost calculator instance.
	 */
	public function __construct( CostCalculator $cost_calculator ) {
		global $wpdb;
		$this->table_name      = $wpdb->prefix . 'wyverncss_usage';
		$this->cost_calculator = $cost_calculator;
	}

	/**
	 * Track a request
	 *
	 * @param int         $user_id      User ID.
	 * @param string      $request_type Type of request (pattern, ai_request, css_generation).
	 * @param string|null $model        AI model used (null for pattern matches).
	 * @param int         $tokens       Tokens used (0 for pattern matches).
	 * @param string|null $prompt_hash  Hash of the prompt for deduplication.
	 * @return int|WP_Error Insert ID or error.
	 */
	public function track_request(
		int $user_id,
		string $request_type,
		?string $model = null,
		int $tokens = 0,
		?string $prompt_hash = null
	) {
		global $wpdb;

		$cost = 0.0;
		if ( $model && $tokens > 0 ) {
			$cost = $this->cost_calculator->calculate_cost( $model, $tokens );
		}

		$data = array(
			'user_id'       => $user_id,
			'request_type'  => $request_type,
			'model_used'    => $model,
			'tokens_used'   => $tokens,
			'cost_estimate' => $cost,
			'prompt_hash'   => $prompt_hash,
			'created_at'    => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$this->table_name,
			$data,
			array( '%d', '%s', '%s', '%d', '%f', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to track request', 'wyvern-ai-styling' ),
				array( 'db_error' => $wpdb->last_error )
			);
		}

		return $wpdb->insert_id;
	}

	/**
	 * Track pattern match (no API cost)
	 *
	 * @param int    $user_id     User ID.
	 * @param string $pattern_key Pattern key matched.
	 * @return int|WP_Error Insert ID or error.
	 */
	public function track_pattern_match( int $user_id, string $pattern_key ) {
		return $this->track_request(
			$user_id,
			'pattern_match',
			null,
			0,
			md5( $pattern_key )
		);
	}

	/**
	 * Track AI request
	 *
	 * @param int    $user_id User ID.
	 * @param string $model   AI model used.
	 * @param int    $tokens  Tokens used.
	 * @param string $prompt  User prompt.
	 * @return int|WP_Error Insert ID or error.
	 */
	public function track_ai_request( int $user_id, string $model, int $tokens, string $prompt ) {
		return $this->track_request(
			$user_id,
			'ai_request',
			$model,
			$tokens,
			md5( $prompt )
		);
	}

	/**
	 * Track CSS generation
	 *
	 * @param int    $user_id User ID.
	 * @param string $model   AI model used.
	 * @param int    $tokens  Tokens used.
	 * @param string $prompt  User prompt.
	 * @return int|WP_Error Insert ID or error.
	 */
	public function track_css_generation( int $user_id, string $model, int $tokens, string $prompt ) {
		return $this->track_request(
			$user_id,
			'css_generation',
			$model,
			$tokens,
			md5( $prompt )
		);
	}

	/**
	 * Get total requests for user
	 *
	 * @param int    $user_id User ID.
	 * @param string $period  Period (day, week, month, all).
	 * @return int Total requests.
	 */
	public function get_total_requests( int $user_id, string $period = 'all' ): int {
		global $wpdb;

		$query_parts = $this->build_query_with_date_filter(
			'SELECT COUNT(*) FROM %i WHERE user_id = %d',
			array( $this->table_name, $user_id ),
			$period
		);

		$query = $wpdb->prepare( $query_parts['sql'], ...$query_parts['args'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return (int) ( $result ?? 0 );
	}

	/**
	 * Get total cost for user
	 *
	 * @param int    $user_id User ID.
	 * @param string $period  Period (day, week, month, all).
	 * @return float Total cost.
	 */
	public function get_total_cost( int $user_id, string $period = 'all' ): float {
		global $wpdb;

		$query_parts = $this->build_query_with_date_filter(
			'SELECT SUM(cost_estimate) FROM %i WHERE user_id = %d',
			array( $this->table_name, $user_id ),
			$period
		);

		$query = $wpdb->prepare( $query_parts['sql'], ...$query_parts['args'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return (float) ( $result ?? 0.0 );
	}

	/**
	 * Build query with date filter
	 *
	 * Safely constructs a SQL query with date filtering without string interpolation.
	 *
	 * @param string            $base_sql Base SQL query with placeholders.
	 * @param array<int|string> $base_args Arguments for base SQL.
	 * @param string            $period Period filter (day, week, month, or empty).
	 * @param string            $suffix Optional SQL suffix (GROUP BY, ORDER BY, etc).
	 * @return array{sql: string, args: array<int|string>} Query parts.
	 */
	private function build_query_with_date_filter( string $base_sql, array $base_args, string $period, string $suffix = '' ): array {
		$sql  = $base_sql;
		$args = $base_args;

		// Add date filter based on period.
		switch ( $period ) {
			case 'day':
				$sql   .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
				$args[] = 1;
				break;
			case 'week':
				$sql   .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL %d WEEK)';
				$args[] = 1;
				break;
			case 'month':
				$sql   .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL %d MONTH)';
				$args[] = 1;
				break;
		}

		// Add suffix if provided.
		if ( ! empty( $suffix ) ) {
			$sql .= ' ' . $suffix;
		}

		return array(
			'sql'  => $sql,
			'args' => $args,
		);
	}

	/**
	 * Clear old usage records
	 *
	 * @param int $days_to_keep Number of days to keep.
	 * @return int|WP_Error Number of deleted rows or error.
	 */
	public function clear_old_records( int $days_to_keep = 90 ) {
		global $wpdb;

		$query = $wpdb->prepare(
			'DELETE FROM %i WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)',
			$this->table_name,
			$days_to_keep
		);

		$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to clear old records', 'wyvern-ai-styling' ),
				array( 'db_error' => $wpdb->last_error )
			);
		}

		return $result;
	}
}
