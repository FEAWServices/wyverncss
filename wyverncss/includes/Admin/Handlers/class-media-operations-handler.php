<?php
/**
 * Media Operations Handler
 *
 * Handles media management operations for Admin AI.
 *
 * @package WyvernCSS
 * @subpackage Admin\Handlers
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Admin\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_Error;
use WP_Query;

/**
 * Media Operations Handler Class
 *
 * Provides methods for:
 * - Finding unused media
 * - Organizing media by date
 * - Bulk generating alt text
 *
 * All methods check user capabilities and return structured results.
 *
 * @since 1.0.0
 */
class Media_Operations_Handler {

	/**
	 * Find media items not attached to any post.
	 *
	 * @return array<string, mixed>|WP_Error Result array or error.
	 */
	public function find_unused_media() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'permission_denied',
				esc_html__( 'You do not have permission to manage media.', 'wyverncss' ),
				array( 'status' => 403 )
			);
		}

		// Query unattached media.
		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_parent'    => 0,
			'posts_per_page' => 100, // Safety limit.
			'fields'         => 'ids',
		);

		$query     = new WP_Query( $query_args );
		$media_ids = $query->posts;

		// Filter out media that may be used in content but not directly attached.
		$unused_media = array();
		foreach ( $media_ids as $media_id ) {
			// Check if media is referenced in post content.
			// $media_id is int because query uses 'fields' => 'ids'.
			$is_used = $this->is_media_referenced( (int) $media_id );
			if ( ! $is_used ) {
				$unused_media[] = array(
					'id'    => $media_id,
					'title' => get_the_title( $media_id ),
					'url'   => wp_get_attachment_url( $media_id ),
					'type'  => get_post_mime_type( $media_id ),
				);
			}
		}

		return array(
			'success'        => true,
			'affected_count' => count( $unused_media ),
			'media_items'    => $unused_media,
			'action'         => 'find_unused_media',
			'message'        => sprintf(
				/* translators: %d: number of unused media items found */
				esc_html( _n( 'Found %d unused media item.', 'Found %d unused media items.', count( $unused_media ), 'wyverncss' ) ),
				count( $unused_media )
			),
		);
	}

	/**
	 * Check if media is referenced in post content.
	 *
	 * @param int $media_id Media attachment ID.
	 * @return bool True if referenced, false otherwise.
	 */
	private function is_media_referenced( int $media_id ): bool {
		global $wpdb;

		$url = wp_get_attachment_url( $media_id );
		if ( ! $url ) {
			return false;
		}

		// Search for media URL in post content.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Necessary for media reference check.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type = 'post' AND post_status = 'publish'",
				'%' . $wpdb->esc_like( $url ) . '%'
			)
		);

		return $count > 0;
	}

	/**
	 * Organize media files by date (stub for now).
	 *
	 * This would reorganize the uploads directory structure.
	 * Since this is a destructive operation, we return a preview for now.
	 *
	 * @return array<string, mixed>|WP_Error Result array or error.
	 */
	public function organize_media_by_date() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'permission_denied',
				esc_html__( 'You do not have permission to manage media.', 'wyverncss' ),
				array( 'status' => 403 )
			);
		}

		// Check if year/month-based organization is enabled.
		$uploads_use_yearmonth_folders = get_option( 'uploads_use_yearmonth_folders' );

		if ( ! $uploads_use_yearmonth_folders ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Year/month organization is disabled. Enable it in Settings > Media to use this feature.', 'wyverncss' ),
				'data'    => array(
					'setting_enabled' => false,
				),
			);
		}

		// For now, just return information about the current organization setting.
		return array(
			'success' => true,
			'message' => esc_html__( 'Media organization by date is already enabled. New uploads will be organized automatically.', 'wyverncss' ),
			'data'    => array(
				'setting_enabled' => true,
				'action'          => 'organize_media_by_date',
			),
		);
	}

	/**
	 * Bulk generate alt text for media (stub - requires AI integration).
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @return array<string, mixed>|WP_Error Result array or error.
	 */
	public function bulk_generate_alt_text( array $attachment_ids ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'permission_denied',
				esc_html__( 'You do not have permission to manage media.', 'wyverncss' ),
				array( 'status' => 403 )
			);
		}

		if ( empty( $attachment_ids ) ) {
			return new WP_Error(
				'invalid_params',
				esc_html__( 'Attachment IDs are required.', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		$affected_count = 0;
		$updated_media  = array();

		foreach ( $attachment_ids as $attachment_id ) {
			if ( ! is_int( $attachment_id ) ) {
				continue;
			}

			// Check if user can edit this attachment.
			if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
				continue;
			}

			// Check if attachment exists and is an image.
			$mime_type = get_post_mime_type( $attachment_id );
			if ( ! $mime_type || ! str_starts_with( $mime_type, 'image/' ) ) {
				continue;
			}

			// Check if alt text already exists.
			$existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			if ( ! empty( $existing_alt ) ) {
				continue;
			}

			// Generate alt text from image filename (placeholder - would use AI in production).
			$alt_text = $this->generate_alt_text_from_filename( $attachment_id );

			if ( ! empty( $alt_text ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
				++$affected_count;
				$updated_media[] = array(
					'id'       => $attachment_id,
					'title'    => get_the_title( $attachment_id ),
					'alt_text' => $alt_text,
				);
			}
		}

		return array(
			'success'        => true,
			'affected_count' => $affected_count,
			'media_items'    => $updated_media,
			'action'         => 'bulk_generate_alt_text',
			'message'        => sprintf(
				/* translators: %d: number of media items updated */
				esc_html( _n( 'Generated alt text for %d image.', 'Generated alt text for %d images.', $affected_count, 'wyverncss' ) ),
				$affected_count
			),
			'note'           => esc_html__( 'Alt text generated from filenames. AI-powered alt text generation coming soon.', 'wyverncss' ),
		);
	}

	/**
	 * Generate alt text from image filename (placeholder).
	 *
	 * In production, this would use AI vision models to analyze the image.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Generated alt text.
	 */
	private function generate_alt_text_from_filename( int $attachment_id ): string {
		$filename = get_the_title( $attachment_id );

		// Clean up filename: remove extension, replace dashes/underscores with spaces.
		$alt_text = preg_replace( '/\.[^.]+$/', '', $filename );
		$alt_text = str_replace( array( '-', '_' ), ' ', $alt_text ?? '' );
		$alt_text = ucwords( $alt_text );

		return $alt_text;
	}
}
