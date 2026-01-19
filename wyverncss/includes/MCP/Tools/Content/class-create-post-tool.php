<?php
/**
 * Create Post MCP Tool
 *
 * Create a new WordPress post or page
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
 * CreatePostTool Class
 *
 * Creates new WordPress posts with specified content and metadata.
 */
class CreatePostTool extends MCP_Tool_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_create_post';
		$this->description = __( 'Create a new WordPress post or page. Requires publish permissions for the specified post type.', 'wyvern-ai-styling' );
		$this->cache_ttl   = 0; // No caching for mutation operations.

		$this->required_capabilities = array( 'edit_posts' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'title'      => array(
					'type'        => 'string',
					'description' => __( 'Post title', 'wyvern-ai-styling' ),
					'minLength'   => 1,
				),
				'content'    => array(
					'type'        => 'string',
					'description' => __( 'Post content (HTML allowed)', 'wyvern-ai-styling' ),
					'default'     => '',
				),
				'excerpt'    => array(
					'type'        => 'string',
					'description' => __( 'Post excerpt', 'wyvern-ai-styling' ),
					'default'     => '',
				),
				'status'     => array(
					'type'        => 'string',
					'description' => __( 'Post status', 'wyvern-ai-styling' ),
					'default'     => 'draft',
					'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
				),
				'post_type'  => array(
					'type'        => 'string',
					'description' => __( 'Post type (post, page, or custom post type)', 'wyvern-ai-styling' ),
					'default'     => 'post',
				),
				'categories' => array(
					'type'        => 'array',
					'description' => __( 'Array of category IDs', 'wyvern-ai-styling' ),
					'items'       => array( 'type' => 'integer' ),
				),
				'tags'       => array(
					'type'        => 'array',
					'description' => __( 'Array of tag names or IDs', 'wyvern-ai-styling' ),
					'items'       => array( 'type' => 'string' ),
				),
			),
			'required'   => array( 'title' ),
		);
	}

	/**
	 * Execute the tool
	 *
	 * @param array<string, mixed> $params Tool parameters.
	 * @return array<string, mixed>|WP_Error Created post data or error.
	 */
	public function execute( array $params ) {
		// Check specific capability based on status.
		$status = sanitize_key( $params['status'] ?? 'draft' );
		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to publish posts', 'wyvern-ai-styling' )
			);
		}

		// Sanitize parameters.
		$title     = sanitize_text_field( $params['title'] );
		$content   = wp_kses_post( $params['content'] ?? '' );
		$excerpt   = sanitize_textarea_field( $params['excerpt'] ?? '' );
		$post_type = sanitize_key( $params['post_type'] ?? 'post' );

		// Prepare post data.
		$postarr = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => $status,
			'post_type'    => $post_type,
		);

		// Insert the post.
		$post_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'post_creation_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to create post: %s', 'wyvern-ai-styling' ),
					$post_id->get_error_message()
				)
			);
		}

		// Set categories if provided.
		if ( ! empty( $params['categories'] ) && is_array( $params['categories'] ) ) {
			$category_ids = array_map( 'absint', $params['categories'] );
			wp_set_post_categories( $post_id, $category_ids );
		}

		// Set tags if provided.
		if ( ! empty( $params['tags'] ) && is_array( $params['tags'] ) ) {
			wp_set_post_tags( $post_id, $params['tags'] );
		}

		// Get the created post.
		$post = get_post( $post_id );

		return array(
			'post_id'     => $post_id,
			'post_title'  => $post ? $post->post_title : $title,
			'post_status' => $post ? $post->post_status : $status,
			'post_type'   => $post ? $post->post_type : $post_type,
			'permalink'   => get_permalink( $post_id ),
			'edit_link'   => get_edit_post_link( $post_id, 'raw' ),
			'message'     => __( 'Post created successfully', 'wyvern-ai-styling' ),
		);
	}
}
