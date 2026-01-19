<?php
/**
 * Plugin Deactivation Handler
 *
 * Handles all actions that need to occur when the plugin is deactivated.
 *
 * @package WyvernCSS
 * @subpackage Core
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\Core;

/**
 * Deactivator Class
 *
 * This class handles plugin deactivation tasks:
 * - Flushing rewrite rules
 * - Clearing scheduled events
 * - NOT deleting data (that happens in uninstall.php)
 *
 * @since 1.0.0
 */
class Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @since 1.0.0
	 * @return void */
	public static function deactivate(): void {
		self::clear_scheduled_events();
		self::flush_cache();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Set deactivation timestamp.
		update_option( 'wyverncss_deactivated', time() );
	}

	/**
	 * Clear all scheduled cron events.
	 *
	 * @since 1.0.0
	 * @return void */
	private static function clear_scheduled_events(): void {
		// Clear any scheduled cron events.
		$timestamp = wp_next_scheduled( 'wyverncss_cleanup_old_data' );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, 'wyverncss_cleanup_old_data' );
		}

		$timestamp = wp_next_scheduled( 'wyverncss_sync_usage_data' );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, 'wyverncss_sync_usage_data' );
		}
	}

	/**
	 * Flush all plugin-related cache.
	 *
	 * @since 1.0.0
	 * @return void */
	private static function flush_cache(): void {
		// Delete all transients with our prefix.
		global $wpdb;

		$sql = $wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s
			OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_wyverncss_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_wyverncss_' ) . '%'
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared statement above.
		$wpdb->query( $sql );

		// Clear object cache if available.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}
}
