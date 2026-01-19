<?php
/**
 * JSON-RPC 2.0 Formatter
 *
 * Formats JSON-RPC 2.0 responses according to specification.
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
 * JSON-RPC Formatter Class
 *
 * Handles formatting of JSON-RPC 2.0 protocol responses.
 */
class JSONRPCFormatter {

	/**
	 * Format successful response
	 *
	 * @param mixed      $result Response result data.
	 * @param string|int $id Request ID.
	 * @return array<string, mixed> JSON-RPC 2.0 success response.
	 */
	public function format_success( $result, $id ): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * Format error response
	 *
	 * @param int        $code Error code.
	 * @param string     $message Error message.
	 * @param mixed      $data Optional error data.
	 * @param string|int $id Request ID (null for parse errors).
	 * @return array<string, mixed> JSON-RPC 2.0 error response.
	 */
	public function format_error( int $code, string $message, $data = null, $id = null ): array {
		$response = array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);

		if ( null !== $data ) {
			$response['error']['data'] = $data;
		}

		return $response;
	}

	/**
	 * Format WP_Error as JSON-RPC error response
	 *
	 * @param WP_Error   $error WordPress error object.
	 * @param string|int $id Request ID.
	 * @return array<string, mixed> JSON-RPC 2.0 error response.
	 */
	public function format_wp_error( WP_Error $error, $id = null ): array {
		$error_data = $error->get_error_data();

		// Get error code from error data if available.
		$code = -32603; // Default: Internal error.
		if ( is_array( $error_data ) && isset( $error_data['code'] ) ) {
			$code = (int) $error_data['code'];
		} else {
			// Map WordPress error codes to JSON-RPC codes.
			$wp_error_code = $error->get_error_code();
			$code          = $this->map_wp_error_code( is_string( $wp_error_code ) ? $wp_error_code : '' );
		}

		$message = $error->get_error_message();

		return $this->format_error( $code, $message, $error_data, $id );
	}

	/**
	 * Map WordPress error codes to JSON-RPC error codes
	 *
	 * @param string $wp_code WordPress error code.
	 * @return int JSON-RPC error code.
	 */
	private function map_wp_error_code( string $wp_code ): int {
		$map = array(
			'parse_error'              => -32700,
			'invalid_request'          => -32600,
			'method_not_found'         => -32601,
			'invalid_params'           => -32602,
			'internal_error'           => -32603,
			'authentication_failed'    => -32001,
			'permission_denied'        => -32002,
			'insufficient_permissions' => -32002,
			'rate_limit_exceeded'      => -32003,
			'validation_error'         => -32004,
			'post_not_found'           => -32005,
			'resource_not_found'       => -32005,
			'db_error'                 => -32006,
			'plugin_error'             => -32007,
		);

		return $map[ $wp_code ] ?? -32603; // Default to Internal error.
	}

	/**
	 * Format MCP tool result
	 *
	 * Wraps tool result in MCP content format.
	 *
	 * @param mixed $result Tool result data.
	 * @return array<string, mixed> MCP formatted result.
	 */
	public function format_mcp_result( $result ): array {
		// If result is already in MCP format, return as-is.
		if ( is_array( $result ) && isset( $result['content'] ) ) {
			return $result;
		}

		// Wrap simple results in MCP content structure.
		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => is_string( $result ) ? $result : wp_json_encode( $result ),
				),
			),
		);
	}

	/**
	 * Format response (automatic success/error detection)
	 *
	 * @param mixed      $data Response data or WP_Error.
	 * @param string|int $id Request ID.
	 * @return array<string, mixed> JSON-RPC 2.0 formatted response.
	 */
	public function format_response( $data, $id ): array {
		if ( is_wp_error( $data ) ) {
			return $this->format_wp_error( $data, $id );
		}

		return $this->format_success( $data, $id );
	}
}
