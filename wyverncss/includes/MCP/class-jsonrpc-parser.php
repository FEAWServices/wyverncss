<?php
/**
 * JSON-RPC 2.0 Parser
 *
 * Parses and validates JSON-RPC 2.0 requests according to specification.
 *
 * @package WyvernCSS\MCP
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\MCP;
use WP_Error;

/**
 * JSON-RPC Parser Class
 *
 * Handles parsing and validation of JSON-RPC 2.0 protocol messages.
 */
class JSONRPCParser {

	/**
	 * Parse JSON-RPC 2.0 request
	 *
	 * @param string $body Request body.
	 * @return array<string, mixed>|WP_Error Parsed request data or error.
	 */
	public function parse( string $body ) {
		// Attempt to decode JSON.
		$decoded = json_decode( $body, true );

		// Check for JSON parse errors.
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'parse_error',
				'Invalid JSON was received by the server.',
				array(
					'code'    => -32700,
					'message' => 'Parse error',
				)
			);
		}

		// Validate JSON-RPC 2.0 format.
		$validation = $this->validate( $decoded );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return $decoded;
	}

	/**
	 * Validate JSON-RPC 2.0 request structure
	 *
	 * @param mixed $request Decoded request data.
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	public function validate( $request ) {
		// Must be an array.
		if ( ! is_array( $request ) ) {
			return new WP_Error(
				'invalid_request',
				'The JSON sent is not a valid Request object.',
				array(
					'code'    => -32600,
					'message' => 'Invalid Request',
				)
			);
		}

		// Check jsonrpc field.
		if ( ! isset( $request['jsonrpc'] ) ) {
			return new WP_Error(
				'invalid_request',
				'Missing required field: jsonrpc',
				array(
					'code'    => -32600,
					'message' => 'Invalid Request',
				)
			);
		}

		if ( '2.0' !== $request['jsonrpc'] ) {
			return new WP_Error(
				'invalid_request',
				'The jsonrpc version must be "2.0"',
				array(
					'code'    => -32600,
					'message' => 'Invalid Request',
				)
			);
		}

		// Check method field (required).
		if ( ! isset( $request['method'] ) ) {
			return new WP_Error(
				'invalid_request',
				'Missing required field: method',
				array(
					'code'    => -32600,
					'message' => 'Invalid Request',
				)
			);
		}

		// Check id field (required for requests, not notifications).
		if ( ! isset( $request['id'] ) ) {
			return new WP_Error(
				'invalid_request',
				'Missing required field: id',
				array(
					'code'    => -32600,
					'message' => 'Invalid Request',
				)
			);
		}

		return true;
	}

	/**
	 * Get method from parsed request
	 *
	 * @param array<string, mixed> $request Parsed request.
	 * @return string Method name.
	 */
	public function get_method( array $request ): string {
		return (string) ( $request['method'] ?? '' );
	}

	/**
	 * Get params from parsed request
	 *
	 * @param array<string, mixed> $request Parsed request.
	 * @return array<string, mixed> Parameters (empty array if not set).
	 */
	public function get_params( array $request ): array {
		return (array) ( $request['params'] ?? array() );
	}

	/**
	 * Get request ID from parsed request
	 *
	 * @param array<string, mixed> $request Parsed request.
	 * @return string|int|null Request ID.
	 */
	public function get_id( array $request ) {
		return $request['id'] ?? null;
	}
}
