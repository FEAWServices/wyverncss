<?php
/**
 * Validation Controller
 *
 * REST endpoint for CSS validation and security checks.
 *
 * @package WyvernCSS
 * @subpackage API
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\API;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Validation Controller Class
 *
 * Handles POST /wyverncss/v1/validate endpoint.
 *
 * Features:
 * - CSS property whitelist validation
 * - Security checks (no JavaScript injection)
 * - Accessibility validation
 * - Performance warnings
 * - CSS syntax validation
 *
 * @since 1.0.0
 */
class ValidationController extends RESTController {

	/**
	 * Allowed CSS properties.
	 *
	 * WordPress VIP approved CSS properties for dynamic styles.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_PROPERTIES = array(
		'color',
		'background-color',
		'background',
		'border',
		'border-top',
		'border-right',
		'border-bottom',
		'border-left',
		'border-width',
		'border-style',
		'border-color',
		'border-radius',
		'box-shadow',
		'text-shadow',
		'padding',
		'padding-top',
		'padding-right',
		'padding-bottom',
		'padding-left',
		'margin',
		'margin-top',
		'margin-right',
		'margin-bottom',
		'margin-left',
		'font-size',
		'font-weight',
		'font-family',
		'font-style',
		'line-height',
		'letter-spacing',
		'text-align',
		'text-decoration',
		'text-transform',
		'width',
		'max-width',
		'min-width',
		'height',
		'max-height',
		'min-height',
		'display',
		'flex',
		'flex-direction',
		'flex-wrap',
		'justify-content',
		'align-items',
		'align-content',
		'gap',
		'opacity',
		'transform',
		'transition',
		'animation',
	);

	/**
	 * Dangerous CSS patterns that should be rejected.
	 *
	 * @var array<int, string>
	 */
	private const DANGEROUS_PATTERNS = array(
		'javascript:',
		'expression(',
		'@import',
		'url(',
		'behavior:',
		'-moz-binding',
	);

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 * @return void */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/validate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'validate_css' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_endpoint_args(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Validate CSS properties.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response or error.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function validate_css( WP_REST_Request $request ) {
		// Get CSS properties to validate.
		$css = $request->get_param( 'css' );

		if ( ! is_array( $css ) || empty( $css ) ) {
			return $this->error_response(
				'invalid_css',
				esc_html__( 'CSS must be provided as a non-empty object.', 'wyvern-ai-styling' ),
				400
			);
		}

		// Validation results.
		$is_valid      = true;
		$errors        = array();
		$warnings      = array();
		$sanitized_css = array();

		// Validate each property.
		foreach ( $css as $property => $value ) {
			$property = $this->sanitize_text( $property );
			$value    = $this->sanitize_text( $value );

			// Check if property is allowed.
			if ( ! $this->is_property_allowed( $property ) ) {
				$is_valid = false;
				$errors[] = sprintf(
					/* translators: %s: CSS property name */
					__( 'Property "%s" is not in the allowed whitelist.', 'wyvern-ai-styling' ),
					$property
				);
				continue;
			}

			// Check for dangerous patterns.
			if ( $this->contains_dangerous_pattern( $value ) ) {
				$is_valid = false;
				$errors[] = sprintf(
					/* translators: %s: CSS property name */
					__( 'Property "%s" contains potentially dangerous content.', 'wyvern-ai-styling' ),
					$property
				);
				continue;
			}

			// Check for accessibility issues.
			$accessibility_check = $this->check_accessibility( $property, $value );
			if ( ! empty( $accessibility_check ) ) {
				$warnings[] = $accessibility_check;
			}

			// Check for performance issues.
			$performance_check = $this->check_performance( $property, $value );
			if ( ! empty( $performance_check ) ) {
				$warnings[] = $performance_check;
			}

			// Property is valid, add to sanitized CSS.
			$sanitized_css[ $property ] = $value;
		}

		// Build response.
		$response_data = array(
			'is_valid'   => $is_valid,
			'css'        => $sanitized_css,
			'errors'     => $errors,
			'warnings'   => $warnings,
			'properties' => array(
				'total'   => count( $css ),
				'valid'   => count( $sanitized_css ),
				'invalid' => count( $errors ),
			),
		);

		if ( ! $is_valid ) {
			return $this->error_response(
				'validation_failed',
				esc_html__( 'CSS validation failed. See errors for details.', 'wyvern-ai-styling' ),
				400,
				$response_data
			);
		}

		return $this->success_response( $response_data );
	}

	/**
	 * Check if a CSS property is allowed.
	 *
	 * @since 1.0.0
	 * @param string $property CSS property name.
	 * @return bool True if allowed.
	 */
	private function is_property_allowed( string $property ): bool {
		return in_array( strtolower( $property ), self::ALLOWED_PROPERTIES, true );
	}

	/**
	 * Check if value contains dangerous patterns.
	 *
	 * @since 1.0.0
	 * @param string $value CSS property value.
	 * @return bool True if dangerous pattern found.
	 */
	private function contains_dangerous_pattern( string $value ): bool {
		$value_lower = strtolower( $value );

		foreach ( self::DANGEROUS_PATTERNS as $pattern ) {
			if ( str_contains( $value_lower, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check for accessibility issues.
	 *
	 * @since 1.0.0
	 * @param string $property CSS property name.
	 * @param string $value CSS property value.
	 * @return string Warning message or empty string.
	 */
	private function check_accessibility( string $property, string $value ): string {
		// Check for very small font sizes.
		if ( 'font-size' === $property && preg_match( '/(\d+(?:\.\d+)?)(px|pt|em|rem)/', $value, $matches ) ) {
			$size = (float) $matches[1];
			$unit = $matches[2];

			if ( 'px' === $unit && $size < 12 ) {
				return sprintf(
					/* translators: %s: font size value */
					__( 'Font size "%s" is very small and may cause accessibility issues. Minimum recommended is 12px.', 'wyvern-ai-styling' ),
					$value
				);
			}

			if ( 'rem' === $unit && $size < 0.75 ) {
				return sprintf(
					/* translators: %s: font size value */
					__( 'Font size "%s" is very small and may cause accessibility issues. Minimum recommended is 0.75rem.', 'wyvern-ai-styling' ),
					$value
				);
			}
		}

		// Check for low contrast colors (simplified check).
		if ( 'color' === $property && preg_match( '/#([a-f0-9]{6})/i', $value, $matches ) ) {
			$hex = $matches[1];
			// Convert hex to RGB.
			$r = hexdec( substr( $hex, 0, 2 ) );
			$g = hexdec( substr( $hex, 2, 2 ) );
			$b = hexdec( substr( $hex, 4, 2 ) );

			// Calculate relative luminance (simplified).
			$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;

			// Warn if color is very light or very dark (potential contrast issues).
			if ( $luminance < 0.1 || $luminance > 0.9 ) {
				return sprintf(
					/* translators: %s: color value */
					__( 'Color "%s" may have contrast issues. Ensure sufficient contrast with background.', 'wyvern-ai-styling' ),
					$value
				);
			}
		}

		return '';
	}

	/**
	 * Check for performance issues.
	 *
	 * @since 1.0.0
	 * @param string $property CSS property name.
	 * @param string $value CSS property value.
	 * @return string Warning message or empty string.
	 */
	private function check_performance( string $property, string $value ): string {
		// Warn about expensive properties.
		$expensive_properties = array( 'box-shadow', 'text-shadow', 'transform', 'filter' );

		if ( in_array( $property, $expensive_properties, true ) ) {
			// Check if value is complex.
			$complexity = substr_count( $value, ',' ) + substr_count( $value, ' ' );
			if ( $complexity > 5 ) {
				return sprintf(
					/* translators: %1$s: CSS property, %2$s: value */
					__( 'Property "%1$s" with complex value "%2$s" may impact performance. Consider simplifying.', 'wyvern-ai-styling' ),
					$property,
					$value
				);
			}
		}

		return '';
	}

	/**
	 * Get endpoint arguments.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Endpoint arguments.
	 */
	private function get_endpoint_args(): array {
		return array(
			'css' => array(
				'description' => __( 'CSS properties to validate as object.', 'wyvern-ai-styling' ),
				'type'        => 'object',
				'required'    => true,
			),
		);
	}

	/**
	 * Get schema for validation endpoint.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Schema definition.
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'validation',
			'type'       => 'object',
			'properties' => array(
				'is_valid'   => array(
					'description' => __( 'Whether CSS is valid.', 'wyvern-ai-styling' ),
					'type'        => 'boolean',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'css'        => array(
					'description' => __( 'Sanitized and validated CSS properties.', 'wyvern-ai-styling' ),
					'type'        => 'object',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'errors'     => array(
					'description' => __( 'Validation errors.', 'wyvern-ai-styling' ),
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
					),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'warnings'   => array(
					'description' => __( 'Validation warnings.', 'wyvern-ai-styling' ),
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
					),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'properties' => array(
					'description' => __( 'Property validation summary.', 'wyvern-ai-styling' ),
					'type'        => 'object',
					'context'     => array( 'view' ),
					'readonly'    => true,
					'properties'  => array(
						'total'   => array(
							'type' => 'integer',
						),
						'valid'   => array(
							'type' => 'integer',
						),
						'invalid' => array(
							'type' => 'integer',
						),
					),
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
