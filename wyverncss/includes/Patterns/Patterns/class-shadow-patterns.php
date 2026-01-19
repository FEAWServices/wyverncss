<?php
/**
 * Shadow Patterns
 *
 * Provides 15+ shadow-related CSS patterns for box shadows and text shadows.
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
 * Shadow Patterns Class
 *
 * Contains a comprehensive library of shadow patterns including:
 * - Box shadows (various depths and styles)
 * - Text shadows
 * - Glow effects
 *
 * @since 1.0.0
 */
class ShadowPatterns {

	/**
	 * Get all shadow patterns.
	 *
	 * Returns an associative array where keys are natural language prompts
	 * and values are CSS property arrays.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, string>> Shadow patterns.
	 */
	public static function get_patterns(): array {
		return array(
			// Basic box shadows.
			'add shadow'         => array( 'box-shadow' => '0 2px 4px rgba(0,0,0,0.1)' ),
			'shadow'             => array( 'box-shadow' => '0 2px 4px rgba(0,0,0,0.1)' ),
			'with shadow'        => array( 'box-shadow' => '0 2px 4px rgba(0,0,0,0.1)' ),
			'give it shadow'     => array( 'box-shadow' => '0 2px 4px rgba(0,0,0,0.1)' ),
			'no shadow'          => array( 'box-shadow' => 'none' ),
			'remove shadow'      => array( 'box-shadow' => 'none' ),

			// Shadow intensities.
			'subtle shadow'      => array( 'box-shadow' => '0 1px 2px rgba(0,0,0,0.05)' ),
			'light shadow'       => array( 'box-shadow' => '0 1px 3px rgba(0,0,0,0.08)' ),
			'medium shadow'      => array( 'box-shadow' => '0 2px 4px rgba(0,0,0,0.1)' ),
			'heavy shadow'       => array( 'box-shadow' => '0 4px 8px rgba(0,0,0,0.15)' ),
			'deep shadow'        => array( 'box-shadow' => '0 8px 16px rgba(0,0,0,0.2)' ),
			'strong shadow'      => array( 'box-shadow' => '0 8px 16px rgba(0,0,0,0.2)' ),
			'very deep shadow'   => array( 'box-shadow' => '0 12px 24px rgba(0,0,0,0.25)' ),

			// Shadow directions.
			'drop shadow'        => array( 'box-shadow' => '0 4px 6px rgba(0,0,0,0.1)' ),
			'inner shadow'       => array( 'box-shadow' => 'inset 0 2px 4px rgba(0,0,0,0.1)' ),
			'inset shadow'       => array( 'box-shadow' => 'inset 0 2px 4px rgba(0,0,0,0.1)' ),

			// Elevated/raised effects.
			'elevated'           => array( 'box-shadow' => '0 4px 12px rgba(0,0,0,0.15)' ),
			'raised'             => array( 'box-shadow' => '0 4px 12px rgba(0,0,0,0.15)' ),
			'floating'           => array( 'box-shadow' => '0 8px 24px rgba(0,0,0,0.15)' ),

			// Text shadows.
			'text shadow'        => array( 'text-shadow' => '1px 1px 2px rgba(0,0,0,0.2)' ),
			'text drop shadow'   => array( 'text-shadow' => '2px 2px 4px rgba(0,0,0,0.3)' ),
			'subtle text shadow' => array( 'text-shadow' => '1px 1px 1px rgba(0,0,0,0.1)' ),
			'heavy text shadow'  => array( 'text-shadow' => '2px 2px 6px rgba(0,0,0,0.4)' ),

			// Glow effects.
			'glow'               => array( 'box-shadow' => '0 0 10px rgba(0,123,255,0.5)' ),
			'glow effect'        => array( 'box-shadow' => '0 0 10px rgba(0,123,255,0.5)' ),
			'text glow'          => array( 'text-shadow' => '0 0 8px rgba(0,123,255,0.8)' ),
			'blue glow'          => array( 'box-shadow' => '0 0 15px rgba(0,115,170,0.6)' ),
			'green glow'         => array( 'box-shadow' => '0 0 15px rgba(70,180,80,0.6)' ),
			'red glow'           => array( 'box-shadow' => '0 0 15px rgba(220,50,50,0.6)' ),

			// Combined shadows.
			'layered shadow'     => array( 'box-shadow' => '0 2px 4px rgba(0,0,0,0.1), 0 8px 16px rgba(0,0,0,0.1)' ),
			'complex shadow'     => array( 'box-shadow' => '0 2px 4px rgba(0,0,0,0.1), 0 8px 16px rgba(0,0,0,0.1)' ),
		);
	}
}
