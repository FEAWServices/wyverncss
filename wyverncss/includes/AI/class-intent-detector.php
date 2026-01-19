<?php
/**
 * Intent Detector
 *
 * Detects user intent from prompts using weighted keyword matching.
 * Supports Style, Content, and Chat intents with confidence scoring.
 *
 * @package WyvernCSS
 * @subpackage AI
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\AI;

/**
 * Class Intent_Detector
 *
 * Analyzes user prompts to determine intent type (Style, Content, or Chat)
 * using weighted keyword matching with disambiguation and caching.
 *
 * @since 1.0.0
 */
class Intent_Detector {

	/**
	 * Cache group name for WordPress Object Cache.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'wyverncss_intent';

	/**
	 * Cache TTL in seconds (5 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 300;

	/**
	 * Confidence threshold for ambiguous detection.
	 *
	 * @var int
	 */
	private const AMBIGUITY_THRESHOLD = 15;

	/**
	 * Minimum confidence score to consider an intent valid.
	 *
	 * @var int
	 */
	private const MIN_CONFIDENCE = 30;

	/**
	 * Style intent keywords with weights (1-10).
	 *
	 * @var array<string, int>
	 */
	private array $style_keywords = array();

	/**
	 * Content intent keywords with weights (1-10).
	 *
	 * @var array<string, int>
	 */
	private array $content_keywords = array();

	/**
	 * Chat intent keywords with weights (1-10).
	 *
	 * @var array<string, int>
	 */
	private array $chat_keywords = array();

	/**
	 * Stop words to exclude from keyword extraction.
	 *
	 * @var array<int, string>
	 */
	private array $stop_words = array();

	/**
	 * Constructor.
	 *
	 * Initializes keyword dictionaries for all intent types.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->initialize_style_keywords();
		$this->initialize_content_keywords();
		$this->initialize_chat_keywords();
		$this->initialize_stop_words();
	}

	/**
	 * Initialize style intent keywords.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function initialize_style_keywords(): void {
		$this->style_keywords = array(
			// Color keywords (weight: 10).
			'color'          => 10,
			'colour'         => 10,
			'blue'           => 9,
			'red'            => 9,
			'green'          => 9,
			'yellow'         => 9,
			'purple'         => 9,
			'orange'         => 9,
			'pink'           => 9,
			'black'          => 9,
			'white'          => 9,
			'gray'           => 9,
			'grey'           => 9,
			'cyan'           => 8,
			'magenta'        => 8,
			'brown'          => 8,
			'navy'           => 8,
			'teal'           => 8,
			'lime'           => 8,
			'indigo'         => 8,
			'violet'         => 8,
			'background'     => 10,
			'foreground'     => 9,
			'primary'        => 8,
			'secondary'      => 8,
			'accent'         => 8,
			'scheme'         => 8,
			'palette'        => 9,
			'hue'            => 7,
			'saturation'     => 7,
			'brightness'     => 7,
			'shade'          => 7,
			'tint'           => 7,

			// Typography keywords (weight: 10).
			'font'           => 10,
			'text'           => 9,
			'typography'     => 10,
			'typeface'       => 9,
			'serif'          => 8,
			'sans-serif'     => 8,
			'monospace'      => 8,
			'cursive'        => 7,
			'bold'           => 9,
			'italic'         => 8,
			'underline'      => 8,
			'weight'         => 8,
			'size'           => 9,
			'line-height'    => 8,
			'letter-spacing' => 8,
			'word-spacing'   => 7,
			'heading'        => 8,
			'title'          => 7,
			'paragraph'      => 7,
			'caption'        => 6,

			// Layout keywords (weight: 10).
			'layout'         => 10,
			'grid'           => 9,
			'flex'           => 9,
			'flexbox'        => 9,
			'columns'        => 8,
			'rows'           => 8,
			'align'          => 8,
			'justify'        => 8,
			'center'         => 8,
			'left'           => 7,
			'right'          => 7,
			'spacing'        => 9,
			'margin'         => 9,
			'padding'        => 9,
			'gap'            => 8,
			'width'          => 8,
			'height'         => 8,
			'container'      => 7,
			'wrapper'        => 7,
			'sidebar'        => 7,
			'footer'         => 7,
			'header'         => 7,
			'navigation'     => 7,
			'nav'            => 7,

			// Design keywords (weight: 10).
			'design'         => 10,
			'style'          => 10,
			'styling'        => 10,
			'css'            => 10,
			'appearance'     => 9,
			'look'           => 8,
			'visual'         => 8,
			'aesthetic'      => 8,
			'theme'          => 9,
			'template'       => 7,
			'modern'         => 7,
			'minimal'        => 7,
			'elegant'        => 7,
			'professional'   => 6,
			'creative'       => 6,

			// Border/Shadow keywords (weight: 8-9).
			'border'         => 9,
			'outline'        => 8,
			'shadow'         => 9,
			'box-shadow'     => 9,
			'text-shadow'    => 8,
			'radius'         => 8,
			'rounded'        => 8,
			'corner'         => 7,

			// Animation/Transition keywords (weight: 8-9).
			'animation'      => 9,
			'transition'     => 9,
			'transform'      => 8,
			'rotate'         => 7,
			'scale'          => 7,
			'translate'      => 7,
			'fade'           => 7,
			'slide'          => 7,
			'hover'          => 8,
			'focus'          => 7,
			'active'         => 7,

			// Positioning keywords (weight: 7-8).
			'position'       => 8,
			'absolute'       => 7,
			'relative'       => 7,
			'fixed'          => 7,
			'sticky'         => 7,
			'float'          => 7,
			'z-index'        => 7,
			'top'            => 6,
			'bottom'         => 6,

			// Display keywords (weight: 7-8).
			'display'        => 8,
			'block'          => 7,
			'inline'         => 7,
			'hidden'         => 7,
			'visible'        => 7,
			'opacity'        => 7,
			'overflow'       => 7,

			// Responsive keywords (weight: 7-8).
			'responsive'     => 8,
			'mobile'         => 7,
			'tablet'         => 7,
			'desktop'        => 7,
			'breakpoint'     => 7,
			'media'          => 7,
		);
	}

	/**
	 * Initialize content intent keywords.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function initialize_content_keywords(): void {
		$this->content_keywords = array(
			// Core content keywords (weight: 10).
			'post'         => 10,
			'page'         => 10,
			'content'      => 10,
			'article'      => 9,
			'blog'         => 9,
			'write'        => 10,
			'create'       => 10,
			'edit'         => 10,
			'update'       => 9,
			'delete'       => 9,
			'remove'       => 8,
			'publish'      => 10,
			'draft'        => 9,
			'save'         => 9,

			// Content actions (weight: 8-9).
			'add'          => 9,
			'insert'       => 8,
			'append'       => 7,
			'prepend'      => 7,
			'replace'      => 8,
			'modify'       => 8,
			'change'       => 8,
			'revise'       => 7,
			'rewrite'      => 8,
			'compose'      => 8,
			'generate'     => 9,

			// Content types (weight: 8-9).
			'paragraph'    => 8,
			'section'      => 8,
			'heading'      => 8,
			'subheading'   => 7,
			'list'         => 8,
			'bullet'       => 7,
			'numbered'     => 7,
			'quote'        => 7,
			'blockquote'   => 7,
			'citation'     => 6,
			'code'         => 7,
			'preformatted' => 6,

			// Media content (weight: 8-9).
			'image'        => 9,
			'photo'        => 8,
			'picture'      => 8,
			'gallery'      => 8,
			'video'        => 8,
			'audio'        => 7,
			'media'        => 9,
			'upload'       => 8,
			'attach'       => 7,
			'embed'        => 8,

			// Content management (weight: 7-9).
			'category'     => 8,
			'tag'          => 8,
			'taxonomy'     => 7,
			'menu'         => 8,
			'widget'       => 7,
			'sidebar'      => 6,
			'comment'      => 7,
			'meta'         => 7,
			'metadata'     => 7,
			'custom-field' => 7,
			'excerpt'      => 7,
			'summary'      => 7,

			// Publishing workflow (weight: 7-9).
			'schedule'     => 8,
			'pending'      => 7,
			'review'       => 7,
			'approve'      => 7,
			'reject'       => 6,
			'trash'        => 7,
			'restore'      => 7,
			'duplicate'    => 7,
			'clone'        => 7,

			// SEO/Meta content (weight: 6-8).
			'title'        => 8,
			'description'  => 7,
			'keywords'     => 7,
			'seo'          => 8,
			'permalink'    => 7,
			'slug'         => 7,
			'url'          => 6,
			'link'         => 6,

			// User content (weight: 7-8).
			'author'       => 7,
			'user'         => 7,
			'profile'      => 6,
			'bio'          => 6,
			'avatar'       => 6,

			// Content structure (weight: 6-7).
			'block'        => 7,
			'shortcode'    => 7,
			'template'     => 6,
			'layout'       => 6,
			'structure'    => 6,
		);
	}

	/**
	 * Initialize chat intent keywords.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function initialize_chat_keywords(): void {
		$this->chat_keywords = array(
			// Question words (weight: 10).
			'what'          => 10,
			'why'           => 10,
			'how'           => 10,
			'when'          => 10,
			'where'         => 10,
			'who'           => 10,
			'which'         => 9,
			'whose'         => 8,
			'whom'          => 8,

			// Help/Assistance keywords (weight: 10).
			'help'          => 10,
			'assist'        => 9,
			'support'       => 9,
			'guide'         => 8,
			'tutorial'      => 8,
			'documentation' => 7,
			'docs'          => 7,
			'manual'        => 6,
			'instructions'  => 7,

			// Explanation keywords (weight: 9-10).
			'explain'       => 10,
			'describe'      => 9,
			'tell'          => 9,
			'show'          => 9,
			'demonstrate'   => 8,
			'clarify'       => 8,
			'elaborate'     => 7,
			'details'       => 7,
			'define'        => 8,
			'meaning'       => 8,

			// Question phrases (weight: 8-9).
			'can'           => 8,
			'could'         => 8,
			'would'         => 7,
			'should'        => 8,
			'will'          => 7,
			'may'           => 6,
			'might'         => 6,
			'must'          => 7,
			'able'          => 7,
			'possible'      => 7,

			// Information seeking (weight: 8-9).
			'know'          => 8,
			'find'          => 8,
			'search'        => 8,
			'look'          => 7,
			'discover'      => 7,
			'learn'         => 9,
			'understand'    => 9,
			'figure'        => 7,
			'determine'     => 7,

			// Comparison/Choice keywords (weight: 7-8).
			'compare'       => 8,
			'difference'    => 8,
			'versus'        => 7,
			'vs'            => 7,
			'between'       => 7,
			'better'        => 7,
			'best'          => 7,
			'worse'         => 6,
			'choose'        => 7,
			'select'        => 6,
			'pick'          => 6,
			'recommend'     => 8,
			'suggest'       => 8,
			'advice'        => 8,

			// Problem/Issue keywords (weight: 8-9).
			'problem'       => 9,
			'issue'         => 9,
			'error'         => 9,
			'bug'           => 8,
			'fix'           => 8,
			'solve'         => 8,
			'resolve'       => 8,
			'troubleshoot'  => 9,
			'debug'         => 8,
			'wrong'         => 7,
			'broken'        => 7,
			'fail'          => 7,
			'failing'       => 7,

			// Feature/Capability keywords (weight: 7-8).
			'feature'       => 8,
			'capability'    => 7,
			'function'      => 7,
			'functionality' => 7,
			'work'          => 7,
			'works'         => 7,
			'working'       => 7,
			'does'          => 8,
			'do'            => 8,

			// Example/Demo keywords (weight: 6-7).
			'example'       => 7,
			'sample'        => 6,
			'demo'          => 6,
			'illustration'  => 5,
			'instance'      => 5,

			// General inquiry (weight: 6-7).
			'question'      => 7,
			'ask'           => 7,
			'wonder'        => 6,
			'curious'       => 6,
			'interested'    => 5,
			'info'          => 6,
			'information'   => 7,
			'detail'        => 6,
		);
	}

	/**
	 * Initialize stop words.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function initialize_stop_words(): void {
		$this->stop_words = array(
			'a',
			'an',
			'and',
			'are',
			'as',
			'at',
			'be',
			'by',
			'for',
			'from',
			'has',
			'he',
			'in',
			'is',
			'it',
			'its',
			'of',
			'on',
			'that',
			'the',
			'to',
			'was',
			'with',
			'i',
			'me',
			'my',
			'we',
			'our',
			'you',
			'your',
			'this',
			'these',
			'those',
			'am',
			'been',
			'being',
			'have',
			'had',
			'having',
			'but',
			'if',
			'or',
			'because',
			'until',
			'while',
			'there',
			'here',
			'all',
			'both',
			'each',
			'few',
			'more',
			'most',
			'other',
			'some',
			'such',
			'no',
			'nor',
			'not',
			'only',
			'own',
			'same',
			'so',
			'than',
			'too',
			'very',
			'just',
			'please',
			'want',
			'need',
			'like',
		);
	}

	/**
	 * Detect intent from prompt.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $prompt User prompt to analyze.
	 * @param array<string, mixed> $context Optional context for disambiguation.
	 * @return array{
	 *     intent: string,
	 *     confidence: int,
	 *     ambiguous: bool,
	 *     secondary_intent: string|null,
	 *     matched_keywords: array<int, string>
	 * }
	 */
	public function detect_intent( string $prompt, array $context = array() ): array {
		// Check cache first.
		$cache_key = $this->get_cache_key( $prompt );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached && is_array( $cached ) && $this->is_valid_result( $cached ) ) {
			// Apply context boost even to cached results.
			if ( ! empty( $context ) ) {
				return $this->boost_with_context( $cached, $context );
			}
			return $cached;
		}

		// Normalize and extract keywords.
		$normalized = $this->normalize_prompt( $prompt );
		$keywords   = $this->extract_keywords( $normalized );

		// Score each intent type.
		$scores = array(
			'style'   => $this->score_intent( $keywords, $this->style_keywords ),
			'content' => $this->score_intent( $keywords, $this->content_keywords ),
			'chat'    => $this->score_intent( $keywords, $this->chat_keywords ),
		);

		$scores = $this->apply_layout_context_boost( $keywords, $scores );

		// Get matched keywords for the top intent.
		arsort( $scores );
		$primary_intent    = (string) array_key_first( $scores );
		$primary_score     = $scores[ $primary_intent ];
		$secondary_intents = array_slice( $scores, 1, null, true );
		$secondary_intent  = (string) array_key_first( $secondary_intents );
		$secondary_score   = $secondary_intents[ $secondary_intent ];

		// Calculate confidence (0-100).
		$confidence = $this->calculate_confidence( $keywords, $primary_intent );

		// Detect ambiguity.
		$ambiguous = ( $primary_score - $secondary_score ) <= self::AMBIGUITY_THRESHOLD
					&& $secondary_score >= self::MIN_CONFIDENCE;

		// Get matched keywords.
		$matched_keywords = $this->get_matched_keywords( $keywords, $primary_intent );

		$result = array(
			'intent'           => $primary_intent,
			'confidence'       => $confidence,
			'ambiguous'        => $ambiguous,
			'secondary_intent' => $ambiguous ? $secondary_intent : null,
			'matched_keywords' => $matched_keywords,
		);

		// Apply context boost if available.
		if ( ! empty( $context ) ) {
			$result = $this->boost_with_context( $result, $context );
		}

		// Cache the result.
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Normalize prompt text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prompt Raw prompt text.
	 * @return string Normalized prompt.
	 */
	public function normalize_prompt( string $prompt ): string {
		// Convert to lowercase.
		$normalized = strtolower( $prompt );

		// Remove punctuation except hyphens (for hyphenated keywords).
		$result = preg_replace( '/[^\w\s-]/u', ' ', $normalized );
		if ( ! is_string( $result ) ) {
			$result = $normalized;
		}
		$normalized = $result;

		// Collapse multiple spaces.
		$result = preg_replace( '/\s+/', ' ', $normalized );
		if ( ! is_string( $result ) ) {
			$result = $normalized;
		}
		$normalized = $result;

		return trim( $normalized );
	}

	/**
	 * Extract keywords from normalized prompt.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prompt Normalized prompt.
	 * @return array<int, string> Extracted keywords.
	 */
	public function extract_keywords( string $prompt ): array {
		$words = explode( ' ', $prompt );

		// Remove stop words.
		$keywords = array_filter(
			$words,
			function ( string $word ): bool {
				return ! in_array( $word, $this->stop_words, true ) && strlen( $word ) > 1;
			}
		);

		return array_values( $keywords );
	}

	/**
	 * Score intent based on keyword matches.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $keywords Extracted keywords from prompt.
	 * @param array<string, int> $intent_keywords Intent-specific weighted keywords.
	 * @return int Total score.
	 */
	private function score_intent( array $keywords, array $intent_keywords ): int {
		$score = 0;

		foreach ( $keywords as $keyword ) {
			if ( isset( $intent_keywords[ $keyword ] ) ) {
				$score += $intent_keywords[ $keyword ];
			}
		}

		return $score;
	}

	/**
	 * Calculate confidence score (0-100).
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $matched_keywords Matched keywords from prompt.
	 * @param string             $intent_type Intent type (style, content, chat).
	 * @return int Confidence score (0-100).
	 */
	public function calculate_confidence( array $matched_keywords, string $intent_type ): int {
		$keywords = array();

		switch ( $intent_type ) {
			case 'style':
				$keywords = $this->style_keywords;
				break;
			case 'content':
				$keywords = $this->content_keywords;
				break;
			case 'chat':
				$keywords = $this->chat_keywords;
				break;
		}

		if ( empty( $keywords ) ) {
			return 0;
		}

		// Calculate score from matched keywords.
		$score = 0;
		$count = 0;

		foreach ( $matched_keywords as $keyword ) {
			if ( isset( $keywords[ $keyword ] ) ) {
				$score += $keywords[ $keyword ];
				++$count;
			}
		}

		// No matches = 0 confidence.
		if ( 0 === $count ) {
			return 0;
		}

		// Calculate average weight of matched keywords.
		$avg_weight = $score / $count;

		// Base confidence on average weight (max weight is 10).
		$base_confidence = (int) round( ( $avg_weight / 10 ) * 100 );

		// Boost for multiple matches.
		$match_boost = min( $count * 5, 20 );

		$confidence = min( $base_confidence + $match_boost, 100 );

		return max( 0, $confidence );
	}

	/**
	 * Get matched keywords for intent type.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $keywords Extracted keywords.
	 * @param string             $intent_type Intent type.
	 * @return array<int, string> Matched keywords.
	 */
	private function get_matched_keywords( array $keywords, string $intent_type ): array {
		$intent_keywords = array();

		switch ( $intent_type ) {
			case 'style':
				$intent_keywords = $this->style_keywords;
				break;
			case 'content':
				$intent_keywords = $this->content_keywords;
				break;
			case 'chat':
				$intent_keywords = $this->chat_keywords;
				break;
		}

		$matched = array();
		foreach ( $keywords as $keyword ) {
			if ( isset( $intent_keywords[ $keyword ] ) ) {
				$matched[] = $keyword;
			}
		}

		return $matched;
	}

	/**
	 * Boost style scores when layout commands target generic content.
	 *
	 * Prompts like "Center the content" contain both layout (style) and content
	 * keywords; this ensures layout instructions take precedence.
	 *
	 * @param array<int, string> $keywords Extracted keywords.
	 * @param array<string, int> $scores   Current scores keyed by intent.
	 * @return array<string, int> Adjusted scores.
	 */
	private function apply_layout_context_boost( array $keywords, array $scores ): array {
		$layout_keywords = array(
			'center',
			'align',
			'justify',
			'margin',
			'padding',
			'grid',
			'flex',
			'flexbox',
			'columns',
			'rows',
			'spacing',
			'layout',
		);

		$has_layout_command = false;
		foreach ( $layout_keywords as $layout_keyword ) {
			if ( in_array( $layout_keyword, $keywords, true ) ) {
				$has_layout_command = true;
				break;
			}
		}

		if ( ! $has_layout_command ) {
			return $scores;
		}

		if ( in_array( 'content', $keywords, true ) ) {
			$scores['style'] = ( $scores['style'] ?? 0 ) + 12;
		}

		return $scores;
	}

	/**
	 * Boost confidence with context.
	 *
	 * @since 1.0.0
	 *
	 * @param array{intent: string, confidence: int, ambiguous: bool, secondary_intent: string|null, matched_keywords: array<int, string>} $result Detection result.
	 * @param array<string, mixed>                                                                                                         $context Context data.
	 * @return array{intent: string, confidence: int, ambiguous: bool, secondary_intent: string|null, matched_keywords: array<int, string>}
	 */
	public function boost_with_context( array $result, array $context ): array {
		$boost = 0;

		// Element context boosts style intent.
		if ( isset( $context['element'] ) && is_array( $context['element'] ) ) {
			if ( 'style' === $result['intent'] ) {
				$boost += 10;
			}
		}

		// Post/Page context boosts content intent.
		if ( isset( $context['post_id'] ) || isset( $context['page_id'] ) ) {
			if ( 'content' === $result['intent'] ) {
				$boost += 10;
			}
		}

		// Question mark boosts chat intent.
		if ( isset( $context['has_question_mark'] ) && $context['has_question_mark'] ) {
			if ( 'chat' === $result['intent'] ) {
				$boost += 15;
			}
		}

		// Apply boost.
		if ( $boost > 0 ) {
			$result['confidence'] = min( $result['confidence'] + $boost, 100 );

			// Re-evaluate ambiguity after boost.
			if ( $result['ambiguous'] && $result['confidence'] >= 80 ) {
				$result['ambiguous']        = false;
				$result['secondary_intent'] = null;
			}
		}

		return $result;
	}

	/**
	 * Generate cache key for prompt.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prompt User prompt.
	 * @return string Cache key.
	 */
	public function get_cache_key( string $prompt ): string {
		return 'intent_' . md5( $prompt );
	}

	/**
	 * Validate that cached data matches expected structure.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string|int, mixed> $data Data to validate.
	 * @return bool True if valid, false otherwise.
	 *
	 * @phpstan-assert-if-true array{intent: string, confidence: int, ambiguous: bool, secondary_intent: string|null, matched_keywords: array<int, string>} $data
	 */
	private function is_valid_result( array $data ): bool {
		return isset( $data['intent'] )
			&& is_string( $data['intent'] )
			&& isset( $data['confidence'] )
			&& is_int( $data['confidence'] )
			&& isset( $data['ambiguous'] )
			&& is_bool( $data['ambiguous'] )
			&& array_key_exists( 'secondary_intent', $data )
			&& ( null === $data['secondary_intent'] || is_string( $data['secondary_intent'] ) )
			&& isset( $data['matched_keywords'] )
			&& is_array( $data['matched_keywords'] );
	}
}
