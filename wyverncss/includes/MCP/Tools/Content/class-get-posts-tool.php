<?php
/**
 * Get Posts MCP Tool
 *
 * Query WordPress posts with filters (post type, status, pagination, etc.)
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
use WP_Query;

/**
 * GetPostsTool Class
 *
 * Provides read-only access to WordPress posts via MCP.
 */
class GetPostsTool extends MCP_Tool_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_get_posts';
		$this->description = __( 'Query WordPress posts with filters. Returns a list of posts matching the specified criteria.', 'wyvern-ai-styling' );
		$this->cache_ttl   = 300; // 5 minutes cache for read operations.

		$this->required_capabilities = array( 'read' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'post_type'      => array(
					'type'        => 'string',
					'description' => __( 'Post type to query (post, page, or custom post type)', 'wyvern-ai-styling' ),
					'default'     => 'post',
				),
				'post_status'    => array(
					'type'        => 'string',
					'description' => __( 'Post status (publish, draft, pending, private, etc.)', 'wyvern-ai-styling' ),
					'default'     => 'publish',
					'enum'        => array( 'publish', 'draft', 'pending', 'private', 'trash', 'any' ),
				),
				'posts_per_page' => array(
					'type'        => 'integer',
					'description' => __( 'Number of posts to return per page', 'wyvern-ai-styling' ),
					'default'     => 10,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'paged'          => array(
					'type'        => 'integer',
					'description' => __( 'Page number for pagination', 'wyvern-ai-styling' ),
					'default'     => 1,
					'minimum'     => 1,
				),
				'orderby'        => array(
					'type'        => 'string',
					'description' => __( 'Field to order results by', 'wyvern-ai-styling' ),
					'default'     => 'date',
					'enum'        => array( 'date', 'title', 'modified', 'ID', 'author', 'comment_count' ),
				),
				'order'          => array(
					'type'        => 'string',
					'description' => __( 'Sort order (ascending or descending)', 'wyvern-ai-styling' ),
					'default'     => 'DESC',
					'enum'        => array( 'ASC', 'DESC' ),
				),
				's'              => array(
					'type'        => 'string',
					'description' => __( 'Search query string', 'wyvern-ai-styling' ),
				),
			),
			'required'   => array(),
		);
	}

	/**
	 * Execute the tool
	 *
	 * @param array<string, mixed> $params Tool parameters.
	 * @return array<string, mixed> Query results.
	 */
	public function execute( array $params ): array {
		// Sanitize parameters.
		$sanitized = $this->sanitize_params( $params );

		// Build query args.
		$query_args = array(
			'post_type'      => sanitize_key( $sanitized['post_type'] ?? 'post' ),
			'post_status'    => sanitize_key( $sanitized['post_status'] ?? 'publish' ),
			'posts_per_page' => isset( $sanitized['posts_per_page'] ) ? (int) $sanitized['posts_per_page'] : 10,
			'paged'          => isset( $sanitized['paged'] ) ? (int) $sanitized['paged'] : 1,
			'orderby'        => sanitize_key( $sanitized['orderby'] ?? 'date' ),
			'order'          => sanitize_key( $sanitized['order'] ?? 'DESC' ),
			'no_found_rows'  => false, // We need total count for pagination.
		);

		// Add search if provided.
		if ( ! empty( $sanitized['s'] ) ) {
			$query_args['s'] = sanitize_text_field( $sanitized['s'] );
		}

		// Execute query.
		$query = new WP_Query( $query_args );

		// Format results.
		$posts = array_map(
			static function ( $post ) {
				// PHPStan: WP_Query->posts can be int|WP_Post, but since we don't set 'fields',
				// we know it will always be WP_Post. Cast to ensure type safety.
				if ( ! $post instanceof \WP_Post ) {
					return array();
				}
				return array(
					'ID'            => $post->ID,
					'post_title'    => $post->post_title,
					'post_excerpt'  => $post->post_excerpt,
					'post_content'  => $post->post_content,
					'post_status'   => $post->post_status,
					'post_type'     => $post->post_type,
					'post_author'   => $post->post_author,
					'post_date'     => $post->post_date,
					'post_modified' => $post->post_modified,
					'permalink'     => get_permalink( $post->ID ),
					'edit_link'     => get_edit_post_link( $post->ID, 'raw' ),
				);
			},
			$query->posts
		);

		return array(
			'posts'        => $posts,
			'total'        => $query->found_posts,
			'total_pages'  => $query->max_num_pages,
			'current_page' => $query_args['paged'],
			'per_page'     => $query_args['posts_per_page'],
		);
	}
}
