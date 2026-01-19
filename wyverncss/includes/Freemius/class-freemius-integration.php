<?php
/**
 * Freemius Integration Wrapper
 *
 * Provides a strongly-typed wrapper around Freemius SDK functionality
 * for license validation, premium feature checks, and checkout flow.
 *
 * @package WyvernCSS
 * @subpackage Freemius
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\Freemius;
use WyvernCSS\Config\Tier_Config;

/**
 * Freemius Integration Class
 *
 * Wraps Freemius SDK with strongly-typed methods for:
 * - License validation
 * - Premium feature checks
 * - Plan information
 * - Upgrade/checkout URLs
 * - Integration with Tier_Config
 *
 * @since 1.0.0
 */
class Freemius_Integration {

	/**
	 * Singleton instance.
	 *
	 * @var Freemius_Integration|null
	 */
	private static ?Freemius_Integration $instance = null;

	/**
	 * Tier configuration instance.
	 *
	 * @var Tier_Config
	 */
	private Tier_Config $tier_config;

	/**
	 * Cached Freemius instance.
	 *
	 * @var object|null
	 * @phpstan-ignore-next-line - Freemius is a third-party class
	 */
	private ?object $freemius = null;

	/**
	 * Cached license data.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cached_license = null;

	/**
	 * Cache TTL for license data (5 minutes).
	 */
	private const CACHE_TTL = 300;

	/**
	 * Private constructor for singleton pattern.
	 */
	private function __construct() {
		$this->tier_config = Tier_Config::get_instance();
		$this->init_hooks();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Freemius_Integration
	 */
	public static function get_instance(): Freemius_Integration {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Clear cache when license changes.
		add_action( 'wyverncss_fs_loaded', array( $this, 'setup_license_hooks' ) );

		// Add Freemius pricing page to admin menu.
		add_action( 'admin_menu', array( $this, 'add_pricing_page' ), 20 );
	}

	/**
	 * Setup license change hooks after Freemius loads.
	 *
	 * @return void
	 */
	public function setup_license_hooks(): void {
		$fs = $this->get_freemius();
		if ( null === $fs ) {
			return;
		}

		// Clear cache on license activation/deactivation.
		// @phpstan-ignore-next-line - Freemius add_action is dynamically added.
		$fs->add_action( 'after_account_connection', array( $this, 'clear_license_cache' ) );
		// @phpstan-ignore-next-line - Freemius add_action is dynamically added.
		$fs->add_action( 'after_account_plan_sync', array( $this, 'clear_license_cache' ) );
		// @phpstan-ignore-next-line - Freemius add_action is dynamically added.
		$fs->add_action( 'after_license_change', array( $this, 'clear_license_cache' ) );
	}

	/**
	 * Get Freemius SDK instance.
	 *
	 * @return object|null Freemius instance or null if not available.
	 * @phpstan-ignore-next-line - Freemius is a third-party class
	 */
	private function get_freemius(): ?object {
		if ( null !== $this->freemius ) {
			return $this->freemius;
		}

		if ( ! function_exists( 'wyverncss_fs' ) ) {
			return null;
		}

		$fs = wyverncss_fs();
		if ( null === $fs ) {
			return null;
		}

		$this->freemius = $fs;
		return $this->freemius;
	}

	/**
	 * Check if user has an active premium license.
	 *
	 * @return bool True if premium, false otherwise.
	 */
	public function is_premium(): bool {
		$fs = $this->get_freemius();
		if ( null === $fs ) {
			return false;
		}

		// @phpstan-ignore-next-line - is_paying() is provided by Freemius SDK.
		return $fs->is_paying();
	}

	/**
	 * Check if Freemius is properly configured.
	 *
	 * @return bool True if configured, false otherwise.
	 */
	public function is_configured(): bool {
		if ( ! function_exists( 'wyverncss_fs_is_configured' ) ) {
			return false;
		}

		return wyverncss_fs_is_configured();
	}

	/**
	 * Get user's current plan name.
	 *
	 * Returns tier-compatible plan name ('free', 'premium', 'professional').
	 *
	 * @return string Plan name.
	 */
	public function get_plan(): string {
		$fs = $this->get_freemius();
		if ( null === $fs ) {
			return 'free';
		}

		// @phpstan-ignore-next-line - is_paying() is provided by Freemius SDK.
		if ( ! $fs->is_paying() ) {
			return 'free';
		}

		// Get plan object from Freemius.
		// @phpstan-ignore-next-line - get_plan() is provided by Freemius SDK.
		$plan = $fs->get_plan();
		if ( null === $plan ) {
			return 'free';
		}

		// Map Freemius plan to WyvernCSS tier.
		// @phpstan-ignore-next-line - name property exists on Freemius plan object.
		$plan_name = strtolower( $plan->name ?? '' );

		// Normalize plan name using Tier_Config.
		return $this->tier_config->normalize_tier( $plan_name );
	}

	/**
	 * Get license key for the current user.
	 *
	 * @return string|null License key or null if not available.
	 */
	public function get_license_key(): ?string {
		$license = $this->get_license_data();
		return $license['key'] ?? null;
	}

	/**
	 * Get full license data.
	 *
	 * Returns cached data if available, otherwise fetches from Freemius.
	 *
	 * @return array<string, mixed> License data.
	 */
	public function get_license_data(): array {
		// Check cache first.
		if ( null !== $this->cached_license ) {
			return $this->cached_license;
		}

		// Check WordPress transient cache.
		$cached = get_transient( 'wyverncss_license_data' );
		if ( is_array( $cached ) ) {
			$this->cached_license = $cached;
			return $this->cached_license;
		}

		// Fetch from Freemius.
		$license_data = $this->fetch_license_data();

		// Cache the result.
		set_transient( 'wyverncss_license_data', $license_data, self::CACHE_TTL );
		$this->cached_license = $license_data;

		return $license_data;
	}

	/**
	 * Fetch license data from Freemius SDK.
	 *
	 * @return array<string, mixed> License data.
	 */
	private function fetch_license_data(): array {
		$fs = $this->get_freemius();
		if ( null === $fs ) {
			return $this->get_default_license_data();
		}

		// @phpstan-ignore-next-line - is_paying() is provided by Freemius SDK.
		if ( ! $fs->is_paying() ) {
			return $this->get_default_license_data();
		}

		// Get license object from Freemius.
		// @phpstan-ignore-next-line - _get_license() is provided by Freemius SDK.
		$license = $fs->_get_license();
		if ( null === $license ) {
			return $this->get_default_license_data();
		}

		// Get plan object.
		// @phpstan-ignore-next-line - get_plan() is provided by Freemius SDK.
		$plan = $fs->get_plan();

		// Extract license data.
		// Convert expiration from MySQL datetime string to Unix timestamp.
		$expiration_str = $license->expiration ?? null; // @phpstan-ignore-line
		$expires        = null;
		if ( is_string( $expiration_str ) && '' !== $expiration_str ) {
			$expires = strtotime( $expiration_str );
			if ( false === $expires ) {
				$expires = null;
			}
		}

		return array(
			'key'         => $license->secret_key ?? null, // @phpstan-ignore-line
			'status'      => $this->get_license_status( $license ),
			'plan'        => $plan->name ?? 'Unknown', // @phpstan-ignore-line
			'plan_id'     => $plan->id ?? null, // @phpstan-ignore-line
			'expires'     => $expires,
			'is_active'   => $license->is_active() ?? false, // @phpstan-ignore-line
			'is_expired'  => $license->is_expired() ?? false, // @phpstan-ignore-line
			'activations' => $license->activated ?? 0, // @phpstan-ignore-line
			'max_sites'   => $license->quota ?? 1, // @phpstan-ignore-line
			'premium'     => true,
		);
	}

	/**
	 * Get default license data for free users.
	 *
	 * @return array<string, mixed> Default license data.
	 */
	private function get_default_license_data(): array {
		return array(
			'key'         => null,
			'status'      => 'free',
			'plan'        => 'Free',
			'plan_id'     => null,
			'expires'     => null,
			'is_active'   => true,
			'is_expired'  => false,
			'activations' => 0,
			'max_sites'   => 1,
			'premium'     => false,
		);
	}

	/**
	 * Get license status string.
	 *
	 * @param object $license Freemius license object.
	 * @return string Status ('active', 'expired', 'blocked', 'cancelled', 'inactive').
	 * @phpstan-ignore-next-line - License is a Freemius object
	 */
	private function get_license_status( object $license ): string {
		// @phpstan-ignore-next-line - Methods exist on Freemius license object.
		if ( $license->is_expired() ) {
			return 'expired';
		}

		// @phpstan-ignore-next-line
		if ( $license->is_blocked() ) {
			return 'blocked';
		}

		// @phpstan-ignore-next-line
		if ( $license->is_cancelled() ) {
			return 'cancelled';
		}

		// @phpstan-ignore-next-line
		if ( $license->is_active() ) {
			return 'active';
		}

		return 'inactive';
	}

	/**
	 * Clear license data cache.
	 *
	 * @return void
	 */
	public function clear_license_cache(): void {
		delete_transient( 'wyverncss_license_data' );
		$this->cached_license = null;
	}

	/**
	 * Get upgrade URL for checkout flow.
	 *
	 * @param string $plan_id Optional plan ID to upgrade to.
	 * @return string Upgrade URL.
	 */
	public function get_upgrade_url( string $plan_id = '' ): string {
		$fs = $this->get_freemius();
		if ( null === $fs ) {
			return $this->get_fallback_upgrade_url();
		}

		// Get Freemius checkout URL.
		// @phpstan-ignore-next-line - get_upgrade_url() is provided by Freemius SDK.
		$url = $fs->get_upgrade_url();

		// Add plan ID if specified.
		if ( ! empty( $plan_id ) ) {
			$url = add_query_arg( 'plan_id', $plan_id, $url );
		}

		return $url;
	}

	/**
	 * Get pricing page URL.
	 *
	 * @return string Pricing page URL.
	 */
	public function get_pricing_url(): string {
		$fs = $this->get_freemius();
		if ( null === $fs ) {
			return $this->get_fallback_upgrade_url();
		}

		// @phpstan-ignore-next-line - pricing_url() is provided by Freemius SDK.
		return $fs->pricing_url();
	}

	/**
	 * Get fallback upgrade URL when Freemius is not available.
	 *
	 * @return string Fallback URL.
	 */
	private function get_fallback_upgrade_url(): string {
		if ( function_exists( 'wyverncss_get_upgrade_url' ) ) {
			return wyverncss_get_upgrade_url();
		}
		return 'https://wordpress.org/plugins/wyvernpress/';
	}

	/**
	 * Get account page URL.
	 *
	 * @return string Account page URL.
	 */
	public function get_account_url(): string {
		$fs = $this->get_freemius();
		if ( null === $fs ) {
			return admin_url( 'admin.php?page=wyvernpress' );
		}

		// @phpstan-ignore-next-line - get_account_url() is provided by Freemius SDK.
		return $fs->get_account_url();
	}

	/**
	 * Check if user can use premium features.
	 *
	 * Alias for is_premium() for clearer intent.
	 *
	 * @return bool True if user can use premium features.
	 */
	public function can_use_premium_only(): bool {
		return $this->is_premium();
	}

	/**
	 * Get tier-specific rate limit using current plan.
	 *
	 * @return int Rate limit for current tier (-1 for unlimited).
	 */
	public function get_rate_limit(): int {
		$plan = $this->get_plan();
		return $this->tier_config->get_rate_limit( $plan );
	}

	/**
	 * Get tier-specific period using current plan.
	 *
	 * @return string Period ('day', 'month', 'unlimited').
	 */
	public function get_period(): string {
		$plan = $this->get_plan();
		return $this->tier_config->get_period( $plan );
	}

	/**
	 * Check if current plan has unlimited requests.
	 *
	 * @return bool True if unlimited.
	 */
	public function is_unlimited(): bool {
		$plan = $this->get_plan();
		return $this->tier_config->is_unlimited( $plan );
	}

	/**
	 * Get AI configuration for current tier.
	 *
	 * @return array<string, mixed> AI configuration.
	 */
	public function get_ai_config(): array {
		$plan = $this->get_plan();
		return $this->tier_config->get_ai_config( $plan );
	}

	/**
	 * Get tier features list for current plan.
	 *
	 * @return array<int, string> Features.
	 */
	public function get_tier_features(): array {
		$plan = $this->get_plan();
		return $this->tier_config->get_tier_features( $plan );
	}

	/**
	 * Check if current tier has a specific feature.
	 *
	 * @param string $feature Feature slug.
	 * @return bool True if tier has feature.
	 */
	public function has_feature( string $feature ): bool {
		$plan = $this->get_plan();
		return $this->tier_config->has_feature( $plan, $feature );
	}

	/**
	 * Get upgrade message for display to users.
	 *
	 * @param string $context Context key ('approaching_limit', 'at_limit', 'low_confidence').
	 * @return string Upgrade message.
	 */
	public function get_upgrade_message( string $context = 'at_limit' ): string {
		return $this->tier_config->get_upgrade_message( $context );
	}

	/**
	 * Add pricing page to admin menu.
	 *
	 * @return void
	 */
	public function add_pricing_page(): void {
		$fs = $this->get_freemius();
		if ( null === $fs ) {
			return;
		}

		// Freemius automatically adds pricing page, but we can customize it here if needed.
	}

	/**
	 * Check if user is in trial period.
	 *
	 * @return bool True if in trial.
	 */
	public function is_trial(): bool {
		$fs = $this->get_freemius();
		if ( null === $fs ) {
			return false;
		}

		// @phpstan-ignore-next-line - is_trial() is provided by Freemius SDK.
		return $fs->is_trial();
	}

	/**
	 * Get trial days remaining.
	 *
	 * @return int Days remaining (0 if not in trial).
	 */
	public function get_trial_days_remaining(): int {
		if ( ! $this->is_trial() ) {
			return 0;
		}

		$license = $this->get_license_data();
		$expires = $license['expires'] ?? null;

		if ( ! is_int( $expires ) || $expires <= 0 ) {
			return 0;
		}

		$now  = time();
		$diff = $expires - $now;
		$days = (int) ceil( $diff / DAY_IN_SECONDS );

		return max( 0, $days );
	}

	/**
	 * Trigger Freemius checkout dialog.
	 *
	 * Returns JavaScript code to trigger checkout.
	 *
	 * @param string $plan_id   Plan ID to checkout.
	 * @param bool   $is_annual Whether to use annual billing.
	 * @return string JavaScript code.
	 */
	public function get_checkout_js( string $plan_id = '', bool $is_annual = true ): string {
		$fs = $this->get_freemius();
		if ( null === $fs ) {
			return '';
		}

		// Build checkout URL with parameters.
		$url = $this->get_upgrade_url( $plan_id );
		if ( $is_annual ) {
			$url = add_query_arg( 'billing_cycle', 'annual', $url );
		}

		// Return JavaScript to open checkout.
		return sprintf(
			'window.location.href = %s;',
			wp_json_encode( $url )
		);
	}

	/**
	 * Get license expiration date.
	 *
	 * @return string|null Expiration date (Y-m-d format) or null.
	 */
	public function get_license_expiration(): ?string {
		$license = $this->get_license_data();
		$expires = $license['expires'] ?? null;

		if ( null === $expires ) {
			return null;
		}

		return gmdate( 'Y-m-d', $expires );
	}

	/**
	 * Check if license is expired.
	 *
	 * @return bool True if expired.
	 */
	public function is_license_expired(): bool {
		$license = $this->get_license_data();
		return $license['is_expired'] ?? false;
	}

	/**
	 * Get days until license expiration.
	 *
	 * @return int Days until expiration (negative if expired).
	 */
	public function get_days_until_expiration(): int {
		$license = $this->get_license_data();
		$expires = $license['expires'] ?? null;

		if ( null === $expires ) {
			return 0;
		}

		$now  = time();
		$diff = $expires - $now;

		return (int) floor( $diff / DAY_IN_SECONDS );
	}
}
