<?php
/**
 * Get Blocks MCP Tool
 *
 * Retrieve Gutenberg block structure from a post for AI analysis
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
use WP_Post;

/**
 * GetBlocksTool Class
 *
 * Parses post content and returns structured Gutenberg block data
 * for AI agents to understand page structure.
 */
class GetBlocksTool extends MCP_Tool_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_get_blocks';
		$this->description = __( 'Get Gutenberg block structure from a post. Returns parsed blocks with content, attributes, and hierarchy for AI-powered editing.', 'wyvern-ai-styling' );
		$this->cache_ttl   = 60; // 1 minute cache.

		$this->required_capabilities = array( 'read' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => __( 'The ID of the post to get blocks from', 'wyvern-ai-styling' ),
					'minimum'     => 1,
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	/**
	 * Execute the tool
	 *
	 * @param array<string, mixed> $params Tool parameters.
	 * @return array<string, mixed>|WP_Error Block structure or error.
	 */
	public function execute( array $params ) {
		$post_id = (int) $params['post_id'];

		// Get the post.
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
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
		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post_id ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to read this post', 'wyvern-ai-styling' )
			);
		}

		// Parse blocks from content.
		$parsed_blocks = parse_blocks( $post->post_content );

		// Filter out empty blocks (where blockName is null).
		$blocks = array_values(
			array_filter(
				$parsed_blocks,
				function ( $block ) {
					return ! empty( $block['blockName'] );
				}
			)
		);

		// Enrich blocks with metadata.
		$enriched_blocks = array_map(
			function ( $block ) {
				return array(
					'blockName'    => $block['blockName'],
					'attrs'        => $block['attrs'],
					'innerContent' => $block['innerContent'],
					'innerHTML'    => $block['innerHTML'],
					'innerBlocks'  => ! empty( $block['innerBlocks'] ) ? $this->enrich_inner_blocks( $block['innerBlocks'] ) : array(),
				);
			},
			$blocks
		);

		return array(
			'post_id'     => $post_id,
			'post_title'  => $post->post_title,
			'post_status' => $post->post_status,
			'post_type'   => $post->post_type,
			'blocks'      => $enriched_blocks,
			'block_count' => count( $blocks ),
			'has_blocks'  => ! empty( $blocks ),
		);
	}

	/**
	 * Enrich inner blocks recursively
	 *
	 * @param array<int, array<string, mixed>> $blocks Inner blocks to enrich.
	 * @return array<int, array<string, mixed>> Enriched blocks.
	 */
	private function enrich_inner_blocks( array $blocks ): array {
		return array_map(
			function ( $block ) {
				return array(
					'blockName'    => $block['blockName'],
					'attrs'        => $block['attrs'] ?? array(),
					'innerContent' => $block['innerContent'] ?? array(),
					'innerHTML'    => $block['innerHTML'] ?? '',
					'innerBlocks'  => ! empty( $block['innerBlocks'] ) ? $this->enrich_inner_blocks( $block['innerBlocks'] ) : array(),
				);
			},
			$blocks
		);
	}
}
