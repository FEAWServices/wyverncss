<?php
/**
 * Server-Sent Events (SSE) Handler
 *
 * Handles SSE connections for real-time MCP communication.
 *
 * @package WyvernCSS\MCP
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * SSE Handler Class
 *
 * Manages Server-Sent Events for streaming MCP responses.
 */
class SSEHandler {

	/**
	 * Set SSE headers
	 *
	 * @return void */
	public function set_headers(): void {
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' ); // Disable nginx buffering.
	}

	/**
	 * Send SSE event
	 *
	 * @param string $data Event data (must be valid JSON string).
	 * @param string $event Event type (optional).
	 * @param string $id Event ID (optional).
	 * @return void */
	public function send_event( string $data, string $event = '', string $id = '' ): void {
		if ( ! empty( $id ) ) {
			echo 'id: ' . esc_html( $id ) . "\n";
		}

		if ( ! empty( $event ) ) {
			echo 'event: ' . esc_html( $event ) . "\n";
		}

		// Validate that data is valid JSON before output.
		// SSE protocol requires JSON data, validation ensures safety.
		$validated = json_decode( $data );
		if ( null === $validated && '' !== $data ) {
			// Invalid JSON, send empty object.
			echo "data: {}\n\n";
		} else {
			// Valid JSON, safe to output in SSE format.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated JSON for SSE protocol
			echo 'data: ' . $data . "\n\n";
		}

		// Flush output buffer.
		if ( ob_get_level() > 0 ) {
			ob_flush();
		}
		flush();
	}

	/**
	 * Send SSE formatted data
	 *
	 * @param mixed  $data Data to send (will be JSON encoded).
	 * @param string $event Event type (optional).
	 * @param string $id Event ID (optional).
	 * @return void */
	public function send_data( $data, string $event = '', string $id = '' ): void {
		$json_data = is_string( $data ) ? $data : wp_json_encode( $data );
		// Ensure we have a string for send_event.
		$safe_json = false !== $json_data ? $json_data : '';
		$this->send_event( $safe_json, $event, $id );
	}

	/**
	 * Send SSE error event
	 *
	 * @param string $message Error message.
	 * @param int    $code Error code.
	 * @return void */
	public function send_error( string $message, int $code = -32603 ): void {
		$error_data = array(
			'jsonrpc' => '2.0',
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);

		$this->send_data( $error_data, 'error' );
	}

	/**
	 * Format data for SSE
	 *
	 * @param mixed $data Data to format.
	 * @return string Formatted SSE data string.
	 */
	public function format_sse_data( $data ): string {
		$json_data = is_string( $data ) ? $data : wp_json_encode( $data );
		// Ensure we have a string to split.
		$safe_json = false !== $json_data ? $json_data : '';

		$output = '';

		// Split data into lines and prefix each with "data: ".
		$lines = explode( "\n", $safe_json );
		foreach ( $lines as $line ) {
			$output .= 'data: ' . $line . "\n";
		}

		// Add extra newline to signal end of event.
		$output .= "\n";

		return $output;
	}

	/**
	 * Send keep-alive ping
	 *
	 * Sends a comment to keep connection alive
	 *
	 * @return void */
	public function send_ping(): void {
		echo ': ping' . "\n\n";

		if ( ob_get_level() > 0 ) {
			ob_flush();
		}
		flush();
	}

	/**
	 * Handle SSE connection
	 *
	 * Sets up SSE connection and sends initial data.
	 *
	 * @param mixed $data Initial data to send.
	 * @return string SSE formatted response.
	 */
	public function handle_connection( $data = null ): string {
		if ( null === $data ) {
			$data = array(
				'jsonrpc' => '2.0',
				'result'  => array(
					'message' => 'SSE connection established',
				),
			);
		}

		return $this->format_sse_data( $data );
	}
}
