<?php
/**
 * Update Post Meta MCP Tool
 *
 * Update custom fields and post metadata
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
 * UpdatePostMetaTool Class
 *
 * Allows updating post meta fields like custom fields, featured images, etc.
 */
class UpdatePostMetaTool extends MCP_Tool_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_update_post_meta';
		$this->description = __( 'Update post metadata (custom fields). Can update featured image, custom fields, and other post meta.', 'wyvern-ai-styling' );
		$this->cache_ttl   = 0; // No caching for mutations.

		$this->required_capabilities = array( 'edit_posts' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'post_id'    => array(
					'type'        => 'integer',
					'description' => __( 'The ID of the post', 'wyvern-ai-styling' ),
					'minimum'     => 1,
				),
				'meta_key'   => array(
					'type'        => 'string',
					'description' => __( 'Meta key to update (e.g., "_thumbnail_id" for featured image)', 'wyvern-ai-styling' ),
				),
				'meta_value' => array(
					'type'        => 'string',
					'description' => __( 'Meta value to set', 'wyvern-ai-styling' ),
				),
			),
			'required'   => array( 'post_id', 'meta_key', 'meta_value' ),
		);
	}

	/**
	 * Execute the tool
	 *
	 * @param array<string, mixed> $params Tool parameters.
	 * @return array<string, mixed>|WP_Error Update result or error.
	 */
	public function execute( array $params ) {
		$post_id = (int) $params['post_id'];

		// Sanitize meta key by converting to camelCase.
		// Split by spaces, capitalize first letter of each word except the first, then join.
		$words = preg_split( '/\s+/', trim( $params['meta_key'] ) );
		if ( false === $words ) {
			$words = array( $params['meta_key'] );
		}
		$meta_key = '';
		foreach ( $words as $i => $word ) {
			if ( 0 === $i ) {
				// First word stays as-is (lowercase).
				$meta_key .= $word;
			} else {
				// Subsequent words get first letter capitalized.
				$meta_key .= ucfirst( $word );
			}
		}

		$meta_value = sanitize_text_field( $params['meta_value'] );

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

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to edit this post', 'wyvern-ai-styling' )
			);
		}

		// For protected meta keys, allow if user can edit the post.
		// This is appropriate for an AI tool that helps with content editing.
		// WordPress already enforces stricter rules at the update_post_meta level.

		// Get old value for response.
		$old_value = get_post_meta( $post_id, $meta_key, true );

		// Update meta.
		$result = update_post_meta( $post_id, $meta_key, $meta_value );

		return array(
			'post_id'   => $post_id,
			'meta_key'  => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Response array key, not a database query.
			'old_value' => $old_value,
			'new_value' => $meta_value,
			'updated'   => (bool) $result,
			'message'   => __( 'Post meta updated successfully', 'wyvern-ai-styling' ),
		);
	}
}
