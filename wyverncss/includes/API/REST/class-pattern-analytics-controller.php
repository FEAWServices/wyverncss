<?php
/**
 * Pattern Analytics REST Controller
 *
 * Proxies pattern analytics and matching requests from WordPress to cloud service.
 * Provides pattern matching, analytics, and cache management endpoints.
 *
 * @package    WyvernCSS
 * @subpackage WyvernCSS/includes/API/REST
 * @since      1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\API\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WyvernCSS\API\REST\Traits\Cloud_Service_Proxy;

/**
 * Pattern Analytics REST Controller Class
 *
 * Provides REST endpoints for pattern matching analytics and management.
 * Enables advanced pattern matching with vector similarity.
 */
class Pattern_Analytics_Controller extends WP_REST_Controller {

	use Cloud_Service_Proxy;

	/**
	 * API namespace.
	 */
	private const NAMESPACE = 'wyverncss/v1';

	/**
	 * REST base path.
	 */
	private const REST_BASE = 'patterns';

	/**
	 * Constructor.
	 *
	 * @param string $cloud_service_url Optional. Cloud service URL. Defaults to localhost.
	 */
	public function __construct( string $cloud_service_url = '' ) {
		$this->namespace = self::NAMESPACE;
		$this->rest_base = self::REST_BASE;
		$this->init_cloud_service_url( $cloud_service_url );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /patterns/match - Advanced pattern matching with vector similarity.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/match',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'match_pattern' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_match_args(),
			)
		);

		// POST /patterns/index - Index pattern definitions for semantic search.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/index',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'index_patterns' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_index_args(),
			)
		);

		// GET /patterns/analytics - Get pattern usage analytics.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/analytics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_analytics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_analytics_args(),
			)
		);

		// GET /patterns/analytics/export - Export pattern analytics data.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/analytics/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_analytics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_export_args(),
			)
		);

		// DELETE /patterns/cache - Clear pattern matching cache.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/cache',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'clear_pattern_cache' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Advanced pattern matching with vector similarity.
	 *
	 * Uses semantic search to find best matching patterns.
	 * Proxies to cloud service RAG search endpoint for pattern matching.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function match_pattern( WP_REST_Request $request ) {
		$license_key = get_option( 'wyverncss_license_key', '' );

		if ( empty( $license_key ) ) {
			return new WP_Error(
				'license_required',
				__( 'License key not configured. Please configure your license key in WyvernCSS settings.', 'wyvern-ai-styling' ),
				array( 'status' => 401 )
			);
		}

		// Prepare request body for semantic search.
		$body = array(
			'query'          => $request->get_param( 'prompt' ),
			'content_types'  => array( 'pattern' ),
			'limit'          => $request->get_param( 'max_results' ) ?? 10,
			'min_similarity' => $request->get_param( 'min_similarity' ) ?? 0.7,
		);

		// Proxy to RAG search endpoint.
		$response = $this->proxy_to_cloud_service(
			'/api/v1/rag/search',
			'POST',
			$body,
			$license_key,
			'Pattern Analytics'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Transform response to pattern match format.
		$patterns = array();
		if ( isset( $response['results'] ) && is_array( $response['results'] ) ) {
			foreach ( $response['results'] as $result ) {
				$patterns[] = array(
					'pattern_id'  => $result['content_type'] ?? 'unknown',
					'title'       => $result['title'] ?? '',
					'description' => $result['excerpt'] ?? '',
					'confidence'  => $result['similarity_score'] ?? 0.0,
					'category'    => 'semantic_match',
				);
			}
		}

		return new WP_REST_Response(
			array(
				'patterns'      => $patterns,
				'count'         => count( $patterns ),
				'search_method' => 'semantic',
			),
			200
		);
	}

	/**
	 * Index pattern definitions for semantic search.
	 *
	 * Indexes pattern library definitions to enable semantic pattern matching.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function index_patterns( WP_REST_Request $request ) {
		$license_key = get_option( 'wyverncss_license_key', '' );

		if ( empty( $license_key ) ) {
			return new WP_Error(
				'license_required',
				__( 'License key not configured. Please configure your license key in WyvernCSS settings.', 'wyvern-ai-styling' ),
				array( 'status' => 401 )
			);
		}

		$patterns = $request->get_param( 'patterns' );
		$indexed  = 0;
		$errors   = array();

		// Index each pattern.
		foreach ( $patterns as $pattern ) {
			$body = array(
				'content_type' => 'pattern',
				'content_id'   => $pattern['pattern_id'] ?? uniqid( 'pattern_', true ),
				'title'        => $pattern['title'] ?? $pattern['pattern_id'] ?? 'Unnamed Pattern',
				'content'      => $pattern['description'] ?? '',
				'excerpt'      => $pattern['keywords'] ?? '',
			);

			$response = $this->proxy_to_cloud_service(
				'/api/v1/rag/index',
				'POST',
				$body,
				$license_key,
				'Pattern Analytics'
			);

			if ( is_wp_error( $response ) ) {
				$errors[] = array(
					'pattern_id' => $pattern['pattern_id'] ?? 'unknown',
					'error'      => $response->get_error_message(),
				);
			} else {
				++$indexed;
			}
		}

		return new WP_REST_Response(
			array(
				'indexed' => $indexed,
				'total'   => count( $patterns ),
				'errors'  => $errors,
				'message' => sprintf(
					/* translators: %1$d: indexed count, %2$d: total count */
					__( 'Indexed %1$d of %2$d patterns', 'wyvern-ai-styling' ),
					$indexed,
					count( $patterns )
				),
			),
			201
		);
	}

	/**
	 * Get pattern usage analytics.
	 *
	 * Retrieves analytics on pattern matching performance and usage.
	 * Proxies to observability metrics endpoints.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function get_analytics( WP_REST_Request $request ) {
		$license_key = get_option( 'wyverncss_license_key', '' );

		if ( empty( $license_key ) ) {
			return new WP_Error(
				'license_required',
				__( 'License key not configured. Please configure your license key in WyvernCSS settings.', 'wyvern-ai-styling' ),
				array( 'status' => 401 )
			);
		}

		$period = $request->get_param( 'period' ) ?? '24h';

		// Get throughput metrics (includes pattern match counts).
		$throughput = $this->proxy_to_cloud_service(
			'/api/v1/observability/metrics/throughput?period=' . $period,
			'GET',
			array(),
			$license_key,
			'Pattern Analytics'
		);

		if ( is_wp_error( $throughput ) ) {
			return $throughput;
		}

		// Get model distribution (pattern vs AI).
		$distribution = $this->proxy_to_cloud_service(
			'/api/v1/observability/metrics/models?period=' . $period,
			'GET',
			array(),
			$license_key,
			'Pattern Analytics'
		);

		if ( is_wp_error( $distribution ) ) {
			return $distribution;
		}

		// Combine analytics data.
		$analytics = array(
			'period'           => $period,
			'throughput'       => $throughput,
			'distribution'     => $distribution,
			'pattern_hit_rate' => $this->calculate_pattern_hit_rate( $throughput ),
			'cost_savings'     => $this->calculate_cost_savings( $throughput ),
		);

		return new WP_REST_Response( $analytics, 200 );
	}

	/**
	 * Export pattern analytics data.
	 *
	 * Exports analytics data in CSV or JSON format.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function export_analytics( WP_REST_Request $request ) {
		$license_key = get_option( 'wyverncss_license_key', '' );

		if ( empty( $license_key ) ) {
			return new WP_Error(
				'license_required',
				__( 'License key not configured. Please configure your license key in WyvernCSS settings.', 'wyvern-ai-styling' ),
				array( 'status' => 401 )
			);
		}

		$period = $request->get_param( 'period' ) ?? '30d';
		$format = $request->get_param( 'format' ) ?? 'json';

		// Get analytics data.
		$analytics_request = new WP_REST_Request( 'GET', '/wyverncss/v1/patterns/analytics' );
		$analytics_request->set_param( 'period', $period );
		$analytics_response = $this->get_analytics( $analytics_request );

		if ( is_wp_error( $analytics_response ) ) {
			return $analytics_response;
		}

		$analytics_data = $analytics_response->get_data();

		// Format export data.
		if ( 'csv' === $format ) {
			$csv_data = $this->format_analytics_as_csv( $analytics_data );
			return new WP_REST_Response(
				array(
					'format' => 'csv',
					'data'   => $csv_data,
				),
				200
			);
		}

		// Default to JSON.
		return new WP_REST_Response(
			array(
				'format' => 'json',
				'data'   => $analytics_data,
			),
			200
		);
	}

	/**
	 * Clear pattern matching cache.
	 *
	 * Clears WordPress transient cache for pattern matching.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function clear_pattern_cache( WP_REST_Request $request ) {
		// Clear all pattern-related transients.
		global $wpdb;

		// Delete transients matching pattern cache keys.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_wyverncss_pattern_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_wyverncss_pattern_' ) . '%'
			)
		);

		// Also clear object cache if available.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'wyverncss_patterns' );
		}

		return new WP_REST_Response(
			array(
				'success'       => true,
				'deleted_count' => $deleted,
				'message'       => sprintf(
					/* translators: %d: number of cache entries deleted */
					__( 'Cleared %d pattern cache entries', 'wyvern-ai-styling' ),
					$deleted
				),
			),
			200
		);
	}

	/**
	 * Calculate pattern hit rate from throughput data.
	 *
	 * @param array<string, mixed> $throughput Throughput data.
	 * @return float Pattern hit rate (0.0-1.0).
	 */
	private function calculate_pattern_hit_rate( array $throughput ): float {
		if ( ! isset( $throughput['total_requests'] ) || 0 === $throughput['total_requests'] ) {
			return 0.0;
		}

		$pattern_requests = $throughput['pattern_requests'] ?? 0;
		return round( $pattern_requests / $throughput['total_requests'], 4 );
	}

	/**
	 * Calculate cost savings from pattern matching.
	 *
	 * @param array<string, mixed> $throughput Throughput data.
	 * @return array<string, mixed> Cost savings data.
	 */
	private function calculate_cost_savings( array $throughput ): array {
		$pattern_requests = $throughput['pattern_requests'] ?? 0;

		// Estimated cost per AI request (in cents).
		$cost_per_ai_request = 0.5;

		// Cost savings from pattern matches (pattern matches are free).
		$savings = $pattern_requests * $cost_per_ai_request;

		return array(
			'pattern_requests'        => $pattern_requests,
			'estimated_savings_cents' => round( $savings, 2 ),
			'estimated_savings_usd'   => round( $savings / 100, 2 ),
		);
	}

	/**
	 * Format analytics data as CSV.
	 *
	 * @param array<string, mixed> $analytics Analytics data.
	 * @return string CSV formatted data.
	 */
	private function format_analytics_as_csv( array $analytics ): string {
		$csv_lines = array();

		// Header.
		$csv_lines[] = 'Metric,Value';

		// Period.
		$csv_lines[] = sprintf( 'Period,%s', $analytics['period'] ?? 'unknown' );

		// Pattern hit rate.
		$csv_lines[] = sprintf( 'Pattern Hit Rate,%.2f%%', ( $analytics['pattern_hit_rate'] ?? 0 ) * 100 );

		// Cost savings.
		if ( isset( $analytics['cost_savings'] ) ) {
			$csv_lines[] = sprintf(
				'Cost Savings (USD),$%.2f',
				$analytics['cost_savings']['estimated_savings_usd'] ?? 0
			);
		}

		return implode( "\n", $csv_lines );
	}

	/**
	 * Get match endpoint arguments.
	 *
	 * @return array<string, mixed> Endpoint arguments.
	 */
	private function get_match_args(): array {
		return array(
			'prompt'         => array(
				'description'       => __( 'User prompt for pattern matching', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'max_results'    => array(
				'description' => __( 'Maximum number of pattern matches', 'wyvern-ai-styling' ),
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 50,
			),
			'min_similarity' => array(
				'description' => __( 'Minimum similarity threshold (0.0-1.0)', 'wyvern-ai-styling' ),
				'type'        => 'number',
				'default'     => 0.7,
				'minimum'     => 0.0,
				'maximum'     => 1.0,
			),
		);
	}

	/**
	 * Get index endpoint arguments.
	 *
	 * @return array<string, mixed> Endpoint arguments.
	 */
	private function get_index_args(): array {
		return array(
			'patterns' => array(
				'description' => __( 'Array of pattern definitions to index', 'wyvern-ai-styling' ),
				'type'        => 'array',
				'required'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'pattern_id'  => array( 'type' => 'string' ),
						'title'       => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'keywords'    => array( 'type' => 'string' ),
					),
				),
			),
		);
	}

	/**
	 * Get analytics endpoint arguments.
	 *
	 * @return array<string, mixed> Endpoint arguments.
	 */
	private function get_analytics_args(): array {
		return array(
			'period' => array(
				'description'       => __( 'Time period for analytics (1h, 24h, 7d, 30d, 90d)', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'default'           => '24h',
				'enum'              => array( '1h', '24h', '7d', '30d', '90d' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get export endpoint arguments.
	 *
	 * @return array<string, mixed> Endpoint arguments.
	 */
	private function get_export_args(): array {
		return array(
			'period' => array(
				'description'       => __( 'Time period for export (7d, 30d, 90d)', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'default'           => '30d',
				'enum'              => array( '7d', '30d', '90d' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'format' => array(
				'description'       => __( 'Export format (json, csv)', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'default'           => 'json',
				'enum'              => array( 'json', 'csv' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Check if user has permission to use pattern features.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return bool|WP_Error True if user can access, error otherwise.
	 */
	public function check_permission( WP_REST_Request $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to use pattern features.', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check if user has admin permission.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return bool|WP_Error True if user is admin, error otherwise.
	 */
	public function check_admin_permission( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage pattern analytics.', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
