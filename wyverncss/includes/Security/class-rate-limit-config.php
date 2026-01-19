<?php
/**
 * Rate Limit Configuration
 *
 * Centralized rate limit configuration for WyvernCSS services.
 * Reads from tiers.json configuration file.
 *
 * @package WyvernCSS\Security
 * @since 2.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\Config\Tier_Config;

/**
 * Rate Limit Configuration Class
 *
 * Provides centralized rate limit tiers configuration
 * shared across MCP Transport and Security Validator.
 * Now reads from tiers.json for single source of truth.
 *
 * @since 2.0.0
 */
class Rate_Limit_Config {

	/**
	 * Get rate limits for tier.
	 *
	 * Returns hourly, burst, and daily limits for given subscription tier.
	 * Daily limit takes precedence - if set, it caps total requests per day.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tier User tier (free, premium, etc.).
	 * @return array<string, int|null> Array with 'hourly', 'burst', and 'daily' limits.
	 */
	public static function get_limits_for_tier( string $tier ): array {
		$config = Tier_Config::get_instance();

		// Get rate limits from tiers.json.
		$per_minute = $config->get_rate_limit_per_minute( $tier );
		$per_day    = $config->get_rate_limit_per_day( $tier );

		// If unlimited (null per_day), use PHP_INT_MAX for hourly/burst.
		if ( null === $per_day ) {
			return array(
				'hourly' => PHP_INT_MAX,
				'burst'  => PHP_INT_MAX,
				'daily'  => null, // Unlimited.
			);
		}

		// For limited tiers, daily cap is the primary limit.
		// Hourly is calculated to spread requests evenly, but daily is enforced.
		// Example: 20/day = ~1/hour max average, burst = per_minute limit.
		$hourly = (int) ceil( $per_day / 24 );
		$burst  = $per_minute;

		return array(
			'hourly' => max( $hourly, $burst ), // At least allow burst rate.
			'burst'  => $burst,
			'daily'  => $per_day, // This is the enforced cap.
		);
	}

	/**
	 * Get daily limit for tier.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tier User tier.
	 * @return int|null Daily limit (null for unlimited).
	 */
	public static function get_daily_limit( string $tier ): ?int {
		$config = Tier_Config::get_instance();
		return $config->get_rate_limit_per_day( $tier );
	}

	/**
	 * Check if tier has unlimited requests.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tier User tier.
	 * @return bool True if unlimited.
	 */
	public static function is_unlimited( string $tier ): bool {
		$config = Tier_Config::get_instance();
		return $config->is_unlimited( $tier );
	}
}
