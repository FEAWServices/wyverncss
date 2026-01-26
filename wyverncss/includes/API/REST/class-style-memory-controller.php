<?php
/**
 * Style Memory REST Controller
 *
 * REST API endpoints for managing user style preferences.
 *
 * @package WyvernCSS
 * @subpackage API
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\API\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WyvernCSS\API\RESTController;
use WyvernCSS\Styles\Style_Memory;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Style Memory Controller Class
 *
 * Handles REST endpoints for:
 * - GET /style-memory - Get user's style memory
 * - POST /style-memory/learn - Learn from a style request
 * - POST /style-memory/favorites/colors - Add favorite color
 * - DELETE /style-memory/favorites/colors - Remove favorite color
 * - GET /style-memory/suggestions - Get suggestions for a prompt
 * - DELETE /style-memory - Clear all style memory
 *
 * @since 1.0.0
 */
class Style_Memory_Controller extends RESTController {

	/**
	 * Style Memory service.
	 *
	 * @var Style_Memory
	 */
	private Style_Memory $style_memory;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->style_memory = new Style_Memory();
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Get style memory.
		register_rest_route(
			$this->namespace,
			'/style-memory',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_memory' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_memory' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Learn from style.
		register_rest_route(
			$this->namespace,
			'/style-memory/learn',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'learn' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'prompt' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'css'    => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		// Get suggestions.
		register_rest_route(
			$this->namespace,
			'/style-memory/suggestions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_suggestions' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'prompt' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Favorite colors.
		register_rest_route(
			$this->namespace,
			'/style-memory/favorites/colors',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_favorite_color' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'color' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_favorite_color' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'color' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Export/Import.
		register_rest_route(
			$this->namespace,
			'/style-memory/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_memory' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/style-memory/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import_memory' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'data' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		// Get history.
		register_rest_route(
			$this->namespace,
			'/style-memory/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_history' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'limit' => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Custom patterns (premium feature).
		register_rest_route(
			$this->namespace,
			'/style-memory/patterns',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_custom_patterns' ),
					'permission_callback' => array( $this, 'check_premium_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_custom_pattern' ),
					'permission_callback' => array( $this, 'check_premium_permission' ),
					'args'                => array(
						'name'   => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'css'    => array(
							'type'     => 'object',
							'required' => true,
						),
						'prompt' => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Single custom pattern.
		register_rest_route(
			$this->namespace,
			'/style-memory/patterns/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_custom_pattern' ),
					'permission_callback' => array( $this, 'check_premium_permission' ),
					'args'                => array(
						'id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_custom_pattern' ),
					'permission_callback' => array( $this, 'check_premium_permission' ),
					'args'                => array(
						'id'   => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'name' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_custom_pattern' ),
					'permission_callback' => array( $this, 'check_premium_permission' ),
					'args'                => array(
						'id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);
	}

	/**
	 * Get user's style memory.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Response.
	 */
	public function get_memory( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$memory  = $this->style_memory->get_memory( $user_id );

		return $this->success_response(
			array(
				'favorite_colors'  => $memory['favorite_colors'],
				'favorite_fonts'   => $memory['favorite_fonts'],
				'border_radius'    => $memory['border_radius_style'],
				'shadow'           => $memory['shadow_preference'],
				'history_count'    => count( $memory['style_history'] ),
			)
		);
	}

	/**
	 * Learn from a style request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Response.
	 */
	public function learn( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$prompt  = $request->get_param( 'prompt' ) ?? '';
		$css     = $request->get_param( 'css' ) ?? array();

		if ( ! is_string( $prompt ) || empty( $prompt ) ) {
			return $this->success_response( array( 'learned' => false ) );
		}

		if ( ! is_array( $css ) || empty( $css ) ) {
			return $this->success_response( array( 'learned' => false ) );
		}

		$this->style_memory->learn( $user_id, $prompt, $css );

		return $this->success_response( array( 'learned' => true ) );
	}

	/**
	 * Get suggestions based on style memory.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Response.
	 */
	public function get_suggestions( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$prompt  = $request->get_param( 'prompt' ) ?? '';

		if ( ! is_string( $prompt ) || empty( $prompt ) ) {
			return $this->success_response( array( 'suggestions' => array() ) );
		}

		$suggestions = $this->style_memory->get_suggestions( $user_id, $prompt );

		return $this->success_response( array( 'suggestions' => $suggestions ) );
	}

	/**
	 * Add a favorite color.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function add_favorite_color( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$color   = $request->get_param( 'color' ) ?? '';

		if ( ! is_string( $color ) || empty( $color ) ) {
			return $this->error_response( 'invalid_color', 'Color is required.', 400 );
		}

		$success = $this->style_memory->add_favorite_color( $user_id, $color );

		if ( ! $success ) {
			return $this->error_response( 'save_failed', 'Failed to save color.', 500 );
		}

		return $this->success_response(
			array(
				'added'           => true,
				'color'           => $color,
				'favorite_colors' => $this->style_memory->get_favorite_colors( $user_id ),
			)
		);
	}

	/**
	 * Remove a favorite color.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function remove_favorite_color( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$color   = $request->get_param( 'color' ) ?? '';

		if ( ! is_string( $color ) || empty( $color ) ) {
			return $this->error_response( 'invalid_color', 'Color is required.', 400 );
		}

		$success = $this->style_memory->remove_favorite_color( $user_id, $color );

		return $this->success_response(
			array(
				'removed'         => $success,
				'color'           => $color,
				'favorite_colors' => $this->style_memory->get_favorite_colors( $user_id ),
			)
		);
	}

	/**
	 * Clear all style memory.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Response.
	 */
	public function clear_memory( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$success = $this->style_memory->clear_memory( $user_id );

		return $this->success_response( array( 'cleared' => $success ) );
	}

	/**
	 * Export style memory.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Response.
	 */
	public function export_memory( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$export  = $this->style_memory->export_memory( $user_id );

		return $this->success_response( $export );
	}

	/**
	 * Import style memory.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function import_memory( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$data    = $request->get_param( 'data' );

		if ( ! is_array( $data ) ) {
			return $this->error_response( 'invalid_data', 'Import data must be an object.', 400 );
		}

		$success = $this->style_memory->import_memory( $user_id, $data );

		if ( ! $success ) {
			return $this->error_response( 'import_failed', 'Failed to import style memory.', 500 );
		}

		return $this->success_response( array( 'imported' => true ) );
	}

	/**
	 * Get style history.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Response.
	 */
	public function get_history( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$limit   = $request->get_param( 'limit' );
		$limit   = is_numeric( $limit ) ? (int) $limit : 10;
		$history = $this->style_memory->get_history( $user_id, $limit );

		return $this->success_response( array( 'history' => $history ) );
	}

	/**
	 * Check premium permission.
	 *
	 * Custom patterns are a premium feature.
	 *
	 * @return bool|WP_Error True if user has premium access.
	 */
	public function check_premium_permission() {
		// First check basic permission.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to access this endpoint.', 'wyverncss' ),
				array( 'status' => 401 )
			);
		}

		// Check if user has premium tier.
		$tier = $this->get_user_tier( get_current_user_id() );
		if ( 'free' === $tier ) {
			return new WP_Error(
				'rest_premium_required',
				__( 'Custom patterns are a premium feature. Please upgrade to save patterns.', 'wyverncss' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get user's subscription tier.
	 *
	 * @param int $user_id User ID.
	 * @return string Tier (free, premium, professional).
	 */
	protected function get_user_tier( int $user_id ): string {
		// Check Freemius integration if available.
		if ( function_exists( 'wyverncss_fs' ) ) {
			$fs = wyverncss_fs();
			if ( null !== $fs && $fs->is_paying() ) {
				return $fs->is_plan( 'professional' ) ? 'professional' : 'premium';
			}
		}

		return 'free';
	}

	/**
	 * Get all custom patterns.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Response.
	 */
	public function get_custom_patterns( WP_REST_Request $request ): WP_REST_Response {
		$user_id  = get_current_user_id();
		$patterns = $this->style_memory->get_custom_patterns( $user_id );

		return $this->success_response(
			array(
				'patterns' => $patterns,
				'count'    => count( $patterns ),
			)
		);
	}

	/**
	 * Save a custom pattern.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function save_custom_pattern( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$name    = $request->get_param( 'name' ) ?? '';
		$css     = $request->get_param( 'css' );
		$prompt  = $request->get_param( 'prompt' ) ?? '';

		if ( ! is_string( $name ) || empty( $name ) ) {
			return $this->error_response( 'invalid_name', 'Pattern name is required.', 400 );
		}

		if ( ! is_array( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS properties are required.', 400 );
		}

		$prompt = is_string( $prompt ) ? $prompt : '';

		$result = $this->style_memory->save_custom_pattern( $user_id, $name, $css, $prompt );

		if ( ! $result['success'] ) {
			return $this->error_response( 'save_failed', $result['error'] ?? 'Failed to save pattern.', 400 );
		}

		return $this->success_response(
			array(
				'saved'    => true,
				'id'       => $result['id'] ?? '',
				'patterns' => $this->style_memory->get_custom_patterns( $user_id ),
			)
		);
	}

	/**
	 * Get a single custom pattern.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function get_custom_pattern( WP_REST_Request $request ) {
		$user_id    = get_current_user_id();
		$pattern_id = $request->get_param( 'id' ) ?? '';

		if ( ! is_string( $pattern_id ) || empty( $pattern_id ) ) {
			return $this->error_response( 'invalid_id', 'Pattern ID is required.', 400 );
		}

		$pattern = $this->style_memory->get_custom_pattern( $user_id, $pattern_id );

		if ( null === $pattern ) {
			return $this->error_response( 'not_found', 'Pattern not found.', 404 );
		}

		return $this->success_response( array( 'pattern' => $pattern ) );
	}

	/**
	 * Update a custom pattern.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function update_custom_pattern( WP_REST_Request $request ) {
		$user_id    = get_current_user_id();
		$pattern_id = $request->get_param( 'id' ) ?? '';
		$name       = $request->get_param( 'name' ) ?? '';

		if ( ! is_string( $pattern_id ) || empty( $pattern_id ) ) {
			return $this->error_response( 'invalid_id', 'Pattern ID is required.', 400 );
		}

		if ( ! is_string( $name ) || empty( $name ) ) {
			return $this->error_response( 'invalid_name', 'Pattern name is required.', 400 );
		}

		$success = $this->style_memory->update_custom_pattern_name( $user_id, $pattern_id, $name );

		if ( ! $success ) {
			return $this->error_response( 'update_failed', 'Pattern not found or update failed.', 404 );
		}

		return $this->success_response(
			array(
				'updated'  => true,
				'patterns' => $this->style_memory->get_custom_patterns( $user_id ),
			)
		);
	}

	/**
	 * Delete a custom pattern.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function delete_custom_pattern( WP_REST_Request $request ) {
		$user_id    = get_current_user_id();
		$pattern_id = $request->get_param( 'id' ) ?? '';

		if ( ! is_string( $pattern_id ) || empty( $pattern_id ) ) {
			return $this->error_response( 'invalid_id', 'Pattern ID is required.', 400 );
		}

		$success = $this->style_memory->delete_custom_pattern( $user_id, $pattern_id );

		if ( ! $success ) {
			return $this->error_response( 'delete_failed', 'Pattern not found or delete failed.', 404 );
		}

		return $this->success_response(
			array(
				'deleted'  => true,
				'patterns' => $this->style_memory->get_custom_patterns( $user_id ),
			)
		);
	}
}
