<?php
/**
 * Replace Block MCP Tool
 *
 * Replace a specific Gutenberg block with new content
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
 * ReplaceBlockTool Class
 *
 * Replaces a block at a specified index with new block content.
 * Ideal for targeted edits based on natural language instructions.
 */
class ReplaceBlockTool extends MCP_Tool_Base {

	use BlockOperationsTrait;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_replace_block';
		$this->description = __( 'Replace a Gutenberg block at a specific index with new content. Perfect for natural language editing like "replace the second paragraph with...".', 'wyvern-ai-styling' );
		$this->cache_ttl   = 0; // No caching for mutations.

		$this->required_capabilities = array( 'edit_posts' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'post_id'   => array(
					'type'        => 'integer',
					'description' => __( 'The ID of the post', 'wyvern-ai-styling' ),
					'minimum'     => 1,
				),
				'index'     => array(
					'type'        => 'integer',
					'description' => __( 'Index of the block to replace (0-based)', 'wyvern-ai-styling' ),
					'minimum'     => 0,
				),
				'new_block' => array(
					'type'        => 'string',
					'description' => __( 'Serialized block content to replace with', 'wyvern-ai-styling' ),
				),
			),
			'required'   => array( 'post_id', 'index', 'new_block' ),
		);
	}

	/**
	 * Execute the tool
	 *
	 * @param array<string, mixed> $params Tool parameters.
	 * @return array<string, mixed>|WP_Error Replace result or error.
	 */
	public function execute( array $params ) {
		$post_id   = (int) $params['post_id'];
		$index     = (int) $params['index'];
		$new_block = $params['new_block'];

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

		// Remove script tags and their contents first.
		$new_block_clean = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $new_block );

		// Sanitize new block content (remove dangerous HTML).
		$sanitized_new_block = wp_kses_post( $new_block_clean );

		// Parse new block.
		$parsed_new_block_all = parse_blocks( $sanitized_new_block );

		// Filter out empty blocks.
		$parsed_new_block = array_filter(
			$parsed_new_block_all,
			function ( $block ) {
				return ! empty( $block['blockName'] );
			}
		);

		if ( empty( $parsed_new_block ) ) {
			return new WP_Error(
				'invalid_block',
				__( 'Invalid block content provided', 'wyvern-ai-styling' )
			);
		}

		// Store old block info for response.
		$old_block = $existing_blocks[ $index ];

		// Replace block (support multiple blocks from parse).
		array_splice( $existing_blocks, $index, 1, $parsed_new_block );

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
			'post_id'     => $post_id,
			'replaced'    => true,
			'index'       => $index,
			'old_block'   => $old_block['blockName'] ?? 'unknown',
			'new_block'   => $parsed_new_block[0]['blockName'] ?? 'unknown',
			'block_count' => count( $existing_blocks ),
			'message'     => __( 'Block replaced successfully', 'wyvern-ai-styling' ),
		);
	}
}
