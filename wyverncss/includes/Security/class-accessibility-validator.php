<?php
/**
 * Accessibility Validator
 *
 * Validates CSS styles for WCAG 2.1 AA compliance.
 * Checks color contrast ratios, font sizes, and keyboard navigation compatibility.
 *
 * @package WyvernCSS
 * @subpackage Security
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WP_Error;

/**
 * Class Accessibility_Validator
 *
 * Validates generated CSS against WCAG 2.1 Level AA accessibility standards.
 * Focuses on:
 * - Color contrast ratios (4.5:1 for normal text, 3:1 for large text)
 * - Minimum font sizes for readability
 * - Keyboard navigation compatibility
 * - Focus indicators
 */
class Accessibility_Validator {

	/**
	 * WCAG AA minimum contrast ratio for normal text
	 *
	 * @var float
	 */
	private const WCAG_AA_NORMAL_CONTRAST = 4.5;

	/**
	 * WCAG AA minimum contrast ratio for large text (18pt+ or 14pt+ bold)
	 *
	 * @var float
	 */
	private const WCAG_AA_LARGE_CONTRAST = 3.0;

	/**
	 * Minimum font size in pixels for readability
	 *
	 * @var int
	 */
	private const MIN_FONT_SIZE_PX = 12;

	/**
	 * Large text threshold in pixels (18pt = 24px)
	 *
	 * @var int
	 */
	private const LARGE_TEXT_THRESHOLD_PX = 24;

	/**
	 * Bold large text threshold in pixels (14pt = 18.66px)
	 *
	 * @var int
	 */
	private const LARGE_BOLD_TEXT_THRESHOLD_PX = 19;

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
	 * Accessibility recommendations
	 *
	 * @var array<string>
	 */
	private array $recommendations = array();

	/**
	 * Validate CSS for accessibility compliance
	 *
	 * @param array<string, string> $css_properties CSS properties to validate.
	 * @param array<string, mixed>  $context Context information (element type, existing styles, etc).
	 * @param bool                  $strict Whether to treat warnings as errors.
	 *
	 * @return array<string, mixed>|WP_Error Validation result or error.
	 */
	public function validate( array $css_properties, array $context = array(), bool $strict = false ) {
		$this->errors          = array();
		$this->warnings        = array();
		$this->recommendations = array();

		// Extract colors from CSS.
		$foreground_color = $this->get_color_value( $css_properties, 'color', $context );
		$background_color = $this->get_color_value( $css_properties, 'background-color', $context );

		// Validate color contrast if both colors are present.
		if ( null !== $foreground_color && null !== $background_color ) {
			$this->validate_color_contrast(
				$foreground_color,
				$background_color,
				$css_properties,
				$context
			);
		}

		// Validate font size.
		if ( isset( $css_properties['font-size'] ) ) {
			$this->validate_font_size( $css_properties['font-size'] );
		}

		// Validate keyboard navigation compatibility.
		$this->validate_keyboard_navigation( $css_properties, $context );

		// Validate focus indicators.
		$this->validate_focus_indicators( $css_properties, $context );

		// Check for text decoration and readability.
		$this->validate_text_decoration( $css_properties );

		// Check opacity for text.
		$this->validate_text_opacity( $css_properties );

		// Return error if validation failed.
		if ( ! empty( $this->errors ) || ( $strict && ! empty( $this->warnings ) ) ) {
			return new WP_Error(
				'accessibility_validation_failed',
				__( 'Accessibility validation failed', 'wyverncss' ),
				array(
					'errors'          => $this->errors,
					'warnings'        => $this->warnings,
					'recommendations' => $this->recommendations,
				)
			);
		}

		return array(
			'valid'           => true,
			'warnings'        => $this->warnings,
			'recommendations' => $this->recommendations,
			'wcag_level'      => 'AA',
		);
	}

	/**
	 * Validate color contrast ratio
	 *
	 * @param string                $foreground Foreground color.
	 * @param string                $background Background color.
	 * @param array<string, string> $css_properties CSS properties.
	 * @param array<string, mixed>  $context Context information.
	 *
	 * @return void */
	private function validate_color_contrast( string $foreground, string $background, array $css_properties, array $context ): void {
		$contrast_ratio = $this->calculate_contrast_ratio( $foreground, $background );

		if ( false === $contrast_ratio ) {
			$this->warnings[] = __( 'Could not calculate color contrast ratio', 'wyverncss' );
			return;
		}

		// Determine if this is large text.
		$is_large_text = $this->is_large_text( $css_properties, $context );

		$required_ratio = $is_large_text ? self::WCAG_AA_LARGE_CONTRAST : self::WCAG_AA_NORMAL_CONTRAST;
		$aaa_ratio      = $is_large_text ? 4.5 : 7.0; // AAA: 7:1 for normal text, 4.5:1 for large text.

		if ( $contrast_ratio < $required_ratio ) {
			$this->errors[] = sprintf(
				/* translators: 1: calculated ratio, 2: required ratio, 3: text size */
				__( 'Insufficient color contrast: %1$.2f:1 (required: %2$.1f:1 for %3$s text)', 'wyverncss' ),
				$contrast_ratio,
				$required_ratio,
				$is_large_text ? 'large' : 'normal'
			);
		} elseif ( $contrast_ratio < $aaa_ratio ) {
			// Recommend AAA level for better accessibility.
			$this->recommendations[] = sprintf(
				/* translators: 1: contrast ratio, 2: AAA level */
				__( 'Consider increasing contrast ratio to %2$.1f:1 for AAA compliance (current: %1$.2f:1)', 'wyverncss' ),
				$contrast_ratio,
				$aaa_ratio
			);
		}
	}

	/**
	 * Validate font size
	 *
	 * @param string $font_size Font size value.
	 *
	 * @return void */
	private function validate_font_size( string $font_size ): void {
		$size_px = $this->convert_to_pixels( $font_size );

		if ( false === $size_px ) {
			$this->warnings[] = sprintf(
				/* translators: %s: font size value */
				__( 'Could not validate font size: %s', 'wyverncss' ),
				esc_html( $font_size )
			);
			return;
		}

		if ( $size_px < self::MIN_FONT_SIZE_PX ) {
			$this->errors[] = sprintf(
				/* translators: 1: provided font size, 2: minimum required size */
				__( 'Font size too small: %1$dpx (minimum: %2$dpx)', 'wyverncss' ),
				(int) $size_px,
				self::MIN_FONT_SIZE_PX
			);
		} elseif ( $size_px < 14 ) {
			$this->warnings[] = sprintf(
				/* translators: %s: font size in pixels */
				__( 'Font size is small (%spx). Consider using at least 14px for better readability.', 'wyverncss' ),
				number_format( $size_px, 0 )
			);
		}
	}

	/**
	 * Validate keyboard navigation compatibility
	 *
	 * @param array<string, string> $css_properties CSS properties.
	 * @param array<string, mixed>  $context Context information.
	 *
	 * @return void */
	private function validate_keyboard_navigation( array $css_properties, array $context ): void {
		$is_interactive = $context['is_interactive'] ?? false;

		// Check for display: none or visibility: hidden on interactive elements.
		if ( $is_interactive ) {
			if ( isset( $css_properties['display'] ) && 'none' === $css_properties['display'] ) {
				$this->errors[] = __( 'Interactive element hidden with display:none breaks keyboard navigation', 'wyverncss' );
			}

			if ( isset( $css_properties['visibility'] ) && 'hidden' === $css_properties['visibility'] ) {
				$this->errors[] = __( 'Interactive element hidden with visibility:hidden breaks keyboard navigation', 'wyverncss' );
			}
		}

		// Check for pointer-events: none on interactive elements.
		if ( $is_interactive && isset( $css_properties['pointer-events'] ) && 'none' === $css_properties['pointer-events'] ) {
			$this->warnings[] = __( 'pointer-events:none may affect keyboard navigation on interactive elements', 'wyverncss' );
		}
	}

	/**
	 * Validate focus indicators
	 *
	 * @param array<string, string> $css_properties CSS properties.
	 * @param array<string, mixed>  $context Context information.
	 *
	 * @return void */
	private function validate_focus_indicators( array $css_properties, array $context ): void {
		$is_interactive = $context['is_interactive'] ?? false;

		if ( ! $is_interactive ) {
			return;
		}

		// Check if outline is disabled.
		if ( isset( $css_properties['outline'] ) && 'none' === $css_properties['outline'] ) {
			// Only flag as error if there's no alternative focus indicator.
			if ( ! isset( $css_properties['border'] ) && ! isset( $css_properties['box-shadow'] ) ) {
				$this->errors[] = __( 'Outline removed without alternative focus indicator', 'wyverncss' );
			} else {
				$this->recommendations[] = __( 'Consider adding a visible focus indicator to replace outline', 'wyverncss' );
			}
		}
	}

	/**
	 * Validate text decoration
	 *
	 * @param array<string, string> $css_properties CSS properties.
	 *
	 * @return void */
	private function validate_text_decoration( array $css_properties ): void {
		// Check if text-decoration is used to convey information.
		if ( isset( $css_properties['text-decoration'] ) && 'none' === $css_properties['text-decoration'] ) {
			// This is a recommendation rather than an error.
			$this->recommendations[] = __( 'Ensure links are distinguishable by more than just color', 'wyverncss' );
		}
	}

	/**
	 * Validate text opacity
	 *
	 * @param array<string, string> $css_properties CSS properties.
	 *
	 * @return void */
	private function validate_text_opacity( array $css_properties ): void {
		if ( isset( $css_properties['opacity'] ) ) {
			$opacity = (float) $css_properties['opacity'];

			if ( $opacity < 0.5 ) {
				$this->warnings[] = sprintf(
					/* translators: %s: opacity value */
					__( 'Low opacity (%s) may affect text readability', 'wyverncss' ),
					number_format( $opacity, 2 )
				);
			}
		}
	}

	/**
	 * Get color value from CSS properties or context
	 *
	 * @param array<string, string> $css_properties CSS properties.
	 * @param string                $property Property name (color or background-color).
	 * @param array<string, mixed>  $context Context with default colors.
	 *
	 * @return string|null Color value or null.
	 */
	private function get_color_value( array $css_properties, string $property, array $context ): ?string {
		if ( isset( $css_properties[ $property ] ) ) {
			return $css_properties[ $property ];
		}

		// Try to get from context (existing element styles).
		$context_key = str_replace( '-', '_', $property );
		if ( isset( $context[ $context_key ] ) ) {
			return $context[ $context_key ];
		}

		// Use default colors as fallback.
		if ( 'color' === $property ) {
			return $context['default_text_color'] ?? null;
		}

		if ( 'background-color' === $property ) {
			return $context['default_background_color'] ?? null;
		}

		return null;
	}

	/**
	 * Calculate contrast ratio between two colors
	 *
	 * @param string $color1 First color (hex, rgb, or rgba).
	 * @param string $color2 Second color (hex, rgb, or rgba).
	 *
	 * @return float|false Contrast ratio or false on failure.
	 */
	private function calculate_contrast_ratio( string $color1, string $color2 ) {
		$rgb1 = $this->parse_color( $color1 );
		$rgb2 = $this->parse_color( $color2 );

		if ( false === $rgb1 || false === $rgb2 ) {
			return false;
		}

		$l1 = $this->get_relative_luminance( $rgb1 );
		$l2 = $this->get_relative_luminance( $rgb2 );

		$lighter = max( $l1, $l2 );
		$darker  = min( $l1, $l2 );

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * Parse color string to RGB array
	 *
	 * @param string $color Color string (hex, rgb, rgba, or named).
	 *
	 * @return array<int>|false RGB array [r, g, b] or false on failure.
	 */
	private function parse_color( string $color ) {
		$color = trim( strtolower( $color ) );

		// Hex color.
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color, $matches ) ) {
			return $this->hex_to_rgb( $matches[1] );
		}

		// RGB/RGBA.
		if ( preg_match( '/^rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/', $color, $matches ) ) {
			return array(
				(int) $matches[1],
				(int) $matches[2],
				(int) $matches[3],
			);
		}

		// Named colors (basic set).
		$named_colors = array(
			'black'   => array( 0, 0, 0 ),
			'white'   => array( 255, 255, 255 ),
			'red'     => array( 255, 0, 0 ),
			'green'   => array( 0, 128, 0 ),
			'blue'    => array( 0, 0, 255 ),
			'yellow'  => array( 255, 255, 0 ),
			'cyan'    => array( 0, 255, 255 ),
			'magenta' => array( 255, 0, 255 ),
			'gray'    => array( 128, 128, 128 ),
			'grey'    => array( 128, 128, 128 ),
		);

		if ( isset( $named_colors[ $color ] ) ) {
			return $named_colors[ $color ];
		}

		return false;
	}

	/**
	 * Convert hex color to RGB array
	 *
	 * @param string $hex Hex color (3 or 6 characters).
	 *
	 * @return array<int> RGB array [r, g, b].
	 */
	private function hex_to_rgb( string $hex ): array {
		// Expand shorthand hex (e.g., "03F" -> "0033FF").
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Calculate relative luminance for RGB color
	 *
	 * @param array<int> $rgb RGB array [r, g, b].
	 *
	 * @return float Relative luminance.
	 */
	private function get_relative_luminance( array $rgb ): float {
		// Convert to sRGB.
		$rgb_srgb = array_map(
			function ( int $value ): float {
				$value = $value / 255.0;
				return $value <= 0.03928
					? $value / 12.92
					: pow( ( $value + 0.055 ) / 1.055, 2.4 );
			},
			$rgb
		);

		// Calculate relative luminance.
		return 0.2126 * $rgb_srgb[0] + 0.7152 * $rgb_srgb[1] + 0.0722 * $rgb_srgb[2];
	}

	/**
	 * Determine if text is considered "large" by WCAG standards
	 *
	 * @param array<string, string> $css_properties CSS properties.
	 * @param array<string, mixed>  $context Context information.
	 *
	 * @return bool Whether text is large.
	 */
	private function is_large_text( array $css_properties, array $context ): bool {
		$font_size   = $css_properties['font-size'] ?? $context['font_size'] ?? '16px';
		$font_weight = $css_properties['font-weight'] ?? $context['font_weight'] ?? 'normal';

		$size_px = $this->convert_to_pixels( $font_size );

		if ( false === $size_px ) {
			return false;
		}

		$is_bold = in_array( $font_weight, array( 'bold', 'bolder', '700', '800', '900' ), true );

		// 18pt (24px) or larger is always large text.
		if ( $size_px >= self::LARGE_TEXT_THRESHOLD_PX ) {
			return true;
		}

		// 14pt (18.66px, rounded to 19px) or larger AND bold is large text.
		if ( $is_bold && $size_px >= self::LARGE_BOLD_TEXT_THRESHOLD_PX ) {
			return true;
		}

		return false;
	}

	/**
	 * Convert CSS size value to pixels
	 *
	 * @param string $value CSS size value.
	 *
	 * @return float|false Size in pixels or false on failure.
	 */
	private function convert_to_pixels( string $value ) {
		$value = trim( strtolower( $value ) );

		// Already in pixels.
		if ( preg_match( '/^([\d.]+)px$/', $value, $matches ) ) {
			return (float) $matches[1];
		}

		// Em units (assume 16px base).
		if ( preg_match( '/^([\d.]+)em$/', $value, $matches ) ) {
			return (float) $matches[1] * 16;
		}

		// Rem units (assume 16px base).
		if ( preg_match( '/^([\d.]+)rem$/', $value, $matches ) ) {
			return (float) $matches[1] * 16;
		}

		// Percentage (assume 16px base).
		if ( preg_match( '/^([\d.]+)%$/', $value, $matches ) ) {
			return ( (float) $matches[1] / 100 ) * 16;
		}

		// Pt units (1pt = 1.333px).
		if ( preg_match( '/^([\d.]+)pt$/', $value, $matches ) ) {
			return (float) $matches[1] * 1.333;
		}

		return false;
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
	 * Get accessibility recommendations
	 *
	 * @return array<string> Recommendations.
	 */
	public function get_recommendations(): array {
		return $this->recommendations;
	}

	/**
	 * Quick check: validate contrast between two colors
	 *
	 * @param string $foreground Foreground color.
	 * @param string $background Background color.
	 * @param bool   $is_large_text Whether text is large.
	 *
	 * @return array<string, mixed> Result with 'valid' boolean and 'ratio' float.
	 */
	public function check_contrast( string $foreground, string $background, bool $is_large_text = false ): array {
		$ratio = $this->calculate_contrast_ratio( $foreground, $background );

		if ( false === $ratio ) {
			return array(
				'valid' => false,
				'ratio' => 0,
				'error' => __( 'Could not calculate contrast ratio', 'wyverncss' ),
			);
		}

		$required = $is_large_text ? self::WCAG_AA_LARGE_CONTRAST : self::WCAG_AA_NORMAL_CONTRAST;

		return array(
			'valid'    => $ratio >= $required,
			'ratio'    => $ratio,
			'required' => $required,
			'level'    => $ratio >= 7.0 ? 'AAA' : ( $ratio >= $required ? 'AA' : 'fail' ),
		);
	}
}
