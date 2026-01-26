<?php
/**
 * Style Extractor Service
 *
 * Extracts CSS styles from external URLs for matching.
 *
 * @package WyvernCSS
 * @subpackage Extractor
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Extractor;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_Error;

/**
 * Style Extractor Class
 *
 * Fetches and analyzes CSS from external websites.
 */
class Style_Extractor {

	/**
	 * HTTP timeout in seconds.
	 */
	private const HTTP_TIMEOUT = 15;

	/**
	 * Maximum CSS size to process (500KB).
	 */
	private const MAX_CSS_SIZE = 512000;

	/**
	 * Extract styles from a URL.
	 *
	 * @param string $url URL to extract styles from.
	 * @return array<string, mixed>|WP_Error Extracted styles or error.
	 */
	public function extract_from_url( string $url ) {
		// Validate URL.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', 'Invalid URL provided.' );
		}

		// Only allow HTTP(S).
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'invalid_scheme', 'Only HTTP and HTTPS URLs are supported.' );
		}

		// Fetch the page.
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => self::HTTP_TIMEOUT,
				'user-agent' => 'WyvernCSS Style Extractor/1.0',
				'headers'    => array(
					'Accept' => 'text/html,application/xhtml+xml',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error(
				'fetch_failed',
				sprintf( 'Failed to fetch URL. Status code: %d', $status_code )
			);
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			return new WP_Error( 'empty_response', 'Empty response from URL.' );
		}

		// Extract styles from HTML.
		return $this->extract_from_html( $html, $url );
	}

	/**
	 * Extract styles from HTML content.
	 *
	 * @param string $html    HTML content.
	 * @param string $base_url Base URL for resolving relative paths.
	 * @return array<string, mixed> Extracted styles.
	 */
	public function extract_from_html( string $html, string $base_url = '' ): array {
		$styles = array(
			'inline_css'    => '',
			'linked_css'    => array(),
			'colors'        => array(),
			'fonts'         => array(),
			'font_sizes'    => array(),
			'border_radii'  => array(),
			'shadows'       => array(),
			'css_variables' => array(),
		);

		// Extract inline styles.
		preg_match_all( '/<style[^>]*>(.*?)<\/style>/is', $html, $style_matches );
		if ( ! empty( $style_matches[1] ) ) {
			$styles['inline_css'] = implode( "\n", $style_matches[1] );
		}

		// Extract linked stylesheets.
		preg_match_all( '/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $link_matches );
		preg_match_all( '/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']stylesheet["\'][^>]*>/i', $html, $link_matches2 );

		$stylesheet_urls = array_merge(
			$link_matches[1],
			$link_matches2[1]
		);

		// Fetch and combine external stylesheets.
		$external_css = '';
		foreach ( array_unique( $stylesheet_urls ) as $stylesheet_url ) {
			$resolved_url = $this->resolve_url( $stylesheet_url, $base_url );
			if ( $resolved_url ) {
				$css = $this->fetch_stylesheet( $resolved_url );
				if ( $css ) {
					$external_css          .= "\n" . $css;
					$styles['linked_css'][] = $resolved_url;
				}
			}
		}

		// Combine all CSS.
		$all_css = $styles['inline_css'] . "\n" . $external_css;

		// Limit CSS size.
		if ( strlen( $all_css ) > self::MAX_CSS_SIZE ) {
			$all_css = substr( $all_css, 0, self::MAX_CSS_SIZE );
		}

		// Extract design tokens.
		$styles['colors']        = $this->extract_colors( $all_css );
		$styles['fonts']         = $this->extract_fonts( $all_css );
		$styles['font_sizes']    = $this->extract_font_sizes( $all_css );
		$styles['border_radii']  = $this->extract_border_radii( $all_css );
		$styles['shadows']       = $this->extract_shadows( $all_css );
		$styles['css_variables'] = $this->extract_css_variables( $all_css );

		// Generate design summary.
		$styles['summary'] = $this->generate_summary( $styles );

		return $styles;
	}

	/**
	 * Generate CSS that matches extracted styles.
	 *
	 * @param array<string, mixed> $styles    Extracted styles.
	 * @param string               $target    Target selector or element type.
	 * @param array<string, mixed> $options   Generation options.
	 * @return array<string, mixed> Generated CSS and recommendations.
	 */
	public function generate_matching_css(
		array $styles,
		string $target = '.element',
		array $options = array()
	): array {
		$css_properties  = array();
		$recommendations = array();

		// Apply primary color.
		$primary_color = $styles['colors']['primary'] ?? null;
		if ( $primary_color ) {
			$css_properties['color'] = $primary_color;
			$recommendations[]       = sprintf( 'Using primary color: %s', $primary_color );
		}

		// Apply primary font.
		$primary_font = $styles['fonts']['primary'] ?? null;
		if ( $primary_font ) {
			$css_properties['font-family'] = $primary_font;
			$recommendations[]             = sprintf( 'Using primary font: %s', $primary_font );
		}

		// Apply common font size.
		$base_font_size = $styles['font_sizes']['base'] ?? null;
		if ( $base_font_size ) {
			$css_properties['font-size'] = $base_font_size;
		}

		// Apply common border radius.
		$common_radius = $styles['border_radii']['common'] ?? null;
		if ( $common_radius ) {
			$css_properties['border-radius'] = $common_radius;
			$recommendations[]               = sprintf( 'Using common border-radius: %s', $common_radius );
		}

		// Apply shadow if button or card.
		$has_shadow = ! empty( $options['with_shadow'] );
		if ( $has_shadow && ! empty( $styles['shadows']['common'] ) ) {
			$css_properties['box-shadow'] = $styles['shadows']['common'];
		}

		// Build CSS string.
		$css_string = $this->build_css_string( $target, $css_properties );

		return array(
			'css'             => $css_properties,
			'css_string'      => $css_string,
			'recommendations' => $recommendations,
			'source_summary'  => $styles['summary'] ?? null,
		);
	}

	/**
	 * Extract colors from CSS.
	 *
	 * @param string $css CSS content.
	 * @return array<string, mixed> Extracted colors.
	 */
	private function extract_colors( string $css ): array {
		$colors = array();

		// Extract hex colors.
		preg_match_all( '/#([a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})\b/', $css, $hex_matches );
		if ( ! empty( $hex_matches[0] ) ) {
			$colors['hex'] = array_unique( $hex_matches[0] );
		}

		// Extract rgb/rgba colors.
		preg_match_all( '/rgba?\s*\([^)]+\)/i', $css, $rgb_matches );
		if ( ! empty( $rgb_matches[0] ) ) {
			$colors['rgb'] = array_unique( $rgb_matches[0] );
		}

		// Extract hsl/hsla colors.
		preg_match_all( '/hsla?\s*\([^)]+\)/i', $css, $hsl_matches );
		if ( ! empty( $hsl_matches[0] ) ) {
			$colors['hsl'] = array_unique( $hsl_matches[0] );
		}

		// Count color occurrences and find most common.
		$all_colors = array_merge(
			$colors['hex'] ?? array(),
			$colors['rgb'] ?? array(),
			$colors['hsl'] ?? array()
		);

		$color_counts = array_count_values( $all_colors );
		arsort( $color_counts );

		// Get top colors.
		$top_colors = array_slice( array_keys( $color_counts ), 0, 5 );
		if ( ! empty( $top_colors ) ) {
			$colors['primary']   = $top_colors[0];
			$colors['secondary'] = $top_colors[1] ?? null;
			$colors['accent']    = $top_colors[2] ?? null;
		}

		$colors['total_count'] = count( $all_colors );

		return $colors;
	}

	/**
	 * Extract fonts from CSS.
	 *
	 * @param string $css CSS content.
	 * @return array<string, mixed> Extracted fonts.
	 */
	private function extract_fonts( string $css ): array {
		$fonts = array();

		// Extract font-family declarations.
		preg_match_all( '/font-family\s*:\s*([^;]+)/i', $css, $font_matches );
		if ( ! empty( $font_matches[1] ) ) {
			$font_stacks = array();
			foreach ( $font_matches[1] as $font_value ) {
				// Clean and normalize.
				$font_value = trim( $font_value );
				$font_value = preg_replace( '/\s*!important\s*/i', '', $font_value );
				if ( null !== $font_value && ! empty( $font_value ) ) {
					$font_stacks[] = $font_value;
				}
			}

			$fonts['stacks'] = array_unique( $font_stacks );

			// Extract primary font name.
			if ( ! empty( $font_stacks[0] ) ) {
				$primary_stack = $font_stacks[0];
				preg_match( '/^["\']?([^"\'`,]+)/', $primary_stack, $name_match );
				if ( ! empty( $name_match[1] ) ) {
					$fonts['primary'] = trim( $name_match[1] );
				}
			}
		}

		return $fonts;
	}

	/**
	 * Extract font sizes from CSS.
	 *
	 * @param string $css CSS content.
	 * @return array<string, mixed> Extracted font sizes.
	 */
	private function extract_font_sizes( string $css ): array {
		$sizes = array();

		// Extract font-size declarations.
		preg_match_all( '/font-size\s*:\s*([^;]+)/i', $css, $size_matches );
		if ( ! empty( $size_matches[1] ) ) {
			$size_values = array();
			foreach ( $size_matches[1] as $size_value ) {
				$size_value = trim( $size_value );
				$size_value = preg_replace( '/\s*!important\s*/i', '', $size_value );
				if ( null !== $size_value && ! empty( $size_value ) ) {
					$size_values[] = $size_value;
				}
			}

			// Count occurrences.
			$size_counts = array_count_values( $size_values );
			arsort( $size_counts );

			$sizes['values'] = array_slice( array_keys( $size_counts ), 0, 10 );

			// Find base font size (most common around 14-18px).
			foreach ( $sizes['values'] as $size ) {
				if ( preg_match( '/^(1[4-8])px$/i', $size ) || preg_match( '/^1rem$/i', $size ) ) {
					$sizes['base'] = $size;
					break;
				}
			}

			// If no base found, use most common.
			if ( empty( $sizes['base'] ) && ! empty( $sizes['values'] ) ) {
				$sizes['base'] = $sizes['values'][0];
			}
		}

		return $sizes;
	}

	/**
	 * Extract border radii from CSS.
	 *
	 * @param string $css CSS content.
	 * @return array<string, mixed> Extracted border radii.
	 */
	private function extract_border_radii( string $css ): array {
		$radii = array();

		// Extract border-radius declarations.
		preg_match_all( '/border-radius\s*:\s*([^;]+)/i', $css, $radius_matches );
		if ( ! empty( $radius_matches[1] ) ) {
			$radius_values = array();
			foreach ( $radius_matches[1] as $radius_value ) {
				$radius_value = trim( $radius_value );
				$radius_value = preg_replace( '/\s*!important\s*/i', '', $radius_value );
				if ( null !== $radius_value && '' !== $radius_value ) {
					$radius_values[] = $radius_value;
				}
			}

			// Count occurrences.
			$radius_counts = array_count_values( $radius_values );
			arsort( $radius_counts );

			// Ensure all values are strings (array_count_values converts '0' to int key 0).
			$radii['values'] = array_map( 'strval', array_slice( array_keys( $radius_counts ), 0, 5 ) );
			$radii['common'] = $radii['values'][0] ?? null;
		}

		return $radii;
	}

	/**
	 * Extract shadows from CSS.
	 *
	 * @param string $css CSS content.
	 * @return array<string, mixed> Extracted shadows.
	 */
	private function extract_shadows( string $css ): array {
		$shadows = array();

		// Extract box-shadow declarations.
		preg_match_all( '/box-shadow\s*:\s*([^;]+)/i', $css, $shadow_matches );
		if ( ! empty( $shadow_matches[1] ) ) {
			$shadow_values = array();
			foreach ( $shadow_matches[1] as $shadow_value ) {
				$shadow_value = trim( $shadow_value );
				$shadow_value = preg_replace( '/\s*!important\s*/i', '', $shadow_value );
				if ( null !== $shadow_value && ! empty( $shadow_value ) && 'none' !== strtolower( $shadow_value ) ) {
					$shadow_values[] = $shadow_value;
				}
			}

			if ( ! empty( $shadow_values ) ) {
				// Count occurrences.
				$shadow_counts = array_count_values( $shadow_values );
				arsort( $shadow_counts );

				$shadows['values'] = array_slice( array_keys( $shadow_counts ), 0, 5 );
				$shadows['common'] = $shadows['values'][0] ?? null;
			}
		}

		// Extract text-shadow declarations.
		preg_match_all( '/text-shadow\s*:\s*([^;]+)/i', $css, $text_shadow_matches );
		if ( ! empty( $text_shadow_matches[1] ) ) {
			$text_values = array();
			foreach ( $text_shadow_matches[1] as $shadow_value ) {
				$shadow_value = trim( $shadow_value );
				if ( ! empty( $shadow_value ) && 'none' !== strtolower( $shadow_value ) ) {
					$text_values[] = $shadow_value;
				}
			}
			$shadows['text'] = array_unique( $text_values );
		}

		return $shadows;
	}

	/**
	 * Extract CSS variables from CSS.
	 *
	 * @param string $css CSS content.
	 * @return array<string, mixed> Extracted CSS variables.
	 */
	private function extract_css_variables( string $css ): array {
		$variables = array();

		// Extract variable declarations.
		preg_match_all( '/--([a-zA-Z0-9-_]+)\s*:\s*([^;]+)/i', $css, $var_matches, PREG_SET_ORDER );
		if ( ! empty( $var_matches ) ) {
			foreach ( $var_matches as $match ) {
				$name  = trim( $match[1] );
				$value = trim( $match[2] );
				if ( ! empty( $name ) && ! empty( $value ) ) {
					$variables[ '--' . $name ] = $value;
				}
			}
		}

		// Categorize variables.
		$categorized = array(
			'colors'  => array(),
			'spacing' => array(),
			'fonts'   => array(),
			'other'   => array(),
		);

		foreach ( $variables as $name => $value ) {
			$lower_name = strtolower( $name );

			if ( preg_match( '/color|bg|background|text|primary|secondary|accent/i', $lower_name ) ||
				preg_match( '/^#|^rgb|^hsl/i', $value )
			) {
				$categorized['colors'][ $name ] = $value;
			} elseif ( preg_match( '/spacing|gap|margin|padding|size/i', $lower_name ) ) {
				$categorized['spacing'][ $name ] = $value;
			} elseif ( preg_match( '/font|family|weight/i', $lower_name ) ) {
				$categorized['fonts'][ $name ] = $value;
			} else {
				$categorized['other'][ $name ] = $value;
			}
		}

		return array(
			'all'         => $variables,
			'categorized' => $categorized,
			'count'       => count( $variables ),
		);
	}

	/**
	 * Generate design summary.
	 *
	 * @param array<string, mixed> $styles Extracted styles.
	 * @return array<string, mixed> Design summary.
	 */
	private function generate_summary( array $styles ): array {
		$summary = array(
			'color_palette'   => array(),
			'typography'      => array(),
			'design_language' => array(),
		);

		// Color palette.
		if ( ! empty( $styles['colors']['primary'] ) ) {
			$summary['color_palette']['primary'] = $styles['colors']['primary'];
		}
		if ( ! empty( $styles['colors']['secondary'] ) ) {
			$summary['color_palette']['secondary'] = $styles['colors']['secondary'];
		}
		if ( ! empty( $styles['colors']['accent'] ) ) {
			$summary['color_palette']['accent'] = $styles['colors']['accent'];
		}

		// Typography.
		if ( ! empty( $styles['fonts']['primary'] ) ) {
			$summary['typography']['primary_font'] = $styles['fonts']['primary'];
		}
		if ( ! empty( $styles['font_sizes']['base'] ) ) {
			$summary['typography']['base_size'] = $styles['font_sizes']['base'];
		}

		// Design language hints.
		$radius = $styles['border_radii']['common'] ?? null;
		if ( null !== $radius ) {
			$radius_str = (string) $radius;
			if ( preg_match( '/^0|none/i', $radius_str ) ) {
				$summary['design_language']['corners'] = 'sharp';
			} elseif ( preg_match( '/50%|9999|full/i', $radius_str ) ) {
				$summary['design_language']['corners'] = 'rounded';
			} else {
				$summary['design_language']['corners'] = 'subtle';
			}
		}

		if ( ! empty( $styles['shadows']['common'] ) ) {
			$summary['design_language']['elevation'] = 'uses_shadows';
		}

		// CSS Variables usage.
		$var_count = $styles['css_variables']['count'] ?? 0;
		if ( $var_count > 20 ) {
			$summary['design_language']['design_system'] = 'mature';
		} elseif ( $var_count > 5 ) {
			$summary['design_language']['design_system'] = 'basic';
		} else {
			$summary['design_language']['design_system'] = 'minimal';
		}

		return $summary;
	}

	/**
	 * Build CSS string from properties.
	 *
	 * @param string               $selector   CSS selector.
	 * @param array<string, mixed> $properties CSS properties.
	 * @return string CSS string.
	 */
	private function build_css_string( string $selector, array $properties ): string {
		if ( empty( $properties ) ) {
			return '';
		}

		$lines = array();
		foreach ( $properties as $prop => $value ) {
			if ( ! empty( $value ) ) {
				$lines[] = sprintf( '  %s: %s;', esc_attr( $prop ), esc_attr( (string) $value ) );
			}
		}

		if ( empty( $lines ) ) {
			return '';
		}

		return sprintf(
			"%s {\n%s\n}",
			esc_attr( $selector ),
			implode( "\n", $lines )
		);
	}

	/**
	 * Resolve relative URL to absolute.
	 *
	 * @param string $url      URL to resolve.
	 * @param string $base_url Base URL.
	 * @return string|null Resolved URL or null.
	 */
	private function resolve_url( string $url, string $base_url ): ?string {
		// Already absolute.
		if ( preg_match( '/^https?:\/\//i', $url ) ) {
			return $url;
		}

		// Protocol-relative.
		if ( str_starts_with( $url, '//' ) ) {
			return 'https:' . $url;
		}

		// Parse base URL.
		$parsed = wp_parse_url( $base_url );
		if ( ! $parsed || empty( $parsed['host'] ) ) {
			return null;
		}

		$scheme = $parsed['scheme'] ?? 'https';
		$host   = $parsed['host'];
		$port   = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';

		// Absolute path.
		if ( str_starts_with( $url, '/' ) ) {
			return sprintf( '%s://%s%s%s', $scheme, $host, $port, $url );
		}

		// Relative path.
		$base_path = $parsed['path'] ?? '/';
		$base_dir  = dirname( $base_path );
		if ( '.' === $base_dir || '\\' === $base_dir ) {
			$base_dir = '/';
		}

		return sprintf( '%s://%s%s%s/%s', $scheme, $host, $port, rtrim( $base_dir, '/' ), $url );
	}

	/**
	 * Fetch external stylesheet.
	 *
	 * @param string $url Stylesheet URL.
	 * @return string|null CSS content or null.
	 */
	private function fetch_stylesheet( string $url ): ?string {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => self::HTTP_TIMEOUT,
				'user-agent' => 'WyvernCSS Style Extractor/1.0',
				'headers'    => array(
					'Accept' => 'text/css,*/*',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return null;
		}

		$css = wp_remote_retrieve_body( $response );

		// Limit size.
		if ( strlen( $css ) > self::MAX_CSS_SIZE ) {
			$css = substr( $css, 0, self::MAX_CSS_SIZE );
		}

		return $css;
	}
}
