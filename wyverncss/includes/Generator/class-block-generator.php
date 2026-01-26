<?php
/**
 * Block Generator
 *
 * Generates Gutenberg block specifications from detected content requests.
 *
 * @package WyvernCSS
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// Import WordPress i18n functions.
use function __;
use function sprintf;

/**
 * Class BlockGenerator
 *
 * Creates Gutenberg-compatible block structures for various content types.
 * Returns block specifications that can be used by the frontend to create actual blocks.
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Generator methods use consistent $params signature for extensibility.
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassAfterLastUsed
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 */
class BlockGenerator {

	/**
	 * Generate block specification based on content type
	 *
	 * @param string               $type Content type (e.g., 'table', 'list_unordered').
	 * @param array<string, mixed> $params Parameters for generation.
	 *
	 * @return array{name: string, attributes: array<string, mixed>, innerBlocks?: array<int, array<string, mixed>>}
	 */
	public function generate( string $type, array $params = array() ): array {
		$method = 'generate_' . $type;

		if ( method_exists( $this, $method ) ) {
			return $this->$method( $params );
		}

		// Fallback for simple block types.
		return $this->generate_simple_block( $type, $params );
	}

	/**
	 * Generate table block
	 *
	 * @param array<string, mixed> $params Parameters including 'columns', 'rows', 'headers'.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_table( array $params ): array {
		$columns = (int) ( $params['columns'] ?? 2 );
		$rows    = (int) ( $params['rows'] ?? 3 );
		$headers = $params['headers'] ?? array();

		// Ensure columns matches headers count if headers provided.
		if ( ! empty( $headers ) ) {
			$columns = count( $headers );
		}

		// Build head section if headers provided.
		$head = array();
		if ( ! empty( $headers ) ) {
			$head_cells = array();
			foreach ( $headers as $header ) {
				$head_cells[] = array(
					'content' => (string) $header,
					'tag'     => 'th',
				);
			}
			$head = array(
				array( 'cells' => $head_cells ),
			);
		}

		// Build body section.
		$body = array();
		for ( $r = 0; $r < $rows; $r++ ) {
			$cells = array();
			for ( $c = 0; $c < $columns; $c++ ) {
				$cells[] = array(
					'content' => '',
					'tag'     => 'td',
				);
			}
			$body[] = array( 'cells' => $cells );
		}

		return array(
			'name'       => 'core/table',
			'attributes' => array(
				'hasFixedLayout' => true,
				'head'           => $head,
				'body'           => $body,
				'foot'           => array(),
			),
		);
	}

	/**
	 * Generate table with headers block
	 *
	 * @param array<string, mixed> $params Parameters including 'headers', 'rows'.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_table_with_headers( array $params ): array {
		// Delegate to generate_table which already handles headers.
		return $this->generate_table( $params );
	}

	/**
	 * Generate unordered list block
	 *
	 * @param array<string, mixed> $params Parameters including 'items'.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_list_unordered( array $params ): array {
		$items  = (int) ( $params['items'] ?? 3 );
		$values = '';

		for ( $i = 1; $i <= $items; $i++ ) {
			$values .= "<li>Item {$i}</li>";
		}

		return array(
			'name'       => 'core/list',
			'attributes' => array(
				'ordered' => false,
				'values'  => $values,
			),
		);
	}

	/**
	 * Generate ordered list block
	 *
	 * @param array<string, mixed> $params Parameters including 'items'.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_list_ordered( array $params ): array {
		$items  = (int) ( $params['items'] ?? 3 );
		$values = '';

		for ( $i = 1; $i <= $items; $i++ ) {
			$values .= "<li>Item {$i}</li>";
		}

		return array(
			'name'       => 'core/list',
			'attributes' => array(
				'ordered' => true,
				'values'  => $values,
			),
		);
	}

	/**
	 * Generate columns layout block
	 *
	 * @param array<string, mixed> $params Parameters including 'count'.
	 *
	 * @return array{name: string, attributes: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>}
	 */
	private function generate_columns( array $params ): array {
		$count        = (int) ( $params['count'] ?? 2 );
		$inner_blocks = array();

		// Create individual column blocks.
		for ( $i = 0; $i < $count; $i++ ) {
			$inner_blocks[] = array(
				'name'        => 'core/column',
				'attributes'  => array(),
				'innerBlocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array(
							'content' => sprintf(
								/* translators: %d: column number */
								__( 'Column %d content', 'wyverncss' ),
								$i + 1
							),
						),
					),
				),
			);
		}

		return array(
			'name'        => 'core/columns',
			'attributes'  => array(
				'verticalAlignment' => 'top',
			),
			'innerBlocks' => $inner_blocks,
		);
	}

	/**
	 * Generate button block
	 *
	 * @param array<string, mixed> $params Parameters including 'text'.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_button( array $params ): array {
		$text = $params['text'] ?? __( 'Click me', 'wyverncss' );

		return array(
			'name'       => 'core/button',
			'attributes' => array(
				'text' => $text,
			),
		);
	}

	/**
	 * Generate buttons group block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>}
	 */
	private function generate_buttons( array $params ): array {
		return array(
			'name'        => 'core/buttons',
			'attributes'  => array(),
			'innerBlocks' => array(
				array(
					'name'       => 'core/button',
					'attributes' => array(
						'text' => __( 'Button 1', 'wyverncss' ),
					),
				),
				array(
					'name'       => 'core/button',
					'attributes' => array(
						'text' => __( 'Button 2', 'wyverncss' ),
					),
				),
			),
		);
	}

	/**
	 * Generate callout/notice block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>}
	 */
	private function generate_callout( array $params ): array {
		return array(
			'name'        => 'core/group',
			'attributes'  => array(
				'className' => 'wyverncss-callout',
				'style'     => array(
					'border'  => array(
						'width' => '2px',
						'style' => 'solid',
						'color' => '#0073aa',
					),
					'spacing' => array(
						'padding' => array(
							'top'    => '1em',
							'bottom' => '1em',
							'left'   => '1em',
							'right'  => '1em',
						),
					),
					'color'   => array(
						'background' => '#f0f7fc',
					),
				),
			),
			'innerBlocks' => array(
				array(
					'name'       => 'core/paragraph',
					'attributes' => array(
						'content'  => __( 'Your callout message here.', 'wyverncss' ),
						'fontSize' => 'medium',
					),
				),
			),
		);
	}

	/**
	 * Generate card block
	 *
	 * @param array<string, mixed> $params Parameters including 'title'.
	 *
	 * @return array{name: string, attributes: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>}
	 */
	private function generate_card( array $params ): array {
		$title = $params['title'] ?? __( 'Card Title', 'wyverncss' );

		return array(
			'name'        => 'core/group',
			'attributes'  => array(
				'className' => 'wyverncss-card',
				'style'     => array(
					'border'  => array(
						'width'  => '1px',
						'style'  => 'solid',
						'color'  => '#ddd',
						'radius' => '8px',
					),
					'spacing' => array(
						'padding' => array(
							'top'    => '1.5em',
							'bottom' => '1.5em',
							'left'   => '1.5em',
							'right'  => '1.5em',
						),
					),
					'shadow'  => 'var(--wp--preset--shadow--natural)',
				),
			),
			'innerBlocks' => array(
				array(
					'name'       => 'core/heading',
					'attributes' => array(
						'content' => $title,
						'level'   => 3,
					),
				),
				array(
					'name'       => 'core/paragraph',
					'attributes' => array(
						'content' => __( 'Card description goes here. Add your content to describe this card.', 'wyverncss' ),
					),
				),
			),
		);
	}

	/**
	 * Generate quote block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_quote( array $params ): array {
		return array(
			'name'       => 'core/quote',
			'attributes' => array(
				'value'    => '<p>' . __( 'Your quote text here.', 'wyverncss' ) . '</p>',
				'citation' => __( '— Citation', 'wyverncss' ),
			),
		);
	}

	/**
	 * Generate pullquote block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_pullquote( array $params ): array {
		return array(
			'name'       => 'core/pullquote',
			'attributes' => array(
				'value'    => '<p>' . __( 'Your pullquote text here.', 'wyverncss' ) . '</p>',
				'citation' => __( '— Citation', 'wyverncss' ),
			),
		);
	}

	/**
	 * Generate image block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_image( array $params ): array {
		return array(
			'name'       => 'core/image',
			'attributes' => array(
				'alt'     => __( 'Placeholder image', 'wyverncss' ),
				'caption' => __( 'Image caption', 'wyverncss' ),
			),
		);
	}

	/**
	 * Generate separator block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_separator( array $params ): array {
		return array(
			'name'       => 'core/separator',
			'attributes' => array(
				'className' => 'is-style-wide',
			),
		);
	}

	/**
	 * Generate heading block
	 *
	 * @param array<string, mixed> $params Parameters including 'level'.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_heading( array $params ): array {
		$level = (int) ( $params['level'] ?? $params['level_alt'] ?? 2 );
		$level = max( 1, min( 6, $level ) ); // Clamp between 1 and 6.

		return array(
			'name'       => 'core/heading',
			'attributes' => array(
				'level'   => $level,
				'content' => __( 'Heading text', 'wyverncss' ),
			),
		);
	}

	/**
	 * Generate spacer block
	 *
	 * @param array<string, mixed> $params Parameters including 'height'.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_spacer( array $params ): array {
		$height = (int) ( $params['height'] ?? 100 );

		return array(
			'name'       => 'core/spacer',
			'attributes' => array(
				'height' => $height . 'px',
			),
		);
	}

	/**
	 * Generate code block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_code( array $params ): array {
		return array(
			'name'       => 'core/code',
			'attributes' => array(
				'content' => '// ' . __( 'Your code here', 'wyverncss' ),
			),
		);
	}

	/**
	 * Generate preformatted block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_preformatted( array $params ): array {
		return array(
			'name'       => 'core/preformatted',
			'attributes' => array(
				'content' => __( 'Preformatted text here', 'wyverncss' ),
			),
		);
	}

	/**
	 * Generate cover block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>}
	 */
	private function generate_cover( array $params ): array {
		return array(
			'name'        => 'core/cover',
			'attributes'  => array(
				'overlayColor'  => 'primary',
				'minHeight'     => 300,
				'minHeightUnit' => 'px',
				'isDark'        => true,
				'dimRatio'      => 50,
			),
			'innerBlocks' => array(
				array(
					'name'       => 'core/heading',
					'attributes' => array(
						'content'   => __( 'Cover Title', 'wyverncss' ),
						'level'     => 2,
						'textAlign' => 'center',
					),
				),
				array(
					'name'       => 'core/paragraph',
					'attributes' => array(
						'content' => __( 'Your cover text here.', 'wyverncss' ),
						'align'   => 'center',
					),
				),
			),
		);
	}

	/**
	 * Generate media-text block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>}
	 */
	private function generate_media_text( array $params ): array {
		return array(
			'name'        => 'core/media-text',
			'attributes'  => array(
				'mediaPosition' => 'left',
				'mediaWidth'    => 50,
			),
			'innerBlocks' => array(
				array(
					'name'       => 'core/heading',
					'attributes' => array(
						'content' => __( 'Media & Text Title', 'wyverncss' ),
						'level'   => 3,
					),
				),
				array(
					'name'       => 'core/paragraph',
					'attributes' => array(
						'content' => __( 'Add your text content here beside the media.', 'wyverncss' ),
					),
				),
			),
		);
	}

	/**
	 * Generate gallery block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_gallery( array $params ): array {
		return array(
			'name'       => 'core/gallery',
			'attributes' => array(
				'columns' => 3,
				'linkTo'  => 'none',
			),
		);
	}

	/**
	 * Generate video block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_video( array $params ): array {
		return array(
			'name'       => 'core/video',
			'attributes' => array(
				'controls' => true,
			),
		);
	}

	/**
	 * Generate embed block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_embed( array $params ): array {
		return array(
			'name'       => 'core/embed',
			'attributes' => array(
				'providerNameSlug' => 'youtube',
			),
		);
	}

	/**
	 * Generate social links block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>}
	 */
	private function generate_social_links( array $params ): array {
		return array(
			'name'        => 'core/social-links',
			'attributes'  => array(
				'className' => 'is-style-default',
			),
			'innerBlocks' => array(
				array(
					'name'       => 'core/social-link',
					'attributes' => array(
						'service' => 'facebook',
					),
				),
				array(
					'name'       => 'core/social-link',
					'attributes' => array(
						'service' => 'twitter',
					),
				),
				array(
					'name'       => 'core/social-link',
					'attributes' => array(
						'service' => 'linkedin',
					),
				),
			),
		);
	}

	/**
	 * Generate search block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_search( array $params ): array {
		return array(
			'name'       => 'core/search',
			'attributes' => array(
				'label'      => __( 'Search', 'wyverncss' ),
				'buttonText' => __( 'Search', 'wyverncss' ),
				'showLabel'  => false,
			),
		);
	}

	/**
	 * Generate page break block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_page_break( array $params ): array {
		return array(
			'name'       => 'core/nextpage',
			'attributes' => array(),
		);
	}

	/**
	 * Generate table of contents block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_toc( array $params ): array {
		return array(
			'name'       => 'core/table-of-contents',
			'attributes' => array(
				'headings' => array(),
			),
		);
	}

	/**
	 * Generate read more block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_read_more( array $params ): array {
		return array(
			'name'       => 'core/more',
			'attributes' => array(
				'customText' => __( 'Read more', 'wyverncss' ),
			),
		);
	}

	/**
	 * Generate details/accordion block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>}
	 */
	private function generate_details( array $params ): array {
		return array(
			'name'        => 'core/details',
			'attributes'  => array(
				'summary' => __( 'Click to expand', 'wyverncss' ),
			),
			'innerBlocks' => array(
				array(
					'name'       => 'core/paragraph',
					'attributes' => array(
						'content' => __( 'Hidden content goes here.', 'wyverncss' ),
					),
				),
			),
		);
	}

	/**
	 * Generate footnotes block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_footnotes( array $params ): array {
		return array(
			'name'       => 'core/footnotes',
			'attributes' => array(),
		);
	}

	/**
	 * Generate audio block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_audio( array $params ): array {
		return array(
			'name'       => 'core/audio',
			'attributes' => array(),
		);
	}

	/**
	 * Generate file block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_file( array $params ): array {
		return array(
			'name'       => 'core/file',
			'attributes' => array(
				'showDownloadButton' => true,
			),
		);
	}

	/**
	 * Generate verse block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_verse( array $params ): array {
		return array(
			'name'       => 'core/verse',
			'attributes' => array(
				'content' => __( 'Write poetry here...', 'wyverncss' ),
			),
		);
	}

	/**
	 * Generate row block (group with flex row)
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_row( array $params ): array {
		return array(
			'name'       => 'core/group',
			'attributes' => array(
				'layout' => array(
					'type'        => 'flex',
					'flexWrap'    => 'nowrap',
					'orientation' => 'horizontal',
				),
			),
		);
	}

	/**
	 * Generate stack block (group with flex column)
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_stack( array $params ): array {
		return array(
			'name'       => 'core/group',
			'attributes' => array(
				'layout' => array(
					'type'        => 'flex',
					'orientation' => 'vertical',
				),
			),
		);
	}

	/**
	 * Generate navigation block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_navigation( array $params ): array {
		return array(
			'name'       => 'core/navigation',
			'attributes' => array(),
		);
	}

	/**
	 * Generate site logo block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_site_logo( array $params ): array {
		return array(
			'name'       => 'core/site-logo',
			'attributes' => array(
				'width' => 120,
			),
		);
	}

	/**
	 * Generate site title block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_site_title( array $params ): array {
		return array(
			'name'       => 'core/site-title',
			'attributes' => array(),
		);
	}

	/**
	 * Generate site tagline block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_site_tagline( array $params ): array {
		return array(
			'name'       => 'core/site-tagline',
			'attributes' => array(),
		);
	}

	/**
	 * Generate query loop block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>}
	 */
	private function generate_query( array $params ): array {
		return array(
			'name'        => 'core/query',
			'attributes'  => array(
				'queryId' => 0,
				'query'   => array(
					'perPage'  => 5,
					'pages'    => 0,
					'offset'   => 0,
					'postType' => 'post',
					'order'    => 'desc',
					'orderBy'  => 'date',
				),
			),
			'innerBlocks' => array(
				array(
					'name'        => 'core/post-template',
					'innerBlocks' => array(
						array(
							'name'       => 'core/post-title',
							'attributes' => array(
								'isLink' => true,
							),
						),
						array(
							'name'       => 'core/post-excerpt',
							'attributes' => array(),
						),
					),
				),
			),
		);
	}

	/**
	 * Generate post title block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_post_title( array $params ): array {
		return array(
			'name'       => 'core/post-title',
			'attributes' => array(
				'level' => 1,
			),
		);
	}

	/**
	 * Generate post content block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_post_content( array $params ): array {
		return array(
			'name'       => 'core/post-content',
			'attributes' => array(),
		);
	}

	/**
	 * Generate post excerpt block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_post_excerpt( array $params ): array {
		return array(
			'name'       => 'core/post-excerpt',
			'attributes' => array(),
		);
	}

	/**
	 * Generate post date block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_post_date( array $params ): array {
		return array(
			'name'       => 'core/post-date',
			'attributes' => array(),
		);
	}

	/**
	 * Generate post author block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_post_author( array $params ): array {
		return array(
			'name'       => 'core/post-author',
			'attributes' => array(
				'showAvatar' => true,
			),
		);
	}

	/**
	 * Generate featured image block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_featured_image( array $params ): array {
		return array(
			'name'       => 'core/post-featured-image',
			'attributes' => array(),
		);
	}

	/**
	 * Generate comments block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_comments( array $params ): array {
		return array(
			'name'       => 'core/comments',
			'attributes' => array(),
		);
	}

	/**
	 * Generate categories block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_categories( array $params ): array {
		return array(
			'name'       => 'core/post-terms',
			'attributes' => array(
				'term' => 'category',
			),
		);
	}

	/**
	 * Generate tags block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_tags( array $params ): array {
		return array(
			'name'       => 'core/post-terms',
			'attributes' => array(
				'term' => 'post_tag',
			),
		);
	}

	/**
	 * Generate archives block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_archives( array $params ): array {
		return array(
			'name'       => 'core/archives',
			'attributes' => array(
				'displayAsDropdown' => false,
				'showPostCounts'    => true,
			),
		);
	}

	/**
	 * Generate calendar block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_calendar( array $params ): array {
		return array(
			'name'       => 'core/calendar',
			'attributes' => array(),
		);
	}

	/**
	 * Generate latest posts block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_latest_posts( array $params ): array {
		return array(
			'name'       => 'core/latest-posts',
			'attributes' => array(
				'postsToShow'     => 5,
				'displayAuthor'   => false,
				'displayPostDate' => true,
			),
		);
	}

	/**
	 * Generate latest comments block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_latest_comments( array $params ): array {
		return array(
			'name'       => 'core/latest-comments',
			'attributes' => array(
				'commentsToShow' => 5,
			),
		);
	}

	/**
	 * Generate tag cloud block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_tag_cloud( array $params ): array {
		return array(
			'name'       => 'core/tag-cloud',
			'attributes' => array(
				'showTagCounts' => true,
			),
		);
	}

	/**
	 * Generate RSS block
	 *
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_rss( array $params ): array {
		return array(
			'name'       => 'core/rss',
			'attributes' => array(
				'itemsToShow' => 5,
			),
		);
	}

	/**
	 * Generate a simple block for types without special handling
	 *
	 * @param string               $type Content type.
	 * @param array<string, mixed> $params Parameters.
	 *
	 * @return array{name: string, attributes: array<string, mixed>}
	 */
	private function generate_simple_block( string $type, array $params ): array {
		// Map type to block name.
		$block_name = 'core/' . str_replace( '_', '-', $type );

		return array(
			'name'       => $block_name,
			'attributes' => $params,
		);
	}
}
