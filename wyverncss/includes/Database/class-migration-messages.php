<?php
/**
 * Messages Table Migration
 *
 * Creates the wyverncss_messages table for storing AI chat messages.
 *
 * @package WyvernCSS\Database
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Migration_Messages
 *
 * Handles creation and updates of the messages table.
 */
class Migration_Messages extends Migration_Base {

	/**
	 * Table name (without prefix)
	 */
	private const TABLE_NAME = 'wyverncss_messages';

	/**
	 * Current schema version
	 */
	private const SCHEMA_VERSION = '1.0.0';

	/**
	 * Run the migration
	 *
	 * Creates the messages table if it doesn't exist.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function up(): bool {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id BIGINT(20) UNSIGNED NOT NULL,
			role ENUM('system', 'user', 'assistant', 'tool') NOT NULL,
			content LONGTEXT NOT NULL,
			tool_calls JSON DEFAULT NULL,
			tokens_used INT UNSIGNED DEFAULT 0,
			cost_usd DECIMAL(10, 6) DEFAULT 0.000000,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_conversation_id (conversation_id),
			KEY idx_role (role),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) !== $table_name ) {
			return false;
		}

		// Store schema version.
		update_option( 'wyverncss_messages_schema_version', self::SCHEMA_VERSION );

		return true;
	}

	/**
	 * Rollback the migration
	 *
	 * Drops the messages table.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function down(): bool {
		return self::drop_table_common();
	}

	/**
	 * Get table name (without prefix)
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name_base(): string {
		return self::TABLE_NAME;
	}

	/**
	 * Get schema version option name
	 *
	 * @return string Option name for schema version.
	 */
	protected static function get_version_option_name(): string {
		return 'wyverncss_messages_schema_version';
	}
}
