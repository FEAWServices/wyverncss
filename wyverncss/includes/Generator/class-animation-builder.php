<?php
/**
 * Animation Builder - Generate CSS keyframe animations from natural language.
 *
 * @package WyvernCSS
 * @since 1.1.0
 */

declare(strict_types=1);

namespace WyvernCSS\Generator;

/**
 * Builds CSS keyframe animations from natural language descriptions.
 */
class Animation_Builder {

	/**
	 * Animation presets library.
	 *
	 * @var array<string, array{keyframes: array<int|string, array<string, string>>, duration: string, timing: string, iteration: string}>
	 */
	private const PRESETS = array(
		'fade-in'        => array(
			'keyframes' => array(
				'from' => array( 'opacity' => '0' ),
				'to'   => array( 'opacity' => '1' ),
			),
			'duration'  => '0.3s',
			'timing'    => 'ease-out',
			'iteration' => '1',
		),
		'fade-out'       => array(
			'keyframes' => array(
				'from' => array( 'opacity' => '1' ),
				'to'   => array( 'opacity' => '0' ),
			),
			'duration'  => '0.3s',
			'timing'    => 'ease-in',
			'iteration' => '1',
		),
		'slide-in-left'  => array(
			'keyframes' => array(
				'from' => array(
					'transform' => 'translateX(-100%)',
					'opacity'   => '0',
				),
				'to'   => array(
					'transform' => 'translateX(0)',
					'opacity'   => '1',
				),
			),
			'duration'  => '0.4s',
			'timing'    => 'ease-out',
			'iteration' => '1',
		),
		'slide-in-right' => array(
			'keyframes' => array(
				'from' => array(
					'transform' => 'translateX(100%)',
					'opacity'   => '0',
				),
				'to'   => array(
					'transform' => 'translateX(0)',
					'opacity'   => '1',
				),
			),
			'duration'  => '0.4s',
			'timing'    => 'ease-out',
			'iteration' => '1',
		),
		'slide-in-up'    => array(
			'keyframes' => array(
				'from' => array(
					'transform' => 'translateY(100%)',
					'opacity'   => '0',
				),
				'to'   => array(
					'transform' => 'translateY(0)',
					'opacity'   => '1',
				),
			),
			'duration'  => '0.4s',
			'timing'    => 'ease-out',
			'iteration' => '1',
		),
		'slide-in-down'  => array(
			'keyframes' => array(
				'from' => array(
					'transform' => 'translateY(-100%)',
					'opacity'   => '0',
				),
				'to'   => array(
					'transform' => 'translateY(0)',
					'opacity'   => '1',
				),
			),
			'duration'  => '0.4s',
			'timing'    => 'ease-out',
			'iteration' => '1',
		),
		'bounce'         => array(
			'keyframes' => array(
				'0%'   => array( 'transform' => 'translateY(0)' ),
				'20%'  => array( 'transform' => 'translateY(0)' ),
				'40%'  => array( 'transform' => 'translateY(-30px)' ),
				'50%'  => array( 'transform' => 'translateY(0)' ),
				'60%'  => array( 'transform' => 'translateY(-15px)' ),
				'80%'  => array( 'transform' => 'translateY(0)' ),
				'100%' => array( 'transform' => 'translateY(0)' ),
			),
			'duration'  => '1s',
			'timing'    => 'ease-in-out',
			'iteration' => 'infinite',
		),
		'pulse'          => array(
			'keyframes' => array(
				'0%'   => array( 'transform' => 'scale(1)' ),
				'50%'  => array( 'transform' => 'scale(1.05)' ),
				'100%' => array( 'transform' => 'scale(1)' ),
			),
			'duration'  => '1s',
			'timing'    => 'ease-in-out',
			'iteration' => 'infinite',
		),
		'shake'          => array(
			'keyframes' => array(
				'0%'   => array( 'transform' => 'translateX(0)' ),
				'10%'  => array( 'transform' => 'translateX(-10px)' ),
				'20%'  => array( 'transform' => 'translateX(10px)' ),
				'30%'  => array( 'transform' => 'translateX(-10px)' ),
				'40%'  => array( 'transform' => 'translateX(10px)' ),
				'50%'  => array( 'transform' => 'translateX(-10px)' ),
				'60%'  => array( 'transform' => 'translateX(10px)' ),
				'70%'  => array( 'transform' => 'translateX(-10px)' ),
				'80%'  => array( 'transform' => 'translateX(10px)' ),
				'90%'  => array( 'transform' => 'translateX(-10px)' ),
				'100%' => array( 'transform' => 'translateX(0)' ),
			),
			'duration'  => '0.5s',
			'timing'    => 'ease-in-out',
			'iteration' => '1',
		),
		'spin'           => array(
			'keyframes' => array(
				'from' => array( 'transform' => 'rotate(0deg)' ),
				'to'   => array( 'transform' => 'rotate(360deg)' ),
			),
			'duration'  => '1s',
			'timing'    => 'linear',
			'iteration' => 'infinite',
		),
		'flip'           => array(
			'keyframes' => array(
				'0%'   => array( 'transform' => 'perspective(400px) rotateY(0)' ),
				'40%'  => array( 'transform' => 'perspective(400px) rotateY(170deg)' ),
				'50%'  => array( 'transform' => 'perspective(400px) rotateY(190deg)' ),
				'80%'  => array( 'transform' => 'perspective(400px) rotateY(360deg)' ),
				'100%' => array( 'transform' => 'perspective(400px) rotateY(360deg)' ),
			),
			'duration'  => '0.8s',
			'timing'    => 'ease-in-out',
			'iteration' => '1',
		),
		'zoom-in'        => array(
			'keyframes' => array(
				'from' => array(
					'transform' => 'scale(0)',
					'opacity'   => '0',
				),
				'to'   => array(
					'transform' => 'scale(1)',
					'opacity'   => '1',
				),
			),
			'duration'  => '0.3s',
			'timing'    => 'ease-out',
			'iteration' => '1',
		),
		'zoom-out'       => array(
			'keyframes' => array(
				'from' => array(
					'transform' => 'scale(1)',
					'opacity'   => '1',
				),
				'to'   => array(
					'transform' => 'scale(0)',
					'opacity'   => '0',
				),
			),
			'duration'  => '0.3s',
			'timing'    => 'ease-in',
			'iteration' => '1',
		),
		'swing'          => array(
			'keyframes' => array(
				'20%'  => array( 'transform' => 'rotate(15deg)' ),
				'40%'  => array( 'transform' => 'rotate(-10deg)' ),
				'60%'  => array( 'transform' => 'rotate(5deg)' ),
				'80%'  => array( 'transform' => 'rotate(-5deg)' ),
				'100%' => array( 'transform' => 'rotate(0deg)' ),
			),
			'duration'  => '0.8s',
			'timing'    => 'ease-in-out',
			'iteration' => '1',
		),
		'wobble'         => array(
			'keyframes' => array(
				'0%'   => array( 'transform' => 'translateX(0%)' ),
				'15%'  => array( 'transform' => 'translateX(-25%) rotate(-5deg)' ),
				'30%'  => array( 'transform' => 'translateX(20%) rotate(3deg)' ),
				'45%'  => array( 'transform' => 'translateX(-15%) rotate(-3deg)' ),
				'60%'  => array( 'transform' => 'translateX(10%) rotate(2deg)' ),
				'75%'  => array( 'transform' => 'translateX(-5%) rotate(-1deg)' ),
				'100%' => array( 'transform' => 'translateX(0%)' ),
			),
			'duration'  => '0.8s',
			'timing'    => 'ease-in-out',
			'iteration' => '1',
		),
		'heartbeat'      => array(
			'keyframes' => array(
				'0%'   => array( 'transform' => 'scale(1)' ),
				'14%'  => array( 'transform' => 'scale(1.3)' ),
				'28%'  => array( 'transform' => 'scale(1)' ),
				'42%'  => array( 'transform' => 'scale(1.3)' ),
				'70%'  => array( 'transform' => 'scale(1)' ),
				'100%' => array( 'transform' => 'scale(1)' ),
			),
			'duration'  => '1.5s',
			'timing'    => 'ease-in-out',
			'iteration' => 'infinite',
		),
		'flash'          => array(
			'keyframes' => array(
				'0%'   => array( 'opacity' => '1' ),
				'25%'  => array( 'opacity' => '0' ),
				'50%'  => array( 'opacity' => '1' ),
				'75%'  => array( 'opacity' => '0' ),
				'100%' => array( 'opacity' => '1' ),
			),
			'duration'  => '1s',
			'timing'    => 'ease-in-out',
			'iteration' => '1',
		),
		'rubber-band'    => array(
			'keyframes' => array(
				'0%'   => array( 'transform' => 'scaleX(1)' ),
				'30%'  => array( 'transform' => 'scaleX(1.25) scaleY(0.75)' ),
				'40%'  => array( 'transform' => 'scaleX(0.75) scaleY(1.25)' ),
				'50%'  => array( 'transform' => 'scaleX(1.15) scaleY(0.85)' ),
				'65%'  => array( 'transform' => 'scaleX(0.95) scaleY(1.05)' ),
				'75%'  => array( 'transform' => 'scaleX(1.05) scaleY(0.95)' ),
				'100%' => array( 'transform' => 'scaleX(1)' ),
			),
			'duration'  => '1s',
			'timing'    => 'ease-in-out',
			'iteration' => '1',
		),
		'jello'          => array(
			'keyframes' => array(
				'0%'    => array( 'transform' => 'skewX(0deg) skewY(0deg)' ),
				'11.1%' => array( 'transform' => 'skewX(-12.5deg) skewY(-12.5deg)' ),
				'22.2%' => array( 'transform' => 'skewX(6.25deg) skewY(6.25deg)' ),
				'33.3%' => array( 'transform' => 'skewX(-3.125deg) skewY(-3.125deg)' ),
				'44.4%' => array( 'transform' => 'skewX(1.5625deg) skewY(1.5625deg)' ),
				'55.5%' => array( 'transform' => 'skewX(-0.78125deg) skewY(-0.78125deg)' ),
				'66.6%' => array( 'transform' => 'skewX(0.390625deg) skewY(0.390625deg)' ),
				'77.7%' => array( 'transform' => 'skewX(-0.1953125deg) skewY(-0.1953125deg)' ),
				'100%'  => array( 'transform' => 'skewX(0deg) skewY(0deg)' ),
			),
			'duration'  => '0.9s',
			'timing'    => 'ease-in-out',
			'iteration' => '1',
		),
		'float'          => array(
			'keyframes' => array(
				'0%'   => array( 'transform' => 'translateY(0px)' ),
				'50%'  => array( 'transform' => 'translateY(-20px)' ),
				'100%' => array( 'transform' => 'translateY(0px)' ),
			),
			'duration'  => '3s',
			'timing'    => 'ease-in-out',
			'iteration' => 'infinite',
		),
		'glow'           => array(
			'keyframes' => array(
				'0%'   => array( 'box-shadow' => '0 0 5px rgba(255,255,255,0.5)' ),
				'50%'  => array( 'box-shadow' => '0 0 20px rgba(255,255,255,0.8), 0 0 30px rgba(255,255,255,0.6)' ),
				'100%' => array( 'box-shadow' => '0 0 5px rgba(255,255,255,0.5)' ),
			),
			'duration'  => '2s',
			'timing'    => 'ease-in-out',
			'iteration' => 'infinite',
		),
		'tada'           => array(
			'keyframes' => array(
				'0%'   => array( 'transform' => 'scale(1) rotate(0deg)' ),
				'10%'  => array( 'transform' => 'scale(0.9) rotate(-3deg)' ),
				'20%'  => array( 'transform' => 'scale(0.9) rotate(-3deg)' ),
				'30%'  => array( 'transform' => 'scale(1.1) rotate(3deg)' ),
				'40%'  => array( 'transform' => 'scale(1.1) rotate(-3deg)' ),
				'50%'  => array( 'transform' => 'scale(1.1) rotate(3deg)' ),
				'60%'  => array( 'transform' => 'scale(1.1) rotate(-3deg)' ),
				'70%'  => array( 'transform' => 'scale(1.1) rotate(3deg)' ),
				'80%'  => array( 'transform' => 'scale(1.1) rotate(-3deg)' ),
				'90%'  => array( 'transform' => 'scale(1.1) rotate(3deg)' ),
				'100%' => array( 'transform' => 'scale(1) rotate(0deg)' ),
			),
			'duration'  => '1s',
			'timing'    => 'ease-in-out',
			'iteration' => '1',
		),
	);

	/**
	 * Keyword to preset mapping.
	 *
	 * @var array<string, string>
	 */
	private const KEYWORD_MAP = array(
		// Fade.
		'fade'       => 'fade-in',
		'appear'     => 'fade-in',
		'show'       => 'fade-in',
		'disappear'  => 'fade-out',
		'hide'       => 'fade-out',
		'vanish'     => 'fade-out',
		// Slide.
		'slide'      => 'slide-in-left',
		'enter'      => 'slide-in-left',
		// Bounce.
		'bounce'     => 'bounce',
		'bouncy'     => 'bounce',
		'jump'       => 'bounce',
		'hop'        => 'bounce',
		// Pulse.
		'pulse'      => 'pulse',
		'pulsate'    => 'pulse',
		'throb'      => 'pulse',
		'breathe'    => 'pulse',
		// Shake.
		'shake'      => 'shake',
		'shaking'    => 'shake',
		'vibrate'    => 'shake',
		'tremble'    => 'shake',
		// Spin.
		'spin'       => 'spin',
		'rotate'     => 'spin',
		'turn'       => 'spin',
		'twirl'      => 'spin',
		// Flip.
		'flip'       => 'flip',
		'flipping'   => 'flip',
		// Zoom.
		'zoom'       => 'zoom-in',
		'grow'       => 'zoom-in',
		'expand'     => 'zoom-in',
		'shrink'     => 'zoom-out',
		'contract'   => 'zoom-out',
		// Swing.
		'swing'      => 'swing',
		'sway'       => 'swing',
		'rock'       => 'swing',
		// Wobble.
		'wobble'     => 'wobble',
		'wiggle'     => 'wobble',
		// Heartbeat.
		'heartbeat'  => 'heartbeat',
		'heart'      => 'heartbeat',
		'beat'       => 'heartbeat',
		// Flash.
		'flash'      => 'flash',
		'blink'      => 'flash',
		'flicker'    => 'flash',
		// Rubber band.
		'rubber'     => 'rubber-band',
		'elastic'    => 'rubber-band',
		'stretch'    => 'rubber-band',
		// Jello.
		'jello'      => 'jello',
		'jelly'      => 'jello',
		'jiggly'     => 'jello',
		// Float.
		'float'      => 'float',
		'hover'      => 'float',
		'levitate'   => 'float',
		// Glow.
		'glow'       => 'glow',
		'shine'      => 'glow',
		'radiate'    => 'glow',
		// Tada.
		'tada'       => 'tada',
		'celebrate'  => 'tada',
		'attention'  => 'tada',
		'excitement' => 'tada',
	);

	/**
	 * Direction modifiers.
	 *
	 * @var array<string, string>
	 */
	private const DIRECTION_MAP = array(
		'left'   => 'slide-in-left',
		'right'  => 'slide-in-right',
		'up'     => 'slide-in-up',
		'down'   => 'slide-in-down',
		'top'    => 'slide-in-down',
		'bottom' => 'slide-in-up',
	);

	/**
	 * Timing function keywords.
	 *
	 * @var array<string, string>
	 */
	private const TIMING_MAP = array(
		'smooth'  => 'ease',
		'linear'  => 'linear',
		'fast'    => 'ease-out',
		'slow'    => 'ease-in',
		'elastic' => 'cubic-bezier(0.68, -0.55, 0.265, 1.55)',
		'snappy'  => 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
		'gentle'  => 'cubic-bezier(0.4, 0, 0.2, 1)',
	);

	/**
	 * Custom keyframes defined by user.
	 *
	 * @var array<string, array{keyframes: array<int|string, array<string, string>>, duration: string, timing: string, iteration: string}>
	 */
	private array $custom_keyframes = array();

	/**
	 * Build animation CSS from natural language description.
	 *
	 * @param string               $selector    CSS selector.
	 * @param string               $description Animation description.
	 * @param array<string, mixed> $options     Additional options.
	 * @return array{animation_css: string, keyframes_css: string, animation_name: string, combined: string}
	 */
	public function build( string $selector, string $description, array $options = array() ): array {
		$parsed = $this->parse_description( $description );

		// Merge with options.
		$animation_name = $options['name'] ?? $parsed['name'];
		$duration       = $options['duration'] ?? $parsed['duration'];
		$timing         = $options['timing'] ?? $parsed['timing'];
		$iteration      = $options['iteration'] ?? $parsed['iteration'];
		$delay          = $options['delay'] ?? $parsed['delay'];
		$direction      = $options['direction'] ?? 'normal';
		$fill_mode      = $options['fill_mode'] ?? 'forwards';

		// Get keyframes.
		$keyframes = $this->get_keyframes( $parsed['preset'] );

		// Generate unique animation name.
		$unique_name = 'wyverncss-' . $animation_name . '-' . substr( md5( $selector ), 0, 6 );

		// Build keyframes CSS.
		$keyframes_css = $this->generate_keyframes_css( $unique_name, $keyframes );

		// Build animation property.
		$animation_parts = array(
			$unique_name,
			$duration,
			$timing,
			$delay,
			$iteration,
			$direction,
			$fill_mode,
		);

		$animation_value = implode( ' ', array_filter( $animation_parts ) );

		// Build element CSS.
		$animation_css = "{$selector} {\n\tanimation: {$animation_value};\n}";

		// Combined output.
		$combined = "{$keyframes_css}\n\n{$animation_css}";

		return array(
			'animation_css'  => $animation_css,
			'keyframes_css'  => $keyframes_css,
			'animation_name' => $unique_name,
			'combined'       => $combined,
		);
	}

	/**
	 * Parse natural language description.
	 *
	 * @param string $description Animation description.
	 * @return array{name: string, preset: string, duration: string, timing: string, iteration: string, delay: string}
	 */
	private function parse_description( string $description ): array {
		$description_lower = strtolower( $description );
		$words             = preg_split( '/\s+/', $description_lower );
		if ( false === $words ) {
			$words = array();
		}

		// Default values.
		$result = array(
			'name'      => 'animation',
			'preset'    => 'fade-in',
			'duration'  => '0.3s',
			'timing'    => 'ease',
			'iteration' => '1',
			'delay'     => '0s',
		);

		// Find animation type.
		foreach ( $words as $word ) {
			// Check keyword map.
			if ( isset( self::KEYWORD_MAP[ $word ] ) ) {
				$result['preset'] = self::KEYWORD_MAP[ $word ];
				$result['name']   = $word;
				break;
			}
		}

		// Check for direction modifiers (for slide animations).
		if ( strpos( $result['preset'], 'slide' ) !== false ) {
			foreach ( $words as $word ) {
				if ( isset( self::DIRECTION_MAP[ $word ] ) ) {
					$result['preset'] = self::DIRECTION_MAP[ $word ];
					break;
				}
			}
		}

		// Parse duration.
		if ( preg_match( '/(\d+(?:\.\d+)?)\s*(?:s|sec|seconds?)/', $description_lower, $matches ) ) {
			$result['duration'] = $matches[1] . 's';
		} elseif ( preg_match( '/(\d+)\s*(?:ms|milliseconds?)/', $description_lower, $matches ) ) {
			$result['duration'] = $matches[1] . 'ms';
		}

		// Parse timing.
		foreach ( $words as $word ) {
			if ( isset( self::TIMING_MAP[ $word ] ) ) {
				$result['timing'] = self::TIMING_MAP[ $word ];
				break;
			}
		}

		// Parse iteration.
		if ( strpos( $description_lower, 'infinite' ) !== false ||
			strpos( $description_lower, 'forever' ) !== false ||
			strpos( $description_lower, 'continuous' ) !== false ||
			strpos( $description_lower, 'loop' ) !== false ) {
			$result['iteration'] = 'infinite';
		} elseif ( preg_match( '/(\d+)\s*(?:times?|x|iterations?)/', $description_lower, $matches ) ) {
			$result['iteration'] = $matches[1];
		}

		// Get default values from preset (preset is always valid from KEYWORD_MAP/DIRECTION_MAP).
		$preset_data = self::PRESETS[ $result['preset'] ];

		// Only use defaults if not explicitly set.
		if ( '0.3s' === $result['duration'] && ! preg_match( '/\d+(?:\.\d+)?\s*(?:s|ms)/', $description_lower ) ) {
			$result['duration'] = $preset_data['duration'];
		}
		if ( '1' === $result['iteration'] && ! preg_match( '/infinite|forever|continuous|loop|\d+\s*times?/', $description_lower ) ) {
			$result['iteration'] = $preset_data['iteration'];
		}

		return $result;
	}

	/**
	 * Get keyframes for a preset.
	 *
	 * @param string $preset Preset name.
	 * @return array<int|string, array<string, string>>
	 */
	private function get_keyframes( string $preset ): array {
		if ( isset( $this->custom_keyframes[ $preset ] ) ) {
			return $this->custom_keyframes[ $preset ]['keyframes'];
		}

		return self::PRESETS[ $preset ]['keyframes'] ?? self::PRESETS['fade-in']['keyframes'];
	}

	/**
	 * Generate keyframes CSS.
	 *
	 * @param string                                   $name      Animation name.
	 * @param array<int|string, array<string, string>> $keyframes Keyframe definitions.
	 * @return string Keyframes CSS.
	 */
	private function generate_keyframes_css( string $name, array $keyframes ): string {
		$css = "@keyframes {$name} {\n";

		foreach ( $keyframes as $position => $properties ) {
			$css .= "\t{$position} {\n";
			foreach ( $properties as $property => $value ) {
				$css .= "\t\t{$property}: {$value};\n";
			}
			$css .= "\t}\n";
		}

		$css .= '}';

		return $css;
	}

	/**
	 * Register a custom animation preset.
	 *
	 * @param string                                   $name      Animation name.
	 * @param array<int|string, array<string, string>> $keyframes Keyframe definitions.
	 * @param array<string, string>                    $defaults  Default animation properties.
	 * @return void
	 */
	public function register_preset( string $name, array $keyframes, array $defaults = array() ): void {
		$this->custom_keyframes[ $name ] = array(
			'keyframes' => $keyframes,
			'duration'  => $defaults['duration'] ?? '0.3s',
			'timing'    => $defaults['timing'] ?? 'ease',
			'iteration' => $defaults['iteration'] ?? '1',
		);
	}

	/**
	 * Get all available presets.
	 *
	 * @return array<string, array{keyframes: array<int|string, array<string, string>>, duration: string, timing: string, iteration: string}>
	 */
	public function get_presets(): array {
		return array_merge( self::PRESETS, $this->custom_keyframes );
	}

	/**
	 * Get preset names grouped by category.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function get_preset_categories(): array {
		return array(
			'entrance'   => array( 'fade-in', 'slide-in-left', 'slide-in-right', 'slide-in-up', 'slide-in-down', 'zoom-in' ),
			'exit'       => array( 'fade-out', 'zoom-out' ),
			'attention'  => array( 'bounce', 'pulse', 'shake', 'swing', 'wobble', 'heartbeat', 'flash', 'rubber-band', 'jello', 'tada' ),
			'continuous' => array( 'spin', 'float', 'glow' ),
			'special'    => array( 'flip' ),
		);
	}

	/**
	 * Build multiple animations for sequencing.
	 *
	 * @param string                                                 $selector CSS selector.
	 * @param array<int, array{description: string, delay?: string}> $sequence Array of animation configs.
	 * @return array{animations: array<int, array{animation_css: string, keyframes_css: string, animation_name: string, combined: string}>, combined: string}
	 */
	public function build_sequence( string $selector, array $sequence ): array {
		$animations       = array();
		$keyframes_css    = array();
		$animation_values = array();
		$total_delay      = 0.0;

		foreach ( $sequence as $index => $config ) {
			$description = $config['description'];
			$delay       = isset( $config['delay'] ) ? (float) $config['delay'] : 0.0;

			$total_delay += $delay;
			$result       = $this->build(
				$selector,
				$description,
				array( 'delay' => $total_delay . 's' )
			);

			$animations[]       = $result;
			$keyframes_css[]    = $result['keyframes_css'];
			$animation_values[] = trim( str_replace( array( $selector, '{', '}', 'animation:', ';' ), '', $result['animation_css'] ) );

			// Add duration to total delay for next animation.
			$parsed       = $this->parse_description( $description );
			$duration     = (float) rtrim( $parsed['duration'], 'sm' );
			$total_delay += $duration;
		}

		// Combine all animations.
		$combined_keyframes = implode( "\n\n", $keyframes_css );
		$combined_animation = implode( ', ', $animation_values );
		$combined_css       = "{$combined_keyframes}\n\n{$selector} {\n\tanimation: {$combined_animation};\n}";

		return array(
			'animations' => $animations,
			'combined'   => $combined_css,
		);
	}
}
