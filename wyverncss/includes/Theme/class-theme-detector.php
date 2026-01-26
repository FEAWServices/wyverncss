<?php
/**
 * Theme Detector - Detect and extract theme colors from WordPress.
 *
 * @package WyvernCSS
 * @since 1.1.0
 */

declare(strict_types=1);

namespace WyvernCSS\Theme;

/**
 * Detects and extracts color palette and styles from the active WordPress theme.
 */
class Theme_Detector {

	/**
	 * Cached theme colors.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $cached_colors = null;

	/**
	 * Get theme color palette.
	 *
	 * @return array<string, string> Color palette with semantic names.
	 */
	public function get_color_palette(): array {
		if ( null !== $this->cached_colors ) {
			return $this->cached_colors;
		}

		$colors = array();

		// Try to get colors from theme.json (block themes).
		$theme_json_colors = $this->get_theme_json_colors();
		if ( ! empty( $theme_json_colors ) ) {
			$colors = array_merge( $colors, $theme_json_colors );
		}

		// Try to get colors from theme mods (classic themes).
		$theme_mod_colors = $this->get_theme_mod_colors();
		if ( ! empty( $theme_mod_colors ) ) {
			$colors = array_merge( $colors, $theme_mod_colors );
		}

		// Try to get colors from editor color palette.
		$editor_colors = $this->get_editor_color_palette();
		if ( ! empty( $editor_colors ) ) {
			$colors = array_merge( $colors, $editor_colors );
		}

		// Fallback to default WordPress colors.
		if ( empty( $colors ) ) {
			$colors = $this->get_default_colors();
		}

		$this->cached_colors = $colors;
		return $colors;
	}

	/**
	 * Get colors from theme.json.
	 *
	 * @return array<string, string> Colors from theme.json.
	 */
	private function get_theme_json_colors(): array {
		$colors = array();

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return $colors;
		}

		$settings = wp_get_global_settings();

		if ( ! isset( $settings['color']['palette']['theme'] ) ) {
			return $colors;
		}

		foreach ( $settings['color']['palette']['theme'] as $color ) {
			if ( isset( $color['slug'], $color['color'] ) ) {
				$colors[ $this->normalize_color_name( $color['slug'] ) ] = $color['color'];
			}
		}

		return $colors;
	}

	/**
	 * Get colors from theme mods.
	 *
	 * @return array<string, string> Colors from theme mods.
	 */
	private function get_theme_mod_colors(): array {
		$colors = array();

		// Common theme mod names for colors.
		$color_mods = array(
			'primary'          => array( 'primary_color', 'primary-color', 'theme_primary_color', 'accent_color' ),
			'secondary'        => array( 'secondary_color', 'secondary-color', 'theme_secondary_color' ),
			'background'       => array( 'background_color', 'bg_color', 'theme_background_color' ),
			'text'             => array( 'text_color', 'body_text_color', 'theme_text_color' ),
			'link'             => array( 'link_color', 'links_color', 'theme_link_color' ),
			'header-background' => array( 'header_background_color', 'header_bg_color' ),
			'footer-background' => array( 'footer_background_color', 'footer_bg_color' ),
		);

		foreach ( $color_mods as $semantic_name => $mod_names ) {
			foreach ( $mod_names as $mod_name ) {
				$value = get_theme_mod( $mod_name, '' );
				if ( ! empty( $value ) && $this->is_valid_color( $value ) ) {
					$colors[ $semantic_name ] = $this->normalize_color( $value );
					break;
				}
			}
		}

		// Also check the WordPress core background_color setting.
		$bg_color = get_background_color();
		if ( ! empty( $bg_color ) && ! isset( $colors['background'] ) ) {
			$colors['background'] = '#' . ltrim( $bg_color, '#' );
		}

		return $colors;
	}

	/**
	 * Get colors from editor color palette.
	 *
	 * @return array<string, string> Colors from editor palette.
	 */
	private function get_editor_color_palette(): array {
		$colors = array();

		$palette = get_theme_support( 'editor-color-palette' );
		if ( empty( $palette ) || ! is_array( $palette[0] ) ) {
			return $colors;
		}

		foreach ( $palette[0] as $color ) {
			if ( isset( $color['slug'], $color['color'] ) ) {
				$colors[ $this->normalize_color_name( $color['slug'] ) ] = $color['color'];
			}
		}

		return $colors;
	}

	/**
	 * Get default WordPress colors.
	 *
	 * @return array<string, string> Default color palette.
	 */
	private function get_default_colors(): array {
		return array(
			'primary'    => '#007cba',
			'secondary'  => '#555d66',
			'background' => '#ffffff',
			'text'       => '#1e1e1e',
			'link'       => '#007cba',
			'accent'     => '#0073aa',
		);
	}

	/**
	 * Get theme typography settings.
	 *
	 * @return array<string, mixed> Typography settings.
	 */
	public function get_typography(): array {
		$typography = array(
			'font-family' => array(
				'body'    => $this->get_body_font_family(),
				'heading' => $this->get_heading_font_family(),
			),
			'font-size'   => array(
				'base'  => $this->get_base_font_size(),
				'scale' => $this->get_font_size_scale(),
			),
			'line-height' => array(
				'body'    => '1.6',
				'heading' => '1.2',
			),
		);

		return $typography;
	}

	/**
	 * Get body font family.
	 *
	 * @return string Font family.
	 */
	private function get_body_font_family(): string {
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$settings = wp_get_global_settings();
			if ( isset( $settings['typography']['fontFamily'] ) ) {
				return $settings['typography']['fontFamily'];
			}
		}

		$font = get_theme_mod( 'body_font_family', '' );
		if ( ! empty( $font ) ) {
			return $font;
		}

		return '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
	}

	/**
	 * Get heading font family.
	 *
	 * @return string Font family.
	 */
	private function get_heading_font_family(): string {
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$settings = wp_get_global_settings();
			// Check for heading-specific font first.
			if ( isset( $settings['blocks']['core/heading']['typography']['fontFamily'] ) ) {
				return $settings['blocks']['core/heading']['typography']['fontFamily'];
			}
		}

		$font = get_theme_mod( 'heading_font_family', '' );
		if ( ! empty( $font ) ) {
			return $font;
		}

		// Default to body font.
		return $this->get_body_font_family();
	}

	/**
	 * Get base font size.
	 *
	 * @return string Font size.
	 */
	private function get_base_font_size(): string {
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$settings = wp_get_global_settings();
			if ( isset( $settings['typography']['fontSize'] ) ) {
				return $settings['typography']['fontSize'];
			}
		}

		$size = get_theme_mod( 'base_font_size', '' );
		if ( ! empty( $size ) ) {
			return $size;
		}

		return '16px';
	}

	/**
	 * Get font size scale.
	 *
	 * @return array<string, string> Font size scale.
	 */
	private function get_font_size_scale(): array {
		$scale = array(
			'small'  => '0.875rem',
			'normal' => '1rem',
			'medium' => '1.125rem',
			'large'  => '1.5rem',
			'x-large' => '2rem',
		);

		if ( function_exists( 'wp_get_global_settings' ) ) {
			$settings = wp_get_global_settings();
			if ( isset( $settings['typography']['fontSizes']['theme'] ) ) {
				foreach ( $settings['typography']['fontSizes']['theme'] as $size ) {
					if ( isset( $size['slug'], $size['size'] ) ) {
						$scale[ $size['slug'] ] = $size['size'];
					}
				}
			}
		}

		return $scale;
	}

	/**
	 * Get theme spacing settings.
	 *
	 * @return array<string, string> Spacing scale.
	 */
	public function get_spacing(): array {
		$spacing = array(
			'xs'   => '0.25rem',
			'sm'   => '0.5rem',
			'md'   => '1rem',
			'lg'   => '1.5rem',
			'xl'   => '2rem',
			'2xl'  => '3rem',
		);

		if ( function_exists( 'wp_get_global_settings' ) ) {
			$settings = wp_get_global_settings();
			if ( isset( $settings['spacing']['spacingSizes']['theme'] ) ) {
				foreach ( $settings['spacing']['spacingSizes']['theme'] as $size ) {
					if ( isset( $size['slug'], $size['size'] ) ) {
						$spacing[ $size['slug'] ] = $size['size'];
					}
				}
			}
		}

		return $spacing;
	}

	/**
	 * Generate CSS variables from theme settings.
	 *
	 * @param string $prefix Variable prefix.
	 * @return string CSS variables declaration.
	 */
	public function generate_css_variables( string $prefix = 'wyverncss' ): string {
		$colors     = $this->get_color_palette();
		$typography = $this->get_typography();
		$spacing    = $this->get_spacing();

		$css = ":root {\n";

		// Colors.
		foreach ( $colors as $name => $value ) {
			$css .= "\t--{$prefix}-color-{$name}: {$value};\n";
		}

		// Typography.
		if ( isset( $typography['font-family']['body'] ) ) {
			$css .= "\t--{$prefix}-font-family-body: {$typography['font-family']['body']};\n";
		}
		if ( isset( $typography['font-family']['heading'] ) ) {
			$css .= "\t--{$prefix}-font-family-heading: {$typography['font-family']['heading']};\n";
		}
		if ( isset( $typography['font-size']['base'] ) ) {
			$css .= "\t--{$prefix}-font-size-base: {$typography['font-size']['base']};\n";
		}

		// Spacing.
		foreach ( $spacing as $name => $value ) {
			$css .= "\t--{$prefix}-spacing-{$name}: {$value};\n";
		}

		$css .= '}';

		return $css;
	}

	/**
	 * Get color by semantic name.
	 *
	 * @param string $name    Color name.
	 * @param string $default Default value.
	 * @return string Color value.
	 */
	public function get_color( string $name, string $default = '' ): string {
		$colors = $this->get_color_palette();
		return $colors[ $name ] ?? $default;
	}

	/**
	 * Find closest matching theme color.
	 *
	 * @param string $color Color to match.
	 * @return array{name: string, color: string, distance: float}|null Closest match or null.
	 */
	public function find_closest_color( string $color ): ?array {
		$colors = $this->get_color_palette();

		if ( empty( $colors ) ) {
			return null;
		}

		$input_rgb = $this->hex_to_rgb( $color );
		if ( null === $input_rgb ) {
			return null;
		}

		$closest     = null;
		$min_distance = PHP_FLOAT_MAX;

		foreach ( $colors as $name => $theme_color ) {
			$theme_rgb = $this->hex_to_rgb( $theme_color );
			if ( null === $theme_rgb ) {
				continue;
			}

			$distance = $this->color_distance( $input_rgb, $theme_rgb );

			if ( $distance < $min_distance ) {
				$min_distance = $distance;
				$closest     = array(
					'name'     => $name,
					'color'    => $theme_color,
					'distance' => $distance,
				);
			}
		}

		return $closest;
	}

	/**
	 * Suggest theme-matching colors for a given CSS.
	 *
	 * @param array<string, string> $css CSS properties.
	 * @return array<string, array{original: string, suggested: string, theme_name: string}>
	 */
	public function suggest_theme_colors( array $css ): array {
		$suggestions   = array();
		$color_props   = array( 'color', 'background-color', 'border-color', 'background' );

		foreach ( $css as $property => $value ) {
			if ( ! in_array( $property, $color_props, true ) ) {
				continue;
			}

			// Extract color from value.
			$color = $this->extract_color( $value );
			if ( null === $color ) {
				continue;
			}

			$closest = $this->find_closest_color( $color );
			if ( null === $closest || $closest['distance'] > 50 ) {
				continue;
			}

			$suggestions[ $property ] = array(
				'original'   => $value,
				'suggested'  => str_replace( $color, "var(--wyverncss-color-{$closest['name']})", $value ),
				'theme_name' => $closest['name'],
			);
		}

		return $suggestions;
	}

	/**
	 * Apply theme colors to CSS.
	 *
	 * @param array<string, string> $css           CSS properties.
	 * @param float                 $max_distance Maximum color distance to match (0-100).
	 * @return array<string, string> CSS with theme color variables.
	 */
	public function apply_theme_colors( array $css, float $max_distance = 30.0 ): array {
		$result      = array();
		$color_props = array( 'color', 'background-color', 'border-color', 'background', 'fill', 'stroke' );

		foreach ( $css as $property => $value ) {
			if ( ! in_array( $property, $color_props, true ) ) {
				$result[ $property ] = $value;
				continue;
			}

			$color = $this->extract_color( $value );
			if ( null === $color ) {
				$result[ $property ] = $value;
				continue;
			}

			$closest = $this->find_closest_color( $color );
			if ( null === $closest || $closest['distance'] > $max_distance ) {
				$result[ $property ] = $value;
				continue;
			}

			$result[ $property ] = str_replace( $color, "var(--wyverncss-color-{$closest['name']})", $value );
		}

		return $result;
	}

	/**
	 * Get full theme settings summary.
	 *
	 * @return array<string, mixed> Theme settings summary.
	 */
	public function get_theme_summary(): array {
		return array(
			'theme_name'   => wp_get_theme()->get( 'Name' ),
			'theme_version' => wp_get_theme()->get( 'Version' ),
			'is_block_theme' => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme(),
			'colors'       => $this->get_color_palette(),
			'typography'   => $this->get_typography(),
			'spacing'      => $this->get_spacing(),
		);
	}

	/**
	 * Clear cached data.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->cached_colors = null;
	}

	/**
	 * Normalize color name to slug format.
	 *
	 * @param string $name Color name.
	 * @return string Normalized name.
	 */
	private function normalize_color_name( string $name ): string {
		return strtolower( preg_replace( '/[^a-zA-Z0-9-]/', '-', $name ) ?? $name );
	}

	/**
	 * Normalize color value.
	 *
	 * @param string $color Color value.
	 * @return string Normalized color.
	 */
	private function normalize_color( string $color ): string {
		$color = trim( $color );

		// Add # if it's a hex without one.
		if ( preg_match( '/^[0-9a-fA-F]{3,6}$/', $color ) ) {
			return '#' . $color;
		}

		return $color;
	}

	/**
	 * Check if value is a valid color.
	 *
	 * @param string $value Value to check.
	 * @return bool True if valid color.
	 */
	private function is_valid_color( string $value ): bool {
		return (bool) preg_match( '/^#?[0-9a-fA-F]{3,6}$|^rgba?\(|^hsla?\(/i', $value );
	}

	/**
	 * Extract color from CSS value.
	 *
	 * @param string $value CSS value.
	 * @return string|null Extracted color or null.
	 */
	private function extract_color( string $value ): ?string {
		if ( preg_match( '/(#[0-9a-fA-F]{3,6})/', $value, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/(rgba?\([^)]+\))/', $value, $matches ) ) {
			return $matches[1];
		}

		return null;
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

		if ( strlen( $hex ) !== 6 ) {
			return null;
		}

		return array(
			'r' => (int) hexdec( substr( $hex, 0, 2 ) ),
			'g' => (int) hexdec( substr( $hex, 2, 2 ) ),
			'b' => (int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Calculate color distance using CIE76.
	 *
	 * @param array{r: int, g: int, b: int} $rgb1 First color.
	 * @param array{r: int, g: int, b: int} $rgb2 Second color.
	 * @return float Color distance.
	 */
	private function color_distance( array $rgb1, array $rgb2 ): float {
		// Simple Euclidean distance in RGB space (normalized to 0-100).
		$dr = $rgb1['r'] - $rgb2['r'];
		$dg = $rgb1['g'] - $rgb2['g'];
		$db = $rgb1['b'] - $rgb2['b'];

		return sqrt( $dr * $dr + $dg * $dg + $db * $db ) / 4.41; // Max distance ~441, normalize to ~100.
	}
}
