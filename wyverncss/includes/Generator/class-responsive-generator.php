<?php
/**
 * Responsive CSS Generator
 *
 * Generates responsive CSS with media queries for different breakpoints.
 * Premium feature for creating mobile-first responsive styles.
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
 * Class ResponsiveGenerator
 *
 * Handles generation of responsive CSS with breakpoints.
 *
 * @since 1.1.0
 */
class ResponsiveGenerator {

	/**
	 * Default breakpoints (mobile-first approach).
	 *
	 * @var array<string, array{min: int, max: int|null, label: string}>
	 */
	public const DEFAULT_BREAKPOINTS = array(
		'mobile'  => array(
			'min'   => 0,
			'max'   => 767,
			'label' => 'Mobile',
		),
		'tablet'  => array(
			'min'   => 768,
			'max'   => 1023,
			'label' => 'Tablet',
		),
		'desktop' => array(
			'min'   => 1024,
			'max'   => 1439,
			'label' => 'Desktop',
		),
		'large'   => array(
			'min'   => 1440,
			'max'   => null,
			'label' => 'Large Desktop',
		),
	);

	/**
	 * Common responsive property transformations.
	 *
	 * @var array<string, array{scale_mobile: float, scale_tablet: float}>
	 */
	private const PROPERTY_SCALING = array(
		'font-size'      => array(
			'scale_mobile' => 0.875,
			'scale_tablet' => 0.9375,
		),
		'padding'        => array(
			'scale_mobile' => 0.75,
			'scale_tablet' => 0.875,
		),
		'padding-top'    => array(
			'scale_mobile' => 0.75,
			'scale_tablet' => 0.875,
		),
		'padding-right'  => array(
			'scale_mobile' => 0.75,
			'scale_tablet' => 0.875,
		),
		'padding-bottom' => array(
			'scale_mobile' => 0.75,
			'scale_tablet' => 0.875,
		),
		'padding-left'   => array(
			'scale_mobile' => 0.75,
			'scale_tablet' => 0.875,
		),
		'margin'         => array(
			'scale_mobile' => 0.75,
			'scale_tablet' => 0.875,
		),
		'margin-top'     => array(
			'scale_mobile' => 0.75,
			'scale_tablet' => 0.875,
		),
		'margin-right'   => array(
			'scale_mobile' => 0.75,
			'scale_tablet' => 0.875,
		),
		'margin-bottom'  => array(
			'scale_mobile' => 0.75,
			'scale_tablet' => 0.875,
		),
		'margin-left'    => array(
			'scale_mobile' => 0.75,
			'scale_tablet' => 0.875,
		),
		'gap'            => array(
			'scale_mobile' => 0.75,
			'scale_tablet' => 0.875,
		),
		'border-radius'  => array(
			'scale_mobile' => 0.875,
			'scale_tablet' => 0.9375,
		),
		'width'          => array(
			'scale_mobile' => 1.0,
			'scale_tablet' => 1.0,
		),
		'max-width'      => array(
			'scale_mobile' => 1.0,
			'scale_tablet' => 1.0,
		),
	);

	/**
	 * Current breakpoints configuration.
	 *
	 * @var array<string, array{min: int, max: int|null, label: string}>
	 */
	private array $breakpoints;

	/**
	 * Constructor.
	 *
	 * @param array<string, array{min: int, max: int|null, label: string}>|null $breakpoints Custom breakpoints.
	 */
	public function __construct( ?array $breakpoints = null ) {
		$this->breakpoints = $breakpoints ?? self::DEFAULT_BREAKPOINTS;
	}

	/**
	 * Generate responsive CSS from base styles.
	 *
	 * @param string                $selector   CSS selector.
	 * @param array<string, string> $base_css   Base CSS properties.
	 * @param array<string, mixed>  $options    Generation options.
	 * @return array{css: string, breakpoints: array<string, array<string, string>>}|WP_Error
	 */
	public function generate( string $selector, array $base_css, array $options = array() ) {
		if ( empty( $selector ) ) {
			return new WP_Error(
				'invalid_selector',
				__( 'CSS selector cannot be empty.', 'wyverncss' )
			);
		}

		if ( empty( $base_css ) ) {
			return new WP_Error(
				'empty_css',
				__( 'Base CSS properties cannot be empty.', 'wyverncss' )
			);
		}

		$auto_scale   = $options['auto_scale'] ?? true;
		$mobile_first = $options['mobile_first'] ?? true;
		$include      = $options['include_breakpoints'] ?? array_keys( $this->breakpoints );

		$breakpoint_css = array();

		// Generate CSS for each breakpoint.
		foreach ( $this->breakpoints as $name => $config ) {
			if ( ! in_array( $name, $include, true ) ) {
				continue;
			}

			if ( $auto_scale ) {
				$breakpoint_css[ $name ] = $this->scale_css_for_breakpoint( $base_css, $name );
			} else {
				$breakpoint_css[ $name ] = $base_css;
			}
		}

		// Allow custom overrides per breakpoint.
		if ( isset( $options['overrides'] ) && is_array( $options['overrides'] ) ) {
			foreach ( $options['overrides'] as $breakpoint => $overrides ) {
				if ( isset( $breakpoint_css[ $breakpoint ] ) && is_array( $overrides ) ) {
					$breakpoint_css[ $breakpoint ] = array_merge(
						$breakpoint_css[ $breakpoint ],
						$this->sanitize_css( $overrides )
					);
				}
			}
		}

		// Generate CSS string.
		$css = $this->generate_css_string( $selector, $breakpoint_css, $mobile_first );

		return array(
			'css'         => $css,
			'breakpoints' => $breakpoint_css,
		);
	}

	/**
	 * Generate CSS for a single breakpoint with custom styles.
	 *
	 * @param string                $selector    CSS selector.
	 * @param string                $breakpoint  Breakpoint name.
	 * @param array<string, string> $css         CSS properties.
	 * @param bool                  $mobile_first Use min-width (true) or max-width (false).
	 * @return string Media query CSS.
	 */
	public function generate_breakpoint_css(
		string $selector,
		string $breakpoint,
		array $css,
		bool $mobile_first = true
	): string {
		if ( ! isset( $this->breakpoints[ $breakpoint ] ) ) {
			return '';
		}

		$config = $this->breakpoints[ $breakpoint ];
		$query  = $this->build_media_query( $config, $mobile_first );

		$css_string = $this->css_array_to_string( $css );

		if ( empty( $query ) ) {
			// No media query for base/mobile in mobile-first.
			return "{$selector} {\n{$css_string}}\n";
		}

		return "@media {$query} {\n  {$selector} {\n{$css_string}  }\n}\n";
	}

	/**
	 * Scale CSS values for a specific breakpoint.
	 *
	 * @param array<string, string> $css       Base CSS properties.
	 * @param string                $breakpoint Breakpoint name.
	 * @return array<string, string> Scaled CSS properties.
	 */
	private function scale_css_for_breakpoint( array $css, string $breakpoint ): array {
		$scaled = array();

		foreach ( $css as $property => $value ) {
			$scaled[ $property ] = $this->scale_value( $property, $value, $breakpoint );
		}

		return $scaled;
	}

	/**
	 * Scale a single CSS value based on property and breakpoint.
	 *
	 * @param string $property   CSS property name.
	 * @param string $value      CSS value.
	 * @param string $breakpoint Breakpoint name.
	 * @return string Scaled value.
	 */
	private function scale_value( string $property, string $value, string $breakpoint ): string {
		// Don't scale for desktop/large breakpoints.
		if ( in_array( $breakpoint, array( 'desktop', 'large' ), true ) ) {
			return $value;
		}

		// Check if property has scaling rules.
		if ( ! isset( self::PROPERTY_SCALING[ $property ] ) ) {
			return $value;
		}

		$scaling = self::PROPERTY_SCALING[ $property ];
		$scale   = 'mobile' === $breakpoint ? $scaling['scale_mobile'] : $scaling['scale_tablet'];

		// Handle different value formats.
		return $this->apply_scale_to_value( $value, $scale );
	}

	/**
	 * Apply scale factor to a CSS value.
	 *
	 * @param string $value CSS value.
	 * @param float  $scale Scale factor.
	 * @return string Scaled value.
	 */
	private function apply_scale_to_value( string $value, float $scale ): string {
		// Don't scale if scale is 1.0.
		if ( abs( $scale - 1.0 ) < 0.0001 ) {
			return $value;
		}

		// Handle numeric values with units (e.g., "16px", "1.5rem", "2em").
		if ( preg_match( '/^(-?\d+(?:\.\d+)?)(px|rem|em|%|vw|vh)$/', $value, $matches ) ) {
			$number = (float) $matches[1];
			$unit   = $matches[2];

			// Don't scale percentages, viewport units.
			if ( in_array( $unit, array( '%', 'vw', 'vh' ), true ) ) {
				return $value;
			}

			$scaled = round( $number * $scale, 2 );

			// Remove unnecessary decimals.
			if ( floor( $scaled ) === $scaled ) {
				$scaled = (int) $scaled;
			}

			return $scaled . $unit;
		}

		// Handle shorthand values (e.g., "10px 20px").
		if ( strpos( $value, ' ' ) !== false ) {
			$parts = preg_split( '/\s+/', $value );
			if ( is_array( $parts ) ) {
				$scaled_parts = array();
				foreach ( $parts as $part ) {
					$scaled_parts[] = $this->apply_scale_to_value( $part, $scale );
				}
				return implode( ' ', $scaled_parts );
			}
		}

		return $value;
	}

	/**
	 * Generate complete CSS string with media queries.
	 *
	 * @param string                               $selector      CSS selector.
	 * @param array<string, array<string, string>> $breakpoint_css CSS per breakpoint.
	 * @param bool                                 $mobile_first  Use mobile-first approach.
	 * @return string Complete CSS.
	 */
	private function generate_css_string(
		string $selector,
		array $breakpoint_css,
		bool $mobile_first
	): string {
		$output = '';

		if ( $mobile_first ) {
			// Mobile-first: Start with mobile, then progressively enhance.
			$order = array( 'mobile', 'tablet', 'desktop', 'large' );
		} else {
			// Desktop-first: Start with desktop, then progressively degrade.
			$order = array( 'large', 'desktop', 'tablet', 'mobile' );
		}

		foreach ( $order as $breakpoint ) {
			if ( ! isset( $breakpoint_css[ $breakpoint ] ) ) {
				continue;
			}

			$css    = $breakpoint_css[ $breakpoint ];
			$config = $this->breakpoints[ $breakpoint ];
			$query  = $this->build_media_query( $config, $mobile_first, $breakpoint );

			$css_string = $this->css_array_to_string( $css, '    ' );

			if ( empty( $query ) ) {
				// Base styles (no media query).
				$output .= "{$selector} {\n{$css_string}}\n\n";
			} else {
				$output .= "@media {$query} {\n  {$selector} {\n{$css_string}  }\n}\n\n";
			}
		}

		return trim( $output );
	}

	/**
	 * Build media query string.
	 *
	 * @param array{min: int, max: int|null, label: string} $config      Breakpoint config.
	 * @param bool                                          $mobile_first Use min-width approach.
	 * @param string                                        $breakpoint   Breakpoint name.
	 * @return string Media query (empty for base styles).
	 */
	private function build_media_query( array $config, bool $mobile_first, string $breakpoint = '' ): string {
		if ( $mobile_first ) {
			// Mobile-first: Use min-width.
			// Mobile (min: 0) is the base, no query needed.
			if ( 'mobile' === $breakpoint || 0 === $config['min'] ) {
				return '';
			}
			return "(min-width: {$config['min']}px)";
		}

		// Desktop-first: Use max-width.
		// Large (max: null) is the base, no query needed.
		if ( 'large' === $breakpoint || null === $config['max'] ) {
			return '';
		}
		return "(max-width: {$config['max']}px)";
	}

	/**
	 * Convert CSS array to string.
	 *
	 * @param array<string, string> $css    CSS properties.
	 * @param string                $indent Indentation.
	 * @return string CSS string.
	 */
	private function css_array_to_string( array $css, string $indent = '  ' ): string {
		$output = '';

		foreach ( $css as $property => $value ) {
			$output .= "{$indent}{$property}: {$value};\n";
		}

		return $output;
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

			// Skip dangerous values.
			if ( preg_match( '/expression|javascript|url\s*\(/i', $value ) ) {
				continue;
			}

			$sanitized[ $property ] = $value;
		}

		return $sanitized;
	}

	/**
	 * Get available breakpoints.
	 *
	 * @return array<string, array{min: int, max: int|null, label: string}> Breakpoints.
	 */
	public function get_breakpoints(): array {
		return $this->breakpoints;
	}

	/**
	 * Set custom breakpoints.
	 *
	 * @param array<string, array{min: int, max: int|null, label: string}> $breakpoints Custom breakpoints.
	 * @return void
	 */
	public function set_breakpoints( array $breakpoints ): void {
		$this->breakpoints = $breakpoints;
	}

	/**
	 * Add a custom breakpoint.
	 *
	 * @param string   $name  Breakpoint name.
	 * @param int      $min   Minimum width.
	 * @param int|null $max   Maximum width (null for no upper bound).
	 * @param string   $label Human-readable label.
	 * @return void
	 */
	public function add_breakpoint( string $name, int $min, ?int $max, string $label ): void {
		$this->breakpoints[ $name ] = array(
			'min'   => $min,
			'max'   => $max,
			'label' => $label,
		);
	}

	/**
	 * Generate responsive layout CSS.
	 *
	 * Specialized method for common responsive layout patterns.
	 *
	 * @param string               $selector CSS selector.
	 * @param string               $pattern  Layout pattern (stack, sidebar, grid).
	 * @param array<string, mixed> $options Pattern options.
	 * @return string|WP_Error Responsive CSS or error.
	 */
	public function generate_layout( string $selector, string $pattern, array $options = array() ) {
		switch ( $pattern ) {
			case 'stack':
				return $this->generate_stack_layout( $selector, $options );
			case 'sidebar':
				return $this->generate_sidebar_layout( $selector, $options );
			case 'grid':
				return $this->generate_grid_layout( $selector, $options );
			default:
				return new WP_Error(
					'invalid_pattern',
					__( 'Invalid layout pattern.', 'wyverncss' )
				);
		}
	}

	/**
	 * Generate stack layout CSS (mobile stacks, desktop row).
	 *
	 * @param string               $selector CSS selector.
	 * @param array<string, mixed> $options  Options.
	 * @return string CSS.
	 */
	private function generate_stack_layout( string $selector, array $options ): string {
		$gap = $options['gap'] ?? '1rem';

		return <<<CSS
{$selector} {
  display: flex;
  flex-direction: column;
  gap: {$gap};
}

@media (min-width: 768px) {
  {$selector} {
    flex-direction: row;
  }
}
CSS;
	}

	/**
	 * Generate sidebar layout CSS.
	 *
	 * @param string               $selector CSS selector.
	 * @param array<string, mixed> $options  Options.
	 * @return string CSS.
	 */
	private function generate_sidebar_layout( string $selector, array $options ): string {
		$sidebar_width = $options['sidebar_width'] ?? '300px';
		$gap           = $options['gap'] ?? '2rem';

		return <<<CSS
{$selector} {
  display: flex;
  flex-direction: column;
  gap: {$gap};
}

@media (min-width: 768px) {
  {$selector} {
    flex-direction: row;
  }

  {$selector} > :first-child {
    flex: 0 0 {$sidebar_width};
  }

  {$selector} > :last-child {
    flex: 1;
  }
}
CSS;
	}

	/**
	 * Generate responsive grid layout CSS.
	 *
	 * @param string               $selector CSS selector.
	 * @param array<string, mixed> $options  Options.
	 * @return string CSS.
	 */
	private function generate_grid_layout( string $selector, array $options ): string {
		$columns_mobile  = $options['columns_mobile'] ?? 1;
		$columns_tablet  = $options['columns_tablet'] ?? 2;
		$columns_desktop = $options['columns_desktop'] ?? 3;
		$gap             = $options['gap'] ?? '1rem';

		return <<<CSS
{$selector} {
  display: grid;
  grid-template-columns: repeat({$columns_mobile}, 1fr);
  gap: {$gap};
}

@media (min-width: 768px) {
  {$selector} {
    grid-template-columns: repeat({$columns_tablet}, 1fr);
  }
}

@media (min-width: 1024px) {
  {$selector} {
    grid-template-columns: repeat({$columns_desktop}, 1fr);
  }
}
CSS;
	}
}
