<?php
/**
 * Context Extractor
 *
 * Extracts element context from DOM for AI prompt generation.
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
 * Class ContextExtractor
 *
 * Analyzes element context for informed CSS generation.
 */
class ContextExtractor {

	/**
	 * Extract context from element data
	 *
	 * @param array<string, mixed> $element_data Raw element data from frontend.
	 *
	 * @return array<string, mixed> Structured element context.
	 */
	public function extract( array $element_data ): array {
		$context = array(
			'tag'            => $this->extract_tag( $element_data ),
			'classes'        => $this->extract_classes( $element_data ),
			'current_styles' => $this->extract_current_styles( $element_data ),
			'parent'         => $this->extract_parent_info( $element_data ),
			'children'       => $this->extract_children_info( $element_data ),
			'position'       => $this->extract_position( $element_data ),
			'dimensions'     => $this->extract_dimensions( $element_data ),
			'block_type'     => $this->extract_block_type( $element_data ),
		);

		return $context;
	}

	/**
	 * Extract tag name
	 *
	 * @param array<string, mixed> $element_data Element data.
	 *
	 * @return string Tag name.
	 */
	private function extract_tag( array $element_data ): string {
		$tag = $element_data['tagName'] ?? $element_data['tag'] ?? 'div';

		// Sanitize tag name.
		$tag = strtolower( sanitize_text_field( $tag ) );

		// Validate it's a real HTML tag.
		$valid_tags = array(
			'div',
			'span',
			'p',
			'h1',
			'h2',
			'h3',
			'h4',
			'h5',
			'h6',
			'section',
			'article',
			'header',
			'footer',
			'nav',
			'aside',
			'ul',
			'ol',
			'li',
			'a',
			'button',
			'img',
			'figure',
			'figcaption',
		);

		return in_array( $tag, $valid_tags, true ) ? $tag : 'div';
	}

	/**
	 * Extract class names
	 *
	 * @param array<string, mixed> $element_data Element data.
	 *
	 * @return array<int, string> Class names.
	 */
	private function extract_classes( array $element_data ): array {
		$classes = $element_data['className'] ?? $element_data['classes'] ?? array();

		if ( is_string( $classes ) ) {
			$classes = explode( ' ', $classes );
		}

		if ( ! is_array( $classes ) ) {
			return array();
		}

		// Sanitize and filter classes.
		$classes = array_map( 'sanitize_html_class', $classes );
		$classes = array_filter( $classes );

		return array_values( $classes );
	}

	/**
	 * Extract current computed styles
	 *
	 * @param array<string, mixed> $element_data Element data.
	 *
	 * @return array<string, string> Current styles.
	 */
	private function extract_current_styles( array $element_data ): array {
		$styles = $element_data['computedStyles'] ?? $element_data['styles'] ?? array();

		if ( ! is_array( $styles ) ) {
			return array();
		}

		// Sanitize style properties.
		$sanitized = array();

		foreach ( $styles as $property => $value ) {
			$property = sanitize_text_field( $property );
			$value    = sanitize_text_field( $value );

			// Only include relevant properties.
			if ( $this->is_relevant_style_property( $property ) ) {
				$sanitized[ $property ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Extract parent element information
	 *
	 * @param array<string, mixed> $element_data Element data.
	 *
	 * @return string|null Parent info.
	 */
	private function extract_parent_info( array $element_data ): ?string {
		$parent = $element_data['parent'] ?? null;

		if ( empty( $parent ) ) {
			return null;
		}

		if ( is_array( $parent ) ) {
			$parent_tag   = $parent['tagName'] ?? $parent['tag'] ?? 'unknown';
			$parent_class = $parent['className'] ?? '';

			return sprintf(
				'%s%s',
				sanitize_text_field( $parent_tag ),
				! empty( $parent_class ) ? '.' . sanitize_html_class( $parent_class ) : ''
			);
		}

		return sanitize_text_field( $parent );
	}

	/**
	 * Extract children information
	 *
	 * @param array<string, mixed> $element_data Element data.
	 *
	 * @return array<string, mixed> Children info.
	 */
	private function extract_children_info( array $element_data ): array {
		$children = $element_data['children'] ?? array();

		if ( ! is_array( $children ) ) {
			return array(
				'count' => 0,
				'types' => array(),
			);
		}

		$types = array();

		foreach ( $children as $child ) {
			if ( is_array( $child ) ) {
				$tag     = $child['tagName'] ?? $child['tag'] ?? 'unknown';
				$types[] = sanitize_text_field( $tag );
			}
		}

		return array(
			'count' => count( $children ),
			'types' => array_unique( $types ),
		);
	}

	/**
	 * Extract position information
	 *
	 * @param array<string, mixed> $element_data Element data.
	 *
	 * @return array<string, string|null> Position data.
	 */
	private function extract_position( array $element_data ): array {
		$position = $element_data['position'] ?? array();

		if ( ! is_array( $position ) ) {
			return array();
		}

		return array(
			'display'  => $position['display'] ?? null,
			'position' => $position['position'] ?? null,
			'float'    => $position['float'] ?? null,
		);
	}

	/**
	 * Extract dimensions
	 *
	 * @param array<string, mixed> $element_data Element data.
	 *
	 * @return array<string, int> Dimensions.
	 */
	private function extract_dimensions( array $element_data ): array {
		$dimensions = array();

		if ( isset( $element_data['width'] ) ) {
			$dimensions['width'] = (int) $element_data['width'];
		}

		if ( isset( $element_data['height'] ) ) {
			$dimensions['height'] = (int) $element_data['height'];
		}

		return $dimensions;
	}

	/**
	 * Extract Gutenberg block type
	 *
	 * @param array<string, mixed> $element_data Element data.
	 *
	 * @return string|null Block type.
	 */
	private function extract_block_type( array $element_data ): ?string {
		$block_type = $element_data['blockType'] ?? $element_data['block_type'] ?? null;

		if ( empty( $block_type ) ) {
			return null;
		}

		return sanitize_text_field( $block_type );
	}

	/**
	 * Check if style property is relevant
	 *
	 * @param string $property Property name.
	 *
	 * @return bool True if relevant.
	 */
	private function is_relevant_style_property( string $property ): bool {
		// Properties that are useful for context.
		$relevant = array(
			'display',
			'position',
			'float',
			'flex-direction',
			'grid-template-columns',
			'width',
			'height',
			'max-width',
			'max-height',
			'padding',
			'margin',
			'border',
			'font-size',
			'font-family',
			'color',
			'background-color',
		);

		// Check if property starts with any relevant prefix.
		foreach ( $relevant as $rel ) {
			if ( strpos( $property, $rel ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract context summary for logging
	 *
	 * @param array<string, mixed> $context Element context.
	 *
	 * @return string Context summary.
	 */
	public function get_context_summary( array $context ): string {
		$parts = array();

		if ( ! empty( $context['tag'] ) ) {
			$parts[] = $context['tag'];
		}

		if ( ! empty( $context['classes'] ) ) {
			$parts[] = '.' . implode( '.', array_slice( $context['classes'], 0, 3 ) );
		}

		if ( ! empty( $context['block_type'] ) ) {
			$parts[] = sprintf( '[%s]', $context['block_type'] );
		}

		return implode( ' ', $parts );
	}

	/**
	 * Validate context data
	 *
	 * @param array<string, mixed> $context Context data.
	 *
	 * @return bool True if valid.
	 */
	public function validate_context( array $context ): bool {
		// Must have at least a tag.
		return ! empty( $context['tag'] );
	}

	/**
	 * Merge contexts (for multi-element selection)
	 *
	 * @param array<int, array<string, mixed>> $contexts Array of context arrays.
	 *
	 * @return array<string, mixed> Merged context.
	 */
	public function merge_contexts( array $contexts ): array {
		if ( empty( $contexts ) ) {
			return array();
		}

		if ( count( $contexts ) === 1 ) {
			return $contexts[0];
		}

		// Merge multiple contexts.
		$merged = array(
			'tag'            => 'multiple',
			'classes'        => array(),
			'current_styles' => array(),
			'common_parent'  => null,
		);

		// Collect all classes.
		foreach ( $contexts as $context ) {
			if ( ! empty( $context['classes'] ) ) {
				$merged['classes'] = array_merge( $merged['classes'], $context['classes'] );
			}
		}

		$merged['classes'] = array_unique( $merged['classes'] );

		return $merged;
	}
}
