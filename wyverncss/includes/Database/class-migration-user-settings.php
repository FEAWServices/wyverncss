<?php
/**
 * User Settings Database Migration
 *
 * Creates the user settings table for storing encrypted API keys and preferences.
 *
 * @package WyvernCSS\Database
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\Database;

/**
 * Class Migration_User_Settings
 *
 * Manages the creation and upgrade of the user settings table.
 */
class Migration_User_Settings {

	/**
	 * Table version
	 */
	private const TABLE_VERSION = '1.0.0';

	/**
	 * Table name
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Option name for storing table version
	 *
	 * @var string
	 */
	private string $version_option;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name     = $wpdb->prefix . 'wyverncss_user_settings';
		$this->version_option = 'wyverncss_user_settings_version';
	}

	/**
	 * Run the migration
	 *
	 * Creates or updates the table if needed.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function run(): bool {
		$current_version = get_option( $this->version_option, '0.0.0' );

		// Check if we need to run migration.
		if ( version_compare( $current_version, self::TABLE_VERSION, '>=' ) ) {
			return true; // Already up to date.
		}

		// Run migration.
		$result = $this->create_table();

		if ( $result ) {
			update_option( $this->version_option, self::TABLE_VERSION );
		}

		return $result;
	}

	/**
	 * Create the user settings table
	 *
	 * @return bool True on success, false on failure.
	 */
	private function create_table(): bool {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			setting_key VARCHAR(100) NOT NULL,
			setting_value LONGTEXT,
			encrypted TINYINT(1) DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_setting (user_id, setting_key),
			KEY user_id (user_id),
			KEY setting_key (setting_key),
			KEY updated_at (updated_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );

		// Verify table was created.
		$table = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $this->table_name )
			)
		);

		return $table === $this->table_name;
	}

	/**
	 * Drop the table (for uninstallation)
	 *
	 * WARNING: This permanently deletes all user settings.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function drop_table(): bool {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $this->table_name )
		);

		if ( false !== $result ) {
			delete_option( $this->version_option );
			return true;
		}

		return false;
	}

	/**
	 * Get table name
	 *
	 * @return string Table name.
	 */
	public function get_table_name(): string {
		return $this->table_name;
	}

	/**
	 * Check if table exists
	 *
	 * @return bool True if table exists, false otherwise.
	 */
	public function table_exists(): bool {
		global $wpdb;

		$table = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $this->table_name )
			)
		);

		return $table === $this->table_name;
	}

	/**
	 * Get current table version
	 *
	 * @return string Current version or '0.0.0' if not set.
	 */
	public function get_current_version(): string {
		return get_option( $this->version_option, '0.0.0' );
	}

	/**
	 * Get target table version
	 *
	 * @return string Target version.
	 */
	public function get_target_version(): string {
		return self::TABLE_VERSION;
	}
}
