<?php
/**
 * Delete Post MCP Tool
 *
 * Delete or trash a WordPress post
 *
 * @package WyvernCSS\MCP\Tools\Content
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\MCP\Tools\Content;
use WyvernCSS\MCP\Tools\MCP_Tool_Base;
use WP_Error;

/**
 * DeletePostTool Class
 *
 * Deletes or moves posts to trash. Supports permanent deletion.
 */
class DeletePostTool extends MCP_Tool_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_delete_post';
		$this->description = __( 'Delete or trash a WordPress post. Requires delete permissions for the post.', 'wyvern-ai-styling' );
		$this->cache_ttl   = 0; // No caching for mutation operations.

		$this->required_capabilities = array( 'delete_posts' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'post_id'      => array(
					'type'        => 'integer',
					'description' => __( 'ID of the post to delete', 'wyvern-ai-styling' ),
					'minimum'     => 1,
				),
				'force_delete' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether to permanently delete (true) or move to trash (false)', 'wyvern-ai-styling' ),
					'default'     => false,
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	/**
	 * Execute the tool
	 *
	 * @param array<string, mixed> $params Tool parameters.
	 * @return array<string, mixed>|WP_Error Deletion result or error.
	 */
	public function execute( array $params ) {
		$post_id      = (int) $params['post_id'];
		$force_delete = (bool) ( $params['force_delete'] ?? false );

		// Check if post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				sprintf(
					/* translators: %d: post ID */
					__( 'Post with ID %d not found', 'wyvern-ai-styling' ),
					$post_id
				)
			);
		}

		// Check if user can delete this specific post.
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to delete this post', 'wyvern-ai-styling' )
			);
		}

		// Store post data before deletion for response.
		$post_title = $post->post_title;
		$post_type  = $post->post_type;

		// Delete the post.
		$result = wp_delete_post( $post_id, $force_delete );

		if ( ! $result ) {
			return new WP_Error(
				'post_deletion_failed',
				__( 'Failed to delete post', 'wyvern-ai-styling' )
			);
		}

		return array(
			'post_id'      => $post_id,
			'post_title'   => $post_title,
			'post_type'    => $post_type,
			'force_delete' => $force_delete,
			'status'       => $force_delete ? 'permanently_deleted' : 'moved_to_trash',
			'message'      => $force_delete
				? __( 'Post permanently deleted', 'wyvern-ai-styling' )
				: __( 'Post moved to trash', 'wyvern-ai-styling' ),
		);
	}
}
