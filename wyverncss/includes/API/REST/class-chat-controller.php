<?php
/**
 * Chat REST API Controller
 *
 * Handles REST API endpoints for AI chat functionality.
 *
 * @package WyvernCSS\API\REST
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\API\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\AI\OpenRouter_Client;
use WyvernCSS\Conversation\Conversation_Service;
use WyvernCSS\Settings\Settings_Service;
use WyvernCSS\MCP\ToolRegistry;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Chat_Controller
 *
 * REST API controller for chat operations.
 */
class Chat_Controller extends WP_REST_Controller {

	use Controller_Helpers;

	/**
	 * API namespace
	 */
	private const NAMESPACE = 'wyverncss/v1';

	/**
	 * Conversation service instance
	 *
	 * @var Conversation_Service
	 */
	private Conversation_Service $conversation_service;

	/**
	 * Settings service instance
	 *
	 * @var Settings_Service
	 */
	private Settings_Service $settings_service;

	/**
	 * MCP Tool Registry
	 *
	 * @var ToolRegistry
	 */
	private ToolRegistry $tool_registry;

	/**
	 * Constructor
	 *
	 * @param Conversation_Service $conversation_service Conversation service instance.
	 * @param Settings_Service     $settings_service     Settings service instance.
	 * @param ToolRegistry         $tool_registry        MCP tool registry instance.
	 */
	public function __construct(
		Conversation_Service $conversation_service,
		Settings_Service $settings_service,
		ToolRegistry $tool_registry
	) {
		$this->conversation_service = $conversation_service;
		$this->settings_service     = $settings_service;
		$this->tool_registry        = $tool_registry;
	}

	/**
	 * Register REST API routes
	 *
	 * @return void */
	public function register_routes(): void {
		// POST /chat/message - Send message to AI.
		register_rest_route(
			self::NAMESPACE,
			'/chat/message',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_message' ),
				'permission_callback' => array( $this, 'check_chat_permission' ),
				'args'                => $this->get_send_message_args(),
			)
		);

		// GET /chat/conversations - List user's conversations.
		register_rest_route(
			self::NAMESPACE,
			'/chat/conversations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_conversations' ),
					'permission_callback' => array( $this, 'check_chat_permission' ),
					'args'                => $this->get_conversations_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_conversation' ),
					'permission_callback' => array( $this, 'check_chat_permission' ),
					'args'                => $this->get_create_conversation_args(),
				),
			)
		);

		// DELETE /chat/conversations/{id} - Delete conversation.
		register_rest_route(
			self::NAMESPACE,
			'/chat/conversations/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_conversation' ),
				'permission_callback' => array( $this, 'check_chat_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// GET /chat/history/{conversation_id} - Get conversation history.
		register_rest_route(
			self::NAMESPACE,
			'/chat/history/(?P<conversation_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_history' ),
				'permission_callback' => array( $this, 'check_chat_permission' ),
				'args'                => $this->get_history_args(),
			)
		);
	}

	/**
	 * Send message to AI
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function send_message( WP_REST_Request $request ) {
		$user_id         = get_current_user_id();
		$message         = sanitize_textarea_field( $request->get_param( 'message' ) );
		$conversation_id = $request->get_param( 'conversation_id' );
		$model           = $request->get_param( 'model' ) ?? 'anthropic/claude-3.5-sonnet';

		// Validate message.
		$validation_result = $this->validate_message_length( $message, 1, 10000 );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		// Get user's API key.
		$api_key = $this->settings_service->get_api_key( $user_id );
		if ( null === $api_key ) {
			return new WP_Error(
				'missing_api_key',
				__( 'Please configure your OpenRouter API key in settings', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		// Create conversation if needed.
		if ( null === $conversation_id ) {
			$conversation_id = $this->conversation_service->create_conversation(
				$user_id,
				$this->generate_conversation_title( $message ),
				$model
			);

			if ( is_wp_error( $conversation_id ) ) {
				return $conversation_id;
			}
		} else {
			// Verify conversation belongs to user.
			$conversation = $this->conversation_service->get_conversation( $conversation_id );
			if ( is_wp_error( $conversation ) ) {
				return $conversation;
			}

			$ownership_check = $this->validate_conversation_ownership( $conversation, $user_id );
			if ( is_wp_error( $ownership_check ) ) {
				return $ownership_check;
			}
		}

		// Save user message.
		$user_message_id = $this->conversation_service->save_message(
			$conversation_id,
			'user',
			$message
		);

		if ( is_wp_error( $user_message_id ) ) {
			return $user_message_id;
		}

		// Build conversation context.
		$context = $this->conversation_service->build_context( $conversation_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		// Initialize OpenRouter client.
		$openrouter = new OpenRouter_Client( $api_key, $this->tool_registry );

		// Send message to AI.
		$ai_response = $openrouter->send_message(
			$message,
			$user_id,
			$context,
			$model
		);

		if ( is_wp_error( $ai_response ) ) {
			return $ai_response;
		}

		// Calculate cost from usage.
		$cost_usd = $this->calculate_cost(
			$ai_response['usage'] ?? array(),
			$model
		);

		// Save assistant response.
		$assistant_message_id = $this->conversation_service->save_message(
			$conversation_id,
			'assistant',
			$ai_response['content'] ?? '',
			$ai_response['tool_executions'] ?? null,
			$ai_response['usage']['total_tokens'] ?? 0,
			$cost_usd
		);

		if ( is_wp_error( $assistant_message_id ) ) {
			return $assistant_message_id;
		}

		// Return response.
		return new WP_REST_Response(
			array(
				'message'         => $ai_response['content'] ?? '',
				'tool_calls'      => $ai_response['tool_executions'] ?? array(),
				'conversation_id' => $conversation_id,
				'message_id'      => $assistant_message_id,
				'usage'           => array(
					'prompt_tokens'     => $ai_response['usage']['prompt_tokens'] ?? 0,
					'completion_tokens' => $ai_response['usage']['completion_tokens'] ?? 0,
					'total_tokens'      => $ai_response['usage']['total_tokens'] ?? 0,
					'estimated_cost'    => $cost_usd,
				),
			),
			200
		);
	}

	/**
	 * Get user's conversations
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function get_conversations( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$limit   = (int) $request->get_param( 'limit' ) ? (int) $request->get_param( 'limit' ) : 20;
		$offset  = (int) $request->get_param( 'offset' ) ? (int) $request->get_param( 'offset' ) : 0;
		$order   = $request->get_param( 'order' ) ? $request->get_param( 'order' ) : 'desc';

		$conversations = $this->conversation_service->get_user_conversations(
			$user_id,
			$limit,
			$offset,
			$order
		);

		if ( is_wp_error( $conversations ) ) {
			return $conversations;
		}

		$total = $this->conversation_service->get_conversation_count( $user_id );

		return new WP_REST_Response(
			array(
				'conversations' => $conversations,
				'total'         => $total,
				'limit'         => $limit,
				'offset'        => $offset,
			),
			200
		);
	}

	/**
	 * Create new conversation
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function create_conversation( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$title   = $request->get_param( 'title' ) ?? __( 'New Conversation', 'wyverncss' );
		$model   = $request->get_param( 'model' ) ?? 'anthropic/claude-3.5-sonnet';

		$conversation_id = $this->conversation_service->create_conversation(
			$user_id,
			sanitize_text_field( $title ),
			sanitize_text_field( $model )
		);

		if ( is_wp_error( $conversation_id ) ) {
			return $conversation_id;
		}

		$conversation = $this->conversation_service->get_conversation( $conversation_id );

		if ( is_wp_error( $conversation ) ) {
			return $conversation;
		}

		return new WP_REST_Response(
			array(
				'id'            => $conversation['id'],
				'title'         => $conversation['title'],
				'model'         => $conversation['model'],
				'message_count' => 0,
				'created_at'    => $conversation['created_at'],
				'updated_at'    => $conversation['updated_at'],
			),
			201
		);
	}

	/**
	 * Delete conversation
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function delete_conversation( WP_REST_Request $request ) {
		$user_id         = get_current_user_id();
		$conversation_id = (int) $request->get_param( 'id' );

		$result = $this->conversation_service->delete_conversation(
			$conversation_id,
			$user_id
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Conversation deleted successfully', 'wyverncss' ),
			),
			200
		);
	}

	/**
	 * Get conversation history
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function get_history( WP_REST_Request $request ) {
		$user_id         = get_current_user_id();
		$conversation_id = (int) $request->get_param( 'conversation_id' );
		$limit           = (int) $request->get_param( 'limit' ) ? (int) $request->get_param( 'limit' ) : 50;

		// Verify conversation ownership.
		$conversation = $this->conversation_service->get_conversation( $conversation_id );
		if ( is_wp_error( $conversation ) ) {
			return $conversation;
		}

		$ownership_check = $this->validate_conversation_ownership( $conversation, $user_id );
		if ( is_wp_error( $ownership_check ) ) {
			return $ownership_check;
		}

		$messages = $this->conversation_service->get_conversation_history(
			$conversation_id,
			$limit
		);

		if ( is_wp_error( $messages ) ) {
			return $messages;
		}

		return new WP_REST_Response(
			array(
				'conversation_id' => $conversation_id,
				'messages'        => $messages,
				'total'           => count( $messages ),
				'has_more'        => count( $messages ) >= $limit,
			),
			200
		);
	}

	/**
	 * Check if user has permission to use chat
	 *
	 * @return bool True if user can access chat, false otherwise.
	 */
	public function check_chat_permission(): bool {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Get arguments for send_message endpoint
	 *
	 * @return array<string, array<string, mixed>> Endpoint arguments.
	 */
	private function get_send_message_args(): array {
		return array(
			'message'         => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'conversation_id' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'model'           => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'anthropic/claude-3.5-sonnet',
			),
		);
	}

	/**
	 * Get arguments for get_conversations endpoint
	 *
	 * @return array<string, array<string, mixed>> Endpoint arguments.
	 */
	private function get_conversations_args(): array {
		return array(
			'limit'  => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 20,
			),
			'offset' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			),
			'order'  => array(
				'required' => false,
				'type'     => 'string',
				'enum'     => array( 'asc', 'desc' ),
				'default'  => 'desc',
			),
		);
	}

	/**
	 * Get arguments for create_conversation endpoint
	 *
	 * @return array<string, array<string, mixed>> Endpoint arguments.
	 */
	private function get_create_conversation_args(): array {
		return array(
			'title' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => __( 'New Conversation', 'wyverncss' ),
			),
			'model' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'anthropic/claude-3.5-sonnet',
			),
		);
	}

	/**
	 * Get arguments for get_history endpoint
	 *
	 * @return array<string, array<string, mixed>> Endpoint arguments.
	 */
	private function get_history_args(): array {
		return array(
			'conversation_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'limit'           => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 50,
			),
		);
	}

	/**
	 * Generate conversation title from first message
	 *
	 * @param string $message User's message.
	 * @return string Generated title.
	 */
	private function generate_conversation_title( string $message ): string {
		// Take first 50 characters or until first newline.
		$title = strtok( $message, "\n" );

		// Ensure strtok returned a string.
		if ( false === $title ) {
			return __( 'New Conversation', 'wyverncss' );
		}

		$title = substr( $title, 0, 50 );

		if ( strlen( $message ) > 50 ) {
			$title .= '...';
		}

		return $title ? $title : __( 'New Conversation', 'wyverncss' );
	}

	/**
	 * Calculate cost from token usage
	 *
	 * @param array<string, mixed> $usage Token usage data.
	 * @param string               $model Model identifier.
	 * @return float Cost in USD.
	 */
	private function calculate_cost( array $usage, string $model ): float {
		$pricing = $this->get_model_pricing( $model );

		$input_tokens  = $usage['prompt_tokens'] ?? 0;
		$output_tokens = $usage['completion_tokens'] ?? 0;

		$input_cost  = ( $input_tokens / 1000 ) * $pricing['input'];
		$output_cost = ( $output_tokens / 1000 ) * $pricing['output'];

		return round( $input_cost + $output_cost, 6 );
	}

	/**
	 * Get pricing for model
	 *
	 * @param string $model Model identifier.
	 * @return array<string, float> Pricing data (input/output per 1k tokens).
	 */
	private function get_model_pricing( string $model ): array {
		$pricing = array(
			'anthropic/claude-3.5-sonnet' => array(
				'input'  => 0.003,
				'output' => 0.015,
			),
			'anthropic/claude-3-haiku'    => array(
				'input'  => 0.00025,
				'output' => 0.00125,
			),
			'anthropic/claude-3-opus'     => array(
				'input'  => 0.015,
				'output' => 0.075,
			),
			'openai/gpt-4-turbo'          => array(
				'input'  => 0.01,
				'output' => 0.03,
			),
			'openai/gpt-3.5-turbo'        => array(
				'input'  => 0.0005,
				'output' => 0.0015,
			),
		);

		return $pricing[ $model ] ?? array(
			'input'  => 0.001,
			'output' => 0.002,
		);
	}
}
