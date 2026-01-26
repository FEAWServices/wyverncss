<?php
/**
 * Pattern Cache
 *
 * Provides caching for pattern matches using Redis with transient fallback.
 *
 * @package WyvernCSS
 * @subpackage Patterns
 */

declare(strict_types=1);

namespace WyvernCSS\Patterns;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Pattern Cache Class
 *
 * Implements caching strategy:
 * - Primary: Redis (if available)
 * - Fallback: WordPress transients
 * - TTL: 1 hour (3600 seconds)
 * - Key format: wyverncss:pattern:{hash}
 *
 * @since 1.0.0
 */
class PatternCache {

	/**
	 * Cache key prefix.
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'wyverncss_pattern_';

	/**
	 * Cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 3600;

	/**
	 * Whether Redis is available.
	 *
	 * @var bool|null
	 */
	private ?bool $redis_available = null;

	/**
	 * Get cached pattern match result.
	 *
	 * @since 1.0.0
	 * @param string $prompt The user prompt.
	 * @return array{css: array<string, string>, confidence: int, matched_patterns: array<int, string>}|null Cached result or null.
	 */
	public function get( string $prompt ): ?array {
		$cache_key = $this->get_cache_key( $prompt );

		if ( $this->is_redis_available() ) {
			return $this->get_from_redis( $cache_key );
		}

		return $this->get_from_transient( $cache_key );
	}

	/**
	 * Set pattern match result in cache.
	 *
	 * @since 1.0.0
	 * @param string                                                                                   $prompt The user prompt.
	 * @param array{css: array<string, string>, confidence: int, matched_patterns: array<int, string>} $result The match result.
	 * @return bool True on success, false on failure.
	 */
	public function set( string $prompt, array $result ): bool {
		$cache_key = $this->get_cache_key( $prompt );

		if ( $this->is_redis_available() ) {
			return $this->set_in_redis( $cache_key, $result );
		}

		return $this->set_in_transient( $cache_key, $result );
	}

	/**
	 * Clear all pattern cache.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function clear(): bool {
		global $wpdb;

		if ( $this->is_redis_available() ) {
			$this->clear_redis();
		}

		// Clear transients.
		$option_name = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$option_name
			)
		);

		return true;
	}

	/**
	 * Generate cache key from prompt.
	 *
	 * @since 1.0.0
	 * @param string $prompt The user prompt.
	 * @return string Cache key.
	 */
	private function get_cache_key( string $prompt ): string {
		return self::CACHE_PREFIX . md5( strtolower( trim( $prompt ) ) );
	}

	/**
	 * Check if Redis is available.
	 *
	 * @since 1.0.0
	 * @return bool True if Redis is available.
	 */
	private function is_redis_available(): bool {
		if ( null !== $this->redis_available ) {
			return $this->redis_available;
		}

		// Check if Redis object cache is available.
		// This is a common pattern in WordPress with Redis plugins.
		if ( ! function_exists( 'wp_cache_get' ) ) {
			$this->redis_available = false;
			return false;
		}

		// Try to determine if Redis is actually being used.
		// Most Redis object cache plugins will define a constant or global.
		$this->redis_available = false;

		// Check for common Redis object cache plugins.
		if ( defined( 'WP_REDIS_DISABLED' ) && WP_REDIS_DISABLED ) {
			$this->redis_available = false;
		} elseif ( class_exists( 'Redis' ) && function_exists( 'wp_cache_add_redis_hash_groups' ) ) {
			$this->redis_available = true;
		} elseif ( function_exists( 'wp_cache_add_non_persistent_groups' ) ) {
			// Persistent object cache is available (likely Redis or Memcached).
			$this->redis_available = true;
		}

		return $this->redis_available;
	}

	/**
	 * Get cached value from Redis.
	 *
	 * @since 1.0.0
	 * @param string $cache_key The cache key.
	 * @return array{css: array<string, string>, confidence: int, matched_patterns: array<int, string>}|null Cached result or null.
	 */
	private function get_from_redis( string $cache_key ): ?array {
		$cached = wp_cache_get( $cache_key, 'wyverncss_patterns' );
		return $this->validate_cached_result( $cached );
	}

	/**
	 * Set value in Redis cache.
	 *
	 * @since 1.0.0
	 * @param string                                                                                   $cache_key The cache key.
	 * @param array{css: array<string, string>, confidence: int, matched_patterns: array<int, string>} $result The match result.
	 * @return bool True on success.
	 */
	private function set_in_redis( string $cache_key, array $result ): bool {
		return wp_cache_set( $cache_key, $result, 'wyverncss_patterns', self::CACHE_TTL );
	}

	/**
	 * Clear Redis cache.
	 *
	 * @since 1.0.0
	 * @return void */
	private function clear_redis(): void {
		// WordPress doesn't provide a native way to flush a specific cache group.
		// We'll rely on TTL expiration and transient clearing.
		wp_cache_flush();
	}

	/**
	 * Validate cached result structure.
	 *
	 * Ensures the cached data has the expected structure with proper types
	 * before returning it. This prevents type errors from corrupted cache data.
	 *
	 * @since 1.0.0
	 * @param mixed $cached The cached value to validate.
	 * @return array{css: array<string, string>, confidence: int, matched_patterns: array<int, string>}|null Validated result or null.
	 */
	private function validate_cached_result( $cached ): ?array {
		if ( false === $cached || ! is_array( $cached ) ) {
			return null;
		}

		// Validate structure and types.
		if ( ! isset( $cached['css'], $cached['confidence'], $cached['matched_patterns'] ) ||
			! is_array( $cached['css'] ) ||
			! is_int( $cached['confidence'] ) ||
			! is_array( $cached['matched_patterns'] ) ) {
			return null;
		}

		/**
		 * Return type annotation.
		 *
		 * @var array{css: array<string, string>, confidence: int, matched_patterns: array<int, string>}
		 */
		return $cached;
	}

	/**
	 * Get cached value from transient.
	 *
	 * @since 1.0.0
	 * @param string $cache_key The cache key.
	 * @return array{css: array<string, string>, confidence: int, matched_patterns: array<int, string>}|null Cached result or null.
	 */
	private function get_from_transient( string $cache_key ): ?array {
		$cached = get_transient( $cache_key );
		return $this->validate_cached_result( $cached );
	}

	/**
	 * Set value in transient.
	 *
	 * @since 1.0.0
	 * @param string                                                                                   $cache_key The cache key.
	 * @param array{css: array<string, string>, confidence: int, matched_patterns: array<int, string>} $result The match result.
	 * @return bool True on success.
	 */
	private function set_in_transient( string $cache_key, array $result ): bool {
		return set_transient( $cache_key, $result, self::CACHE_TTL );
	}
}
