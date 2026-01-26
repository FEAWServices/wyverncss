<?php
/**
 * Get Media MCP Tool
 *
 * Query WordPress media library
 *
 * @package WyvernCSS\MCP\Tools\Media
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\MCP\Tools\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\MCP\Tools\MCP_Tool_Base;
use WP_Query;

/**
 * GetMediaTool Class
 *
 * Provides read-only access to WordPress media library.
 */
class GetMediaTool extends MCP_Tool_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_get_media';
		$this->description = __( 'Query WordPress media library. Returns attachments matching the specified criteria with URLs and metadata.', 'wyverncss' );
		$this->cache_ttl   = 300; // 5 minutes cache.

		$this->required_capabilities = array( 'upload_files' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'media_type'     => array(
					'type'        => 'string',
					'description' => __( 'Type of media to query (image, video, audio, application)', 'wyverncss' ),
					'enum'        => array( 'image', 'video', 'audio', 'application', 'all' ),
					'default'     => 'image',
				),
				'posts_per_page' => array(
					'type'        => 'integer',
					'description' => __( 'Number of items to return', 'wyverncss' ),
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'paged'          => array(
					'type'        => 'integer',
					'description' => __( 'Page number for pagination', 'wyverncss' ),
					'default'     => 1,
					'minimum'     => 1,
				),
				's'              => array(
					'type'        => 'string',
					'description' => __( 'Search query string', 'wyverncss' ),
				),
			),
			'required'   => array(),
		);
	}

	/**
	 * Execute the tool
	 *
	 * @param array<string, mixed> $params Tool parameters.
	 * @return array<string, mixed> Media query results.
	 */
	public function execute( array $params ): array {
		// Sanitize parameters.
		$media_type = sanitize_key( $params['media_type'] ?? 'image' );
		$per_page   = isset( $params['posts_per_page'] ) ? (int) $params['posts_per_page'] : 20;
		$paged      = isset( $params['paged'] ) ? (int) $params['paged'] : 1;

		// Build query args.
		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'no_found_rows'  => false,
		);

		// Add media type filter.
		if ( 'all' !== $media_type ) {
			$query_args['post_mime_type'] = $media_type;
		}

		// Add search if provided.
		if ( ! empty( $params['s'] ) ) {
			$query_args['s'] = sanitize_text_field( $params['s'] );
		}

		// Execute query.
		$query = new WP_Query( $query_args );

		// Format results.
		$media_items = array_map(
			static function ( $attachment ) {
				/**
				 * Type assertion: WP_Query with post_type=attachment always returns WP_Post objects.
				 *
				 * @var \WP_Post $attachment
				 */
				$metadata = wp_get_attachment_metadata( $attachment->ID );

				return array(
					'ID'          => $attachment->ID,
					'title'       => $attachment->post_title,
					'description' => $attachment->post_content,
					'caption'     => $attachment->post_excerpt,
					'alt_text'    => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
					'mime_type'   => $attachment->post_mime_type,
					'url'         => wp_get_attachment_url( $attachment->ID ),
					'thumbnail'   => wp_get_attachment_image_src( $attachment->ID, 'thumbnail' ),
					'medium'      => wp_get_attachment_image_src( $attachment->ID, 'medium' ),
					'large'       => wp_get_attachment_image_src( $attachment->ID, 'large' ),
					'full'        => wp_get_attachment_image_src( $attachment->ID, 'full' ),
					'file_size'   => $metadata['filesize'] ?? null,
					'width'       => $metadata['width'] ?? null,
					'height'      => $metadata['height'] ?? null,
					'upload_date' => $attachment->post_date,
					'uploaded_by' => $attachment->post_author,
				);
			},
			$query->posts
		);

		return array(
			'media'        => $media_items,
			'total'        => $query->found_posts,
			'total_pages'  => $query->max_num_pages,
			'current_page' => $paged,
			'per_page'     => $per_page,
		);
	}
}
