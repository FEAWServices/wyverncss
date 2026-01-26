<?php
/**
 * Update Blocks MCP Tool
 *
 * Update Gutenberg blocks in a post with new content
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
 * UpdateBlocksTool Class
 *
 * Allows AI agents to update specific blocks or entire block structure
 * in a post for natural language editing.
 */
class UpdateBlocksTool extends MCP_Tool_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_update_blocks';
		$this->description = __( 'Update Gutenberg blocks in a post. Provide serialized block content to replace the entire post content, enabling natural language editing.', 'wyverncss' );
		$this->cache_ttl   = 0; // No caching for mutations.

		$this->required_capabilities = array( 'edit_posts' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => __( 'The ID of the post to update', 'wyverncss' ),
					'minimum'     => 1,
				),
				'blocks'  => array(
					'type'        => 'string',
					'description' => __( 'Serialized block content (HTML block comments format)', 'wyverncss' ),
				),
			),
			'required'   => array( 'post_id', 'blocks' ),
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
		$blocks  = wp_kses_post( $params['blocks'] );

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

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to edit this post', 'wyverncss' )
			);
		}

		// Update post content.
		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $blocks,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'update_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to update blocks: %s', 'wyverncss' ),
					$result->get_error_message()
				)
			);
		}

		// Parse updated blocks for response.
		$parsed_blocks_all = parse_blocks( $blocks );

		// Filter out empty blocks (where blockName is null).
		$parsed_blocks = array_filter(
			$parsed_blocks_all,
			function ( $block ) {
				return ! empty( $block['blockName'] );
			}
		);

		return array(
			'post_id'     => $post_id,
			'updated'     => true,
			'block_count' => count( $parsed_blocks ),
			'message'     => __( 'Blocks updated successfully', 'wyverncss' ),
		);
	}
}
