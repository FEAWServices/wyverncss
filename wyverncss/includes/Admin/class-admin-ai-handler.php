<?php
/**
 * Admin AI Handler
 *
 * Main handler that parses natural language commands and routes them to
 * appropriate operation handlers (posts, media, settings).
 *
 * @package WyvernCSS
 * @subpackage Admin
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WyvernCSS\Admin\Handlers\Post_Operations_Handler;
use WyvernCSS\Admin\Handlers\Media_Operations_Handler;
use WyvernCSS\Admin\Handlers\Settings_Operations_Handler;
use WP_Error;

/**
 * Admin AI Handler Class
 *
 * Parses natural language commands and routes to specific handlers.
 * Handles confirmation flow for destructive actions.
 * Logs all actions to audit log.
 *
 * @since 1.0.0
 */
class Admin_AI_Handler {

	/**
	 * Post operations handler.
	 *
	 * @var Post_Operations_Handler
	 */
	private Post_Operations_Handler $post_handler;

	/**
	 * Media operations handler.
	 *
	 * @var Media_Operations_Handler
	 */
	private Media_Operations_Handler $media_handler;

	/**
	 * Settings operations handler.
	 *
	 * @var Settings_Operations_Handler
	 */
	private Settings_Operations_Handler $settings_handler;

	/**
	 * Audit logger.
	 *
	 * @var Audit_Logger
	 */
	private Audit_Logger $audit_logger;

	/**
	 * Command patterns for post operations.
	 *
	 * @var array<string, array<string>>
	 */
	private array $post_patterns = array(
		'bulk_update_categories' => array( 'add categories', 'update categories', 'set categories', 'categorize' ),
		'bulk_update_tags'       => array( 'add tags', 'update tags', 'set tags', 'tag posts' ),
		'bulk_publish_posts'     => array( 'publish posts', 'publish drafts', 'make posts live' ),
		'bulk_unpublish_posts'   => array( 'unpublish', 'set to draft', 'make private' ),
		'delete_old_drafts'      => array( 'delete old drafts', 'remove drafts', 'clean up drafts' ),
	);

	/**
	 * Command patterns for media operations.
	 *
	 * @var array<string, array<string>>
	 */
	private array $media_patterns = array(
		'find_unused_media'      => array( 'find unused media', 'find unattached', 'unused images' ),
		'organize_media_by_date' => array( 'organize media', 'organize by date', 'reorganize uploads' ),
		'bulk_generate_alt_text' => array( 'generate alt text', 'add alt text', 'alt tags' ),
	);

	/**
	 * Command patterns for settings operations.
	 *
	 * @var array<string, array<string>>
	 */
	private array $settings_patterns = array(
		'get_performance_recommendations' => array( 'performance recommendations', 'optimize performance', 'speed up' ),
		'get_security_recommendations'    => array( 'security recommendations', 'security check', 'improve security' ),
		'apply_recommendation'            => array( 'apply recommendation', 'fix issue', 'enable setting' ),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->post_handler     = new Post_Operations_Handler();
		$this->media_handler    = new Media_Operations_Handler();
		$this->settings_handler = new Settings_Operations_Handler();
		$this->audit_logger     = new Audit_Logger();
	}

	/**
	 * Execute a command.
	 *
	 * @param string               $command Natural language command.
	 * @param array<string, mixed> $params  Optional parameters.
	 * @param bool                 $confirm Whether user confirmed destructive action.
	 * @return array<string, mixed>|WP_Error Result or error.
	 */
	public function execute( string $command, array $params = array(), bool $confirm = false ) {
		// Normalize command for matching.
		$normalized_command = strtolower( trim( $command ) );

		// Try to match command to an operation.
		$operation = $this->match_operation( $normalized_command );

		if ( null === $operation ) {
			return new WP_Error(
				'unknown_command',
				sprintf(
					/* translators: %s: the command that was not understood */
					esc_html__( 'Unable to understand command: "%s". Please try rephrasing.', 'wyvern-ai-styling' ),
					esc_html( $command )
				),
				array( 'status' => 400 )
			);
		}

		// Check if operation requires confirmation.
		if ( $this->requires_confirmation( $operation ) && ! $confirm ) {
			return array(
				'success'               => false,
				'requires_confirmation' => true,
				'message'               => $this->get_confirmation_message( $operation ),
				'confirmation_prompt'   => $this->get_confirmation_prompt( $operation ),
				'data'                  => array(
					'operation' => $operation,
					'params'    => $params,
				),
			);
		}

		// Execute the operation.
		$result = $this->execute_operation( $operation, $params );

		// Log the action if successful.
		if ( ! is_wp_error( $result ) && ( $result['success'] ?? false ) ) {
			$this->audit_logger->log(
				$operation,
				array(
					'command' => $command,
					'params'  => $params,
					'result'  => $result,
				)
			);
		}

		return $result;
	}

	/**
	 * Match command to an operation.
	 *
	 * @param string $command Normalized command string.
	 * @return string|null Operation name or null if no match.
	 */
	private function match_operation( string $command ): ?string {
		// Try post operations.
		foreach ( $this->post_patterns as $operation => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( str_contains( $command, $pattern ) ) {
					return 'post_' . $operation;
				}
			}
		}

		// Try media operations.
		foreach ( $this->media_patterns as $operation => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( str_contains( $command, $pattern ) ) {
					return 'media_' . $operation;
				}
			}
		}

		// Try settings operations.
		foreach ( $this->settings_patterns as $operation => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( str_contains( $command, $pattern ) ) {
					return 'settings_' . $operation;
				}
			}
		}

		return null;
	}

	/**
	 * Execute an operation.
	 *
	 * @param string               $operation Operation name.
	 * @param array<string, mixed> $params    Parameters.
	 * @return array<string, mixed>|WP_Error Result or error.
	 */
	private function execute_operation( string $operation, array $params ) {
		// Route to appropriate handler.
		if ( str_starts_with( $operation, 'post_' ) ) {
			return $this->execute_post_operation( $operation, $params );
		}

		if ( str_starts_with( $operation, 'media_' ) ) {
			return $this->execute_media_operation( $operation, $params );
		}

		if ( str_starts_with( $operation, 'settings_' ) ) {
			return $this->execute_settings_operation( $operation, $params );
		}

		return new WP_Error(
			'invalid_operation',
			esc_html__( 'Invalid operation type.', 'wyvern-ai-styling' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Execute a post operation.
	 *
	 * @param string               $operation Operation name.
	 * @param array<string, mixed> $params    Parameters.
	 * @return array<string, mixed>|WP_Error Result or error.
	 */
	private function execute_post_operation( string $operation, array $params ) {
		$method = str_replace( 'post_', '', $operation );

		switch ( $method ) {
			case 'bulk_update_categories':
				return $this->post_handler->bulk_update_categories(
					$params['post_ids'] ?? array(),
					$params['categories'] ?? array()
				);

			case 'bulk_update_tags':
				return $this->post_handler->bulk_update_tags(
					$params['post_ids'] ?? array(),
					$params['tags'] ?? array()
				);

			case 'bulk_publish_posts':
				return $this->post_handler->bulk_publish_posts( $params );

			case 'bulk_unpublish_posts':
				return $this->post_handler->bulk_unpublish_posts( $params );

			case 'delete_old_drafts':
				$days_old = isset( $params['days_old'] ) && is_int( $params['days_old'] )
					? $params['days_old']
					: 30;
				return $this->post_handler->delete_old_drafts( $days_old );

			default:
				return new WP_Error(
					'unknown_operation',
					esc_html__( 'Unknown post operation.', 'wyvern-ai-styling' ),
					array( 'status' => 400 )
				);
		}
	}

	/**
	 * Execute a media operation.
	 *
	 * @param string               $operation Operation name.
	 * @param array<string, mixed> $params    Parameters.
	 * @return array<string, mixed>|WP_Error Result or error.
	 */
	private function execute_media_operation( string $operation, array $params ) {
		$method = str_replace( 'media_', '', $operation );

		switch ( $method ) {
			case 'find_unused_media':
				return $this->media_handler->find_unused_media();

			case 'organize_media_by_date':
				return $this->media_handler->organize_media_by_date();

			case 'bulk_generate_alt_text':
				return $this->media_handler->bulk_generate_alt_text(
					$params['attachment_ids'] ?? array()
				);

			default:
				return new WP_Error(
					'unknown_operation',
					esc_html__( 'Unknown media operation.', 'wyvern-ai-styling' ),
					array( 'status' => 400 )
				);
		}
	}

	/**
	 * Execute a settings operation.
	 *
	 * @param string               $operation Operation name.
	 * @param array<string, mixed> $params    Parameters.
	 * @return array<string, mixed>|WP_Error Result or error.
	 */
	private function execute_settings_operation( string $operation, array $params ) {
		$method = str_replace( 'settings_', '', $operation );

		switch ( $method ) {
			case 'get_performance_recommendations':
				return $this->settings_handler->get_performance_recommendations();

			case 'get_security_recommendations':
				return $this->settings_handler->get_security_recommendations();

			case 'apply_recommendation':
				$recommendation_id = $params['recommendation_id'] ?? '';
				if ( ! is_string( $recommendation_id ) || empty( $recommendation_id ) ) {
					return new WP_Error(
						'missing_recommendation_id',
						esc_html__( 'Recommendation ID is required.', 'wyvern-ai-styling' ),
						array( 'status' => 400 )
					);
				}
				return $this->settings_handler->apply_recommendation( $recommendation_id );

			default:
				return new WP_Error(
					'unknown_operation',
					esc_html__( 'Unknown settings operation.', 'wyvern-ai-styling' ),
					array( 'status' => 400 )
				);
		}
	}

	/**
	 * Check if operation requires confirmation.
	 *
	 * @param string $operation Operation name.
	 * @return bool True if requires confirmation.
	 */
	private function requires_confirmation( string $operation ): bool {
		$destructive_operations = array(
			'post_bulk_unpublish_posts',
			'post_delete_old_drafts',
			'media_organize_media_by_date',
		);

		return in_array( $operation, $destructive_operations, true );
	}

	/**
	 * Get confirmation message for operation.
	 *
	 * @param string $operation Operation name.
	 * @return string Confirmation message.
	 */
	private function get_confirmation_message( string $operation ): string {
		$messages = array(
			'post_bulk_unpublish_posts'    => esc_html__( 'This will unpublish posts and make them drafts. This action can be undone by republishing.', 'wyvern-ai-styling' ),
			'post_delete_old_drafts'       => esc_html__( 'This will permanently delete old draft posts. This action cannot be undone.', 'wyvern-ai-styling' ),
			'media_organize_media_by_date' => esc_html__( 'This will reorganize your media files by date. Existing URLs will be affected.', 'wyvern-ai-styling' ),
		);

		return $messages[ $operation ] ?? esc_html__( 'This action requires confirmation.', 'wyvern-ai-styling' );
	}

	/**
	 * Get confirmation prompt for operation.
	 *
	 * @param string $operation Operation name (reserved for future use).
	 * @return string Confirmation prompt.
	 */
	private function get_confirmation_prompt( string $operation ): string {
		// All operations use same confirmation prompt for now.
		// $operation parameter reserved for future operation-specific prompts.
		unset( $operation );
		return esc_html__( 'Are you sure you want to proceed?', 'wyvern-ai-styling' );
	}
}
