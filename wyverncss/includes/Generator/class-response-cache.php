<?php
/**
 * Response Cache
 *
 * Caches CSS generation responses with Redis fallback to WordPress transients.
 *
 * @package WyvernCSS
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class ResponseCache
 *
 * Provides caching for CSS generation responses.
 */
class ResponseCache {

	/**
	 * Cache key prefix
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'wyverncss_css_';

	/**
	 * Default TTL (24 hours)
	 *
	 * @var int
	 */
	private const DEFAULT_TTL = 86400;

	/**
	 * Redis client instance
	 *
	 * @var \Redis|null
	 */
	private ?\Redis $redis = null;

	/**
	 * Whether Redis is available
	 *
	 * @var bool
	 */
	private bool $redis_available = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->initialize_redis();
	}

	/**
	 * Initialize Redis connection
	 */
	private function initialize_redis(): void {
		// Check if Redis extension is available.
		if ( ! class_exists( 'Redis' ) ) {
			return;
		}

		try {
			$this->redis = new \Redis();

			// Try to connect to Redis.
			$host = defined( 'WP_REDIS_HOST' ) ? WP_REDIS_HOST : '127.0.0.1';
			$port = defined( 'WP_REDIS_PORT' ) ? WP_REDIS_PORT : 6379;

			$connected = $this->redis->connect( $host, $port, 1 ); // 1 second timeout.

			if ( $connected ) {
				// Authenticate if password is set.
				if ( defined( 'WP_REDIS_PASSWORD' ) && ! empty( WP_REDIS_PASSWORD ) ) {
					$this->redis->auth( WP_REDIS_PASSWORD );
				}

				// Select database.
				$database = defined( 'WP_REDIS_DATABASE' ) ? WP_REDIS_DATABASE : 0;
				$this->redis->select( $database );

				$this->redis_available = true;
			}
		} catch ( \Exception $e ) {
			// Redis not available, will use transients.
			$this->redis           = null;
			$this->redis_available = false;

			// Only log Redis connection errors if not running in test environment.
			// Check for PHPUnit running or WP test environment.
			$is_test_env = class_exists( 'PHPUnit\Framework\TestCase' ) || defined( 'WP_TESTS_DIR' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && ! $is_test_env ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WyvernCSS: Redis connection failed: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Generate cache key
	 *
	 * @param string               $prompt User prompt.
	 * @param array<string, mixed> $context Element context.
	 *
	 * @return string Cache key.
	 */
	public function generate_key( string $prompt, array $context ): string {
		$data = array(
			'prompt'  => $prompt,
			'context' => $context,
		);

		$encoded = wp_json_encode( $data );
		if ( false === $encoded ) {
			$encoded = '';
		}

		$hash = hash( 'sha256', $encoded );

		return self::CACHE_PREFIX . substr( $hash, 0, 32 );
	}

	/**
	 * Get cached value
	 *
	 * @param string $key Cache key.
	 *
	 * @return array<string, mixed>|null Cached value or null.
	 */
	public function get( string $key ): ?array {
		if ( $this->redis_available && null !== $this->redis ) {
			return $this->get_from_redis( $key );
		}

		return $this->get_from_transient( $key );
	}

	/**
	 * Set cached value
	 *
	 * @param string               $key Cache key.
	 * @param array<string, mixed> $value Value to cache.
	 * @param int                  $ttl Time to live in seconds.
	 *
	 * @return bool True on success.
	 */
	public function set( string $key, array $value, int $ttl = self::DEFAULT_TTL ): bool {
		if ( $this->redis_available && null !== $this->redis ) {
			return $this->set_in_redis( $key, $value, $ttl );
		}

		return $this->set_in_transient( $key, $value, $ttl );
	}

	/**
	 * Delete cached value
	 *
	 * @param string $key Cache key.
	 *
	 * @return bool True on success.
	 */
	public function delete( string $key ): bool {
		if ( $this->redis_available && null !== $this->redis ) {
			return $this->delete_from_redis( $key );
		}

		return $this->delete_from_transient( $key );
	}

	/**
	 * Clear all cached values
	 *
	 * @return bool True on success.
	 */
	public function clear(): bool {
		if ( $this->redis_available && null !== $this->redis ) {
			return $this->clear_redis();
		}

		return $this->clear_transients();
	}

	/**
	 * Get value from Redis
	 *
	 * @param string $key Cache key.
	 *
	 * @return array<string, mixed>|null Cached value or null.
	 */
	private function get_from_redis( string $key ): ?array {
		if ( null === $this->redis ) {
			return null;
		}

		try {
			$value = $this->redis->get( $key );

			if ( false === $value ) {
				return null;
			}

			$decoded = json_decode( $value, true );

			return is_array( $decoded ) ? $decoded : null;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WyvernCSS Redis get error: ' . $e->getMessage() );
			}
			return null;
		}
	}

	/**
	 * Set value in Redis
	 *
	 * @param string               $key Cache key.
	 * @param array<string, mixed> $value Value.
	 * @param int                  $ttl TTL.
	 *
	 * @return bool True on success.
	 */
	private function set_in_redis( string $key, array $value, int $ttl ): bool {
		if ( null === $this->redis ) {
			return false;
		}

		try {
			$encoded = wp_json_encode( $value );
			if ( false === $encoded ) {
				return false;
			}
			$result = $this->redis->setex( $key, $ttl, $encoded );
			return is_int( $result ) ? $result > 0 : (bool) $result;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WyvernCSS Redis set error: ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Delete value from Redis
	 *
	 * @param string $key Cache key.
	 *
	 * @return bool True on success.
	 */
	private function delete_from_redis( string $key ): bool {
		if ( null === $this->redis ) {
			return false;
		}

		try {
			$result = $this->redis->del( $key );
			return is_int( $result ) && $result > 0;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WyvernCSS Redis delete error: ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Clear all Redis keys with prefix
	 *
	 * @return bool True on success.
	 */
	private function clear_redis(): bool {
		if ( null === $this->redis ) {
			return false;
		}

		try {
			$pattern = self::CACHE_PREFIX . '*';
			$keys    = $this->redis->keys( $pattern );

			if ( is_array( $keys ) && ! empty( $keys ) ) {
				$result = $this->redis->del( $keys );
				return is_int( $result ) && $result > 0;
			}

			return true;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WyvernCSS Redis clear error: ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Get value from WordPress transient
	 *
	 * @param string $key Cache key.
	 *
	 * @return array<string, mixed>|null Cached value or null.
	 */
	private function get_from_transient( string $key ): ?array {
		$value = get_transient( $key );

		return false !== $value && is_array( $value ) ? $value : null;
	}

	/**
	 * Set value in WordPress transient
	 *
	 * @param string               $key Cache key.
	 * @param array<string, mixed> $value Value.
	 * @param int                  $ttl TTL.
	 *
	 * @return bool True on success.
	 */
	private function set_in_transient( string $key, array $value, int $ttl ): bool {
		return set_transient( $key, $value, $ttl );
	}

	/**
	 * Delete value from WordPress transient
	 *
	 * @param string $key Cache key.
	 *
	 * @return bool True on success.
	 */
	private function delete_from_transient( string $key ): bool {
		return delete_transient( $key );
	}

	/**
	 * Clear all WordPress transients with prefix
	 *
	 * @return bool True on success.
	 */
	private function clear_transients(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching.
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%'
			)
		);

		return false !== $result;
	}

	/**
	 * Check if Redis is available
	 *
	 * @return bool True if Redis is available.
	 */
	public function is_redis_available(): bool {
		return $this->redis_available;
	}

	/**
	 * Get cache statistics
	 *
	 * @return array<string, mixed> Cache statistics.
	 */
	public function get_stats(): array {
		$stats = array(
			'backend' => $this->redis_available ? 'redis' : 'transient',
		);

		if ( $this->redis_available && null !== $this->redis ) {
			try {
				$info           = $this->redis->info();
				$keys           = $this->redis->keys( self::CACHE_PREFIX . '*' );
				$stats['redis'] = array(
					'connected' => true,
					'keys'      => is_array( $keys ) ? count( $keys ) : 0,
					'memory'    => is_array( $info ) && isset( $info['used_memory_human'] ) ? $info['used_memory_human'] : 'unknown',
				);
			} catch ( \Exception $e ) {
				$stats['redis'] = array( 'connected' => false );
			}
		}

		return $stats;
	}
}
