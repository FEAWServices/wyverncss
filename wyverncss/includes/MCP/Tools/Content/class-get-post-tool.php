<?php
/**
 * Get Post MCP Tool
 *
 * Retrieve a single WordPress post by ID
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
use WP_Post;

/**
 * GetPostTool Class
 *
 * Retrieves a single post by ID with full details.
 */
class GetPostTool extends MCP_Tool_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_get_post';
		$this->description = __( 'Get a single WordPress post by ID. Returns full post details including content, metadata, and author information.', 'wyvern-ai-styling' );
		$this->cache_ttl   = 300; // 5 minutes cache.

		$this->required_capabilities = array( 'read' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => __( 'The ID of the post to retrieve', 'wyvern-ai-styling' ),
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
	 * @return array<string, mixed>|WP_Error Post data or error.
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
				),
				array( 'post_id' => $post_id )
			);
		}

		// Check if user has permission to read this post.
		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post_id ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to read this post', 'wyvern-ai-styling' ),
				array( 'post_id' => $post_id )
			);
		}

		// Get post metadata.
		$metadata = get_post_meta( $post_id );

		// Get author info.
		$author = get_userdata( (int) $post->post_author );

		// Get featured image.
		$featured_image_id = get_post_thumbnail_id( $post_id );
		$featured_image    = $featured_image_id ? wp_get_attachment_image_src( $featured_image_id, 'full' ) : null;

		// Get categories and tags.
		$categories = get_the_category( $post_id );
		$tags       = get_the_tags( $post_id );

		return array(
			'ID'             => $post->ID,
			'post_title'     => $post->post_title,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_status'    => $post->post_status,
			'post_type'      => $post->post_type,
			'post_author'    => $post->post_author,
			'author_name'    => $author ? $author->display_name : '',
			'post_date'      => $post->post_date,
			'post_modified'  => $post->post_modified,
			'permalink'      => get_permalink( $post_id ),
			'edit_link'      => get_edit_post_link( $post_id, 'raw' ),
			'featured_image' => $featured_image ? array(
				'url'    => $featured_image[0],
				'width'  => $featured_image[1],
				'height' => $featured_image[2],
			) : null,
			'categories'     => $categories ? array_map(
				static function ( $cat ) {
					return array(
						'term_id' => $cat->term_id,
						'name'    => $cat->name,
						'slug'    => $cat->slug,
					);
				},
				$categories
			) : array(),
			'tags'           => is_array( $tags ) ? array_map(
				static function ( $tag ) {
					return array(
						'term_id' => $tag->term_id,
						'name'    => $tag->name,
						'slug'    => $tag->slug,
					);
				},
				$tags
			) : array(),
			'metadata'       => $metadata,
		);
	}
}
