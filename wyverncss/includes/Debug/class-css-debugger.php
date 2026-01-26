<?php
/**
 * CSS Debugger Service
 *
 * Analyzes and fixes broken CSS.
 *
 * @package WyvernCSS
 * @subpackage Debug
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Debug;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CSS Debugger Class
 *
 * Parses CSS to identify errors and suggest fixes.
 */
class CSS_Debugger {

	/**
	 * Issue severity levels.
	 */
	public const SEVERITY_ERROR   = 'error';
	public const SEVERITY_WARNING = 'warning';
	public const SEVERITY_INFO    = 'info';

	/**
	 * Common CSS property typos and their corrections.
	 *
	 * @var array<string, string>
	 */
	private array $property_typos = array(
		'backgroud'         => 'background',
		'backround'         => 'background',
		'backgorund'        => 'background',
		'colour'            => 'color',
		'colur'             => 'color',
		'heigth'            => 'height',
		'widht'             => 'width',
		'dispaly'           => 'display',
		'positon'           => 'position',
		'postion'           => 'position',
		'margni'            => 'margin',
		'maring'            => 'margin',
		'padidng'           => 'padding',
		'paddng'            => 'padding',
		'font-weigth'       => 'font-weight',
		'font-wieght'       => 'font-weight',
		'font-szie'         => 'font-size',
		'border-raduis'     => 'border-radius',
		'border-radious'    => 'border-radius',
		'trasition'         => 'transition',
		'tranistion'        => 'transition',
		'tranform'          => 'transform',
		'transfrorm'        => 'transform',
		'z-idnex'           => 'z-index',
		'z-indx'            => 'z-index',
		'opactiy'           => 'opacity',
		'opacitiy'          => 'opacity',
		'visibilty'         => 'visibility',
		'visiblity'         => 'visibility',
		'overlfow'          => 'overflow',
		'overfow'           => 'overflow',
		'flex-direcion'     => 'flex-direction',
		'flex-direciton'    => 'flex-direction',
		'justfy-content'    => 'justify-content',
		'jusitfy-content'   => 'justify-content',
		'align-itmes'       => 'align-items',
		'allign-items'      => 'align-items',
		'text-algin'        => 'text-align',
		'text-algn'         => 'text-align',
		'font-famliy'       => 'font-family',
		'font-famly'        => 'font-family',
		'letter-spacng'     => 'letter-spacing',
		'line-heigt'        => 'line-height',
		'box-shadw'         => 'box-shadow',
		'box-shadwo'        => 'box-shadow',
		'border-collpase'   => 'border-collapse',
		'cursor-pointer'    => 'cursor',
		'backface-visiblity' => 'backface-visibility',
	);

	/**
	 * Common CSS value typos.
	 *
	 * @var array<string, string>
	 */
	private array $value_typos = array(
		'absolut'     => 'absolute',
		'relatvie'    => 'relative',
		'inhert'      => 'inherit',
		'initail'     => 'initial',
		'flax'        => 'flex',
		'infline'     => 'inline',
		'inlien'      => 'inline',
		'bloack'      => 'block',
		'blcok'       => 'block',
		'gird'        => 'grid',
		'hiden'       => 'hidden',
		'visiable'    => 'visible',
		'trasparent'  => 'transparent',
		'trasnsparent' => 'transparent',
		'underine'    => 'underline',
		'captialize'  => 'capitalize',
		'uppercasse'  => 'uppercase',
		'lowercasse'  => 'lowercase',
		'colunm'      => 'column',
		'colum'       => 'column',
		'no-warp'     => 'no-wrap',
		'nowrpa'      => 'nowrap',
		'scoll'       => 'scroll',
		'auot'        => 'auto',
		'aut0'        => 'auto',
		'ceneter'     => 'center',
		'cener'       => 'center',
		'strech'      => 'stretch',
		'sapce-between' => 'space-between',
		'space-beetween' => 'space-between',
		'spcae-around' => 'space-around',
	);

	/**
	 * Valid CSS display values.
	 *
	 * @var array<int, string>
	 */
	private array $valid_display_values = array(
		'none',
		'block',
		'inline',
		'inline-block',
		'flex',
		'inline-flex',
		'grid',
		'inline-grid',
		'table',
		'table-row',
		'table-cell',
		'list-item',
		'contents',
		'flow-root',
	);

	/**
	 * Valid CSS position values.
	 *
	 * @var array<int, string>
	 */
	private array $valid_position_values = array(
		'static',
		'relative',
		'absolute',
		'fixed',
		'sticky',
	);

	/**
	 * Analyze CSS for issues.
	 *
	 * @param string $css CSS to analyze.
	 * @return array<string, mixed> Analysis result.
	 */
	public function analyze( string $css ): array {
		$issues = array();
		$lines  = explode( "\n", $css );

		// Track parsing state.
		$brace_count        = 0;
		$in_selector        = true;
		$current_selector   = '';
		$line_number        = 0;
		$in_comment         = false;

		foreach ( $lines as $line ) {
			++$line_number;
			$trimmed = trim( $line );

			// Handle multi-line comments.
			if ( $in_comment ) {
				if ( strpos( $trimmed, '*/' ) !== false ) {
					$in_comment = false;
				}
				continue;
			}

			if ( strpos( $trimmed, '/*' ) !== false && strpos( $trimmed, '*/' ) === false ) {
				$in_comment = true;
				continue;
			}

			// Skip empty lines and single-line comments.
			if ( empty( $trimmed ) || strpos( $trimmed, '//' ) === 0 ) {
				continue;
			}

			// Check for opening brace.
			if ( strpos( $trimmed, '{' ) !== false ) {
				++$brace_count;
				$in_selector = false;

				// Extract selector.
				$current_selector = trim( explode( '{', $trimmed )[0] );

				// Check for empty selector.
				if ( empty( $current_selector ) ) {
					$issues[] = $this->create_issue(
						$line_number,
						self::SEVERITY_ERROR,
						'empty_selector',
						'Empty selector found.',
						$line
					);
				}
			}

			// Check for closing brace.
			if ( strpos( $trimmed, '}' ) !== false ) {
				--$brace_count;
				$in_selector = true;

				if ( $brace_count < 0 ) {
					$issues[]     = $this->create_issue(
						$line_number,
						self::SEVERITY_ERROR,
						'unmatched_brace',
						'Unexpected closing brace.',
						$line
					);
					$brace_count = 0;
				}
			}

			// Check property declarations.
			if ( ! $in_selector && strpos( $trimmed, ':' ) !== false && strpos( $trimmed, '{' ) === false ) {
				$issues = array_merge( $issues, $this->analyze_declaration( $trimmed, $line_number, $line ) );
			}
		}

		// Check for unclosed braces.
		if ( $brace_count > 0 ) {
			$issues[] = $this->create_issue(
				$line_number,
				self::SEVERITY_ERROR,
				'unclosed_brace',
				sprintf( '%d unclosed brace(s) found.', $brace_count ),
				''
			);
		}

		return array(
			'valid'         => 0 === count( array_filter( $issues, fn( $i ) => self::SEVERITY_ERROR === $i['severity'] ) ),
			'issues'        => $issues,
			'error_count'   => count( array_filter( $issues, fn( $i ) => self::SEVERITY_ERROR === $i['severity'] ) ),
			'warning_count' => count( array_filter( $issues, fn( $i ) => self::SEVERITY_WARNING === $i['severity'] ) ),
			'info_count'    => count( array_filter( $issues, fn( $i ) => self::SEVERITY_INFO === $i['severity'] ) ),
		);
	}

	/**
	 * Analyze a CSS declaration.
	 *
	 * @param string $declaration The declaration to analyze.
	 * @param int    $line_number Line number.
	 * @param string $full_line   Full line content.
	 * @return array<int, array<string, mixed>> Issues found.
	 */
	private function analyze_declaration( string $declaration, int $line_number, string $full_line ): array {
		$issues = array();

		// Remove trailing semicolon and comment for analysis.
		$clean = preg_replace( '/\/\*.*?\*\//', '', $declaration );
		if ( null === $clean ) {
			$clean = $declaration;
		}
		$clean = trim( $clean, " \t\n\r\0\x0B;}" );

		// Split property and value.
		$parts = explode( ':', $clean, 2 );

		if ( count( $parts ) !== 2 ) {
			return $issues;
		}

		$property = strtolower( trim( $parts[0] ) );
		$value    = trim( $parts[1] );

		// Check for property typos.
		if ( isset( $this->property_typos[ $property ] ) ) {
			$issues[] = $this->create_issue(
				$line_number,
				self::SEVERITY_ERROR,
				'property_typo',
				sprintf(
					'Property "%s" appears to be a typo. Did you mean "%s"?',
					$property,
					$this->property_typos[ $property ]
				),
				$full_line,
				array(
					'original'   => $property,
					'suggestion' => $this->property_typos[ $property ],
				)
			);
		}

		// Check for missing semicolon at end of line.
		$trimmed_decl = rtrim( $declaration );
		if ( ! str_ends_with( $trimmed_decl, ';' ) && ! str_ends_with( $trimmed_decl, '}' ) && ! str_ends_with( $trimmed_decl, '{' ) ) {
			$issues[] = $this->create_issue(
				$line_number,
				self::SEVERITY_WARNING,
				'missing_semicolon',
				'Missing semicolon at end of declaration.',
				$full_line,
				array( 'property' => $property )
			);
		}

		// Check for value typos.
		$value_lower = strtolower( $value );
		$value_words = preg_split( '/[\s,()]+/', $value_lower );
		if ( $value_words ) {
			foreach ( $value_words as $word ) {
				if ( isset( $this->value_typos[ $word ] ) ) {
					$issues[] = $this->create_issue(
						$line_number,
						self::SEVERITY_ERROR,
						'value_typo',
						sprintf(
							'Value "%s" appears to be a typo. Did you mean "%s"?',
							$word,
							$this->value_typos[ $word ]
						),
						$full_line,
						array(
							'original'   => $word,
							'suggestion' => $this->value_typos[ $word ],
						)
					);
				}
			}
		}

		// Check specific property values.
		$issues = array_merge( $issues, $this->validate_property_value( $property, $value, $line_number, $full_line ) );

		// Check for empty value.
		if ( empty( $value ) ) {
			$issues[] = $this->create_issue(
				$line_number,
				self::SEVERITY_ERROR,
				'empty_value',
				sprintf( 'Property "%s" has no value.', $property ),
				$full_line
			);
		}

		// Check for invalid color values.
		if ( in_array( $property, array( 'color', 'background-color', 'border-color', 'outline-color' ), true ) ) {
			$issues = array_merge( $issues, $this->validate_color( $value, $line_number, $full_line ) );
		}

		// Check for invalid numeric values.
		$issues = array_merge( $issues, $this->validate_numeric_value( $property, $value, $line_number, $full_line ) );

		return $issues;
	}

	/**
	 * Validate property-specific values.
	 *
	 * @param string $property    Property name.
	 * @param string $value       Property value.
	 * @param int    $line_number Line number.
	 * @param string $full_line   Full line content.
	 * @return array<int, array<string, mixed>> Issues found.
	 */
	private function validate_property_value( string $property, string $value, int $line_number, string $full_line ): array {
		$issues      = array();
		$value_lower = strtolower( $value );

		// Check display values.
		if ( 'display' === $property && ! in_array( $value_lower, $this->valid_display_values, true ) ) {
			if ( ! str_starts_with( $value_lower, 'var(' ) ) {
				$issues[] = $this->create_issue(
					$line_number,
					self::SEVERITY_WARNING,
					'invalid_display',
					sprintf( 'Unknown display value "%s".', $value ),
					$full_line,
					array( 'valid_values' => $this->valid_display_values )
				);
			}
		}

		// Check position values.
		if ( 'position' === $property && ! in_array( $value_lower, $this->valid_position_values, true ) ) {
			if ( ! str_starts_with( $value_lower, 'var(' ) ) {
				$issues[] = $this->create_issue(
					$line_number,
					self::SEVERITY_WARNING,
					'invalid_position',
					sprintf( 'Unknown position value "%s".', $value ),
					$full_line,
					array( 'valid_values' => $this->valid_position_values )
				);
			}
		}

		// Check z-index (should be integer).
		if ( 'z-index' === $property ) {
			if ( ! is_numeric( $value ) && 'auto' !== $value_lower && ! str_starts_with( $value_lower, 'var(' ) ) {
				$issues[] = $this->create_issue(
					$line_number,
					self::SEVERITY_ERROR,
					'invalid_zindex',
					'z-index must be a number or "auto".',
					$full_line
				);
			}
		}

		// Check opacity (should be 0-1).
		if ( 'opacity' === $property && is_numeric( $value ) ) {
			$opacity = (float) $value;
			if ( $opacity < 0 || $opacity > 1 ) {
				$issues[] = $this->create_issue(
					$line_number,
					self::SEVERITY_WARNING,
					'opacity_range',
					'Opacity should be between 0 and 1.',
					$full_line
				);
			}
		}

		return $issues;
	}

	/**
	 * Validate color values.
	 *
	 * @param string $value       Color value.
	 * @param int    $line_number Line number.
	 * @param string $full_line   Full line content.
	 * @return array<int, array<string, mixed>> Issues found.
	 */
	private function validate_color( string $value, int $line_number, string $full_line ): array {
		$issues      = array();
		$value_lower = strtolower( trim( rtrim( $value, ';' ) ) );

		// Skip CSS variables and functions.
		if ( str_starts_with( $value_lower, 'var(' ) ||
			str_starts_with( $value_lower, 'rgb(' ) ||
			str_starts_with( $value_lower, 'rgba(' ) ||
			str_starts_with( $value_lower, 'hsl(' ) ||
			str_starts_with( $value_lower, 'hsla(' ) ||
			str_starts_with( $value_lower, 'inherit' ) ||
			str_starts_with( $value_lower, 'initial' ) ||
			str_starts_with( $value_lower, 'unset' ) ||
			str_starts_with( $value_lower, 'currentcolor' ) ||
			str_starts_with( $value_lower, 'transparent' )
		) {
			return $issues;
		}

		// Check hex color format.
		if ( str_starts_with( $value_lower, '#' ) ) {
			$hex = substr( $value_lower, 1 );
			if ( ! preg_match( '/^[a-f0-9]{3}$|^[a-f0-9]{6}$|^[a-f0-9]{8}$/i', $hex ) ) {
				$issues[] = $this->create_issue(
					$line_number,
					self::SEVERITY_ERROR,
					'invalid_hex_color',
					'Invalid hex color format. Use #RGB, #RRGGBB, or #RRGGBBAA.',
					$full_line
				);
			}
		}

		return $issues;
	}

	/**
	 * Validate numeric values with units.
	 *
	 * @param string $property    Property name.
	 * @param string $value       Property value.
	 * @param int    $line_number Line number.
	 * @param string $full_line   Full line content.
	 * @return array<int, array<string, mixed>> Issues found.
	 */
	private function validate_numeric_value( string $property, string $value, int $line_number, string $full_line ): array {
		$issues      = array();
		$value_clean = trim( rtrim( $value, ';' ) );

		// Properties that require units.
		$requires_unit = array(
			'width',
			'height',
			'min-width',
			'max-width',
			'min-height',
			'max-height',
			'margin',
			'margin-top',
			'margin-right',
			'margin-bottom',
			'margin-left',
			'padding',
			'padding-top',
			'padding-right',
			'padding-bottom',
			'padding-left',
			'top',
			'right',
			'bottom',
			'left',
			'font-size',
			'border-width',
			'border-radius',
			'gap',
			'row-gap',
			'column-gap',
		);

		// Skip auto, inherit, etc.
		if ( in_array( strtolower( $value_clean ), array( 'auto', 'inherit', 'initial', 'unset', '0' ), true ) ) {
			return $issues;
		}

		// Skip CSS variables.
		if ( str_starts_with( strtolower( $value_clean ), 'var(' ) ) {
			return $issues;
		}

		// Skip calc() expressions.
		if ( str_starts_with( strtolower( $value_clean ), 'calc(' ) ) {
			return $issues;
		}

		// Check if property requires unit.
		if ( in_array( $property, $requires_unit, true ) ) {
			// Check if it's a plain number (not 0).
			if ( preg_match( '/^-?\d+(\.\d+)?$/', $value_clean ) && '0' !== $value_clean ) {
				$issues[] = $this->create_issue(
					$line_number,
					self::SEVERITY_ERROR,
					'missing_unit',
					sprintf( 'Property "%s" value "%s" requires a unit (e.g., px, em, rem, %%).', $property, $value_clean ),
					$full_line
				);
			}
		}

		return $issues;
	}

	/**
	 * Fix CSS issues automatically.
	 *
	 * @param string $css CSS to fix.
	 * @return array<string, mixed> Fixed CSS and changes made.
	 */
	public function fix( string $css ): array {
		$changes = array();
		$fixed   = $css;

		// Fix property typos.
		foreach ( $this->property_typos as $typo => $correct ) {
			if ( stripos( $fixed, $typo . ':' ) !== false ) {
				$pattern  = '/\b' . preg_quote( $typo, '/' ) . '\s*:/i';
				$replaced = preg_replace( $pattern, $correct . ':', $fixed );
				if ( null !== $replaced && $replaced !== $fixed ) {
					$changes[] = array(
						'type'    => 'property_typo',
						'from'    => $typo,
						'to'      => $correct,
					);
					$fixed = $replaced;
				}
			}
		}

		// Fix value typos.
		foreach ( $this->value_typos as $typo => $correct ) {
			if ( stripos( $fixed, $typo ) !== false ) {
				$pattern  = '/:\s*([^;]*\b)' . preg_quote( $typo, '/' ) . '(\b[^;]*;?)/i';
				$replaced = preg_replace_callback(
					$pattern,
					function ( $matches ) use ( $correct ) {
						return ':' . $matches[1] . $correct . $matches[2];
					},
					$fixed
				);
				if ( null !== $replaced && $replaced !== $fixed ) {
					$changes[] = array(
						'type'    => 'value_typo',
						'from'    => $typo,
						'to'      => $correct,
					);
					$fixed = $replaced;
				}
			}
		}

		// Fix missing semicolons (add before closing brace).
		$pattern  = '/([a-z0-9%\)#"\']+)\s*\}/i';
		$replaced = preg_replace_callback(
			$pattern,
			function ( $matches ) use ( &$changes ) {
				if ( ! str_ends_with( trim( $matches[1] ), ';' ) ) {
					$changes[] = array(
						'type' => 'add_semicolon',
						'near' => $matches[0],
					);
					return $matches[1] . ';}';
				}
				return $matches[0];
			},
			$fixed
		);
		if ( null !== $replaced ) {
			$fixed = $replaced;
		}

		// Fix unmatched braces - count and add missing closing braces.
		$open_count  = substr_count( $fixed, '{' );
		$close_count = substr_count( $fixed, '}' );

		if ( $open_count > $close_count ) {
			$missing = $open_count - $close_count;
			$fixed  .= str_repeat( "\n}", $missing );
			$changes[] = array(
				'type'  => 'add_closing_braces',
				'count' => $missing,
			);
		}

		return array(
			'original' => $css,
			'fixed'    => $fixed,
			'changes'  => $changes,
			'changed'  => $fixed !== $css,
		);
	}

	/**
	 * Get suggestions for improving CSS.
	 *
	 * @param string $css CSS to analyze.
	 * @return array<int, array<string, mixed>> Suggestions.
	 */
	public function get_suggestions( string $css ): array {
		$suggestions = array();

		// Check for vendor prefix usage without standard property.
		$vendor_prefixes = array( '-webkit-', '-moz-', '-ms-', '-o-' );
		foreach ( $vendor_prefixes as $prefix ) {
			if ( strpos( $css, $prefix ) !== false ) {
				$suggestions[] = array(
					'type'       => 'vendor_prefix',
					'severity'   => self::SEVERITY_INFO,
					'message'    => 'Consider using autoprefixer or postcss for vendor prefixes instead of manual prefixing.',
					'suggestion' => 'Use a build tool to automatically add vendor prefixes.',
				);
				break;
			}
		}

		// Check for !important overuse.
		$important_count = substr_count( $css, '!important' );
		if ( $important_count > 3 ) {
			$suggestions[] = array(
				'type'       => 'important_overuse',
				'severity'   => self::SEVERITY_WARNING,
				'message'    => sprintf( 'Found %d uses of !important. Consider improving CSS specificity instead.', $important_count ),
				'suggestion' => 'Use more specific selectors instead of !important.',
			);
		}

		// Check for duplicate properties.
		$properties = array();
		preg_match_all( '/([a-z-]+)\s*:/i', $css, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $prop ) {
				$prop_lower = strtolower( $prop );
				if ( ! isset( $properties[ $prop_lower ] ) ) {
					$properties[ $prop_lower ] = 0;
				}
				++$properties[ $prop_lower ];
			}

			$duplicates = array_filter( $properties, fn( $count ) => $count > 2 );
			if ( ! empty( $duplicates ) ) {
				$suggestions[] = array(
					'type'       => 'duplicate_properties',
					'severity'   => self::SEVERITY_INFO,
					'message'    => 'Some properties are declared multiple times: ' . implode( ', ', array_keys( $duplicates ) ),
					'suggestion' => 'Consider consolidating duplicate property declarations.',
				);
			}
		}

		// Check for very long selectors.
		preg_match_all( '/([^{}]+)\{/', $css, $selector_matches );
		if ( ! empty( $selector_matches[1] ) ) {
			foreach ( $selector_matches[1] as $selector ) {
				$depth = substr_count( $selector, ' ' );
				if ( $depth > 4 ) {
					$suggestions[] = array(
						'type'       => 'deep_nesting',
						'severity'   => self::SEVERITY_WARNING,
						'message'    => 'Deep selector nesting detected. This may cause specificity issues.',
						'suggestion' => 'Flatten your selectors or use BEM naming convention.',
						'selector'   => trim( $selector ),
					);
				}
			}
		}

		// Check for px usage in font-size.
		if ( preg_match( '/font-size\s*:\s*\d+px/i', $css ) ) {
			$suggestions[] = array(
				'type'       => 'px_font_size',
				'severity'   => self::SEVERITY_INFO,
				'message'    => 'Using px for font-size can cause accessibility issues.',
				'suggestion' => 'Consider using rem or em units for font-size for better accessibility.',
			);
		}

		return $suggestions;
	}

	/**
	 * Create an issue object.
	 *
	 * @param int                  $line     Line number.
	 * @param string               $severity Issue severity.
	 * @param string               $type     Issue type.
	 * @param string               $message  Human-readable message.
	 * @param string               $context  Line content.
	 * @param array<string, mixed> $extra    Extra data.
	 * @return array<string, mixed> Issue object.
	 */
	private function create_issue( int $line, string $severity, string $type, string $message, string $context, array $extra = array() ): array {
		return array_merge(
			array(
				'line'     => $line,
				'severity' => $severity,
				'type'     => $type,
				'message'  => $message,
				'context'  => trim( $context ),
			),
			$extra
		);
	}
}
