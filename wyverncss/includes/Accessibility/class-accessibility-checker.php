<?php
/**
 * Accessibility Checker Service
 *
 * Analyzes CSS for WCAG accessibility compliance.
 *
 * @package WyvernCSS
 * @subpackage Accessibility
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Accessibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Accessibility Checker Class
 *
 * Checks CSS for WCAG 2.1 AA/AAA compliance issues.
 */
class Accessibility_Checker {

	/**
	 * Issue severity levels.
	 */
	public const SEVERITY_ERROR   = 'error';
	public const SEVERITY_WARNING = 'warning';
	public const SEVERITY_INFO    = 'info';

	/**
	 * WCAG conformance levels.
	 */
	public const WCAG_A   = 'A';
	public const WCAG_AA  = 'AA';
	public const WCAG_AAA = 'AAA';

	/**
	 * Minimum contrast ratios per WCAG.
	 */
	private const CONTRAST_NORMAL_AA  = 4.5;
	private const CONTRAST_NORMAL_AAA = 7.0;
	private const CONTRAST_LARGE_AA   = 3.0;
	private const CONTRAST_LARGE_AAA  = 4.5;

	/**
	 * Minimum font sizes in pixels.
	 */
	private const MIN_FONT_SIZE_NORMAL = 12;

	/**
	 * Check CSS for accessibility issues.
	 *
	 * @param string               $css     CSS to check.
	 * @param array<string, mixed> $context Optional context (background color, etc.).
	 * @return array<string, mixed> Check result.
	 */
	public function check( string $css, array $context = array() ): array {
		$issues = array();

		// Parse CSS into rule blocks.
		$rules = $this->parse_css( $css );

		foreach ( $rules as $rule ) {
			// Check each rule for accessibility issues.
			$issues = array_merge( $issues, $this->check_rule( $rule, $context ) );
		}

		// Add general CSS-level checks.
		$issues = array_merge( $issues, $this->check_general( $css ) );

		return array(
			'passes'        => 0 === count( array_filter( $issues, fn( $i ) => self::SEVERITY_ERROR === $i['severity'] ) ),
			'issues'        => $issues,
			'error_count'   => count( array_filter( $issues, fn( $i ) => self::SEVERITY_ERROR === $i['severity'] ) ),
			'warning_count' => count( array_filter( $issues, fn( $i ) => self::SEVERITY_WARNING === $i['severity'] ) ),
			'info_count'    => count( array_filter( $issues, fn( $i ) => self::SEVERITY_INFO === $i['severity'] ) ),
			'wcag_level'    => $this->determine_wcag_level( $issues ),
		);
	}

	/**
	 * Check color contrast between foreground and background.
	 *
	 * @param string $foreground Foreground color (hex).
	 * @param string $background Background color (hex).
	 * @param string $level      WCAG level (AA or AAA).
	 * @param bool   $large_text Whether text is large (18px+ or 14px+ bold).
	 * @return array<string, mixed> Contrast check result.
	 */
	public function check_contrast(
		string $foreground,
		string $background,
		string $level = self::WCAG_AA,
		bool $large_text = false
	): array {
		$fg_rgb = $this->hex_to_rgb( $foreground );
		$bg_rgb = $this->hex_to_rgb( $background );

		if ( null === $fg_rgb || null === $bg_rgb ) {
			return array(
				'passes'   => false,
				'ratio'    => 0,
				'required' => 0,
				'error'    => 'Invalid color format.',
			);
		}

		$ratio = $this->calculate_contrast_ratio( $fg_rgb, $bg_rgb );

		// Determine required ratio.
		if ( self::WCAG_AAA === $level ) {
			$required = $large_text ? self::CONTRAST_LARGE_AAA : self::CONTRAST_NORMAL_AAA;
		} else {
			$required = $large_text ? self::CONTRAST_LARGE_AA : self::CONTRAST_NORMAL_AA;
		}

		return array(
			'passes'     => $ratio >= $required,
			'ratio'      => round( $ratio, 2 ),
			'required'   => $required,
			'level'      => $level,
			'large_text' => $large_text,
			'suggestion' => $ratio < $required ? $this->suggest_color_fix( $fg_rgb, $bg_rgb, $required ) : null,
		);
	}

	/**
	 * Get accessibility suggestions for CSS.
	 *
	 * @param string               $css     CSS to analyze.
	 * @param array<string, mixed> $context Optional context.
	 * @return array<int, array<string, mixed>> Suggestions.
	 */
	public function get_suggestions( string $css, array $context = array() ): array {
		$suggestions = array();

		// Check for focus styles.
		if ( strpos( $css, ':focus' ) === false && strpos( $css, 'outline' ) === false ) {
			$suggestions[] = array(
				'type'       => 'missing_focus_styles',
				'wcag'       => '2.4.7',
				'level'      => self::WCAG_AA,
				'message'    => 'No focus styles detected. Users navigating with keyboard need visible focus indicators.',
				'suggestion' => 'Add :focus styles with outline or box-shadow for interactive elements.',
				'example'    => ':focus { outline: 2px solid #007cba; outline-offset: 2px; }',
			);
		}

		// Check for hover-only interactions.
		if ( strpos( $css, ':hover' ) !== false && strpos( $css, ':focus' ) === false ) {
			$suggestions[] = array(
				'type'       => 'hover_without_focus',
				'wcag'       => '2.1.1',
				'level'      => self::WCAG_A,
				'message'    => 'Found :hover styles without corresponding :focus styles.',
				'suggestion' => 'Add :focus styles alongside :hover for keyboard accessibility.',
				'example'    => ':hover, :focus { /* your styles */ }',
			);
		}

		// Check for animations without reduced motion.
		if ( preg_match( '/animation|transition|transform/i', $css ) ) {
			if ( strpos( $css, 'prefers-reduced-motion' ) === false ) {
				$suggestions[] = array(
					'type'       => 'animation_no_reduced_motion',
					'wcag'       => '2.3.3',
					'level'      => self::WCAG_AAA,
					'message'    => 'Animations detected without prefers-reduced-motion media query.',
					'suggestion' => 'Respect user preferences by disabling animations for users who prefer reduced motion.',
					'example'    => '@media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }',
				);
			}
		}

		// Check for very small fonts.
		if ( preg_match( '/font-size\s*:\s*(\d+(?:\.\d+)?)(px|pt)/i', $css, $matches ) ) {
			$size = (float) $matches[1];
			$unit = strtolower( $matches[2] );

			// Convert pt to px if needed.
			if ( 'pt' === $unit ) {
				$size = $size * 1.333;
			}

			if ( $size < self::MIN_FONT_SIZE_NORMAL ) {
				$suggestions[] = array(
					'type'       => 'font_too_small',
					'wcag'       => '1.4.4',
					'level'      => self::WCAG_AA,
					'message'    => sprintf( 'Font size %.1fpx is below minimum recommended size.', $size ),
					'suggestion' => 'Use at least 12px, preferably 16px, for body text.',
					'example'    => 'font-size: 1rem; /* or 16px */',
				);
			}
		}

		// Check for text with background image.
		if ( strpos( $css, 'background-image' ) !== false || strpos( $css, 'background:' ) !== false ) {
			if ( preg_match( '/url\s*\(/i', $css ) ) {
				$suggestions[] = array(
					'type'       => 'text_over_image',
					'wcag'       => '1.4.3',
					'level'      => self::WCAG_AA,
					'message'    => 'Background image detected. Ensure text remains readable.',
					'suggestion' => 'Add a solid background color fallback or text shadow for contrast.',
					'example'    => 'background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url(image.jpg);',
				);
			}
		}

		// Check for fixed positioning (may affect screen magnifiers).
		if ( preg_match( '/position\s*:\s*fixed/i', $css ) ) {
			$suggestions[] = array(
				'type'       => 'fixed_positioning',
				'wcag'       => '1.4.10',
				'level'      => self::WCAG_AA,
				'message'    => 'Fixed positioning may cause issues for users with screen magnifiers.',
				'suggestion' => 'Ensure fixed elements do not block content or navigation.',
				'example'    => 'Consider sticky positioning or ensuring adequate spacing.',
			);
		}

		// Check for color-only information.
		if ( preg_match( '/color\s*:\s*(red|green|#f00|#0f0|#ff0000|#00ff00)/i', $css ) ) {
			$suggestions[] = array(
				'type'       => 'color_only_info',
				'wcag'       => '1.4.1',
				'level'      => self::WCAG_A,
				'message'    => 'Color alone should not be used to convey information.',
				'suggestion' => 'Combine color with text, icons, or patterns for status indicators.',
				'example'    => 'Use icons or text labels alongside color changes.',
			);
		}

		return $suggestions;
	}

	/**
	 * Parse CSS into rule blocks.
	 *
	 * @param string $css CSS to parse.
	 * @return array<int, array<string, mixed>> Parsed rules.
	 */
	private function parse_css( string $css ): array {
		$rules = array();

		// Remove comments.
		$clean = preg_replace( '/\/\*.*?\*\//s', '', $css );
		if ( null === $clean ) {
			return array();
		}

		// Match rule blocks.
		preg_match_all( '/([^{}]+)\{([^{}]*)\}/s', $clean, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$selector     = trim( $match[1] );
			$declarations = trim( $match[2] );

			$properties = array();
			preg_match_all( '/([a-z-]+)\s*:\s*([^;]+)/i', $declarations, $prop_matches, PREG_SET_ORDER );

			foreach ( $prop_matches as $prop ) {
				$properties[ strtolower( trim( $prop[1] ) ) ] = trim( $prop[2] );
			}

			$rules[] = array(
				'selector'   => $selector,
				'properties' => $properties,
				'raw'        => $match[0],
			);
		}

		return $rules;
	}

	/**
	 * Check a single CSS rule for accessibility issues.
	 *
	 * @param array<string, mixed> $rule    Parsed rule.
	 * @param array<string, mixed> $context Optional context.
	 * @return array<int, array<string, mixed>> Issues found.
	 */
	private function check_rule( array $rule, array $context ): array {
		$issues     = array();
		$properties = $rule['properties'] ?? array();
		$selector   = $rule['selector'] ?? '';

		// Check color contrast if both color and background are present.
		if ( isset( $properties['color'] ) && isset( $properties['background-color'] ) ) {
			$fg = $properties['color'];
			$bg = $properties['background-color'];

			// Only check if both are hex colors.
			if ( $this->is_hex_color( $fg ) && $this->is_hex_color( $bg ) ) {
				$contrast = $this->check_contrast( $fg, $bg );

				if ( ! $contrast['passes'] ) {
					$issues[] = array(
						'severity'   => self::SEVERITY_ERROR,
						'type'       => 'insufficient_contrast',
						'wcag'       => '1.4.3',
						'level'      => self::WCAG_AA,
						'selector'   => $selector,
						'message'    => sprintf(
							'Contrast ratio %.2f:1 is below the required %.1f:1 for WCAG AA.',
							$contrast['ratio'],
							$contrast['required']
						),
						'suggestion' => $contrast['suggestion'],
						'details'    => array(
							'foreground' => $fg,
							'background' => $bg,
							'ratio'      => $contrast['ratio'],
						),
					);
				}
			}
		}

		// Check for context-based contrast.
		if ( isset( $properties['color'] ) && isset( $context['background'] ) ) {
			$fg = $properties['color'];
			$bg = $context['background'];

			if ( $this->is_hex_color( $fg ) && $this->is_hex_color( $bg ) ) {
				$contrast = $this->check_contrast( $fg, $bg );

				if ( ! $contrast['passes'] ) {
					$issues[] = array(
						'severity'   => self::SEVERITY_WARNING,
						'type'       => 'contrast_context',
						'wcag'       => '1.4.3',
						'level'      => self::WCAG_AA,
						'selector'   => $selector,
						'message'    => sprintf(
							'Color may have insufficient contrast (%.2f:1) against context background.',
							$contrast['ratio']
						),
						'suggestion' => $contrast['suggestion'],
					);
				}
			}
		}

		// Check font size.
		if ( isset( $properties['font-size'] ) ) {
			$size = $this->parse_font_size( $properties['font-size'] );

			if ( null !== $size && $size < self::MIN_FONT_SIZE_NORMAL ) {
				$issues[] = array(
					'severity'   => self::SEVERITY_WARNING,
					'type'       => 'small_font',
					'wcag'       => '1.4.4',
					'level'      => self::WCAG_AA,
					'selector'   => $selector,
					'message'    => sprintf( 'Font size %.1fpx is very small and may be difficult to read.', $size ),
					'suggestion' => 'Use at least 12px, preferably 16px, for body text.',
				);
			}
		}

		// Check for outline removal.
		if ( isset( $properties['outline'] ) &&
			( 'none' === $properties['outline'] || '0' === $properties['outline'] )
		) {
			// Check if it's a focus rule.
			if ( strpos( $selector, ':focus' ) !== false ) {
				$issues[] = array(
					'severity'   => self::SEVERITY_ERROR,
					'type'       => 'focus_outline_removed',
					'wcag'       => '2.4.7',
					'level'      => self::WCAG_AA,
					'selector'   => $selector,
					'message'    => 'Removing outline on focus breaks keyboard navigation visibility.',
					'suggestion' => 'Replace with a visible alternative like box-shadow or custom outline.',
				);
			}
		}

		// Check line height.
		if ( isset( $properties['line-height'] ) ) {
			$line_height = $this->parse_line_height( $properties['line-height'] );

			if ( null !== $line_height && $line_height < 1.5 ) {
				$issues[] = array(
					'severity'   => self::SEVERITY_WARNING,
					'type'       => 'tight_line_height',
					'wcag'       => '1.4.12',
					'level'      => self::WCAG_AA,
					'selector'   => $selector,
					'message'    => sprintf( 'Line height %.2f is below the recommended 1.5 for readability.', $line_height ),
					'suggestion' => 'Use line-height of at least 1.5 for paragraph text.',
				);
			}
		}

		// Check text decoration (underline removal on links).
		if ( strpos( $selector, 'a' ) !== false || strpos( $selector, 'link' ) !== false ) {
			if ( isset( $properties['text-decoration'] ) && 'none' === $properties['text-decoration'] ) {
				$issues[] = array(
					'severity'   => self::SEVERITY_WARNING,
					'type'       => 'link_underline_removed',
					'wcag'       => '1.4.1',
					'level'      => self::WCAG_A,
					'selector'   => $selector,
					'message'    => 'Removing underline from links may make them less recognizable.',
					'suggestion' => 'Use another visual indicator (e.g., border-bottom) or ensure color contrast is sufficient.',
				);
			}
		}

		return $issues;
	}

	/**
	 * Run general CSS-level accessibility checks.
	 *
	 * @param string $css CSS to check.
	 * @return array<int, array<string, mixed>> Issues found.
	 */
	private function check_general( string $css ): array {
		$issues = array();

		// Check for !important overuse.
		$important_count = substr_count( $css, '!important' );
		if ( $important_count > 5 ) {
			$issues[] = array(
				'severity'   => self::SEVERITY_INFO,
				'type'       => 'important_overuse',
				'wcag'       => '1.4.4',
				'level'      => self::WCAG_AA,
				'message'    => sprintf( 'Found %d uses of !important which may prevent user style overrides.', $important_count ),
				'suggestion' => 'Reduce !important usage to allow users to apply custom styles.',
			);
		}

		// Check for user-select: none.
		if ( strpos( $css, 'user-select: none' ) !== false || strpos( $css, 'user-select:none' ) !== false ) {
			$issues[] = array(
				'severity'   => self::SEVERITY_WARNING,
				'type'       => 'text_selection_disabled',
				'wcag'       => '1.4.4',
				'level'      => self::WCAG_AA,
				'message'    => 'Disabling text selection may impact users with assistive technologies.',
				'suggestion' => 'Only disable selection on interactive elements, not text content.',
			);
		}

		// Check for pointer-events: none.
		if ( preg_match( '/pointer-events\s*:\s*none/i', $css ) ) {
			$issues[] = array(
				'severity'   => self::SEVERITY_INFO,
				'type'       => 'pointer_events_disabled',
				'wcag'       => '2.1.1',
				'level'      => self::WCAG_A,
				'message'    => 'Disabling pointer events may impact accessibility.',
				'suggestion' => 'Ensure disabled elements are communicated via ARIA attributes.',
			);
		}

		return $issues;
	}

	/**
	 * Determine WCAG compliance level based on issues.
	 *
	 * @param array<int, array<string, mixed>> $issues Found issues.
	 * @return string|null WCAG level (A, AA, AAA) or null if fails A.
	 */
	private function determine_wcag_level( array $issues ): ?string {
		$has_a_error   = false;
		$has_aa_error  = false;
		$has_aaa_error = false;

		foreach ( $issues as $issue ) {
			if ( self::SEVERITY_ERROR !== $issue['severity'] ) {
				continue;
			}

			$level = $issue['level'] ?? self::WCAG_A;

			switch ( $level ) {
				case self::WCAG_A:
					$has_a_error = true;
					break;
				case self::WCAG_AA:
					$has_aa_error = true;
					break;
				case self::WCAG_AAA:
					$has_aaa_error = true;
					break;
			}
		}

		if ( $has_a_error ) {
			return null; // Fails WCAG A.
		}

		if ( $has_aa_error ) {
			return self::WCAG_A; // Passes A, fails AA.
		}

		if ( $has_aaa_error ) {
			return self::WCAG_AA; // Passes AA, fails AAA.
		}

		return self::WCAG_AAA; // Passes all levels.
	}

	/**
	 * Convert hex color to RGB.
	 *
	 * @param string $hex Hex color string.
	 * @return array{r: int, g: int, b: int}|null RGB values or null if invalid.
	 */
	private function hex_to_rgb( string $hex ): ?array {
		$hex = ltrim( $hex, '#' );

		// Handle shorthand.
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( strlen( $hex ) !== 6 && strlen( $hex ) !== 8 ) {
			return null;
		}

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		return array(
			'r' => (int) $r,
			'g' => (int) $g,
			'b' => (int) $b,
		);
	}

	/**
	 * Calculate relative luminance.
	 *
	 * @param array{r: int, g: int, b: int} $rgb RGB values.
	 * @return float Relative luminance.
	 */
	private function calculate_luminance( array $rgb ): float {
		$r = $rgb['r'] / 255;
		$g = $rgb['g'] / 255;
		$b = $rgb['b'] / 255;

		$r = $r <= 0.03928 ? $r / 12.92 : pow( ( $r + 0.055 ) / 1.055, 2.4 );
		$g = $g <= 0.03928 ? $g / 12.92 : pow( ( $g + 0.055 ) / 1.055, 2.4 );
		$b = $b <= 0.03928 ? $b / 12.92 : pow( ( $b + 0.055 ) / 1.055, 2.4 );

		return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
	}

	/**
	 * Calculate contrast ratio between two colors.
	 *
	 * @param array{r: int, g: int, b: int} $fg Foreground RGB.
	 * @param array{r: int, g: int, b: int} $bg Background RGB.
	 * @return float Contrast ratio.
	 */
	private function calculate_contrast_ratio( array $fg, array $bg ): float {
		$l1 = $this->calculate_luminance( $fg );
		$l2 = $this->calculate_luminance( $bg );

		$lighter = max( $l1, $l2 );
		$darker  = min( $l1, $l2 );

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * Suggest a color fix for better contrast.
	 *
	 * @param array{r: int, g: int, b: int} $fg       Foreground RGB.
	 * @param array{r: int, g: int, b: int} $bg       Background RGB.
	 * @param float                         $required Required ratio.
	 * @return string Suggested color fix.
	 */
	private function suggest_color_fix( array $fg, array $bg, float $required ): string {
		$fg_luminance = $this->calculate_luminance( $fg );
		$bg_luminance = $this->calculate_luminance( $bg );

		// Determine if we should lighten or darken the foreground.
		if ( $fg_luminance > $bg_luminance ) {
			// Foreground is lighter, try making it lighter.
			return 'Try using a lighter foreground color or darker background.';
		} else {
			// Foreground is darker, try making it darker.
			return 'Try using a darker foreground color or lighter background.';
		}
	}

	/**
	 * Check if string is a valid hex color.
	 *
	 * @param string $value Value to check.
	 * @return bool True if valid hex color.
	 */
	private function is_hex_color( string $value ): bool {
		return (bool) preg_match( '/^#[a-fA-F0-9]{3}([a-fA-F0-9]{3})?([a-fA-F0-9]{2})?$/', $value );
	}

	/**
	 * Parse font size to pixels.
	 *
	 * @param string $value Font size value.
	 * @return float|null Size in pixels or null.
	 */
	private function parse_font_size( string $value ): ?float {
		// Handle px.
		if ( preg_match( '/^(\d+(?:\.\d+)?)\s*px$/i', $value, $matches ) ) {
			return (float) $matches[1];
		}

		// Handle pt.
		if ( preg_match( '/^(\d+(?:\.\d+)?)\s*pt$/i', $value, $matches ) ) {
			return (float) $matches[1] * 1.333;
		}

		// Handle rem (assume 16px base).
		if ( preg_match( '/^(\d+(?:\.\d+)?)\s*rem$/i', $value, $matches ) ) {
			return (float) $matches[1] * 16;
		}

		// Handle em (estimate based on 16px).
		if ( preg_match( '/^(\d+(?:\.\d+)?)\s*em$/i', $value, $matches ) ) {
			return (float) $matches[1] * 16;
		}

		return null;
	}

	/**
	 * Parse line height value.
	 *
	 * @param string $value Line height value.
	 * @return float|null Unitless line height or null.
	 */
	private function parse_line_height( string $value ): ?float {
		// Unitless value.
		if ( preg_match( '/^(\d+(?:\.\d+)?)$/', $value, $matches ) ) {
			return (float) $matches[1];
		}

		// Percentage.
		if ( preg_match( '/^(\d+(?:\.\d+)?)\s*%$/i', $value, $matches ) ) {
			return (float) $matches[1] / 100;
		}

		return null;
	}
}
