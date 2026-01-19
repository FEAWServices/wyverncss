<?php
/**
 * Plugin Uninstall Handler
 *
 * Handles cleanup when the plugin is deleted via WordPress admin.
 * This file is called automatically by WordPress when the plugin is uninstalled.
 *
 * IMPORTANT: This file must be safe to run multiple times and should only
 * delete data if WP_UNINSTALL_PLUGIN is defined.
 *
 * Data Cleaned Up:
 * - WordPress options (wyverncss_*)
 * - Transients (wyverncss_*)
 * - User meta (wyverncss_*)
 * - Post meta (_wyverncss_*)
 * - Custom database tables (5 tables)
 * - Scheduled cron jobs
 * - User capabilities (manage_wyvernpress, use_wyvernpress)
 * - Uploaded files in wp-content/uploads/wyvernpress/
 * - Multisite network options
 *
 * @package WyvernCSS
 * @since 1.0.0
 */

declare(strict_types=1);

// Exit if accessed directly or if uninstall not triggered by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all WyvernCSS database tables
 *
 * @since 1.0.0
 * @return void
 */
function wyverncss_delete_tables(): void {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'wyverncss_usage',
		$wpdb->prefix . 'wyverncss_settings',
		$wpdb->prefix . 'wyverncss_styles',
		$wpdb->prefix . 'wyverncss_user_settings',
		$wpdb->prefix . 'wyverncss_conversations',
		$wpdb->prefix . 'wyverncss_messages',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}
}

/**
 * Delete all WyvernCSS options from wp_options
 *
 * Options cleaned up:
 * - Core: activated, version, db_version
 * - Settings: enabled, debug_mode, model configuration, rate limits
 * - API: api_key, license_key, OpenRouter settings
 * - Admin: settings page configuration, default_bot
 * - Database: schema version tracking
 *
 * @since 1.0.0
 * @return void
 */
function wyverncss_delete_options(): void {
	$options = array(
		// Core plugin options (from Activator).
		'wyverncss_activated',
		'wyverncss_deactivated',
		'wyverncss_version',
		'wyverncss_db_version',

		// Feature flags and settings (from Activator).
		'wyverncss_enabled',
		'wyverncss_debug_mode',
		'wyverncss_default_model',
		'wyverncss_max_tokens',
		'wyverncss_temperature',
		'wyverncss_cache_ttl',
		'wyverncss_rate_limit_requests',
		'wyverncss_rate_limit_window',

		// Admin settings (from SettingsPage).
		'wyverncss_api_key',
		'wyverncss_model',
		'wyverncss_default_bot',
		'wyverncss_settings',

		// Cloud service configuration.
		'wyverncss_cloud_service_url',

		// Database schema versions (from migrations).
		'wyverncss_conversations_schema_version',
		'wyverncss_messages_schema_version',
		'wyverncss_user_settings_version',

		// Legacy/additional options.
		'wyverncss_openrouter_api_key',
		'wyverncss_preferred_model',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete any remaining options with wyverncss_ prefix that might have been added dynamically.
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'wyverncss_' ) . '%'
		)
	);
}

/**
 * Delete all WyvernCSS transients from wp_options
 *
 * Transient prefixes cleaned up:
 * - wyverncss_css_* (ResponseCache - CSS generation cache)
 * - wyverncss_pattern_* (PatternCache - pattern matching cache)
 * - wyverncss_mcp_* (MCP CacheManager - tool response cache)
 * - wyverncss_circuit_state_* (Circuit Breaker - service state)
 * - wyverncss_circuit_failures_* (Circuit Breaker - failure count)
 * - wyverncss_circuit_opened_at_* (Circuit Breaker - opened timestamp)
 * - wyverncss_circuit_notified_* (Circuit Breaker - notification flag)
 * - wyverncss_admin_notices (NoticeManager - admin notices)
 *
 * @since 1.0.0
 * @return void
 */
function wyverncss_delete_transients(): void {
	global $wpdb;

	// Delete all transients with wyvernpress prefix (covers all the specific prefixes above).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s
			OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_wyverncss_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_wyverncss_' ) . '%'
		)
	);

	// Also delete site transients (for multisite installations).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s
			OR option_name LIKE %s",
			$wpdb->esc_like( '_site_transient_wyverncss_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_wyverncss_' ) . '%'
		)
	);
}

/**
 * Delete all WyvernCSS user meta
 *
 * Removes all user metadata keys starting with wyverncss_
 *
 * @since 1.0.0
 * @return void
 */
function wyverncss_delete_user_meta(): void {
	global $wpdb;

	// Delete all user meta with wyverncss_ prefix.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'wyverncss_' ) . '%'
		)
	);
}

/**
 * Delete all WyvernCSS post meta
 *
 * Removes all post metadata keys starting with _wyverncss_
 * (Note: WordPress convention uses underscore prefix for "private" meta)
 *
 * @since 1.0.0
 * @return void
 */
function wyverncss_delete_post_meta(): void {
	global $wpdb;

	// Delete all post meta with _wyverncss_ prefix.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_wyverncss_' ) . '%'
		)
	);
}

/**
 * Remove custom capabilities
 *
 * Capabilities removed:
 * - manage_wyvernpress (administrator only)
 * - use_wyvernpress (administrator and editor)
 *
 * @since 1.0.0
 * @return void
 */
function wyverncss_remove_capabilities(): void {
	$roles = array( 'administrator', 'editor' );

	foreach ( $roles as $role_name ) {
		$role = get_role( $role_name );

		if ( $role instanceof WP_Role ) {
			$role->remove_cap( 'manage_wyvernpress' );
			$role->remove_cap( 'use_wyvernpress' );
		}
	}
}

/**
 * Delete uploaded files (if any exist in uploads directory)
 *
 * @since 1.0.0
 * @return void
 */
function wyverncss_delete_uploaded_files(): void {
	$upload_dir      = wp_upload_dir();
	$wyverncss_dir = $upload_dir['basedir'] . '/wyvernpress';

	if ( file_exists( $wyverncss_dir ) && is_dir( $wyverncss_dir ) ) {
		// Recursively delete directory and its contents.
		wyverncss_recursive_delete( $wyverncss_dir );
	}
}

/**
 * Recursively delete a directory and all its contents
 *
 * @since 1.0.0
 * @param string $dir Directory path to delete.
 * @return bool True on success, false on failure.
 */
function wyverncss_recursive_delete( string $dir ): bool {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	// Initialize WP_Filesystem.
	global $wp_filesystem;
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	WP_Filesystem();

	if ( ! $wp_filesystem ) {
		return false;
	}

	// Use WP_Filesystem to delete the directory recursively.
	return $wp_filesystem->rmdir( $dir, true );
}

/**
 * Clear any scheduled cron events
 *
 * @since 1.0.0
 * @return void
 */
function wyverncss_clear_scheduled_events(): void {
	// Clear any cron events that might have been scheduled.
	$cron_hooks = array(
		'wyverncss_daily_cleanup',
		'wyverncss_weekly_report',
		'wyverncss_cache_cleanup',
	);

	foreach ( $cron_hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}
}

// ============================================================================
// Execute cleanup
// ============================================================================

// Only proceed if this is a single site OR if we're on the main site of a multisite.
if ( ! is_multisite() || is_main_site() ) {

	// 1. Delete database tables.
	wyverncss_delete_tables();

	// 2. Delete all options.
	wyverncss_delete_options();

	// 3. Delete all transients.
	wyverncss_delete_transients();

	// 4. Delete user meta.
	wyverncss_delete_user_meta();

	// 5. Delete post meta.
	wyverncss_delete_post_meta();

	// 6. Remove custom capabilities.
	wyverncss_remove_capabilities();

	// 7. Delete uploaded files.
	wyverncss_delete_uploaded_files();

	// 8. Clear scheduled cron events.
	wyverncss_clear_scheduled_events();

	// Flush rewrite rules one last time.
	flush_rewrite_rules();
}

// For multisite installations, clean up network-wide options.
if ( is_multisite() ) {
	delete_site_option( 'wyverncss_network_version' );
	delete_site_option( 'wyverncss_network_activated' );
}

// Log the uninstall for debugging (will only work if WP_DEBUG_LOG is enabled).
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( 'WyvernCSS: Plugin uninstalled and all data removed.' );
}
