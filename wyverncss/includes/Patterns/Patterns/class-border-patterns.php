<?php
/**
 * Border Patterns
 *
 * Provides 20+ border-related CSS patterns for border styles, colors,
 * widths, and border radius.
 *
 * @package WyvernCSS
 * @subpackage Patterns
 */

declare(strict_types=1);

namespace WyvernCSS\Patterns\Patterns;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Border Patterns Class
 *
 * Contains a comprehensive library of border patterns including:
 * - Border styles (solid, dashed, dotted)
 * - Border colors
 * - Border widths (thin, medium, thick)
 * - Border radius (rounded corners)
 * - Specific side borders
 *
 * @since 1.0.0
 */
class BorderPatterns {

	/**
	 * Get all border patterns.
	 *
	 * Returns an associative array where keys are natural language prompts
	 * and values are CSS property arrays.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, string>> Border patterns.
	 */
	public static function get_patterns(): array {
		return array(
			// Basic borders.
			'add border'          => array( 'border' => '1px solid #cccccc' ),
			'border'              => array( 'border' => '1px solid #cccccc' ),
			'with border'         => array( 'border' => '1px solid #cccccc' ),
			'give it a border'    => array( 'border' => '1px solid #cccccc' ),
			'no border'           => array( 'border' => 'none' ),
			'remove border'       => array( 'border' => 'none' ),

			// Border styles.
			'solid border'        => array( 'border' => '1px solid #cccccc' ),
			'dashed border'       => array( 'border' => '1px dashed #cccccc' ),
			'dotted border'       => array( 'border' => '1px dotted #cccccc' ),
			'double border'       => array( 'border' => '3px double #cccccc' ),

			// Border widths.
			'thin border'         => array( 'border' => '1px solid #cccccc' ),
			'medium border'       => array( 'border' => '2px solid #cccccc' ),
			'thick border'        => array( 'border' => '4px solid #cccccc' ),
			'heavy border'        => array( 'border' => '5px solid #cccccc' ),

			// Border colors.
			'black border'        => array( 'border' => '1px solid #000000' ),
			'white border'        => array( 'border' => '1px solid #ffffff' ),
			'gray border'         => array( 'border' => '1px solid #666666' ),
			'grey border'         => array( 'border' => '1px solid #666666' ),
			'blue border'         => array( 'border' => '1px solid #0073aa' ),
			'red border'          => array( 'border' => '1px solid #dc3232' ),
			'green border'        => array( 'border' => '1px solid #46b450' ),
			'yellow border'       => array( 'border' => '1px solid #ffb900' ),
			'light gray border'   => array( 'border' => '1px solid #cccccc' ),
			'light grey border'   => array( 'border' => '1px solid #cccccc' ),
			'dark gray border'    => array( 'border' => '1px solid #333333' ),
			'dark grey border'    => array( 'border' => '1px solid #333333' ),

			// Border radius (rounded corners).
			'rounded corners'     => array( 'border-radius' => '5px' ),
			'rounded'             => array( 'border-radius' => '5px' ),
			'round corners'       => array( 'border-radius' => '5px' ),
			'slightly rounded'    => array( 'border-radius' => '3px' ),
			'very rounded'        => array( 'border-radius' => '10px' ),
			'extra rounded'       => array( 'border-radius' => '15px' ),
			'completely rounded'  => array( 'border-radius' => '50%' ),
			'circle'              => array( 'border-radius' => '50%' ),
			'pill shape'          => array( 'border-radius' => '50px' ),
			'no rounded corners'  => array( 'border-radius' => '0' ),
			'square corners'      => array( 'border-radius' => '0' ),

			// Specific side borders.
			'border top'          => array( 'border-top' => '1px solid #cccccc' ),
			'top border'          => array( 'border-top' => '1px solid #cccccc' ),
			'border bottom'       => array( 'border-bottom' => '1px solid #cccccc' ),
			'bottom border'       => array( 'border-bottom' => '1px solid #cccccc' ),
			'border left'         => array( 'border-left' => '1px solid #cccccc' ),
			'left border'         => array( 'border-left' => '1px solid #cccccc' ),
			'border right'        => array( 'border-right' => '1px solid #cccccc' ),
			'right border'        => array( 'border-right' => '1px solid #cccccc' ),

			// Combined patterns.
			'blue border rounded' => array(
				'border'        => '1px solid #0073aa',
				'border-radius' => '5px',
			),
			'thick red border'    => array( 'border' => '4px solid #dc3232' ),
			'thin gray border'    => array( 'border' => '1px solid #cccccc' ),
		);
	}
}
