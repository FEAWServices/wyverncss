<?php
/**
 * Layout Patterns
 *
 * Provides 20+ layout-related CSS patterns for display, positioning,
 * flexbox, and dimensions.
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
 * Layout Patterns Class
 *
 * Contains a comprehensive library of layout patterns including:
 * - Display properties (block, inline, flex, grid)
 * - Positioning (relative, absolute, fixed)
 * - Flexbox patterns
 * - Width and height
 * - Visibility and overflow
 *
 * @since 1.0.0
 */
class LayoutPatterns {

	/**
	 * Get all layout patterns.
	 *
	 * Returns an associative array where keys are natural language prompts
	 * and values are CSS property arrays.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, string>> Layout patterns.
	 */
	public static function get_patterns(): array {
		return array(
			// Display properties.
			'hide this'         => array( 'display' => 'none' ),
			'hide'              => array( 'display' => 'none' ),
			'hidden'            => array( 'display' => 'none' ),
			'show this'         => array( 'display' => 'block' ),
			'show'              => array( 'display' => 'block' ),
			'visible'           => array( 'display' => 'block' ),
			'block'             => array( 'display' => 'block' ),
			'inline'            => array( 'display' => 'inline' ),
			'inline block'      => array( 'display' => 'inline-block' ),
			'flex'              => array( 'display' => 'flex' ),
			'flexbox'           => array( 'display' => 'flex' ),
			'grid'              => array( 'display' => 'grid' ),

			// Flexbox patterns.
			'center with flex'  => array(
				'display'         => 'flex',
				'justify-content' => 'center',
				'align-items'     => 'center',
			),
			'flex center'       => array(
				'display'         => 'flex',
				'justify-content' => 'center',
				'align-items'     => 'center',
			),
			'space between'     => array(
				'display'         => 'flex',
				'justify-content' => 'space-between',
			),
			'space around'      => array(
				'display'         => 'flex',
				'justify-content' => 'space-around',
			),
			'flex row'          => array(
				'display'        => 'flex',
				'flex-direction' => 'row',
			),
			'flex column'       => array(
				'display'        => 'flex',
				'flex-direction' => 'column',
			),
			'vertical center'   => array(
				'display'     => 'flex',
				'align-items' => 'center',
			),
			'horizontal center' => array(
				'display'         => 'flex',
				'justify-content' => 'center',
			),

			// Positioning.
			'absolute'          => array( 'position' => 'absolute' ),
			'absolute position' => array( 'position' => 'absolute' ),
			'relative'          => array( 'position' => 'relative' ),
			'relative position' => array( 'position' => 'relative' ),
			'fixed'             => array( 'position' => 'fixed' ),
			'fixed position'    => array( 'position' => 'fixed' ),
			'sticky'            => array( 'position' => 'sticky' ),
			'sticky position'   => array( 'position' => 'sticky' ),
			'static'            => array( 'position' => 'static' ),

			// Width and height.
			'full width'        => array( 'width' => '100%' ),
			'100% width'        => array( 'width' => '100%' ),
			'full height'       => array( 'height' => '100%' ),
			'100% height'       => array( 'height' => '100%' ),
			'half width'        => array( 'width' => '50%' ),
			'50% width'         => array( 'width' => '50%' ),
			'auto width'        => array( 'width' => 'auto' ),
			'auto height'       => array( 'height' => 'auto' ),

			// Overflow.
			'scrollable'        => array( 'overflow' => 'auto' ),
			'scroll'            => array( 'overflow' => 'auto' ),
			'hide overflow'     => array( 'overflow' => 'hidden' ),
			'overflow hidden'   => array( 'overflow' => 'hidden' ),
			'overflow visible'  => array( 'overflow' => 'visible' ),

			// Float (legacy but still used).
			'float left'        => array( 'float' => 'left' ),
			'float right'       => array( 'float' => 'right' ),
			'no float'          => array( 'float' => 'none' ),
			'clear float'       => array( 'clear' => 'both' ),

			// Z-index.
			'bring to front'    => array( 'z-index' => '100' ),
			'send to back'      => array( 'z-index' => '1' ),
			'on top'            => array( 'z-index' => '999' ),
		);
	}
}
