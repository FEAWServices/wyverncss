<?php
/**
 * Observability REST API Controller
 *
 * Handles REST API endpoints for observability metrics and analytics.
 * Proxies requests to the MCP/Cloud service for metrics aggregation.
 *
 * @package WyvernCSS\API\REST
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\API\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Observability_Controller
 *
 * REST API controller for observability and analytics operations.
 *
 * @phpstan-type RequestParams array<string, mixed>
 */
class Observability_Controller extends WP_REST_Controller {

	/**
	 * API namespace
	 */
	private const NAMESPACE = 'wyverncss/v1';

	/**
	 * MCP Service base URL
	 *
	 * @var string
	 */
	private string $mcp_service_url;

	/**
	 * Constructor
	 *
	 * @param string $mcp_service_url MCP service base URL.
	 */
	public function __construct( string $mcp_service_url = '' ) {
		// Default to localhost in development.
		$this->mcp_service_url = ! empty( $mcp_service_url ) ? $mcp_service_url : 'http://localhost:8001';
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /observability/service-health - Get service health status.
		register_rest_route(
			self::NAMESPACE,
			'/observability/service-health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_service_health' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// GET /observability/metrics/throughput - Get throughput metrics.
		register_rest_route(
			self::NAMESPACE,
			'/observability/metrics/throughput',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_throughput_metrics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_period_args(),
			)
		);

		// GET /observability/metrics/models - Get model distribution.
		register_rest_route(
			self::NAMESPACE,
			'/observability/metrics/models',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_model_distribution' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_period_args(),
			)
		);

		// GET /observability/metrics/tokens - Get token usage metrics.
		register_rest_route(
			self::NAMESPACE,
			'/observability/metrics/tokens',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_token_usage' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_period_args( '7d' ),
			)
		);

		// GET /observability/metrics/costs - Get cost metrics.
		register_rest_route(
			self::NAMESPACE,
			'/observability/metrics/costs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cost_metrics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'period' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '6m',
						'enum'              => array( '3m', '6m', '12m' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /observability/performance - Get performance metrics.
		register_rest_route(
			self::NAMESPACE,
			'/observability/performance',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_performance_metrics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'period' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '24h',
						'enum'              => array( '1h', '24h', '7d', '30d' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /observability/alerts - Get active alerts.
		register_rest_route(
			self::NAMESPACE,
			'/observability/alerts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_alerts' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// GET /observability/licenses - Get license usage metrics.
		register_rest_route(
			self::NAMESPACE,
			'/observability/licenses',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_license_metrics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'period' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '30d',
						'enum'              => array( '7d', '30d', '90d' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /observability/requests - Get request log.
		register_rest_route(
			self::NAMESPACE,
			'/observability/requests',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_request_log' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'limit'  => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
					'offset' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Get service health status
	 *
	 * @phpstan-param WP_REST_Request<RequestParams> $request
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_service_health( WP_REST_Request $request ) {
		return $this->proxy_request( '/api/v1/observability/service-health' );
	}

	/**
	 * Get throughput metrics
	 *
	 * @phpstan-param WP_REST_Request<RequestParams> $request
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_throughput_metrics( WP_REST_Request $request ) {
		$period = $request->get_param( 'period' ) ?? '24h';
		return $this->proxy_request( '/api/v1/observability/metrics/throughput?period=' . $period );
	}

	/**
	 * Get model distribution metrics
	 *
	 * @phpstan-param WP_REST_Request<RequestParams> $request
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_model_distribution( WP_REST_Request $request ) {
		$period = $request->get_param( 'period' ) ?? '24h';
		return $this->proxy_request( '/api/v1/observability/metrics/models?period=' . $period );
	}

	/**
	 * Get token usage metrics
	 *
	 * @phpstan-param WP_REST_Request<RequestParams> $request
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_token_usage( WP_REST_Request $request ) {
		$period = $request->get_param( 'period' ) ?? '7d';
		return $this->proxy_request( '/api/v1/observability/metrics/tokens?period=' . $period );
	}

	/**
	 * Get cost metrics
	 *
	 * @phpstan-param WP_REST_Request<RequestParams> $request
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_cost_metrics( WP_REST_Request $request ) {
		$period = $request->get_param( 'period' ) ?? '6m';
		return $this->proxy_request( '/api/v1/observability/metrics/costs?period=' . $period );
	}

	/**
	 * Get performance metrics
	 *
	 * @phpstan-param WP_REST_Request<RequestParams> $request
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_performance_metrics( WP_REST_Request $request ) {
		$period = $request->get_param( 'period' ) ?? '24h';
		return $this->proxy_request( '/api/v1/observability/performance?period=' . $period );
	}

	/**
	 * Get active alerts
	 *
	 * @phpstan-param WP_REST_Request<RequestParams> $request
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_alerts( WP_REST_Request $request ) {
		return $this->proxy_request( '/api/v1/observability/alerts' );
	}

	/**
	 * Get license metrics
	 *
	 * @phpstan-param WP_REST_Request<RequestParams> $request
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_license_metrics( WP_REST_Request $request ) {
		$period = $request->get_param( 'period' ) ?? '30d';
		return $this->proxy_request( '/api/v1/observability/licenses?period=' . $period );
	}

	/**
	 * Get request log
	 *
	 * @phpstan-param WP_REST_Request<RequestParams> $request
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_request_log( WP_REST_Request $request ) {
		$limit  = $request->get_param( 'limit' ) ?? 50;
		$offset = $request->get_param( 'offset' ) ?? 0;
		return $this->proxy_request( '/api/v1/observability/requests?limit=' . $limit . '&offset=' . $offset );
	}

	/**
	 * Proxy request to MCP service
	 *
	 * @param string $endpoint Endpoint path.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	private function proxy_request( string $endpoint ) {
		$url = $this->mcp_service_url . $endpoint;

		// Make HTTP request to MCP service.
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'service_unavailable',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to connect to observability service: %s', 'wyverncss' ),
					$response->get_error_message()
				),
				array( 'status' => 503 )
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		// Decode JSON response.
		$data = json_decode( $body, true );
		if ( null === $data ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response from observability service', 'wyverncss' ),
				array( 'status' => 500 )
			);
		}

		// Return response with original status code.
		return new WP_REST_Response( $data, $status_code );
	}

	/**
	 * Get period parameter args for REST routes
	 *
	 * @param string   $default_value Default period value.
	 * @param string[] $allowed_values Allowed period values.
	 * @return array<string, mixed> Period parameter configuration.
	 */
	private function get_period_args( string $default_value = '24h', array $allowed_values = array( '1h', '24h', '7d', '30d', '90d' ) ): array {
		return array(
			'period' => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => $default_value,
				'enum'              => $allowed_values,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Check if user has admin permission
	 *
	 * @return bool True if user is admin, false otherwise.
	 */
	public function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}
}
