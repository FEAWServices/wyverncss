<?php
/**
 * Spacing Patterns
 *
 * Provides 20+ spacing-related CSS patterns for padding, margin,
 * and specific side spacing adjustments.
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
 * Spacing Patterns Class
 *
 * Contains a comprehensive library of spacing patterns including:
 * - Padding (all sides, specific sides, sizes)
 * - Margin (all sides, specific sides, sizes)
 * - Auto centering with margin
 *
 * @since 1.0.0
 */
class SpacingPatterns {

	/**
	 * Get all spacing patterns.
	 *
	 * Returns an associative array where keys are natural language prompts
	 * and values are CSS property arrays.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, string>> Spacing patterns.
	 */
	public static function get_patterns(): array {
		return array(
			// Padding - general.
			'add padding'        => array( 'padding' => '20px' ),
			'padding'            => array( 'padding' => '20px' ),
			'with padding'       => array( 'padding' => '20px' ),
			'give it padding'    => array( 'padding' => '20px' ),
			'small padding'      => array( 'padding' => '10px' ),
			'tiny padding'       => array( 'padding' => '5px' ),
			'medium padding'     => array( 'padding' => '20px' ),
			'large padding'      => array( 'padding' => '40px' ),
			'big padding'        => array( 'padding' => '40px' ),
			'huge padding'       => array( 'padding' => '60px' ),
			'no padding'         => array( 'padding' => '0' ),
			'remove padding'     => array( 'padding' => '0' ),

			// Padding - specific sides.
			'padding left'       => array( 'padding-left' => '20px' ),
			'left padding'       => array( 'padding-left' => '20px' ),
			'padding right'      => array( 'padding-right' => '20px' ),
			'right padding'      => array( 'padding-right' => '20px' ),
			'padding top'        => array( 'padding-top' => '20px' ),
			'top padding'        => array( 'padding-top' => '20px' ),
			'padding bottom'     => array( 'padding-bottom' => '20px' ),
			'bottom padding'     => array( 'padding-bottom' => '20px' ),

			// Margin - general.
			'add margin'         => array( 'margin' => '20px' ),
			'margin'             => array( 'margin' => '20px' ),
			'with margin'        => array( 'margin' => '20px' ),
			'give it margin'     => array( 'margin' => '20px' ),
			'small margin'       => array( 'margin' => '10px' ),
			'tiny margin'        => array( 'margin' => '5px' ),
			'medium margin'      => array( 'margin' => '20px' ),
			'large margin'       => array( 'margin' => '40px' ),
			'big margin'         => array( 'margin' => '40px' ),
			'huge margin'        => array( 'margin' => '60px' ),
			'no margin'          => array( 'margin' => '0' ),
			'remove margin'      => array( 'margin' => '0' ),

			// Margin - specific sides.
			'margin left'        => array( 'margin-left' => '20px' ),
			'left margin'        => array( 'margin-left' => '20px' ),
			'margin right'       => array( 'margin-right' => '20px' ),
			'right margin'       => array( 'margin-right' => '20px' ),
			'margin top'         => array( 'margin-top' => '20px' ),
			'top margin'         => array( 'margin-top' => '20px' ),
			'margin bottom'      => array( 'margin-bottom' => '20px' ),
			'bottom margin'      => array( 'margin-bottom' => '20px' ),

			// Margin auto (centering).
			'center with margin' => array( 'margin' => '0 auto' ),
			'margin auto'        => array( 'margin' => '0 auto' ),
			'auto margin'        => array( 'margin' => '0 auto' ),
			'horizontal center'  => array( 'margin' => '0 auto' ),

			// Vertical spacing.
			'space above'        => array( 'margin-top' => '20px' ),
			'space below'        => array( 'margin-bottom' => '20px' ),
			'space around'       => array( 'margin' => '20px' ),

			// Combined padding and margin.
			'padding and margin' => array(
				'padding' => '20px',
				'margin'  => '20px',
			),
			'add spacing'        => array(
				'padding' => '20px',
				'margin'  => '20px',
			),
		);
	}
}
