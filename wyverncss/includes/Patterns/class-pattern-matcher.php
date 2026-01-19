<?php
/**
 * Pattern Matcher
 *
 * Implements the pattern matching algorithm with confidence scoring.
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
 * Pattern Matcher Class
 *
 * Responsible for:
 * - Normalizing and tokenizing user prompts
 * - Matching prompts against pattern library
 * - Calculating confidence scores
 * - Combining multiple pattern matches
 *
 * @since 1.0.0
 */
class PatternMatcher {

	/**
	 * Common stop words to filter out during tokenization.
	 *
	 * @var array<int, string>
	 */
	private const STOP_WORDS = array(
		'the',
		'a',
		'an',
		'this',
		'that',
		'with',
		'and',
		'or',
		'but',
		'in',
		'on',
		'at',
		'to',
		'for',
		'of',
		'it',
		'make',
		'give',
		'add',
		'set',
		'apply',
	);

	/**
	 * Common words that should not alone trigger matches.
	 *
	 * @var array<int, string>
	 */
	private const WEAK_MATCH_WORDS = array(
		'text',
		'background',
		'color',
		'style',
	);

	/**
	 * Word variations for fuzzy matching.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const WORD_VARIATIONS = array(
		'blue'    => array( 'blueish', 'bluish' ),
		'red'     => array( 'reddish' ),
		'green'   => array( 'greenish' ),
		'large'   => array( 'big', 'huge', 'bigger' ),
		'small'   => array( 'tiny', 'little', 'smaller' ),
		'center'  => array( 'centered', 'middle' ),
		'round'   => array( 'rounded', 'circular' ),
		'shadow'  => array( 'shadowed', 'shaded' ),
		'border'  => array( 'bordered', 'outline' ),
		'padding' => array( 'padded', 'space', 'spacing' ),
		'margin'  => array( 'margined' ),
		'bold'    => array( 'bolder', 'heavy', 'thick' ),
		'light'   => array( 'lighter', 'thin' ),
	);

	/**
	 * All loaded patterns from all categories.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $patterns = array();

	/**
	 * Pattern database instance.
	 *
	 * Reserved for future PatternDatabase implementation.
	 * Part of TDD stub API - used in constructor but not yet in main logic.
	 *
	 * @var PatternDatabase|null
	 * @phpstan-ignore-next-line property.onlyWritten
	 */
	private ?PatternDatabase $database = null;

	/**
	 * Confidence threshold for pattern matching.
	 *
	 * @var int
	 */
	private int $confidence_threshold = 70;

	/**
	 * Cache TTL in seconds.
	 *
	 * Reserved for future caching implementation. Part of TDD stub API.
	 *
	 * @var int
	 * @phpstan-ignore-next-line property.onlyWritten
	 */
	private int $cache_ttl = 300;

	/**
	 * Matching mode.
	 *
	 * Reserved for future mode switching (pattern/hybrid/ai). Part of TDD stub API.
	 *
	 * @var string
	 * @phpstan-ignore-next-line property.onlyWritten
	 */
	private string $mode = 'pattern';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param array<string, array<string, string>>|PatternDatabase $patterns All patterns from pattern library or PatternDatabase instance.
	 */
	public function __construct( $patterns ) {
		if ( $patterns instanceof PatternDatabase ) {
			$this->database = $patterns;
			$this->patterns = array(); // Will be loaded from database as needed.
		} else {
			$this->patterns = $patterns;
		}
	}

	/**
	 * Match a prompt against all patterns.
	 *
	 * @since 1.0.0
	 * @param string               $prompt The user's natural language prompt.
	 * @param array<string, mixed> $context Optional context.
	 * @param array<string, mixed> $preferences Optional user preferences (reserved for future use).
	 * @return array<string, mixed> Match result.
	 */
	public function match( string $prompt, array $context = array(), array $preferences = array() ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Reserved for future implementation.
		$start_time        = microtime( true );
		$normalized_prompt = $this->normalize_prompt( $prompt );

		// Try exact match first.
		if ( isset( $this->patterns[ $normalized_prompt ] ) ) {
			return $this->format_result(
				array(
					'css'              => $this->patterns[ $normalized_prompt ],
					'confidence'       => 100,
					'matched_patterns' => array( $normalized_prompt ),
					'pattern_id'       => $normalized_prompt,
				),
				$start_time,
				$context
			);
		}

		// Try to find best matches.
		$matches = $this->find_matches( $normalized_prompt );

		if ( empty( $matches ) ) {
			return $this->format_result(
				array(
					'css'              => array(),
					'confidence'       => 0,
					'matched_patterns' => array(),
				),
				$start_time,
				$context
			);
		}

		// Combine multiple matches.
		$result               = $this->combine_matches( $matches, $normalized_prompt );
		$result['pattern_id'] = $matches[0]['pattern'] ?? '';

		return $this->format_result( $result, $start_time, $context );
	}

	/**
	 * Format match result with additional metadata.
	 *
	 * @param array<string, mixed> $result Base result.
	 * @param float                $start_time Start time.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Formatted result.
	 */
	private function format_result( array $result, float $start_time, array $context ): array {
		$confidence = $result['confidence'] ?? 0;

		// Add metadata.
		$result['source']        = $result['source'] ?? 'pattern_matcher';
		$result['match_time_ms'] = ( microtime( true ) - $start_time ) * 1000;

		// Add recommendation based on confidence.
		if ( $confidence >= $this->confidence_threshold ) {
			$result['recommendation'] = 'pattern';
		} elseif ( $confidence > 0 && $confidence < $this->confidence_threshold ) {
			$result['recommendation']           = 'needs_ai_fallback';
			$result['fallback_reason']          = 'Low confidence score';
			$result['partial_match']            = true;
			$result['ai_enhancement_suggested'] = true;
		} else {
			$result['recommendation'] = 'full_ai_generation';
		}

		// Add confidence level.
		if ( $confidence >= 80 ) {
			$result['confidence_level'] = 'high';
		} elseif ( $confidence >= 60 ) {
			$result['confidence_level'] = 'medium';
		} elseif ( $confidence > 0 ) {
			$result['confidence_level'] = 'low';
		} else {
			$result['confidence_level'] = 'none';
		}

		// Preserve context if provided.
		if ( ! empty( $context ) ) {
			$result['context'] = $context;
		}

		return $result;
	}

	/**
	 * Normalize a prompt for matching.
	 *
	 * @since 1.0.0
	 * @param string $prompt The raw prompt.
	 * @return string Normalized prompt.
	 */
	private function normalize_prompt( string $prompt ): string {
		// Convert to lowercase.
		$normalized = strtolower( trim( $prompt ) );

		// Remove punctuation (except hyphens which are used in CSS property names).
		$normalized = preg_replace( '/[^\w\s-]/', '', $normalized );

		// Remove extra whitespace.
		$normalized = preg_replace( '/\s+/', ' ', $normalized );

		return $normalized ?? '';
	}

	/**
	 * Tokenize a prompt into words.
	 *
	 * @since 1.0.0
	 * @param string $prompt The normalized prompt.
	 * @return array<int, string> Tokens.
	 */
	private function tokenize( string $prompt ): array {
		$words = explode( ' ', $prompt );

		// Filter out stop words and empty strings.
		return array_values(
			array_filter(
				$words,
				function ( string $word ): bool {
					return '' !== $word && ! in_array( $word, self::STOP_WORDS, true );
				}
			)
		);
	}

	/**
	 * Find all matching patterns with scores.
	 *
	 * @since 1.0.0
	 * @param string $prompt The normalized prompt.
	 * @return array<int, array{pattern: string, css: array<string, string>, score: int}> Matches with scores.
	 */
	private function find_matches( string $prompt ): array {
		$tokens  = $this->tokenize( $prompt );
		$matches = array();

		foreach ( $this->patterns as $pattern_key => $css ) {
			$score = $this->calculate_match_score( $prompt, $tokens, $pattern_key );

			if ( $score > 0 ) {
				$matches[] = array(
					'pattern'     => $pattern_key,
					'css'         => $css,
					'score'       => $score,
					'token_count' => count( $this->tokenize( $pattern_key ) ),
				);
			}
		}

		// Sort by score descending, then by token count ascending (prefer simpler patterns).
		usort(
			$matches,
			function ( array $a, array $b ): int {
				$score_diff = $b['score'] - $a['score'];
				if ( 0 !== $score_diff ) {
					return $score_diff;
				}
				// If scores are equal, prefer pattern with fewer tokens.
				return $a['token_count'] - $b['token_count'];
			}
		);

		return $matches;
	}

	/**
	 * Calculate match score between prompt and pattern.
	 *
	 * The algorithm penalizes:
	 * - Complex prompts with low coverage (user asked for more than pattern provides)
	 * - Substring-only matches without semantic relationship
	 * - Weak-word-only matches
	 *
	 * @since 1.0.0
	 * @param string             $prompt The normalized prompt.
	 * @param array<int, string> $tokens The tokenized prompt.
	 * @param string             $pattern_key The pattern key to match against.
	 * @return int Score (0-100).
	 */
	private function calculate_match_score( string $prompt, array $tokens, string $pattern_key ): int {
		// Exact match.
		if ( $prompt === $pattern_key ) {
			return 100;
		}

		// Tokenize pattern and compare tokens.
		$pattern_tokens = $this->tokenize( $pattern_key );

		if ( empty( $pattern_tokens ) ) {
			return 0;
		}

		$matched_tokens       = 0;
		$fuzzy_matches        = 0;
		$found_pattern_tokens = 0;
		$weak_matches         = 0;
		$exact_matches        = 0; // Track exact token matches for accuracy scoring.
		$variation_matches    = 0; // Track word variation matches (e.g., "round" in WORD_VARIATIONS has "rounded").
		$root_word_matches    = 0; // Track root word matches (e.g., stemming "rounded" -> "round").

		// Check how many pattern tokens are found in the prompt.
		foreach ( $pattern_tokens as $pattern_token ) {
			$found = false;

			// Direct token match.
			if ( in_array( $pattern_token, $tokens, true ) ) {
				++$found_pattern_tokens;
				++$exact_matches;
				// Track if this is a weak word.
				if ( in_array( $pattern_token, self::WEAK_MATCH_WORDS, true ) ) {
					++$weak_matches;
				}
				$found = true;
				continue;
			}

			// Check word variations (only explicit variations, not substrings).
			// NOTE: Word variations are NOT exact matches - "round" in prompt might mean
			// "round shape", "round number", not necessarily "rounded corners".
			foreach ( $tokens as $token ) {
				if ( $this->is_explicit_word_variation( $token, $pattern_token ) ) {
					++$found_pattern_tokens;
					++$variation_matches; // Variations are semantic inferences, not exact.
					$found = true;
					break;
				}
			}

			// Check root word matching (e.g., "rounded" matches "round") - stricter than before.
			if ( ! $found ) {
				foreach ( $tokens as $token ) {
					if ( $this->is_root_word_match( $token, $pattern_token ) ) {
						++$found_pattern_tokens;
						++$root_word_matches; // Track separately - less precise.
						break;
					}
				}
			}
		}

		// Also check how many prompt tokens are found in pattern.
		foreach ( $tokens as $token ) {
			// Direct token match.
			if ( in_array( $token, $pattern_tokens, true ) ) {
				++$matched_tokens;
				continue;
			}

			// Check explicit word variations.
			foreach ( $pattern_tokens as $pattern_token ) {
				if ( $this->is_explicit_word_variation( $token, $pattern_token ) ) {
					++$fuzzy_matches;
					break;
				}
			}

			// Check root word matches.
			foreach ( $pattern_tokens as $pattern_token ) {
				if ( $this->is_root_word_match( $token, $pattern_token ) ) {
					++$fuzzy_matches;
					break;
				}
			}
		}

		$pattern_token_count = count( $pattern_tokens );
		$token_count         = count( $tokens );

		// If only weak words matched and not all pattern tokens, heavily penalize.
		$strong_matches = $found_pattern_tokens - $weak_matches;
		if ( 0 === $strong_matches && $found_pattern_tokens < $pattern_token_count ) {
			// Only weak words matched and pattern incomplete.
			return 0;
		}

		// Calculate pattern coverage (how much of the pattern is found in prompt).
		$pattern_coverage = $pattern_token_count > 0 ? $found_pattern_tokens / $pattern_token_count : 0;

		// Calculate prompt coverage (how much of the prompt matches the pattern).
		$prompt_coverage = $token_count > 0 ? ( $matched_tokens + $fuzzy_matches ) / $token_count : 0;

		// COMPLEXITY PENALTY: If prompt is complex (many tokens) but pattern is simple,
		// penalize heavily. This prevents simple patterns from matching complex requests.
		// Note: $pattern_token_count is always > 0 here (checked at line 346).
		$complexity_ratio = $token_count / $pattern_token_count;

		// Priority: All pattern tokens found = high score.
		if ( $pattern_coverage >= 1.0 ) {
			// All pattern tokens found - high confidence.
			$base = 75 + (int) round( $prompt_coverage * 20 );

			// INFERENCE MATCH PENALTY: If ALL matches came from word variations or root word
			// matching (e.g., "round" matching "rounded corners"), penalize heavily.
			// These are semantic inferences, not exact matches. "round" alone doesn't
			// necessarily mean "rounded corners" - could mean round shape, round number, etc.
			$inference_matches = $variation_matches + $root_word_matches;
			if ( $inference_matches > 0 && 0 === $exact_matches ) {
				// All matches are from inference - significant penalty.
				$base = (int) round( $base * 0.55 ); // 45% penalty - brings 95 -> 52.
			} elseif ( $inference_matches > $exact_matches ) {
				// More inference matches than exact - moderate penalty.
				$base = (int) round( $base * 0.75 ); // 25% penalty.
			}

			// Penalty for weak-only matches.
			if ( 0 === $strong_matches ) {
				$base = (int) round( $base * 0.6 ); // 40% penalty.
			}

			// COMPLEXITY PENALTY: Only apply for very complex prompts (5+ times more tokens).
			// For moderately complex prompts (2-4x), we allow pattern matching to combine
			// multiple patterns to fulfill the request.
			if ( $complexity_ratio >= 5.0 ) {
				// Severe penalty: pattern is way too simple for very complex request.
				$penalty = min( 0.35, 0.1 * ( $complexity_ratio - 4 ) );
				$base    = (int) round( $base * ( 1 - $penalty ) );
			}

			// LOW PROMPT COVERAGE PENALTY: If we matched the pattern but most of
			// the user's request wasn't addressed AND the request is very long,
			// reduce confidence. This targets cases like the brutalist design prompt.
			if ( $prompt_coverage < 0.2 && $token_count > 8 ) {
				// Very long request with very few matches - likely needs AI.
				$base = (int) round( $base * 0.5 );
			} elseif ( $prompt_coverage < 0.25 && $token_count > 6 ) {
				// Long request with few matches.
				$base = (int) round( $base * 0.65 );
			}

			return min( 95, max( 0, $base ) );
		}

		// Partial pattern match - only return if pattern coverage is meaningful.
		// For multi-word patterns, require ALL tokens when the prompt also has multiple tokens.
		// This prevents "black underline" from matching "black background".
		// But allows "rounded" to match "rounded corners" (single word prompt).
		if ( $pattern_token_count >= 2 && $token_count >= 2 && $pattern_coverage < 1.0 ) {
			// Multi-word prompt with multi-word pattern, but not all pattern words found - reject.
			return 0;
		}

		if ( $pattern_coverage >= 0.5 ) {
			// Base score: 40-70 depending on how much of pattern is found.
			$base_score = (int) round( 40 + ( $pattern_coverage * 30 ) );
			$bonus      = (int) round( $prompt_coverage * 20 );
			$score      = $base_score + $bonus;

			// INFERENCE MATCH PENALTY: Same logic as full coverage - if matches are all
			// from variations/root words, user intent is ambiguous. E.g., "round" matching
			// "rounded corners" with 50% coverage should still be penalized.
			$inference_matches = $variation_matches + $root_word_matches;
			if ( $inference_matches > 0 && 0 === $exact_matches ) {
				// All matches are from inference - significant penalty.
				$score = (int) round( $score * 0.55 ); // 45% penalty.
			} elseif ( $inference_matches > $exact_matches ) {
				// More inference matches than exact - moderate penalty.
				$score = (int) round( $score * 0.75 ); // 25% penalty.
			}

			// Apply complexity penalty only for very long prompts.
			if ( $complexity_ratio >= 5.0 ) {
				$score = (int) round( $score * 0.6 );
			}

			// Penalize low prompt coverage for partial matches too.
			// If we only matched a small portion of what the user asked for,
			// reduce confidence - the user likely has more requirements.
			if ( $prompt_coverage < 0.2 && $token_count > 8 ) {
				// Very long request with very few matches - likely needs AI.
				$score = (int) round( $score * 0.5 );
			} elseif ( $prompt_coverage < 0.25 && $token_count > 6 ) {
				// Long request with few matches.
				$score = (int) round( $score * 0.65 );
			}

			return max( 0, $score );
		}

		return 0;
	}

	/**
	 * Check if two words are explicit variations of each other (from WORD_VARIATIONS).
	 *
	 * This is stricter than is_word_variation - it only checks explicit mappings,
	 * not substring relationships.
	 *
	 * @since 1.0.0
	 * @param string $word1 First word.
	 * @param string $word2 Second word.
	 * @return bool True if explicit variations.
	 */
	private function is_explicit_word_variation( string $word1, string $word2 ): bool {
		// Check in both directions.
		if ( isset( self::WORD_VARIATIONS[ $word1 ] ) &&
			in_array( $word2, self::WORD_VARIATIONS[ $word1 ], true ) ) {
			return true;
		}

		if ( isset( self::WORD_VARIATIONS[ $word2 ] ) &&
			in_array( $word1, self::WORD_VARIATIONS[ $word2 ], true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if one word is a root/stem of the other.
	 *
	 * This only matches words that share a common root, like "round" and "rounded",
	 * not arbitrary substrings.
	 *
	 * @since 1.0.0
	 * @param string $word1 First word.
	 * @param string $word2 Second word.
	 * @return bool True if root word match.
	 */
	private function is_root_word_match( string $word1, string $word2 ): bool {
		// Skip if either word is too short.
		if ( strlen( $word1 ) < 4 || strlen( $word2 ) < 4 ) {
			return false;
		}

		// Common suffixes that indicate word forms.
		$suffixes = array( 'ed', 'ing', 'er', 'est', 'ly', 's', 'ish' );

		// Try stripping suffixes to find root.
		foreach ( $suffixes as $suffix ) {
			if ( str_ends_with( $word1, $suffix ) ) {
				$root1 = substr( $word1, 0, -strlen( $suffix ) );
				if ( strlen( $root1 ) >= 3 && ( $root1 === $word2 || str_starts_with( $word2, $root1 ) ) ) {
					return true;
				}
			}

			if ( str_ends_with( $word2, $suffix ) ) {
				$root2 = substr( $word2, 0, -strlen( $suffix ) );
				if ( strlen( $root2 ) >= 3 && ( $root2 === $word1 || str_starts_with( $word1, $root2 ) ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Combine multiple matches into a single result.
	 *
	 * Uses a weighted confidence calculation that favors the best match
	 * while still considering supporting matches.
	 *
	 * @since 1.0.0
	 * @param array<int, array{pattern: string, css: array<string, string>, score: int}> $matches          All matches.
	 * @param string                                                                     $normalized_prompt The normalized user prompt.
	 * @return array{css: array<string, string>, confidence: int, matched_patterns: array<int, string>} Combined result.
	 */
	private function combine_matches( array $matches, string $normalized_prompt ): array {
		$combined_css     = array();
		$matched_patterns = array();
		$scores           = array();

		// First, filter matches by minimum score threshold.
		$filtered_matches = array_values(
			array_filter(
				$matches,
				function ( array $item ): bool {
					return $item['score'] >= 50; // Raised from 40 to be more selective.
				}
			)
		);

		if ( empty( $filtered_matches ) ) {
			return array(
				'css'              => array(),
				'confidence'       => 0,
				'matched_patterns' => array(),
			);
		}

		$top_score = $filtered_matches[0]['score'] ?? 0;

		// If the top match has a high score (>= 70), use more selective threshold.
		if ( $top_score >= 70 ) {
			// If we have an excellent exact match (>= 90), be selective but allow
			// high-confidence supporting matches that add distinct functionality.
			// Example: "make the text blue" shouldn't add border-radius from
			// "blue text with rounded corners" (score ~75) because "rounded" isn't in prompt.
			// But "make the text blue and add shadow" SHOULD combine both because
			// "shadow" is explicitly in the prompt.
			if ( $top_score >= 90 ) {
				// Get the top pattern's tokens for comparison.
				$top_pattern        = $filtered_matches[0]['pattern'] ?? '';
				$top_pattern_tokens = $this->tokenize( $top_pattern );

				// Strict filtering: include matches within 10 points OR patterns whose
				// keywords are actually present in the prompt (not just shared with top pattern).
				$filtered_matches = array_values(
					array_filter(
						$filtered_matches,
						function ( array $item ) use ( $top_score, $top_pattern_tokens, $normalized_prompt ): bool {
							// Always include very close matches.
							$within_range = ( $top_score - $item['score'] ) <= 10;
							if ( $within_range ) {
								return true;
							}

							// For patterns scoring >= 70 but not within 10 points,
							// check if their unique keywords appear in the prompt.
							if ( $item['score'] >= 70 ) {
								$pattern_tokens = $this->tokenize( $item['pattern'] );
								// Find keywords unique to this pattern (not in top pattern).
								$unique_tokens = array_diff( $pattern_tokens, $top_pattern_tokens );

								// If all unique tokens appear in the prompt, include this pattern.
								// This means the user specifically mentioned these aspects.
								if ( ! empty( $unique_tokens ) ) {
									foreach ( $unique_tokens as $token ) {
										if ( ! str_contains( $normalized_prompt, $token ) ) {
											// Unique keyword not in prompt - exclude pattern.
											return false;
										}
									}
									// All unique keywords found in prompt - include pattern.
									return true;
								}
							}

							return false;
						}
					)
				);
			} else {
				// Moderate filtering: include matches within 25 points of top score.
				$filtered_matches = array_values(
					array_filter(
						$filtered_matches,
						function ( array $item ) use ( $top_score ): bool {
							return ( $top_score - $item['score'] ) < 25;
						}
					)
				);
			}
		}

		// Take top matches (up to 3), prioritizing patterns that add unique CSS properties.
		// If we have more than 3 filtered matches, we want to include patterns that add
		// diverse functionality rather than just the highest-scoring overlapping patterns.
		$top_matches        = array();
		$covered_properties = array();
		$max_matches        = 3;

		foreach ( $filtered_matches as $match ) {
			// Check if this pattern adds any new CSS properties.
			$adds_new_property = false;
			foreach ( $match['css'] as $property => $value ) {
				if ( ! isset( $covered_properties[ $property ] ) ) {
					$adds_new_property = true;
					break;
				}
			}

			// Include pattern if we haven't reached the limit yet,
			// OR if it adds a new CSS property (even if we're at the limit).
			if ( count( $top_matches ) < $max_matches || $adds_new_property ) {
				$top_matches[] = $match;

				// Mark properties as covered.
				foreach ( $match['css'] as $property => $value ) {
					$covered_properties[ $property ] = true;
				}
			}
		}

		foreach ( $top_matches as $match ) {
			// Merge CSS properties (avoid duplicates).
			foreach ( $match['css'] as $property => $value ) {
				// Only add if not already present (first match wins for same property).
				if ( ! isset( $combined_css[ $property ] ) ) {
					$combined_css[ $property ] = $value;
				}
			}

			$matched_patterns[] = $match['pattern'];
			$scores[]           = $match['score'];
		}

		// Calculate confidence: weighted towards the best match.
		// Use 60% weight on best match, 40% distributed among others.
		// This prevents low-scoring supporting matches from dragging down confidence.
		if ( empty( $scores ) ) {
			$confidence = 0;
		} elseif ( count( $scores ) === 1 ) {
			$confidence = $scores[0];
		} else {
			$best_score    = $scores[0];
			$other_scores  = array_slice( $scores, 1 );
			$other_average = array_sum( $other_scores ) / count( $other_scores );
			$confidence    = (int) round( ( $best_score * 0.7 ) + ( $other_average * 0.3 ) );
		}

		return array(
			'css'              => $combined_css,
			'confidence'       => $confidence,
			'matched_patterns' => $matched_patterns,
		);
	}

	// ===================================================================
	// TDD Stub Methods (To be fully implemented)
	// ===================================================================

	/**
	 * Extract keywords from natural language text.
	 *
	 * @param string $text Input text.
	 * @return array<string, int> Keywords with weights.
	 */
	public function extract_keywords( string $text ): array {
		// Stub: Basic implementation.
		$tokens   = $this->tokenize( $this->normalize_prompt( $text ) );
		$keywords = array();
		foreach ( $tokens as $token ) {
			$keywords[ $token ] = 1;
		}
		return $keywords;
	}

	/**
	 * Calculate fuzzy match score between two strings.
	 *
	 * @param string $str1 First string.
	 * @param string $str2 Second string.
	 * @return int Score (0-100).
	 */
	public function fuzzy_match( string $str1, string $str2 ): int {
		$str1 = strtolower( $str1 );
		$str2 = strtolower( $str2 );

		if ( $str1 === $str2 ) {
			return 100;
		}

		// Use similar_text for basic fuzzy matching.
		similar_text( $str1, $str2, $percent );
		return (int) round( $percent );
	}

	/**
	 * Score keyword match between request and pattern keywords.
	 *
	 * @param array<string, int> $request_keywords Request keywords.
	 * @param array<string, int> $pattern_keywords Pattern keywords.
	 * @return int Score (0-100).
	 */
	public function score_keyword_match( array $request_keywords, array $pattern_keywords ): int {
		// Stub: Basic implementation.
		$matched = 0;
		$total   = count( $request_keywords );

		if ( 0 === $total ) {
			return 0;
		}

		foreach ( $request_keywords as $keyword => $weight ) {
			if ( isset( $pattern_keywords[ $keyword ] ) ) {
				++$matched;
			}
		}

		return (int) round( ( $matched / $total ) * 100 );
	}

	/**
	 * Get synonyms for a word.
	 *
	 * @param string $word Word to find synonyms for.
	 * @return array<int, string> Synonyms.
	 */
	public function get_synonyms( string $word ): array {
		// Stub: Return word variations if available.
		return self::WORD_VARIATIONS[ $word ] ?? array();
	}

	/**
	 * Set confidence threshold.
	 *
	 * @param int $threshold Confidence threshold (0-100).
	 * @return void
	 */
	public function set_confidence_threshold( int $threshold ): void {
		$this->confidence_threshold = $threshold;
	}

	/**
	 * Set matching mode.
	 *
	 * @param string $mode Mode (pattern, hybrid, ai).
	 * @return void
	 */
	public function set_mode( string $mode ): void {
		$this->mode = $mode;
	}

	/**
	 * Set cache TTL.
	 *
	 * @param int $ttl TTL in seconds.
	 * @return void
	 */
	public function set_cache_ttl( int $ttl ): void {
		$this->cache_ttl = $ttl;
	}

	/**
	 * Check if a prompt is cached.
	 *
	 * @param string $prompt Prompt to check (reserved for future caching implementation).
	 * @return bool True if cached.
	 */
	public function is_cached( string $prompt ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Stub method for future implementation.
		// Stub: Not yet implemented.
		return false;
	}

	/**
	 * Invalidate cache for a specific prompt.
	 *
	 * @param string $prompt Prompt to invalidate (reserved for future caching implementation).
	 * @return bool True on success.
	 */
	public function invalidate_cache( string $prompt ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Stub method for future implementation.
		// Stub: Not yet implemented.
		return false;
	}

	/**
	 * Clear all cache.
	 *
	 * @return bool True on success.
	 */
	public function clear_cache(): bool {
		// Stub: Not yet implemented.
		return true;
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array{hits: int, misses: int, hit_rate: float} Cache stats.
	 */
	public function get_cache_stats(): array {
		// Stub: Not yet implemented.
		return array(
			'hits'     => 0,
			'misses'   => 0,
			'hit_rate' => 0,
		);
	}
}
