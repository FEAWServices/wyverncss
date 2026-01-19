<?php
/**
 * REST Controller Helper Trait
 *
 * Provides reusable methods for REST controllers to eliminate code duplication.
 * Handles common patterns like error handling, response formatting, validation,
 * and permission checks.
 *
 * @package WyvernCSS
 * @subpackage API\REST
 * @since 2.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\API\REST;
use WP_Error;
use WP_REST_Response;

/**
 * Controller Helpers Trait
 *
 * Provides common functionality for REST API controllers:
 * - Exception to WP_Error conversion
 * - HTTP health check requests
 * - Response formatting utilities
 * - Permission validation helpers
 * - Data masking utilities
 *
 * @since 2.0.0
 */
trait Controller_Helpers {

	/**
	 * Check external service health via HTTP request.
	 *
	 * Makes a health check request to a remote service URL and returns status.
	 *
	 * @since 2.0.0
	 *
	 * @param string $service_url Base URL of the service.
	 * @return string Service status: 'ok', 'not_configured', or 'unreachable'.
	 */
	protected function check_service_health( string $service_url ): string {
		if ( empty( $service_url ) ) {
			return 'not_configured';
		}

		$health_url = trailingslashit( $service_url ) . 'api/v1/health';

		$response = wp_remote_get(
			$health_url,
			array(
				'timeout' => 5,
				'headers' => array(
					'User-Agent' => 'WyvernCSS-WordPress/' . ( defined( 'WYVERNCSS_VERSION' ) ? WYVERNCSS_VERSION : '2.0.0' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return 'unreachable';
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		return 200 === $status_code ? 'ok' : 'unreachable';
	}

	/**
	 * Create a success response with message.
	 *
	 * Standardized success response format for REST endpoints.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed  $data Response data.
	 * @param string $message Success message.
	 * @param int    $status_code HTTP status code. Default 200.
	 * @return WP_REST_Response Response object.
	 */
	protected function success_response_with_message( $data, string $message, int $status_code = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
				'message' => $message,
			),
			$status_code
		);
	}

	/**
	 * Validate conversation ownership.
	 *
	 * Checks if a conversation belongs to a specific user.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $conversation Conversation data with 'user_id' key.
	 * @param int                  $user_id User ID to validate against.
	 * @return true|WP_Error True if user owns conversation, WP_Error otherwise.
	 */
	protected function validate_conversation_ownership( array $conversation, int $user_id ) {
		if ( (int) $conversation['user_id'] !== $user_id ) {
			return new WP_Error(
				'permission_denied',
				__( 'You do not have permission to access this conversation', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Mask API key for security.
	 *
	 * Shows only the last 4 characters of an API key, masking the rest with asterisks.
	 *
	 * @since 2.0.0
	 *
	 * @param string $api_key The API key to mask.
	 * @return string Masked API key.
	 */
	protected function mask_api_key( string $api_key ): string {
		if ( empty( $api_key ) ) {
			return '';
		}

		$api_key_length = strlen( $api_key );

		if ( $api_key_length > 4 ) {
			return str_repeat( '*', $api_key_length - 4 ) . substr( $api_key, -4 );
		}

		return str_repeat( '*', $api_key_length );
	}

	/**
	 * Check admin permission with nonce validation.
	 *
	 * Requires manage_options capability and valid WP REST nonce.
	 *
	 * @since 2.0.0
	 *
	 * @param string $nonce Nonce value from request header.
	 * @param string $error_message Optional. Custom error message for capability check.
	 * @return true|WP_Error True if authorized, WP_Error otherwise.
	 */
	protected function check_admin_permission_with_nonce( string $nonce, string $error_message = '' ): bool|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			$message = ! empty( $error_message )
				? $error_message
				: __( 'You do not have permission to perform this action.', 'wyvern-ai-styling' );

			return new WP_Error(
				'rest_forbidden',
				$message,
				array( 'status' => 403 )
			);
		}

		// Verify nonce for security.
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid security token.', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Validate message length.
	 *
	 * Checks if a message is within acceptable length bounds.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message Message to validate.
	 * @param int    $min_length Minimum length. Default 1.
	 * @param int    $max_length Maximum length. Default 10000.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	protected function validate_message_length( string $message, int $min_length = 1, int $max_length = 10000 ): bool|WP_Error {
		$length = strlen( $message );

		if ( $length < $min_length || $length > $max_length ) {
			return new WP_Error(
				'invalid_message',
				sprintf(
					/* translators: %1$d: minimum length, %2$d: maximum length */
					__( 'Message must be between %1$d and %2$d characters', 'wyvern-ai-styling' ),
					$min_length,
					$max_length
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}
}
