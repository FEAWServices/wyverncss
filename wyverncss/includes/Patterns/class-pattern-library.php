<?php
/**
 * Pattern Library
 *
 * Main orchestrator for the pattern matching engine.
 *
 * @package WyvernCSS
 * @subpackage Patterns
 */

declare(strict_types=1);

namespace WyvernCSS\Patterns;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\Patterns\Patterns\BorderPatterns;
use WyvernCSS\Patterns\Patterns\ButtonPatterns;
use WyvernCSS\Patterns\Patterns\ColorPatterns;
use WyvernCSS\Patterns\Patterns\LayoutPatterns;
use WyvernCSS\Patterns\Patterns\ShadowPatterns;
use WyvernCSS\Patterns\Patterns\SpacingPatterns;
use WyvernCSS\Patterns\Patterns\TypographyPatterns;

/**
 * Pattern Library Class
 *
 * The main entry point for pattern matching functionality.
 * Coordinates pattern loading, matching, and caching.
 *
 * Features:
 * - 100+ CSS patterns across 6 categories
 * - Pattern matching with confidence scoring
 * - Multi-pattern combination support
 * - Redis caching for performance
 * - <50ms response time target
 *
 * @since 1.0.0
 */
class PatternLibrary {

	/**
	 * All loaded patterns.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $patterns = array();

	/**
	 * Pattern matcher instance.
	 *
	 * @var PatternMatcher|null
	 */
	private ?PatternMatcher $matcher = null;

	/**
	 * Pattern cache instance.
	 *
	 * @var PatternCache
	 */
	private PatternCache $cache;

	/**
	 * Whether patterns have been loaded.
	 *
	 * @var bool
	 */
	private bool $patterns_loaded = false;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->cache = new PatternCache();
	}

	/**
	 * Match a prompt against the pattern library.
	 *
	 * PatternMatcher returns additional metadata fields beyond the core structure.
	 *
	 * @since 1.0.0
	 * @param string $prompt The user's natural language prompt.
	 * @return array{css: array<string, string>, confidence: int, matched_patterns: array<int, string>, source: string} Match result.
	 * @phpstan-return array<string, mixed>
	 */
	public function match( string $prompt ): array {
		// Check cache first.
		$cached = $this->cache->get( $prompt );
		if ( null !== $cached ) {
			$cached['source'] = 'pattern_library_cache';
			return $cached;
		}

		// Load patterns if not loaded.
		if ( ! $this->patterns_loaded ) {
			$this->load_patterns();
		}

		// Initialize matcher if not initialized.
		if ( null === $this->matcher ) {
			$this->matcher = new PatternMatcher( $this->patterns );
		}

		// Perform matching.
		$result           = $this->matcher->match( $prompt );
		$result['source'] = 'pattern_library';

		// Cache the result - extract only the core fields for caching.
		$cache_data = array(
			'css'              => $result['css'] ?? array(),
			'confidence'       => $result['confidence'] ?? 0,
			'matched_patterns' => $result['matched_patterns'] ?? array(),
		);
		$this->cache->set( $prompt, $cache_data );

		return $result;
	}

	/**
	 * Get all patterns from all categories.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, string>> All patterns.
	 */
	public function get_all_patterns(): array {
		if ( ! $this->patterns_loaded ) {
			$this->load_patterns();
		}

		return $this->patterns;
	}

	/**
	 * Get pattern count.
	 *
	 * @since 1.0.0
	 * @return int Total number of patterns.
	 */
	public function get_pattern_count(): int {
		if ( ! $this->patterns_loaded ) {
			$this->load_patterns();
		}

		return count( $this->patterns );
	}

	/**
	 * Get pattern count by category.
	 *
	 * @since 1.0.0
	 * @return array<string, int> Pattern counts per category.
	 */
	public function get_pattern_counts_by_category(): array {
		return array(
			'colors'     => count( ColorPatterns::get_patterns() ),
			'typography' => count( TypographyPatterns::get_patterns() ),
			'spacing'    => count( SpacingPatterns::get_patterns() ),
			'borders'    => count( BorderPatterns::get_patterns() ),
			'shadows'    => count( ShadowPatterns::get_patterns() ),
			'layouts'    => count( LayoutPatterns::get_patterns() ),
		);
	}

	/**
	 * Clear pattern cache.
	 *
	 * @since 1.0.0
	 * @return bool True on success.
	 */
	public function clear_cache(): bool {
		return $this->cache->clear();
	}

	/**
	 * Load all patterns from all categories.
	 *
	 * Patterns are loaded lazily on first use for performance.
	 *
	 * @since 1.0.0
	 * @return void */
	private function load_patterns(): void {
		if ( $this->patterns_loaded ) {
			return;
		}

		// Load patterns from each category.
		$this->patterns = array_merge(
			$this->patterns,
			ColorPatterns::get_patterns(),
			TypographyPatterns::get_patterns(),
			SpacingPatterns::get_patterns(),
			BorderPatterns::get_patterns(),
			ShadowPatterns::get_patterns(),
			LayoutPatterns::get_patterns(),
			ButtonPatterns::get_patterns()
		);

		$this->patterns_loaded = true;
	}

	/**
	 * Get patterns by category.
	 *
	 * @since 1.0.0
	 * @param string $category Category name (colors, typography, spacing, borders, shadows, layouts, buttons).
	 * @return array<string, array<string, string>> Patterns for the category.
	 */
	public function get_patterns_by_category( string $category ): array {
		$patterns = match ( $category ) {
			'colors'     => ColorPatterns::get_patterns(),
			'typography' => TypographyPatterns::get_patterns(),
			'spacing'    => SpacingPatterns::get_patterns(),
			'borders'    => BorderPatterns::get_patterns(),
			'shadows'    => ShadowPatterns::get_patterns(),
			'layouts'    => LayoutPatterns::get_patterns(),
			'buttons'    => ButtonPatterns::get_patterns(),
			default      => array(),
		};

		return $patterns;
	}

	/**
	 * Validate if a prompt matches any pattern with high confidence.
	 *
	 * @since 1.0.0
	 * @param string $prompt The user prompt.
	 * @param int    $min_confidence Minimum confidence threshold (default 70).
	 * @return bool True if pattern matches with sufficient confidence.
	 */
	public function can_handle_prompt( string $prompt, int $min_confidence = 70 ): bool {
		$result = $this->match( $prompt );
		return $result['confidence'] >= $min_confidence;
	}
}
