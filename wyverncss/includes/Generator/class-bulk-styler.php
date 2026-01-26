<?php
/**
 * Bulk Styler Service
 *
 * Applies CSS styles to multiple blocks simultaneously.
 *
 * @package WyvernCSS
 * @subpackage Generator
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Bulk Styler Class
 *
 * Generates and applies consistent CSS styles across multiple
 * Gutenberg blocks in a single operation.
 */
class Bulk_Styler {

	/**
	 * CSS Generator instance.
	 *
	 * @var CSSGenerator
	 */
	private CSSGenerator $css_generator;

	/**
	 * Maximum blocks per bulk operation.
	 */
	private const MAX_BLOCKS = 50;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->css_generator = new CSSGenerator();
	}

	/**
	 * Apply style to multiple blocks.
	 *
	 * @param string                           $prompt  Style prompt.
	 * @param array<int, array<string, mixed>> $blocks  Array of block contexts.
	 * @param array<string, mixed>             $options Additional options.
	 * @return array<string, mixed> Result with styled blocks.
	 */
	public function apply_to_blocks( string $prompt, array $blocks, array $options = array() ): array {
		// Validate block count.
		if ( count( $blocks ) > self::MAX_BLOCKS ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					'Maximum %d blocks allowed per bulk operation.',
					self::MAX_BLOCKS
				),
			);
		}

		if ( empty( $blocks ) ) {
			return array(
				'success' => false,
				'error'   => 'No blocks provided.',
			);
		}

		// Generate base CSS from prompt.
		$base_result = $this->css_generator->generate(
			$prompt,
			array(),
			$options
		);

		if ( is_wp_error( $base_result ) ) {
			return array(
				'success' => false,
				'error'   => $base_result->get_error_message(),
			);
		}

		$base_css    = $base_result['css'];
		$source      = $base_result['source'];
		$confidence  = $base_result['confidence'];

		// Apply to each block.
		$styled_blocks = array();
		$failures      = array();

		foreach ( $blocks as $index => $block ) {
			$block_result = $this->style_block( $block, $base_css, $options );

			if ( $block_result['success'] ) {
				$styled_blocks[] = $block_result;
			} else {
				$failures[] = array(
					'index' => $index,
					'error' => $block_result['error'],
					'block' => $block['clientId'] ?? "block_$index",
				);
			}
		}

		return array(
			'success'       => count( $failures ) === 0,
			'partial'       => count( $failures ) > 0 && count( $styled_blocks ) > 0,
			'styled_blocks' => $styled_blocks,
			'failures'      => $failures,
			'total_blocks'  => count( $blocks ),
			'styled_count'  => count( $styled_blocks ),
			'failed_count'  => count( $failures ),
			'css'           => $base_css,
			'source'        => $source,
			'confidence'    => $confidence,
		);
	}

	/**
	 * Apply style to blocks by selector.
	 *
	 * Finds all blocks matching a selector pattern and applies styles.
	 *
	 * @param string               $prompt   Style prompt.
	 * @param string               $selector Block selector (e.g., 'core/button', '.my-class').
	 * @param array<string, mixed> $options  Additional options.
	 * @return array<string, mixed> Result with selector info.
	 */
	public function apply_by_selector( string $prompt, string $selector, array $options = array() ): array {
		// Generate CSS.
		$result = $this->css_generator->generate(
			$prompt,
			array(),
			$options
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'error'   => $result->get_error_message(),
			);
		}

		// Determine if selector is a block name or CSS selector.
		$is_block_name = strpos( $selector, '/' ) !== false;

		return array(
			'success'       => true,
			'selector'      => $selector,
			'selector_type' => $is_block_name ? 'block_name' : 'css_selector',
			'css'           => $result['css'],
			'source'        => $result['source'],
			'confidence'    => $result['confidence'],
			'apply_method'  => $this->get_apply_method( $selector, $is_block_name ),
		);
	}

	/**
	 * Group blocks by type.
	 *
	 * @param array<int, array<string, mixed>> $blocks Array of block contexts.
	 * @return array<string, array<int, array<string, mixed>>> Blocks grouped by type.
	 */
	public function group_by_type( array $blocks ): array {
		$grouped = array();

		foreach ( $blocks as $block ) {
			$block_name = $block['blockName'] ?? 'unknown';

			if ( ! isset( $grouped[ $block_name ] ) ) {
				$grouped[ $block_name ] = array();
			}

			$grouped[ $block_name ][] = $block;
		}

		return $grouped;
	}

	/**
	 * Apply different styles to grouped blocks.
	 *
	 * @param array<string, string>                           $prompts Block type => prompt mapping.
	 * @param array<string, array<int, array<string, mixed>>> $grouped Grouped blocks.
	 * @param array<string, mixed>                            $options Additional options.
	 * @return array<string, array<string, mixed>> Results per block type.
	 */
	public function apply_to_groups(
		array $prompts,
		array $grouped,
		array $options = array()
	): array {
		$results = array();

		foreach ( $prompts as $block_type => $prompt ) {
			if ( ! isset( $grouped[ $block_type ] ) ) {
				$results[ $block_type ] = array(
					'success' => false,
					'error'   => "No blocks of type '$block_type' found.",
				);
				continue;
			}

			$results[ $block_type ] = $this->apply_to_blocks(
				$prompt,
				$grouped[ $block_type ],
				$options
			);
		}

		return $results;
	}

	/**
	 * Generate stylesheet for multiple selectors.
	 *
	 * @param array<string, string> $selector_prompts Selector => prompt mapping.
	 * @param array<string, mixed>  $options          Additional options.
	 * @return array<string, mixed> Combined stylesheet result.
	 */
	public function generate_stylesheet(
		array $selector_prompts,
		array $options = array()
	): array {
		$rules      = array();
		$errors     = array();
		$ai_used    = false;

		foreach ( $selector_prompts as $selector => $prompt ) {
			$result = $this->css_generator->generate(
				$prompt,
				array(),
				$options
			);

			if ( is_wp_error( $result ) ) {
				$errors[ $selector ] = $result->get_error_message();
				continue;
			}

			if ( 'ai' === $result['source'] ) {
				$ai_used = true;
			}

			$rules[ $selector ] = array(
				'css'        => $result['css'],
				'source'     => $result['source'],
				'confidence' => $result['confidence'],
			);
		}

		// Build combined stylesheet.
		$stylesheet = $this->build_stylesheet( $rules );

		return array(
			'success'    => count( $errors ) === 0,
			'partial'    => count( $errors ) > 0 && count( $rules ) > 0,
			'stylesheet' => $stylesheet,
			'rules'      => $rules,
			'errors'     => $errors,
			'ai_used'    => $ai_used,
		);
	}

	/**
	 * Validate blocks for bulk styling.
	 *
	 * @param array<int, array<string, mixed>> $blocks Array of block contexts.
	 * @return array<string, mixed> Validation result.
	 */
	public function validate_blocks( array $blocks ): array {
		$issues       = array();
		$valid_blocks = array();

		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				$issues[] = array(
					'index' => $index,
					'error' => 'Block must be an array.',
				);
				continue;
			}

			// Check for client ID.
			if ( empty( $block['clientId'] ) ) {
				$issues[] = array(
					'index' => $index,
					'error' => 'Block missing clientId.',
				);
				continue;
			}

			$valid_blocks[] = $block;
		}

		return array(
			'valid'          => count( $issues ) === 0,
			'valid_blocks'   => $valid_blocks,
			'issues'         => $issues,
			'total_count'    => count( $blocks ),
			'valid_count'    => count( $valid_blocks ),
			'invalid_count'  => count( $issues ),
		);
	}

	/**
	 * Style a single block.
	 *
	 * @param array<string, mixed> $block   Block context.
	 * @param array<string, mixed> $css     CSS properties.
	 * @param array<string, mixed> $options Options.
	 * @return array<string, mixed> Styled block result.
	 */
	private function style_block( array $block, array $css, array $options ): array {
		$client_id  = $block['clientId'] ?? null;
		$block_name = $block['blockName'] ?? 'unknown';

		if ( empty( $client_id ) ) {
			return array(
				'success' => false,
				'error'   => 'Block missing clientId.',
			);
		}

		// Adapt CSS if needed based on block type.
		$adapted_css = $this->adapt_css_for_block( $css, $block_name );

		return array(
			'success'    => true,
			'clientId'   => $client_id,
			'blockName'  => $block_name,
			'css'        => $adapted_css,
			'attributes' => $block['attributes'] ?? array(),
		);
	}

	/**
	 * Adapt CSS for specific block types.
	 *
	 * @param array<string, mixed> $css        CSS properties.
	 * @param string               $block_name Block name.
	 * @return array<string, mixed> Adapted CSS.
	 */
	private function adapt_css_for_block( array $css, string $block_name ): array {
		$adapted = $css;

		// Handle button-specific adaptations.
		if ( strpos( $block_name, 'button' ) !== false ) {
			// Buttons might need padding adjusted.
			if ( isset( $adapted['padding'] ) && empty( $adapted['padding'] ) ) {
				unset( $adapted['padding'] );
			}
		}

		// Handle heading-specific adaptations.
		if ( strpos( $block_name, 'heading' ) !== false ) {
			// Don't apply background to headings by default.
			if ( isset( $adapted['background-color'] ) && empty( $css['force-background'] ?? false ) ) {
				unset( $adapted['background-color'] );
			}
		}

		return $adapted;
	}

	/**
	 * Get apply method instructions.
	 *
	 * @param string $selector      Selector string.
	 * @param bool   $is_block_name Whether selector is block name.
	 * @return array<string, mixed> Apply method info.
	 */
	private function get_apply_method( string $selector, bool $is_block_name ): array {
		if ( $is_block_name ) {
			return array(
				'type'        => 'block_attribute',
				'description' => 'Apply via block className or style attribute',
				'target'      => $selector,
			);
		}

		return array(
			'type'        => 'css_rule',
			'description' => 'Add CSS rule to stylesheet',
			'target'      => $selector,
		);
	}

	/**
	 * Build stylesheet from rules.
	 *
	 * @param array<string, array<string, mixed>> $rules CSS rules by selector.
	 * @return string Combined stylesheet.
	 */
	private function build_stylesheet( array $rules ): string {
		$lines = array();

		foreach ( $rules as $selector => $data ) {
			$css = $data['css'];

			if ( empty( $css ) ) {
				continue;
			}

			$properties = array();
			foreach ( $css as $prop => $value ) {
				$properties[] = sprintf( '  %s: %s;', esc_attr( $prop ), esc_attr( (string) $value ) );
			}

			$lines[] = sprintf(
				"%s {\n%s\n}",
				esc_attr( $selector ),
				implode( "\n", $properties )
			);
		}

		return implode( "\n\n", $lines );
	}
}
