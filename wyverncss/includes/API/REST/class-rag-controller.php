<?php
/**
 * RAG REST Controller
 *
 * Proxies RAG (Retrieval Augmented Generation) requests from WordPress to cloud service.
 * Handles semantic search, content indexing, and RAG-enhanced AI generation.
 *
 * @package    WyvernCSS
 * @subpackage WyvernCSS/includes/API/REST
 * @since      1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\API\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WyvernCSS\API\REST\Traits\Cloud_Service_Proxy;

/**
 * RAG REST Controller Class
 *
 * Provides REST endpoints that proxy to cloud service RAG API.
 * Enables RAG-enhanced AI responses with WordPress content.
 */
class RAG_Controller extends WP_REST_Controller {

	use Cloud_Service_Proxy;

	/**
	 * API namespace.
	 */
	private const NAMESPACE = 'wyverncss/v1';

	/**
	 * REST base path.
	 */
	private const REST_BASE = 'rag';

	/**
	 * Constructor.
	 *
	 * @param string $cloud_service_url Optional. Cloud service URL. Defaults to localhost.
	 */
	public function __construct( string $cloud_service_url = '' ) {
		$this->namespace = self::NAMESPACE;
		$this->rest_base = self::REST_BASE;
		$this->init_cloud_service_url( $cloud_service_url );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /rag/generate - Generate AI response with RAG.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_with_rag' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_generate_args(),
			)
		);

		// POST /rag/index - Index content for semantic search.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/index',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'index_content' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_index_args(),
			)
		);

		// POST /rag/search - Semantic search for content.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/search',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'semantic_search' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_search_args(),
			)
		);

		// DELETE /rag/content/{content_type}/{content_id} - Delete indexed content.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/content/(?P<content_type>[a-z0-9_-]+)/(?P<content_id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_indexed_content' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'content_type' => array(
						'description'       => __( 'Content type (post, page, custom)', 'wyvern-ai-styling' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'content_id'   => array(
						'description'       => __( 'Content ID', 'wyvern-ai-styling' ),
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		// GET /rag/stats - Get RAG statistics.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_rag_stats' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Generate AI response with RAG.
	 *
	 * Proxies to cloud service /api/v1/rag/generate endpoint.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function generate_with_rag( WP_REST_Request $request ) {
		$license_key = get_option( 'wyverncss_license_key', '' );

		if ( empty( $license_key ) ) {
			return new WP_Error(
				'license_required',
				__( 'License key not configured. Please configure your license key in WyvernCSS settings.', 'wyvern-ai-styling' ),
				array( 'status' => 401 )
			);
		}

		// Prepare request body.
		$body = array(
			'bot_slug'       => $request->get_param( 'bot_slug' ),
			'user_message'   => $request->get_param( 'user_message' ),
			'enable_rag'     => $request->get_param( 'enable_rag' ) ?? true,
			'top_k'          => $request->get_param( 'top_k' ) ?? 5,
			'min_similarity' => $request->get_param( 'min_similarity' ) ?? 0.7,
		);

		// Add optional conversation history.
		if ( $request->has_param( 'conversation_history' ) ) {
			$body['conversation_history'] = $request->get_param( 'conversation_history' );
		}

		// Proxy request to cloud service.
		$response = $this->proxy_to_cloud_service(
			'/api/v1/rag/generate',
			'POST',
			$body,
			$license_key,
			'RAG'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Index content for semantic search.
	 *
	 * Proxies to cloud service /api/v1/rag/index endpoint.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function index_content( WP_REST_Request $request ) {
		$license_key = get_option( 'wyverncss_license_key', '' );

		if ( empty( $license_key ) ) {
			return new WP_Error(
				'license_required',
				__( 'License key not configured. Please configure your license key in WyvernCSS settings.', 'wyvern-ai-styling' ),
				array( 'status' => 401 )
			);
		}

		// Prepare request body.
		$body = array(
			'content_type' => $request->get_param( 'content_type' ),
			'content_id'   => $request->get_param( 'content_id' ),
			'title'        => $request->get_param( 'title' ),
			'content'      => $request->get_param( 'content' ),
		);

		// Add optional fields.
		if ( $request->has_param( 'url' ) ) {
			$body['url'] = $request->get_param( 'url' );
		}
		if ( $request->has_param( 'excerpt' ) ) {
			$body['excerpt'] = $request->get_param( 'excerpt' );
		}

		// Proxy request to cloud service.
		$response = $this->proxy_to_cloud_service(
			'/api/v1/rag/index',
			'POST',
			$body,
			$license_key,
			'RAG'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new WP_REST_Response( $response, 201 );
	}

	/**
	 * Semantic search for content.
	 *
	 * Proxies to cloud service /api/v1/rag/search endpoint.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function semantic_search( WP_REST_Request $request ) {
		$license_key = get_option( 'wyverncss_license_key', '' );

		if ( empty( $license_key ) ) {
			return new WP_Error(
				'license_required',
				__( 'License key not configured. Please configure your license key in WyvernCSS settings.', 'wyvern-ai-styling' ),
				array( 'status' => 401 )
			);
		}

		// Prepare request body.
		$body = array(
			'query'          => $request->get_param( 'query' ),
			'limit'          => $request->get_param( 'limit' ) ?? 10,
			'min_similarity' => $request->get_param( 'min_similarity' ) ?? 0.7,
		);

		// Add optional content types filter.
		if ( $request->has_param( 'content_types' ) ) {
			$body['content_types'] = $request->get_param( 'content_types' );
		}

		// Proxy request to cloud service.
		$response = $this->proxy_to_cloud_service(
			'/api/v1/rag/search',
			'POST',
			$body,
			$license_key,
			'RAG'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Delete indexed content.
	 *
	 * Proxies to cloud service /api/v1/rag/content/{type}/{id} endpoint.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function delete_indexed_content( WP_REST_Request $request ) {
		$license_key = get_option( 'wyverncss_license_key', '' );

		if ( empty( $license_key ) ) {
			return new WP_Error(
				'license_required',
				__( 'License key not configured. Please configure your license key in WyvernCSS settings.', 'wyvern-ai-styling' ),
				array( 'status' => 401 )
			);
		}

		$content_type = $request->get_param( 'content_type' );
		$content_id   = $request->get_param( 'content_id' );

		// Proxy request to cloud service.
		$response = $this->proxy_to_cloud_service(
			'/api/v1/rag/content/' . $content_type . '/' . $content_id,
			'DELETE',
			array(),
			$license_key,
			'RAG'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Get RAG statistics.
	 *
	 * Proxies to cloud service /api/v1/rag/stats endpoint.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function get_rag_stats( WP_REST_Request $request ) {
		$license_key = get_option( 'wyverncss_license_key', '' );

		if ( empty( $license_key ) ) {
			return new WP_Error(
				'license_required',
				__( 'License key not configured. Please configure your license key in WyvernCSS settings.', 'wyvern-ai-styling' ),
				array( 'status' => 401 )
			);
		}

		// Proxy request to cloud service.
		$response = $this->proxy_to_cloud_service(
			'/api/v1/rag/stats',
			'GET',
			array(),
			$license_key,
			'RAG'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Get generate endpoint arguments.
	 *
	 * @return array<string, mixed> Endpoint arguments.
	 */
	private function get_generate_args(): array {
		return array(
			'bot_slug'             => array(
				'description'       => __( 'Bot slug identifier', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'user_message'         => array(
				'description'       => __( 'User message/question', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'enable_rag'           => array(
				'description' => __( 'Enable RAG (Retrieval Augmented Generation)', 'wyvern-ai-styling' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'top_k'                => array(
				'description' => __( 'Number of content pieces to retrieve (1-20)', 'wyvern-ai-styling' ),
				'type'        => 'integer',
				'default'     => 5,
				'minimum'     => 1,
				'maximum'     => 20,
			),
			'min_similarity'       => array(
				'description' => __( 'Minimum similarity threshold (0.0-1.0)', 'wyvern-ai-styling' ),
				'type'        => 'number',
				'default'     => 0.7,
				'minimum'     => 0.0,
				'maximum'     => 1.0,
			),
			'conversation_history' => array(
				'description' => __( 'Previous conversation messages for context', 'wyvern-ai-styling' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'object',
				),
			),
		);
	}

	/**
	 * Get index endpoint arguments.
	 *
	 * @return array<string, mixed> Endpoint arguments.
	 */
	private function get_index_args(): array {
		return array(
			'content_type' => array(
				'description'       => __( 'Content type (post, page, custom)', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'content_id'   => array(
				'description'       => __( 'WordPress content ID', 'wyvern-ai-styling' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'title'        => array(
				'description'       => __( 'Content title', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content'      => array(
				'description'       => __( 'Full content text', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'wp_kses_post',
			),
			'url'          => array(
				'description'       => __( 'Content URL', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'excerpt'      => array(
				'description'       => __( 'Content excerpt/summary', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
	}

	/**
	 * Get search endpoint arguments.
	 *
	 * @return array<string, mixed> Endpoint arguments.
	 */
	private function get_search_args(): array {
		return array(
			'query'          => array(
				'description'       => __( 'Search query text', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'limit'          => array(
				'description' => __( 'Maximum number of results (1-50)', 'wyvern-ai-styling' ),
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 50,
			),
			'min_similarity' => array(
				'description' => __( 'Minimum similarity threshold (0.0-1.0)', 'wyvern-ai-styling' ),
				'type'        => 'number',
				'default'     => 0.7,
				'minimum'     => 0.0,
				'maximum'     => 1.0,
			),
			'content_types'  => array(
				'description' => __( 'Filter by content types', 'wyvern-ai-styling' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
				),
			),
		);
	}

	/**
	 * Check if user has permission to use RAG features.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return bool|WP_Error True if user can access, error otherwise.
	 */
	public function check_permission( WP_REST_Request $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to use RAG features.', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check if user has admin permission.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return bool|WP_Error True if user is admin, error otherwise.
	 */
	public function check_admin_permission( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage RAG settings.', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
