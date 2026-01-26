<?php
/**
 * Post Operations Handler
 *
 * Handles bulk post operations for Admin AI.
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
 * Post Operations Handler Class
 *
 * Provides methods for:
 * - Bulk updating categories
 * - Bulk updating tags
 * - Bulk publishing posts
 * - Bulk unpublishing posts
 * - Deleting old drafts
 *
 * All methods check user capabilities and return structured results.
 *
 * @since 1.0.0
 */
class Post_Operations_Handler {

	/**
	 * Bulk update categories for posts.
	 *
	 * @param array<int>    $post_ids   Array of post IDs.
	 * @param array<string> $categories Array of category names or slugs.
	 * @return array<string, mixed>|WP_Error Result array or error.
	 */
	public function bulk_update_categories( array $post_ids, array $categories ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'permission_denied',
				esc_html__( 'You do not have permission to edit posts.', 'wyverncss' ),
				array( 'status' => 403 )
			);
		}

		if ( empty( $post_ids ) ) {
			return new WP_Error(
				'invalid_params',
				esc_html__( 'Post IDs are required.', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $categories ) ) {
			return new WP_Error(
				'invalid_params',
				esc_html__( 'Categories are required.', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		// Convert category names/slugs to IDs.
		$category_ids = array();
		foreach ( $categories as $category ) {
			$term = get_term_by( 'name', $category, 'category' );
			if ( ! $term ) {
				$term = get_term_by( 'slug', $category, 'category' );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$category_ids[] = $term->term_id;
			}
		}

		if ( empty( $category_ids ) ) {
			return new WP_Error(
				'invalid_categories',
				esc_html__( 'No valid categories found.', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		$affected_count = 0;
		$updated_posts  = array();

		foreach ( $post_ids as $post_id ) {
			if ( ! is_int( $post_id ) ) {
				continue;
			}

			// Check if user can edit this specific post.
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$result = wp_set_post_categories( $post_id, $category_ids, false );
			if ( ! is_wp_error( $result ) ) {
				++$affected_count;
				$updated_posts[] = $post_id;
			}
		}

		return array(
			'success'        => true,
			'affected_count' => $affected_count,
			'post_ids'       => $updated_posts,
			'action'         => 'bulk_update_categories',
			'message'        => sprintf(
				/* translators: %d: number of posts updated */
				esc_html( _n( 'Updated categories for %d post.', 'Updated categories for %d posts.', $affected_count, 'wyverncss' ) ),
				$affected_count
			),
		);
	}

	/**
	 * Bulk update tags for posts.
	 *
	 * @param array<int>    $post_ids Array of post IDs.
	 * @param array<string> $tags     Array of tag names.
	 * @return array<string, mixed>|WP_Error Result array or error.
	 */
	public function bulk_update_tags( array $post_ids, array $tags ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'permission_denied',
				esc_html__( 'You do not have permission to edit posts.', 'wyverncss' ),
				array( 'status' => 403 )
			);
		}

		if ( empty( $post_ids ) ) {
			return new WP_Error(
				'invalid_params',
				esc_html__( 'Post IDs are required.', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $tags ) ) {
			return new WP_Error(
				'invalid_params',
				esc_html__( 'Tags are required.', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		$affected_count = 0;
		$updated_posts  = array();

		foreach ( $post_ids as $post_id ) {
			if ( ! is_int( $post_id ) ) {
				continue;
			}

			// Check if user can edit this specific post.
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$result = wp_set_post_tags( $post_id, $tags, false );
			if ( ! is_wp_error( $result ) ) {
				++$affected_count;
				$updated_posts[] = $post_id;
			}
		}

		return array(
			'success'        => true,
			'affected_count' => $affected_count,
			'post_ids'       => $updated_posts,
			'action'         => 'bulk_update_tags',
			'message'        => sprintf(
				/* translators: %d: number of posts updated */
				esc_html( _n( 'Updated tags for %d post.', 'Updated tags for %d posts.', $affected_count, 'wyverncss' ) ),
				$affected_count
			),
		);
	}

	/**
	 * Bulk publish posts matching criteria.
	 *
	 * @param array<string, mixed> $criteria Criteria (status, date_after, date_before, category, etc.).
	 * @return array<string, mixed>|WP_Error Result array or error.
	 */
	public function bulk_publish_posts( array $criteria ) {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error(
				'permission_denied',
				esc_html__( 'You do not have permission to publish posts.', 'wyverncss' ),
				array( 'status' => 403 )
			);
		}

		// Build query args.
		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => $criteria['status'] ?? 'draft',
			'posts_per_page' => 100, // Safety limit.
			'fields'         => 'ids',
		);

		// Add date filters if provided.
		if ( ! empty( $criteria['date_after'] ) && is_string( $criteria['date_after'] ) ) {
			$query_args['date_query'][] = array(
				'after'     => sanitize_text_field( $criteria['date_after'] ),
				'inclusive' => true,
			);
		}

		if ( ! empty( $criteria['date_before'] ) && is_string( $criteria['date_before'] ) ) {
			$query_args['date_query'][] = array(
				'before'    => sanitize_text_field( $criteria['date_before'] ),
				'inclusive' => true,
			);
		}

		// Add category filter if provided.
		if ( ! empty( $criteria['category'] ) && is_string( $criteria['category'] ) ) {
			$query_args['category_name'] = sanitize_text_field( $criteria['category'] );
		}

		$query           = new WP_Query( $query_args );
		$post_ids        = $query->posts;
		$affected_count  = 0;
		$published_posts = array();

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$result = wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);

			if ( ! is_wp_error( $result ) && 0 !== $result ) {
				++$affected_count;
				$published_posts[] = $post_id;
			}
		}

		return array(
			'success'        => true,
			'affected_count' => $affected_count,
			'post_ids'       => $published_posts,
			'action'         => 'bulk_publish_posts',
			'message'        => sprintf(
				/* translators: %d: number of posts published */
				esc_html( _n( 'Published %d post.', 'Published %d posts.', $affected_count, 'wyverncss' ) ),
				$affected_count
			),
		);
	}

	/**
	 * Bulk unpublish posts matching criteria.
	 *
	 * @param array<string, mixed> $criteria Criteria (date_after, date_before, category, etc.).
	 * @return array<string, mixed>|WP_Error Result array or error.
	 */
	public function bulk_unpublish_posts( array $criteria ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'permission_denied',
				esc_html__( 'You do not have permission to edit posts.', 'wyverncss' ),
				array( 'status' => 403 )
			);
		}

		// Build query args.
		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 100, // Safety limit.
			'fields'         => 'ids',
		);

		// Add date filters if provided.
		if ( ! empty( $criteria['date_after'] ) && is_string( $criteria['date_after'] ) ) {
			$query_args['date_query'][] = array(
				'after'     => sanitize_text_field( $criteria['date_after'] ),
				'inclusive' => true,
			);
		}

		if ( ! empty( $criteria['date_before'] ) && is_string( $criteria['date_before'] ) ) {
			$query_args['date_query'][] = array(
				'before'    => sanitize_text_field( $criteria['date_before'] ),
				'inclusive' => true,
			);
		}

		// Add category filter if provided.
		if ( ! empty( $criteria['category'] ) && is_string( $criteria['category'] ) ) {
			$query_args['category_name'] = sanitize_text_field( $criteria['category'] );
		}

		$query             = new WP_Query( $query_args );
		$post_ids          = $query->posts;
		$affected_count    = 0;
		$unpublished_posts = array();

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$result = wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				)
			);

			if ( ! is_wp_error( $result ) && 0 !== $result ) {
				++$affected_count;
				$unpublished_posts[] = $post_id;
			}
		}

		return array(
			'success'        => true,
			'affected_count' => $affected_count,
			'post_ids'       => $unpublished_posts,
			'action'         => 'bulk_unpublish_posts',
			'message'        => sprintf(
				/* translators: %d: number of posts unpublished */
				esc_html( _n( 'Unpublished %d post.', 'Unpublished %d posts.', $affected_count, 'wyverncss' ) ),
				$affected_count
			),
		);
	}

	/**
	 * Delete drafts older than specified days.
	 *
	 * @param int $days_old Number of days (default 30).
	 * @return array<string, mixed>|WP_Error Result array or error.
	 */
	public function delete_old_drafts( int $days_old = 30 ) {
		if ( ! current_user_can( 'delete_posts' ) ) {
			return new WP_Error(
				'permission_denied',
				esc_html__( 'You do not have permission to delete posts.', 'wyverncss' ),
				array( 'status' => 403 )
			);
		}

		if ( $days_old < 1 ) {
			return new WP_Error(
				'invalid_params',
				esc_html__( 'Days must be at least 1.', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		// Find drafts older than specified days.
		$date_before = gmdate( 'Y-m-d', strtotime( "-{$days_old} days" ) );

		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => 'draft',
			'posts_per_page' => 100, // Safety limit.
			'date_query'     => array(
				array(
					'before'    => $date_before,
					'inclusive' => true,
				),
			),
			'fields'         => 'ids',
		);

		$query          = new WP_Query( $query_args );
		$post_ids       = $query->posts;
		$affected_count = 0;
		$deleted_posts  = array();

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'delete_post', $post_id ) ) {
				continue;
			}

			// Permanently delete (bypass trash).
			$result = wp_delete_post( $post_id, true );

			if ( false !== $result && null !== $result ) {
				++$affected_count;
				$deleted_posts[] = $post_id;
			}
		}

		return array(
			'success'        => true,
			'affected_count' => $affected_count,
			'post_ids'       => $deleted_posts,
			'action'         => 'delete_old_drafts',
			'message'        => sprintf(
				/* translators: %1$d: number of posts deleted, %2$d: number of days */
				esc_html( _n( 'Deleted %1$d draft older than %2$d days.', 'Deleted %1$d drafts older than %2$d days.', $affected_count, 'wyverncss' ) ),
				$affected_count,
				$days_old
			),
		);
	}
}
