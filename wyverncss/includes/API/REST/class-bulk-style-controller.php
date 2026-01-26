<?php
/**
 * Bulk Style REST Controller
 *
 * REST API endpoints for bulk styling operations.
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
use WyvernCSS\Generator\Bulk_Styler;
use WyvernCSS\Freemius\Freemius_Integration;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Bulk Style Controller Class
 *
 * Handles REST endpoints for:
 * - POST /bulk-style - Apply style to multiple blocks
 * - POST /bulk-style/selector - Apply style by selector
 * - POST /bulk-style/groups - Apply styles to grouped blocks
 * - POST /bulk-style/stylesheet - Generate combined stylesheet
 * - POST /bulk-style/validate - Validate blocks for bulk styling
 *
 * @since 1.0.0
 */
class Bulk_Style_Controller extends RESTController {

	/**
	 * Bulk Styler service.
	 *
	 * @var Bulk_Styler
	 */
	private Bulk_Styler $bulk_styler;

	/**
	 * Freemius Integration.
	 *
	 * @var Freemius_Integration
	 */
	private Freemius_Integration $freemius;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->bulk_styler = new Bulk_Styler();
		$this->freemius    = Freemius_Integration::get_instance();
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Bulk apply to blocks.
		register_rest_route(
			$this->namespace,
			'/bulk-style',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk_style_blocks' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'prompt' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_prompt' ),
					),
					'blocks' => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'object' ),
					),
				),
			)
		);

		// Apply by selector.
		register_rest_route(
			$this->namespace,
			'/bulk-style/selector',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'style_by_selector' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'prompt'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_prompt' ),
					),
					'selector' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Apply to groups.
		register_rest_route(
			$this->namespace,
			'/bulk-style/groups',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'style_groups' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'prompts' => array(
						'type'     => 'object',
						'required' => true,
					),
					'blocks'  => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'object' ),
					),
				),
			)
		);

		// Generate stylesheet.
		register_rest_route(
			$this->namespace,
			'/bulk-style/stylesheet',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_stylesheet' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'rules' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		// Validate blocks.
		register_rest_route(
			$this->namespace,
			'/bulk-style/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_blocks' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'blocks' => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'object' ),
					),
				),
			)
		);
	}

	/**
	 * Check premium permission.
	 *
	 * Bulk styling is a premium feature.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function check_premium_permission( WP_REST_Request $request ) {
		$base_check = $this->check_permission( $request );

		if ( is_wp_error( $base_check ) ) {
			return $base_check;
		}

		// Check for premium tier.
		if ( ! $this->freemius->is_premium() && ! $this->freemius->is_trial() ) {
			return new WP_Error(
				'premium_required',
				esc_html__( 'Bulk styling is a premium feature. Upgrade to access this functionality.', 'wyvern-ai-styling' ),
				array(
					'status'      => 403,
					'upgrade_url' => $this->freemius->get_upgrade_url(),
				)
			);
		}

		return true;
	}

	/**
	 * Bulk style multiple blocks.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function bulk_style_blocks( WP_REST_Request $request ) {
		$prompt = $request->get_param( 'prompt' );
		$blocks = $request->get_param( 'blocks' );

		if ( ! is_string( $prompt ) || empty( $prompt ) ) {
			return $this->error_response( 'invalid_prompt', 'Prompt is required.', 400 );
		}

		if ( ! is_array( $blocks ) || empty( $blocks ) ) {
			return $this->error_response( 'invalid_blocks', 'Blocks array is required.', 400 );
		}

		$user_tier = $this->freemius->get_plan();

		$result = $this->bulk_styler->apply_to_blocks(
			$prompt,
			$blocks,
			array( 'user_tier' => $user_tier )
		);

		if ( ! $result['success'] && ! ( $result['partial'] ?? false ) ) {
			return $this->error_response(
				'bulk_style_failed',
				$result['error'] ?? 'Failed to apply bulk styles.',
				400
			);
		}

		return $this->success_response( $result );
	}

	/**
	 * Apply style by selector.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function style_by_selector( WP_REST_Request $request ) {
		$prompt   = $request->get_param( 'prompt' );
		$selector = $request->get_param( 'selector' );

		if ( ! is_string( $prompt ) || empty( $prompt ) ) {
			return $this->error_response( 'invalid_prompt', 'Prompt is required.', 400 );
		}

		if ( ! is_string( $selector ) || empty( $selector ) ) {
			return $this->error_response( 'invalid_selector', 'Selector is required.', 400 );
		}

		$user_tier = $this->freemius->get_plan();

		$result = $this->bulk_styler->apply_by_selector(
			$prompt,
			$selector,
			array( 'user_tier' => $user_tier )
		);

		if ( ! $result['success'] ) {
			return $this->error_response(
				'selector_style_failed',
				$result['error'] ?? 'Failed to apply style to selector.',
				400
			);
		}

		return $this->success_response( $result );
	}

	/**
	 * Apply styles to grouped blocks.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function style_groups( WP_REST_Request $request ) {
		$prompts = $request->get_param( 'prompts' );
		$blocks  = $request->get_param( 'blocks' );

		if ( ! is_array( $prompts ) || empty( $prompts ) ) {
			return $this->error_response( 'invalid_prompts', 'Prompts object is required.', 400 );
		}

		if ( ! is_array( $blocks ) || empty( $blocks ) ) {
			return $this->error_response( 'invalid_blocks', 'Blocks array is required.', 400 );
		}

		$user_tier = $this->freemius->get_plan();

		// Group blocks by type.
		$grouped = $this->bulk_styler->group_by_type( $blocks );

		// Apply styles to groups.
		$result = $this->bulk_styler->apply_to_groups(
			$prompts,
			$grouped,
			array( 'user_tier' => $user_tier )
		);

		return $this->success_response(
			array(
				'success' => true,
				'results' => $result,
				'groups'  => array_keys( $grouped ),
			)
		);
	}

	/**
	 * Generate combined stylesheet.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function generate_stylesheet( WP_REST_Request $request ) {
		$rules = $request->get_param( 'rules' );

		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return $this->error_response( 'invalid_rules', 'Rules object is required.', 400 );
		}

		$user_tier = $this->freemius->get_plan();

		$result = $this->bulk_styler->generate_stylesheet(
			$rules,
			array( 'user_tier' => $user_tier )
		);

		return $this->success_response( $result );
	}

	/**
	 * Validate blocks for bulk styling.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Response.
	 */
	public function validate_blocks( WP_REST_Request $request ): WP_REST_Response {
		$blocks = $request->get_param( 'blocks' );

		if ( ! is_array( $blocks ) ) {
			return $this->success_response(
				array(
					'valid'        => false,
					'issues'       => array( array( 'error' => 'Blocks must be an array.' ) ),
					'valid_blocks' => array(),
				)
			);
		}

		$result = $this->bulk_styler->validate_blocks( $blocks );

		return $this->success_response( $result );
	}
}
