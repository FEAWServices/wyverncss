<?php
/**
 * MCP Exception Class
 *
 * Custom exception for MCP-related errors.
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
use Exception;

/**
 * MCP Exception Class
 *
 * Specialized exception for MCP client and service errors.
 *
 * Error Codes:
 * - SERVICE_UNREACHABLE: Cannot connect to MCP service
 * - TIMEOUT: Request timed out
 * - INVALID_RESPONSE: Malformed or invalid response
 * - TOOL_NOT_FOUND: Requested tool does not exist
 * - INVALID_PARAMS: Tool parameters are invalid
 * - TOOL_EXECUTION_FAILED: Tool execution failed
 * - CIRCUIT_BREAKER_OPEN: Circuit breaker is open (service down)
 * - AUTHENTICATION_FAILED: Request signature verification failed
 *
 * @since 2.0.0
 */
class MCP_Exception extends Exception {

	/**
	 * Error code (machine-readable).
	 *
	 * @var string
	 */
	private string $error_code;

	/**
	 * Additional error details.
	 *
	 * @var array<string, mixed>
	 */
	private array $error_details;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $message       User-friendly error message.
	 * @param string               $error_code    Machine-readable error code.
	 * @param array<string, mixed> $error_details Additional error details.
	 * @param Exception|null       $previous      Previous exception for chaining.
	 */
	public function __construct( string $message, string $error_code, array $error_details = array(), ?Exception $previous = null ) {
		parent::__construct( $message, 0, $previous );
		$this->error_code    = $error_code;
		$this->error_details = $error_details;
	}

	/**
	 * Get error code.
	 *
	 * @since 2.0.0
	 *
	 * @return string Error code.
	 */
	public function get_error_code(): string {
		return $this->error_code;
	}

	/**
	 * Get error details.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, mixed> Error details.
	 */
	public function get_error_details(): array {
		return $this->error_details;
	}

	/**
	 * Create exception for service unreachable error.
	 *
	 * @since 2.0.0
	 *
	 * @param string         $reason   Reason for unreachability.
	 * @param Exception|null $previous Previous exception.
	 * @return self Exception instance.
	 */
	public static function service_unreachable( string $reason = '', ?Exception $previous = null ): self {
		$message = __( 'MCP service is currently unavailable. Please try again later.', 'wyverncss' );

		return new self(
			$message,
			'SERVICE_UNREACHABLE',
			array(
				'reason'    => $reason,
				'user_hint' => __( 'Check your internet connection or contact support if this persists.', 'wyverncss' ),
			),
			$previous
		);
	}

	/**
	 * Create exception for timeout error.
	 *
	 * @since 2.0.0
	 *
	 * @param int            $timeout  Timeout value in seconds.
	 * @param Exception|null $previous Previous exception.
	 * @return self Exception instance.
	 */
	public static function timeout( int $timeout = 30, ?Exception $previous = null ): self {
		$message = sprintf(
			/* translators: %d: timeout in seconds */
			__( 'MCP service request timed out after %d seconds.', 'wyverncss' ),
			$timeout
		);

		return new self(
			$message,
			'TIMEOUT',
			array(
				'timeout'   => $timeout,
				'user_hint' => __( 'The service is taking longer than expected. Please try again.', 'wyverncss' ),
			),
			$previous
		);
	}

	/**
	 * Create exception for invalid response error.
	 *
	 * @since 2.0.0
	 *
	 * @param string         $reason   Reason for invalid response.
	 * @param Exception|null $previous Previous exception.
	 * @return self Exception instance.
	 */
	public static function invalid_response( string $reason = '', ?Exception $previous = null ): self {
		$message = __( 'MCP service returned an invalid response.', 'wyverncss' );

		return new self(
			$message,
			'INVALID_RESPONSE',
			array(
				'reason'    => $reason,
				'user_hint' => __( 'There may be a service version mismatch. Contact support.', 'wyverncss' ),
			),
			$previous
		);
	}

	/**
	 * Create exception for tool not found error.
	 *
	 * @since 2.0.0
	 *
	 * @param string         $tool_name Tool name that was not found.
	 * @param Exception|null $previous  Previous exception.
	 * @return self Exception instance.
	 */
	public static function tool_not_found( string $tool_name, ?Exception $previous = null ): self {
		$message = sprintf(
			/* translators: %s: tool name */
			__( 'MCP tool "%s" was not found.', 'wyverncss' ),
			$tool_name
		);

		return new self(
			$message,
			'TOOL_NOT_FOUND',
			array(
				'tool_name' => $tool_name,
				'user_hint' => __( 'The requested feature may not be available in your plan.', 'wyverncss' ),
			),
			$previous
		);
	}

	/**
	 * Create exception for invalid parameters error.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $tool_name Tool name.
	 * @param array<string, mixed> $errors    Validation errors.
	 * @param Exception|null       $previous  Previous exception.
	 * @return self Exception instance.
	 */
	public static function invalid_params( string $tool_name, array $errors = array(), ?Exception $previous = null ): self {
		$message = sprintf(
			/* translators: %s: tool name */
			__( 'Invalid parameters provided for tool "%s".', 'wyverncss' ),
			$tool_name
		);

		return new self(
			$message,
			'INVALID_PARAMS',
			array(
				'tool_name'         => $tool_name,
				'validation_errors' => $errors,
				'user_hint'         => __( 'Please check your input and try again.', 'wyverncss' ),
			),
			$previous
		);
	}

	/**
	 * Create exception for tool execution failure.
	 *
	 * @since 2.0.0
	 *
	 * @param string         $tool_name    Tool name.
	 * @param string         $error_message Error message from tool.
	 * @param Exception|null $previous     Previous exception.
	 * @return self Exception instance.
	 */
	public static function tool_execution_failed( string $tool_name, string $error_message = '', ?Exception $previous = null ): self {
		$message = sprintf(
			/* translators: %s: tool name */
			__( 'Failed to execute tool "%s".', 'wyverncss' ),
			$tool_name
		);

		return new self(
			$message,
			'TOOL_EXECUTION_FAILED',
			array(
				'tool_name'     => $tool_name,
				'error_message' => $error_message,
				'user_hint'     => __( 'An error occurred while processing your request. Please try again.', 'wyverncss' ),
			),
			$previous
		);
	}

	/**
	 * Create exception for circuit breaker open.
	 *
	 * @since 2.0.0
	 *
	 * @param int            $retry_after Seconds until retry is allowed.
	 * @param Exception|null $previous    Previous exception.
	 * @return self Exception instance.
	 */
	public static function circuit_breaker_open( int $retry_after = 3600, ?Exception $previous = null ): self {
		$retry_minutes = (int) ceil( $retry_after / 60 );

		$message = sprintf(
			/* translators: %d: minutes until retry */
			__( 'MCP service is temporarily disabled. Please try again in %d minutes.', 'wyverncss' ),
			$retry_minutes
		);

		return new self(
			$message,
			'CIRCUIT_BREAKER_OPEN',
			array(
				'retry_after'   => $retry_after,
				'retry_minutes' => $retry_minutes,
				'user_hint'     => __( 'The service is experiencing issues. It will automatically recover.', 'wyverncss' ),
			),
			$previous
		);
	}

	/**
	 * Create exception for authentication failure.
	 *
	 * @since 2.0.0
	 *
	 * @param string         $reason   Reason for authentication failure.
	 * @param Exception|null $previous Previous exception.
	 * @return self Exception instance.
	 */
	public static function authentication_failed( string $reason = '', ?Exception $previous = null ): self {
		$message = __( 'MCP service authentication failed.', 'wyverncss' );

		return new self(
			$message,
			'AUTHENTICATION_FAILED',
			array(
				'reason'    => $reason,
				'user_hint' => __( 'Please check your license key and configuration.', 'wyverncss' ),
			),
			$previous
		);
	}

	/**
	 * Create exception from WP_Error.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Error $error WP_Error instance.
	 * @return self Exception instance.
	 */
	public static function from_wp_error( \WP_Error $error ): self {
		$error_code = $error->get_error_code();
		$message    = $error->get_error_message();
		$data       = $error->get_error_data();

		// Map WP_Error codes to MCP exception types.
		$code_mapping = array(
			'mcp_network_error'    => 'SERVICE_UNREACHABLE',
			'mcp_timeout'          => 'TIMEOUT',
			'mcp_invalid_response' => 'INVALID_RESPONSE',
			'mcp_tool_not_found'   => 'TOOL_NOT_FOUND',
			'mcp_invalid_params'   => 'INVALID_PARAMS',
			'mcp_tool_error'       => 'TOOL_EXECUTION_FAILED',
			'mcp_circuit_breaker'  => 'CIRCUIT_BREAKER_OPEN',
			'mcp_authentication'   => 'AUTHENTICATION_FAILED',
		);

		$mcp_code = $code_mapping[ $error_code ] ?? 'UNKNOWN_ERROR';

		return new self(
			$message,
			$mcp_code,
			is_array( $data ) ? $data : array( 'original_error' => $data ),
			null
		);
	}

	/**
	 * Convert exception to user-friendly message.
	 *
	 * @since 2.0.0
	 *
	 * @return string User-friendly message.
	 */
	public function get_user_message(): string {
		$message = $this->getMessage();

		// Add user hint if available.
		if ( isset( $this->error_details['user_hint'] ) ) {
			$message .= ' ' . $this->error_details['user_hint'];
		}

		return $message;
	}

	/**
	 * Convert exception to array for API responses.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, mixed> Exception as array.
	 */
	public function to_array(): array {
		return array(
			'error'   => true,
			'code'    => $this->error_code,
			'message' => $this->getMessage(),
			'details' => $this->error_details,
		);
	}

	/**
	 * Log exception if WP_DEBUG is enabled.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function log(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_message = sprintf(
				'WyvernCSS MCP Exception [%s]: %s (Details: %s)',
				$this->error_code,
				$this->getMessage(),
				wp_json_encode( $this->error_details )
			);

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $log_message );

			// Log stack trace if available.
			if ( $this->getPrevious() ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Previous exception: ' . $this->getPrevious()->getMessage() );
			}
		}
	}
}
