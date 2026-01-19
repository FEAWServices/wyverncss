<?php
/**
 * Typography Patterns
 *
 * Provides 25+ typography-related CSS patterns for font sizes, weights,
 * alignment, decoration, and text transformations.
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
 * Typography Patterns Class
 *
 * Contains a comprehensive library of typography patterns including:
 * - Font sizes (small, medium, large, etc.)
 * - Font weights (bold, light, normal)
 * - Text alignment (left, center, right, justify)
 * - Text decoration (underline, strikethrough)
 * - Text transform (uppercase, lowercase, capitalize)
 * - Line height and letter spacing
 *
 * @since 1.0.0
 */
class TypographyPatterns {

	/**
	 * Get all typography patterns.
	 *
	 * Returns an associative array where keys are natural language prompts
	 * and values are CSS property arrays.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, string>> Typography patterns.
	 */
	public static function get_patterns(): array {
		return array(
			// Font sizes.
			'small text'            => array( 'font-size' => '12px' ),
			'small font'            => array( 'font-size' => '12px' ),
			'make this small'       => array( 'font-size' => '12px' ),
			'tiny text'             => array( 'font-size' => '10px' ),
			'tiny font'             => array( 'font-size' => '10px' ),
			'normal text'           => array( 'font-size' => '16px' ),
			'normal font'           => array( 'font-size' => '16px' ),
			'medium text'           => array( 'font-size' => '18px' ),
			'medium font'           => array( 'font-size' => '18px' ),
			'large text'            => array( 'font-size' => '24px' ),
			'large font'            => array( 'font-size' => '24px' ),
			'make this large'       => array( 'font-size' => '24px' ),
			'big text'              => array( 'font-size' => '24px' ),
			'big font'              => array( 'font-size' => '24px' ),
			'huge text'             => array( 'font-size' => '36px' ),
			'huge font'             => array( 'font-size' => '36px' ),
			'very large text'       => array( 'font-size' => '32px' ),
			'very large font'       => array( 'font-size' => '32px' ),
			'extra large text'      => array( 'font-size' => '32px' ),
			'extra large font'      => array( 'font-size' => '32px' ),

			// Font weights.
			'bold'                  => array( 'font-weight' => 'bold' ),
			'bold text'             => array( 'font-weight' => 'bold' ),
			'make this bold'        => array( 'font-weight' => 'bold' ),
			'make it bold'          => array( 'font-weight' => 'bold' ),
			'heavy text'            => array( 'font-weight' => '700' ),
			'extra bold'            => array( 'font-weight' => '800' ),
			'very bold'             => array( 'font-weight' => '800' ),
			'light text'            => array( 'font-weight' => '300' ),
			'light font'            => array( 'font-weight' => '300' ),
			'thin text'             => array( 'font-weight' => '200' ),
			'thin font'             => array( 'font-weight' => '200' ),
			'normal weight'         => array( 'font-weight' => 'normal' ),
			'regular weight'        => array( 'font-weight' => 'normal' ),

			// Text alignment.
			'center this'           => array( 'text-align' => 'center' ),
			'center text'           => array( 'text-align' => 'center' ),
			'center align'          => array( 'text-align' => 'center' ),
			'align center'          => array( 'text-align' => 'center' ),
			'centered'              => array( 'text-align' => 'center' ),
			'left align'            => array( 'text-align' => 'left' ),
			'align left'            => array( 'text-align' => 'left' ),
			'left text'             => array( 'text-align' => 'left' ),
			'right align'           => array( 'text-align' => 'right' ),
			'align right'           => array( 'text-align' => 'right' ),
			'right text'            => array( 'text-align' => 'right' ),
			'justify'               => array( 'text-align' => 'justify' ),
			'justify text'          => array( 'text-align' => 'justify' ),
			'justified'             => array( 'text-align' => 'justify' ),

			// Text decoration.
			'underline'             => array( 'text-decoration' => 'underline' ),
			'underline this'        => array( 'text-decoration' => 'underline' ),
			'underline text'        => array( 'text-decoration' => 'underline' ),
			'strikethrough'         => array( 'text-decoration' => 'line-through' ),
			'strike through'        => array( 'text-decoration' => 'line-through' ),
			'cross out'             => array( 'text-decoration' => 'line-through' ),
			'line through'          => array( 'text-decoration' => 'line-through' ),
			'no underline'          => array( 'text-decoration' => 'none' ),
			'remove underline'      => array( 'text-decoration' => 'none' ),
			'no decoration'         => array( 'text-decoration' => 'none' ),

			// Text transform.
			'uppercase'             => array( 'text-transform' => 'uppercase' ),
			'all caps'              => array( 'text-transform' => 'uppercase' ),
			'make uppercase'        => array( 'text-transform' => 'uppercase' ),
			'capitalize'            => array( 'text-transform' => 'capitalize' ),
			'capitalize this'       => array( 'text-transform' => 'capitalize' ),
			'title case'            => array( 'text-transform' => 'capitalize' ),
			'lowercase'             => array( 'text-transform' => 'lowercase' ),
			'make lowercase'        => array( 'text-transform' => 'lowercase' ),
			'all lowercase'         => array( 'text-transform' => 'lowercase' ),

			// Line height.
			'tight lines'           => array( 'line-height' => '1.2' ),
			'tight spacing'         => array( 'line-height' => '1.2' ),
			'normal line height'    => array( 'line-height' => '1.5' ),
			'normal spacing'        => array( 'line-height' => '1.5' ),
			'loose lines'           => array( 'line-height' => '2' ),
			'loose spacing'         => array( 'line-height' => '2' ),
			'double spaced'         => array( 'line-height' => '2' ),
			'single spaced'         => array( 'line-height' => '1' ),

			// Letter spacing.
			'spread out'            => array( 'letter-spacing' => '2px' ),
			'wide letters'          => array( 'letter-spacing' => '2px' ),
			'spaced out'            => array( 'letter-spacing' => '2px' ),
			'tight letters'         => array( 'letter-spacing' => '-0.5px' ),
			'condensed'             => array( 'letter-spacing' => '-0.5px' ),
			'normal letter spacing' => array( 'letter-spacing' => 'normal' ),

			// Font style.
			'italic'                => array( 'font-style' => 'italic' ),
			'italicize'             => array( 'font-style' => 'italic' ),
			'make italic'           => array( 'font-style' => 'italic' ),
			'slanted'               => array( 'font-style' => 'italic' ),
			'not italic'            => array( 'font-style' => 'normal' ),
			'remove italic'         => array( 'font-style' => 'normal' ),
		);
	}
}
