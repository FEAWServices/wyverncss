<?php
/**
 * Freemius SDK Integration
 *
 * Initializes Freemius SDK for licensing, updates, and premium features.
 *
 * @package WyvernCSS
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Exit if accessed directly.
// Skip Freemius in WP-CLI to avoid hanging issues.
// Freemius SDK makes API calls that block in CLI mode.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// Define stub functions for compatibility.
	if ( ! function_exists( 'wyverncss_fs' ) ) {
		/**
		 * Stub function for WP-CLI compatibility.
		 *
		 * @return null
		 */
		function wyverncss_fs() {
			return null;
		}
	}

	if ( ! function_exists( 'wyverncss_is_premium' ) ) {
		/**
		 * Check if premium (always false in CLI).
		 *
		 * @return bool
		 */
		function wyverncss_is_premium(): bool {
			return false;
		}
	}

	if ( ! function_exists( 'wyverncss_get_plan' ) ) {
		/**
		 * Get plan name (always free in CLI).
		 *
		 * @return string
		 */
		function wyverncss_get_plan(): string {
			return 'free';
		}
	}

	if ( ! function_exists( 'wyverncss_get_upgrade_url' ) ) {
		/**
		 * Get upgrade URL (fallback in CLI).
		 *
		 * @return string
		 */
		function wyverncss_get_upgrade_url(): string {
			return 'https://wordpress.org/plugins/wyvernpress/';
		}
	}

	if ( ! function_exists( 'wyverncss_fs_is_configured' ) ) {
		/**
		 * Check if Freemius is configured (always false in CLI).
		 *
		 * @return bool
		 */
		function wyverncss_fs_is_configured(): bool {
			return false;
		}
	}

	return;
}

if ( ! function_exists( 'wyverncss_fs' ) ) {
	/**
	 * Create a helper function for easy SDK access.
	 *
	 * @return \Freemius|null The Freemius SDK instance or null if not initialized.
	 * @phpstan-ignore-next-line - Freemius class is provided by third-party SDK
	 */
	function wyverncss_fs() {
		global $wyverncss_fs;

		if ( ! isset( $wyverncss_fs ) ) {
			// Include Freemius SDK.
			require_once WYVERNCSS_PLUGIN_DIR . 'freemius/start.php';

			// @phpstan-ignore-next-line - fs_dynamic_init() is provided by Freemius SDK
			$wyverncss_fs = fs_dynamic_init(
				array(
					'id'                  => '22259',
					'slug'                => 'wyvern-ai-styling',
					'premium_slug'        => 'wyvernpress-premium',
					'type'                => 'plugin',
					'public_key'          => 'pk_5cad950fed79e06553e6b464645ed',
					'is_premium'          => false,
					'has_premium_version' => true,
					'has_paid_plans'      => true,
					'has_addons'          => false,
					'is_org_compliant'    => true, // WordPress.org compliant (no premium code in free version).
					// NOTE: No trial period - WordPress.org prohibits time-limited features.
					// Free tier has 20 requests/day, Premium is unlimited - both fully functional.
					'menu'                => array(
						'slug'    => 'wyvern-ai-styling',
						'parent'  => array(
							'slug' => 'options-general.php',
						),
						'contact' => true,
						'support' => true,
					),
					'anonymous_mode'      => false, // Ask users to opt-in to analytics.
					'is_live'             => true,
				)
			);
		}

		return $wyverncss_fs;
	}

	// Initialize Freemius.
	wyverncss_fs();

	// Signal that SDK was initiated.
	do_action( 'wyverncss_fs_loaded' );
}

/**
 * Check if the current user has an active premium license.
 *
 * @return bool True if user has premium, false otherwise.
 */
function wyverncss_is_premium(): bool {
	if ( ! function_exists( 'wyverncss_fs' ) ) {
		return false;
	}

	$fs = wyverncss_fs();
	if ( null === $fs ) {
		return false;
	}

	// @phpstan-ignore-next-line - is_paying() is provided by Freemius SDK
	return $fs->is_paying();
}

/**
 * Get the user's current plan name.
 *
 * @return string Plan name ('free' or 'premium').
 */
function wyverncss_get_plan(): string {
	if ( ! function_exists( 'wyverncss_fs' ) ) {
		return 'free';
	}

	$fs = wyverncss_fs();
	if ( null === $fs ) {
		return 'free';
	}

	// @phpstan-ignore-next-line - is_paying() is provided by Freemius SDK
	if ( $fs->is_paying() ) {
		return 'premium';
	}

	return 'free';
}

/**
 * Get the upgrade URL for premium features.
 *
 * For 1.0.0 release, returns WordPress.org plugin page.
 * Premium plans will be available in a future release.
 *
 * @return string The upgrade URL.
 */
function wyverncss_get_upgrade_url(): string {
	// For 1.0.0 release, premium is "coming soon" - link to plugin page.
	return 'https://wordpress.org/plugins/wyvernpress/';
}

/**
 * Check if Freemius is properly configured.
 *
 * @return bool True if configured, false if using placeholder values.
 */
function wyverncss_fs_is_configured(): bool {
	// Check if we're still using placeholder values.
	if ( ! function_exists( 'wyverncss_fs' ) ) {
		return false;
	}

	$fs = wyverncss_fs();
	if ( null === $fs ) {
		return false;
	}

	// The SDK will throw errors if not properly configured.
	// This is a simple check - real config will have a numeric ID.
	// @phpstan-ignore-next-line - get_id() is provided by Freemius SDK.
	return is_numeric( $fs->get_id() ) && (int) $fs->get_id() > 0;
}
