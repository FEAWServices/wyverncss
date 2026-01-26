<?php
/**
 * MCP Transport Layer
 *
 * Handles HTTP/SSE transport for Model Context Protocol.
 * Implements JSON-RPC 2.0 protocol over WordPress REST API.
 *
 * @package WyvernCSS\MCP
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\Security\Rate_Limit_Config;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Transport Class
 *
 * Main MCP transport layer implementation.
 */
class Transport {

	/**
	 * JSON-RPC parser instance
	 *
	 * @var JSONRPCParser
	 */
	private JSONRPCParser $parser;

	/**
	 * JSON-RPC formatter instance
	 *
	 * @var JSONRPCFormatter
	 */
	private JSONRPCFormatter $formatter;

	/**
	 * SSE handler instance
	 *
	 * @var SSEHandler
	 */
	private SSEHandler $sse_handler;

	/**
	 * Cache manager instance
	 *
	 * @var CacheManager
	 */
	private CacheManager $cache;

	/**
	 * Tool registry instance (kept for backward compatibility with REST controllers)
	 *
	 * @var ToolRegistry
	 */
	private ToolRegistry $tool_registry;

	/**
	 * MCP client for external service calls
	 *
	 * @var MCP_Client|null
	 */
	private ?MCP_Client $mcp_client = null;

	/**
	 * Circuit breaker for service health monitoring
	 *
	 * @var MCP_Circuit_Breaker|null
	 */
	private ?MCP_Circuit_Breaker $circuit_breaker = null;

	/**
	 * Constructor
	 *
	 * @param MCP_Client|null          $mcp_client      MCP client instance.
	 * @param MCP_Circuit_Breaker|null $circuit_breaker Circuit breaker instance (optional).
	 */
	public function __construct( ?MCP_Client $mcp_client = null, ?MCP_Circuit_Breaker $circuit_breaker = null ) {
		$this->parser        = new JSONRPCParser();
		$this->formatter     = new JSONRPCFormatter();
		$this->sse_handler   = new SSEHandler();
		$this->cache         = new CacheManager();
		$this->tool_registry = new ToolRegistry();
		$this->tool_registry->auto_discover_tools(); // Auto-discover built-in tools.
		$this->mcp_client      = $mcp_client;
		$this->circuit_breaker = $circuit_breaker ?? new MCP_Circuit_Breaker();
	}

	/**
	 * Get tool registry instance
	 *
	 * Returns the tool registry with auto-discovered built-in tools.
	 * Tools can execute locally (built-in) or via external MCP service.
	 *
	 * @return ToolRegistry Tool registry instance with built-in tools.
	 */
	public function get_tool_registry(): ToolRegistry {
		return $this->tool_registry;
	}


	/**
	 * Initialize transport layer
	 *
	 * Registers REST API routes
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'convert_json_errors' ), 10, 3 );
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Tools list endpoint.
		register_rest_route(
			'wyverncss/v1',
			'/mcp/tools/list',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_tools_list' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		// Tool execution endpoint.
		register_rest_route(
			'wyverncss/v1',
			'/mcp/tools/call',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_tool_call' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		// Session management endpoint.
		register_rest_route(
			'wyverncss/v1',
			'/mcp/session',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_session' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		// SSE endpoint.
		register_rest_route(
			'wyverncss/v1',
			'/mcp/sse',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_sse' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Permission callback for all MCP endpoints
	 *
	 * @return bool|WP_Error True if user has permission, WP_Error otherwise.
	 */
	public function permission_callback() {
		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'authentication_failed',
				__( 'You must be logged in to access this endpoint.', 'wyverncss' ),
				array( 'status' => 401 )
			);
		}

		// Check basic read capability.
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error(
				'permission_denied',
				__( 'You do not have permission to access this endpoint.', 'wyverncss' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle tools list request
	 *
	 * Returns list of available MCP tools (built-in + external if available).
	 *
	 * @param WP_REST_Request $_request REST request object (unused).
	 * @return WP_REST_Response|WP_Error Response object or error.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function handle_tools_list( WP_REST_Request $_request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Get built-in tools from registry.
		$builtin_tools = $this->tool_registry->get_tools_list();

		// Determine source.
		$has_license = ! empty( get_option( 'wyverncss_license_key', '' ) );
		$source      = count( $builtin_tools ) > 0 ? 'hybrid' : 'none';
		if ( $has_license && null !== $this->mcp_client ) {
			$source = 'hybrid';
		} elseif ( count( $builtin_tools ) > 0 ) {
			$source = 'builtin';
		}

		$result = array(
			'tools'        => $builtin_tools,
			'count'        => count( $builtin_tools ),
			'source'       => $source,
			'has_external' => $has_license && null !== $this->mcp_client,
		);

		return new WP_REST_Response(
			$this->formatter->format_success( $result, 'tools-list' ),
			200
		);
	}

	/**
	 * Handle tool call request
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function handle_tool_call( WP_REST_Request $request ) {
		// Parse JSON-RPC request.
		$body   = $request->get_body();
		$parsed = $this->parser->parse( $body );

		if ( is_wp_error( $parsed ) ) {
			return new WP_REST_Response(
				$this->formatter->format_wp_error( $parsed ),
				400
			);
		}

		$request_id = $this->parser->get_id( $parsed );
		$method     = $this->parser->get_method( $parsed );
		$params     = $this->parser->get_params( $parsed );

		// Check rate limits.
		$rate_limit = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit ) ) {
			return new WP_REST_Response(
				$this->formatter->format_wp_error( $rate_limit, $request_id ),
				429
			);
		}

		// Verify nonce for same-site requests.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! empty( $nonce ) && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response(
				$this->formatter->format_error(
					-32002,
					__( 'Invalid security token.', 'wyverncss' ),
					null,
					$request_id
				),
				403
			);
		}

		// Execute tool.
		// Ensure request_id is not null before passing.
		$safe_request_id = $request_id ?? '';
		$result          = $this->execute_tool( $params, $safe_request_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				$this->formatter->format_wp_error( $result, $request_id ),
				400
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Execute MCP tool
	 *
	 * Hybrid execution: Try local built-in tools first, then fall back to external service.
	 *
	 * @param array<string,mixed> $params Tool parameters.
	 * @param string|int          $request_id Request ID.
	 * @return array<string,mixed>|WP_Error Tool result or error.
	 */
	private function execute_tool( array $params, $request_id ) {
		// Extract tool name.
		$tool_name = sanitize_text_field( $params['name'] ?? '' );

		if ( empty( $tool_name ) ) {
			return new WP_Error(
				'invalid_params',
				__( 'Tool name is required.', 'wyverncss' ),
				array(
					'code' => -32602,
				)
			);
		}

		$arguments = $params['arguments'] ?? array();

		// HYBRID ARCHITECTURE: Try local tool registry first.
		if ( $this->tool_registry->has_tool( $tool_name ) ) {
			return $this->execute_tool_locally_via_registry( $tool_name, $arguments, $request_id );
		}

		// If tool not found locally, try external MCP service.
		if ( null !== $this->mcp_client ) {
			$license_key = get_option( 'wyverncss_license_key', '' );
			if ( ! empty( $license_key ) ) {
				return $this->execute_tool_via_service( $tool_name, $arguments, $request_id );
			}
		}

		// No local tool and no external service available.
		return new WP_Error(
			'tool_not_found',
			sprintf(
				/* translators: %s: tool name */
				__( 'Tool "%s" not found. Tool is not available locally and no external service is configured.', 'wyverncss' ),
				$tool_name
			),
			array(
				'code' => -32601,
			)
		);
	}

	/**
	 * Execute tool locally via tool registry.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $tool_name  Tool name.
	 * @param array<string, mixed> $arguments  Tool arguments.
	 * @param string|int           $request_id Request ID.
	 * @return array<string,mixed>|WP_Error Tool result or error.
	 */
	private function execute_tool_locally_via_registry( string $tool_name, array $arguments, $request_id ) {
		// Check cache first (for read operations).
		$cache_key = $this->generate_cache_key( $tool_name, $arguments );
		$cached    = $this->cache->get( $tool_name, $arguments );

		if ( false !== $cached ) {
			return $this->formatter->format_success( $cached, $request_id );
		}

		// Execute tool via registry (handles validation, capabilities, execution).
		$result = $this->tool_registry->call_tool( $tool_name, $arguments );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Cache result if tool has a cache TTL.
		$cache_ttl = $this->tool_registry->get_tool_cache_ttl( $tool_name );
		if ( $cache_ttl > 0 ) {
			$this->cache->set( $tool_name, $arguments, $result, $cache_ttl );
		}

		return $this->formatter->format_success( $result, $request_id );
	}

	/**
	 * Execute tool via external MCP service.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $tool_name  Tool name.
	 * @param array<string, mixed> $arguments  Tool arguments.
	 * @param string|int           $request_id Request ID.
	 * @return array<string,mixed>|WP_Error Tool result or error.
	 */
	private function execute_tool_via_service( string $tool_name, array $arguments, $request_id ) {
		// Check circuit breaker before making request.
		if ( null !== $this->circuit_breaker && ! $this->circuit_breaker->is_available() ) {
			return new WP_Error(
				'service_unavailable',
				sprintf(
					/* translators: %d: minutes until retry */
					__( 'MCP service is temporarily unavailable. Please try again in %d minutes.', 'wyverncss' ),
					(int) ceil( $this->circuit_breaker->get_retry_after() / 60 )
				),
				array(
					'code'        => -32001,
					'retry_after' => $this->circuit_breaker->get_retry_after(),
				)
			);
		}

		// Prepare context for external service.
		$context = array(
			'user_id'           => get_current_user_id(),
			'user_capabilities' => $this->get_user_capabilities(),
		);

		// Check cache (managed by MCP_Client internally, but we also check here for consistency).
		$cache_key = $this->generate_cache_key( $tool_name, $arguments );
		$cached    = $this->cache->get( $tool_name, $arguments );

		if ( false !== $cached ) {
			return $this->formatter->format_success( $cached, $request_id );
		}

		// Null safety check (should never happen as we checked earlier, but required for PHPStan).
		if ( null === $this->mcp_client ) {
			return new WP_Error(
				'mcp_client_not_configured',
				__( 'MCP client not configured. External service required.', 'wyverncss' ),
				array(
					'code' => -32001,
				)
			);
		}

		$license_key = get_option( 'wyverncss_license_key', '' );
		if ( empty( $license_key ) ) {
			return $this->execute_tool_locally_and_format( $tool_name, $arguments, $request_id );
		}

		$result = $this->mcp_client->execute( $tool_name, $arguments, $context );

		if ( is_wp_error( $result ) ) {
			// Record failure in circuit breaker.
			if ( null !== $this->circuit_breaker ) {
				$this->circuit_breaker->record_failure();
			}

			if ( $this->should_use_local_executor( $result ) ) {
				return $this->execute_tool_locally_and_format( $tool_name, $arguments, $request_id );
			}

			return $result;
		}

		// Record success in circuit breaker.
		if ( null !== $this->circuit_breaker ) {
			$this->circuit_breaker->record_success();
		}

		// Cache result (5 minutes).
		$this->cache->set( $tool_name, $arguments, $result, 300 );

		return $this->formatter->format_success( $result, $request_id );
	}

	/**
	 * Get current user capabilities.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, string> Array of capability names.
	 */
	private function get_user_capabilities(): array {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return array();
		}

		return array_keys( $user->allcaps );
	}

	/**
	 * Generate cache key for tool execution.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $tool_name Tool name.
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return string Cache key.
	 */
	private function generate_cache_key( string $tool_name, array $arguments ): string {
		$serialized = wp_json_encode( $arguments );
		$hash       = md5( false !== $serialized ? $serialized : '' );
		return 'mcp_tool_' . $tool_name . '_' . $hash;
	}

	/**
	 * Determine if the local executor should handle the request.
	 *
	 * @param WP_Error $error Error returned by MCP client.
	 * @return bool True if we should fallback to local execution.
	 */
	private function should_use_local_executor( WP_Error $error ): bool {
		return in_array(
			$error->get_error_code(),
			array(
				'mcp_client_not_configured',
				'mcp_network_error',
				'mcp_service_unavailable',
			),
			true
		);
	}

	/**
	 * Execute supported tools locally when the MCP service is unavailable.
	 *
	 * @param string               $tool_name Tool name.
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|WP_Error Tool result or error.
	 */
	private function execute_tool_locally( string $tool_name, array $arguments ) {
		switch ( $tool_name ) {
			case 'wp_get_posts':
				$post_args = array(
					'posts_per_page' => isset( $arguments['posts_per_page'] ) ? (int) $arguments['posts_per_page'] : 5,
					'post_status'    => sanitize_key( $arguments['post_status'] ?? 'publish' ),
					'paged'          => isset( $arguments['paged'] ) ? (int) $arguments['paged'] : 1,
					'no_found_rows'  => true,
				);
				$query     = new \WP_Query( $post_args );
				$posts     = array_map(
					static function ( $post ) {
						/**
						 * Type assertion: WP_Query always returns WP_Post objects in posts array.
						 *
						 * @var \WP_Post $post
						 */
						return array(
							'ID'           => $post->ID,
							'post_title'   => $post->post_title,
							'post_excerpt' => $post->post_excerpt,
						);
					},
					$query->posts
				);

				return array(
					'posts' => $posts,
					'total' => count( $posts ),
				);

			case 'wp_create_post':
				if ( ! current_user_can( 'publish_posts' ) ) {
					return new WP_Error(
						'permission_denied',
						__( 'You do not have permission to create posts.', 'wyverncss' ),
						array( 'code' => -32002 )
					);
				}

				$title = sanitize_text_field( $arguments['title'] ?? '' );
				if ( '' === $title ) {
					return new WP_Error(
						'invalid_params',
						__( 'Post title is required.', 'wyverncss' ),
						array( 'code' => -32602 )
					);
				}

				$postarr = array(
					'post_title'   => $title,
					'post_content' => wp_kses_post( $arguments['content'] ?? '' ),
					'post_status'  => sanitize_key( $arguments['status'] ?? 'draft' ),
				);

				$post_id = wp_insert_post( $postarr, true );

				if ( is_wp_error( $post_id ) ) {
					return new WP_Error(
						'internal_error',
						__( 'Failed to create post.', 'wyverncss' ),
						array(
							'code'    => -32603,
							'details' => $post_id->get_error_message(),
						)
					);
				}

				return array(
					'post_id' => $post_id,
					'status'  => get_post_status( $post_id ),
				);

			default:
				return new WP_Error(
					'method_not_found',
					sprintf(
						/* translators: %s: tool name */
						__( 'Method "%s" not found.', 'wyverncss' ),
						$tool_name
					),
					array( 'code' => -32601 )
				);
		}
	}

	/**
	 * Execute tool locally and format the JSON-RPC response.
	 *
	 * @param string               $tool_name Tool name.
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @param string|int           $request_id Request ID.
	 * @return array<string, mixed>|WP_Error Formatted response or error.
	 */
	private function execute_tool_locally_and_format( string $tool_name, array $arguments, $request_id ) {
		$local_result = $this->execute_tool_locally( $tool_name, $arguments );

		if ( is_wp_error( $local_result ) ) {
			return $local_result;
		}

		$this->cache->set( $tool_name, $arguments, $local_result, 300 );

		return $this->formatter->format_success( $local_result, $request_id );
	}


	/**
	 * Check rate limits
	 *
	 * @return true|WP_Error True if within limits, WP_Error otherwise.
	 */
	public function check_rate_limit() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return true; // No rate limiting for non-logged in users (they'll fail auth anyway).
		}

		// Get user tier (default to free).
		$tier = get_user_meta( $user_id, 'wyverncss_tier', true ) ? get_user_meta( $user_id, 'wyverncss_tier', true ) : 'free';

		// Get rate limits from configuration.
		$tier_limits = Rate_Limit_Config::get_limits_for_tier( $tier );

		// Check hourly limit.
		$hourly_key   = "wyverncss_mcp_rate_limit_hourly_{$user_id}";
		$hourly_count = (int) get_transient( $hourly_key );

		if ( $hourly_count >= $tier_limits['hourly'] ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Hourly rate limit exceeded.', 'wyverncss' ),
				array(
					'code'        => -32003,
					'retry_after' => 3600,
				)
			);
		}

		// Check burst limit (per minute).
		$burst_key   = "wyverncss_mcp_rate_limit_burst_{$user_id}";
		$burst_count = (int) get_transient( $burst_key );

		if ( $burst_count >= $tier_limits['burst'] ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Burst rate limit exceeded.', 'wyverncss' ),
				array(
					'code'        => -32003,
					'retry_after' => 60,
				)
			);
		}

		// Increment counters.
		set_transient( $hourly_key, $hourly_count + 1, HOUR_IN_SECONDS );
		set_transient( $burst_key, $burst_count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Handle session request
	 *
	 * @param WP_REST_Request $_request REST request object (unused).
	 * @return WP_REST_Response|WP_Error Response object or error.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function handle_session( WP_REST_Request $_request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Mock session creation.
		$session_data = array(
			'session_id' => wp_generate_uuid4(),
			'expires_at' => gmdate( 'c', time() + 3600 ),
		);

		return new WP_REST_Response( $session_data, 201 );
	}

	/**
	 * Handle SSE request
	 *
	 * @param WP_REST_Request $_request REST request object (unused).
	 * @return WP_REST_Response|WP_Error Response object or error.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function handle_sse( WP_REST_Request $_request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Return SSE formatted data.
		$data = $this->sse_handler->handle_connection();

		$response = new WP_REST_Response( $data, 200 );
		$response->set_headers( array( 'Content-Type' => 'text/event-stream' ) );

		return $response;
	}

	/**
	 * Convert WordPress JSON errors to JSON-RPC format
	 *
	 * @param WP_REST_Response|WP_Error $response Response object.
	 * @param array<string,mixed>       $handler Route handler.
	 * @param WP_REST_Request           $request Request object.
	 * @return WP_REST_Response|WP_Error Modified response.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function convert_json_errors( $response, $handler, WP_REST_Request $request ) {
		// Only handle our MCP endpoints.
		$route = $request->get_route();
		if ( strpos( $route, '/wyverncss/v1/mcp/' ) !== 0 ) {
			return $response;
		}

		// Check if this is a WordPress JSON parse error.
		if ( is_wp_error( $response ) && 'rest_invalid_json' === $response->get_error_code() ) {
			// Convert to JSON-RPC error format.
			$jsonrpc_error = $this->formatter->format_error(
				-32700,
				__( 'Parse error: Invalid JSON was received', 'wyverncss' ),
				null,
				null
			);

			return new WP_REST_Response( $jsonrpc_error, 400 );
		}

		return $response;
	}
}
