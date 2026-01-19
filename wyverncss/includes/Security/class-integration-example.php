<?php
/**
 * Security Validators Integration Example
 *
 * Demonstrates how to integrate CSS and Accessibility validators
 * into the CSS generation pipeline.
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
use WyvernCSS\Generator\CSSGenerator;
use WP_Error;

/**
 * Class Integration_Example
 *
 * Example implementation showing security validator integration.
 */
class Integration_Example {

	/**
	 * CSS Validator instance
	 *
	 * @var CSS_Validator
	 */
	private CSS_Validator $css_validator;

	/**
	 * Accessibility Validator instance
	 *
	 * @var Accessibility_Validator
	 */
	private Accessibility_Validator $accessibility_validator;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->css_validator           = new CSS_Validator();
		$this->accessibility_validator = new Accessibility_Validator();
	}

	/**
	 * Generate and validate CSS
	 *
	 * This method demonstrates the complete workflow:
	 * 1. Generate CSS using AI/patterns
	 * 2. Validate CSS for security
	 * 3. Validate for accessibility
	 * 4. Return validated CSS or errors
	 *
	 * @param string               $prompt User's natural language request.
	 * @param array<string, mixed> $element_context Element context.
	 * @param array<string, mixed> $options Generation options.
	 *
	 * @return array<string, mixed>|WP_Error Validated CSS or error.
	 */
	public function generate_secure_css( string $prompt, array $element_context, array $options = array() ) {
		// Step 1: Generate CSS using the CSS Generator.
		$generator = new CSSGenerator();
		$result    = $generator->generate( $prompt, $element_context, $options );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Extract the CSS from the generation result.
		$css_string = $result['css'] ?? '';
		$css_array  = $this->parse_css_string( $css_string );

		// Step 2: Validate CSS for security.
		$validation_result = $this->css_validator->validate( $css_array, false );

		if ( is_wp_error( $validation_result ) ) {
			return new WP_Error(
				'css_security_validation_failed',
				__( 'Generated CSS failed security validation', 'wyvern-ai-styling' ),
				array(
					'errors'       => $validation_result->get_error_data()['errors'],
					'warnings'     => $validation_result->get_error_data()['warnings'],
					'original_css' => $css_string,
				)
			);
		}

		$validated_css = $validation_result['css'];

		// Step 3: Validate for accessibility.
		$accessibility_context = array_merge(
			$element_context,
			array(
				'is_interactive' => $this->is_interactive_element( $element_context ),
			)
		);

		$a11y_result = $this->accessibility_validator->validate(
			$validated_css,
			$accessibility_context,
			$options['strict_accessibility'] ?? false
		);

		if ( is_wp_error( $a11y_result ) ) {
			return new WP_Error(
				'css_accessibility_validation_failed',
				__( 'Generated CSS failed accessibility validation', 'wyvern-ai-styling' ),
				array(
					'errors'          => $a11y_result->get_error_data()['errors'],
					'warnings'        => $a11y_result->get_error_data()['warnings'],
					'recommendations' => $a11y_result->get_error_data()['recommendations'],
					'css'             => $validated_css,
				)
			);
		}

		// Step 4: Return validated CSS with metadata.
		return array(
			'css'                  => $validated_css,
			'inline_style'         => $this->css_validator->to_inline_style( $validated_css ),
			'source'               => $result['source'],
			'security_warnings'    => $validation_result['warnings'],
			'a11y_warnings'        => $a11y_result['warnings'],
			'a11y_recommendations' => $a11y_result['recommendations'],
			'wcag_level'           => $a11y_result['wcag_level'],
		);
	}

	/**
	 * Validate existing CSS
	 *
	 * Validates CSS that's already been generated or provided by the user.
	 *
	 * @param array<string, string> $css_properties CSS properties to validate.
	 * @param array<string, mixed>  $context Element context.
	 *
	 * @return array<string, mixed>|WP_Error Validation result or error.
	 */
	public function validate_existing_css( array $css_properties, array $context = array() ) {
		// Validate security.
		$security_result = $this->css_validator->validate( $css_properties, false );

		if ( is_wp_error( $security_result ) ) {
			return $security_result;
		}

		// Validate accessibility.
		$a11y_result = $this->accessibility_validator->validate(
			$security_result['css'],
			$context,
			false
		);

		if ( is_wp_error( $a11y_result ) ) {
			return $a11y_result;
		}

		return array(
			'valid'                => true,
			'css'                  => $security_result['css'],
			'security_warnings'    => $security_result['warnings'],
			'a11y_warnings'        => $a11y_result['warnings'],
			'a11y_recommendations' => $a11y_result['recommendations'],
			'wcag_level'           => $a11y_result['wcag_level'],
		);
	}

	/**
	 * Quick contrast check
	 *
	 * Standalone method to quickly check color contrast.
	 *
	 * @param string $foreground Foreground color.
	 * @param string $background Background color.
	 * @param bool   $is_large_text Whether text is large.
	 *
	 * @return array<string, mixed> Contrast check result.
	 */
	public function check_color_contrast( string $foreground, string $background, bool $is_large_text = false ): array {
		return $this->accessibility_validator->check_contrast( $foreground, $background, $is_large_text );
	}

	/**
	 * Parse CSS string to array
	 *
	 * Converts CSS string (e.g., "color: red; font-size: 16px;") to array.
	 *
	 * @param string $css_string CSS string.
	 *
	 * @return array<string, string> CSS properties array.
	 */
	private function parse_css_string( string $css_string ): array {
		$css_array    = array();
		$declarations = explode( ';', $css_string );

		foreach ( $declarations as $declaration ) {
			$declaration = trim( $declaration );
			if ( empty( $declaration ) ) {
				continue;
			}

			$parts = explode( ':', $declaration, 2 );
			if ( count( $parts ) === 2 ) {
				$property               = trim( $parts[0] );
				$value                  = trim( $parts[1] );
				$css_array[ $property ] = $value;
			}
		}

		return $css_array;
	}

	/**
	 * Determine if element is interactive
	 *
	 * @param array<string, mixed> $context Element context.
	 *
	 * @return bool Whether element is interactive.
	 */
	private function is_interactive_element( array $context ): bool {
		$interactive_tags = array( 'a', 'button', 'input', 'select', 'textarea' );
		$tag              = $context['tag_name'] ?? '';

		return in_array( strtolower( $tag ), $interactive_tags, true );
	}
}

/**
 * Example Usage:
 *
 * ```php
 * // Initialize the integration.
 * $integration = new Integration_Example();
 *
 * // Generate and validate CSS.
 * $result = $integration->generate_secure_css(
 *     'Make the text blue and larger',
 *     [
 *         'tag_name' => 'p',
 *         'default_background_color' => '#ffffff',
 *     ],
 *     [
 *         'api_key' => get_option( 'wyverncss_api_key' ),
 *         'user_tier' => 'pro',
 *     ]
 * );
 *
 * if ( is_wp_error( $result ) ) {
 *     // Handle error.
 *     $errors = $result->get_error_data();
 *     error_log( 'CSS validation failed: ' . print_r( $errors, true ) );
 * } else {
 *     // Use the validated CSS.
 *     $inline_style = $result['inline_style'];
 *     $wcag_level = $result['wcag_level'];
 * }
 *
 * // Validate existing CSS.
 * $validation = $integration->validate_existing_css(
 *     [
 *         'color' => '#0066cc',
 *         'background-color' => '#ffffff',
 *         'font-size' => '16px',
 *     ],
 *     [
 *         'is_interactive' => true,
 *     ]
 * );
 *
 * // Quick contrast check.
 * $contrast = $integration->check_color_contrast( '#0066cc', '#ffffff' );
 * if ( $contrast['valid'] ) {
 *     echo "Contrast ratio: {$contrast['ratio']}:1 ({$contrast['level']} level)";
 * }
 * ```
 */
