<?php
/**
 * Block Operations Trait
 *
 * Provides common block operation utilities for MCP tools.
 *
 * @package WyvernCSS\MCP\Tools\Content
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\MCP\Tools\Content;
use WP_Error;
use WP_Post;

/**
 * BlockOperationsTrait
 *
 * Consolidated block operation utilities to eliminate code duplication.
 */
trait BlockOperationsTrait {

	/**
	 * Validate post and permissions for block operations.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id     The post ID to validate.
	 * @param string $capability  The required capability ('read' or 'edit').
	 * @return WP_Post|WP_Error   Validated post object or error.
	 */
	protected function validate_post_and_permissions( int $post_id, string $capability = 'edit' ) {
		// Check if post exists.
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

		// Check permissions based on capability type.
		if ( 'edit' === $capability ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return new WP_Error(
					'insufficient_permissions',
					__( 'You do not have permission to edit this post', 'wyvern-ai-styling' )
				);
			}
		} elseif ( 'read' === $capability ) {
			if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post_id ) ) {
				return new WP_Error(
					'insufficient_permissions',
					__( 'You do not have permission to read this post', 'wyvern-ai-styling' )
				);
			}
		}

		return $post;
	}

	/**
	 * Parse and filter blocks from post content.
	 *
	 * Removes empty blocks (where blockName is null) and reindexes the array.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The post content to parse.
	 * @return array<int, array<string, mixed>> Filtered and reindexed blocks.
	 */
	protected function parse_and_filter_blocks( string $content ): array {
		$parsed_blocks = parse_blocks( $content );

		// Filter out empty blocks (where blockName is null).
		return array_values(
			array_filter(
				$parsed_blocks,
				function ( $block ) {
					return ! empty( $block['blockName'] );
				}
			)
		);
	}

	/**
	 * Validate block index exists in block array.
	 *
	 * @since 1.0.0
	 *
	 * @param int                              $index         The block index to validate.
	 * @param array<int, array<string, mixed>> $existing_blocks The array of existing blocks.
	 * @return true|WP_Error       True if valid, WP_Error otherwise.
	 */
	protected function validate_block_index( int $index, array $existing_blocks ) {
		if ( ! isset( $existing_blocks[ $index ] ) ) {
			return new WP_Error(
				'invalid_index',
				sprintf(
					/* translators: 1: index, 2: block count */
					__( 'Block index %1$d does not exist. Post has %2$d blocks.', 'wyvern-ai-styling' ),
					$index,
					count( $existing_blocks )
				)
			);
		}

		return true;
	}

	/**
	 * Update post content after block operations.
	 *
	 * @since 1.0.0
	 *
	 * @param int                                                                                                                                                $post_id The post ID.
	 * @param array<int|string, array{blockName: string, attrs: array<string, mixed>, innerBlocks: array<mixed>, innerHTML: string, innerContent: array<mixed>}> $blocks  The modified blocks array.
	 * @return true|WP_Error True if successful, WP_Error on failure.
	 */
	protected function update_post_content( int $post_id, array $blocks ) {
		$new_content = serialize_blocks( $blocks );

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}
