<?php
/**
 * Cost Calculator
 *
 * Calculates API costs based on model pricing.
 *
 * @package WyvernCSS
 * @subpackage Analytics
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\Analytics;

/**
 * Cost Calculator Class
 *
 * Estimates and calculates costs for OpenRouter API requests.
 *
 * @since 1.0.0
 */
class CostCalculator {

	/**
	 * Model pricing rates (per 1K tokens)
	 *
	 * @var array<string, float>
	 */
	private array $pricing_rates = array(
		'claude-3-5-sonnet-20241022' => 0.003,  // $3 per 1M tokens.
		'claude-3-opus-20240229'     => 0.015,  // $15 per 1M tokens.
		'claude-3-sonnet-20240229'   => 0.003,  // $3 per 1M tokens.
		'claude-3-haiku-20240307'    => 0.00025, // $0.25 per 1M tokens
		'gpt-4-turbo'                => 0.01,   // $10 per 1M tokens.
		'gpt-3.5-turbo'              => 0.0005, // $0.50 per 1M tokens
	);

	/**
	 * Calculate cost for API request
	 *
	 * @param string $model  Model identifier.
	 * @param int    $tokens Number of tokens.
	 * @return float Cost in dollars.
	 */
	public function calculate_cost( string $model, int $tokens ): float {
		$rate_per_1k = $this->get_model_rate( $model );
		return ( $tokens / 1000 ) * $rate_per_1k;
	}

	/**
	 * Get model pricing rate
	 *
	 * @param string $model Model identifier.
	 * @return float Rate per 1K tokens.
	 */
	public function get_model_rate( string $model ): float {
		return $this->pricing_rates[ $model ] ?? 0.003; // Default to Claude Sonnet rate.
	}

	/**
	 * Estimate cost for prompt
	 *
	 * @param string $prompt User prompt.
	 * @param string $model  Model identifier.
	 * @return float Estimated cost.
	 */
	public function estimate_prompt_cost( string $prompt, string $model ): float {
		// Rough estimation: 1 token ≈ 4 characters.
		$estimated_tokens = (int) ceil( strlen( $prompt ) / 4 );

		// Add 50% buffer for completion tokens.
		$total_tokens = (int) ceil( $estimated_tokens * 1.5 );

		return $this->calculate_cost( $model, $total_tokens );
	}

	/**
	 * Get all pricing rates
	 *
	 * @return array<string, float> Model => Rate mapping.
	 */
	public function get_all_rates(): array {
		return $this->pricing_rates;
	}

	/**
	 * Format cost for display
	 *
	 * @param float $cost Cost in dollars.
	 * @return string Formatted cost string.
	 */
	public function format_cost( float $cost ): string {
		if ( $cost < 0.01 ) {
			return '<$0.01';
		}

		return '$' . number_format( $cost, 2 );
	}

	/**
	 * Calculate cost savings from pattern matches
	 *
	 * @param int    $pattern_matches Number of pattern matches.
	 * @param string $model           Model that would have been used.
	 * @param int    $avg_tokens      Average tokens per request.
	 * @return float Cost saved.
	 */
	public function calculate_savings( int $pattern_matches, string $model, int $avg_tokens = 500 ): float {
		return $this->calculate_cost( $model, $pattern_matches * $avg_tokens );
	}

	/**
	 * Get model display name
	 *
	 * @param string $model Model identifier.
	 * @return string Display name.
	 */
	public function get_model_name( string $model ): string {
		$names = array(
			'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
			'claude-3-opus-20240229'     => 'Claude 3 Opus',
			'claude-3-sonnet-20240229'   => 'Claude 3 Sonnet',
			'claude-3-haiku-20240307'    => 'Claude 3 Haiku',
			'gpt-4-turbo'                => 'GPT-4 Turbo',
			'gpt-3.5-turbo'              => 'GPT-3.5 Turbo',
		);

		return $names[ $model ] ?? $model;
	}
}
