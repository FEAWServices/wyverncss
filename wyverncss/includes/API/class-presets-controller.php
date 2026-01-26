<?php
/**
 * Presets REST API Controller
 *
 * REST API endpoints for managing user style presets.
 * Premium feature requiring authentication.
 *
 * @package WyvernCSS
 * @subpackage API
 * @since 1.1.0
 */

declare(strict_types=1);

namespace WyvernCSS\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WyvernCSS\Presets\StylePreset;

/**
 * Class PresetsController
 *
 * Handles REST API requests for style presets.
 *
 * @since 1.1.0
 */
class PresetsController extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wyverncss/v1';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'presets';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Get all presets / Create preset.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'category' => array(
							'description'       => __( 'Filter by category.', 'wyverncss' ),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Single preset operations.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the preset.', 'wyverncss' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the preset.', 'wyverncss' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Duplicate preset.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)/duplicate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'duplicate_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the preset to duplicate.', 'wyverncss' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// Export presets.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		// Import presets.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_items' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'data'    => array(
							'description' => __( 'JSON data to import.', 'wyverncss' ),
							'type'        => 'string',
							'required'    => true,
						),
						'replace' => array(
							'description' => __( 'Replace existing presets.', 'wyverncss' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);

		// Get categories.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_categories' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Check if a given request has access to get items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access, WP_Error otherwise.
	 */
	public function get_items_permissions_check( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to access presets.', 'wyverncss' ),
				array( 'status' => 401 )
			);
		}

		return $this->check_premium_access();
	}

	/**
	 * Check if a given request has access to get a specific item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access, WP_Error otherwise.
	 */
	public function get_item_permissions_check( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to create items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access, WP_Error otherwise.
	 */
	public function create_item_permissions_check( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to create presets.', 'wyverncss' ),
				array( 'status' => 401 )
			);
		}

		return $this->check_premium_access();
	}

	/**
	 * Check if a given request has access to update a specific item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access, WP_Error otherwise.
	 */
	public function update_item_permissions_check( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return $this->create_item_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to delete a specific item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access, WP_Error otherwise.
	 */
	public function delete_item_permissions_check( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return $this->create_item_permissions_check( $request );
	}

	/**
	 * Get all presets.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success.
	 */
	public function get_items( $request ): WP_REST_Response {
		$preset_manager = new StylePreset();
		$category       = $request->get_param( 'category' );

		if ( $category ) {
			$presets = $preset_manager->get_by_category( $category );
		} else {
			$presets = $preset_manager->get_all();
		}

		$data = array();
		foreach ( $presets as $preset ) {
			$data[] = $this->prepare_preset_for_response( $preset, $request )->get_data();
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get a single preset.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function get_item( $request ) {
		$preset_manager = new StylePreset();
		$preset_id      = $request->get_param( 'id' );
		$preset         = $preset_manager->get( $preset_id );

		if ( null === $preset ) {
			return new WP_Error(
				'preset_not_found',
				__( 'Preset not found.', 'wyverncss' ),
				array( 'status' => 404 )
			);
		}

		return $this->prepare_preset_for_response( $preset, $request );
	}

	/**
	 * Create a preset.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function create_item( $request ) {
		$preset_manager = new StylePreset();

		$name     = $request->get_param( 'name' );
		$css      = $request->get_param( 'css' );
		$category = $request->get_param( 'category' ) ?? 'custom';

		$result = $preset_manager->create( $name, $css, $category );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$preset = $preset_manager->get( $result );

		return new WP_REST_Response(
			$this->prepare_preset_for_response( $preset, $request )->get_data(),
			201
		);
	}

	/**
	 * Update a preset.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function update_item( $request ) {
		$preset_manager = new StylePreset();
		$preset_id      = $request->get_param( 'id' );

		$data = array();
		if ( $request->has_param( 'name' ) ) {
			$data['name'] = $request->get_param( 'name' );
		}
		if ( $request->has_param( 'css' ) ) {
			$data['css'] = $request->get_param( 'css' );
		}
		if ( $request->has_param( 'category' ) ) {
			$data['category'] = $request->get_param( 'category' );
		}

		$result = $preset_manager->update( $preset_id, $data );

		if ( is_wp_error( $result ) ) {
			$status = 'preset_not_found' === $result->get_error_code() ? 404 : 400;
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => $status )
			);
		}

		$preset = $preset_manager->get( $preset_id );

		return $this->prepare_preset_for_response( $preset, $request );
	}

	/**
	 * Delete a preset.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function delete_item( $request ) {
		$preset_manager = new StylePreset();
		$preset_id      = $request->get_param( 'id' );

		// Get preset before deletion for response.
		$preset = $preset_manager->get( $preset_id );
		if ( null === $preset ) {
			return new WP_Error(
				'preset_not_found',
				__( 'Preset not found.', 'wyverncss' ),
				array( 'status' => 404 )
			);
		}

		$result = $preset_manager->delete( $preset_id );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'deleted'  => true,
				'previous' => $this->prepare_preset_for_response( $preset, $request )->get_data(),
			),
			200
		);
	}

	/**
	 * Duplicate a preset.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function duplicate_item( WP_REST_Request $request ) {
		$preset_manager = new StylePreset();
		$preset_id      = $request->get_param( 'id' );

		$result = $preset_manager->duplicate( $preset_id );

		if ( is_wp_error( $result ) ) {
			$status = 'preset_not_found' === $result->get_error_code() ? 404 : 400;
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => $status )
			);
		}

		$preset = $preset_manager->get( $result );

		return new WP_REST_Response(
			$this->prepare_preset_for_response( $preset, $request )->get_data(),
			201
		);
	}

	/**
	 * Export all presets.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function export_items( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$preset_manager = new StylePreset();

		return new WP_REST_Response(
			array(
				'data'        => $preset_manager->export(),
				'count'       => $preset_manager->get_count(),
				'exported_at' => current_time( 'mysql' ),
			),
			200
		);
	}

	/**
	 * Import presets.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function import_items( WP_REST_Request $request ) {
		$preset_manager = new StylePreset();

		$json    = $request->get_param( 'data' );
		$replace = $request->get_param( 'replace' ) ?? false;

		$result = $preset_manager->import( $json, $replace );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Get categories.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_categories( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$preset_manager = new StylePreset();

		return new WP_REST_Response(
			$preset_manager->get_categories(),
			200
		);
	}

	/**
	 * Prepare a preset for response.
	 *
	 * This is a custom method to avoid overriding the parent's prepare_item_for_response
	 * which has a different signature.
	 *
	 * @param array<string, mixed>|null $preset  The preset data.
	 * @param WP_REST_Request           $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	private function prepare_preset_for_response( ?array $preset, WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( null === $preset ) {
			return new WP_REST_Response( null, 200 );
		}

		$data = array(
			'id'         => $preset['id'] ?? '',
			'name'       => $preset['name'] ?? '',
			'css'        => $preset['css'] ?? array(),
			'category'   => $preset['category'] ?? 'custom',
			'created_at' => $preset['created_at'] ?? '',
			'updated_at' => $preset['updated_at'] ?? '',
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get the preset schema.
	 *
	 * @return array<string, mixed> Schema array.
	 */
	public function get_item_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'preset',
			'type'       => 'object',
			'properties' => array(
				'id'         => array(
					'description' => __( 'Unique identifier for the preset.', 'wyverncss' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'name'       => array(
					'description' => __( 'Display name for the preset.', 'wyverncss' ),
					'type'        => 'string',
					'required'    => true,
				),
				'css'        => array(
					'description' => __( 'CSS properties for the preset.', 'wyverncss' ),
					'type'        => 'object',
					'required'    => true,
				),
				'category'   => array(
					'description' => __( 'Category for organization.', 'wyverncss' ),
					'type'        => 'string',
					'default'     => 'custom',
				),
				'created_at' => array(
					'description' => __( 'Creation timestamp.', 'wyverncss' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'readonly'    => true,
				),
				'updated_at' => array(
					'description' => __( 'Last update timestamp.', 'wyverncss' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Check premium access.
	 *
	 * Currently allows all logged-in users for development.
	 * In production, this will verify the user has a premium subscription.
	 *
	 * @return true Always returns true for development.
	 */
	private function check_premium_access(): true {
		// Check if user has premium tier via Freemius or custom logic.
		// For development, allow all logged-in users.
		// In production, this would verify tier and return WP_Error for non-premium.

		return true;
	}
}
