<?php
/**
 * Bot Frontend API
 *
 * Public-facing REST API for the bot chat widget.
 * Provides endpoints for bot configuration and chat messaging without authentication.
 *
 * @package    WyvernCSS
 * @subpackage WyvernCSS/includes/Public
 * @since      1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WyvernCSS\MCP\MCP_Client;

/**
 * Bot Frontend API Class
 *
 * Provides REST endpoints for the public chat widget.
 * No authentication required but rate limited.
 *
 * Security features:
 * - Rate limiting: 10 requests/minute per IP
 * - Input sanitization: HTML stripped, max 4000 chars
 * - Output filtering: Only public bot info exposed
 *
 * @since 1.0.0
 */
class Bot_Frontend_API extends WP_REST_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wyverncss/v1';

	/**
	 * REST API base path.
	 *
	 * @var string
	 */
	protected $rest_base = 'public';

	/**
	 * MCP Client instance for cloud service communication.
	 *
	 * @var MCP_Client
	 */
	private MCP_Client $mcp_client;

	/**
	 * Rate limit: requests per minute per IP.
	 *
	 * @var int
	 */
	private const RATE_LIMIT_PER_MINUTE = 10;

	/**
	 * Rate limit: requests per hour per IP.
	 *
	 * @var int
	 */
	private const RATE_LIMIT_PER_HOUR = 50;

	/**
	 * Maximum message length in characters.
	 *
	 * @var int
	 */
	private const MAX_MESSAGE_LENGTH = 4000;

	/**
	 * Rate limit transient prefix.
	 *
	 * @var string
	 */
	private const RATE_LIMIT_PREFIX = 'wyverncss_rate_limit_';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param MCP_Client $mcp_client MCP Client instance for cloud service communication.
	 */
	public function __construct( MCP_Client $mcp_client ) {
		$this->mcp_client = $mcp_client;
	}

	/**
	 * Register REST API routes.
	 *
	 * These endpoints are INTENTIONALLY PUBLIC for the frontend chat widget.
	 * Security is enforced through:
	 * - Rate limiting: 10 requests/minute, 50 requests/hour per IP
	 * - Input sanitization: HTML stripped, max 4000 characters
	 * - Output filtering: Only public bot info exposed (never system prompts)
	 * - Validation: All inputs validated before processing
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		/*
		 * GET /public/bot/{id} - Get bot config (public info only).
		 *
		 * This endpoint is intentionally public to allow the frontend chat
		 * widget to load bot configuration without authentication.
		 * Only safe, public data is returned (name, description, avatar).
		 * System prompts and internal settings are NEVER exposed.
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bot/(?P<id>[a-zA-Z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_bot_config' ),
				'permission_callback' => '__return_true', // Intentionally public - see class docblock for security measures.
				'args'                => array(
					'id' => array(
						'description'       => __( 'Bot ID or slug', 'wyverncss' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && preg_match( '/^[a-zA-Z0-9-]+$/', $param );
						},
					),
				),
			)
		);

		/*
		 * POST /public/chat - Send chat message.
		 *
		 * This endpoint is intentionally public to allow unauthenticated
		 * site visitors to use the chat widget. Security is enforced through
		 * rate limiting (10/min, 50/hour per IP), input sanitization,
		 * and message length limits (4000 chars max).
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => '__return_true', // Intentionally public - see class docblock for security measures.
				'args'                => array(
					'bot_id'     => array(
						'description'       => __( 'Bot ID or slug', 'wyverncss' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && preg_match( '/^[a-zA-Z0-9-]+$/', $param );
						},
					),
					'message'    => array(
						'description'       => __( 'Chat message', 'wyverncss' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_message' ),
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && strlen( $param ) > 0;
						},
					),
					'session_id' => array(
						'description'       => __( 'Session ID for conversation tracking', 'wyverncss' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Get public bot configuration.
	 *
	 * Returns ONLY public information: name, description, avatar.
	 * NEVER exposes system_prompt or internal settings.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error REST response or error.
	 */
	public function get_bot_config( WP_REST_Request $request ) {
		$bot_id = $request->get_param( 'id' );

		// Get bot from cloud service.
		$response = $this->mcp_client->request(
			'GET',
			'/api/v1/bots/' . $bot_id
		);

		if ( is_wp_error( $response ) ) {
			return $this->handle_cloud_service_error( $response );
		}

		$bot_data = $response['body'] ?? array();

		// Filter to only public fields.
		$public_config = $this->filter_public_bot_fields( $bot_data );

		return new WP_REST_Response( $public_config, 200 );
	}

	/**
	 * Handle chat message submission.
	 *
	 * Processes chat messages with:
	 * - Rate limiting (10 req/min per IP, 50 req/hour per session)
	 * - Input sanitization (HTML stripped, max 4000 chars)
	 * - Message processing through bot
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error REST response or error.
	 */
	public function handle_chat( WP_REST_Request $request ) {
		// Check rate limits.
		$rate_limit_check = $this->check_rate_limit( $request );
		if ( is_wp_error( $rate_limit_check ) ) {
			return $rate_limit_check;
		}

		$bot_id     = $request->get_param( 'bot_id' );
		$message    = $request->get_param( 'message' );
		$session_id = $request->get_param( 'session_id' );

		// Validate message length (already sanitized by REST API).
		if ( strlen( $message ) > self::MAX_MESSAGE_LENGTH ) {
			return new WP_Error(
				'message_too_long',
				sprintf(
					/* translators: %d: Maximum message length */
					__( 'Message is too long. Maximum length is %d characters.', 'wyverncss' ),
					self::MAX_MESSAGE_LENGTH
				),
				array( 'status' => 400 )
			);
		}

		// Prepare chat request data.
		$chat_data = array(
			'bot_id'  => $bot_id,
			'message' => $message,
		);

		if ( ! empty( $session_id ) ) {
			$chat_data['session_id'] = $session_id;
		}

		// Send to cloud service.
		$response = $this->mcp_client->request(
			'POST',
			'/api/v1/chat',
			$chat_data
		);

		if ( is_wp_error( $response ) ) {
			return $this->handle_cloud_service_error( $response );
		}

		// Increment rate limit counter.
		$this->increment_rate_limit( $request );

		$status = $response['status'] ?? 200;
		$body   = $response['body'] ?? array();

		return new WP_REST_Response( $body, $status );
	}

	/**
	 * Filter bot data to only public fields.
	 *
	 * Returns only safe, public information about the bot.
	 * Strips all sensitive configuration like system_prompt, API keys, etc.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $bot_data Full bot data.
	 * @return array<string, mixed> Filtered public data.
	 */
	private function filter_public_bot_fields( array $bot_data ): array {
		$public_fields = array(
			'id',
			'name',
			'description',
			'avatar_url',
			'welcome_message',
			'is_active',
		);

		$public_config = array();

		foreach ( $public_fields as $field ) {
			if ( isset( $bot_data[ $field ] ) ) {
				$public_config[ $field ] = $bot_data[ $field ];
			}
		}

		return $public_config;
	}

	/**
	 * Check rate limits for the request.
	 *
	 * Implements two-tier rate limiting:
	 * - 10 requests per minute per IP
	 * - 50 requests per hour per session
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return true|WP_Error True if within limits, WP_Error otherwise.
	 */
	private function check_rate_limit( WP_REST_Request $request ) {
		$ip_address = $this->get_client_ip();

		// Check per-minute rate limit.
		$minute_key   = self::RATE_LIMIT_PREFIX . 'minute_' . md5( $ip_address );
		$minute_count = (int) get_transient( $minute_key );

		if ( $minute_count >= self::RATE_LIMIT_PER_MINUTE ) {
			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: Rate limit per minute */
					__( 'Rate limit exceeded. Maximum %d requests per minute allowed.', 'wyverncss' ),
					self::RATE_LIMIT_PER_MINUTE
				),
				array( 'status' => 429 )
			);
		}

		// Check per-hour rate limit.
		$hour_key   = self::RATE_LIMIT_PREFIX . 'hour_' . md5( $ip_address );
		$hour_count = (int) get_transient( $hour_key );

		if ( $hour_count >= self::RATE_LIMIT_PER_HOUR ) {
			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: Rate limit per hour */
					__( 'Rate limit exceeded. Maximum %d requests per hour allowed.', 'wyverncss' ),
					self::RATE_LIMIT_PER_HOUR
				),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Increment rate limit counters.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return void
	 */
	private function increment_rate_limit( WP_REST_Request $request ): void {
		$ip_address = $this->get_client_ip();

		// Increment per-minute counter.
		$minute_key   = self::RATE_LIMIT_PREFIX . 'minute_' . md5( $ip_address );
		$minute_count = (int) get_transient( $minute_key );
		set_transient( $minute_key, $minute_count + 1, MINUTE_IN_SECONDS );

		// Increment per-hour counter.
		$hour_key   = self::RATE_LIMIT_PREFIX . 'hour_' . md5( $ip_address );
		$hour_count = (int) get_transient( $hour_key );
		set_transient( $hour_key, $hour_count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Get client IP address.
	 *
	 * Checks various headers to get the real client IP,
	 * useful when behind proxies or load balancers.
	 *
	 * @since 1.0.0
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // CloudFlare.
			'HTTP_X_FORWARDED_FOR',  // Proxy.
			'HTTP_X_REAL_IP',        // Nginx proxy.
			'REMOTE_ADDR',           // Standard.
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

				// Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs).
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}

				// Validate IP address.
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Sanitize chat message.
	 *
	 * Strips HTML tags and limits length to prevent abuse.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Raw message input.
	 * @return string Sanitized message.
	 */
	public function sanitize_message( string $message ): string {
		// Strip all HTML tags.
		$message = wp_strip_all_tags( $message );

		// Remove control characters except newlines and tabs.
		$message = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $message );

		// Ensure preg_replace succeeded.
		if ( null === $message ) {
			$message = '';
		}

		// Trim whitespace.
		$message = trim( $message );

		// Limit length.
		if ( strlen( $message ) > self::MAX_MESSAGE_LENGTH ) {
			$message = substr( $message, 0, self::MAX_MESSAGE_LENGTH );
		}

		return $message;
	}

	/**
	 * Handle cloud service errors.
	 *
	 * Converts cloud service errors to appropriate REST API error responses.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Error $error WordPress error object.
	 * @return WP_Error Formatted error response.
	 */
	private function handle_cloud_service_error( WP_Error $error ): WP_Error {
		$error_code    = $error->get_error_code();
		$error_message = $error->get_error_message();
		$error_data    = $error->get_error_data();

		// Default status code.
		$status_code = 500;

		// Map common error codes to HTTP status codes.
		$status_map = array(
			'bot_not_found'             => 404,
			'cloud_service_unavailable' => 503,
			'validation_error'          => 400,
			'unauthorized'              => 401,
		);

		// Check if error data contains status.
		if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
			$status_code = (int) $error_data['status'];
		} elseif ( isset( $status_map[ $error_code ] ) ) {
			$status_code = $status_map[ $error_code ];
		}

		// Return user-friendly error message.
		return new WP_Error(
			$error_code,
			$error_message,
			array( 'status' => $status_code )
		);
	}
}
