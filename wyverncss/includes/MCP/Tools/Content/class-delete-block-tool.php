<?php
/**
 * Delete Block MCP Tool
 *
 * Delete a specific Gutenberg block from a post
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
 * DeleteBlockTool Class
 *
 * Removes a block at a specified index from the post content.
 */
class DeleteBlockTool extends MCP_Tool_Base {

	use BlockOperationsTrait;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_delete_block';
		$this->description = __( 'Delete a Gutenberg block at a specific index (0-based) from a post. Useful for removing unwanted content sections.', 'wyvern-ai-styling' );
		$this->cache_ttl   = 0; // No caching for mutations.

		$this->required_capabilities = array( 'edit_posts' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => __( 'The ID of the post', 'wyvern-ai-styling' ),
					'minimum'     => 1,
				),
				'index'   => array(
					'type'        => 'integer',
					'description' => __( 'Index of the block to delete (0-based)', 'wyvern-ai-styling' ),
					'minimum'     => 0,
				),
			),
			'required'   => array( 'post_id', 'index' ),
		);
	}

	/**
	 * Execute the tool
	 *
	 * @param array<string, mixed> $params Tool parameters.
	 * @return array<string, mixed>|WP_Error Delete result or error.
	 */
	public function execute( array $params ) {
		$post_id = (int) $params['post_id'];
		$index   = (int) $params['index'];

		// Validate post and permissions.
		$post = $this->validate_post_and_permissions( $post_id, 'edit' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		// Parse and filter blocks.
		$existing_blocks = $this->parse_and_filter_blocks( $post->post_content );

		// Validate block index.
		$index_validation = $this->validate_block_index( $index, $existing_blocks );
		if ( is_wp_error( $index_validation ) ) {
			return $index_validation;
		}

		// Store deleted block info for response.
		$deleted_block = $existing_blocks[ $index ];

		// Remove block.
		array_splice( $existing_blocks, $index, 1 );

		/**
		 * Update post content.
		 *
		 * @phpstan-ignore-next-line Blocks from parse_blocks() match expected structure
		 */
		$update_result = $this->update_post_content( $post_id, $existing_blocks );
		if ( is_wp_error( $update_result ) ) {
			return $update_result;
		}

		return array(
			'post_id'          => $post_id,
			'deleted'          => true,
			'deleted_index'    => $index,
			'deleted_block'    => $deleted_block['blockName'] ?? 'unknown',
			'remaining_blocks' => count( $existing_blocks ),
			'message'          => __( 'Block deleted successfully', 'wyvern-ai-styling' ),
		);
	}
}
