<?php
/**
 * Usage Analyzer
 *
 * Analyzes usage data and generates statistics.
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
 * Usage Analyzer Class
 *
 * Generates analytics and statistics from usage data.
 *
 * @since 1.0.0
 */
class UsageAnalyzer {

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
	 * Get statistics for a period
	 *
	 * @param string $period Period (daily, weekly, monthly).
	 * @return array<string, mixed> Statistics array.
	 */
	public function get_statistics( string $period = 'daily' ): array {
		$user_id = get_current_user_id();

		return array(
			'current_month'     => $this->get_monthly_total( $user_id ),
			'pattern_matches'   => $this->get_pattern_match_count( $user_id, $period ),
			'ai_requests'       => $this->get_ai_request_count( $user_id, $period ),
			'total_cost'        => $this->get_total_cost( $user_id, $period ),
			'cost_saved'        => $this->get_cost_saved( $user_id, $period ),
			'cache_hit_rate'    => $this->get_cache_hit_rate( $user_id, $period ),
			'avg_response_time' => $this->get_avg_response_time(),
			'top_prompts'       => $this->get_top_prompts( $user_id, 10 ),
			'model_breakdown'   => $this->get_model_breakdown( $user_id, $period ),
		);
	}

	/**
	 * Get monthly total requests
	 *
	 * @param int $user_id User ID.
	 * @return int Total requests this month.
	 */
	public function get_monthly_total( int $user_id ): int {
		global $wpdb;

		$query = $wpdb->prepare(
			'SELECT COUNT(*) FROM %i
			 WHERE user_id = %d
			 AND created_at >= DATE_FORMAT(NOW(), \'%%Y-%%m-01\')',
			$this->table_name,
			$user_id
		);

		$result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return (int) ( $result ?? 0 );
	}

	/**
	 * Get pattern match count
	 *
	 * @param int    $user_id User ID.
	 * @param string $period  Period.
	 * @return int Pattern match count.
	 */
	public function get_pattern_match_count( int $user_id, string $period ): int {
		global $wpdb;

		$query_parts = $this->build_query_with_date_filter(
			"SELECT COUNT(*) FROM %i
			 WHERE user_id = %d
			 AND request_type = 'pattern_match'",
			array( $this->table_name, $user_id ),
			$period
		);

		$query = $wpdb->prepare( $query_parts['sql'], ...$query_parts['args'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return (int) ( $result ?? 0 );
	}

	/**
	 * Get AI request count
	 *
	 * @param int    $user_id User ID.
	 * @param string $period  Period.
	 * @return int AI request count.
	 */
	public function get_ai_request_count( int $user_id, string $period ): int {
		global $wpdb;

		$query_parts = $this->build_query_with_date_filter(
			"SELECT COUNT(*) FROM %i
			 WHERE user_id = %d
			 AND request_type IN ('ai_request', 'css_generation')",
			array( $this->table_name, $user_id ),
			$period
		);

		$query = $wpdb->prepare( $query_parts['sql'], ...$query_parts['args'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return (int) ( $result ?? 0 );
	}

	/**
	 * Get total cost
	 *
	 * @param int    $user_id User ID.
	 * @param string $period  Period.
	 * @return float Total cost.
	 */
	public function get_total_cost( int $user_id, string $period ): float {
		global $wpdb;

		$query_parts = $this->build_query_with_date_filter(
			'SELECT SUM(cost_estimate) FROM %i
			 WHERE user_id = %d',
			array( $this->table_name, $user_id ),
			$period
		);

		$query = $wpdb->prepare( $query_parts['sql'], ...$query_parts['args'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return (float) ( $result ?? 0.0 );
	}

	/**
	 * Get cost saved via pattern matches
	 *
	 * @param int    $user_id User ID.
	 * @param string $period  Period.
	 * @return float Cost saved.
	 */
	public function get_cost_saved( int $user_id, string $period ): float {
		$pattern_matches = $this->get_pattern_match_count( $user_id, $period );
		$model           = get_option( 'wyverncss_model', 'claude-3-5-sonnet-20241022' );

		// Assume average 500 tokens per request.
		return $this->cost_calculator->calculate_savings( $pattern_matches, $model, 500 );
	}

	/**
	 * Get cache hit rate (pattern matches / total requests)
	 *
	 * @param int    $user_id User ID.
	 * @param string $period  Period.
	 * @return int Cache hit rate percentage.
	 */
	public function get_cache_hit_rate( int $user_id, string $period ): int {
		global $wpdb;

		$query_parts = $this->build_query_with_date_filter(
			'SELECT COUNT(*) FROM %i
			 WHERE user_id = %d',
			array( $this->table_name, $user_id ),
			$period
		);

		$total_query = $wpdb->prepare( $query_parts['sql'], ...$query_parts['args'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$total = (int) $wpdb->get_var( $total_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( 0 === $total ) {
			return 0;
		}

		$pattern_matches = $this->get_pattern_match_count( $user_id, $period );

		return (int) round( ( $pattern_matches / $total ) * 100 );
	}

	/**
	 * Get average response time (mock for now)
	 *
	 * @return string Average response time.
	 */
	public function get_avg_response_time(): string {
		// TODO: Implement response time tracking.
		return '1.2s';
	}

	/**
	 * Get top prompts
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Number of prompts.
	 * @return array<int, array<string, mixed>> Top prompts.
	 */
	public function get_top_prompts( int $user_id, int $limit = 10 ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			'SELECT prompt_hash, COUNT(*) as count, MAX(created_at) as last_used
			 FROM %i
			 WHERE user_id = %d
			 AND prompt_hash IS NOT NULL
			 GROUP BY prompt_hash
			 ORDER BY count DESC
			 LIMIT %d',
			$this->table_name,
			$user_id,
			$limit
		);

		$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $results ?? array();
	}

	/**
	 * Get model breakdown
	 *
	 * @param int    $user_id User ID.
	 * @param string $period  Period.
	 * @return array<int, array<string, mixed>> Model usage breakdown.
	 */
	public function get_model_breakdown( int $user_id, string $period ): array {
		global $wpdb;

		$query_parts = $this->build_query_with_date_filter(
			'SELECT
				model_used,
				COUNT(*) as request_count,
				SUM(tokens_used) as total_tokens,
				SUM(cost_estimate) as total_cost
			 FROM %i
			 WHERE user_id = %d
			 AND model_used IS NOT NULL',
			array( $this->table_name, $user_id ),
			$period,
			'GROUP BY model_used ORDER BY total_cost DESC'
		);

		$query = $wpdb->prepare( $query_parts['sql'], ...$query_parts['args'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Add model display names.
		$formatted = array();
		foreach ( $results as $row ) {
			$formatted[] = array(
				'model'         => $row['model_used'],
				'model_name'    => $this->cost_calculator->get_model_name( $row['model_used'] ),
				'request_count' => (int) $row['request_count'],
				'total_tokens'  => (int) $row['total_tokens'],
				'total_cost'    => (float) $row['total_cost'],
			);
		}

		return $formatted;
	}

	/**
	 * Export usage data to CSV
	 *
	 * @param string $period Period.
	 * @return string CSV data.
	 */
	public function export_to_csv( string $period = 'daily' ): string {
		global $wpdb;

		$user_id = get_current_user_id();

		$query_parts = $this->build_query_with_date_filter(
			'SELECT * FROM %i
			 WHERE user_id = %d',
			array( $this->table_name, $user_id ),
			$period,
			'ORDER BY created_at DESC'
		);

		$query = $wpdb->prepare( $query_parts['sql'], ...$query_parts['args'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Build CSV.
		$csv = "ID,User ID,Request Type,Model,Tokens,Cost,Created At\n";

		foreach ( $results as $row ) {
			$csv .= sprintf(
				"%d,%d,%s,%s,%d,%s,%s\n",
				$row['id'],
				$row['user_id'],
				$row['request_type'],
				$row['model_used'] ?? 'N/A',
				$row['tokens_used'],
				number_format( (float) $row['cost_estimate'], 4 ),
				$row['created_at']
			);
		}

		return $csv;
	}

	/**
	 * Build query with date filter
	 *
	 * Safely constructs a SQL query with date filtering without string interpolation.
	 *
	 * @param string            $base_sql Base SQL query with placeholders.
	 * @param array<int|string> $base_args Arguments for base SQL.
	 * @param string            $period Period filter (daily, weekly, monthly, or empty).
	 * @param string            $suffix Optional SQL suffix (GROUP BY, ORDER BY, etc).
	 * @return array{sql: string, args: array<int|string>} Query parts.
	 */
	private function build_query_with_date_filter( string $base_sql, array $base_args, string $period, string $suffix = '' ): array {
		$sql  = $base_sql;
		$args = $base_args;

		// Add date filter based on period.
		switch ( $period ) {
			case 'daily':
				$sql   .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
				$args[] = 1;
				break;
			case 'weekly':
				$sql   .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL %d WEEK)';
				$args[] = 1;
				break;
			case 'monthly':
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
}
