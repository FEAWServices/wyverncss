<?php
/**
 * Tier Configuration Handler
 *
 * Loads tier limits and settings from JSON config file.
 *
 * @package WyvernCSS
 * @subpackage Config
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\Config;

/**
 * Tier Configuration Class
 *
 * Singleton class that loads and provides access to tier configuration
 * from config/tiers.json file.
 *
 * @since 1.0.0
 */
class Tier_Config {

	/**
	 * Singleton instance.
	 *
	 * @var Tier_Config|null
	 */
	private static ?Tier_Config $instance = null;

	/**
	 * Configuration data.
	 *
	 * @var array<string, mixed>
	 */
	private array $config = array();

	/**
	 * Whether config has been loaded.
	 *
	 * @var bool
	 */
	private bool $loaded = false;

	/**
	 * Private constructor for singleton pattern.
	 */
	private function __construct() {
		$this->load_config();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Tier_Config
	 */
	public static function get_instance(): Tier_Config {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load configuration from JSON file.
	 *
	 * @return void
	 */
	private function load_config(): void {
		if ( $this->loaded ) {
			return;
		}

		$config_path = WYVERNCSS_PLUGIN_DIR . 'config/tiers.json';

		if ( ! file_exists( $config_path ) ) {
			// Use hardcoded defaults if config file missing.
			$this->config = $this->get_default_config();
			$this->loaded = true;
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local config file, not remote URL.
		$json_content = file_get_contents( $config_path );
		if ( false === $json_content ) {
			$this->config = $this->get_default_config();
			$this->loaded = true;
			return;
		}

		$decoded = json_decode( $json_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
			$this->config = $this->get_default_config();
			$this->loaded = true;
			return;
		}

		$this->config = $decoded;
		$this->loaded = true;
	}

	/**
	 * Get default configuration if JSON file is missing.
	 *
	 * @return array<string, mixed>
	 */
	private function get_default_config(): array {
		return array(
			'tiers'    => array(
				'free'         => array(
					'name'   => 'Free',
					'limit'  => 20,
					'period' => 'day',
				),
				'premium'      => array(
					'name'    => 'Premium',
					'aliases' => array( 'starter' ),
					'limit'   => 500,
					'period'  => 'month',
				),
				'professional' => array(
					'name'    => 'Professional',
					'aliases' => array( 'pro' ),
					'limit'   => -1,
					'period'  => 'unlimited',
				),
			),
			'defaults' => array(
				'fallback_tier'  => 'free',
				'fallback_limit' => 20,
			),
		);
	}

	/**
	 * Get rate limit for a tier.
	 *
	 * @param string $tier Tier name (free, premium, professional, or aliases).
	 * @return int Rate limit (-1 for unlimited).
	 */
	public function get_rate_limit( string $tier ): int {
		$normalized_tier = $this->normalize_tier( $tier );
		$tier_config     = $this->config['tiers'][ $normalized_tier ] ?? null;

		if ( null === $tier_config ) {
			return (int) ( $this->config['defaults']['fallback_limit'] ?? 20 );
		}

		return (int) ( $tier_config['limit'] ?? 20 );
	}

	/**
	 * Get reset period for a tier.
	 *
	 * @param string $tier Tier name.
	 * @return string Period ('day', 'month', 'unlimited').
	 */
	public function get_period( string $tier ): string {
		$normalized_tier = $this->normalize_tier( $tier );
		$tier_config     = $this->config['tiers'][ $normalized_tier ] ?? null;

		if ( null === $tier_config ) {
			return 'day';
		}

		return $tier_config['period'] ?? 'day';
	}

	/**
	 * Check if tier resets daily.
	 *
	 * @param string $tier Tier name.
	 * @return bool True if daily reset.
	 */
	public function is_daily_reset( string $tier ): bool {
		return 'day' === $this->get_period( $tier );
	}

	/**
	 * Check if tier has unlimited requests.
	 *
	 * @param string $tier Tier name.
	 * @return bool True if unlimited.
	 */
	public function is_unlimited( string $tier ): bool {
		return $this->get_rate_limit( $tier ) === -1;
	}

	/**
	 * Normalize tier name (handle aliases).
	 *
	 * @param string $tier Raw tier name.
	 * @return string Normalized tier name.
	 */
	public function normalize_tier( string $tier ): string {
		$tier = strtolower( $tier );

		// Direct match.
		if ( isset( $this->config['tiers'][ $tier ] ) ) {
			return $tier;
		}

		// Check aliases.
		foreach ( $this->config['tiers'] as $tier_key => $tier_config ) {
			$aliases = $tier_config['aliases'] ?? array();
			if ( in_array( $tier, $aliases, true ) ) {
				return $tier_key;
			}
		}

		// Fallback.
		return $this->config['defaults']['fallback_tier'] ?? 'free';
	}

	/**
	 * Get tier display name.
	 *
	 * @param string $tier Tier name.
	 * @return string Display name.
	 */
	public function get_tier_name( string $tier ): string {
		$normalized_tier = $this->normalize_tier( $tier );
		return $this->config['tiers'][ $normalized_tier ]['name'] ?? ucfirst( $tier );
	}

	/**
	 * Get tier description.
	 *
	 * @param string $tier Tier name.
	 * @return string Description.
	 */
	public function get_tier_description( string $tier ): string {
		$normalized_tier = $this->normalize_tier( $tier );
		return $this->config['tiers'][ $normalized_tier ]['description'] ?? '';
	}

	/**
	 * Get tier features list.
	 *
	 * @param string $tier Tier name.
	 * @return array<int, string> Features.
	 */
	public function get_tier_features( string $tier ): array {
		$normalized_tier = $this->normalize_tier( $tier );
		return $this->config['tiers'][ $normalized_tier ]['features'] ?? array();
	}

	/**
	 * Get all tiers.
	 *
	 * @return array<string, array<string, mixed>> All tier configurations.
	 */
	public function get_all_tiers(): array {
		return $this->config['tiers'] ?? array();
	}

	/**
	 * Get upgrade prompt settings.
	 *
	 * @return array<string, mixed> Upgrade prompt configuration.
	 */
	public function get_upgrade_prompts(): array {
		return $this->config['upgrade_prompts'] ?? array();
	}

	/**
	 * Get upgrade message by key.
	 *
	 * @param string $key Message key (approaching_limit, at_limit, low_confidence).
	 * @return string Message text.
	 */
	public function get_upgrade_message( string $key ): string {
		$messages = $this->config['upgrade_prompts']['messages'] ?? array();
		return $messages[ $key ] ?? '';
	}

	/**
	 * Get tier price.
	 *
	 * @param string $tier   Tier name.
	 * @param string $period Price period ('monthly' or 'yearly').
	 * @return float Price amount.
	 */
	public function get_price( string $tier, string $period = 'monthly' ): float {
		$normalized_tier = $this->normalize_tier( $tier );
		$tier_config     = $this->config['tiers'][ $normalized_tier ] ?? null;

		if ( null === $tier_config || ! isset( $tier_config['price'] ) ) {
			return 0.0;
		}

		return (float) ( $tier_config['price'][ $period ] ?? 0 );
	}

	/**
	 * Get tier AI configuration.
	 *
	 * @param string $tier Tier name.
	 * @return array<string, mixed> AI configuration.
	 */
	public function get_ai_config( string $tier ): array {
		$normalized_tier = $this->normalize_tier( $tier );
		$tier_config     = $this->config['tiers'][ $normalized_tier ] ?? null;

		if ( null === $tier_config || ! isset( $tier_config['ai'] ) ) {
			return array(
				'provider' => 'ollama',
				'model'    => 'qwen2.5:7b',
			);
		}

		return $tier_config['ai'];
	}

	/**
	 * Check if tier has a specific feature.
	 *
	 * @param string $tier    Tier name.
	 * @param string $feature Feature slug.
	 * @return bool True if tier has the feature.
	 */
	public function has_feature( string $tier, string $feature ): bool {
		$features = $this->get_tier_features( $tier );
		return in_array( $feature, $features, true );
	}

	/**
	 * Get rate limit per minute for tier.
	 *
	 * @param string $tier Tier name.
	 * @return int Requests per minute.
	 */
	public function get_rate_limit_per_minute( string $tier ): int {
		$normalized_tier = $this->normalize_tier( $tier );
		$rate_limits     = $this->config['rateLimit'] ?? array();

		return (int) ( $rate_limits[ $normalized_tier ]['requestsPerMinute'] ?? 10 );
	}

	/**
	 * Get rate limit per day for tier.
	 *
	 * @param string $tier Tier name.
	 * @return int|null Requests per day (null for unlimited).
	 */
	public function get_rate_limit_per_day( string $tier ): ?int {
		$normalized_tier = $this->normalize_tier( $tier );
		$rate_limits     = $this->config['rateLimit'] ?? array();

		$limit = $rate_limits[ $normalized_tier ]['requestsPerDay'] ?? 20;
		return null === $limit ? null : (int) $limit;
	}

	/**
	 * Get config version.
	 *
	 * @return string Version string.
	 */
	public function get_version(): string {
		return $this->config['version'] ?? '1.0.0';
	}

	/**
	 * Get raw config for debugging.
	 *
	 * @return array<string, mixed> Raw configuration.
	 */
	public function get_raw_config(): array {
		return $this->config;
	}
}
