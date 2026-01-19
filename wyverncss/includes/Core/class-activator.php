<?php
/**
 * Plugin Activation Handler
 *
 * Handles all actions that need to occur when the plugin is activated.
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
 * Activator Class
 *
 * This class handles plugin activation tasks:
 * - Creating database tables
 * - Setting default options
 * - Flushing rewrite rules
 *
 * @since 1.0.0
 */
class Activator {

	/**
	 * Activate the plugin.
	 *
	 * @since 1.0.0
	 * @return void */
	public static function activate(): void {
		self::create_database_tables();
		self::set_default_options();
		self::create_capabilities();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Set activation timestamp.
		update_option( 'wyverncss_activated', time() );
		update_option( 'wyverncss_version', WYVERNCSS_VERSION );
	}

	/**
	 * Create database tables.
	 *
	 * @since 1.0.0
	 * @return void */
	private static function create_database_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_prefix    = $wpdb->prefix;

		// Table 1: wyverncss_usage - Track API usage.
		$usage_table = $table_prefix . 'wyverncss_usage';

		$usage_sql = "CREATE TABLE {$usage_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			request_type VARCHAR(50) NOT NULL,
			model_used VARCHAR(100) DEFAULT NULL,
			tokens_used INT(11) DEFAULT NULL,
			cost_estimate DECIMAL(10,6) DEFAULT NULL,
			prompt_hash VARCHAR(32) DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_id_idx (user_id),
			KEY created_at_idx (created_at),
			KEY prompt_hash_idx (prompt_hash)
		) {$charset_collate};";

		// Table 2: wyverncss_settings - User settings and API keys.
		$settings_table = $table_prefix . 'wyverncss_settings';

		$settings_sql = "CREATE TABLE {$settings_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			openrouter_api_key VARCHAR(255) DEFAULT NULL,
			preferred_model VARCHAR(100) DEFAULT 'anthropic/claude-3.5-haiku',
			tier VARCHAR(20) DEFAULT 'free',
			settings LONGTEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_id_unique (user_id),
			KEY user_id_idx (user_id)
		) {$charset_collate};";

		// Table 3: wyverncss_styles - Style history.
		$styles_table = $table_prefix . 'wyverncss_styles';

		$styles_sql = "CREATE TABLE {$styles_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			element_selector VARCHAR(255) DEFAULT NULL,
			prompt TEXT DEFAULT NULL,
			generated_css TEXT DEFAULT NULL,
			is_favorite TINYINT(1) DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_id_idx (user_id),
			KEY created_at_idx (created_at)
		) {$charset_collate};";

		// Include WordPress upgrade functions.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create or update tables using dbDelta.
		dbDelta( $usage_sql );
		dbDelta( $settings_sql );
		dbDelta( $styles_sql );

		// Run user settings migration (new architecture).
		$user_settings_migration = new \WyvernCSS\Database\Migration_User_Settings();
		$user_settings_migration->run();

		// Run AI conversation migrations.
		\WyvernCSS\Database\Migration_Conversations::up();
		\WyvernCSS\Database\Migration_Messages::up();

		// Store the database version.
		update_option( 'wyverncss_db_version', '1.0.0' );
	}

	/**
	 * Set default plugin options.
	 *
	 * @since 1.0.0
	 * @return void */
	private static function set_default_options(): void {
		$default_options = array(
			'wyverncss_enabled'             => true,
			'wyverncss_debug_mode'          => false,
			'wyverncss_default_model'       => 'anthropic/claude-3.5-haiku',
			'wyverncss_max_tokens'          => 4096,
			'wyverncss_temperature'         => 0.7,
			'wyverncss_cache_ttl'           => 3600,
			'wyverncss_rate_limit_requests' => 100,
			'wyverncss_rate_limit_window'   => 3600,
		);

		foreach ( $default_options as $option_name => $option_value ) {
			// Only set if the option doesn't already exist.
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $option_value );
			}
		}
	}

	/**
	 * Create custom capabilities.
	 *
	 * @since 1.0.0
	 * @return void */
	private static function create_capabilities(): void {
		$role = get_role( 'administrator' );

		if ( $role instanceof \WP_Role ) {
			$role->add_cap( 'manage_wyvernpress' );
			$role->add_cap( 'use_wyvernpress' );
		}

		// Add capabilities to editors as well.
		$editor = get_role( 'editor' );
		if ( $editor instanceof \WP_Role ) {
			$editor->add_cap( 'use_wyvernpress' );
		}
	}
}
