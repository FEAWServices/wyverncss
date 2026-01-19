<?php
/**
 * MCP Client Class
 *
 * HTTP client for calling external MCP Processing Service.
 *
 * @package WyvernCSS
 * @subpackage MCP
 * @since 2.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\Security\HMAC_Signer;
use WP_Error;

/**
 * MCP Client Class
 *
 * Handles communication with external MCP Processing Service at http://localhost:8001.
 * Features:
 * - HMAC-SHA256 request signing
 * - Retry logic with exponential backoff (3 attempts: 1s, 2s, 4s)
 * - 30-second timeout
 * - Circuit breaker integration
 * - Response caching (5-minute TTL)
 *
 * @since 2.0.0
 */
class MCP_Client {

	/**
	 * MCP Processing Service URL.
	 *
	 * @var string
	 */
	private string $service_url;

	/**
	 * API secret for HMAC signing.
	 *
	 * @var string
	 */
	private string $api_secret;

	/**
	 * Circuit breaker instance.
	 *
	 * @var MCP_Circuit_Breaker
	 */
	private MCP_Circuit_Breaker $circuit_breaker;

	/**
	 * HMAC signer instance.
	 *
	 * @var HMAC_Signer
	 */
	private HMAC_Signer $hmac_signer;

	/**
	 * Maximum retry attempts.
	 *
	 * @var int
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private const REQUEST_TIMEOUT = 30;

	/**
	 * Cache TTL for cacheable tools (5 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 300;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string                   $service_url      MCP Service URL (default from constant).
	 * @param string                   $api_secret       API secret for request signing.
	 * @param MCP_Circuit_Breaker|null $circuit_breaker  Circuit breaker instance (optional).
	 */
	public function __construct( string $service_url = '', string $api_secret = '', ?MCP_Circuit_Breaker $circuit_breaker = null ) {
		$this->service_url     = ! empty( $service_url ) ? $service_url : $this->get_default_service_url();
		$this->api_secret      = ! empty( $api_secret ) ? $api_secret : $this->get_default_api_secret();
		$this->circuit_breaker = $circuit_breaker ?? new MCP_Circuit_Breaker();
		$this->hmac_signer     = new HMAC_Signer( $this->api_secret );
	}

	/**
	 * Get default MCP Service URL from constants.
	 *
	 * @since 2.0.0
	 *
	 * @return string MCP Service URL.
	 */
	private function get_default_service_url(): string {
		if ( defined( 'WYVERNCSS_MCP_SERVICE_URL' ) ) {
			return WYVERNCSS_MCP_SERVICE_URL;
		}

		// Default to localhost in development.
		return 'http://localhost:8001';
	}

	/**
	 * Get default API secret from constants.
	 *
	 * @since 2.0.0
	 *
	 * @return string API secret.
	 */
	private function get_default_api_secret(): string {
		if ( defined( 'WYVERNCSS_MCP_SERVICE_API_SECRET' ) ) {
			return WYVERNCSS_MCP_SERVICE_API_SECRET;
		}

		return '';
	}

	/**
	 * Execute MCP tool on external service.
	 *
	 * Implements retry logic with exponential backoff and circuit breaker pattern.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $tool_name  Tool name to execute (e.g., 'wp_get_posts').
	 * @param array<string, mixed> $params     Tool parameters.
	 * @param array<string, mixed> $context    Request context (site_url, license_key, etc.).
	 * @return array<string, mixed>|WP_Error Tool result or error.
	 */
	public function execute( string $tool_name, array $params = array(), array $context = array() ): array|WP_Error {
		// Check circuit breaker first.
		if ( ! $this->circuit_breaker->is_available() ) {
			return new WP_Error(
				'mcp_service_unavailable',
				__( 'MCP service is temporarily unavailable. Please try again later.', 'wyvern-ai-styling' ),
				array(
					'code'        => 'SERVICE_UNREACHABLE',
					'retry_after' => $this->circuit_breaker->get_retry_after(),
				)
			);
		}

		// Check cache for read operations.
		if ( $this->is_cacheable_tool( $tool_name ) ) {
			$cached = $this->get_cached_response( $tool_name, $params );
			if ( null !== $cached ) {
				return $cached;
			}
		}

		$url = trailingslashit( $this->service_url ) . 'api/v1/mcp/execute';

		// Prepare request payload (JSON-RPC 2.0 format).
		$payload = array(
			'jsonrpc' => '2.0',
			'id'      => wp_generate_uuid4(),
			'method'  => $tool_name,
			'params'  => $params,
			'context' => array_merge(
				array(
					'site_url'   => get_site_url(),
					'wp_version' => get_bloginfo( 'version' ),
				),
				$context
			),
		);

		// Execute with retry logic.
		$result = $this->execute_with_retry( $url, $payload, self::MAX_RETRIES );

		if ( is_wp_error( $result ) ) {
			$this->circuit_breaker->record_failure();
			return $result;
		}

		// Success - record and cache.
		$this->circuit_breaker->record_success();

		if ( is_array( $result ) ) {
			$result['_request_signature'] = $this->get_cache_key( $tool_name, $params );
		}

		if ( $this->is_cacheable_tool( $tool_name ) ) {
			$this->cache_response( $tool_name, $params, $result );
		}

		return $result;
	}

	/**
	 * Perform a RESTful request against the MCP/Cloud service.
	 *
	 * Used by controllers that need to proxy CRUD-style operations to the
	 * remote service (e.g., bot management) instead of JSON-RPC tools.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $method HTTP method (GET, POST, PUT, DELETE, etc.).
	 * @param string               $path   Request path (e.g., '/api/v1/bots').
	 * @param array<string, mixed> $params Query parameters for GET or body payload for others.
	 * @return array<string, mixed>|WP_Error Array with 'status' and 'body' keys or WP_Error on failure.
	 */
	public function request( string $method, string $path, array $params = array() ) {
		$method = strtoupper( $method );
		$query  = 'GET' === $method ? $params : array();
		$url    = $this->build_request_url( $path, $query );

		$body_payload = 'GET' === $method ? array() : $params;
		$body_json    = wp_json_encode( $body_payload );
		if ( false === $body_json ) {
			return new WP_Error(
				'mcp_json_encode_failed',
				__( 'Failed to encode MCP request payload.', 'wyvern-ai-styling' )
			);
		}

		$args = array(
			'method'  => $method,
			'headers' => $this->build_signed_headers( $body_json ),
			'timeout' => self::REQUEST_TIMEOUT,
		);

		if ( 'GET' !== $method && ! empty( $body_payload ) ) {
			$args['body'] = $body_json;
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'mcp_network_error',
				sprintf(
					/* translators: %s: error message */
					__( 'MCP service network error: %s', 'wyvern-ai-styling' ),
					$response->get_error_message()
				),
				array( 'code' => 'SERVICE_UNREACHABLE' )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( null === $data && '' !== $body ) {
			return new WP_Error(
				'mcp_invalid_response',
				__( 'MCP service returned invalid JSON response.', 'wyvern-ai-styling' ),
				array( 'code' => 'INVALID_RESPONSE' )
			);
		}

		return array(
			'status' => (int) $status_code,
			'body'   => $data ?? array(),
		);
	}

	/**
	 * Execute request with retry logic and exponential backoff.
	 *
	 * Retries on 5xx errors and network failures. Backoff: 1s, 2s, 4s.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $url         Request URL.
	 * @param array<string, mixed> $payload     Request payload.
	 * @param int                  $max_retries Maximum retry attempts.
	 * @return array<string, mixed>|WP_Error Response or error.
	 */
	private function execute_with_retry( string $url, array $payload, int $max_retries ): array|WP_Error {
		$attempt = 0;

		while ( $attempt < $max_retries ) {
			++$attempt;

			$result = $this->send_request( $url, $payload );

			// Success - return immediately.
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}

			// Check if error is retryable.
			$error_code = $result->get_error_code();
			$error_data = $result->get_error_data();

			// Don't retry on client errors (4xx) or invalid responses.
			if ( in_array( $error_code, array( 'mcp_client_error', 'mcp_invalid_response' ), true ) ) {
				return $result;
			}

			// Last attempt - return error.
			if ( $attempt >= $max_retries ) {
				return $result;
			}

			// Exponential backoff: 1s, 2s, 4s.
			$backoff_seconds = (int) pow( 2, $attempt - 1 );
			sleep( $backoff_seconds );

			// Log retry if WP_DEBUG enabled.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'WyvernCSS MCP Client: Retry %d/%d after %ds (error: %s)',
						$attempt,
						$max_retries,
						$backoff_seconds,
						$error_code
					)
				);
			}
		}

		// Should never reach here, but return generic error just in case.
		return new WP_Error(
			'mcp_max_retries_exceeded',
			__( 'Maximum retry attempts exceeded for MCP service.', 'wyvern-ai-styling' )
		);
	}

	/**
	 * Send HTTP request to MCP service.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $url     Request URL.
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error Response or error.
	 */
	private function send_request( string $url, array $payload ): array|WP_Error {
		$body_json = wp_json_encode( $payload );
		if ( false === $body_json ) {
			return new WP_Error(
				'mcp_json_encode_failed',
				__( 'Failed to encode MCP request payload.', 'wyvern-ai-styling' )
			);
		}

		// Generate HMAC signature and prepare headers.
		$timestamp = time();
		$version   = defined( 'WYVERNCSS_VERSION' ) ? WYVERNCSS_VERSION : '2.0.0';
		$headers   = $this->hmac_signer->sign_request( $timestamp, get_site_url(), $body_json, $version );

		// Send request using WordPress HTTP API.
		$response = wp_remote_post(
			$url,
			array(
				'body'    => $body_json,
				'headers' => $headers,
				'timeout' => self::REQUEST_TIMEOUT,
			)
		);

		// Handle network errors.
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'mcp_network_error',
				sprintf(
					/* translators: %s: error message */
					__( 'MCP service network error: %s', 'wyvern-ai-styling' ),
					$response->get_error_message()
				),
				array( 'code' => 'SERVICE_UNREACHABLE' )
			);
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Parse JSON response.
		$data = json_decode( $response_body, true );
		if ( null === $data ) {
			return new WP_Error(
				'mcp_invalid_response',
				__( 'MCP service returned invalid JSON response.', 'wyvern-ai-styling' ),
				array( 'code' => 'INVALID_RESPONSE' )
			);
		}

		// Handle HTTP errors.
		if ( is_int( $status_code ) && $status_code >= 400 ) {
			return $this->handle_error_response( $status_code, $data );
		}

		// Validate JSON-RPC response format.
		if ( ! isset( $data['jsonrpc'] ) || '2.0' !== $data['jsonrpc'] ) {
			return new WP_Error(
				'mcp_invalid_response',
				__( 'Invalid JSON-RPC 2.0 response format.', 'wyvern-ai-styling' ),
				array( 'code' => 'INVALID_RESPONSE' )
			);
		}

		// Check for JSON-RPC error.
		if ( isset( $data['error'] ) ) {
			return new WP_Error(
				'mcp_tool_error',
				$data['error']['message'] ?? __( 'MCP tool execution failed.', 'wyvern-ai-styling' ),
				array(
					'code'    => $data['error']['code'] ?? -32603,
					'details' => $data['error']['data'] ?? array(),
				)
			);
		}

		// Return successful result.
		if ( ! isset( $data['result'] ) ) {
			return new WP_Error(
				'mcp_invalid_response',
				__( 'MCP response missing result field.', 'wyvern-ai-styling' ),
				array( 'code' => 'INVALID_RESPONSE' )
			);
		}

		return is_array( $data['result'] ) ? $data['result'] : array( 'data' => $data['result'] );
	}

	/**
	 * Build fully-qualified request URL.
	 *
	 * @param string               $path  Request path.
	 * @param array<string, mixed> $query Optional query arguments.
	 * @return string
	 */
	private function build_request_url( string $path, array $query = array() ): string {
		$base = trailingslashit( $this->service_url );
		$path = ltrim( $path, '/' );
		$url  = $base . $path;

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		return $url;
	}

	/**
	 * Prepare signed headers for REST-style requests.
	 *
	 * @param string $body_json JSON-encoded request body.
	 * @return array<string, string>
	 */
	private function build_signed_headers( string $body_json ): array {
		$timestamp = time();
		$version   = defined( 'WYVERNCSS_VERSION' ) ? WYVERNCSS_VERSION : '2.0.0';
		return $this->hmac_signer->sign_request( $timestamp, get_site_url(), $body_json, $version );
	}

	/**
	 * Handle error response from MCP service.
	 *
	 * @since 2.0.0
	 *
	 * @param int                  $status_code HTTP status code.
	 * @param array<string, mixed> $data        Response data.
	 * @return WP_Error Error object.
	 */
	private function handle_error_response( int $status_code, array $data ): WP_Error {
		// Client errors (4xx) - don't retry.
		if ( $status_code >= 400 && $status_code < 500 ) {
			$message = $data['error']['message'] ?? __( 'MCP client error.', 'wyvern-ai-styling' );
			return new WP_Error(
				'mcp_client_error',
				$message,
				array(
					'code'        => $data['error']['code'] ?? 'CLIENT_ERROR',
					'status_code' => $status_code,
				)
			);
		}

		// Server errors (5xx) - retryable.
		$message = $data['error']['message'] ?? __( 'MCP server error.', 'wyvern-ai-styling' );
		return new WP_Error(
			'mcp_server_error',
			$message,
			array(
				'code'        => $data['error']['code'] ?? 'SERVER_ERROR',
				'status_code' => $status_code,
			)
		);
	}

	/**
	 * Check if tool supports caching.
	 *
	 * Read operations are cacheable, write operations are not.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tool_name Tool name.
	 * @return bool True if cacheable, false otherwise.
	 */
	private function is_cacheable_tool( string $tool_name ): bool {
		$read_operations = array(
			'wp_get_posts',
			'wp_get_post',
			'wp_get_pages',
			'wp_get_page',
			'wp_get_users',
			'wp_get_user',
			'wp_get_comments',
			'wp_get_categories',
			'wp_get_site_info',
			'wp_get_option',
			'wp_search_content',
			'wp_get_site_stats',
		);

		return in_array( $tool_name, $read_operations, true );
	}

	/**
	 * Get cached response for tool execution.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $tool_name Tool name.
	 * @param array<string, mixed> $params    Tool parameters.
	 * @return array<string, mixed>|null Cached response or null if not found.
	 */
	private function get_cached_response( string $tool_name, array $params ): ?array {
		$cache_key = $this->get_cache_key( $tool_name, $params );
		$cached    = get_transient( $cache_key );

		if ( false === $cached || ! is_array( $cached ) ) {
			return null;
		}

		return $cached;
	}

	/**
	 * Cache response for tool execution.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $tool_name Tool name.
	 * @param array<string, mixed> $params    Tool parameters.
	 * @param array<string, mixed> $result    Tool result.
	 * @return void
	 */
	private function cache_response( string $tool_name, array $params, array $result ): void {
		$cache_key = $this->get_cache_key( $tool_name, $params );
		set_transient( $cache_key, $result, self::CACHE_TTL );
	}

	/**
	 * Generate cache key for tool execution.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $tool_name Tool name.
	 * @param array<string, mixed> $params    Tool parameters.
	 * @return string Cache key.
	 */
	private function get_cache_key( string $tool_name, array $params ): string {
		$serialized = wp_json_encode( $params );
		$hash       = md5( false !== $serialized ? $serialized : '' );
		return 'wyverncss_mcp_' . $tool_name . '_' . $hash;
	}
}
