<?php
/**
 * CSS Optimizer Service
 *
 * Combines, minifies, and optimizes CSS.
 *
 * @package WyvernCSS
 * @subpackage Optimizer
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Optimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CSS Optimizer Class
 *
 * Provides CSS optimization utilities including:
 * - Minification
 * - Combining duplicate selectors
 * - Removing unused properties
 * - Shorthand conversion
 */
class CSS_Optimizer {

	/**
	 * Minify CSS.
	 *
	 * @param string $css CSS to minify.
	 * @return array<string, mixed> Minification result.
	 */
	public function minify( string $css ): array {
		$original_size = strlen( $css );

		// Remove comments.
		$minified = preg_replace( '/\/\*[\s\S]*?\*\//', '', $css );
		if ( null === $minified ) {
			$minified = $css;
		}

		// Remove whitespace around selectors and braces.
		$minified = preg_replace( '/\s*([{}:;,>+~])\s*/', '$1', $minified );
		if ( null === $minified ) {
			$minified = $css;
		}

		// Remove extra whitespace.
		$minified = preg_replace( '/\s+/', ' ', $minified );
		if ( null === $minified ) {
			$minified = $css;
		}

		// Remove whitespace at start/end.
		$minified = trim( $minified );

		// Remove spaces around combinators.
		$minified = preg_replace( '/\s*([>+~])\s*/', '$1', $minified );
		if ( null === $minified ) {
			$minified = $css;
		}

		// Remove semicolon before closing brace.
		$minified = str_replace( ';}', '}', $minified );

		// Remove empty rules.
		$minified = preg_replace( '/[^{}]+\{\s*\}/', '', $minified );
		if ( null === $minified ) {
			$minified = $css;
		}

		$minified_size = strlen( $minified );
		$savings       = $original_size - $minified_size;
		$savings_pct   = $original_size > 0 ? round( ( $savings / $original_size ) * 100, 2 ) : 0;

		return array(
			'original'      => $css,
			'minified'      => $minified,
			'original_size' => $original_size,
			'minified_size' => $minified_size,
			'savings'       => $savings,
			'savings_pct'   => $savings_pct,
		);
	}

	/**
	 * Combine duplicate selectors.
	 *
	 * @param string $css CSS to optimize.
	 * @return array<string, mixed> Combined CSS result.
	 */
	public function combine_selectors( string $css ): array {
		$rules     = $this->parse_css( $css );
		$combined  = array();
		$changes   = 0;

		// Group rules by selector.
		foreach ( $rules as $rule ) {
			$selector = $rule['selector'];

			if ( ! isset( $combined[ $selector ] ) ) {
				$combined[ $selector ] = array(
					'selector'   => $selector,
					'properties' => array(),
				);
			}

			// Merge properties (later properties override earlier).
			foreach ( $rule['properties'] as $prop => $value ) {
				if ( isset( $combined[ $selector ]['properties'][ $prop ] ) ) {
					++$changes; // Count overwritten properties.
				}
				$combined[ $selector ]['properties'][ $prop ] = $value;
			}
		}

		// Check for duplicate selector count.
		$original_count = count( $rules );
		$combined_count = count( $combined );
		$duplicates     = $original_count - $combined_count;

		// Rebuild CSS.
		$output = $this->build_css( array_values( $combined ) );

		return array(
			'original'         => $css,
			'combined'         => $output,
			'original_rules'   => $original_count,
			'combined_rules'   => $combined_count,
			'duplicates_merged' => $duplicates,
			'properties_merged' => $changes,
		);
	}

	/**
	 * Convert properties to shorthand.
	 *
	 * @param string $css CSS to optimize.
	 * @return array<string, mixed> Shorthand conversion result.
	 */
	public function convert_to_shorthand( string $css ): array {
		$rules   = $this->parse_css( $css );
		$changes = 0;

		foreach ( $rules as &$rule ) {
			$props = $rule['properties'];

			// Convert margin-* to margin.
			$margin_result = $this->combine_box_properties( $props, 'margin' );
			if ( $margin_result['changed'] ) {
				$rule['properties'] = $margin_result['properties'];
				++$changes;
			}

			// Convert padding-* to padding.
			$padding_result = $this->combine_box_properties( $rule['properties'], 'padding' );
			if ( $padding_result['changed'] ) {
				$rule['properties'] = $padding_result['properties'];
				++$changes;
			}

			// Convert border-* to border.
			$border_result = $this->combine_border_properties( $rule['properties'] );
			if ( $border_result['changed'] ) {
				$rule['properties'] = $border_result['properties'];
				++$changes;
			}

			// Convert background-* to background.
			$bg_result = $this->combine_background_properties( $rule['properties'] );
			if ( $bg_result['changed'] ) {
				$rule['properties'] = $bg_result['properties'];
				++$changes;
			}
		}

		$output = $this->build_css( $rules );

		return array(
			'original'  => $css,
			'optimized' => $output,
			'changes'   => $changes,
		);
	}

	/**
	 * Remove redundant properties.
	 *
	 * @param string $css CSS to optimize.
	 * @return array<string, mixed> Optimization result.
	 */
	public function remove_redundant( string $css ): array {
		$rules   = $this->parse_css( $css );
		$removed = 0;

		foreach ( $rules as &$rule ) {
			$props = $rule['properties'];

			// Remove properties with default values.
			$defaults = array(
				'opacity'           => '1',
				'visibility'        => 'visible',
				'position'          => 'static',
				'float'             => 'none',
				'clear'             => 'none',
				'z-index'           => 'auto',
				'vertical-align'    => 'baseline',
				'text-decoration'   => 'none',
				'text-transform'    => 'none',
				'font-style'        => 'normal',
				'font-weight'       => 'normal',
				'background-color'  => 'transparent',
				'border'            => 'none',
				'outline'           => 'none',
				'box-shadow'        => 'none',
				'text-shadow'       => 'none',
			);

			foreach ( $defaults as $prop => $default_value ) {
				if ( isset( $props[ $prop ] ) && strtolower( trim( $props[ $prop ] ) ) === $default_value ) {
					unset( $rule['properties'][ $prop ] );
					++$removed;
				}
			}

			// Remove duplicate values (e.g., margin: 10px 10px 10px 10px -> margin: 10px).
			foreach ( $rule['properties'] as $prop => $value ) {
				$simplified = $this->simplify_repeated_values( $value );
				if ( $simplified !== $value ) {
					$rule['properties'][ $prop ] = $simplified;
					++$removed;
				}
			}
		}

		$output = $this->build_css( $rules );

		return array(
			'original'            => $css,
			'optimized'           => $output,
			'redundant_removed'   => $removed,
		);
	}

	/**
	 * Full optimization pipeline.
	 *
	 * @param string               $css     CSS to optimize.
	 * @param array<string, mixed> $options Optimization options.
	 * @return array<string, mixed> Full optimization result.
	 */
	public function optimize( string $css, array $options = array() ): array {
		$original_size = strlen( $css );
		$optimized     = $css;
		$steps         = array();

		// Step 1: Combine selectors.
		if ( $options['combine_selectors'] ?? true ) {
			$combine_result = $this->combine_selectors( $optimized );
			$optimized      = $combine_result['combined'];
			$steps['combine_selectors'] = array(
				'duplicates_merged'  => $combine_result['duplicates_merged'],
				'properties_merged'  => $combine_result['properties_merged'],
			);
		}

		// Step 2: Convert to shorthand.
		if ( $options['shorthand'] ?? true ) {
			$shorthand_result = $this->convert_to_shorthand( $optimized );
			$optimized        = $shorthand_result['optimized'];
			$steps['shorthand'] = array(
				'changes' => $shorthand_result['changes'],
			);
		}

		// Step 3: Remove redundant.
		if ( $options['remove_redundant'] ?? true ) {
			$redundant_result = $this->remove_redundant( $optimized );
			$optimized        = $redundant_result['optimized'];
			$steps['remove_redundant'] = array(
				'removed' => $redundant_result['redundant_removed'],
			);
		}

		// Step 4: Minify.
		if ( $options['minify'] ?? true ) {
			$minify_result = $this->minify( $optimized );
			$optimized     = $minify_result['minified'];
			$steps['minify'] = array(
				'original_size' => $minify_result['original_size'],
				'minified_size' => $minify_result['minified_size'],
			);
		}

		$final_size  = strlen( $optimized );
		$savings     = $original_size - $final_size;
		$savings_pct = $original_size > 0 ? round( ( $savings / $original_size ) * 100, 2 ) : 0;

		return array(
			'original'      => $css,
			'optimized'     => $optimized,
			'original_size' => $original_size,
			'final_size'    => $final_size,
			'savings'       => $savings,
			'savings_pct'   => $savings_pct,
			'steps'         => $steps,
		);
	}

	/**
	 * Analyze CSS for optimization opportunities.
	 *
	 * @param string $css CSS to analyze.
	 * @return array<string, mixed> Analysis result.
	 */
	public function analyze( string $css ): array {
		$rules = $this->parse_css( $css );

		// Count selectors.
		$selectors = array();
		foreach ( $rules as $rule ) {
			$selector = $rule['selector'];
			if ( ! isset( $selectors[ $selector ] ) ) {
				$selectors[ $selector ] = 0;
			}
			++$selectors[ $selector ];
		}

		// Find duplicates.
		$duplicates = array_filter( $selectors, fn( $count ) => $count > 1 );

		// Analyze properties.
		$total_properties  = 0;
		$longhand_count    = 0;
		$redundant_count   = 0;

		$longhand_properties = array(
			'margin-top',
			'margin-right',
			'margin-bottom',
			'margin-left',
			'padding-top',
			'padding-right',
			'padding-bottom',
			'padding-left',
			'border-top',
			'border-right',
			'border-bottom',
			'border-left',
			'border-width',
			'border-style',
			'border-color',
			'background-color',
			'background-image',
			'background-repeat',
			'background-position',
		);

		$default_values = array(
			'opacity'          => '1',
			'visibility'       => 'visible',
			'position'         => 'static',
			'float'            => 'none',
			'background-color' => 'transparent',
		);

		foreach ( $rules as $rule ) {
			$total_properties += count( $rule['properties'] );

			foreach ( $rule['properties'] as $prop => $value ) {
				if ( in_array( $prop, $longhand_properties, true ) ) {
					++$longhand_count;
				}

				if ( isset( $default_values[ $prop ] ) &&
					strtolower( trim( $value ) ) === $default_values[ $prop ]
				) {
					++$redundant_count;
				}
			}
		}

		// Calculate potential savings.
		$minify_result = $this->minify( $css );

		return array(
			'total_rules'            => count( $rules ),
			'unique_selectors'       => count( $selectors ),
			'duplicate_selectors'    => count( $duplicates ),
			'duplicates'             => $duplicates,
			'total_properties'       => $total_properties,
			'longhand_properties'    => $longhand_count,
			'redundant_properties'   => $redundant_count,
			'current_size'           => strlen( $css ),
			'potential_minified'     => $minify_result['minified_size'],
			'potential_savings'      => $minify_result['savings'],
			'potential_savings_pct'  => $minify_result['savings_pct'],
			'recommendations'        => $this->get_recommendations(
				count( $duplicates ),
				$longhand_count,
				$redundant_count,
				$minify_result['savings_pct']
			),
		);
	}

	/**
	 * Parse CSS into rules.
	 *
	 * @param string $css CSS to parse.
	 * @return array<int, array<string, mixed>> Parsed rules.
	 */
	private function parse_css( string $css ): array {
		$rules = array();

		// Remove comments.
		$clean = preg_replace( '/\/\*[\s\S]*?\*\//', '', $css );
		if ( null === $clean ) {
			return array();
		}

		// Match rule blocks.
		preg_match_all( '/([^{}]+)\{([^{}]*)\}/s', $clean, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$selector     = trim( $match[1] );
			$declarations = trim( $match[2] );

			// Skip empty selectors.
			if ( empty( $selector ) ) {
				continue;
			}

			$properties = array();
			preg_match_all( '/([a-z-]+)\s*:\s*([^;]+)/i', $declarations, $prop_matches, PREG_SET_ORDER );

			foreach ( $prop_matches as $prop ) {
				$prop_name  = strtolower( trim( $prop[1] ) );
				$prop_value = trim( $prop[2] );
				$properties[ $prop_name ] = $prop_value;
			}

			$rules[] = array(
				'selector'   => $selector,
				'properties' => $properties,
			);
		}

		return $rules;
	}

	/**
	 * Build CSS from rules.
	 *
	 * @param array<int, array<string, mixed>> $rules Rules to build.
	 * @return string CSS string.
	 */
	private function build_css( array $rules ): string {
		$lines = array();

		foreach ( $rules as $rule ) {
			if ( empty( $rule['properties'] ) ) {
				continue;
			}

			$props = array();
			foreach ( $rule['properties'] as $prop => $value ) {
				$props[] = sprintf( '  %s: %s;', $prop, $value );
			}

			$lines[] = sprintf(
				"%s {\n%s\n}",
				$rule['selector'],
				implode( "\n", $props )
			);
		}

		return implode( "\n\n", $lines );
	}

	/**
	 * Combine box model properties (margin, padding).
	 *
	 * @param array<string, string> $props    Properties.
	 * @param string                $property Base property name.
	 * @return array{changed: bool, properties: array<string, string>} Result.
	 */
	private function combine_box_properties( array $props, string $property ): array {
		$sides = array( 'top', 'right', 'bottom', 'left' );
		$values = array();

		foreach ( $sides as $side ) {
			$key = "{$property}-{$side}";
			if ( isset( $props[ $key ] ) ) {
				$values[ $side ] = $props[ $key ];
			}
		}

		// Need all 4 sides to combine.
		if ( count( $values ) !== 4 ) {
			return array(
				'changed'    => false,
				'properties' => $props,
			);
		}

		// Remove individual properties.
		foreach ( $sides as $side ) {
			$key = $property . '-' . $side;
			unset( $props[ $key ] );
		}

		// Add shorthand.
		$shorthand = $this->create_box_shorthand(
			$values['top'],
			$values['right'],
			$values['bottom'],
			$values['left']
		);

		$props[ $property ] = $shorthand;

		return array(
			'changed'    => true,
			'properties' => $props,
		);
	}

	/**
	 * Combine border properties.
	 *
	 * @param array<string, string> $props Properties.
	 * @return array{changed: bool, properties: array<string, string>} Result.
	 */
	private function combine_border_properties( array $props ): array {
		$has_width = isset( $props['border-width'] );
		$has_style = isset( $props['border-style'] );
		$has_color = isset( $props['border-color'] );

		// Need at least width and style to combine.
		if ( ! $has_width || ! $has_style ) {
			return array(
				'changed'    => false,
				'properties' => $props,
			);
		}

		$width = $props['border-width'];
		$style = $props['border-style'];
		$color = $has_color ? $props['border-color'] : '';

		// Remove individual properties.
		unset( $props['border-width'], $props['border-style'], $props['border-color'] );

		// Add shorthand.
		$shorthand = trim( "{$width} {$style} {$color}" );
		$props['border'] = $shorthand;

		return array(
			'changed'    => true,
			'properties' => $props,
		);
	}

	/**
	 * Combine background properties.
	 *
	 * @param array<string, string> $props Properties.
	 * @return array{changed: bool, properties: array<string, string>} Result.
	 */
	private function combine_background_properties( array $props ): array {
		$bg_props = array(
			'background-color',
			'background-image',
			'background-repeat',
			'background-position',
			'background-size',
		);

		$found = array();
		foreach ( $bg_props as $prop ) {
			if ( isset( $props[ $prop ] ) ) {
				$found[ $prop ] = $props[ $prop ];
			}
		}

		// Need at least 2 properties to make combining worthwhile.
		if ( count( $found ) < 2 ) {
			return array(
				'changed'    => false,
				'properties' => $props,
			);
		}

		// Build shorthand value.
		$parts = array();

		if ( isset( $found['background-color'] ) ) {
			$parts[] = $found['background-color'];
		}

		if ( isset( $found['background-image'] ) ) {
			$parts[] = $found['background-image'];
		}

		if ( isset( $found['background-repeat'] ) ) {
			$parts[] = $found['background-repeat'];
		}

		if ( isset( $found['background-position'] ) ) {
			$parts[] = $found['background-position'];
		}

		// Remove individual properties.
		foreach ( $found as $prop => $value ) {
			unset( $props[ $prop ] );
		}

		// Add shorthand.
		$props['background'] = implode( ' ', $parts );

		return array(
			'changed'    => true,
			'properties' => $props,
		);
	}

	/**
	 * Create box model shorthand.
	 *
	 * @param string $top    Top value.
	 * @param string $right  Right value.
	 * @param string $bottom Bottom value.
	 * @param string $left   Left value.
	 * @return string Shorthand value.
	 */
	private function create_box_shorthand( string $top, string $right, string $bottom, string $left ): string {
		// All same.
		if ( $top === $right && $right === $bottom && $bottom === $left ) {
			return $top;
		}

		// Top/bottom same, left/right same.
		if ( $top === $bottom && $left === $right ) {
			return "{$top} {$right}";
		}

		// Left/right same.
		if ( $left === $right ) {
			return "{$top} {$right} {$bottom}";
		}

		// All different.
		return "{$top} {$right} {$bottom} {$left}";
	}

	/**
	 * Simplify repeated values.
	 *
	 * @param string $value Property value.
	 * @return string Simplified value.
	 */
	private function simplify_repeated_values( string $value ): string {
		$parts = preg_split( '/\s+/', trim( $value ) );

		if ( false === $parts || count( $parts ) !== 4 ) {
			return $value;
		}

		return $this->create_box_shorthand(
			$parts[0],
			$parts[1],
			$parts[2],
			$parts[3]
		);
	}

	/**
	 * Get optimization recommendations.
	 *
	 * @param int   $duplicates    Number of duplicate selectors.
	 * @param int   $longhand      Number of longhand properties.
	 * @param int   $redundant     Number of redundant properties.
	 * @param float $savings_pct   Potential savings percentage.
	 * @return array<int, string> Recommendations.
	 */
	private function get_recommendations( int $duplicates, int $longhand, int $redundant, float $savings_pct ): array {
		$recommendations = array();

		if ( $duplicates > 0 ) {
			$recommendations[] = sprintf(
				'Combine %d duplicate selector(s) to reduce redundancy.',
				$duplicates
			);
		}

		if ( $longhand > 5 ) {
			$recommendations[] = sprintf(
				'Convert %d longhand properties to shorthand.',
				$longhand
			);
		}

		if ( $redundant > 0 ) {
			$recommendations[] = sprintf(
				'Remove %d redundant properties with default values.',
				$redundant
			);
		}

		if ( $savings_pct > 20 ) {
			$recommendations[] = sprintf(
				'Minification can reduce size by %.1f%%.',
				$savings_pct
			);
		}

		if ( empty( $recommendations ) ) {
			$recommendations[] = 'Your CSS is already well optimized!';
		}

		return $recommendations;
	}
}
