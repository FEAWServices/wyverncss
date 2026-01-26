<?php
/**
 * Response Parser
 *
 * Parses and validates AI model responses for CSS generation.
 *
 * @package WyvernCSS
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WP_Error;

/**
 * Class ResponseParser
 *
 * Extracts and validates CSS properties from AI model responses.
 */
class ResponseParser {

	/**
	 * Valid CSS properties (subset for validation)
	 *
	 * @var array<int, string>
	 */
	private const VALID_CSS_PROPERTIES = array(
		'color',
		'background',
		'background-color',
		'background-image',
		'background-size',
		'background-position',
		'background-repeat',
		'background-attachment',
		'border',
		'border-radius',
		'border-width',
		'border-style',
		'border-color',
		'border-top',
		'border-right',
		'border-bottom',
		'border-left',
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
		'width',
		'height',
		'min-width',
		'max-width',
		'min-height',
		'max-height',
		'display',
		'position',
		'top',
		'right',
		'bottom',
		'left',
		'flex',
		'flex-direction',
		'flex-wrap',
		'justify-content',
		'align-items',
		'align-content',
		'grid',
		'grid-template-columns',
		'grid-template-rows',
		'grid-gap',
		'gap',
		'font-size',
		'font-weight',
		'font-family',
		'line-height',
		'letter-spacing',
		'text-align',
		'text-decoration',
		'text-transform',
		'opacity',
		'visibility',
		'overflow',
		'z-index',
		'box-shadow',
		'text-shadow',
		'transform',
		'transition',
		'animation',
		'content',
	);

	/**
	 * Parse API response and extract CSS
	 *
	 * @param array<string, mixed> $response API response data.
	 *
	 * @return array<string, mixed>|WP_Error Parsed CSS properties or error.
	 */
	public function parse( array $response ) {
		// Validate response structure.
		if ( empty( $response['choices'] ) || ! is_array( $response['choices'] ) ) {
			return new WP_Error(
				'invalid_response_structure',
				__( 'Invalid API response structure', 'wyverncss' ),
				array( 'response' => $response )
			);
		}

		// Get first choice.
		$choice = $response['choices'][0] ?? null;
		if ( empty( $choice ) || empty( $choice['message']['content'] ) ) {
			return new WP_Error(
				'empty_response',
				__( 'Empty response from API', 'wyverncss' ),
				array( 'choice' => $choice )
			);
		}

		$content = $choice['message']['content'];

		// Extract JSON from response.
		$css_json = $this->extract_json( $content );

		if ( is_wp_error( $css_json ) ) {
			return $css_json;
		}

		// Validate CSS properties.
		$validated = $this->validate_css_properties( $css_json );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		return $validated;
	}

	/**
	 * Extract JSON from response content
	 *
	 * @param string $content Response content.
	 *
	 * @return array<string, mixed>|WP_Error Decoded JSON or error.
	 */
	private function extract_json( string $content ) {
		// Try direct JSON decode first.
		$decoded = json_decode( $content, true );

		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			return $decoded;
		}

		// Try to extract JSON from markdown code blocks.
		if ( preg_match( '/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches ) ) {
			$decoded = json_decode( $matches[1], true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		// Try to extract any JSON object.
		if ( preg_match( '/(\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\})/s', $content, $matches ) ) {
			$decoded = json_decode( $matches[1], true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return new WP_Error(
			'json_parse_error',
			__( 'Failed to parse JSON from response', 'wyverncss' ),
			array(
				'content'    => $content,
				'json_error' => json_last_error_msg(),
			)
		);
	}

	/**
	 * Validate CSS properties
	 *
	 * @param array<string, mixed> $css CSS properties.
	 *
	 * @return array<string, mixed>|WP_Error Validated CSS or error.
	 */
	private function validate_css_properties( array $css ) {
		if ( empty( $css ) ) {
			return new WP_Error(
				'empty_css',
				__( 'No CSS properties found in response', 'wyverncss' )
			);
		}

		$validated = array();
		$errors    = array();

		foreach ( $css as $property => $value ) {
			// Handle media queries (nested objects).
			if ( strpos( $property, '@media' ) === 0 ) {
				if ( is_array( $value ) ) {
					$nested_validated = $this->validate_css_properties( $value );
					if ( ! is_wp_error( $nested_validated ) ) {
						$validated[ $property ] = $nested_validated;
					} else {
						$errors[] = sprintf(
							/* translators: %s: Media query */
							__( 'Invalid nested CSS in %s', 'wyverncss' ),
							$property
						);
					}
				}
				continue;
			}

			// Validate property name.
			if ( ! $this->is_valid_property( $property ) ) {
				$errors[] = sprintf(
					/* translators: %s: Property name */
					__( 'Invalid CSS property: %s', 'wyverncss' ),
					$property
				);
				continue;
			}

			// Validate property value.
			if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
				$errors[] = sprintf(
					/* translators: %s: Property name */
					__( 'Invalid value type for property: %s', 'wyverncss' ),
					$property
				);
				continue;
			}

			// Sanitize value.
			$sanitized = $this->sanitize_css_value( (string) $value );
			if ( ! empty( $sanitized ) ) {
				$validated[ $property ] = $sanitized;
			}
		}

		// If too many errors, return error.
		if ( count( $errors ) > count( $css ) / 2 ) {
			return new WP_Error(
				'validation_failed',
				__( 'Too many invalid CSS properties', 'wyverncss' ),
				array( 'errors' => $errors )
			);
		}

		return $validated;
	}

	/**
	 * Check if property is valid
	 *
	 * @param string $property CSS property name.
	 *
	 * @return bool True if valid.
	 */
	private function is_valid_property( string $property ): bool {
		// Allow custom properties (CSS variables).
		if ( strpos( $property, '--' ) === 0 ) {
			return true;
		}

		// Check against known properties.
		return in_array( $property, self::VALID_CSS_PROPERTIES, true );
	}

	/**
	 * Sanitize CSS value
	 *
	 * @param string $value CSS value.
	 *
	 * @return string Sanitized value.
	 */
	private function sanitize_css_value( string $value ): string {
		// Remove any potential XSS vectors.
		$value = wp_strip_all_tags( $value );

		// Remove JavaScript protocols and function calls.
		$value = preg_replace( '/javascript:/i', '', $value );
		if ( null === $value ) {
			return '';
		}

		$value = preg_replace( '/on\w+\s*=/i', '', $value );
		if ( null === $value ) {
			return '';
		}

		// Remove common XSS patterns (alert, eval, etc).
		$value = preg_replace( '/\b(alert|eval|prompt|confirm|expression)\s*\(/i', '', $value );
		if ( null === $value ) {
			return '';
		}

		// Trim whitespace.
		return trim( $value );
	}

	/**
	 * Filter CSS to only include properties explicitly requested
	 *
	 * This prevents AI from adding unrequested properties like background-color.
	 *
	 * @param array<string, mixed> $css CSS properties from AI.
	 * @param string               $prompt User's original request.
	 *
	 * @return array<string, mixed> Filtered CSS properties.
	 */
	public function filter_unrequested_properties( array $css, string $prompt ): array {
		$prompt_lower = strtolower( $prompt );

		// Map of keywords to allowed CSS properties.
		$keyword_to_properties = array(
			// Color keywords.
			'color'      => array( 'color' ),
			'text color' => array( 'color' ),
			'navy'       => array( 'color' ),
			'blue'       => array( 'color', 'border', 'border-color', 'border-top', 'border-right', 'border-bottom', 'border-left' ),
			'red'        => array( 'color', 'border', 'border-color', 'border-top', 'border-right', 'border-bottom', 'border-left' ),
			// Font keywords.
			'font'       => array( 'font-size', 'font-weight', 'font-family', 'line-height' ),
			'typeface'   => array( 'font-family' ),
			'times'      => array( 'font-family' ),
			'arial'      => array( 'font-family' ),
			'helvetica'  => array( 'font-family' ),
			'georgia'    => array( 'font-family' ),
			'verdana'    => array( 'font-family' ),
			'roboto'     => array( 'font-family' ),
			'open sans'  => array( 'font-family' ),
			'serif'      => array( 'font-family' ),
			'sans-serif' => array( 'font-family' ),
			'monospace'  => array( 'font-family' ),
			'cursive'    => array( 'font-family' ),
			'size'       => array( 'font-size', 'width', 'height' ),
			'weight'     => array( 'font-weight' ),
			'bold'       => array( 'font-weight' ),
			'semi-bold'  => array( 'font-weight' ),
			'letter'     => array( 'letter-spacing' ),
			'spacing'    => array( 'letter-spacing', 'gap' ),
			// Border keywords.
			'border'     => array( 'border', 'border-radius', 'border-width', 'border-style', 'border-color', 'border-top', 'border-right', 'border-bottom', 'border-left' ),
			'accent'     => array( 'border', 'border-color', 'border-top', 'border-right', 'border-bottom', 'border-left' ),
			'rounded'    => array( 'border-radius' ),
			'radius'     => array( 'border-radius' ),
			// Background keywords - only allow if explicitly requested.
			'background' => array( 'background', 'background-color', 'background-image' ),
			// Spacing keywords.
			'padding'    => array( 'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left' ),
			'margin'     => array( 'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left' ),
			// Layout keywords.
			'shadow'     => array( 'box-shadow', 'text-shadow' ),
			'glow'       => array( 'text-shadow', 'box-shadow' ),
			'opacity'    => array( 'opacity' ),
			'transform'  => array( 'transform' ),
			'transition' => array( 'transition' ),
			'animation'  => array( 'animation' ),
			'hover'      => array( 'transition', 'transform' ),
			'gradient'   => array( 'background', 'background-image' ),
		);

		// Detect which properties the user is asking for.
		$allowed_properties = array();
		foreach ( $keyword_to_properties as $keyword => $properties ) {
			if ( strpos( $prompt_lower, $keyword ) !== false ) {
				$allowed_properties = array_merge( $allowed_properties, $properties );
			}
		}

		// If no keywords detected, return all CSS (fallback).
		if ( empty( $allowed_properties ) ) {
			return $css;
		}

		$allowed_properties = array_unique( $allowed_properties );

		// Filter CSS to only include allowed properties.
		$filtered = array();
		foreach ( $css as $property => $value ) {
			// Handle media queries.
			if ( strpos( $property, '@media' ) === 0 && is_array( $value ) ) {
				$nested_filtered = $this->filter_unrequested_properties( $value, $prompt );
				if ( ! empty( $nested_filtered ) ) {
					$filtered[ $property ] = $nested_filtered;
				}
				continue;
			}

			// Check if property is allowed.
			if ( in_array( $property, $allowed_properties, true ) ) {
				$filtered[ $property ] = $value;
			}
		}

		return $filtered;
	}

	/**
	 * Get usage data from response
	 *
	 * @param array<string, mixed> $response API response.
	 *
	 * @return array<string, int> Usage data.
	 */
	public function get_usage_data( array $response ): array {
		$usage = $response['usage'] ?? array();

		return array(
			'prompt_tokens'     => $usage['prompt_tokens'] ?? 0,
			'completion_tokens' => $usage['completion_tokens'] ?? 0,
			'total_tokens'      => $usage['total_tokens'] ?? 0,
		);
	}

	/**
	 * Get model from response
	 *
	 * @param array<string, mixed> $response API response.
	 *
	 * @return string Model identifier.
	 */
	public function get_model( array $response ): string {
		return $response['model'] ?? 'unknown';
	}

	/**
	 * Convert CSS array to string
	 *
	 * @param array<string, mixed> $css CSS properties.
	 *
	 * @return string CSS string.
	 */
	public function css_to_string( array $css ): string {
		$lines = array();

		foreach ( $css as $property => $value ) {
			if ( strpos( $property, '@media' ) === 0 && is_array( $value ) ) {
				// Handle media queries.
				$nested  = $this->css_to_string( $value );
				$lines[] = sprintf( "%s {\n%s\n}", $property, $nested );
			} else {
				$lines[] = sprintf( '%s: %s;', $property, $value );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Validate response has required fields
	 *
	 * @param array<string, mixed> $response API response.
	 *
	 * @return bool True if valid.
	 */
	public function validate_response_structure( array $response ): bool {
		return ! empty( $response['choices'] ) &&
			is_array( $response['choices'] ) &&
			! empty( $response['choices'][0]['message']['content'] );
	}
}
