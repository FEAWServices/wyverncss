<?php
/**
 * Content Detector
 *
 * Detects content generation requests from prompts (tables, lists, cards, etc.).
 *
 * @package WyvernCSS
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\Generator;
// Import WordPress i18n functions.
use function __;
use function _n;
use function sprintf;
use function esc_html__;

/**
 * Class ContentDetector
 *
 * Analyzes prompts to detect requests for content generation (e.g., "add a table with 3 columns").
 * Returns block specifications for Gutenberg block creation.
 */
class ContentDetector {

	/**
	 * Content type patterns for detection
	 *
	 * Each pattern includes:
	 * - pattern: Regex to match
	 * - captures: Named captures to extract
	 * - type: Content type identifier
	 * - blockName: Gutenberg block name
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private const CONTENT_PATTERNS = array(
		// Table: "add a table with 3 columns and 5 rows".
		'table'                 => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?table\s+(?:with\s+)?(\d+)\s*(?:columns?|cols?)\s*(?:and\s+)?(\d+)\s*rows?/i',
			'captures'  => array( 'columns', 'rows' ),
			'type'      => 'table',
			'blockName' => 'core/table',
		),
		// Table with headers: "add a table with headers Name and Email".
		'table_headers'         => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?table\s+(?:with\s+)?(?:headings?|headers?|columns?)\s+(?:named\s+|called\s+)?["\']?([^"\']+?)["\']?\s+and\s+["\']?([^"\']+?)["\']?(?:\s+(?:with|and)\s+(\d+)\s*(?:rows?|empty\s*rows?))?/i',
			'captures'  => array( 'header1', 'header2', 'rows' ),
			'type'      => 'table_with_headers',
			'blockName' => 'core/table',
		),
		// Table with headers (natural language): "add a table with 2 headings, one says X and the other says Y".
		'table_headers_natural' => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?table\s+(?:with\s+)?\d+\s*(?:headings?|headers?|columns?),?\s+(?:one\s+)?(?:says?|is|called|named)\s+"([^"]+)"\s+and\s+(?:the\s+other\s+)?(?:says?|is|called|named)\s+"([^"]+)"(?:\.?\s+(?:the\s+)?(?:tables?\s+)?(?:should\s+)?(?:have\s+)?(\d+)\s*(?:rows?\s*)?(?:empty)?)?/i',
			'captures'  => array( 'header1', 'header2', 'rows' ),
			'type'      => 'table_with_headers',
			'blockName' => 'core/table',
		),
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Pattern documentation, not code.
		// Unordered list: "add a bulleted list with 5 items".
		'list_unordered'        => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:bulleted?|unordered)\s+list\s+(?:with\s+)?(\d+)\s*items?/i',
			'captures'  => array( 'items' ),
			'type'      => 'list_unordered',
			'blockName' => 'core/list',
		),
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Pattern documentation, not code.
		// Ordered list: "add a numbered list with 5 items".
		'list_ordered'          => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:numbered|ordered)\s+list\s+(?:with\s+)?(\d+)\s*items?/i',
			'captures'  => array( 'items' ),
			'type'      => 'list_ordered',
			'blockName' => 'core/list',
		),
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Pattern documentation, not code.
		// Generic list: "add a list with 5 items".
		'list_generic'          => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?list\s+(?:with\s+)?(\d+)\s*items?/i',
			'captures'  => array( 'items' ),
			'type'      => 'list_unordered',
			'blockName' => 'core/list',
		),
		// Columns/Grid: "add a 3 column grid" or "add a 2-column layout".
		'columns'               => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(\d+)[- ]?column\s*(?:grid|layout|section)?/i',
			'captures'  => array( 'count' ),
			'type'      => 'columns',
			'blockName' => 'core/columns',
		),
		// Button: "add a button" or "add a button that says Click Here".
		'button'                => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?button(?:\s+(?:that\s+)?(?:says?|with\s+text|labeled?|called?)\s+["\']?(.+?)["\']?)?$/i',
			'captures'  => array( 'text' ),
			'type'      => 'button',
			'blockName' => 'core/button',
		),
		// Callout/Notice/Alert: "add a callout" or "add an alert".
		'callout'               => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a|an)\s+(?:callout|notice|alert|info\s*box|warning\s*box)/i',
			'captures'  => array(),
			'type'      => 'callout',
			'blockName' => 'core/group',
		),
		// Card: "add a card" or "add a card with title".
		'card'                  => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?card(?:\s+(?:with|titled?)\s+["\']?(.+?)["\']?)?$/i',
			'captures'  => array( 'title' ),
			'type'      => 'card',
			'blockName' => 'core/group',
		),
		// Quote: "add a quote" or "add a blockquote".
		'quote'                 => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:block)?quote/i',
			'captures'  => array(),
			'type'      => 'quote',
			'blockName' => 'core/quote',
		),
		// Image placeholder: "add an image" or "add a placeholder image".
		'image'                 => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a|an)\s+(?:placeholder\s+)?image/i',
			'captures'  => array(),
			'type'      => 'image',
			'blockName' => 'core/image',
		),
		// Separator/Divider: "add a separator" or "add a divider".
		'separator'             => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:separator|divider|horizontal\s+rule|hr)/i',
			'captures'  => array(),
			'type'      => 'separator',
			'blockName' => 'core/separator',
		),
		// Heading: "add a heading" or "add an h2".
		'heading'               => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a|an)\s+(?:h([1-6])|heading(?:\s+level\s+(\d))?)/i',
			'captures'  => array( 'level', 'level_alt' ),
			'type'      => 'heading',
			'blockName' => 'core/heading',
		),
		// Spacer: "add a spacer" or "add some space".
		'spacer'                => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:spacer|space|vertical\s+space)(?:\s+(\d+)\s*px)?/i',
			'captures'  => array( 'height' ),
			'type'      => 'spacer',
			'blockName' => 'core/spacer',
		),
		// Code block: "add a code block".
		'code'                  => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?code\s*block/i',
			'captures'  => array(),
			'type'      => 'code',
			'blockName' => 'core/code',
		),
		// Preformatted: "add a preformatted block".
		'preformatted'          => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:preformatted|pre)\s*(?:block|text)?/i',
			'captures'  => array(),
			'type'      => 'preformatted',
			'blockName' => 'core/preformatted',
		),
		// Cover: "add a cover block" or "add a hero section".
		'cover'                 => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:cover|hero)\s*(?:block|section)?/i',
			'captures'  => array(),
			'type'      => 'cover',
			'blockName' => 'core/cover',
		),
		// Media & Text: "add a media and text block".
		'media_text'            => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:media\s*(?:and|&)\s*text|image\s*(?:and|&|with)\s*text)\s*(?:block)?/i',
			'captures'  => array(),
			'type'      => 'media_text',
			'blockName' => 'core/media-text',
		),
		// Gallery: "add a gallery" or "add an image gallery".
		'gallery'               => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a|an)\s+(?:image\s+)?gallery/i',
			'captures'  => array(),
			'type'      => 'gallery',
			'blockName' => 'core/gallery',
		),
		// Video: "add a video block".
		'video'                 => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?video\s*(?:block)?/i',
			'captures'  => array(),
			'type'      => 'video',
			'blockName' => 'core/video',
		),
		// Embed: "add a youtube embed" or "add an embed".
		'embed'                 => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a|an)\s+(?:youtube|vimeo|twitter|embed)\s*(?:block)?/i',
			'captures'  => array(),
			'type'      => 'embed',
			'blockName' => 'core/embed',
		),
		// Social links: "add social links".
		'social_links'          => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?social\s*(?:links?|icons?|media)\s*(?:block)?/i',
			'captures'  => array(),
			'type'      => 'social_links',
			'blockName' => 'core/social-links',
		),
		// Search: "add a search box".
		'search'                => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?search\s*(?:box|bar|field|form|block)?/i',
			'captures'  => array(),
			'type'      => 'search',
			'blockName' => 'core/search',
		),
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Pattern documentation, not code.
		// Page break: "add a page break".
		'page_break'            => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?page\s*break/i',
			'captures'  => array(),
			'type'      => 'page_break',
			'blockName' => 'core/nextpage',
		),
		// Table of contents: "add a table of contents".
		'toc'                   => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?table\s+of\s+contents/i',
			'captures'  => array(),
			'type'      => 'toc',
			'blockName' => 'core/table-of-contents',
		),
		// Read more: "add a read more block".
		'read_more'             => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:read\s*more|more)\s*(?:block|link)?/i',
			'captures'  => array(),
			'type'      => 'read_more',
			'blockName' => 'core/more',
		),
		// Details/Accordion: "add a details block" or "add an accordion".
		'details'               => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a|an)\s+(?:details|accordion|expandable|collapsible)\s*(?:block|section)?/i',
			'captures'  => array(),
			'type'      => 'details',
			'blockName' => 'core/details',
		),
		// Footnotes: "add footnotes".
		'footnotes'             => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?footnotes?\s*(?:block)?/i',
			'captures'  => array(),
			'type'      => 'footnotes',
			'blockName' => 'core/footnotes',
		),
		// Audio: "add an audio block".
		'audio'                 => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a|an)\s+audio\s*(?:block|player)?/i',
			'captures'  => array(),
			'type'      => 'audio',
			'blockName' => 'core/audio',
		),
		// File: "add a file download" or "add a file block".
		'file'                  => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:file|download)\s*(?:block|link)?/i',
			'captures'  => array(),
			'type'      => 'file',
			'blockName' => 'core/file',
		),
		// Pullquote: "add a pullquote".
		'pullquote'             => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?pull\s*quote/i',
			'captures'  => array(),
			'type'      => 'pullquote',
			'blockName' => 'core/pullquote',
		),
		// Verse: "add a verse block".
		'verse'                 => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?verse\s*(?:block)?/i',
			'captures'  => array(),
			'type'      => 'verse',
			'blockName' => 'core/verse',
		),
		// Buttons group: "add a buttons group" or "add multiple buttons".
		'buttons'               => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:buttons?\s+group|multiple\s+buttons|button\s+group)/i',
			'captures'  => array(),
			'type'      => 'buttons',
			'blockName' => 'core/buttons',
		),
		// Row: "add a row block".
		'row'                   => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:row|flex\s*row)\s*(?:block)?/i',
			'captures'  => array(),
			'type'      => 'row',
			'blockName' => 'core/group',
		),
		// Stack: "add a stack block".
		'stack'                 => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:stack|flex\s*column)\s*(?:block)?/i',
			'captures'  => array(),
			'type'      => 'stack',
			'blockName' => 'core/group',
		),
		// Navigation: "add a navigation menu".
		'navigation'            => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:navigation|nav|menu)\s*(?:block)?/i',
			'captures'  => array(),
			'type'      => 'navigation',
			'blockName' => 'core/navigation',
		),
		// Site logo: "add a site logo".
		'site_logo'             => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:site\s*)?logo/i',
			'captures'  => array(),
			'type'      => 'site_logo',
			'blockName' => 'core/site-logo',
		),
		// Site title: "add a site title".
		'site_title'            => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?site\s*title/i',
			'captures'  => array(),
			'type'      => 'site_title',
			'blockName' => 'core/site-title',
		),
		// Site tagline: "add a site tagline".
		'site_tagline'          => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?site\s*tagline/i',
			'captures'  => array(),
			'type'      => 'site_tagline',
			'blockName' => 'core/site-tagline',
		),
		// Query loop: "add a posts list" or "add a query loop".
		'query'                 => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:posts?\s*list|query\s*(?:loop)?|blog\s*posts?)/i',
			'captures'  => array(),
			'type'      => 'query',
			'blockName' => 'core/query',
		),
		// Post title: "add a post title".
		'post_title'            => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?post\s*title/i',
			'captures'  => array(),
			'type'      => 'post_title',
			'blockName' => 'core/post-title',
		),
		// Post content: "add post content".
		'post_content'          => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?post\s*content/i',
			'captures'  => array(),
			'type'      => 'post_content',
			'blockName' => 'core/post-content',
		),
		// Post excerpt: "add a post excerpt".
		'post_excerpt'          => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?post\s*excerpt/i',
			'captures'  => array(),
			'type'      => 'post_excerpt',
			'blockName' => 'core/post-excerpt',
		),
		// Post date: "add a post date".
		'post_date'             => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?post\s*date/i',
			'captures'  => array(),
			'type'      => 'post_date',
			'blockName' => 'core/post-date',
		),
		// Post author: "add a post author".
		'post_author'           => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?post\s*author/i',
			'captures'  => array(),
			'type'      => 'post_author',
			'blockName' => 'core/post-author',
		),
		// Post featured image: "add a featured image".
		'featured_image'        => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:featured\s*image|post\s*thumbnail)/i',
			'captures'  => array(),
			'type'      => 'featured_image',
			'blockName' => 'core/post-featured-image',
		),
		// Comments: "add comments section".
		'comments'              => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?comments?\s*(?:section|block)?/i',
			'captures'  => array(),
			'type'      => 'comments',
			'blockName' => 'core/comments',
		),
		// Categories: "add categories".
		'categories'            => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:post\s*)?categories/i',
			'captures'  => array(),
			'type'      => 'categories',
			'blockName' => 'core/post-terms',
		),
		// Tags: "add tags".
		'tags'                  => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:post\s*)?tags/i',
			'captures'  => array(),
			'type'      => 'tags',
			'blockName' => 'core/post-terms',
		),
		// Archives: "add archives block".
		'archives'              => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a|an)\s+archives?\s*(?:block)?/i',
			'captures'  => array(),
			'type'      => 'archives',
			'blockName' => 'core/archives',
		),
		// Calendar: "add a calendar".
		'calendar'              => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?calendar/i',
			'captures'  => array(),
			'type'      => 'calendar',
			'blockName' => 'core/calendar',
		),
		// Latest posts: "add latest posts".
		'latest_posts'          => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:latest|recent)\s*posts?/i',
			'captures'  => array(),
			'type'      => 'latest_posts',
			'blockName' => 'core/latest-posts',
		),
		// Latest comments: "add latest comments".
		'latest_comments'       => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?(?:latest|recent)\s*comments?/i',
			'captures'  => array(),
			'type'      => 'latest_comments',
			'blockName' => 'core/latest-comments',
		),
		// Tag cloud: "add a tag cloud".
		'tag_cloud'             => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a\s+)?tag\s*cloud/i',
			'captures'  => array(),
			'type'      => 'tag_cloud',
			'blockName' => 'core/tag-cloud',
		),
		// RSS: "add an RSS feed".
		'rss'                   => array(
			'pattern'   => '/(?:add|create|insert|make|generate)\s+(?:a|an)\s+rss\s*(?:feed|block)?/i',
			'captures'  => array(),
			'type'      => 'rss',
			'blockName' => 'core/rss',
		),
	);

	/**
	 * Edit patterns for modifying existing content
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private const EDIT_PATTERNS = array(
		// Add columns to table: "add 2 columns".
		'add_columns'    => array(
			'pattern'       => '/add\s+(\d+)\s*(?:more\s+)?columns?/i',
			'captures'      => array( 'count' ),
			'type'          => 'add_columns',
			'requires_type' => 'core/table',
		),
		// Remove columns from table: "remove 1 column".
		'remove_columns' => array(
			'pattern'       => '/(?:remove|delete)\s+(\d+)\s*columns?/i',
			'captures'      => array( 'count' ),
			'type'          => 'remove_columns',
			'requires_type' => 'core/table',
		),
		// Add rows to table: "add 3 rows".
		'add_rows'       => array(
			'pattern'       => '/add\s+(\d+)\s*(?:more\s+)?rows?/i',
			'captures'      => array( 'count' ),
			'type'          => 'add_rows',
			'requires_type' => 'core/table',
		),
		// Remove rows from table: "remove 2 rows".
		'remove_rows'    => array(
			'pattern'       => '/(?:remove|delete)\s+(\d+)\s*rows?/i',
			'captures'      => array( 'count' ),
			'type'          => 'remove_rows',
			'requires_type' => 'core/table',
		),
		// Add list items: "add 3 items".
		'add_items'      => array(
			'pattern'       => '/add\s+(\d+)\s*(?:more\s+)?items?/i',
			'captures'      => array( 'count' ),
			'type'          => 'add_items',
			'requires_type' => 'core/list',
		),
		// Remove list items: "remove 2 items".
		'remove_items'   => array(
			'pattern'       => '/(?:remove|delete)\s+(\d+)\s*items?/i',
			'captures'      => array( 'count' ),
			'type'          => 'remove_items',
			'requires_type' => 'core/list',
		),
		// Add column to layout: "add a column".
		'add_column'     => array(
			'pattern'       => '/add\s+(?:a\s+)?(?:another\s+)?column/i',
			'captures'      => array(),
			'type'          => 'add_column',
			'requires_type' => 'core/columns',
		),
		// Remove column from layout: "remove a column".
		'remove_column'  => array(
			'pattern'       => '/(?:remove|delete)\s+(?:a\s+)?column/i',
			'captures'      => array(),
			'type'          => 'remove_column',
			'requires_type' => 'core/columns',
		),
	);

	/**
	 * Detect content generation request from prompt
	 *
	 * @param string               $prompt User prompt.
	 * @param array<string, mixed> $block_context Current block context with 'blockName' key.
	 *
	 * @return array{detected: bool, type: string, action: string, blockName: string, params: array<string, mixed>, warning: string, replacesCurrentElement: bool}|null
	 */
	public function detect( string $prompt, array $block_context = array() ): ?array {
		// First check for edit patterns (if we have block context).
		if ( ! empty( $block_context['blockName'] ) ) {
			$edit_result = $this->detect_edit_operation( $prompt, $block_context );
			if ( null !== $edit_result ) {
				return $edit_result;
			}
		}

		// Check content creation patterns.
		foreach ( self::CONTENT_PATTERNS as $key => $config ) {
			$pattern = $config['pattern'];

			if ( preg_match( $pattern, $prompt, $matches ) ) {
				$params = $this->extract_params( $matches, $config['captures'] );

				// Special handling for table_headers (both patterns).
				if ( 'table_headers' === $key || 'table_headers_natural' === $key ) {
					$params = $this->process_table_headers( $params, $prompt );
				}

				// Determine if this will replace current element.
				$replaces_current = $this->will_replace_current( $block_context );

				// Generate warning message.
				$warning = $this->generate_warning(
					$config['type'],
					$config['blockName'],
					$params,
					$replaces_current,
					$block_context
				);

				return array(
					'detected'               => true,
					'type'                   => $config['type'],
					'action'                 => 'insert_block',
					'blockName'              => $config['blockName'],
					'params'                 => $params,
					'warning'                => $warning,
					'replacesCurrentElement' => $replaces_current,
				);
			}
		}

		return null;
	}

	/**
	 * Detect edit operations on existing blocks
	 *
	 * @param string               $prompt User prompt.
	 * @param array<string, mixed> $block_context Current block context.
	 *
	 * @return array{detected: bool, type: string, action: string, blockName: string, params: array<string, mixed>, warning: string, replacesCurrentElement: bool}|null
	 */
	private function detect_edit_operation( string $prompt, array $block_context ): ?array {
		$current_block = $block_context['blockName'] ?? null;

		if ( empty( $current_block ) ) {
			return null;
		}

		foreach ( self::EDIT_PATTERNS as $key => $config ) {
			// Check if this edit pattern applies to the current block type.
			if ( $config['requires_type'] !== $current_block ) {
				continue;
			}

			$pattern = $config['pattern'];

			if ( preg_match( $pattern, $prompt, $matches ) ) {
				$params = $this->extract_params( $matches, $config['captures'] );

				$warning = $this->generate_edit_warning( $config['type'], $params, $block_context );

				return array(
					'detected'               => true,
					'type'                   => $config['type'],
					'action'                 => 'update_block',
					'blockName'              => $current_block,
					'params'                 => $params,
					'warning'                => $warning,
					'replacesCurrentElement' => false,
				);
			}
		}

		return null;
	}

	/**
	 * Extract parameters from regex matches
	 *
	 * @param array<int, string> $matches Regex matches.
	 * @param array<int, string> $captures Capture names.
	 *
	 * @return array<string, mixed>
	 */
	private function extract_params( array $matches, array $captures ): array {
		$params = array();

		foreach ( $captures as $index => $name ) {
			$value = $matches[ $index + 1 ] ?? null;

			if ( null !== $value && '' !== $value ) {
				// Convert numeric strings to integers.
				if ( is_numeric( $value ) ) {
					$value = (int) $value;
				} else {
					// Trim whitespace from string values.
					$value = trim( $value );
				}
				$params[ $name ] = $value;
			}
		}

		return $params;
	}

	/**
	 * Process table headers from prompt
	 *
	 * @param array<string, mixed> $params Extracted params.
	 * @param string               $prompt Original prompt.
	 *
	 * @return array<string, mixed>
	 */
	private function process_table_headers( array $params, string $prompt ): array {
		$headers = array();

		if ( ! empty( $params['header1'] ) ) {
			$headers[] = trim( $params['header1'] );
		}
		if ( ! empty( $params['header2'] ) ) {
			$headers[] = trim( $params['header2'] );
		}

		// Try to find more headers.
		// Pattern for "headers X, Y, and Z" or "headers X, Y, Z".
		if ( preg_match( '/(?:headings?|headers?|columns?)\s+(?:named\s+|called\s+)?(.+?)(?:\s+(?:with|and)\s+\d+\s*rows?)?$/i', $prompt, $header_matches ) ) {
			$header_string = $header_matches[1];
			// Split by "and", ",", or combination.
			$parsed_headers = preg_split( '/\s*(?:,\s*and\s*|,\s*|\s+and\s+)\s*/i', $header_string );
			if ( is_array( $parsed_headers ) && count( $parsed_headers ) > count( $headers ) ) {
				$headers = array_map(
					function ( $h ) {
						return trim( str_replace( array( '"', "'" ), '', $h ) );
					},
					$parsed_headers
				);
			}
		}

		// Remove temporary header params and add combined headers array.
		unset( $params['header1'], $params['header2'] );
		$params['headers'] = array_filter( $headers );
		$params['columns'] = count( $params['headers'] );

		// Default rows if not specified.
		if ( empty( $params['rows'] ) ) {
			$params['rows'] = 3;
		}

		return $params;
	}

	/**
	 * Determine if operation will replace current element
	 *
	 * @param array<string, mixed> $block_context Current block context.
	 *
	 * @return bool
	 */
	private function will_replace_current( array $block_context ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Reserved for future expansion.
		// For now, content insertion doesn't replace - it inserts after.
		// This could be expanded based on user preference or specific patterns.
		return false;
	}

	/**
	 * Generate warning message for content generation
	 *
	 * @param string               $type Content type.
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $params Extracted parameters.
	 * @param bool                 $replaces_current Whether it replaces current.
	 * @param array<string, mixed> $block_context Current block context.
	 *
	 * @return string
	 */
	private function generate_warning(
		string $type,
		string $block_name,
		array $params,
		bool $replaces_current,
		array $block_context
	): string {
		$description = $this->get_content_description( $type, $params );

		if ( $replaces_current && ! empty( $block_context['blockName'] ) ) {
			$current_type = $this->get_block_friendly_name( $block_context['blockName'] );
			return sprintf(
				/* translators: 1: description of new content, 2: current block type */
				__( 'This will replace the selected %2$s with %1$s', 'wyvern-ai-styling' ),
				$description,
				$current_type
			);
		}

		return sprintf(
			/* translators: %s: description of content to be inserted */
			__( 'This will insert %s after the current selection', 'wyvern-ai-styling' ),
			$description
		);
	}

	/**
	 * Generate warning for edit operations
	 *
	 * @param string               $type Edit type.
	 * @param array<string, mixed> $params Parameters.
	 * @param array<string, mixed> $block_context Block context.
	 *
	 * @return string
	 */
	private function generate_edit_warning( string $type, array $params, array $block_context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Reserved for future expansion.
		$count = $params['count'] ?? 1;

		switch ( $type ) {
			case 'add_columns':
				return sprintf(
					/* translators: %d: number of columns */
					_n(
						'This will add %d column to the table',
						'This will add %d columns to the table',
						$count,
						'wyvern-ai-styling'
					),
					$count
				);
			case 'remove_columns':
				return sprintf(
					/* translators: %d: number of columns */
					_n(
						'This will remove %d column from the table',
						'This will remove %d columns from the table',
						$count,
						'wyvern-ai-styling'
					),
					$count
				);
			case 'add_rows':
				return sprintf(
					/* translators: %d: number of rows */
					_n(
						'This will add %d row to the table',
						'This will add %d rows to the table',
						$count,
						'wyvern-ai-styling'
					),
					$count
				);
			case 'remove_rows':
				return sprintf(
					/* translators: %d: number of rows */
					_n(
						'This will remove %d row from the table',
						'This will remove %d rows from the table',
						$count,
						'wyvern-ai-styling'
					),
					$count
				);
			case 'add_items':
				return sprintf(
					/* translators: %d: number of items */
					_n(
						'This will add %d item to the list',
						'This will add %d items to the list',
						$count,
						'wyvern-ai-styling'
					),
					$count
				);
			case 'remove_items':
				return sprintf(
					/* translators: %d: number of items */
					_n(
						'This will remove %d item from the list',
						'This will remove %d items from the list',
						$count,
						'wyvern-ai-styling'
					),
					$count
				);
			case 'add_column':
				return __( 'This will add a column to the layout', 'wyvern-ai-styling' );
			case 'remove_column':
				return __( 'This will remove a column from the layout', 'wyvern-ai-styling' );
			default:
				return __( 'This will modify the selected block', 'wyvern-ai-styling' );
		}
	}

	/**
	 * Get human-readable description of content to be created
	 *
	 * @param string               $type Content type.
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return string
	 */
	private function get_content_description( string $type, array $params ): string {
		switch ( $type ) {
			case 'table':
				$cols = $params['columns'] ?? 2;
				$rows = $params['rows'] ?? 3;
				return sprintf(
					/* translators: 1: number of columns, 2: number of rows */
					__( 'a table with %1$d columns and %2$d rows', 'wyvern-ai-styling' ),
					$cols,
					$rows
				);
			case 'table_with_headers':
				$headers = $params['headers'] ?? array();
				$rows    = $params['rows'] ?? 3;
				if ( count( $headers ) > 0 ) {
					return sprintf(
						/* translators: 1: header names, 2: number of rows */
						__( 'a table with headers "%1$s" and %2$d rows', 'wyvern-ai-styling' ),
						implode( '", "', $headers ),
						$rows
					);
				}
				return __( 'a table with headers', 'wyvern-ai-styling' );
			case 'list_unordered':
				$items = $params['items'] ?? 3;
				return sprintf(
					/* translators: %d: number of items */
					__( 'a bulleted list with %d items', 'wyvern-ai-styling' ),
					$items
				);
			case 'list_ordered':
				$items = $params['items'] ?? 3;
				return sprintf(
					/* translators: %d: number of items */
					__( 'a numbered list with %d items', 'wyvern-ai-styling' ),
					$items
				);
			case 'columns':
				$count = $params['count'] ?? 2;
				return sprintf(
					/* translators: %d: number of columns */
					__( 'a %d-column layout', 'wyvern-ai-styling' ),
					$count
				);
			case 'button':
				$text = $params['text'] ?? '';
				if ( ! empty( $text ) ) {
					return sprintf(
						/* translators: %s: button text */
						__( 'a button with text "%s"', 'wyvern-ai-styling' ),
						$text
					);
				}
				return __( 'a button', 'wyvern-ai-styling' );
			case 'callout':
				return __( 'a callout/notice block', 'wyvern-ai-styling' );
			case 'card':
				$title = $params['title'] ?? '';
				if ( ! empty( $title ) ) {
					return sprintf(
						/* translators: %s: card title */
						__( 'a card titled "%s"', 'wyvern-ai-styling' ),
						$title
					);
				}
				return __( 'a card block', 'wyvern-ai-styling' );
			case 'quote':
				return __( 'a quote block', 'wyvern-ai-styling' );
			case 'image':
				return __( 'an image placeholder', 'wyvern-ai-styling' );
			case 'separator':
				return __( 'a separator/divider', 'wyvern-ai-styling' );
			case 'heading':
				$level = $params['level'] ?? $params['level_alt'] ?? 2;
				return sprintf(
					/* translators: %d: heading level */
					__( 'an h%d heading', 'wyvern-ai-styling' ),
					$level
				);
			case 'spacer':
				$height = $params['height'] ?? 100;
				return sprintf(
					/* translators: %d: spacer height in pixels */
					__( 'a spacer (%dpx)', 'wyvern-ai-styling' ),
					$height
				);
			case 'code':
				return __( 'a code block', 'wyvern-ai-styling' );
			case 'cover':
				return __( 'a cover/hero block', 'wyvern-ai-styling' );
			case 'media_text':
				return __( 'a media & text block', 'wyvern-ai-styling' );
			case 'gallery':
				return __( 'an image gallery', 'wyvern-ai-styling' );
			case 'details':
				return __( 'a details/accordion block', 'wyvern-ai-styling' );
			case 'search':
				return __( 'a search box', 'wyvern-ai-styling' );
			case 'navigation':
				return __( 'a navigation menu', 'wyvern-ai-styling' );
			case 'social_links':
				return __( 'a social links block', 'wyvern-ai-styling' );
			case 'buttons':
				return __( 'a buttons group', 'wyvern-ai-styling' );
			default:
				return sprintf(
					/* translators: %s: block type */
					__( 'a %s block', 'wyvern-ai-styling' ),
					str_replace( '_', ' ', $type )
				);
		}
	}

	/**
	 * Get friendly name for a block type
	 *
	 * @param string $block_name Block name (e.g., 'core/paragraph').
	 *
	 * @return string
	 */
	private function get_block_friendly_name( string $block_name ): string {
		$names = array(
			'core/paragraph'    => __( 'paragraph', 'wyvern-ai-styling' ),
			'core/heading'      => __( 'heading', 'wyvern-ai-styling' ),
			'core/table'        => __( 'table', 'wyvern-ai-styling' ),
			'core/list'         => __( 'list', 'wyvern-ai-styling' ),
			'core/image'        => __( 'image', 'wyvern-ai-styling' ),
			'core/button'       => __( 'button', 'wyvern-ai-styling' ),
			'core/buttons'      => __( 'buttons group', 'wyvern-ai-styling' ),
			'core/columns'      => __( 'column layout', 'wyvern-ai-styling' ),
			'core/group'        => __( 'group', 'wyvern-ai-styling' ),
			'core/cover'        => __( 'cover', 'wyvern-ai-styling' ),
			'core/quote'        => __( 'quote', 'wyvern-ai-styling' ),
			'core/code'         => __( 'code block', 'wyvern-ai-styling' ),
			'core/separator'    => __( 'separator', 'wyvern-ai-styling' ),
			'core/spacer'       => __( 'spacer', 'wyvern-ai-styling' ),
			'core/media-text'   => __( 'media & text', 'wyvern-ai-styling' ),
			'core/gallery'      => __( 'gallery', 'wyvern-ai-styling' ),
			'core/navigation'   => __( 'navigation', 'wyvern-ai-styling' ),
			'core/search'       => __( 'search', 'wyvern-ai-styling' ),
			'core/details'      => __( 'details', 'wyvern-ai-styling' ),
			'core/pullquote'    => __( 'pullquote', 'wyvern-ai-styling' ),
			'core/preformatted' => __( 'preformatted text', 'wyvern-ai-styling' ),
		);

		return $names[ $block_name ] ?? str_replace( array( 'core/', '-' ), array( '', ' ' ), $block_name );
	}

	/**
	 * Check if prompt is a content generation request
	 *
	 * @param string $prompt User prompt.
	 *
	 * @return bool
	 */
	public function is_content_request( string $prompt ): bool {
		// Quick check for content creation keywords.
		$creation_keywords = '/\b(?:add|create|insert|make|generate)\s+(?:a|an)?\s*(?:table|list|column|grid|button|card|callout|notice|alert|quote|image|separator|divider|heading|spacer|code|cover|hero|gallery|video|embed|search|menu|navigation)/i';

		return (bool) preg_match( $creation_keywords, $prompt );
	}

	/**
	 * Get all supported content types
	 *
	 * @return array<string, string>
	 */
	public function get_supported_types(): array {
		$types = array();
		foreach ( self::CONTENT_PATTERNS as $key => $config ) {
			$types[ $config['type'] ] = $config['blockName'];
		}
		return $types;
	}
}
