<?php
/**
 * Upload Media MCP Tool
 *
 * Upload files to WordPress media library
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
use WP_Error;

/**
 * UploadMediaTool Class
 *
 * Handles file uploads to WordPress media library with validation.
 */
class UploadMediaTool extends MCP_Tool_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name        = 'wp_upload_media';
		$this->description = __( 'Upload a file to WordPress media library. Requires upload permissions. Accepts base64 encoded file data or URL.', 'wyvern-ai-styling' );
		$this->cache_ttl   = 0; // No caching for mutation operations.

		$this->required_capabilities = array( 'upload_files' );

		$this->input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'filename'    => array(
					'type'        => 'string',
					'description' => __( 'Name of the file including extension', 'wyvern-ai-styling' ),
					'pattern'     => '^[a-zA-Z0-9_\-\.]+$',
				),
				'file_data'   => array(
					'type'        => 'string',
					'description' => __( 'Base64 encoded file data', 'wyvern-ai-styling' ),
				),
				'file_url'    => array(
					'type'        => 'string',
					'description' => __( 'URL of the file to download and upload (alternative to file_data)', 'wyvern-ai-styling' ),
					'format'      => 'uri',
				),
				'title'       => array(
					'type'        => 'string',
					'description' => __( 'Media title', 'wyvern-ai-styling' ),
				),
				'caption'     => array(
					'type'        => 'string',
					'description' => __( 'Media caption', 'wyvern-ai-styling' ),
				),
				'description' => array(
					'type'        => 'string',
					'description' => __( 'Media description', 'wyvern-ai-styling' ),
				),
				'alt_text'    => array(
					'type'        => 'string',
					'description' => __( 'Alt text for images (accessibility)', 'wyvern-ai-styling' ),
				),
			),
			'required'   => array( 'filename' ),
		);
	}

	/**
	 * Execute the tool
	 *
	 * @param array<string, mixed> $params Tool parameters.
	 * @return array<string, mixed>|WP_Error Upload result or error.
	 */
	public function execute( array $params ) {
		// Validate that either file_data or file_url is provided.
		if ( empty( $params['file_data'] ) && empty( $params['file_url'] ) ) {
			return new WP_Error(
				'missing_file_source',
				__( 'Either file_data or file_url must be provided', 'wyvern-ai-styling' )
			);
		}

		$filename = sanitize_file_name( $params['filename'] );

		// Validate filename.
		if ( empty( $filename ) ) {
			return new WP_Error(
				'invalid_filename',
				__( 'Invalid filename provided', 'wyvern-ai-styling' )
			);
		}

		// Handle file upload based on source.
		if ( ! empty( $params['file_data'] ) ) {
			$result = $this->upload_from_base64( $filename, $params['file_data'] );
		} else {
			$result = $this->upload_from_url( $filename, $params['file_url'] );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$attachment_id = $result;

		// Set attachment metadata.
		if ( ! empty( $params['title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $attachment_id,
					'post_title' => sanitize_text_field( $params['title'] ),
				)
			);
		}

		if ( ! empty( $params['caption'] ) ) {
			wp_update_post(
				array(
					'ID'           => $attachment_id,
					'post_excerpt' => sanitize_textarea_field( $params['caption'] ),
				)
			);
		}

		if ( ! empty( $params['description'] ) ) {
			wp_update_post(
				array(
					'ID'           => $attachment_id,
					'post_content' => sanitize_textarea_field( $params['description'] ),
				)
			);
		}

		if ( ! empty( $params['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $params['alt_text'] ) );
		}

		// Get attachment details.
		$attachment    = get_post( $attachment_id );
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$attached_file = get_attached_file( $attachment_id );

		return array(
			'attachment_id' => $attachment_id,
			'title'         => $attachment ? $attachment->post_title : '',
			'filename'      => $attached_file ? basename( $attached_file ) : '',
			'url'           => wp_get_attachment_url( $attachment_id ),
			'mime_type'     => get_post_mime_type( $attachment_id ),
			'file_size'     => $metadata['filesize'] ?? null,
			'width'         => $metadata['width'] ?? null,
			'height'        => $metadata['height'] ?? null,
			'message'       => __( 'Media uploaded successfully', 'wyvern-ai-styling' ),
		);
	}

	/**
	 * Create attachment from file path.
	 *
	 * Common logic extracted from upload_from_base64 and upload_from_url to eliminate code duplication.
	 *
	 * @param string $file_path Full path to the file.
	 * @param string $filename  Original filename for title generation.
	 * @return int|WP_Error Attachment ID or error.
	 */
	private function create_attachment_from_file( string $file_path, string $filename ) {
		// Verify file type.
		$filetype = wp_check_filetype( $file_path );
		if ( ! $filetype['type'] ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $file_path );
			return new WP_Error(
				'invalid_file_type',
				__( 'File type is not allowed', 'wyvern-ai-styling' )
			);
		}

		// Create attachment.
		$attachment = array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path );

		/**
		 * Check for errors or failures.
		 *
		 * @phpstan-ignore-next-line wp_insert_attachment can return int|WP_Error
		 */
		$is_error = is_wp_error( $attachment_id );
		$is_zero  = ! $is_error && 0 === $attachment_id;

		if ( $is_error || $is_zero ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $file_path );
			if ( $is_error ) {
				/**
				 * Type assertion for error case.
				 *
				 * @var \WP_Error $attachment_id
				 */
				return $attachment_id;
			}
			return new WP_Error(
				'attachment_creation_failed',
				__( 'Failed to create attachment', 'wyvern-ai-styling' )
			);
		}

		// Generate attachment metadata.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		return $attachment_id;
	}

	/**
	 * Upload file from base64 encoded data
	 *
	 * @param string $filename Filename.
	 * @param string $file_data Base64 encoded file data.
	 * @return int|WP_Error Attachment ID or error.
	 */
	private function upload_from_base64( string $filename, string $file_data ) {
		// Decode base64 data.
		$decoded = base64_decode( $file_data, true );

		if ( false === $decoded ) {
			return new WP_Error(
				'invalid_base64',
				__( 'Invalid base64 encoded data', 'wyvern-ai-styling' )
			);
		}

		// Create temporary file.
		$upload_dir = wp_upload_dir();
		$temp_file  = $upload_dir['path'] . '/' . wp_unique_filename( $upload_dir['path'], $filename );

		// Write decoded data to file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $temp_file, $decoded ) ) {
			return new WP_Error(
				'file_write_failed',
				__( 'Failed to write file to disk', 'wyvern-ai-styling' )
			);
		}

		return $this->create_attachment_from_file( $temp_file, $filename );
	}

	/**
	 * Upload file from URL
	 *
	 * @param string $filename Filename.
	 * @param string $file_url URL of the file.
	 * @return int|WP_Error Attachment ID or error.
	 */
	private function upload_from_url( string $filename, string $file_url ) {
		// Validate URL.
		if ( ! filter_var( $file_url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error(
				'invalid_url',
				__( 'Invalid URL provided', 'wyvern-ai-styling' )
			);
		}

		// Download file.
		$temp_file = download_url( $file_url );

		if ( is_wp_error( $temp_file ) ) {
			return new WP_Error(
				'download_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to download file: %s', 'wyvern-ai-styling' ),
					$temp_file->get_error_message()
				)
			);
		}

		// Move to uploads directory.
		$upload_dir = wp_upload_dir();
		$new_file   = $upload_dir['path'] . '/' . wp_unique_filename( $upload_dir['path'], $filename );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( ! rename( $temp_file, $new_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $temp_file );
			return new WP_Error(
				'file_move_failed',
				__( 'Failed to move uploaded file', 'wyvern-ai-styling' )
			);
		}

		return $this->create_attachment_from_file( $new_file, $filename );
	}
}
