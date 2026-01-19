<?php
/**
 * Debug REST Controller
 *
 * Provides comprehensive debugging information for troubleshooting.
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
 * Debug_Controller Class
 *
 * REST API endpoint for system debugging and diagnostics.
 *
 * @since 1.0.0
 */
class Debug_Controller extends WP_REST_Controller {

	use Controller_Helpers;
	use RestControllerHelpersTrait;

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
	protected $rest_base = 'debug';

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
					'callback'            => array( $this, 'get_debug_info' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Permission callback.
	 *
	 * Debug endpoint requires admin capabilities.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $_request REST request object (unused).
	 * @return bool True if user has permission, false otherwise.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function check_permission( WP_REST_Request $_request ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get comprehensive debug information.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $_request REST request object (unused).
	 * @return WP_REST_Response Response object.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function get_debug_info( WP_REST_Request $_request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$debug_info = array(
			'system'      => $this->get_system_info(),
			'mcp'         => $this->get_mcp_debug_info(),
			'permissions' => $this->get_permissions_info(),
			'rest_api'    => $this->get_rest_api_info(),
			'errors'      => $this->get_recent_errors(),
			'constants'   => $this->get_wp_constants(),
		);

		return new WP_REST_Response( $debug_info, 200 );
	}

	/**
	 * Get system information.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> System information.
	 */
	private function get_system_info(): array {
		global $wp_version;

		return array(
			'php_version'        => PHP_VERSION,
			'wordpress_version'  => $wp_version,
			'plugin_version'     => defined( 'WYVERNCSS_VERSION' ) ? WYVERNCSS_VERSION : 'unknown',
			'memory_limit'       => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'wp_debug'           => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log'       => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'active_theme'       => wp_get_theme()->get( 'Name' ),
		);
	}

	/**
	 * Get MCP debug information.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> MCP debug information.
	 */
	private function get_mcp_debug_info(): array {
		$tools       = $this->tool_registry->get_tools();
		$tools_count = count( $tools );

		$tool_details = array();
		foreach ( $tools as $name => $tool ) {
			$tool_details[] = array(
				'name'        => $name,
				'description' => $tool->get_description(),
				'class'       => get_class( $tool ),
			);
		}

		return array(
			'tools_count' => $tools_count,
			'tools'       => $tool_details,
		);
	}

	/**
	 * Get file permissions information.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Permissions information.
	 */
	private function get_permissions_info(): array {
		$tools_dir   = WYVERNCSS_PLUGIN_DIR . 'includes/MCP/Tools/';
		$permissions = array();

		if ( is_dir( $tools_dir . 'Content/' ) ) {
			$files = scandir( $tools_dir . 'Content/' );
			if ( false !== $files ) {
				foreach ( $files as $file ) {
					if ( '.php' === substr( $file, -4 ) ) {
						$filepath             = $tools_dir . 'Content/' . $file;
						$perms                = fileperms( $filepath );
						$permissions[ $file ] = array(
							'readable'    => is_readable( $filepath ),
							'permissions' => substr( sprintf( '%o', $perms ), -4 ),
						);
					}
				}
			}
		}

		return $permissions;
	}

	/**
	 * Get REST API information.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> REST API information.
	 */
	private function get_rest_api_info(): array {
		$rest_server = rest_get_server();
		$routes      = $rest_server->get_routes();

		$wyverncss_routes = array();
		foreach ( $routes as $route => $endpoints ) {
			if ( strpos( $route, '/wyverncss/' ) === 0 ) {
				$wyverncss_routes[] = $route;
			}
		}

		return array(
			'routes' => $wyverncss_routes,
			'count'  => count( $wyverncss_routes ),
		);
	}

	/**
	 * Get recent WordPress errors from debug log.
	 *
	 * Delegates to trait method to eliminate code duplication.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> Recent errors.
	 */
	private function get_recent_errors(): array {
		return $this->get_recent_errors_from_log();
	}

	/**
	 * Get WordPress constants.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> WordPress constants.
	 */
	private function get_wp_constants(): array {
		$constants = array( 'WP_DEBUG', 'WP_DEBUG_LOG', 'WYVERNCSS_VERSION' );
		$values    = array();

		foreach ( $constants as $constant ) {
			$values[ $constant ] = defined( $constant ) ? constant( $constant ) : 'Not defined';
		}

		return $values;
	}
}
