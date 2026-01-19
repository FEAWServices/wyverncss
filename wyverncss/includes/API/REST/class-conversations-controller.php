<?php
/**
 * Conversations REST Controller
 *
 * Manages conversation history and memory for AI bots.
 * Provides endpoints for conversation retrieval, summarization, and context optimization.
 *
 * @package    WyvernCSS
 * @subpackage WyvernCSS/includes/API/REST
 * @since      1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\API\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WyvernCSS\Conversation\Conversation_Service;
use WyvernCSS\API\REST\Traits\Cloud_Service_Proxy;

/**
 * Conversations REST Controller Class
 *
 * Provides REST endpoints for conversation management with RAG integration.
 */
class Conversations_Controller extends WP_REST_Controller {

	use Cloud_Service_Proxy;

	/**
	 * API namespace.
	 */
	private const NAMESPACE = 'wyverncss/v1';

	/**
	 * REST base path.
	 */
	private const REST_BASE = 'conversations';

	/**
	 * Conversation service instance.
	 *
	 * @var Conversation_Service
	 */
	private Conversation_Service $conversation_service;

	/**
	 * Constructor.
	 *
	 * @param Conversation_Service $conversation_service Conversation service instance.
	 * @param string               $cloud_service_url    Optional. Cloud service URL.
	 */
	public function __construct(
		Conversation_Service $conversation_service,
		string $cloud_service_url = ''
	) {
		$this->namespace            = self::NAMESPACE;
		$this->rest_base            = self::REST_BASE;
		$this->conversation_service = $conversation_service;
		$this->init_cloud_service_url( $cloud_service_url );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /conversations - List conversations.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversations' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_collection_args(),
			)
		);

		// GET /conversations/{id} - Get single conversation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversation' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'description'       => __( 'Conversation ID', 'wyvern-ai-styling' ),
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// GET /conversations/{id}/relevant-history - Get relevant conversation history.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/relevant-history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_relevant_history' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_relevant_history_args(),
			)
		);

		// POST /conversations/{id}/summarize - Summarize conversation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/summarize',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'summarize_conversation' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_summarize_args(),
			)
		);

		// POST /conversations/{id}/optimize-context - Optimize conversation context.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/optimize-context',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'optimize_context' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_optimize_context_args(),
			)
		);

		// DELETE /conversations/{id} - Delete conversation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_conversation' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'description'       => __( 'Conversation ID', 'wyvern-ai-styling' ),
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Get conversations list.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function get_conversations( WP_REST_Request $request ) {
		$user_id  = get_current_user_id();
		$bot_slug = $request->get_param( 'bot_slug' );
		$limit    = $request->get_param( 'limit' ) ?? 20;
		$offset   = $request->get_param( 'offset' ) ?? 0;

		// Get conversations from service.
		$conversations = $this->conversation_service->get_user_conversations(
			$user_id,
			$bot_slug,
			$limit,
			$offset
		);

		return new WP_REST_Response(
			array(
				'conversations' => $conversations,
				'count'         => count( $conversations ),
				'limit'         => $limit,
				'offset'        => $offset,
			),
			200
		);
	}

	/**
	 * Get single conversation.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function get_conversation( WP_REST_Request $request ) {
		$conversation_id = $request->get_param( 'id' );
		$conversation    = $this->get_conversation_with_permission_check( $conversation_id );

		if ( is_wp_error( $conversation ) ) {
			return $conversation;
		}

		return new WP_REST_Response( $conversation, 200 );
	}

	/**
	 * Get relevant conversation history using semantic search.
	 *
	 * Retrieves the most relevant messages from conversation history
	 * based on the current query using RAG semantic search.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function get_relevant_history( WP_REST_Request $request ) {
		$conversation_id = $request->get_param( 'id' );
		$query           = $request->get_param( 'query' );
		$max_messages    = $request->get_param( 'max_messages' ) ?? 10;

		// Get conversation with permission check.
		$conversation = $this->get_conversation_with_permission_check( $conversation_id );

		if ( is_wp_error( $conversation ) ) {
			return $conversation;
		}

		$license_key = get_option( 'wyverncss_license_key', '' );

		if ( empty( $license_key ) ) {
			// Fallback to recency-based history if no license.
			$messages = $this->conversation_service->get_conversation_history(
				$conversation_id,
				$max_messages
			);

			return new WP_REST_Response(
				array(
					'messages'      => $messages,
					'count'         => count( $messages ),
					'search_method' => 'recency',
				),
				200
			);
		}

		// Use RAG semantic search to find relevant messages.
		$search_body = array(
			'query'          => $query,
			'content_types'  => array( 'conversation_message' ),
			'limit'          => $max_messages,
			'min_similarity' => 0.5,
		);

		$response = $this->proxy_to_cloud_service(
			'/api/v1/rag/search',
			'POST',
			$search_body,
			$license_key,
			'Conversations'
		);

		if ( is_wp_error( $response ) ) {
			// Fallback to recency if search fails.
			$messages = $this->conversation_service->get_conversation_history(
				$conversation_id,
				$max_messages
			);

			return new WP_REST_Response(
				array(
					'messages'      => $messages,
					'count'         => count( $messages ),
					'search_method' => 'recency_fallback',
				),
				200
			);
		}

		// Extract messages from search results.
		$relevant_messages = array();
		if ( isset( $response['results'] ) && is_array( $response['results'] ) ) {
			foreach ( $response['results'] as $result ) {
				$relevant_messages[] = array(
					'role'            => $result['content_type'] ?? 'user',
					'content'         => $result['excerpt'] ?? '',
					'relevance_score' => $result['similarity_score'] ?? 0.0,
				);
			}
		}

		return new WP_REST_Response(
			array(
				'messages'      => $relevant_messages,
				'count'         => count( $relevant_messages ),
				'search_method' => 'semantic',
			),
			200
		);
	}

	/**
	 * Summarize conversation.
	 *
	 * Generates a summary of the conversation using AI.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function summarize_conversation( WP_REST_Request $request ) {
		$conversation_id = $request->get_param( 'id' );
		$max_length      = $request->get_param( 'max_length' ) ?? 500;

		// Get conversation with permission check.
		$conversation = $this->get_conversation_with_permission_check( $conversation_id );

		if ( is_wp_error( $conversation ) ) {
			return $conversation;
		}

		// Get conversation history.
		$messages = $this->conversation_service->get_conversation_history( $conversation_id );

		if ( empty( $messages ) ) {
			return new WP_Error(
				'no_messages',
				__( 'No messages to summarize', 'wyvern-ai-styling' ),
				array( 'status' => 400 )
			);
		}

		// Build summary prompt.
		$summary_prompt = sprintf(
			'Summarize the following conversation in %d words or less:\n\n',
			$max_length
		);

		foreach ( $messages as $message ) {
			$summary_prompt .= sprintf(
				"%s: %s\n",
				ucfirst( $message['role'] ),
				$message['content']
			);
		}

		// Use RAG generate to create summary.
		$license_key = get_option( 'wyverncss_license_key', '' );

		if ( empty( $license_key ) ) {
			return new WP_Error(
				'license_required',
				__( 'License key required for conversation summarization', 'wyvern-ai-styling' ),
				array( 'status' => 401 )
			);
		}

		$response = $this->proxy_to_cloud_service(
			'/api/v1/rag/generate',
			'POST',
			array(
				'bot_slug'     => $conversation['bot_slug'] ?? 'default',
				'user_message' => $summary_prompt,
				'enable_rag'   => false,
			),
			$license_key,
			'Conversations'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new WP_REST_Response(
			array(
				'summary'         => $response['response'] ?? '',
				'conversation_id' => $conversation_id,
				'message_count'   => count( $messages ),
			),
			200
		);
	}

	/**
	 * Optimize conversation context.
	 *
	 * Reduces conversation context to most relevant messages for token efficiency.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function optimize_context( WP_REST_Request $request ) {
		$conversation_id = $request->get_param( 'id' );
		$current_query   = $request->get_param( 'current_query' );
		$max_messages    = $request->get_param( 'max_messages' ) ?? 5;

		// Get conversation with permission check.
		$conversation = $this->get_conversation_with_permission_check( $conversation_id );

		if ( is_wp_error( $conversation ) ) {
			return $conversation;
		}

		// Use relevant history endpoint to get optimized context.
		$history_request = new WP_REST_Request( 'GET', sprintf( '/wyverncss/v1/conversations/%d/relevant-history', $conversation_id ) );
		$history_request->set_param( 'id', $conversation_id );
		$history_request->set_param( 'query', $current_query );
		$history_request->set_param( 'max_messages', $max_messages );

		$history_response = $this->get_relevant_history( $history_request );

		if ( is_wp_error( $history_response ) ) {
			return $history_response;
		}

		$history_data = $history_response->get_data();

		return new WP_REST_Response(
			array(
				'optimized_context' => $history_data['messages'] ?? array(),
				'original_count'    => $this->conversation_service->get_message_count( $conversation_id ),
				'optimized_count'   => count( $history_data['messages'] ?? array() ),
				'search_method'     => $history_data['search_method'] ?? 'unknown',
			),
			200
		);
	}

	/**
	 * Delete conversation.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function delete_conversation( WP_REST_Request $request ) {
		$conversation_id = $request->get_param( 'id' );

		// Get conversation with permission check.
		$conversation = $this->get_conversation_with_permission_check( $conversation_id );

		if ( is_wp_error( $conversation ) ) {
			return $conversation;
		}

		// Delete conversation.
		$deleted = $this->conversation_service->delete_conversation( $conversation_id );

		if ( ! $deleted ) {
			return new WP_Error(
				'delete_failed',
				__( 'Failed to delete conversation', 'wyvern-ai-styling' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Conversation deleted successfully', 'wyvern-ai-styling' ),
			),
			200
		);
	}

	/**
	 * Get conversation with permission check.
	 *
	 * Retrieves conversation and verifies the current user has permission to access it.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return array<string, mixed>|WP_Error Conversation data or error.
	 */
	private function get_conversation_with_permission_check( int $conversation_id ) {
		$user_id = get_current_user_id();

		// Get conversation.
		$conversation = $this->conversation_service->get_conversation( $conversation_id );

		if ( ! $conversation ) {
			return new WP_Error(
				'conversation_not_found',
				__( 'Conversation not found', 'wyvern-ai-styling' ),
				array( 'status' => 404 )
			);
		}

		// Verify ownership.
		if ( (int) $conversation['user_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have permission to access this conversation', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		return $conversation;
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array<string, mixed> Collection parameters.
	 */
	private function get_collection_args(): array {
		return array(
			'bot_slug' => array(
				'description'       => __( 'Filter by bot slug', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'limit'    => array(
				'description' => __( 'Maximum conversations to return', 'wyvern-ai-styling' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'offset'   => array(
				'description' => __( 'Offset for pagination', 'wyvern-ai-styling' ),
				'type'        => 'integer',
				'default'     => 0,
				'minimum'     => 0,
			),
		);
	}

	/**
	 * Get relevant history parameters.
	 *
	 * @return array<string, mixed> Relevant history parameters.
	 */
	private function get_relevant_history_args(): array {
		return array(
			'id'           => array(
				'description'       => __( 'Conversation ID', 'wyvern-ai-styling' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'query'        => array(
				'description'       => __( 'Current query for context relevance', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'max_messages' => array(
				'description' => __( 'Maximum messages to return', 'wyvern-ai-styling' ),
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 50,
			),
		);
	}

	/**
	 * Get summarize parameters.
	 *
	 * @return array<string, mixed> Summarize parameters.
	 */
	private function get_summarize_args(): array {
		return array(
			'id'         => array(
				'description'       => __( 'Conversation ID', 'wyvern-ai-styling' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'max_length' => array(
				'description' => __( 'Maximum summary length in words', 'wyvern-ai-styling' ),
				'type'        => 'integer',
				'default'     => 500,
				'minimum'     => 50,
				'maximum'     => 2000,
			),
		);
	}

	/**
	 * Get optimize context parameters.
	 *
	 * @return array<string, mixed> Optimize context parameters.
	 */
	private function get_optimize_context_args(): array {
		return array(
			'id'            => array(
				'description'       => __( 'Conversation ID', 'wyvern-ai-styling' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'current_query' => array(
				'description'       => __( 'Current user query', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'max_messages'  => array(
				'description' => __( 'Maximum messages in optimized context', 'wyvern-ai-styling' ),
				'type'        => 'integer',
				'default'     => 5,
				'minimum'     => 1,
				'maximum'     => 20,
			),
		);
	}

	/**
	 * Check if user has permission to access conversations.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return bool|WP_Error True if user can access, error otherwise.
	 */
	public function check_permission( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to access conversations.', 'wyvern-ai-styling' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}
}
