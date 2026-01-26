<?php
/**
 * Insert Block MCP Tool
 *
 * Insert a new Gutenberg block at a specific position in a post
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
 * InsertBlockTool Class
 *
 * Inserts a new block at a specified position in the post content.
 */
class InsertBlockTool extends MCP_Tool_Base {

	use BlockOperationsTrait;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_insert_block';
		$this->description = __( 'Insert a new Gutenberg block at a specific position in a post. Position can be "start", "end", or a numeric index.', 'wyverncss' );
		$this->cache_ttl   = 0; // No caching for mutations.

		$this->required_capabilities = array( 'edit_posts' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'post_id'  => array(
					'type'        => 'integer',
					'description' => __( 'The ID of the post', 'wyverncss' ),
					'minimum'     => 1,
				),
				'position' => array(
					'type'        => 'string',
					'description' => __( 'Where to insert: "start", "end", or numeric index (e.g., "2")', 'wyverncss' ),
					'default'     => 'end',
				),
				'block'    => array(
					'type'        => 'string',
					'description' => __( 'Serialized block content to insert', 'wyverncss' ),
				),
			),
			'required'   => array( 'post_id', 'block' ),
		);
	}

	/**
	 * Execute the tool
	 *
	 * @param array<string, mixed> $params Tool parameters.
	 * @return array<string, mixed>|WP_Error Insert result or error.
	 */
	public function execute( array $params ) {
		$post_id  = (int) $params['post_id'];
		$position = sanitize_text_field( $params['position'] ?? 'end' );
		$block    = wp_kses_post( $params['block'] );

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

		// Parse existing blocks.
		$parsed_existing_blocks = parse_blocks( $post->post_content );
		$parsed_new_block       = parse_blocks( $block );

		// Filter out empty blocks (where blockName is null).
		$existing_blocks = array_values(
			array_filter(
				$parsed_existing_blocks,
				function ( $block ) {
					return ! empty( $block['blockName'] );
				}
			)
		);

		$new_block = array_filter(
			$parsed_new_block,
			function ( $block ) {
				return ! empty( $block['blockName'] );
			}
		);

		if ( empty( $new_block ) ) {
			return new WP_Error(
				'invalid_block',
				__( 'Invalid block content provided', 'wyverncss' )
			);
		}

		// Determine insert position.
		if ( 'start' === $position ) {
			array_unshift( $existing_blocks, ...$new_block );
		} elseif ( 'end' === $position ) {
			array_push( $existing_blocks, ...$new_block );
		} elseif ( is_numeric( $position ) ) {
			$index = (int) $position;
			array_splice( $existing_blocks, $index, 0, $new_block );
		} else {
			return new WP_Error(
				'invalid_position',
				__( 'Position must be "start", "end", or a numeric index', 'wyverncss' )
			);
		}

		// Serialize blocks back to content.
		$new_content = serialize_blocks( $existing_blocks );

		// Update post.
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

		return array(
			'post_id'     => $post_id,
			'inserted'    => true,
			'position'    => $position,
			'block_count' => count( $existing_blocks ),
			'message'     => __( 'Block inserted successfully', 'wyverncss' ),
		);
	}
}
