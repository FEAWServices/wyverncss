<?php
/**
 * Model Selection Logic
 *
 * Selects appropriate AI model based on request complexity, user tier,
 * and cost constraints.
 *
 * @package WyvernCSS
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\AI;
use WP_Error;

/**
 * Class ModelSelector
 *
 * Handles intelligent model selection based on various factors.
 */
class ModelSelector {

	/**
	 * Available AI models with pricing and capabilities
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public const AVAILABLE_MODELS = array(
		// Claude models.
		'claude-opus'   => array(
			'id'            => 'anthropic/claude-3-opus',
			'name'          => 'Claude 3 Opus',
			'cost_per_1k'   => 0.015,
			'tier'          => 'pro',
			'max_tokens'    => 4096,
			'quality_score' => 10,
			'speed_score'   => 6,
		),
		'claude-sonnet' => array(
			'id'            => 'anthropic/claude-3.5-sonnet',
			'name'          => 'Claude 3.5 Sonnet',
			'cost_per_1k'   => 0.003,
			'tier'          => 'starter',
			'max_tokens'    => 4096,
			'quality_score' => 9,
			'speed_score'   => 8,
		),
		'claude-haiku'  => array(
			'id'            => 'anthropic/claude-3-haiku',
			'name'          => 'Claude 3 Haiku',
			'cost_per_1k'   => 0.00025,
			'tier'          => 'free',
			'max_tokens'    => 4096,
			'quality_score' => 7,
			'speed_score'   => 10,
		),

		// GPT models.
		'gpt-4'         => array(
			'id'            => 'openai/gpt-4-turbo',
			'name'          => 'GPT-4 Turbo',
			'cost_per_1k'   => 0.01,
			'tier'          => 'pro',
			'max_tokens'    => 4096,
			'quality_score' => 9,
			'speed_score'   => 7,
		),
		'gpt-3.5'       => array(
			'id'            => 'openai/gpt-3.5-turbo',
			'name'          => 'GPT-3.5 Turbo',
			'cost_per_1k'   => 0.0005,
			'tier'          => 'free',
			'max_tokens'    => 4096,
			'quality_score' => 6,
			'speed_score'   => 9,
		),

		// Open source models.
		'llama-3'       => array(
			'id'            => 'meta-llama/llama-3-70b-instruct',
			'name'          => 'Llama 3 70B',
			'cost_per_1k'   => 0.0004,
			'tier'          => 'free',
			'max_tokens'    => 8192,
			'quality_score' => 6,
			'speed_score'   => 8,
		),
	);

	/**
	 * Complexity levels for requests
	 *
	 * @var array<string, int>
	 */
	private const COMPLEXITY_SCORES = array(
		'simple'  => 1,  // Basic color/spacing changes.
		'medium'  => 5,  // Layout adjustments.
		'complex' => 10, // Advanced animations/responsive.
	);

	/**
	 * User tier model access
	 *
	 * @var array<string, array<string>>
	 */
	private const TIER_MODELS = array(
		'free'    => array( 'claude-haiku', 'gpt-3.5', 'llama-3' ),
		'starter' => array( 'claude-haiku', 'claude-sonnet', 'gpt-3.5', 'llama-3' ),
		'pro'     => array( 'claude-haiku', 'claude-sonnet', 'claude-opus', 'gpt-4', 'gpt-3.5', 'llama-3' ),
	);

	/**
	 * Select best model for request
	 *
	 * @param string               $complexity Request complexity (simple/medium/complex).
	 * @param string               $user_tier User subscription tier (free/starter/pro).
	 * @param array<string, mixed> $options Additional options (prefer_speed, max_cost, etc.).
	 *
	 * @return array<string, mixed>|WP_Error Selected model configuration or error.
	 */
	public function select_model( string $complexity, string $user_tier, array $options = array() ) {
		// Validate inputs.
		if ( ! isset( self::COMPLEXITY_SCORES[ $complexity ] ) ) {
			return new WP_Error(
				'invalid_complexity',
				__( 'Invalid complexity level', 'wyvern-ai-styling' ),
				array( 'complexity' => $complexity )
			);
		}

		if ( ! isset( self::TIER_MODELS[ $user_tier ] ) ) {
			return new WP_Error(
				'invalid_tier',
				__( 'Invalid user tier', 'wyvern-ai-styling' ),
				array( 'tier' => $user_tier )
			);
		}

		// Get available models for user tier.
		$available_models = $this->get_available_models_for_tier( $user_tier );

		if ( empty( $available_models ) ) {
			return new WP_Error(
				'no_models_available',
				__( 'No models available for your tier', 'wyvern-ai-styling' )
			);
		}

		// Filter by max cost if specified.
		$max_cost = $options['max_cost'] ?? null;
		if ( null !== $max_cost ) {
			$available_models = array_filter(
				$available_models,
				function ( $model ) use ( $max_cost ) {
					return $model['cost_per_1k'] <= $max_cost;
				}
			);
		}

		// Select model based on complexity and preferences.
		$selected = $this->select_by_complexity(
			$available_models,
			$complexity,
			$options['prefer_speed'] ?? false
		);

		if ( empty( $selected ) ) {
			return new WP_Error(
				'no_suitable_model',
				__( 'No suitable model found for your requirements', 'wyvern-ai-styling' )
			);
		}

		return $selected;
	}

	/**
	 * Get available models for user tier
	 *
	 * @param string $user_tier User subscription tier.
	 *
	 * @return array<string, array<string, mixed>> Available models.
	 */
	private function get_available_models_for_tier( string $user_tier ): array {
		$tier_model_keys = self::TIER_MODELS[ $user_tier ] ?? array();
		$available       = array();

		foreach ( $tier_model_keys as $key ) {
			// Key exists in TIER_MODELS, so it must exist in AVAILABLE_MODELS.
			$available[ $key ] = self::AVAILABLE_MODELS[ $key ];
		}

		return $available;
	}

	/**
	 * Select model by complexity and preferences
	 *
	 * @param array<string, array<string, mixed>> $models Available models.
	 * @param string                              $complexity Request complexity.
	 * @param bool                                $prefer_speed Whether to prefer speed over quality.
	 *
	 * @return array<string, mixed> Selected model configuration.
	 */
	private function select_by_complexity( array $models, string $complexity, bool $prefer_speed ): array {
		$complexity_score = self::COMPLEXITY_SCORES[ $complexity ];

		// Sort models by appropriate score.
		$score_key = $prefer_speed ? 'speed_score' : 'quality_score';

		uasort(
			$models,
			function ( $a, $b ) use ( $score_key, $complexity_score ) {
				// For simple requests, prefer faster/cheaper models.
				if ( $complexity_score <= 3 ) {
					return $b['speed_score'] <=> $a['speed_score'];
				}

				// For complex requests, prefer quality.
				if ( $complexity_score >= 8 ) {
					return $b['quality_score'] <=> $a['quality_score'];
				}

				// For medium complexity, balance based on preference.
				return $b[ $score_key ] <=> $a[ $score_key ];
			}
		);

		// Return the best model (reset returns the first value or false if empty).
		$selected = reset( $models );
		return false !== $selected ? $selected : array();
	}

	/**
	 * Get fallback model for failed request
	 *
	 * @param string $failed_model_key Failed model key.
	 * @param string $user_tier User tier.
	 *
	 * @return array<string, mixed>|WP_Error Fallback model or error.
	 */
	public function get_fallback_model( string $failed_model_key, string $user_tier ) {
		$available_models = $this->get_available_models_for_tier( $user_tier );

		// Remove failed model from options.
		unset( $available_models[ $failed_model_key ] );

		if ( empty( $available_models ) ) {
			return new WP_Error(
				'no_fallback_available',
				__( 'No fallback model available', 'wyvern-ai-styling' )
			);
		}

		// Sort by speed (fallback should be fast).
		uasort(
			$available_models,
			function ( $a, $b ) {
				return $b['speed_score'] <=> $a['speed_score'];
			}
		);

		return reset( $available_models );
	}

	/**
	 * Determine request complexity from user input
	 *
	 * @param string $user_request User's natural language request.
	 *
	 * @return string Complexity level (simple/medium/complex).
	 */
	public function determine_complexity( string $user_request ): string {
		$request_lower = strtolower( $user_request );

		// Complex indicators.
		$complex_keywords = array(
			'animation',
			'transition',
			'transform',
			'gradient',
			'responsive',
			'breakpoint',
			'media query',
			'hover effect',
			'keyframe',
			'3d',
		);

		foreach ( $complex_keywords as $keyword ) {
			if ( strpos( $request_lower, $keyword ) !== false ) {
				return 'complex';
			}
		}

		// Medium indicators.
		$medium_keywords = array(
			'layout',
			'grid',
			'flexbox',
			'position',
			'align',
			'justify',
			'spacing',
			'margin',
			'padding',
		);

		foreach ( $medium_keywords as $keyword ) {
			if ( strpos( $request_lower, $keyword ) !== false ) {
				return 'medium';
			}
		}

		// Default to simple.
		return 'simple';
	}

	/**
	 * Get model by key
	 *
	 * @param string $model_key Model key.
	 *
	 * @return array<string, mixed>|null Model configuration or null.
	 */
	public function get_model( string $model_key ): ?array {
		return self::AVAILABLE_MODELS[ $model_key ] ?? null;
	}

	/**
	 * Get all models for a tier
	 *
	 * @param string $tier User tier.
	 *
	 * @return array<string, array<string, mixed>> Models available for tier.
	 */
	public function get_models_for_tier( string $tier ): array {
		return $this->get_available_models_for_tier( $tier );
	}

	/**
	 * Check if model is available for tier
	 *
	 * @param string $model_key Model key.
	 * @param string $tier User tier.
	 *
	 * @return bool True if available.
	 */
	public function is_model_available_for_tier( string $model_key, string $tier ): bool {
		$tier_models = self::TIER_MODELS[ $tier ] ?? array();
		return in_array( $model_key, $tier_models, true );
	}
}
