<?php
/**
 * MCP Status REST Controller
 *
 * Provides MCP server status including built-in and external tools.
 *
 * @package WyvernCSS
 * @subpackage API\REST
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\API\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\MCP\ToolRegistry;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

/**
 * MCP_Status_Controller Class
 *
 * REST API endpoint for checking MCP server status.
 *
 * @since 1.0.0
 */
class MCP_Status_Controller extends WP_REST_Controller {

	use Controller_Helpers;

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wyverncss/v1';

	/**
	 * REST API base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'mcp/status';

	/**
	 * Tool Registry instance.
	 *
	 * @var ToolRegistry
	 */
	private ToolRegistry $tool_registry;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param ToolRegistry $tool_registry Tool Registry instance.
	 */
	public function __construct( ToolRegistry $tool_registry ) {
		$this->tool_registry = $tool_registry;
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
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
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Permission callback.
	 *
	 * MCP status endpoint requires authenticated users.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $_request REST request object (unused).
	 * @return bool True if user has permission, false otherwise.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function check_permission( WP_REST_Request $_request ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Allow any authenticated user to check MCP status.
		return is_user_logged_in();
	}

	/**
	 * Get MCP status.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $_request REST request object (unused).
	 * @return WP_REST_Response Response object.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function get_status( WP_REST_Request $_request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Get built-in tools count.
		$builtin_tools = $this->tool_registry->get_tools();
		$tools_count   = count( $builtin_tools );

		// Check if external service is configured.
		$license_key  = get_option( 'wyverncss_license_key', '' );
		$has_license  = ! empty( $license_key );
		$service_url  = get_option( 'wyverncss_cloud_service_url', '' );
		$has_external = $has_license && ! empty( $service_url );

		// Determine overall status.
		$is_online = $tools_count > 0; // Online if any built-in tools are available.

		// Determine source type.
		if ( $has_external && $tools_count > 0 ) {
			$source = 'hybrid';
		} elseif ( $tools_count > 0 ) {
			$source = 'builtin';
		} elseif ( $has_external ) {
			$source = 'external';
		} else {
			$source = 'none';
		}

		// Build response.
		$response_data = array(
			'status'               => $is_online ? 'online' : 'offline',
			'source'               => $source,
			'tools_count'          => $tools_count,
			'has_builtin_tools'    => $tools_count > 0,
			'has_external_service' => $has_external,
			'license_configured'   => $has_license,
			'tools'                => array_keys( $builtin_tools ),
			'message'              => $this->get_status_message( $is_online, $source, $tools_count ),
		);

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Get human-readable status message.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $is_online   Whether MCP is online.
	 * @param string $source      Source type (builtin, external, hybrid, none).
	 * @param int    $tools_count Number of available tools.
	 * @return string Status message.
	 */
	private function get_status_message( bool $is_online, string $source, int $tools_count ): string {
		if ( ! $is_online ) {
			return __( 'MCP server is offline. No tools available.', 'wyvern-ai-styling' );
		}

		switch ( $source ) {
			case 'hybrid':
				return sprintf(
					/* translators: %d: number of tools */
					__( 'MCP server is online with %d built-in tools. External service available for advanced features.', 'wyvern-ai-styling' ),
					$tools_count
				);

			case 'builtin':
				return sprintf(
					/* translators: %d: number of tools */
					__( 'MCP server is online with %d built-in tools.', 'wyvern-ai-styling' ),
					$tools_count
				);

			case 'external':
				return __( 'MCP server is online via external service.', 'wyvern-ai-styling' );

			default:
				return __( 'MCP server status unknown.', 'wyvern-ai-styling' );
		}
	}
}
