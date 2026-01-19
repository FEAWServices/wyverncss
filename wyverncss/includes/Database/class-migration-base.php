<?php
/**
 * Base Migration Class
 *
 * Provides common functionality for all database migrations.
 * Implements DRY principles by extracting shared methods.
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
 * Abstract class Migration_Base
 *
 * Base class for all database migrations.
 * Provides common utility methods for table management.
 */
abstract class Migration_Base {

	/**
	 * Get table name (without prefix)
	 *
	 * Must be implemented by child classes.
	 *
	 * @return string Table name without prefix.
	 */
	abstract protected static function get_table_name_base(): string;

	/**
	 * Get schema version option name
	 *
	 * Must be implemented by child classes.
	 *
	 * @return string Option name for schema version.
	 */
	abstract protected static function get_version_option_name(): string;

	/**
	 * Get table name with prefix
	 *
	 * @return string Full table name with WordPress prefix.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . static::get_table_name_base();
	}

	/**
	 * Check if table exists
	 *
	 * @return bool True if table exists, false otherwise.
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table_name = static::get_table_name();

		return $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $table_name )
			)
		) === $table_name;
	}

	/**
	 * Get current schema version
	 *
	 * @return string Schema version or '0.0.0' if not set.
	 */
	public static function get_schema_version(): string {
		return get_option( static::get_version_option_name(), '0.0.0' );
	}

	/**
	 * Drop the table
	 *
	 * Common implementation for dropping a table and cleaning up its version option.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected static function drop_table_common(): bool {
		global $wpdb;

		$table_name = static::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$result = $wpdb->query(
			$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name )
		);

		delete_option( static::get_version_option_name() );

		return false !== $result;
	}
}
