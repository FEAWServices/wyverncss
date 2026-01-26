<?php
/**
 * CSS Optimizer REST Controller
 *
 * REST API endpoints for CSS optimization.
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
use WyvernCSS\Optimizer\CSS_Optimizer;
use WyvernCSS\Freemius\Freemius_Integration;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * CSS Optimizer Controller Class
 *
 * Handles REST endpoints for:
 * - POST /css-optimize - Full optimization pipeline
 * - POST /css-optimize/minify - Minify CSS only
 * - POST /css-optimize/analyze - Analyze optimization opportunities
 * - POST /css-optimize/combine - Combine duplicate selectors
 *
 * @since 1.0.0
 */
class CSS_Optimizer_Controller extends RESTController {

	/**
	 * CSS Optimizer service.
	 *
	 * @var CSS_Optimizer
	 */
	private CSS_Optimizer $optimizer;

	/**
	 * Freemius Integration.
	 *
	 * @var Freemius_Integration
	 */
	private Freemius_Integration $freemius;

	/**
	 * Maximum CSS size for free tier (10KB).
	 */
	private const MAX_CSS_SIZE_FREE = 10240;

	/**
	 * Maximum CSS size for premium tier (500KB).
	 */
	private const MAX_CSS_SIZE_PREMIUM = 512000;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->optimizer = new CSS_Optimizer();
		$this->freemius  = Freemius_Integration::get_instance();
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Full optimization.
		register_rest_route(
			$this->namespace,
			'/css-optimize',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'optimize' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'css'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_css_input' ),
					),
					'options' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);

		// Minify only.
		register_rest_route(
			$this->namespace,
			'/css-optimize/minify',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'minify' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'css' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_css_input' ),
					),
				),
			)
		);

		// Analyze.
		register_rest_route(
			$this->namespace,
			'/css-optimize/analyze',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'analyze' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'css' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_css_input' ),
					),
				),
			)
		);

		// Combine selectors (premium).
		register_rest_route(
			$this->namespace,
			'/css-optimize/combine',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'combine' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'css' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_css_input' ),
					),
				),
			)
		);

		// Convert to shorthand (premium).
		register_rest_route(
			$this->namespace,
			'/css-optimize/shorthand',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'shorthand' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'css' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_css_input' ),
					),
				),
			)
		);
	}

	/**
	 * Check premium permission.
	 *
	 * Advanced optimization is a premium feature.
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
				esc_html__( 'Advanced CSS optimization is a premium feature. Upgrade to access this functionality.', 'wyverncss' ),
				array(
					'status'      => 403,
					'upgrade_url' => $this->freemius->get_upgrade_url(),
				)
			);
		}

		return true;
	}

	/**
	 * Sanitize CSS input.
	 *
	 * @param string $css CSS input.
	 * @return string Sanitized CSS.
	 */
	public function sanitize_css_input( string $css ): string {
		// Remove null bytes and control characters except newlines and tabs.
		$sanitized = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $css );
		if ( null === $sanitized ) {
			return '';
		}
		return $sanitized;
	}

	/**
	 * Get max CSS size based on user tier.
	 *
	 * @return int Maximum CSS size.
	 */
	private function get_max_css_size(): int {
		if ( $this->freemius->is_premium() || $this->freemius->is_trial() ) {
			return self::MAX_CSS_SIZE_PREMIUM;
		}
		return self::MAX_CSS_SIZE_FREE;
	}

	/**
	 * Validate CSS size.
	 *
	 * @param string $css CSS to validate.
	 * @return WP_Error|true True if valid, error otherwise.
	 */
	private function validate_css_size( string $css ) {
		$max_size = $this->get_max_css_size();
		$css_size = strlen( $css );

		if ( $css_size > $max_size ) {
			$is_premium = $this->freemius->is_premium() || $this->freemius->is_trial();

			return new WP_Error(
				'css_too_large',
				sprintf(
					/* translators: 1: CSS size, 2: max size */
					esc_html__( 'CSS is too large (%1$s). Maximum allowed is %2$s.', 'wyverncss' ),
					size_format( $css_size ),
					size_format( $max_size )
				),
				array(
					'status'      => 400,
					'upgrade_url' => ! $is_premium ? $this->freemius->get_upgrade_url() : null,
				)
			);
		}

		return true;
	}

	/**
	 * Full CSS optimization.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function optimize( WP_REST_Request $request ) {
		$css     = $request->get_param( 'css' );
		$options = $request->get_param( 'options' );

		if ( ! is_string( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS is required.', 400 );
		}

		$size_check = $this->validate_css_size( $css );
		if ( is_wp_error( $size_check ) ) {
			return $size_check;
		}

		// Validate options.
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$result = $this->optimizer->optimize( $css, $options );

		return $this->success_response(
			array(
				'success' => true,
				'result'  => $result,
			)
		);
	}

	/**
	 * Minify CSS only.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function minify( WP_REST_Request $request ) {
		$css = $request->get_param( 'css' );

		if ( ! is_string( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS is required.', 400 );
		}

		$size_check = $this->validate_css_size( $css );
		if ( is_wp_error( $size_check ) ) {
			return $size_check;
		}

		$result = $this->optimizer->minify( $css );

		return $this->success_response(
			array(
				'success' => true,
				'result'  => $result,
			)
		);
	}

	/**
	 * Analyze CSS for optimization opportunities.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function analyze( WP_REST_Request $request ) {
		$css = $request->get_param( 'css' );

		if ( ! is_string( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS is required.', 400 );
		}

		$size_check = $this->validate_css_size( $css );
		if ( is_wp_error( $size_check ) ) {
			return $size_check;
		}

		$result = $this->optimizer->analyze( $css );

		return $this->success_response(
			array(
				'success'  => true,
				'analysis' => $result,
			)
		);
	}

	/**
	 * Combine duplicate selectors.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function combine( WP_REST_Request $request ) {
		$css = $request->get_param( 'css' );

		if ( ! is_string( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS is required.', 400 );
		}

		$size_check = $this->validate_css_size( $css );
		if ( is_wp_error( $size_check ) ) {
			return $size_check;
		}

		$result = $this->optimizer->combine_selectors( $css );

		return $this->success_response(
			array(
				'success' => true,
				'result'  => $result,
			)
		);
	}

	/**
	 * Convert to shorthand properties.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function shorthand( WP_REST_Request $request ) {
		$css = $request->get_param( 'css' );

		if ( ! is_string( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS is required.', 400 );
		}

		$size_check = $this->validate_css_size( $css );
		if ( is_wp_error( $size_check ) ) {
			return $size_check;
		}

		$result = $this->optimizer->convert_to_shorthand( $css );

		return $this->success_response(
			array(
				'success' => true,
				'result'  => $result,
			)
		);
	}
}
