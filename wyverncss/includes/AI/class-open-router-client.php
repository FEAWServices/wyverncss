<?php
/**
 * OpenRouter API Client
 *
 * Manages communication with OpenRouter API for AI model inference
 * with MCP tool calling support.
 *
 * @package WyvernCSS\AI
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\MCP\ToolRegistry;
use WP_Error;

/**
 * Class OpenRouter_Client
 *
 * Handles all communication with the OpenRouter API, including:
 * - Sending messages to AI models
 * - Tool definition formatting
 * - Tool execution coordination
 * - Response parsing
 * - Error handling and retries
 */
class OpenRouter_Client {

	/**
	 * OpenRouter API base URL
	 */
	private const API_BASE_URL = 'https://openrouter.ai/api/v1';

	/**
	 * Default AI model
	 */
	private const DEFAULT_MODEL = 'anthropic/claude-3.5-sonnet';

	/**
	 * Maximum retry attempts for failed requests
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Request timeout in seconds
	 */
	private const TIMEOUT = 60;

	/**
	 * User's OpenRouter API key
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * MCP Tool Registry (optional - not needed for CSS styling mode)
	 *
	 * @var ToolRegistry|null
	 */
	private ?ToolRegistry $tool_registry;

	/**
	 * Constructor
	 *
	 * @param string            $api_key       OpenRouter API key.
	 * @param ToolRegistry|null $tool_registry MCP tool registry instance (optional).
	 */
	public function __construct( string $api_key, ?ToolRegistry $tool_registry = null ) {
		$this->api_key       = $api_key;
		$this->tool_registry = $tool_registry;
	}

	/**
	 * Send message to AI with tool support
	 *
	 * @param string                           $message              User message.
	 * @param int                              $user_id              WordPress user ID.
	 * @param array<int, array<string, mixed>> $conversation_history Previous messages in conversation.
	 * @param string|null                      $model                Model to use (defaults to Claude 3.5 Sonnet).
	 * @param array<string, mixed>             $options              Additional options (temperature, max_tokens, etc.).
	 * @return array<string, mixed>|WP_Error Response data or error.
	 */
	public function send_message(
		string $message,
		int $user_id,
		array $conversation_history = array(),
		?string $model = null,
		array $options = array()
	) {
		$model = $model ?? self::DEFAULT_MODEL;

		// Build messages array.
		$messages   = $conversation_history;
		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		// Get tool definitions from MCP registry.
		$tools = $this->format_tools_for_openrouter();

		// Build request payload.
		$payload = array(
			'model'       => $model,
			'messages'    => $messages,
			'tools'       => $tools,
			'tool_choice' => 'auto',
		);

		// Merge additional options.
		$payload = array_merge( $payload, $options );

		// Send request to OpenRouter.
		$response = $this->send_request( '/chat/completions', $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check if AI wants to call tools.
		if ( $this->has_tool_calls( $response ) ) {
			return $this->process_tool_calls( $response, $messages, $model, $user_id );
		}

		return $this->parse_response( $response );
	}

	/**
	 * Format MCP tools for OpenRouter API
	 *
	 * Converts MCP tool schemas to OpenRouter/OpenAI function calling format.
	 *
	 * @return array<int, array<string, mixed>> Tool definitions in OpenRouter format.
	 */
	private function format_tools_for_openrouter(): array {
		// No tools available in CSS styling mode.
		if ( null === $this->tool_registry ) {
			return array();
		}
		$mcp_tools = $this->tool_registry->get_tools();
		$tools     = array();

		foreach ( $mcp_tools as $tool ) {
			$schema = $tool->get_input_schema();

			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $tool->get_name(),
					'description' => $tool->get_description(),
					'parameters'  => array(
						'type'       => 'object',
						'properties' => $schema['properties'] ?? array(),
						'required'   => $schema['required'] ?? array(),
					),
				),
			);
		}

		return $tools;
	}

	/**
	 * Send HTTP request to OpenRouter API
	 *
	 * @param string               $endpoint API endpoint path.
	 * @param array<string, mixed> $payload  Request payload.
	 * @param int                  $retry    Current retry attempt.
	 * @return array<string, mixed>|WP_Error Response data or error.
	 */
	private function send_request( string $endpoint, array $payload, int $retry = 0 ) {
		$url = self::API_BASE_URL . $endpoint;

		$headers = array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
			'HTTP-Referer'  => home_url(),
			'X-Title'       => get_bloginfo( 'name' ),
		);

		$encoded_body = wp_json_encode( $payload );
		if ( false === $encoded_body ) {
			return new WP_Error(
				'json_encode_error',
				__( 'Failed to encode request payload', 'wyvern-ai-styling' )
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => $encoded_body,
				'timeout' => self::TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			// Retry on network errors.
			if ( $retry < self::MAX_RETRIES ) {
				// Exponential backoff: 1s, 2s, 4s.
				sleep( pow( 2, $retry ) );
				return $this->send_request( $endpoint, $payload, $retry + 1 );
			}

			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		// Ensure status code is an int.
		$status_code = is_numeric( $status_code ) ? (int) $status_code : 0;

		// Handle non-200 responses.
		if ( 200 !== $status_code ) {
			return $this->handle_error_response( $status_code, $body );
		}

		// Parse JSON response.
		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'json_decode_error',
				__( 'Failed to decode OpenRouter response', 'wyvern-ai-styling' ),
				array(
					'body'       => $body,
					'json_error' => json_last_error_msg(),
				)
			);
		}

		return $data;
	}

	/**
	 * Handle error response from OpenRouter
	 *
	 * @param int    $status_code HTTP status code.
	 * @param string $body        Response body.
	 * @return WP_Error Error object with details.
	 */
	private function handle_error_response( int $status_code, string $body ): WP_Error {
		$error_data = json_decode( $body, true );

		$error_message = $error_data['error']['message'] ?? __( 'OpenRouter API error', 'wyvern-ai-styling' );
		$error_code    = $error_data['error']['code'] ?? 'api_error';

		$wp_error_code = match ( $status_code ) {
			401     => 'invalid_api_key',
			429     => 'rate_limit_exceeded',
			400     => 'invalid_request',
			500, 502, 503 => 'service_unavailable',
			default => 'api_error',
		};

		return new WP_Error(
			$wp_error_code,
			$error_message,
			array(
				'status'          => $status_code,
				'openrouter_code' => $error_code,
				'response_body'   => $error_data,
			)
		);
	}

	/**
	 * Check if response contains tool calls
	 *
	 * @param array<string, mixed> $response OpenRouter API response.
	 * @return bool True if response has tool calls, false otherwise.
	 */
	private function has_tool_calls( array $response ): bool {
		return isset( $response['choices'][0]['message']['tool_calls'] ) &&
			! empty( $response['choices'][0]['message']['tool_calls'] );
	}

	/**
	 * Process tool calls from AI response
	 *
	 * Executes requested tools and sends results back to AI for final response.
	 *
	 * @param array<string, mixed>             $response          OpenRouter response with tool calls.
	 * @param array<int, array<string, mixed>> $original_messages Original conversation messages.
	 * @param string                           $model             Model being used.
	 * @param int                              $_user_id          WordPress user ID (unused, reserved for future use).
	 * @return array<string, mixed>|WP_Error Final response or error.
	 *
	 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $_user_id reserved for future use.
	 */
	private function process_tool_calls(
		array $response,
		array $original_messages,
		string $model,
		int $_user_id
	) {
		$assistant_message = $response['choices'][0]['message'];
		$tool_calls        = $assistant_message['tool_calls'];

		// Add assistant message with tool calls to conversation.
		$messages   = $original_messages;
		$messages[] = $assistant_message;

		// Execute each tool call.
		$tool_results = array();

		foreach ( $tool_calls as $tool_call ) {
			$result = $this->execute_tool_call( $tool_call );

			// Add tool result to messages.
			$messages[] = array(
				'role'         => 'tool',
				'tool_call_id' => $tool_call['id'],
				'content'      => is_wp_error( $result )
					? $result->get_error_message()
					: wp_json_encode( $result ),
			);

			$tool_results[] = array(
				'tool_call' => $tool_call,
				'result'    => $result,
				'success'   => ! is_wp_error( $result ),
			);
		}

		// Send tool results back to AI for final response.
		$final_response = $this->send_request(
			'/chat/completions',
			array(
				'model'    => $model,
				'messages' => $messages,
			)
		);

		if ( is_wp_error( $final_response ) ) {
			return $final_response;
		}

		// Parse final response and attach tool execution details.
		$parsed                    = $this->parse_response( $final_response );
		$parsed['tool_executions'] = $tool_results;

		return $parsed;
		// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	}

	/**
	 * Execute a single tool call
	 *
	 * @param array<string, mixed> $tool_call Tool call from AI response.
	 * @return array<string, mixed>|WP_Error Tool execution result or error.
	 */
	private function execute_tool_call( array $tool_call ) {
		$tool_name = $tool_call['function']['name'];
		$arguments = json_decode( $tool_call['function']['arguments'], true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'invalid_tool_arguments',
				__( 'Failed to parse tool arguments', 'wyvern-ai-styling' ),
				array(
					'tool'       => $tool_name,
					'json_error' => json_last_error_msg(),
				)
			);
		}

		// Execute tool via MCP registry (if available).
		if ( null === $this->tool_registry ) {
			return new WP_Error(
				'no_tool_registry',
				__( 'Tool execution not available in CSS styling mode', 'wyvern-ai-styling' ),
				array( 'tool' => $tool_name )
			);
		}
		$start_time     = microtime( true );
		$result         = $this->tool_registry->call_tool( $tool_name, $arguments );
		$execution_time = ( microtime( true ) - $start_time ) * 1000; // Convert to ms.
		if ( is_wp_error( $result ) ) {
			$result->add_data(
				array(
					'execution_time_ms' => round( $execution_time, 2 ),
					'tool_name'         => $tool_name,
				)
			);
			return $result;
		}

		// Add execution metadata.
		if ( is_array( $result ) ) {
			$result['_meta'] = array(
				'execution_time_ms' => round( $execution_time, 2 ),
				'tool_name'         => $tool_name,
				'timestamp'         => current_time( 'mysql' ),
			);
		}

		return $result;
	}

	/**
	 * Parse OpenRouter API response
	 *
	 * @param array<string, mixed> $response Raw API response.
	 * @return array<string, mixed> Parsed response data.
	 */
	private function parse_response( array $response ): array {
		$choice  = $response['choices'][0] ?? array();
		$message = $choice['message'] ?? array();

		return array(
			'content'       => $message['content'] ?? '',
			'role'          => $message['role'] ?? 'assistant',
			'tool_calls'    => $message['tool_calls'] ?? array(),
			'finish_reason' => $choice['finish_reason'] ?? 'stop',
			'usage'         => array(
				'prompt_tokens'     => $response['usage']['prompt_tokens'] ?? 0,
				'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
				'total_tokens'      => $response['usage']['total_tokens'] ?? 0,
			),
			'model'         => $response['model'] ?? '',
			'id'            => $response['id'] ?? '',
		);
	}

	/**
	 * Verify API key is valid
	 *
	 * Tests the API key by making a request to OpenRouter.
	 *
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	public function verify_api_key() {
		$response = wp_remote_get(
			self::API_BASE_URL . '/auth/key',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			return new WP_Error(
				'invalid_api_key',
				__( 'Invalid OpenRouter API key', 'wyvern-ai-styling' ),
				array( 'status' => $status )
			);
		}

		return true;
	}

	/**
	 * Get available models from OpenRouter
	 *
	 * @return array<int, mixed>|WP_Error List of available models or error.
	 */
	public function get_available_models() {
		$response = wp_remote_get(
			self::API_BASE_URL . '/models',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['data'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid models response from OpenRouter', 'wyvern-ai-styling' )
			);
		}

		return $data['data'];
	}

	/**
	 * Calculate estimated cost for a request
	 *
	 * @param array<int, array<string, mixed>> $messages Conversation messages.
	 * @param string                           $model    Model identifier.
	 * @return array<string, mixed> Cost estimate details.
	 */
	public function estimate_cost( array $messages, string $model ): array {
		// Rough token estimation: 1 token ≈ 4 characters.
		$total_chars = 0;

		foreach ( $messages as $message ) {
			$total_chars += strlen( $message['content'] ?? '' );
		}

		$estimated_tokens = (int) ceil( $total_chars / 4 );

		// Get model pricing.
		$pricing = $this->get_model_pricing( $model );

		$input_cost  = ( $estimated_tokens / 1000 ) * $pricing['input_cost_per_1k'];
		$output_cost = ( $estimated_tokens / 1000 ) * $pricing['output_cost_per_1k'];

		return array(
			'estimated_input_tokens'  => $estimated_tokens,
			'estimated_output_tokens' => $estimated_tokens,
			'estimated_input_cost'    => round( $input_cost, 6 ),
			'estimated_output_cost'   => round( $output_cost, 6 ),
			'estimated_total_cost'    => round( $input_cost + $output_cost, 6 ),
			'currency'                => 'USD',
		);
	}

	/**
	 * Get pricing information for a model
	 *
	 * @param string $model Model identifier.
	 * @return array<string, float> Pricing data (input/output cost per 1k tokens).
	 */
	private function get_model_pricing( string $model ): array {
		// Default pricing (from OpenRouter documentation).
		$pricing = array(
			'anthropic/claude-3.5-sonnet' => array(
				'input_cost_per_1k'  => 0.003,
				'output_cost_per_1k' => 0.015,
			),
			'anthropic/claude-3-haiku'    => array(
				'input_cost_per_1k'  => 0.00025,
				'output_cost_per_1k' => 0.00125,
			),
			'anthropic/claude-3-opus'     => array(
				'input_cost_per_1k'  => 0.015,
				'output_cost_per_1k' => 0.075,
			),
			'openai/gpt-4-turbo'          => array(
				'input_cost_per_1k'  => 0.01,
				'output_cost_per_1k' => 0.03,
			),
			'openai/gpt-3.5-turbo'        => array(
				'input_cost_per_1k'  => 0.0005,
				'output_cost_per_1k' => 0.0015,
			),
		);

		// Return model-specific pricing or default.
		return $pricing[ $model ] ?? array(
			'input_cost_per_1k'  => 0.001,
			'output_cost_per_1k' => 0.002,
		);
	}

	/**
	 * Build system prompt with available tools
	 *
	 * @return string System prompt describing available tools.
	 */
	public function build_system_prompt(): string {
		// No tools in CSS styling mode.
		if ( null === $this->tool_registry ) {
			return __( 'You are WyvernCSS AI, a CSS styling assistant for WordPress Gutenberg blocks. Generate clean, valid CSS based on user descriptions.', 'wyvern-ai-styling' );
		}

		$tools_list = $this->tool_registry->get_tools_list();

		if ( empty( $tools_list ) ) {
			return __( 'You are WyvernCSS AI, a helpful WordPress assistant.', 'wyvern-ai-styling' );
		}

		$tool_descriptions = array_map(
			function ( $tool ) {
				return sprintf( '- %s: %s', $tool['name'], $tool['description'] );
			},
			$tools_list
		);

		return sprintf(
			"You are WyvernCSS AI, a helpful WordPress assistant with access to WordPress management tools.\n\n" .
			"Available WordPress Tools:\n%s\n\n" .
			"Guidelines:\n" .
			"- Use tools to perform WordPress operations when requested\n" .
			"- Always confirm successful operations with details (post ID, URL, etc.)\n" .
			"- If a tool fails, explain the error clearly and suggest alternatives\n" .
			"- Be concise but informative\n" .
			'- Respect WordPress capabilities - only suggest actions the user can perform',
			implode( "\n", $tool_descriptions )
		);
	}

	/**
	 * Direct chat completion without tool calling
	 *
	 * Simplified method for direct AI completions without MCP tool integration.
	 * Used primarily by CSS Generator for simple text generation.
	 *
	 * @param string                           $model    Model identifier.
	 * @param array<int, array<string, mixed>> $messages Array of message objects.
	 * @param array<string, mixed>             $options  Additional options (temperature, max_tokens, etc.).
	 * @return array<string, mixed>|WP_Error Response data or error.
	 */
	public function chat_completion( string $model, array $messages, array $options = array() ) {
		// Build request payload without tools.
		$payload = array(
			'model'    => $model,
			'messages' => $messages,
		);

		// Merge additional options.
		$payload = array_merge( $payload, $options );

		// Send request to OpenRouter.
		$response = $this->send_request( '/chat/completions', $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}
}
