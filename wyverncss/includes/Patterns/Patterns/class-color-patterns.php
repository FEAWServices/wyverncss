<?php
/**
 * Color Patterns
 *
 * Provides 25+ color-related CSS patterns for text colors, backgrounds,
 * and WordPress-specific color schemes.
 *
 * @package WyvernCSS
 * @subpackage Patterns
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\Patterns\Patterns;

/**
 * Color Patterns Class
 *
 * Contains a comprehensive library of color patterns including:
 * - Basic colors (red, blue, green, etc.)
 * - Color intensities (light, dark)
 * - Background colors
 * - WordPress brand colors
 * - Status colors (success, warning, error)
 * - Text and background combinations
 *
 * @since 1.0.0
 */
class ColorPatterns {

	/**
	 * Get all color patterns.
	 *
	 * Returns an associative array where keys are natural language prompts
	 * and values are CSS property arrays.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, string>> Color patterns.
	 */
	public static function get_patterns(): array {
		return array(
			// Basic blue colors.
			'blue'                        => array( 'color' => '#0073aa' ),
			'make this blue'              => array( 'color' => '#0073aa' ),
			'blue text'                   => array( 'color' => '#0073aa' ),
			'color blue'                  => array( 'color' => '#0073aa' ),
			'light blue'                  => array( 'color' => '#00a0d2' ),
			'dark blue'                   => array( 'color' => '#005177' ),
			'bright blue'                 => array( 'color' => '#00b9eb' ),
			'navy blue'                   => array( 'color' => '#003050' ),

			// Basic red colors.
			'red'                         => array( 'color' => '#dc3232' ),
			'red text'                    => array( 'color' => '#dc3232' ),
			'make this red'               => array( 'color' => '#dc3232' ),
			'color red'                   => array( 'color' => '#dc3232' ),
			'light red'                   => array( 'color' => '#f56e28' ),
			'dark red'                    => array( 'color' => '#8a1b1b' ),
			'bright red'                  => array( 'color' => '#ff3333' ),

			// Basic green colors.
			'green'                       => array( 'color' => '#46b450' ),
			'green text'                  => array( 'color' => '#46b450' ),
			'make this green'             => array( 'color' => '#46b450' ),
			'color green'                 => array( 'color' => '#46b450' ),
			'light green'                 => array( 'color' => '#5fd35f' ),
			'dark green'                  => array( 'color' => '#2e7d32' ),

			// Basic yellow colors.
			'yellow'                      => array( 'color' => '#ffb900' ),
			'yellow text'                 => array( 'color' => '#ffb900' ),
			'make this yellow'            => array( 'color' => '#ffb900' ),
			'light yellow'                => array( 'color' => '#ffd700' ),
			'dark yellow'                 => array( 'color' => '#e6a800' ),

			// Basic orange colors.
			'orange'                      => array( 'color' => '#f56e28' ),
			'orange text'                 => array( 'color' => '#f56e28' ),
			'make this orange'            => array( 'color' => '#f56e28' ),
			'light orange'                => array( 'color' => '#ff8c3a' ),
			'dark orange'                 => array( 'color' => '#d85a1a' ),

			// Basic purple colors.
			'purple'                      => array( 'color' => '#826eb4' ),
			'purple text'                 => array( 'color' => '#826eb4' ),
			'make this purple'            => array( 'color' => '#826eb4' ),
			'light purple'                => array( 'color' => '#a88cd4' ),
			'dark purple'                 => array( 'color' => '#5e4d80' ),

			// Neutral colors.
			'black'                       => array( 'color' => '#000000' ),
			'black text'                  => array( 'color' => '#000000' ),
			'white'                       => array( 'color' => '#ffffff' ),
			'white text'                  => array( 'color' => '#ffffff' ),
			'gray'                        => array( 'color' => '#666666' ),
			'grey'                        => array( 'color' => '#666666' ),
			'gray text'                   => array( 'color' => '#666666' ),
			'grey text'                   => array( 'color' => '#666666' ),
			'light gray'                  => array( 'color' => '#cccccc' ),
			'light grey'                  => array( 'color' => '#cccccc' ),
			'dark gray'                   => array( 'color' => '#333333' ),
			'dark grey'                   => array( 'color' => '#333333' ),

			// Background colors - blue.
			'blue background'             => array( 'background-color' => '#0073aa' ),
			'light blue background'       => array( 'background-color' => '#00a0d2' ),
			'dark blue background'        => array( 'background-color' => '#005177' ),

			// Background colors - red.
			'red background'              => array( 'background-color' => '#dc3232' ),
			'light red background'        => array( 'background-color' => '#f56e28' ),
			'dark red background'         => array( 'background-color' => '#8a1b1b' ),

			// Background colors - green.
			'green background'            => array( 'background-color' => '#46b450' ),
			'light green background'      => array( 'background-color' => '#5fd35f' ),
			'dark green background'       => array( 'background-color' => '#2e7d32' ),

			// Background colors - yellow.
			'yellow background'           => array( 'background-color' => '#ffb900' ),
			'light yellow background'     => array( 'background-color' => '#ffd700' ),

			// Background colors - neutral.
			'white background'            => array( 'background-color' => '#ffffff' ),
			'black background'            => array( 'background-color' => '#000000' ),
			'gray background'             => array( 'background-color' => '#666666' ),
			'grey background'             => array( 'background-color' => '#666666' ),
			'light gray background'       => array( 'background-color' => '#f0f0f0' ),
			'light grey background'       => array( 'background-color' => '#f0f0f0' ),
			'dark gray background'        => array( 'background-color' => '#333333' ),
			'dark grey background'        => array( 'background-color' => '#333333' ),

			// WordPress specific colors.
			'wordpress blue'              => array( 'color' => '#0073aa' ),
			'wp blue'                     => array( 'color' => '#0073aa' ),
			'admin blue'                  => array( 'color' => '#0073aa' ),
			'admin red'                   => array( 'color' => '#dc3232' ),
			'admin green'                 => array( 'color' => '#46b450' ),
			'admin orange'                => array( 'color' => '#f56e28' ),

			// Status colors.
			'success'                     => array( 'color' => '#46b450' ),
			'success green'               => array( 'color' => '#46b450' ),
			'error'                       => array( 'color' => '#dc3232' ),
			'error red'                   => array( 'color' => '#dc3232' ),
			'warning'                     => array( 'color' => '#ffb900' ),
			'warning yellow'              => array( 'color' => '#ffb900' ),
			'info'                        => array( 'color' => '#00a0d2' ),
			'info blue'                   => array( 'color' => '#00a0d2' ),

			// Combined patterns.
			'blue with white background'  => array(
				'color'            => '#0073aa',
				'background-color' => '#ffffff',
			),
			'white with blue background'  => array(
				'color'            => '#ffffff',
				'background-color' => '#0073aa',
			),
			'black with white background' => array(
				'color'            => '#000000',
				'background-color' => '#ffffff',
			),
			'white with black background' => array(
				'color'            => '#ffffff',
				'background-color' => '#000000',
			),
			'red with white background'   => array(
				'color'            => '#dc3232',
				'background-color' => '#ffffff',
			),
			'green with white background' => array(
				'color'            => '#46b450',
				'background-color' => '#ffffff',
			),
		);
	}
}
