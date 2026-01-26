<?php
/**
 * Update Post MCP Tool
 *
 * Update an existing WordPress post
 *
 * @package WyvernCSS\MCP\Tools\Content
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\MCP\Tools\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\MCP\Tools\MCP_Tool_Base;
use WP_Error;

/**
 * UpdatePostTool Class
 *
 * Updates existing WordPress posts with new content or metadata.
 */
class UpdatePostTool extends MCP_Tool_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_update_post';
		$this->description = __( 'Update an existing WordPress post. Requires edit permissions for the post.', 'wyverncss' );
		$this->cache_ttl   = 0; // No caching for mutation operations.

		$this->required_capabilities = array( 'edit_posts' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => __( 'ID of the post to update', 'wyverncss' ),
					'minimum'     => 1,
				),
				'title'   => array(
					'type'        => 'string',
					'description' => __( 'New post title', 'wyverncss' ),
				),
				'content' => array(
					'type'        => 'string',
					'description' => __( 'New post content (HTML allowed)', 'wyverncss' ),
				),
				'excerpt' => array(
					'type'        => 'string',
					'description' => __( 'New post excerpt', 'wyverncss' ),
				),
				'status'  => array(
					'type'        => 'string',
					'description' => __( 'New post status', 'wyverncss' ),
					'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	/**
	 * Execute the tool
	 *
	 * @param array<string, mixed> $params Tool parameters.
	 * @return array<string, mixed>|WP_Error Updated post data or error.
	 */
	public function execute( array $params ) {
		$post_id = (int) $params['post_id'];

		// Check if post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				sprintf(
					/* translators: %d: post ID */
					__( 'Post with ID %d not found', 'wyverncss' ),
					$post_id
				)
			);
		}

		// Check if user can edit this specific post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to edit this post', 'wyverncss' )
			);
		}

		// Prepare update data.
		$postarr = array( 'ID' => $post_id );

		if ( isset( $params['title'] ) ) {
			$postarr['post_title'] = sanitize_text_field( $params['title'] );
		}

		if ( isset( $params['content'] ) ) {
			$postarr['post_content'] = wp_kses_post( $params['content'] );
		}

		if ( isset( $params['excerpt'] ) ) {
			$postarr['post_excerpt'] = sanitize_textarea_field( $params['excerpt'] );
		}

		if ( isset( $params['status'] ) ) {
			$new_status = sanitize_key( $params['status'] );

			// Check publish permission if changing to publish.
			if ( 'publish' === $new_status && 'publish' !== $post->post_status && ! current_user_can( 'publish_posts' ) ) {
				return new WP_Error(
					'insufficient_permissions',
					__( 'You do not have permission to publish posts', 'wyverncss' )
				);
			}

			$postarr['post_status'] = $new_status;
		}

		// Update the post.
		$result = wp_update_post( $postarr, true );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'post_update_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to update post: %s', 'wyverncss' ),
					$result->get_error_message()
				)
			);
		}

		// Get updated post.
		$updated_post = get_post( $post_id );

		return array(
			'post_id'       => $post_id,
			'post_title'    => $updated_post ? $updated_post->post_title : '',
			'post_status'   => $updated_post ? $updated_post->post_status : '',
			'post_modified' => $updated_post ? $updated_post->post_modified : '',
			'permalink'     => get_permalink( $post_id ),
			'edit_link'     => get_edit_post_link( $post_id, 'raw' ),
			'message'       => __( 'Post updated successfully', 'wyverncss' ),
		);
	}
}
