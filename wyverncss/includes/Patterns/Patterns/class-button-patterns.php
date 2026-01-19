<?php
/**
 * Button Style Patterns
 *
 * Common button styling patterns for rapid generation.
 *
 * @package WyvernCSS
 * @subpackage Patterns
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\Patterns\Patterns;

/**
 * Button Patterns Class
 *
 * Provides pre-built button styles for instant application.
 */
class ButtonPatterns {

	/**
	 * Get all button patterns
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_patterns(): array {
		return array(
			// Basic button styles.
			'button'             => array(
				'padding'         => '10px 20px',
				'border-radius'   => '4px',
				'border'          => 'none',
				'cursor'          => 'pointer',
				'display'         => 'inline-block',
				'text-align'      => 'center',
				'text-decoration' => 'none',
				'transition'      => 'all 0.3s ease',
			),
			'make this a button' => array(
				'padding'         => '10px 20px',
				'border-radius'   => '4px',
				'border'          => 'none',
				'cursor'          => 'pointer',
				'display'         => 'inline-block',
				'text-align'      => 'center',
				'text-decoration' => 'none',
			),

			// Blue buttons.
			'blue button'        => array(
				'background-color' => '#0073aa',
				'color'            => '#ffffff',
				'padding'          => '10px 20px',
				'border-radius'    => '4px',
				'border'           => 'none',
				'cursor'           => 'pointer',
				'display'          => 'inline-block',
				'text-align'       => 'center',
				'font-weight'      => '500',
			),
			'primary button'     => array(
				'background-color' => '#0073aa',
				'color'            => '#ffffff',
				'padding'          => '12px 24px',
				'border-radius'    => '4px',
				'border'           => 'none',
				'cursor'           => 'pointer',
				'font-weight'      => '600',
			),

			// Red buttons.
			'red button'         => array(
				'background-color' => '#dc3232',
				'color'            => '#ffffff',
				'padding'          => '10px 20px',
				'border-radius'    => '4px',
				'border'           => 'none',
				'cursor'           => 'pointer',
			),
			'danger button'      => array(
				'background-color' => '#dc3232',
				'color'            => '#ffffff',
				'padding'          => '10px 20px',
				'border-radius'    => '4px',
				'border'           => 'none',
			),
			'delete button'      => array(
				'background-color' => '#a00',
				'color'            => '#ffffff',
				'padding'          => '8px 16px',
				'border-radius'    => '4px',
			),

			// Green buttons.
			'green button'       => array(
				'background-color' => '#46b450',
				'color'            => '#ffffff',
				'padding'          => '10px 20px',
				'border-radius'    => '4px',
				'border'           => 'none',
				'cursor'           => 'pointer',
			),
			'success button'     => array(
				'background-color' => '#46b450',
				'color'            => '#ffffff',
				'padding'          => '10px 20px',
				'border-radius'    => '4px',
			),

			// Secondary/neutral buttons.
			'secondary button'   => array(
				'background-color' => '#f0f0f0',
				'color'            => '#333333',
				'padding'          => '10px 20px',
				'border-radius'    => '4px',
				'border'           => '1px solid #ddd',
				'cursor'           => 'pointer',
			),
			'gray button'        => array(
				'background-color' => '#666666',
				'color'            => '#ffffff',
				'padding'          => '10px 20px',
				'border-radius'    => '4px',
			),
			'grey button'        => array(
				'background-color' => '#666666',
				'color'            => '#ffffff',
				'padding'          => '10px 20px',
				'border-radius'    => '4px',
			),

			// Button sizes.
			'large button'       => array(
				'padding'       => '16px 32px',
				'font-size'     => '18px',
				'border-radius' => '6px',
			),
			'small button'       => array(
				'padding'       => '6px 12px',
				'font-size'     => '12px',
				'border-radius' => '3px',
			),

			// Button shapes.
			'rounded button'     => array(
				'border-radius' => '50px',
				'padding'       => '10px 24px',
			),
			'square button'      => array(
				'border-radius' => '0',
				'padding'       => '10px 20px',
			),
			'pill button'        => array(
				'border-radius' => '100px',
				'padding'       => '12px 32px',
			),

			// Button states and effects.
			'button with shadow' => array(
				'box-shadow'    => '0 2px 4px rgba(0,0,0,0.2)',
				'padding'       => '10px 20px',
				'border-radius' => '4px',
			),
			'flat button'        => array(
				'box-shadow'       => 'none',
				'border'           => 'none',
				'background-color' => '#f0f0f0',
				'padding'          => '10px 20px',
			),
			'outlined button'    => array(
				'background-color' => 'transparent',
				'border'           => '2px solid #0073aa',
				'color'            => '#0073aa',
				'padding'          => '10px 20px',
				'border-radius'    => '4px',
			),
			'ghost button'       => array(
				'background-color' => 'transparent',
				'border'           => '1px solid currentColor',
				'padding'          => '10px 20px',
				'border-radius'    => '4px',
			),
		);
	}
}
