<?php
/**
 * Health Check REST Controller
 *
 * Provides health status for external services (Admin Service and MCP Service).
 *
 * @package WyvernCSS
 * @subpackage API\REST
 * @since 2.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\API\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\MCP\MCP_Circuit_Breaker;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Health Controller Class
 *
 * REST API endpoint for checking service health status.
 *
 * @since 2.0.0
 */
class Health_Controller extends WP_REST_Controller {

	use Controller_Helpers;

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wyvernpress/v1';

	/**
	 * REST API base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'health';

	/**
	 * Circuit Breaker instance.
	 *
	 * @var MCP_Circuit_Breaker
	 */
	private MCP_Circuit_Breaker $circuit_breaker;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param MCP_Circuit_Breaker $circuit_breaker Circuit Breaker instance.
	 */
	public function __construct( MCP_Circuit_Breaker $circuit_breaker ) {
		$this->circuit_breaker = $circuit_breaker;
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'check_health' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Permission callback.
	 *
	 * Health check endpoint is public for monitoring purposes.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $_request REST request object (unused).
	 * @return bool True if user has permission, false otherwise.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function check_permission( WP_REST_Request $_request ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return true; // Public endpoint for health monitoring.
	}

	/**
	 * Check health of all services.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $_request REST request object (unused).
	 * @return WP_REST_Response Health check response.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function check_health( WP_REST_Request $_request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$status = array(
			'plugin'          => 'ok',
			'admin_service'   => $this->check_admin_service(),
			'mcp_service'     => $this->check_mcp_service(),
			'pattern_library' => 'ok',
		);

		// Determine overall health.
		$all_ok = true;
		foreach ( $status as $service => $service_status ) {
			if ( 'ok' !== $service_status ) {
				$all_ok = false;
				break;
			}
		}

		return new WP_REST_Response(
			array(
				'status'    => $all_ok ? 'healthy' : 'degraded',
				'services'  => $status,
				'timestamp' => current_time( 'mysql' ),
			),
			200
		);
	}

	/**
	 * Check Admin Service health.
	 *
	 * @since 2.0.0
	 *
	 * @return string Service status (ok, not_configured, unreachable).
	 */
	private function check_admin_service(): string {
		$url = defined( 'WYVERNCSS_ADMIN_SERVICE_URL' ) ? WYVERNCSS_ADMIN_SERVICE_URL : '';

		return $this->check_service_health( $url );
	}

	/**
	 * Check MCP Service health.
	 *
	 * @since 2.0.0
	 *
	 * @return string Service status (ok, not_configured, unreachable, circuit_open).
	 */
	private function check_mcp_service(): string {
		// Check circuit breaker first.
		if ( ! $this->circuit_breaker->is_available() ) {
			return 'circuit_open';
		}

		$url = defined( 'WYVERNCSS_MCP_SERVICE_URL' ) ? WYVERNCSS_MCP_SERVICE_URL : '';

		return $this->check_service_health( $url );
	}
}
