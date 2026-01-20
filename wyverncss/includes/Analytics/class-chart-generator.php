<?php
/**
 * Chart Generator
 *
 * Generates Chart.js compatible data for dashboard visualizations.
 *
 * @package WyvernCSS
 * @subpackage Analytics
 */

declare(strict_types=1);

namespace WyvernCSS\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Chart Generator Class
 *
 * Creates data structures for Chart.js visualizations.
 *
 * @since 1.0.0
 */
class ChartGenerator {

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
	 * Create standard pattern/AI datasets
	 *
	 * Helper method to create Chart.js datasets for pattern matches and AI requests.
	 * Follows DRY principle by centralizing dataset structure.
	 *
	 * @param array<int, mixed> $pattern_data Pattern match data points.
	 * @param array<int, mixed> $ai_data      AI request data points.
	 * @param bool              $fill          Whether to fill the area under the line.
	 * @return array<int, array<string, mixed>> Chart.js datasets.
	 */
	private function create_pattern_ai_datasets( array $pattern_data, array $ai_data, bool $fill = true ): array {
		return array(
			array(
				'label'           => __( 'Pattern Matches', 'wyvern-ai-styling' ),
				'data'            => $pattern_data,
				'backgroundColor' => 'rgba(34, 139, 230, 0.2)',
				'borderColor'     => 'rgba(34, 139, 230, 1)',
				'borderWidth'     => 2,
				'fill'            => $fill,
			),
			array(
				'label'           => __( 'AI Requests', 'wyvern-ai-styling' ),
				'data'            => $ai_data,
				'backgroundColor' => 'rgba(236, 72, 153, 0.2)',
				'borderColor'     => 'rgba(236, 72, 153, 1)',
				'borderWidth'     => 2,
				'fill'            => $fill,
			),
		);
	}

	/**
	 * Generate daily requests chart data
	 *
	 * @return array<string, mixed> Chart.js data.
	 */
	public function generate_daily_chart(): array {
		global $wpdb;

		$user_id = get_current_user_id();

		// Get last 7 days of data.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(created_at) as date,
					COUNT(*) as total,
					SUM(CASE WHEN request_type = 'pattern_match' THEN 1 ELSE 0 END) as patterns,
					SUM(CASE WHEN request_type IN ('ai_request', 'css_generation') THEN 1 ELSE 0 END) as ai_requests
				 FROM %i
				 WHERE user_id = %d
				 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
				 GROUP BY DATE(created_at)
				 ORDER BY date ASC",
				$this->table_name,
				$user_id
			),
			ARRAY_A
		);

		$labels       = array();
		$pattern_data = array();
		$ai_data      = array();

		foreach ( $results as $row ) {
			$labels[]       = gmdate( 'M d', strtotime( $row['date'] ) );
			$pattern_data[] = (int) $row['patterns'];
			$ai_data[]      = (int) $row['ai_requests'];
		}

		return array(
			'labels'   => $labels,
			'datasets' => $this->create_pattern_ai_datasets( $pattern_data, $ai_data ),
		);
	}

	/**
	 * Generate weekly requests chart data
	 *
	 * @return array<string, mixed> Chart.js data.
	 */
	public function generate_weekly_chart(): array {
		global $wpdb;

		$user_id = get_current_user_id();

		// Get last 4 weeks of data.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					YEARWEEK(created_at) as week,
					COUNT(*) as total,
					SUM(CASE WHEN request_type = 'pattern_match' THEN 1 ELSE 0 END) as patterns,
					SUM(CASE WHEN request_type IN ('ai_request', 'css_generation') THEN 1 ELSE 0 END) as ai_requests
				 FROM %i
				 WHERE user_id = %d
				 AND created_at >= DATE_SUB(NOW(), INTERVAL 4 WEEK)
				 GROUP BY YEARWEEK(created_at)
				 ORDER BY week ASC",
				$this->table_name,
				$user_id
			),
			ARRAY_A
		);

		$labels       = array();
		$pattern_data = array();
		$ai_data      = array();

		foreach ( $results as $row ) {
			$labels[]       = 'Week ' . substr( $row['week'], -2 );
			$pattern_data[] = (int) $row['patterns'];
			$ai_data[]      = (int) $row['ai_requests'];
		}

		return array(
			'labels'   => $labels,
			'datasets' => $this->create_pattern_ai_datasets( $pattern_data, $ai_data, false ),
		);
	}

	/**
	 * Generate monthly requests chart data
	 *
	 * @return array<string, mixed> Chart.js data.
	 */
	public function generate_monthly_chart(): array {
		global $wpdb;

		$user_id = get_current_user_id();

		// Get last 6 months of data.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE_FORMAT(created_at, '%%Y-%%m') as month,
					COUNT(*) as total,
					SUM(CASE WHEN request_type = 'pattern_match' THEN 1 ELSE 0 END) as patterns,
					SUM(CASE WHEN request_type IN ('ai_request', 'css_generation') THEN 1 ELSE 0 END) as ai_requests
				 FROM %i
				 WHERE user_id = %d
				 AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
				 GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
				 ORDER BY month ASC",
				$this->table_name,
				$user_id
			),
			ARRAY_A
		);

		$labels       = array();
		$pattern_data = array();
		$ai_data      = array();

		foreach ( $results as $row ) {
			$timestamp      = strtotime( $row['month'] . '-01' );
			$labels[]       = gmdate( 'M Y', false !== $timestamp ? $timestamp : 0 );
			$pattern_data[] = (int) $row['patterns'];
			$ai_data[]      = (int) $row['ai_requests'];
		}

		return array(
			'labels'   => $labels,
			'datasets' => $this->create_pattern_ai_datasets( $pattern_data, $ai_data, false ),
		);
	}

	/**
	 * Generate model usage pie chart data
	 *
	 * @return array<string, mixed> Chart.js data.
	 */
	public function generate_model_usage_chart(): array {
		global $wpdb;

		$user_id = get_current_user_id();

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					model_used,
					COUNT(*) as count
				 FROM %i
				 WHERE user_id = %d
				 AND model_used IS NOT NULL
				 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
				 GROUP BY model_used
				 ORDER BY count DESC',
				$this->table_name,
				$user_id
			),
			ARRAY_A
		);

		$labels = array();
		$data   = array();
		$colors = array(
			'rgba(34, 139, 230, 0.8)',
			'rgba(236, 72, 153, 0.8)',
			'rgba(16, 185, 129, 0.8)',
			'rgba(245, 158, 11, 0.8)',
			'rgba(139, 92, 246, 0.8)',
		);

		foreach ( $results as $index => $row ) {
			$labels[] = $this->cost_calculator->get_model_name( $row['model_used'] );
			$data[]   = (int) $row['count'];
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => __( 'Requests by Model', 'wyvern-ai-styling' ),
					'data'            => $data,
					'backgroundColor' => array_slice( $colors, 0, count( $data ) ),
					'borderWidth'     => 2,
					'borderColor'     => '#fff',
				),
			),
		);
	}

	/**
	 * Generate cost breakdown chart data
	 *
	 * @return array<string, mixed> Chart.js data.
	 */
	public function generate_cost_breakdown_chart(): array {
		global $wpdb;

		$user_id = get_current_user_id();

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					model_used,
					SUM(cost_estimate) as total_cost
				 FROM %i
				 WHERE user_id = %d
				 AND model_used IS NOT NULL
				 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
				 GROUP BY model_used
				 ORDER BY total_cost DESC',
				$this->table_name,
				$user_id
			),
			ARRAY_A
		);

		$labels = array();
		$data   = array();

		foreach ( $results as $row ) {
			$labels[] = $this->cost_calculator->get_model_name( $row['model_used'] );
			$data[]   = round( (float) $row['total_cost'], 4 );
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => __( 'Cost by Model ($)', 'wyvern-ai-styling' ),
					'data'            => $data,
					'backgroundColor' => 'rgba(245, 158, 11, 0.5)',
					'borderColor'     => 'rgba(245, 158, 11, 1)',
					'borderWidth'     => 2,
				),
			),
		);
	}
}
