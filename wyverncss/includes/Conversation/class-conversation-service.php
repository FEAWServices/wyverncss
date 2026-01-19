<?php
/**
 * Conversation Service
 *
 * Manages AI chat conversations and messages.
 *
 * @package WyvernCSS\Conversation
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Conversation;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\Database\Migration_Conversations;
use WyvernCSS\Database\Migration_Messages;
use WP_Error;

/**
 * Class Conversation_Service
 *
 * Handles conversation and message CRUD operations, context building,
 * and conversation history management.
 */
class Conversation_Service {

	/**
	 * Conversations table name
	 *
	 * @var string
	 */
	private string $conversations_table;

	/**
	 * Messages table name
	 *
	 * @var string
	 */
	private string $messages_table;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->conversations_table = $wpdb->prefix . 'wyverncss_conversations';
		$this->messages_table      = $wpdb->prefix . 'wyverncss_messages';
	}

	/**
	 * Create new conversation
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $title   Conversation title.
	 * @param string $model   AI model to use.
	 * @return int|WP_Error Conversation ID or error.
	 */
	public function create_conversation( int $user_id, string $title = 'New Conversation', string $model = 'anthropic/claude-3.5-sonnet' ) {
		global $wpdb;

		// Sanitize inputs.
		$title = sanitize_text_field( $title );
		$model = sanitize_text_field( $model );

		// Validate title length.
		if ( strlen( $title ) > 255 ) {
			return new WP_Error(
				'invalid_title',
				__( 'Conversation title cannot exceed 255 characters', 'wyvern-ai-styling' ),
				array( 'status' => 400 )
			);
		}

		// Insert conversation.
		$result = $wpdb->insert(
			$this->conversations_table,
			array(
				'user_id' => $user_id,
				'title'   => $title,
				'model'   => $model,
			),
			array( '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to create conversation', 'wyvern-ai-styling' ),
				array(
					'status' => 500,
					'error'  => $wpdb->last_error,
				)
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Save message to conversation
	 *
	 * @param int                       $conversation_id Conversation ID.
	 * @param string                    $role            Message role (user, assistant, system, tool).
	 * @param string                    $content         Message content.
	 * @param array<string, mixed>|null $tool_calls      Tool calls made (for assistant messages).
	 * @param int                       $tokens_used     Tokens consumed.
	 * @param float                     $cost_usd        Cost in USD.
	 * @return int|WP_Error Message ID or error.
	 */
	public function save_message(
		int $conversation_id,
		string $role,
		string $content,
		?array $tool_calls = null,
		int $tokens_used = 0,
		float $cost_usd = 0.0
	) {
		global $wpdb;

		// Validate role.
		$valid_roles = array( 'system', 'user', 'assistant', 'tool' );
		if ( ! in_array( $role, $valid_roles, true ) ) {
			return new WP_Error(
				'invalid_role',
				sprintf(
					/* translators: %s: Valid role values */
					__( 'Invalid role. Must be one of: %s', 'wyvern-ai-styling' ),
					implode( ', ', $valid_roles )
				),
				array( 'status' => 400 )
			);
		}

		// Validate conversation exists.
		if ( ! $this->conversation_exists( $conversation_id ) ) {
			return new WP_Error(
				'conversation_not_found',
				__( 'Conversation not found', 'wyvern-ai-styling' ),
				array( 'status' => 404 )
			);
		}

		// Encode tool calls as JSON.
		$tool_calls_json = null;
		if ( null !== $tool_calls && ! empty( $tool_calls ) ) {
			$tool_calls_json = wp_json_encode( $tool_calls );
			if ( false === $tool_calls_json ) {
				return new WP_Error(
					'json_encode_error',
					__( 'Failed to encode tool calls', 'wyvern-ai-styling' ),
					array( 'status' => 500 )
				);
			}
		}

		// Insert message.
		$result = $wpdb->insert(
			$this->messages_table,
			array(
				'conversation_id' => $conversation_id,
				'role'            => $role,
				'content'         => $content,
				'tool_calls'      => $tool_calls_json,
				'tokens_used'     => $tokens_used,
				'cost_usd'        => $cost_usd,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%f' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to save message', 'wyvern-ai-styling' ),
				array(
					'status' => 500,
					'error'  => $wpdb->last_error,
				)
			);
		}

		// Update conversation's updated_at timestamp.
		$wpdb->update(
			$this->conversations_table,
			array( 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $conversation_id ),
			array( '%s' ),
			array( '%d' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get conversation history
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $limit           Maximum messages to return (0 for all).
	 * @param int $offset          Offset for pagination.
	 * @return array<int, array<string, mixed>>|WP_Error Array of messages or error.
	 */
	public function get_conversation_history( int $conversation_id, int $limit = 20, int $offset = 0 ) {
		global $wpdb;

		// Validate conversation exists.
		if ( ! $this->conversation_exists( $conversation_id ) ) {
			return new WP_Error(
				'conversation_not_found',
				__( 'Conversation not found', 'wyvern-ai-styling' ),
				array( 'status' => 404 )
			);
		}

		$query = $wpdb->prepare(
			'SELECT id, conversation_id, role, content, tool_calls, tokens_used, cost_usd, created_at
			FROM %i
			WHERE conversation_id = %d
			ORDER BY created_at ASC, id ASC',
			$this->messages_table,
			$conversation_id
		);

		if ( $limit > 0 ) {
			$query .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}

		$messages = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $messages ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to retrieve conversation history', 'wyvern-ai-styling' ),
				array(
					'status' => 500,
					'error'  => $wpdb->last_error,
				)
			);
		}

		// Decode JSON fields.
		foreach ( $messages as &$message ) {
			$message['id']              = (int) $message['id'];
			$message['conversation_id'] = (int) $message['conversation_id'];
			$message['tokens_used']     = (int) $message['tokens_used'];
			$message['cost_usd']        = (float) $message['cost_usd'];

			if ( null !== $message['tool_calls'] ) {
				$decoded               = json_decode( $message['tool_calls'], true );
				$message['tool_calls'] = is_array( $decoded ) ? $decoded : array();
			} else {
				$message['tool_calls'] = array();
			}
		}

		return $messages;
	}

	/**
	 * Get user's conversations
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param int    $limit   Maximum conversations to return.
	 * @param int    $offset  Offset for pagination.
	 * @param string $order   Sort order (asc or desc).
	 * @return array<int, array<string, mixed>>|WP_Error Array of conversations or error.
	 */
	public function get_user_conversations( int $user_id, int $limit = 20, int $offset = 0, string $order = 'desc' ) {
		global $wpdb;

		// Validate order.
		$order = strtolower( $order );
		if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = 'desc';
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order is sanitized to 'asc' or 'desc' above.
		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					c.id,
					c.user_id,
					c.title,
					c.model,
					c.created_at,
					c.updated_at,
					COUNT(m.id) as message_count,
					COALESCE(SUM(m.cost_usd), 0) as total_cost
				FROM %i c
				LEFT JOIN %i m ON c.id = m.conversation_id
				WHERE c.user_id = %d
				GROUP BY c.id
				ORDER BY c.updated_at {$order}
				LIMIT %d OFFSET %d",
				$this->conversations_table,
				$this->messages_table,
				$user_id,
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( null === $conversations ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to retrieve conversations', 'wyvern-ai-styling' ),
				array(
					'status' => 500,
					'error'  => $wpdb->last_error,
				)
			);
		}

		// Type cast numeric values.
		foreach ( $conversations as &$conversation ) {
			$conversation['id']            = (int) $conversation['id'];
			$conversation['user_id']       = (int) $conversation['user_id'];
			$conversation['message_count'] = (int) $conversation['message_count'];
			$conversation['total_cost']    = (float) $conversation['total_cost'];
		}

		return $conversations;
	}

	/**
	 * Delete conversation and all its messages
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id         User ID (for permission check).
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function delete_conversation( int $conversation_id, int $user_id ) {
		global $wpdb;

		// Verify conversation belongs to user.
		$ownership_check = $this->verify_conversation_ownership( $conversation_id, $user_id, 'delete' );
		if ( is_wp_error( $ownership_check ) ) {
			return $ownership_check;
		}

		// Delete messages first.
		$wpdb->delete(
			$this->messages_table,
			array( 'conversation_id' => $conversation_id ),
			array( '%d' )
		);

		// Delete conversation.
		$result = $wpdb->delete(
			$this->conversations_table,
			array( 'id' => $conversation_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to delete conversation', 'wyvern-ai-styling' ),
				array(
					'status' => 500,
					'error'  => $wpdb->last_error,
				)
			);
		}

		return true;
	}

	/**
	 * Build context for AI from conversation history
	 *
	 * Retrieves recent messages and formats them for AI API.
	 *
	 * @param int  $conversation_id  Conversation ID.
	 * @param int  $message_limit    Maximum messages to include.
	 * @param bool $include_system  Whether to include system prompt.
	 * @return array<int, array<string, mixed>>|WP_Error Array of messages formatted for AI or error.
	 */
	public function build_context( int $conversation_id, int $message_limit = 20, bool $include_system = true ) {
		global $wpdb;

		// Get recent messages.
		$messages = $this->get_conversation_history( $conversation_id, $message_limit );

		if ( is_wp_error( $messages ) ) {
			return $messages;
		}

		// Format for AI API.
		$context = array();

		// Add system prompt if requested.
		if ( $include_system ) {
			$context[] = array(
				'role'    => 'system',
				'content' => $this->get_system_prompt(),
			);
		}

		// Add conversation messages.
		foreach ( $messages as $message ) {
			$formatted = array(
				'role'    => $message['role'],
				'content' => $message['content'],
			);

			// Include tool calls for assistant messages.
			if ( 'assistant' === $message['role'] && ! empty( $message['tool_calls'] ) ) {
				$formatted['tool_calls'] = $message['tool_calls'];
			}

			$context[] = $formatted;
		}

		return $context;
	}

	/**
	 * Get system prompt for AI
	 *
	 * @return string System prompt.
	 */
	private function get_system_prompt(): string {
		return apply_filters(
			'wyverncss_ai_system_prompt',
			__( 'You are WyvernCSS AI, a helpful WordPress assistant with access to WordPress management tools. Use the available tools to help users manage their WordPress site. Always confirm successful operations and explain any errors clearly.', 'wyvern-ai-styling' )
		);
	}

	/**
	 * Check if conversation exists
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return bool True if exists, false otherwise.
	 */
	private function conversation_exists( int $conversation_id ): bool {
		global $wpdb;

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE id = %d',
				$this->conversations_table,
				$conversation_id
			)
		);

		return null !== $exists;
	}

	/**
	 * Get conversation by ID
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return array<string, mixed>|WP_Error Conversation data or error.
	 */
	public function get_conversation( int $conversation_id ) {
		global $wpdb;

		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$this->conversations_table,
				$conversation_id
			),
			ARRAY_A
		);

		if ( null === $conversation ) {
			return new WP_Error(
				'conversation_not_found',
				__( 'Conversation not found', 'wyvern-ai-styling' ),
				array( 'status' => 404 )
			);
		}

		// Type cast.
		$conversation['id']      = (int) $conversation['id'];
		$conversation['user_id'] = (int) $conversation['user_id'];

		return $conversation;
	}

	/**
	 * Update conversation title
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $title           New title.
	 * @param int    $user_id         User ID (for permission check).
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function update_conversation_title( int $conversation_id, string $title, int $user_id ) {
		global $wpdb;

		// Validate title.
		$title = sanitize_text_field( $title );
		if ( strlen( $title ) > 255 ) {
			return new WP_Error(
				'invalid_title',
				__( 'Title cannot exceed 255 characters', 'wyvern-ai-styling' ),
				array( 'status' => 400 )
			);
		}

		// Verify ownership.
		$ownership_check = $this->verify_conversation_ownership( $conversation_id, $user_id, 'update' );
		if ( is_wp_error( $ownership_check ) ) {
			return $ownership_check;
		}

		// Update title.
		$result = $wpdb->update(
			$this->conversations_table,
			array( 'title' => $title ),
			array( 'id' => $conversation_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to update conversation title', 'wyvern-ai-styling' ),
				array(
					'status' => 500,
					'error'  => $wpdb->last_error,
				)
			);
		}

		return true;
	}

	/**
	 * Get total conversation count for user
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Total conversations.
	 */
	public function get_conversation_count( int $user_id ): int {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE user_id = %d',
				$this->conversations_table,
				$user_id
			)
		);

		return null !== $count ? (int) $count : 0;
	}

	/**
	 * Verify conversation ownership
	 *
	 * Checks if a conversation exists and belongs to the specified user.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param int    $user_id         User ID.
	 * @param string $action          Action being performed (for error message).
	 * @return true|WP_Error True if user owns conversation, WP_Error otherwise.
	 */
	private function verify_conversation_ownership( int $conversation_id, int $user_id, string $action = 'access' ) {
		global $wpdb;

		$owner = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT user_id FROM %i WHERE id = %d',
				$this->conversations_table,
				$conversation_id
			)
		);

		if ( null === $owner ) {
			return new WP_Error(
				'conversation_not_found',
				__( 'Conversation not found', 'wyvern-ai-styling' ),
				array( 'status' => 404 )
			);
		}

		if ( (int) $owner !== $user_id ) {
			return new WP_Error(
				'permission_denied',
				sprintf(
					/* translators: %s: action name */
					__( 'You do not have permission to %s this conversation', 'wyvern-ai-styling' ),
					$action
				),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
