<?php
/**
 * Conversations Table Migration
 *
 * Creates the wyverncss_conversations table for storing AI chat conversations.
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
 * Class Migration_Conversations
 *
 * Handles creation and updates of the conversations table.
 */
class Migration_Conversations extends Migration_Base {

	/**
	 * Table name (without prefix)
	 */
	private const TABLE_NAME = 'wyverncss_conversations';

	/**
	 * Current schema version
	 */
	private const SCHEMA_VERSION = '1.0.0';

	/**
	 * Run the migration
	 *
	 * Creates the conversations table if it doesn't exist.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function up(): bool {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT 'New Conversation',
			model VARCHAR(100) NOT NULL DEFAULT 'anthropic/claude-3.5-sonnet',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user_id (user_id),
			KEY idx_created_at (created_at),
			KEY idx_updated_at (updated_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) !== $table_name ) {
			return false;
		}

		// Store schema version.
		update_option( 'wyverncss_conversations_schema_version', self::SCHEMA_VERSION );

		return true;
	}

	/**
	 * Rollback the migration
	 *
	 * Drops the conversations table.
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
		return 'wyverncss_conversations_schema_version';
	}
}
