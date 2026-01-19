<?php
/**
 * CSS Validator
 *
 * Validates CSS properties and values using a whitelist approach to prevent XSS attacks.
 * Implements strict validation rules for CSS properties, values, units, and functions.
 *
 * @package WyvernCSS
 * @subpackage Security
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\Security;
use WP_Error;

/**
 * Class CSS_Validator
 *
 * Provides comprehensive CSS validation to prevent injection attacks while supporting
 * common styling use cases. Uses a whitelist approach for maximum security.
 */
class CSS_Validator {

	/**
	 * Allowed CSS properties (whitelist)
	 *
	 * @var array<string, bool>
	 */
	private const ALLOWED_PROPERTIES = array(
		// Typography.
		'font-family'           => true,
		'font-size'             => true,
		'font-weight'           => true,
		'font-style'            => true,
		'line-height'           => true,
		'text-align'            => true,
		'text-decoration'       => true,
		'text-transform'        => true,
		'letter-spacing'        => true,
		'word-spacing'          => true,

		// Colors.
		'color'                 => true,
		'background'            => true,
		'background-color'      => true,
		'border-color'          => true,
		'outline-color'         => true,

		// Box Model.
		'width'                 => true,
		'height'                => true,
		'min-width'             => true,
		'min-height'            => true,
		'max-width'             => true,
		'max-height'            => true,
		'margin'                => true,
		'margin-top'            => true,
		'margin-right'          => true,
		'margin-bottom'         => true,
		'margin-left'           => true,
		'padding'               => true,
		'padding-top'           => true,
		'padding-right'         => true,
		'padding-bottom'        => true,
		'padding-left'          => true,

		// Border.
		'border'                => true,
		'border-width'          => true,
		'border-style'          => true,
		'border-radius'         => true,
		'border-top'            => true,
		'border-right'          => true,
		'border-bottom'         => true,
		'border-left'           => true,

		// Display & Positioning.
		'display'               => true,
		'position'              => true,
		'top'                   => true,
		'right'                 => true,
		'bottom'                => true,
		'left'                  => true,
		'z-index'               => true,
		'float'                 => true,
		'clear'                 => true,
		'overflow'              => true,
		'overflow-x'            => true,
		'overflow-y'            => true,

		// Flexbox.
		'flex'                  => true,
		'flex-direction'        => true,
		'flex-wrap'             => true,
		'justify-content'       => true,
		'align-items'           => true,
		'align-content'         => true,
		'gap'                   => true,

		// Grid.
		'grid-template-columns' => true,
		'grid-template-rows'    => true,
		'grid-gap'              => true,
		'grid-column'           => true,
		'grid-row'              => true,

		// Visual Effects.
		'opacity'               => true,
		'box-shadow'            => true,
		'text-shadow'           => true,
		'transform'             => true,
		'transition'            => true,
		'animation'             => true,

		// Other.
		'cursor'                => true,
		'visibility'            => true,
		'list-style'            => true,
		'list-style-type'       => true,
		'vertical-align'        => true,
		'behavior'              => true, // Will be rejected by dangerous pattern check.
	);

	/**
	 * Allowed CSS units
	 *
	 * @var array<string, bool>
	 */
	private const ALLOWED_UNITS = array(
		'px'   => true,
		'em'   => true,
		'rem'  => true,
		'%'    => true,
		'vh'   => true,
		'vw'   => true,
		'vmin' => true,
		'vmax' => true,
		'ch'   => true,
		'ex'   => true,
		'pt'   => true,
		'cm'   => true,
		'mm'   => true,
		'in'   => true,
		'pc'   => true,
		'deg'  => true,
		'rad'  => true,
		'turn' => true,
		's'    => true,
		'ms'   => true,
		'fr'   => true, // CSS Grid fractional unit.
	);

	/**
	 * Allowed CSS functions
	 *
	 * @var array<string, bool>
	 */
	private const ALLOWED_FUNCTIONS = array(
		'rgb'             => true,
		'rgba'            => true,
		'hsl'             => true,
		'hsla'            => true,
		'calc'            => true,
		'var'             => true,
		'linear-gradient' => true,
		'radial-gradient' => true,
		'scale'           => true,
		'rotate'          => true,
		'translate'       => true,
		'translateX'      => true,
		'translateY'      => true,
		'skew'            => true,
	);

	/**
	 * Allowed keywords for specific properties
	 *
	 * @var array<string, array<string, bool>>
	 */
	private const ALLOWED_KEYWORDS = array(
		'display'      => array(
			'block'        => true,
			'inline'       => true,
			'inline-block' => true,
			'flex'         => true,
			'grid'         => true,
			'none'         => true,
		),
		'position'     => array(
			'static'   => true,
			'relative' => true,
			'absolute' => true,
			'fixed'    => true,
			'sticky'   => true,
		),
		'text-align'   => array(
			'left'    => true,
			'right'   => true,
			'center'  => true,
			'justify' => true,
		),
		'font-weight'  => array(
			'normal'  => true,
			'bold'    => true,
			'bolder'  => true,
			'lighter' => true,
		),
		'font-style'   => array(
			'normal'  => true,
			'italic'  => true,
			'oblique' => true,
		),
		'border-style' => array(
			'none'   => true,
			'solid'  => true,
			'dashed' => true,
			'dotted' => true,
			'double' => true,
		),
		'cursor'       => array(
			'auto'        => true,
			'pointer'     => true,
			'default'     => true,
			'text'        => true,
			'move'        => true,
			'not-allowed' => true,
		),
	);

	/**
	 * Dangerous patterns to reject
	 *
	 * @var array<string>
	 */
	private const DANGEROUS_PATTERNS = array(
		'javascript:',
		'expression(',
		'behavior:',
		'@import',
		'-moz-binding',
		'vbscript:',
		'data:text/html',
	);

	/**
	 * Maximum allowed value for numeric properties (prevents DoS via rendering)
	 *
	 * @var int
	 */
	private const MAX_NUMERIC_VALUE = 10000;

	/**
	 * Validation errors
	 *
	 * @var array<string>
	 */
	private array $errors = array();

	/**
	 * Validation warnings
	 *
	 * @var array<string>
	 */
	private array $warnings = array();

	/**
	 * Validate CSS properties array
	 *
	 * @param array<string, string> $css_properties CSS properties to validate.
	 * @param bool                  $strict Whether to use strict validation (reject warnings).
	 *
	 * @return array<string, mixed>|WP_Error Validated CSS or error.
	 */
	public function validate( array $css_properties, bool $strict = false ) {
		$this->errors   = array();
		$this->warnings = array();
		$validated      = array();

		// Check for dangerous patterns first.
		$this->check_dangerous_patterns( $css_properties );

		if ( ! empty( $this->errors ) ) {
			return new WP_Error(
				'css_validation_failed',
				__( 'CSS validation failed: dangerous patterns detected', 'wyvern-ai-styling' ),
				array(
					'errors'   => $this->errors,
					'warnings' => $this->warnings,
				)
			);
		}

		// Validate each property.
		foreach ( $css_properties as $property => $value ) {
			$sanitized_property = $this->sanitize_property_name( $property );
			$sanitized_value    = $this->sanitize_value( $value );

			if ( ! $this->is_property_allowed( $sanitized_property ) ) {
				$this->errors[] = sprintf(
					/* translators: %s: CSS property name */
					__( 'Property not allowed: %s', 'wyvern-ai-styling' ),
					esc_html( $property )
				);
				continue;
			}

			$validation_result = $this->validate_value( $sanitized_property, $sanitized_value );

			if ( is_wp_error( $validation_result ) ) {
				$this->errors[] = $validation_result->get_error_message();
				continue;
			}

			$validated[ $sanitized_property ] = $sanitized_value;
		}

		// Return error if validation failed.
		if ( ! empty( $this->errors ) || ( $strict && ! empty( $this->warnings ) ) ) {
			return new WP_Error(
				'css_validation_failed',
				__( 'CSS validation failed', 'wyvern-ai-styling' ),
				array(
					'errors'   => $this->errors,
					'warnings' => $this->warnings,
				)
			);
		}

		return array(
			'css'      => $validated,
			'warnings' => $this->warnings,
		);
	}

	/**
	 * Check for dangerous patterns in CSS
	 *
	 * @param array<string, string> $css_properties CSS properties.
	 *
	 * @return void */
	private function check_dangerous_patterns( array $css_properties ): void {
		// Check property names and values separately.
		foreach ( $css_properties as $property => $value ) {
			$property_lower = strtolower( $property );
			$value_lower    = strtolower( $value );

			// Check if property itself is dangerous (e.g., 'behavior').
			if ( 'behavior' === $property_lower ) {
				$this->errors[] = __( 'Dangerous pattern detected: behavior:', 'wyvern-ai-styling' );
			}

			// Check for dangerous patterns in values.
			foreach ( self::DANGEROUS_PATTERNS as $pattern ) {
				if ( str_contains( $value_lower, strtolower( $pattern ) ) ) {
					$this->errors[] = sprintf(
						/* translators: %s: dangerous pattern */
						__( 'Dangerous pattern detected: %s', 'wyvern-ai-styling' ),
						esc_html( $pattern )
					);
				}
			}
		}
	}

	/**
	 * Sanitize property name
	 *
	 * @param string $property Property name.
	 *
	 * @return string Sanitized property name.
	 */
	private function sanitize_property_name( string $property ): string {
		// Convert to lowercase and trim.
		$property = strtolower( trim( $property ) );

		// Remove any non-alphanumeric characters except hyphens.
		$property = preg_replace( '/[^a-z0-9\-]/', '', $property );

		return (string) $property;
	}

	/**
	 * Sanitize CSS value
	 *
	 * @param string $value CSS value.
	 *
	 * @return string Sanitized value.
	 */
	private function sanitize_value( string $value ): string {
		// Trim whitespace.
		$value = trim( $value );

		// Remove null bytes.
		$value = str_replace( "\0", '', $value );

		// Remove line breaks and excessive whitespace.
		$value = preg_replace( '/\s+/', ' ', $value );

		return (string) $value;
	}

	/**
	 * Check if property is allowed
	 *
	 * @param string $property Property name.
	 *
	 * @return bool Whether property is allowed.
	 */
	private function is_property_allowed( string $property ): bool {
		return isset( self::ALLOWED_PROPERTIES[ $property ] );
	}

	/**
	 * Validate CSS value
	 *
	 * @param string $property Property name.
	 * @param string $value Property value.
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_value( string $property, string $value ) {
		// Empty values are not allowed.
		if ( empty( $value ) ) {
			return new WP_Error(
				'empty_value',
				sprintf(
					/* translators: %s: CSS property name */
					__( 'Empty value for property: %s', 'wyvern-ai-styling' ),
					esc_html( $property )
				)
			);
		}

		// Check for URLs (only allow in specific properties like background-image).
		if ( preg_match( '/url\s*\(/i', $value ) ) {
			return new WP_Error(
				'url_not_allowed',
				__( 'URL values are not allowed for security reasons', 'wyvern-ai-styling' )
			);
		}

		// Validate color values.
		if ( $this->is_color_property( $property ) ) {
			return $this->validate_color( $value );
		}

		// Validate numeric values with units.
		if ( preg_match( '/^-?[\d.]+([a-z%]+)?$/i', $value, $matches ) ) {
			return $this->validate_numeric_value( $value, $matches[1] ?? '' );
		}

		// Validate keyword values.
		if ( isset( self::ALLOWED_KEYWORDS[ $property ] ) ) {
			return $this->validate_keyword( $property, $value );
		}

		// Validate CSS functions.
		if ( preg_match( '/^([a-z\-]+)\s*\(/i', $value, $matches ) ) {
			return $this->validate_function( $matches[1], $value );
		}

		// Check for global CSS keywords that are allowed for all properties.
		$global_keywords = array( 'none', 'auto', 'inherit', 'initial', 'unset' );
		if ( in_array( $value, $global_keywords, true ) ) {
			return true;
		}

		// Complex values (e.g., "10px 20px", "bold 16px Arial").
		if ( str_contains( $value, ' ' ) ) {
			return $this->validate_complex_value( $property, $value );
		}

		// If we can't validate it, add a warning but allow it.
		$this->warnings[] = sprintf(
			/* translators: 1: CSS property name, 2: CSS value */
			__( 'Could not fully validate %1$s: %2$s', 'wyvern-ai-styling' ),
			esc_html( $property ),
			esc_html( $value )
		);

		return true;
	}

	/**
	 * Check if property is a color property
	 *
	 * @param string $property Property name.
	 *
	 * @return bool Whether property is color-related.
	 */
	private function is_color_property( string $property ): bool {
		return str_contains( $property, 'color' );
	}

	/**
	 * Validate color value
	 *
	 * @param string $value Color value.
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_color( string $value ) {
		// Global keywords.
		if ( in_array( $value, array( 'inherit', 'initial', 'unset', 'none', 'auto' ), true ) ) {
			return true;
		}

		// Hex colors.
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ) {
			return true;
		}

		// RGB/RGBA.
		if ( preg_match( '/^rgba?\s*\(\s*\d+\s*,\s*\d+\s*,\s*\d+(\s*,\s*[\d.]+)?\s*\)$/i', $value ) ) {
			return true;
		}

		// HSL/HSLA.
		if ( preg_match( '/^hsla?\s*\(\s*\d+\s*,\s*\d+%\s*,\s*\d+%(\s*,\s*[\d.]+)?\s*\)$/i', $value ) ) {
			return true;
		}

		// Named colors (basic set).
		$named_colors = array(
			'transparent',
			'black',
			'white',
			'red',
			'green',
			'blue',
			'yellow',
			'cyan',
			'magenta',
			'gray',
			'grey',
			'orange',
			'purple',
			'pink',
			'brown',
			'navy',
			'teal',
			'olive',
			'maroon',
			'lime',
			'aqua',
			'silver',
		);

		if ( in_array( strtolower( $value ), $named_colors, true ) ) {
			return true;
		}

		return new WP_Error(
			'invalid_color',
			sprintf(
				/* translators: %s: color value */
				__( 'Invalid color value: %s', 'wyvern-ai-styling' ),
				esc_html( $value )
			)
		);
	}

	/**
	 * Validate numeric value
	 *
	 * @param string $value Numeric value with unit.
	 * @param string $unit Unit (px, em, etc).
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_numeric_value( string $value, string $unit ) {
		// Extract numeric part.
		$numeric = (float) $value;

		// Check for reasonable values (prevent DoS).
		if ( abs( $numeric ) > self::MAX_NUMERIC_VALUE ) {
			return new WP_Error(
				'value_too_large',
				sprintf(
					/* translators: %d: maximum allowed value */
					__( 'Numeric value exceeds maximum allowed (%d)', 'wyvern-ai-styling' ),
					self::MAX_NUMERIC_VALUE
				)
			);
		}

		// If there's a unit, validate it.
		if ( ! empty( $unit ) && ! isset( self::ALLOWED_UNITS[ strtolower( $unit ) ] ) ) {
			return new WP_Error(
				'invalid_unit',
				sprintf(
					/* translators: %s: CSS unit */
					__( 'Invalid CSS unit: %s', 'wyvern-ai-styling' ),
					esc_html( $unit )
				)
			);
		}

		return true;
	}

	/**
	 * Validate keyword value
	 *
	 * @param string $property Property name.
	 * @param string $value Keyword value.
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_keyword( string $property, string $value ) {
		$allowed = self::ALLOWED_KEYWORDS[ $property ] ?? array();

		if ( ! isset( $allowed[ strtolower( $value ) ] ) ) {
			return new WP_Error(
				'invalid_keyword',
				sprintf(
					/* translators: 1: CSS value, 2: CSS property name */
					__( 'Invalid keyword "%1$s" for property %2$s', 'wyvern-ai-styling' ),
					esc_html( $value ),
					esc_html( $property )
				)
			);
		}

		return true;
	}

	/**
	 * Validate CSS function
	 *
	 * @param string $function_name Function name.
	 * @param string $full_value Full function value.
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_function( string $function_name, string $full_value ) {
		if ( ! isset( self::ALLOWED_FUNCTIONS[ strtolower( $function_name ) ] ) ) {
			return new WP_Error(
				'invalid_function',
				sprintf(
					/* translators: %s: CSS function name */
					__( 'CSS function not allowed: %s', 'wyvern-ai-styling' ),
					esc_html( $function_name )
				)
			);
		}

		// Basic validation: ensure function is properly closed.
		if ( substr_count( $full_value, '(' ) !== substr_count( $full_value, ')' ) ) {
			return new WP_Error(
				'malformed_function',
				__( 'Malformed CSS function (mismatched parentheses)', 'wyvern-ai-styling' )
			);
		}

		return true;
	}

	/**
	 * Validate complex value (space-separated values)
	 *
	 * @param string $property Property name.
	 * @param string $value Complex value.
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_complex_value( string $property, string $value ) {
		$parts = explode( ' ', $value );

		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( empty( $part ) ) {
				continue;
			}

			// Recursively validate each part.
			$result = $this->validate_value( $property, $part );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Get validation errors
	 *
	 * @return array<string> Validation errors.
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Get validation warnings
	 *
	 * @return array<string> Validation warnings.
	 */
	public function get_warnings(): array {
		return $this->warnings;
	}

	/**
	 * Convert validated CSS array to inline style string
	 *
	 * @param array<string, string> $css_properties Validated CSS properties.
	 *
	 * @return string Inline style string.
	 */
	public function to_inline_style( array $css_properties ): string {
		$styles = array();

		foreach ( $css_properties as $property => $value ) {
			// First validate the CSS to strip any malicious content.
			$validated = $this->validate( array( $property => $value ), false );

			// If validation fails, skip this property.
			if ( is_wp_error( $validated ) ) {
				continue;
			}

			// Get the validated value.
			$safe_value = $validated['css'][ $property ] ?? $value;

			// Remove any quotes that could break out of the style attribute.
			$safe_value = str_replace( array( '"', "'", '<', '>' ), '', $safe_value );

			$styles[] = esc_attr( $property ) . ': ' . esc_attr( $safe_value );
		}

		return implode( '; ', $styles );
	}
}
