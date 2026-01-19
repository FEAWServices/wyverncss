<?php
/**
 * Cache Manager
 *
 * Manages caching for MCP tool responses using WordPress Transients.
 *
 * @package WyvernCSS\MCP
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\MCP;

/**
 * Cache Manager Class
 *
 * Handles caching of MCP tool responses for improved performance.
 */
class CacheManager {

	/**
	 * Cache key prefix
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'wyverncss_mcp_';

	/**
	 * Get cached result
	 *
	 * @param string              $tool_name Tool name.
	 * @param array<string,mixed> $params Tool parameters.
	 * @return mixed|false Cached result or false if not found.
	 */
	public function get( string $tool_name, array $params ) {
		$cache_key = $this->generate_cache_key( $tool_name, $params );

		// Try object cache first (Redis/Memcached if available).
		$cached = wp_cache_get( $cache_key, 'wyverncss_mcp' );

		if ( false !== $cached ) {
			return $cached;
		}

		// Fallback to transient.
		return get_transient( $cache_key );
	}

	/**
	 * Set cached result
	 *
	 * @param string              $tool_name Tool name.
	 * @param array<string,mixed> $params Tool parameters.
	 * @param mixed               $result Result to cache.
	 * @param int                 $ttl Cache TTL in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function set( string $tool_name, array $params, $result, int $ttl ): bool {
		if ( 0 === $ttl ) {
			return false; // Don't cache.
		}

		$cache_key = $this->generate_cache_key( $tool_name, $params );

		// Set in object cache (if available).
		wp_cache_set( $cache_key, $result, 'wyverncss_mcp', $ttl );

		// Set transient as fallback.
		return set_transient( $cache_key, $result, $ttl );
	}

	/**
	 * Delete cached result
	 *
	 * @param string              $tool_name Tool name.
	 * @param array<string,mixed> $params Tool parameters.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $tool_name, array $params ): bool {
		$cache_key = $this->generate_cache_key( $tool_name, $params );

		// Delete from object cache.
		wp_cache_delete( $cache_key, 'wyverncss_mcp' );

		// Delete transient.
		return delete_transient( $cache_key );
	}

	/**
	 * Generate cache key
	 *
	 * Creates a unique cache key based on tool name and parameters.
	 *
	 * @param string              $tool_name Tool name.
	 * @param array<string,mixed> $params Tool parameters.
	 * @return string Cache key.
	 */
	public function generate_cache_key( string $tool_name, array $params ): string {
		// Sort params for consistent hashing.
		ksort( $params );

		// Create hash of parameters.
		$json_params = wp_json_encode( $params );
		$param_hash  = md5( false !== $json_params ? $json_params : '' );

		return self::CACHE_PREFIX . $tool_name . '_' . $param_hash;
	}

	/**
	 * Clear all MCP caches
	 *
	 * @return void */
	public function clear_all(): void {
		global $wpdb;

		// Delete all transients matching our prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%'
			)
		);

		// Flush object cache group.
		wp_cache_flush_group( 'wyverncss_mcp' );
	}

	/**
	 * Clear caches for specific tool
	 *
	 * @param string $tool_name Tool name.
	 * @return void */
	public function clear_tool( string $tool_name ): void {
		global $wpdb;

		$pattern = self::CACHE_PREFIX . $tool_name . '_%';

		// Delete transients for this tool.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::CACHE_PREFIX . $tool_name . '_' ) . '%'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX . $tool_name . '_' ) . '%'
			)
		);
	}

	/**
	 * Check if result is cached
	 *
	 * @param string              $tool_name Tool name.
	 * @param array<string,mixed> $params Tool parameters.
	 * @return bool True if cached, false otherwise.
	 */
	public function has( string $tool_name, array $params ): bool {
		return false !== $this->get( $tool_name, $params );
	}

	/**
	 * Get cache statistics
	 *
	 * @return array<string,mixed> Cache statistics.
	 */
	public function get_stats(): array {
		global $wpdb;

		// Count cached items.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%'
			)
		);

		return array(
			'total_items' => $count,
			'prefix'      => self::CACHE_PREFIX,
		);
	}
}
