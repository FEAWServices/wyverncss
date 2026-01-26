<?php
/**
 * Accessibility REST Controller
 *
 * REST API endpoints for accessibility checking.
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
use WyvernCSS\Accessibility\Accessibility_Checker;
use WyvernCSS\Freemius\Freemius_Integration;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Accessibility Controller Class
 *
 * Handles REST endpoints for:
 * - POST /accessibility/check - Check CSS for WCAG compliance
 * - POST /accessibility/contrast - Check color contrast
 * - POST /accessibility/suggestions - Get accessibility suggestions
 *
 * @since 1.0.0
 */
class Accessibility_Controller extends RESTController {

	/**
	 * Accessibility Checker service.
	 *
	 * @var Accessibility_Checker
	 */
	private Accessibility_Checker $checker;

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
		$this->checker  = new Accessibility_Checker();
		$this->freemius = Freemius_Integration::get_instance();
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Check CSS for WCAG compliance.
		register_rest_route(
			$this->namespace,
			'/accessibility/check',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'check_css' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'css'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_css_input' ),
					),
					'context' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);

		// Check color contrast.
		register_rest_route(
			$this->namespace,
			'/accessibility/contrast',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'check_contrast' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'foreground' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_hex_color',
					),
					'background' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_hex_color',
					),
					'level'      => array(
						'type'    => 'string',
						'default' => 'AA',
						'enum'    => array( 'A', 'AA', 'AAA' ),
					),
					'large_text' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		// Get accessibility suggestions (premium).
		register_rest_route(
			$this->namespace,
			'/accessibility/suggestions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_suggestions' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'css'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_css_input' ),
					),
					'context' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);

		// Full accessibility report (premium).
		register_rest_route(
			$this->namespace,
			'/accessibility/report',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_report' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'css'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_css_input' ),
					),
					'context' => array(
						'type'    => 'object',
						'default' => array(),
					),
					'level'   => array(
						'type'    => 'string',
						'default' => 'AA',
						'enum'    => array( 'A', 'AA', 'AAA' ),
					),
				),
			)
		);
	}

	/**
	 * Check premium permission.
	 *
	 * Suggestions and reports are premium features.
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
				esc_html__( 'Accessibility reports are a premium feature. Upgrade to access this functionality.', 'wyvern-ai-styling' ),
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
	 * Check CSS for WCAG compliance.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function check_css( WP_REST_Request $request ) {
		$css     = $request->get_param( 'css' );
		$context = $request->get_param( 'context' );

		if ( ! is_string( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS is required.', 400 );
		}

		if ( ! is_array( $context ) ) {
			$context = array();
		}

		$result = $this->checker->check( $css, $context );

		return $this->success_response(
			array(
				'success' => true,
				'result'  => $result,
			)
		);
	}

	/**
	 * Check color contrast.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function check_contrast( WP_REST_Request $request ) {
		$foreground = $request->get_param( 'foreground' );
		$background = $request->get_param( 'background' );
		$level      = $request->get_param( 'level' );
		$large_text = $request->get_param( 'large_text' );

		if ( ! is_string( $foreground ) || empty( $foreground ) ) {
			return $this->error_response( 'invalid_foreground', 'Foreground color is required.', 400 );
		}

		if ( ! is_string( $background ) || empty( $background ) ) {
			return $this->error_response( 'invalid_background', 'Background color is required.', 400 );
		}

		// Normalize level.
		$level = is_string( $level ) ? strtoupper( $level ) : 'AA';
		if ( ! in_array( $level, array( 'A', 'AA', 'AAA' ), true ) ) {
			$level = 'AA';
		}

		$result = $this->checker->check_contrast(
			$foreground,
			$background,
			$level,
			(bool) $large_text
		);

		return $this->success_response(
			array(
				'success' => true,
				'result'  => $result,
			)
		);
	}

	/**
	 * Get accessibility suggestions.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function get_suggestions( WP_REST_Request $request ) {
		$css     = $request->get_param( 'css' );
		$context = $request->get_param( 'context' );

		if ( ! is_string( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS is required.', 400 );
		}

		if ( ! is_array( $context ) ) {
			$context = array();
		}

		$suggestions = $this->checker->get_suggestions( $css, $context );

		return $this->success_response(
			array(
				'success'     => true,
				'suggestions' => $suggestions,
				'count'       => count( $suggestions ),
			)
		);
	}

	/**
	 * Generate full accessibility report.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function generate_report( WP_REST_Request $request ) {
		$css     = $request->get_param( 'css' );
		$context = $request->get_param( 'context' );
		$level   = $request->get_param( 'level' );

		if ( ! is_string( $css ) || empty( $css ) ) {
			return $this->error_response( 'invalid_css', 'CSS is required.', 400 );
		}

		if ( ! is_array( $context ) ) {
			$context = array();
		}

		// Normalize level.
		$level = is_string( $level ) ? strtoupper( $level ) : 'AA';
		if ( ! in_array( $level, array( 'A', 'AA', 'AAA' ), true ) ) {
			$level = 'AA';
		}

		// Run full check.
		$check_result = $this->checker->check( $css, $context );

		// Get suggestions.
		$suggestions = $this->checker->get_suggestions( $css, $context );

		// Build report.
		$report = array(
			'summary'         => array(
				'passes'        => $check_result['passes'],
				'wcag_level'    => $check_result['wcag_level'],
				'target_level'  => $level,
				'meets_target'  => $this->meets_target_level( $check_result['wcag_level'], $level ),
				'total_issues'  => count( $check_result['issues'] ),
				'error_count'   => $check_result['error_count'],
				'warning_count' => $check_result['warning_count'],
				'info_count'    => $check_result['info_count'],
			),
			'issues'          => $check_result['issues'],
			'suggestions'     => $suggestions,
			'recommendations' => $this->get_recommendations( $check_result, $suggestions, $level ),
			'generated_at'    => gmdate( 'c' ),
		);

		return $this->success_response(
			array(
				'success' => true,
				'report'  => $report,
			)
		);
	}

	/**
	 * Check if WCAG level meets target.
	 *
	 * @param string|null $achieved Achieved level.
	 * @param string      $target   Target level.
	 * @return bool True if meets target.
	 */
	private function meets_target_level( ?string $achieved, string $target ): bool {
		if ( null === $achieved ) {
			return false;
		}

		$levels = array(
			'A'   => 1,
			'AA'  => 2,
			'AAA' => 3,
		);

		$achieved_value = $levels[ $achieved ] ?? 0;
		$target_value   = $levels[ $target ] ?? 2;

		return $achieved_value >= $target_value;
	}

	/**
	 * Get prioritized recommendations.
	 *
	 * @param array<string, mixed>             $check_result Check result.
	 * @param array<int, array<string, mixed>> $suggestions  Suggestions.
	 * @param string                           $target_level Target WCAG level.
	 * @return array<int, array<string, mixed>> Recommendations.
	 */
	private function get_recommendations( array $check_result, array $suggestions, string $target_level ): array {
		$recommendations = array();

		// Prioritize errors first.
		foreach ( $check_result['issues'] as $issue ) {
			if ( 'error' !== $issue['severity'] ) {
				continue;
			}

			$recommendations[] = array(
				'priority'   => 'high',
				'type'       => $issue['type'],
				'wcag'       => $issue['wcag'] ?? null,
				'message'    => $issue['message'],
				'suggestion' => $issue['suggestion'] ?? null,
			);
		}

		// Add warnings for AA+ targets.
		if ( in_array( $target_level, array( 'AA', 'AAA' ), true ) ) {
			foreach ( $check_result['issues'] as $issue ) {
				if ( 'warning' !== $issue['severity'] ) {
					continue;
				}

				$recommendations[] = array(
					'priority'   => 'medium',
					'type'       => $issue['type'],
					'wcag'       => $issue['wcag'] ?? null,
					'message'    => $issue['message'],
					'suggestion' => $issue['suggestion'] ?? null,
				);
			}
		}

		// Add suggestions as low priority.
		foreach ( $suggestions as $suggestion ) {
			$recommendations[] = array(
				'priority'   => 'low',
				'type'       => $suggestion['type'],
				'wcag'       => $suggestion['wcag'] ?? null,
				'message'    => $suggestion['message'],
				'suggestion' => $suggestion['suggestion'] ?? null,
				'example'    => $suggestion['example'] ?? null,
			);
		}

		return $recommendations;
	}
}
