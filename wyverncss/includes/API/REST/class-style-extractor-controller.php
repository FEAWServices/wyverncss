<?php
/**
 * Style Extractor REST Controller
 *
 * REST API endpoints for extracting styles from URLs.
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
use WyvernCSS\Extractor\Style_Extractor;
use WyvernCSS\Freemius\Freemius_Integration;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Style Extractor Controller Class
 *
 * Handles REST endpoints for:
 * - POST /style-extract - Extract styles from URL
 * - POST /style-extract/match - Generate matching CSS
 * - POST /style-extract/analyze - Analyze extracted styles
 *
 * @since 1.0.0
 */
class Style_Extractor_Controller extends RESTController {

	/**
	 * Style Extractor service.
	 *
	 * @var Style_Extractor
	 */
	private Style_Extractor $extractor;

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
		$this->extractor = new Style_Extractor();
		$this->freemius  = Freemius_Integration::get_instance();
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Extract styles from URL.
		register_rest_route(
			$this->namespace,
			'/style-extract',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'extract_styles' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'url' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		// Generate matching CSS.
		register_rest_route(
			$this->namespace,
			'/style-extract/match',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_matching' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'url'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
					'target'  => array(
						'type'    => 'string',
						'default' => '.element',
					),
					'options' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);

		// Analyze styles (return summary only).
		register_rest_route(
			$this->namespace,
			'/style-extract/analyze',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'analyze_styles' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'url' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		// Extract from HTML (for local content).
		register_rest_route(
			$this->namespace,
			'/style-extract/html',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'extract_from_html' ),
				'permission_callback' => array( $this, 'check_premium_permission' ),
				'args'                => array(
					'html' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Check premium permission.
	 *
	 * Style extraction is a premium feature.
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
				esc_html__( 'Style extraction is a premium feature. Upgrade to access this functionality.', 'wyvern-ai-styling' ),
				array(
					'status'      => 403,
					'upgrade_url' => $this->freemius->get_upgrade_url(),
				)
			);
		}

		return true;
	}

	/**
	 * Extract styles from URL.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function extract_styles( WP_REST_Request $request ) {
		$url = $request->get_param( 'url' );

		if ( ! is_string( $url ) || empty( $url ) ) {
			return $this->error_response( 'invalid_url', 'URL is required.', 400 );
		}

		$result = $this->extractor->extract_from_url( $url );

		if ( is_wp_error( $result ) ) {
			return $this->error_response(
				(string) $result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		return $this->success_response(
			array(
				'success' => true,
				'url'     => $url,
				'styles'  => $result,
			)
		);
	}

	/**
	 * Generate matching CSS from URL.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function generate_matching( WP_REST_Request $request ) {
		$url     = $request->get_param( 'url' );
		$target  = $request->get_param( 'target' );
		$options = $request->get_param( 'options' );

		if ( ! is_string( $url ) || empty( $url ) ) {
			return $this->error_response( 'invalid_url', 'URL is required.', 400 );
		}

		// Validate target selector.
		$target = is_string( $target ) ? $target : '.element';
		if ( empty( $target ) ) {
			$target = '.element';
		}

		// Validate options.
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		// Extract styles first.
		$styles = $this->extractor->extract_from_url( $url );

		if ( is_wp_error( $styles ) ) {
			return $this->error_response(
				(string) $styles->get_error_code(),
				$styles->get_error_message(),
				400
			);
		}

		// Generate matching CSS.
		$result = $this->extractor->generate_matching_css( $styles, $target, $options );

		return $this->success_response(
			array(
				'success' => true,
				'url'     => $url,
				'result'  => $result,
			)
		);
	}

	/**
	 * Analyze styles from URL.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function analyze_styles( WP_REST_Request $request ) {
		$url = $request->get_param( 'url' );

		if ( ! is_string( $url ) || empty( $url ) ) {
			return $this->error_response( 'invalid_url', 'URL is required.', 400 );
		}

		// Extract styles.
		$styles = $this->extractor->extract_from_url( $url );

		if ( is_wp_error( $styles ) ) {
			return $this->error_response(
				(string) $styles->get_error_code(),
				$styles->get_error_message(),
				400
			);
		}

		// Return summary only for quick analysis.
		return $this->success_response(
			array(
				'success' => true,
				'url'     => $url,
				'summary' => $styles['summary'] ?? array(),
				'colors'  => array(
					'primary'   => $styles['colors']['primary'] ?? null,
					'secondary' => $styles['colors']['secondary'] ?? null,
					'accent'    => $styles['colors']['accent'] ?? null,
					'count'     => $styles['colors']['total_count'] ?? 0,
				),
				'fonts'   => array(
					'primary'   => $styles['fonts']['primary'] ?? null,
					'base_size' => $styles['font_sizes']['base'] ?? null,
				),
				'design'  => array(
					'border_radius'  => $styles['border_radii']['common'] ?? null,
					'has_shadows'    => ! empty( $styles['shadows']['common'] ),
					'css_vars_count' => $styles['css_variables']['count'] ?? 0,
				),
			)
		);
	}

	/**
	 * Extract styles from HTML content.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function extract_from_html( WP_REST_Request $request ) {
		$html = $request->get_param( 'html' );

		if ( ! is_string( $html ) || empty( $html ) ) {
			return $this->error_response( 'invalid_html', 'HTML content is required.', 400 );
		}

		// Limit HTML size.
		$max_size = 1024 * 1024; // 1MB.
		if ( strlen( $html ) > $max_size ) {
			return $this->error_response(
				'html_too_large',
				'HTML content is too large. Maximum size is 1MB.',
				400
			);
		}

		$result = $this->extractor->extract_from_html( $html );

		return $this->success_response(
			array(
				'success' => true,
				'styles'  => $result,
			)
		);
	}
}
