<?php
/**
 * Cost Tracker
 *
 * Tracks API usage and cost estimation for OpenRouter requests.
 *
 * @package WyvernCSS
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\AI;
use WP_Error;

/**
 * Class CostTracker
 *
 * Monitors and logs API usage costs.
 */
class CostTracker {

	/**
	 * Database table name
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'wyverncss_usage';
	}

	/**
	 * Calculate cost for API request
	 *
	 * @param array<string, int>   $usage Usage data (prompt_tokens, completion_tokens, total_tokens).
	 * @param array<string, mixed> $model_config Model configuration with pricing.
	 *
	 * @return float Cost in dollars.
	 */
	public function calculate_cost( array $usage, array $model_config ): float {
		$total_tokens = $usage['total_tokens'] ?? 0;
		$cost_per_1k  = $model_config['cost_per_1k'] ?? 0.0;

		// Calculate cost: (tokens / 1000) * cost_per_1k.
		return ( $total_tokens / 1000 ) * $cost_per_1k;
	}

	/**
	 * Log usage to database
	 *
	 * @param int                $user_id User ID.
	 * @param string             $model_id Model identifier.
	 * @param array<string, int> $usage Usage data.
	 * @param float              $cost Cost estimate.
	 * @param string             $request_type Type of request (css_generation, refinement, etc.).
	 *
	 * @return int|WP_Error Insert ID or error.
	 */
	public function log_usage( int $user_id, string $model_id, array $usage, float $cost, string $request_type = 'css_generation' ) {
		global $wpdb;

		$data = array(
			'user_id'       => $user_id,
			'request_type'  => $request_type,
			'model_used'    => $model_id,
			'tokens_used'   => $usage['total_tokens'] ?? 0,
			'cost_estimate' => $cost,
			'created_at'    => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$this->table_name,
			$data,
			array( '%d', '%s', '%s', '%d', '%f', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to log usage', 'wyvern-ai-styling' ),
				array( 'db_error' => $wpdb->last_error )
			);
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get total usage for user
	 *
	 * @param int    $user_id User ID.
	 * @param string $period Period (day/week/month/all).
	 *
	 * @return array<string, mixed>|WP_Error Usage statistics or error.
	 */
	public function get_user_usage( int $user_id, string $period = 'all' ) {
		global $wpdb;

		$query_parts = $this->build_query_with_date_filter(
			'SELECT
				COUNT(*) as total_requests,
				SUM(tokens_used) as total_tokens,
				SUM(cost_estimate) as total_cost,
				AVG(tokens_used) as avg_tokens,
				AVG(cost_estimate) as avg_cost
			FROM %i
			WHERE user_id = %d',
			array( $this->table_name, $user_id ),
			$period
		);

		$query = $wpdb->prepare( $query_parts['sql'], ...$query_parts['args'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$result = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $wpdb->last_error ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to retrieve usage', 'wyvern-ai-styling' ),
				array( 'db_error' => $wpdb->last_error )
			);
		}

		return array(
			'total_requests' => (int) ( $result['total_requests'] ?? 0 ),
			'total_tokens'   => (int) ( $result['total_tokens'] ?? 0 ),
			'total_cost'     => (float) ( $result['total_cost'] ?? 0.0 ),
			'avg_tokens'     => (float) ( $result['avg_tokens'] ?? 0.0 ),
			'avg_cost'       => (float) ( $result['avg_cost'] ?? 0.0 ),
			'period'         => $period,
		);
	}

	/**
	 * Get usage by model
	 *
	 * @param int    $user_id User ID.
	 * @param string $period Period.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error Usage by model or error.
	 */
	public function get_usage_by_model( int $user_id, string $period = 'all' ) {
		global $wpdb;

		$query_parts = $this->build_query_with_date_filter(
			'SELECT
				model_used,
				COUNT(*) as request_count,
				SUM(tokens_used) as total_tokens,
				SUM(cost_estimate) as total_cost
			FROM %i
			WHERE user_id = %d',
			array( $this->table_name, $user_id ),
			$period,
			'GROUP BY model_used ORDER BY total_cost DESC'
		);

		$query = $wpdb->prepare( $query_parts['sql'], ...$query_parts['args'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $wpdb->last_error ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to retrieve usage by model', 'wyvern-ai-styling' ),
				array( 'db_error' => $wpdb->last_error )
			);
		}

		return $results ?? array();
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
	 * Check if user has exceeded quota
	 *
	 * @param int                      $user_id User ID.
	 * @param array<string, int|float> $tier_limits Tier limits (requests_per_day, cost_per_month).
	 *
	 * @return bool True if exceeded.
	 */
	public function has_exceeded_quota( int $user_id, array $tier_limits ): bool {
		// Check daily request limit.
		if ( isset( $tier_limits['requests_per_day'] ) ) {
			$daily_usage = $this->get_user_usage( $user_id, 'day' );
			if ( ! is_wp_error( $daily_usage ) && $daily_usage['total_requests'] >= $tier_limits['requests_per_day'] ) {
				return true;
			}
		}

		// Check monthly cost limit.
		if ( isset( $tier_limits['cost_per_month'] ) ) {
			$monthly_usage = $this->get_user_usage( $user_id, 'month' );
			if ( ! is_wp_error( $monthly_usage ) && $monthly_usage['total_cost'] >= $tier_limits['cost_per_month'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get recent usage logs
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Number of records.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error Recent logs or error.
	 */
	public function get_recent_logs( int $user_id, int $limit = 10 ) {
		global $wpdb;

		$query = $wpdb->prepare(
			'SELECT * FROM %i
			WHERE user_id = %d
			ORDER BY created_at DESC
			LIMIT %d',
			$this->table_name,
			$user_id,
			$limit
		);

		$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $wpdb->last_error ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to retrieve logs', 'wyvern-ai-styling' ),
				array( 'db_error' => $wpdb->last_error )
			);
		}

		return $results ?? array();
	}

	/**
	 * Get estimated cost for request
	 *
	 * @param int                  $estimated_tokens Estimated token count.
	 * @param array<string, mixed> $model_config Model configuration.
	 *
	 * @return float Estimated cost.
	 */
	public function estimate_cost( int $estimated_tokens, array $model_config ): float {
		$cost_per_1k = $model_config['cost_per_1k'] ?? 0.0;
		return ( $estimated_tokens / 1000 ) * $cost_per_1k;
	}

	/**
	 * Clear old usage logs
	 *
	 * @param int $days_to_keep Number of days to keep.
	 *
	 * @return int|WP_Error Number of deleted rows or error.
	 */
	public function clear_old_logs( int $days_to_keep = 90 ) {
		global $wpdb;

		$query = $wpdb->prepare(
			'DELETE FROM %i
			WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)',
			$this->table_name,
			$days_to_keep
		);

		$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to clear old logs', 'wyvern-ai-styling' ),
				array( 'db_error' => $wpdb->last_error )
			);
		}

		return $result;
	}
}
