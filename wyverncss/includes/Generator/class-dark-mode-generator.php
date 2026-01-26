<?php
/**
 * Dark Mode CSS Generator
 *
 * Generates dark mode variants of CSS styles.
 * Premium feature for automatic dark mode conversion.
 *
 * @package WyvernCSS
 * @subpackage Generator
 * @since 1.1.0
 */

declare(strict_types=1);

namespace WyvernCSS\Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_Error;

/**
 * Class DarkModeGenerator
 *
 * Generates dark mode CSS variants using prefers-color-scheme
 * or custom selectors.
 *
 * @since 1.1.0
 */
class DarkModeGenerator {

	/**
	 * Color transformation strategies.
	 */
	public const STRATEGY_INVERT     = 'invert';
	public const STRATEGY_LIGHTEN    = 'lighten';
	public const STRATEGY_COMPLEMENT = 'complement';
	public const STRATEGY_CUSTOM     = 'custom';

	/**
	 * Common light colors to convert to dark.
	 *
	 * @var array<string, string>
	 */
	private const COLOR_MAPPINGS = array(
		// White variants to dark.
		'#ffffff' => '#1a1a1a',
		'#fff'    => '#1a1a1a',
		'white'   => '#1a1a1a',
		'#fafafa' => '#1f1f1f',
		'#f5f5f5' => '#262626',
		'#f0f0f0' => '#2d2d2d',
		'#eeeeee' => '#333333',
		'#e0e0e0' => '#404040',
		// Black variants to light.
		'#000000' => '#ffffff',
		'#000'    => '#ffffff',
		'black'   => '#ffffff',
		'#111111' => '#f0f0f0',
		'#1a1a1a' => '#e8e8e8',
		'#222222' => '#dddddd',
		'#333333' => '#cccccc',
		// Common grays.
		'#666666' => '#999999',
		'#777777' => '#888888',
		'#888888' => '#777777',
		'#999999' => '#666666',
	);

	/**
	 * CSS properties that contain colors.
	 *
	 * @var array<int, string>
	 */
	private const COLOR_PROPERTIES = array(
		'color',
		'background-color',
		'background',
		'border-color',
		'border-top-color',
		'border-right-color',
		'border-bottom-color',
		'border-left-color',
		'outline-color',
		'box-shadow',
		'text-shadow',
		'fill',
		'stroke',
	);

	/**
	 * Dark mode selector strategy.
	 *
	 * @var string
	 */
	private string $selector_strategy = 'media-query';

	/**
	 * Custom dark mode selector.
	 *
	 * @var string
	 */
	private string $custom_selector = '.dark-mode';

	/**
	 * Color transformation strategy.
	 *
	 * @var string
	 */
	private string $color_strategy = self::STRATEGY_INVERT;

	/**
	 * Custom color mappings.
	 *
	 * @var array<string, string>
	 */
	private array $custom_mappings = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $options Configuration options.
	 */
	public function __construct( array $options = array() ) {
		if ( isset( $options['selector_strategy'] ) ) {
			$this->selector_strategy = $options['selector_strategy'];
		}
		if ( isset( $options['custom_selector'] ) ) {
			$this->custom_selector = $options['custom_selector'];
		}
		if ( isset( $options['color_strategy'] ) ) {
			$this->color_strategy = $options['color_strategy'];
		}
		if ( isset( $options['custom_mappings'] ) && is_array( $options['custom_mappings'] ) ) {
			$this->custom_mappings = $options['custom_mappings'];
		}
	}

	/**
	 * Generate dark mode CSS from light mode styles.
	 *
	 * @param string                $selector    CSS selector.
	 * @param array<string, string> $light_css   Light mode CSS properties.
	 * @param array<string, mixed>  $options     Generation options.
	 * @return array{light_css: string, dark_css: string, combined: string, conversions: array<string, array{from: string, to: string}>}|WP_Error
	 */
	public function generate( string $selector, array $light_css, array $options = array() ) {
		if ( empty( $selector ) ) {
			return new WP_Error(
				'invalid_selector',
				__( 'CSS selector cannot be empty.', 'wyvern-ai-styling' )
			);
		}

		if ( empty( $light_css ) ) {
			return new WP_Error(
				'empty_css',
				__( 'CSS properties cannot be empty.', 'wyvern-ai-styling' )
			);
		}

		// Apply option overrides.
		$strategy = $options['color_strategy'] ?? $this->color_strategy;

		// Convert light mode to dark mode.
		$conversions = array();
		$dark_css    = $this->convert_to_dark( $light_css, $strategy, $conversions );

		// Apply explicit overrides.
		if ( isset( $options['overrides'] ) && is_array( $options['overrides'] ) ) {
			$dark_css = array_merge( $dark_css, $this->sanitize_css( $options['overrides'] ) );
		}

		// Generate CSS strings.
		$light_str = $this->generate_css_block( $selector, $light_css );
		$dark_str  = $this->generate_dark_mode_css( $selector, $dark_css, $options );

		// Combine into single output.
		$combined = $light_str . "\n\n" . $dark_str;

		return array(
			'light_css'   => $light_str,
			'dark_css'    => $dark_str,
			'combined'    => $combined,
			'conversions' => $conversions,
		);
	}

	/**
	 * Convert light mode CSS to dark mode.
	 *
	 * @param array<string, string>                          $css          CSS properties.
	 * @param string                                         $strategy     Color strategy.
	 * @param array<string, array{from: string, to: string}> $conversions Tracks conversions.
	 * @return array<string, string> Dark mode CSS.
	 */
	private function convert_to_dark( array $css, string $strategy, array &$conversions ): array {
		$dark_css = array();

		foreach ( $css as $property => $value ) {
			if ( $this->is_color_property( $property ) ) {
				$converted = $this->convert_color_value( $value, $strategy );
				if ( $converted !== $value ) {
					$conversions[ $property ] = array(
						'from' => $value,
						'to'   => $converted,
					);
				}
				$dark_css[ $property ] = $converted;
			} else {
				// Non-color properties pass through unchanged.
				$dark_css[ $property ] = $value;
			}
		}

		return $dark_css;
	}

	/**
	 * Check if a property is color-related.
	 *
	 * @param string $property CSS property name.
	 * @return bool True if color property.
	 */
	private function is_color_property( string $property ): bool {
		return in_array( $property, self::COLOR_PROPERTIES, true );
	}

	/**
	 * Convert a color value to dark mode.
	 *
	 * @param string $value    Color value.
	 * @param string $strategy Conversion strategy.
	 * @return string Converted color.
	 */
	private function convert_color_value( string $value, string $strategy ): string {
		$value_lower = strtolower( trim( $value ) );

		// Check custom mappings first.
		if ( isset( $this->custom_mappings[ $value_lower ] ) ) {
			return $this->custom_mappings[ $value_lower ];
		}

		// Check built-in mappings.
		if ( isset( self::COLOR_MAPPINGS[ $value_lower ] ) ) {
			return self::COLOR_MAPPINGS[ $value_lower ];
		}

		// Handle complex values (gradients, shadows with colors).
		// Note: Don't treat standalone rgba/rgb values as complex - they're handled below.
		$is_standalone_rgb = preg_match( '/^rgba?\([^)]+\)$/i', $value );
		if ( ! $is_standalone_rgb && ( strpos( $value, ',' ) !== false || strpos( $value, 'gradient' ) !== false ||
			( strpos( $value, ' ' ) !== false && preg_match( '/#[0-9a-fA-F]{3,8}|rgba?\(/i', $value ) ) ) ) {
			return $this->convert_complex_value( $value, $strategy );
		}

		// Apply strategy-based conversion for hex colors.
		if ( preg_match( '/^#[0-9a-f]{3,8}$/i', $value ) ) {
			switch ( $strategy ) {
				case self::STRATEGY_INVERT:
					return $this->invert_color( $value );
				case self::STRATEGY_LIGHTEN:
					return $this->adjust_lightness( $value, 0.7 );
				case self::STRATEGY_COMPLEMENT:
					return $this->get_complement( $value );
				default:
					return $value;
			}
		}

		// Handle rgba/rgb.
		if ( preg_match( '/^rgba?\(/i', $value ) ) {
			return $this->convert_rgb_value( $value, $strategy );
		}

		return $value;
	}

	/**
	 * Convert complex CSS values (shadows, gradients).
	 *
	 * @param string $value    Complex value.
	 * @param string $strategy Conversion strategy.
	 * @return string Converted value.
	 */
	private function convert_complex_value( string $value, string $strategy ): string {
		// Find all hex colors and rgb/rgba values.
		$converted = preg_replace_callback(
			'/(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))/',
			function ( $matches ) use ( $strategy ) {
				return $this->convert_color_value( $matches[0], $strategy );
			},
			$value
		);

		return is_string( $converted ) ? $converted : $value;
	}

	/**
	 * Invert a hex color.
	 *
	 * @param string $hex Hex color.
	 * @return string Inverted hex.
	 */
	private function invert_color( string $hex ): string {
		$rgb = $this->hex_to_rgb( $hex );
		if ( null === $rgb ) {
			return $hex;
		}

		return sprintf(
			'#%02x%02x%02x',
			255 - $rgb['r'],
			255 - $rgb['g'],
			255 - $rgb['b']
		);
	}

	/**
	 * Adjust lightness of a color.
	 *
	 * @param string $hex    Hex color.
	 * @param float  $factor Lightness factor (0-1).
	 * @return string Adjusted hex.
	 */
	private function adjust_lightness( string $hex, float $factor ): string {
		$rgb = $this->hex_to_rgb( $hex );
		if ( null === $rgb ) {
			return $hex;
		}

		$hsl = $this->rgb_to_hsl( $rgb['r'], $rgb['g'], $rgb['b'] );

		// For dark mode, if lightness > 50%, reduce it; if < 50%, increase it.
		if ( $hsl['l'] > 0.5 ) {
			$hsl['l'] = $hsl['l'] * $factor;
		} else {
			$hsl['l'] = 1 - ( ( 1 - $hsl['l'] ) * $factor );
		}

		$rgb = $this->hsl_to_rgb( $hsl['h'], $hsl['s'], $hsl['l'] );

		return sprintf( '#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b'] );
	}

	/**
	 * Get complementary color.
	 *
	 * @param string $hex Hex color.
	 * @return string Complement hex.
	 */
	private function get_complement( string $hex ): string {
		$rgb = $this->hex_to_rgb( $hex );
		if ( null === $rgb ) {
			return $hex;
		}

		$hsl = $this->rgb_to_hsl( $rgb['r'], $rgb['g'], $rgb['b'] );

		// Rotate hue by 180 degrees.
		$hsl['h'] = fmod( $hsl['h'] + 180, 360 );

		// Also invert lightness for dark mode.
		$hsl['l'] = 1 - $hsl['l'];

		$rgb = $this->hsl_to_rgb( $hsl['h'], $hsl['s'], $hsl['l'] );

		return sprintf( '#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b'] );
	}

	/**
	 * Convert RGB/RGBA value to dark mode.
	 *
	 * @param string $value    RGB/RGBA value.
	 * @param string $strategy Conversion strategy.
	 * @return string Converted value.
	 */
	private function convert_rgb_value( string $value, string $strategy ): string {
		if ( preg_match( '/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?\)/', $value, $matches ) ) {
			$r     = (int) $matches[1];
			$g     = (int) $matches[2];
			$b     = (int) $matches[3];
			$alpha = $matches[4] ?? null;

			// Convert to hex, apply strategy, convert back.
			$hex       = sprintf( '#%02x%02x%02x', $r, $g, $b );
			$converted = $this->convert_color_value( $hex, $strategy );
			$rgb       = $this->hex_to_rgb( $converted );

			if ( null === $rgb ) {
				return $value;
			}

			if ( null !== $alpha ) {
				return "rgba({$rgb['r']}, {$rgb['g']}, {$rgb['b']}, {$alpha})";
			}

			return "rgb({$rgb['r']}, {$rgb['g']}, {$rgb['b']})";
		}

		return $value;
	}

	/**
	 * Convert hex to RGB.
	 *
	 * @param string $hex Hex color.
	 * @return array{r: int, g: int, b: int}|null RGB values or null.
	 */
	private function hex_to_rgb( string $hex ): ?array {
		$hex = ltrim( $hex, '#' );

		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( strlen( $hex ) !== 6 && strlen( $hex ) !== 8 ) {
			return null;
		}

		return array(
			'r' => (int) hexdec( substr( $hex, 0, 2 ) ),
			'g' => (int) hexdec( substr( $hex, 2, 2 ) ),
			'b' => (int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Convert RGB to HSL.
	 *
	 * @param int $r Red (0-255).
	 * @param int $g Green (0-255).
	 * @param int $b Blue (0-255).
	 * @return array{h: float, s: float, l: float} HSL values.
	 */
	private function rgb_to_hsl( int $r, int $g, int $b ): array {
		$r /= 255;
		$g /= 255;
		$b /= 255;

		$max   = max( $r, $g, $b );
		$min   = min( $r, $g, $b );
		$l     = ( $max + $min ) / 2;
		$h     = 0.0;
		$s     = 0.0;
		$delta = $max - $min;

		if ( $delta > 0 ) {
			$s = $l > 0.5 ? $delta / ( 2 - $max - $min ) : $delta / ( $max + $min );

			if ( $max === $r ) {
				$h = fmod( ( $g - $b ) / $delta + ( $g < $b ? 6 : 0 ), 6 );
			} elseif ( $max === $g ) {
				$h = ( $b - $r ) / $delta + 2;
			} else {
				$h = ( $r - $g ) / $delta + 4;
			}

			$h *= 60;
		}

		return array(
			'h' => $h,
			's' => $s,
			'l' => $l,
		);
	}

	/**
	 * Convert HSL to RGB.
	 *
	 * @param float $h Hue (0-360).
	 * @param float $s Saturation (0-1).
	 * @param float $l Lightness (0-1).
	 * @return array{r: int, g: int, b: int} RGB values.
	 */
	private function hsl_to_rgb( float $h, float $s, float $l ): array {
		if ( abs( $s ) < 0.0001 ) {
			$v = (int) round( $l * 255 );
			return array(
				'r' => $v,
				'g' => $v,
				'b' => $v,
			);
		}

		$q = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
		$p = 2 * $l - $q;

		$h /= 360;

		return array(
			'r' => (int) round( $this->hue_to_rgb( $p, $q, $h + 1 / 3 ) * 255 ),
			'g' => (int) round( $this->hue_to_rgb( $p, $q, $h ) * 255 ),
			'b' => (int) round( $this->hue_to_rgb( $p, $q, $h - 1 / 3 ) * 255 ),
		);
	}

	/**
	 * Helper for HSL to RGB conversion.
	 *
	 * @param float $p P value.
	 * @param float $q Q value.
	 * @param float $t T value.
	 * @return float RGB component.
	 */
	private function hue_to_rgb( float $p, float $q, float $t ): float {
		if ( $t < 0 ) {
			++$t;
		}
		if ( $t > 1 ) {
			--$t;
		}
		if ( $t < 1 / 6 ) {
			return $p + ( $q - $p ) * 6 * $t;
		}
		if ( $t < 1 / 2 ) {
			return $q;
		}
		if ( $t < 2 / 3 ) {
			return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
		}
		return $p;
	}

	/**
	 * Generate CSS block.
	 *
	 * @param string                $selector CSS selector.
	 * @param array<string, string> $css      CSS properties.
	 * @return string CSS block.
	 */
	private function generate_css_block( string $selector, array $css ): string {
		$output = "{$selector} {\n";

		foreach ( $css as $property => $value ) {
			$output .= "  {$property}: {$value};\n";
		}

		$output .= '}';

		return $output;
	}

	/**
	 * Generate dark mode CSS with appropriate wrapper.
	 *
	 * @param string                $selector CSS selector.
	 * @param array<string, string> $css      Dark mode CSS.
	 * @param array<string, mixed>  $options  Options.
	 * @return string Dark mode CSS.
	 */
	private function generate_dark_mode_css( string $selector, array $css, array $options ): string {
		$strategy        = $options['selector_strategy'] ?? $this->selector_strategy;
		$custom_selector = $options['custom_selector'] ?? $this->custom_selector;

		$css_block = '';
		foreach ( $css as $property => $value ) {
			$css_block .= "    {$property}: {$value};\n";
		}

		switch ( $strategy ) {
			case 'media-query':
				return "@media (prefers-color-scheme: dark) {\n  {$selector} {\n{$css_block}  }\n}";

			case 'class':
				return "{$custom_selector} {$selector} {\n{$css_block}}";

			case 'data-attribute':
				return "[data-theme=\"dark\"] {$selector} {\n{$css_block}}";

			case 'both':
				$media = "@media (prefers-color-scheme: dark) {\n  {$selector} {\n{$css_block}  }\n}";
				$class = "\n\n{$custom_selector} {$selector} {\n{$css_block}}";
				return $media . $class;

			default:
				return "@media (prefers-color-scheme: dark) {\n  {$selector} {\n{$css_block}  }\n}";
		}
	}

	/**
	 * Sanitize CSS properties.
	 *
	 * @param array<string, mixed> $css CSS properties.
	 * @return array<string, string> Sanitized CSS.
	 */
	private function sanitize_css( array $css ): array {
		$sanitized = array();

		foreach ( $css as $property => $value ) {
			$property = sanitize_key( str_replace( '_', '-', $property ) );
			$value    = sanitize_text_field( (string) $value );

			if ( preg_match( '/expression|javascript|url\s*\(/i', $value ) ) {
				continue;
			}

			$sanitized[ $property ] = $value;
		}

		return $sanitized;
	}

	/**
	 * Get available color strategies.
	 *
	 * @return array<string, string> Strategy names and descriptions.
	 */
	public function get_strategies(): array {
		return array(
			self::STRATEGY_INVERT     => __( 'Invert colors (RGB inversion)', 'wyvern-ai-styling' ),
			self::STRATEGY_LIGHTEN    => __( 'Adjust lightness (HSL-based)', 'wyvern-ai-styling' ),
			self::STRATEGY_COMPLEMENT => __( 'Complementary colors (Hue rotation)', 'wyvern-ai-styling' ),
			self::STRATEGY_CUSTOM     => __( 'Custom color mappings', 'wyvern-ai-styling' ),
		);
	}

	/**
	 * Add custom color mapping.
	 *
	 * @param string $light_color Light mode color.
	 * @param string $dark_color  Dark mode color.
	 * @return void
	 */
	public function add_color_mapping( string $light_color, string $dark_color ): void {
		$this->custom_mappings[ strtolower( $light_color ) ] = $dark_color;
	}

	/**
	 * Set selector strategy.
	 *
	 * @param string $strategy Strategy (media-query, class, data-attribute, both).
	 * @return void
	 */
	public function set_selector_strategy( string $strategy ): void {
		$this->selector_strategy = $strategy;
	}

	/**
	 * Set custom selector for class-based dark mode.
	 *
	 * @param string $selector Custom selector (e.g., '.dark-mode', '[data-theme="dark"]').
	 * @return void
	 */
	public function set_custom_selector( string $selector ): void {
		$this->custom_selector = $selector;
	}
}
