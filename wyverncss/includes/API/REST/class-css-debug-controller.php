<?php
/**
 * CSS Debug REST Controller
 *
 * REST API endpoints for CSS debugging.
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
use WyvernCSS\Debug\CSS_Debugger;
use WyvernCSS\Freemius\Freemius_Integration;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * CSS Debug Controller Class
 *
 * Handles REST endpoints for:
 * - POST /css-debug/analyze - Analyze CSS for issues
 * - POST /css-debug/fix - Auto-fix CSS issues
 * - POST /css-debug/suggestions - Get improvement suggestions
 *
 * @since 1.0.0
 */
class CSS_Debug_Controller extends RESTController {

	/**
	 * CSS Debugger service.
	 *
	 * @var CSS_Debugger
	 */
	private CSS_Debugger $debugger;

	/**
	 * Freemius Integration.
	 *
	 * @var Freemius_Integration
	 */
	private Freemius_Integration $freemius;

	/**
	 * Maximum CSS length for free tier.
	 */
	private const MAX_CSS_LENGTH_FREE = 5000;

	/**
	 * Maximum CSS length for premium tier.
	 */
	private const MAX_CSS_LENGTH_PREMIUM = 50000;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->debugger = new CSS_Debugger();
		$this->freemius = Freemius_Integration::get_instance();
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Analyze CSS.
		register_rest_route(
			$this->namespace,
			'/css-debug/analyze',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'analyze_css' ),
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

		// Auto-fix CSS.
		register_rest_route(
			$this->namespace,
			'/css-debug/fix',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'fix_css' ),
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

		// Get suggestions.
		register_rest_route(
			$this->namespace,
			'/css-debug/suggestions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_suggestions' ),
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

		// Combined analyze and fix (convenience endpoint).
		register_rest_route(
			$this->namespace,
			'/css-debug/full',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'full_debug' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'css'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_css_input' ),
					),
					'auto_fix' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);
	}

	/**
	 * Check premium permission.
	 *
	 * Auto-fix and suggestions are premium features.
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
				esc_html__( 'CSS auto-fix is a premium feature. Upgrade to access this functionality.', 'wyvern-ai-styling' ),
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
	 * Get max CSS length based on user tier.
	 *
	 * @return int Maximum CSS length.
	 */
	private function get_max_css_length(): int {
		if ( $this->freemius->is_premium() || $this->freemius->is_trial() ) {
			return self::MAX_CSS_LENGTH_PREMIUM;
		}
		return self::MAX_CSS_LENGTH_FREE;
	}

	/**
	 * Validate CSS length.
	 *
	 * @param string $css CSS to validate.
	 * @return WP_Error|true True if valid, error otherwise.
	 */
	private function validate_css_length( string $css ) {
		$max_length = $this->get_max_css_length();
		$css_length = strlen( $css );

		if ( $css_length > $max_length ) {
			return new WP_Error(
				'css_too_long',
				sprintf(
					/* translators: 1: CSS length, 2: max length */
					esc_html__( 'CSS is too long (%1$d characters). Maximum allowed is %2$d characters.', 'wyvern-ai-styling' ),
					$css_length,
					$max_length
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Analyze CSS for issues.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function analyze_css( WP_REST_Request $request ) {
		$css = $request->get_param( 'css' );

		if ( ! is_string( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS is required.', 400 );
		}

		$length_check = $this->validate_css_length( $css );
		if ( is_wp_error( $length_check ) ) {
			return $length_check;
		}

		$analysis = $this->debugger->analyze( $css );

		return $this->success_response(
			array(
				'success'  => true,
				'analysis' => $analysis,
			)
		);
	}

	/**
	 * Auto-fix CSS issues.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function fix_css( WP_REST_Request $request ) {
		$css = $request->get_param( 'css' );

		if ( ! is_string( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS is required.', 400 );
		}

		$length_check = $this->validate_css_length( $css );
		if ( is_wp_error( $length_check ) ) {
			return $length_check;
		}

		$result = $this->debugger->fix( $css );

		return $this->success_response(
			array(
				'success' => true,
				'result'  => $result,
			)
		);
	}

	/**
	 * Get improvement suggestions.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function get_suggestions( WP_REST_Request $request ) {
		$css = $request->get_param( 'css' );

		if ( ! is_string( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS is required.', 400 );
		}

		$length_check = $this->validate_css_length( $css );
		if ( is_wp_error( $length_check ) ) {
			return $length_check;
		}

		$suggestions = $this->debugger->get_suggestions( $css );

		return $this->success_response(
			array(
				'success'     => true,
				'suggestions' => $suggestions,
				'count'       => count( $suggestions ),
			)
		);
	}

	/**
	 * Full debug: analyze, fix, and suggest.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function full_debug( WP_REST_Request $request ) {
		$css      = $request->get_param( 'css' );
		$auto_fix = $request->get_param( 'auto_fix' );

		if ( ! is_string( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS is required.', 400 );
		}

		$length_check = $this->validate_css_length( $css );
		if ( is_wp_error( $length_check ) ) {
			return $length_check;
		}

		// Analyze original CSS.
		$analysis = $this->debugger->analyze( $css );

		// Fix CSS if requested.
		$fix_result = null;
		$fixed_css  = $css;
		if ( $auto_fix ) {
			$fix_result = $this->debugger->fix( $css );
			$fixed_css  = $fix_result['fixed'];
		}

		// Re-analyze fixed CSS.
		$fixed_analysis = null;
		if ( $auto_fix && $fix_result && $fix_result['changed'] ) {
			$fixed_analysis = $this->debugger->analyze( $fixed_css );
		}

		// Get suggestions for the fixed CSS.
		$suggestions = $this->debugger->get_suggestions( $fixed_css );

		return $this->success_response(
			array(
				'success'         => true,
				'original'        => array(
					'css'      => $css,
					'analysis' => $analysis,
				),
				'fixed'           => $auto_fix ? array(
					'css'      => $fixed_css,
					'changes'  => $fix_result ? $fix_result['changes'] : array(),
					'changed'  => $fix_result ? $fix_result['changed'] : false,
					'analysis' => $fixed_analysis,
				) : null,
				'suggestions'     => $suggestions,
				'issues_resolved' => $auto_fix && $fixed_analysis
					? $analysis['error_count'] - $fixed_analysis['error_count']
					: 0,
			)
		);
	}
}
