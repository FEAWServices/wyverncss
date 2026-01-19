<?php
/**
 * Element Transformer
 *
 * Detects and extracts element transformation requests from prompts.
 *
 * @package WyvernCSS
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\Generator;

/**
 * Class ElementTransformer
 *
 * Analyzes prompts to detect requests for element type changes (e.g., "turn this p into a button").
 *
 * phpcs:disable Squiz.PHP.CommentedOutCode.Found -- Comments document regex pattern matching examples, not commented-out code.
 */
class ElementTransformer {

	/**
	 * Valid HTML element tags that can be transformed
	 *
	 * @var array<string, array<string>>
	 */
	private const VALID_TRANSFORMS = array(
		'p'      => array( 'button', 'a', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ),
		'div'    => array( 'button', 'a', 'p', 'section', 'article', 'aside', 'nav', 'header', 'footer' ),
		'span'   => array( 'button', 'a', 'strong', 'em', 'code', 'kbd' ),
		'button' => array( 'a', 'div', 'span' ),
		'a'      => array( 'button', 'span', 'div' ),
		'h1'     => array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' ),
		'h2'     => array( 'h1', 'h3', 'h4', 'h5', 'h6', 'p', 'div' ),
		'h3'     => array( 'h1', 'h2', 'h4', 'h5', 'h6', 'p', 'div' ),
		'h4'     => array( 'h1', 'h2', 'h3', 'h5', 'h6', 'p', 'div' ),
		'h5'     => array( 'h1', 'h2', 'h3', 'h4', 'h6', 'p', 'div' ),
		'h6'     => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'p', 'div' ),
	);

	/**
	 * Keywords that indicate complex CSS requiring stylesheet injection
	 *
	 * @var array<string>
	 */
	private const COMPLEX_CSS_KEYWORDS = array(
		'hover',
		'on hover',
		'when hovered',
		'mouse over',
		'mouseover',
		'active',
		'focus',
		'visited',
		'animation',
		'animate',
		'animated',
		'transition',
		'transform on',
		'keyframes',
		'pulse',
		'fade',
		'slide',
		'bounce',
		'glow',
		'shimmer',
	);

	/**
	 * Transformation keywords and patterns
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private const TRANSFORM_PATTERNS = array(
		// Match "turn into a button" or "turn into a blue button" - looks for known HTML elements.
		array(
			'pattern' => '/(?:turn|convert|change|transform|make)\s+(?:this\s+)?(?:into|to)\s+(?:a|an)\s+.*?(button|link|anchor|heading|paragraph|div|span)/i',
			'target'  => 1,
		),
		// Simpler pattern: "turn this into a button".
		array(
			'pattern' => '/(?:into|to)\s+(?:a|an)\s+(button|link|anchor|heading|paragraph|div|span)/i',
			'target'  => 1,
		),
		// Pattern for "should be a button".
		array(
			'pattern' => '/(?:should|must|need)\s+(?:be|become)\s+(?:a|an)\s+(button|link|anchor|heading|paragraph|div|span)/i',
			'target'  => 1,
		),
	);

	/**
	 * Detect if prompt requests element transformation
	 *
	 * @param string               $prompt User prompt.
	 * @param array<string, mixed> $element_context Current element context with 'tag' key.
	 *
	 * @return array{transform: bool, newTag?: string, attributes?: array<string, string>, textContent?: string}|null
	 */
	public function detect_transformation( string $prompt, array $element_context ): ?array {
		$current_tag = $element_context['tag'] ?? null;

		if ( empty( $current_tag ) ) {
			return null;
		}

		$current_tag = strtolower( $current_tag );

		// Check each pattern for transformation keywords.
		foreach ( self::TRANSFORM_PATTERNS as $pattern_config ) {
			$pattern    = $pattern_config['pattern'];
			$target_idx = $pattern_config['target'];

			if ( preg_match( $pattern, $prompt, $matches ) ) {
				$target_tag = strtolower( $matches[ $target_idx ] );

				// Normalize element names to HTML tags.
				$target_tag = $this->normalize_element_name( $target_tag );

				// Validate transformation is allowed.
				if ( $this->is_valid_transformation( $current_tag, $target_tag ) ) {
					$result = array(
						'transform'          => true,
						'newTag'             => $target_tag,
						'preserveAttributes' => true,
						'attributes'         => $this->get_default_attributes( $target_tag ),
					);

					// Extract text content if specified in prompt.
					$text_content = $this->extract_text_content( $prompt );
					if ( null !== $text_content ) {
						$result['textContent'] = $text_content;
					}

					return $result;
				}
			}
		}

		return null;
	}

	/**
	 * Extract text content from prompt
	 *
	 * Looks for patterns like:
	 * - 'says "CLICK HERE"'
	 * - 'that says "CLICK HERE"'
	 * - 'with text "CLICK HERE"'
	 * - 'labeled "CLICK HERE"'
	 *
	 * @param string $prompt User prompt.
	 *
	 * @return string|null Extracted text content or null if not found.
	 */
	private function extract_text_content( string $prompt ): ?string {
		// Pattern to match text content specifications.
		$patterns = array(
			// "says 'text'" or 'says "text"'.
			'/(?:that\s+)?says?\s+["\']([^"\']+)["\']/i',
			// "with text 'text'" or 'with text "text"'.
			'/with\s+(?:the\s+)?text\s+["\']([^"\']+)["\']/i',
			// "labeled 'text'" or 'labeled "text"'.
			'/labeled?\s+["\']([^"\']+)["\']/i',
			// "called 'text'" or 'called "text"'.
			'/called\s+["\']([^"\']+)["\']/i',
			// "reading 'text'" or 'reading "text"'.
			'/reading\s+["\']([^"\']+)["\']/i',
			// "displaying 'text'" or 'displaying "text"'.
			'/displaying\s+["\']([^"\']+)["\']/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $prompt, $matches ) ) {
				return trim( $matches[1] );
			}
		}

		return null;
	}

	/**
	 * Normalize element name to HTML tag
	 *
	 * @param string $element_name Element name (e.g., "link", "paragraph", "button").
	 *
	 * @return string HTML tag name.
	 */
	private function normalize_element_name( string $element_name ): string {
		$normalizations = array(
			'link'      => 'a',
			'anchor'    => 'a',
			'paragraph' => 'p',
			'heading'   => 'h2', // Default to h2 for generic "heading".
		);

		return $normalizations[ $element_name ] ?? $element_name;
	}

	/**
	 * Check if transformation from one element to another is valid
	 *
	 * @param string $from_tag Source element tag.
	 * @param string $to_tag Target element tag.
	 *
	 * @return bool
	 */
	private function is_valid_transformation( string $from_tag, string $to_tag ): bool {
		if ( ! isset( self::VALID_TRANSFORMS[ $from_tag ] ) ) {
			return false;
		}

		return in_array( $to_tag, self::VALID_TRANSFORMS[ $from_tag ], true );
	}

	/**
	 * Get default attributes for specific element types
	 *
	 * @param string $tag Element tag name.
	 *
	 * @return array<string, string>
	 */
	private function get_default_attributes( string $tag ): array {
		$defaults = array(
			'button' => array(
				'type' => 'button',
			),
			'a'      => array(
				'href' => '#',
			),
		);

		return $defaults[ $tag ] ?? array();
	}

	/**
	 * Detect if prompt requires complex CSS (hover states, animations)
	 *
	 * @param string $prompt User prompt.
	 *
	 * @return bool True if complex CSS is needed.
	 */
	public function requires_complex_css( string $prompt ): bool {
		$prompt_lower = strtolower( $prompt );

		foreach ( self::COMPLEX_CSS_KEYWORDS as $keyword ) {
			if ( false !== strpos( $prompt_lower, $keyword ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate a unique CSS class name for complex styles
	 *
	 * Creates human-friendly class names like 'wp-style-btn-1234' instead of random strings.
	 *
	 * @return string Unique class name.
	 */
	public function generate_unique_class(): string {
		// Use a short numeric suffix for uniqueness (4 digits is enough to avoid collisions).
		$suffix = wp_rand( 1000, 9999 );
		return 'wp-style-' . $suffix;
	}

	/**
	 * Extract hover color from prompt
	 *
	 * @param string $prompt User prompt.
	 *
	 * @return string|null Hover color if found.
	 */
	public function extract_hover_color( string $prompt ): ?string {
		// Patterns for hover color extraction (ordered by specificity).
		$patterns = array(
			// "background turns blue on hover", "background becomes red when hovered".
			'/background\s+(?:turns?|becomes?)\s+(\w+)\s+(?:on\s+hover|when\s+hover(?:ed)?)/i',
			// "hover background: blue", "hover background blue", "hover bg: red".
			'/hover\s+(?:background|bg)[:\s]+(\w+)/i',
			// "blue on hover", "red when hovered".
			'/(\w+)\s+(?:on\s+hover|when\s+hover(?:ed)?)/i',
			// "hover: blue", "hover color blue".
			'/hover[:\s]+(?:color\s+)?(\w+)/i',
			// "becomes blue on hover", "turns red on hover".
			'/(?:becomes?|turns?)\s+(\w+)\s+(?:on\s+)?hover/i',
			// "background: blue on hover".
			'/background[:\s]+(\w+)\s+on\s+hover/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $prompt, $matches ) ) {
				return $this->normalize_color( $matches[1] );
			}
		}

		return null;
	}

	/**
	 * Extract hover text color from prompt
	 *
	 * @param string $prompt User prompt.
	 *
	 * @return string|null Hover text color if found.
	 */
	public function extract_hover_text_color( string $prompt ): ?string {
		// Patterns for hover text color extraction.
		$patterns = array(
			// "text turns white on hover", "text becomes blue when hovered".
			'/text\s+(?:turns?|becomes?)\s+(\w+)\s+(?:on\s+hover|when\s+hover(?:ed)?)/i',
			// "hover text: white", "hover text color: blue".
			// Use \s* to allow optional space between "text" and ":" or "color".
			'/hover\s+text\s*(?:color)?[:\s]+(\w+)/i',
			// "text: white on hover".
			'/text[:\s]+(\w+)\s+on\s+hover/i',
			// "white text on hover".
			'/(\w+)\s+text\s+on\s+hover/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $prompt, $matches ) ) {
				return $this->normalize_color( $matches[1] );
			}
		}

		return null;
	}

	/**
	 * Extract border radius style from prompt
	 *
	 * @param string $prompt User prompt.
	 *
	 * @return string|null Border radius value if ellipse/pill detected.
	 */
	public function extract_border_radius( string $prompt ): ?string {
		// Detect ellipse/pill/very rounded keywords.
		$prompt_lower = strtolower( $prompt );

		// Pill or ellipse shape = very large border radius.
		if ( preg_match( '/\b(ellipse|pill|capsule)\b/i', $prompt_lower ) ) {
			return '9999px';
		}

		// "Very rounded" or "super rounded" = large border radius.
		if ( preg_match( '/\b(very|super|extremely)\s+rounded/i', $prompt_lower ) ) {
			return '50px';
		}

		// "Fully rounded" or "completely rounded" = 50%.
		if ( preg_match( '/\b(fully|completely|totally)\s+rounded/i', $prompt_lower ) ) {
			return '50%';
		}

		return null;
	}

	/**
	 * Extract base background color from prompt (non-hover)
	 *
	 * Looks for patterns like:
	 * - "should be green"
	 * - "make it blue"
	 * - "green button" (but NOT "green on hover")
	 *
	 * IMPORTANT: Does NOT match text color requests like "make the text black"
	 *
	 * @param string $prompt User prompt.
	 *
	 * @return string|null Base background color if found.
	 */
	public function extract_base_color( string $prompt ): ?string {
		// First, remove hover-related phrases to avoid matching them.
		$prompt_without_hover = preg_replace(
			'/(\w+)\s+(on\s+hover|when\s+hover(?:ed)?|hover)/',
			'',
			$prompt
		);

		if ( null === $prompt_without_hover ) {
			$prompt_without_hover = $prompt;
		}

		// Check if this is specifically a TEXT COLOR request - if so, don't match as background color.
		// Only skip if the prompt is asking to change the text/font color itself.
		// Patterns like "make the text black" or "text should be blue".
		// But NOT "with the text" or "button text should say".
		$text_color_patterns = array(
			'/make\s+(?:the\s+)?(?:text|font|writing)\s+(?:color\s+)?(?:blue|red|green|yellow|orange|purple|pink|gray|grey|black|white)/i',
			'/(?:text|font|writing)\s+(?:should|must)\s+be\s+(?:blue|red|green|yellow|orange|purple|pink|gray|grey|black|white)/i',
			'/(?:blue|red|green|yellow|orange|purple|pink|gray|grey|black|white)\s+(?:text|font|writing)/i',
			'/(?:text|font|writing)\s+in\s+(?:blue|red|green|yellow|orange|purple|pink|gray|grey|black|white)/i',
		);

		foreach ( $text_color_patterns as $pattern ) {
			if ( preg_match( $pattern, $prompt_without_hover ) ) {
				return null;
			}
		}

		// Known color names for matching.
		$color_names = array(
			'blue',
			'red',
			'green',
			'yellow',
			'orange',
			'purple',
			'pink',
			'gray',
			'grey',
			'black',
			'white',
		);

		$color_pattern = implode( '|', $color_names );

		// Patterns for base color extraction (ordered by specificity).
		$patterns = array(
			// "should be green", "must be blue", "needs to be red".
			'/(?:should|must|needs?\s+to)\s+be\s+(' . $color_pattern . ')/i',
			// "make it green", "make this blue" (but NOT "make the text").
			'/make\s+(?:it|this)\s+(' . $color_pattern . ')/i',
			// "green button", "blue background".
			'/(' . $color_pattern . ')\s+(?:button|background|element)/i',
			// "in green", "in blue".
			'/\bin\s+(' . $color_pattern . ')\b/i',
			// Standalone color next to button keywords (e.g., "button ... green").
			'/button\b.*?\b(' . $color_pattern . ')\b(?!\s+(?:on\s+hover|when\s+hover))/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $prompt_without_hover, $matches ) ) {
				return $this->normalize_color( $matches[1] );
			}
		}

		return null;
	}

	/**
	 * Extract base text color from prompt (non-hover)
	 *
	 * @param string $prompt User prompt.
	 *
	 * @return string|null Base text color if found.
	 */
	public function extract_base_text_color( string $prompt ): ?string {
		// First, remove hover-related phrases to avoid matching them.
		$prompt_without_hover = preg_replace(
			'/(\w+)\s+text\s+(on\s+hover|when\s+hover(?:ed)?|hover)/',
			'',
			$prompt
		);

		if ( null === $prompt_without_hover ) {
			$prompt_without_hover = $prompt;
		}

		// Known color names for matching.
		$color_names = array(
			'blue',
			'red',
			'green',
			'yellow',
			'orange',
			'purple',
			'pink',
			'gray',
			'grey',
			'black',
			'white',
		);

		$color_pattern = implode( '|', $color_names );

		// Patterns for base text color extraction.
		$patterns = array(
			// "white text", "blue text".
			'/(' . $color_pattern . ')\s+(?:text|writing|font)/i',
			// "text in white", "text in blue".
			'/(?:text|writing)\s+in\s+(' . $color_pattern . ')/i',
			// "text should be white".
			'/text\s+(?:should|must)\s+be\s+(' . $color_pattern . ')/i',
			// "make the text black", "make text white".
			'/make\s+(?:the\s+)?(?:text|font|writing)\s+(' . $color_pattern . ')/i',
			// "the text black", "text black" at end of phrase.
			'/(?:the\s+)?(?:text|font|writing)\s+(' . $color_pattern . ')$/i',
			// "change text to black", "set text to white".
			'/(?:change|set)\s+(?:the\s+)?(?:text|font|writing)\s+(?:to\s+)?(' . $color_pattern . ')/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $prompt_without_hover, $matches ) ) {
				return $this->normalize_color( $matches[1] );
			}
		}

		return null;
	}

	/**
	 * Normalize color name to CSS value
	 *
	 * @param string $color Color name or value.
	 *
	 * @return string CSS color value.
	 */
	private function normalize_color( string $color ): string {
		$color_map = array(
			'blue'   => '#0073aa',
			'red'    => '#dc3545',
			'green'  => '#28a745',
			'yellow' => '#ffc107',
			'orange' => '#fd7e14',
			'purple' => '#6f42c1',
			'pink'   => '#e83e8c',
			'gray'   => '#6c757d',
			'grey'   => '#6c757d',
			'black'  => '#000000',
			'white'  => '#ffffff',
		);

		$color_lower = strtolower( $color );
		return $color_map[ $color_lower ] ?? $color;
	}
}
