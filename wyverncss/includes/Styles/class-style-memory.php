<?php
/**
 * Style Memory Service
 *
 * Remembers user styling preferences and patterns to provide
 * personalized suggestions and consistent styling.
 *
 * @package WyvernCSS
 * @subpackage Styles
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Styles;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Style Memory Class
 *
 * Tracks and learns from user's styling choices to:
 * - Remember favorite colors, fonts, and spacing values
 * - Provide personalized suggestions
 * - Maintain consistent styling across sessions
 */
class Style_Memory {

	/**
	 * User meta key for style preferences.
	 */
	private const META_KEY = 'wyverncss_style_memory';

	/**
	 * Maximum history items to track.
	 */
	private const MAX_HISTORY = 50;

	/**
	 * Maximum custom patterns allowed.
	 */
	private const MAX_CUSTOM_PATTERNS = 50;

	/**
	 * Default style preferences structure.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULT_PREFERENCES = array(
		'favorite_colors'      => array(),
		'favorite_fonts'       => array(),
		'favorite_spacing'     => array(),
		'border_radius_style'  => null,
		'shadow_preference'    => null,
		'animation_preference' => null,
		'style_history'        => array(),
		'usage_counts'         => array(
			'colors' => array(),
			'fonts'  => array(),
		),
		'custom_patterns'      => array(),
	);

	/**
	 * Color name to hex mapping.
	 *
	 * @var array<string, string>
	 */
	private const COLOR_MAP = array(
		'red'    => '#ff0000',
		'blue'   => '#0000ff',
		'green'  => '#00ff00',
		'yellow' => '#ffff00',
		'orange' => '#ffa500',
		'purple' => '#800080',
		'pink'   => '#ffc0cb',
		'black'  => '#000000',
		'white'  => '#ffffff',
		'gray'   => '#808080',
		'grey'   => '#808080',
		'navy'   => '#000080',
		'teal'   => '#008080',
		'coral'  => '#ff7f50',
		'salmon' => '#fa8072',
		'gold'   => '#ffd700',
		'silver' => '#c0c0c0',
	);

	/**
	 * Get user's style memory.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed> Style memory data.
	 */
	public function get_memory( int $user_id ): array {
		$memory = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $memory ) || empty( $memory ) ) {
			return self::DEFAULT_PREFERENCES;
		}

		return array_merge( self::DEFAULT_PREFERENCES, $memory );
	}

	/**
	 * Save user's style memory.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $memory  Style memory data.
	 * @return bool True on success.
	 */
	public function save_memory( int $user_id, array $memory ): bool {
		return (bool) update_user_meta( $user_id, self::META_KEY, $memory );
	}

	/**
	 * Learn from a styling request.
	 *
	 * Analyzes the prompt and generated CSS to learn user preferences.
	 *
	 * @param int                  $user_id User ID.
	 * @param string               $prompt  The styling prompt.
	 * @param array<string, mixed> $css     Generated CSS properties.
	 * @return void
	 */
	public function learn( int $user_id, string $prompt, array $css ): void {
		$memory = $this->get_memory( $user_id );

		// Extract and count colors.
		$colors = $this->extract_colors_from_css( $css );
		foreach ( $colors as $color ) {
			$memory = $this->increment_usage( $memory, 'colors', $color );
		}

		// Extract and count fonts.
		$fonts = $this->extract_fonts_from_css( $css );
		foreach ( $fonts as $font ) {
			$memory = $this->increment_usage( $memory, 'fonts', $font );
		}

		// Learn border-radius preference.
		if ( isset( $css['border-radius'] ) ) {
			$memory['border_radius_style'] = $this->categorize_border_radius( $css['border-radius'] );
		}

		// Learn shadow preference.
		if ( isset( $css['box-shadow'] ) || isset( $css['text-shadow'] ) ) {
			$memory['shadow_preference'] = $this->categorize_shadow( $css['box-shadow'] ?? $css['text-shadow'] ?? '' );
		}

		// Track in history.
		$history_entry = array(
			'prompt'    => substr( $prompt, 0, 100 ),
			'css_keys'  => array_keys( $css ),
			'timestamp' => time(),
		);

		$memory['style_history'] = array_slice(
			array_merge( array( $history_entry ), $memory['style_history'] ),
			0,
			self::MAX_HISTORY
		);

		// Update favorite colors based on usage (merge with manually added favorites).
		$usage_colors = $this->get_top_items( $memory['usage_counts']['colors'], 5 );
		if ( ! empty( $usage_colors ) ) {
			// Merge usage-based colors with existing favorites, keeping unique values.
			$memory['favorite_colors'] = array_values(
				array_unique(
					array_merge( $usage_colors, $memory['favorite_colors'] )
				)
			);
			$memory['favorite_colors'] = array_slice( $memory['favorite_colors'], 0, 10 );
		}

		$usage_fonts = $this->get_top_items( $memory['usage_counts']['fonts'], 3 );
		if ( ! empty( $usage_fonts ) ) {
			// Merge usage-based fonts with existing favorites, keeping unique values.
			$memory['favorite_fonts'] = array_values(
				array_unique(
					array_merge( $usage_fonts, $memory['favorite_fonts'] )
				)
			);
			$memory['favorite_fonts'] = array_slice( $memory['favorite_fonts'], 0, 5 );
		}

		$this->save_memory( $user_id, $memory );
	}

	/**
	 * Get suggestions based on user's style memory.
	 *
	 * @param int    $user_id User ID.
	 * @param string $prompt  Current prompt.
	 * @return array<string, mixed> Suggested styles.
	 */
	public function get_suggestions( int $user_id, string $prompt ): array {
		$memory      = $this->get_memory( $user_id );
		$suggestions = array();

		// Suggest favorite colors if prompt mentions color but not specific one.
		$prompt_lower = strtolower( $prompt );
		if ( $this->mentions_color( $prompt_lower ) && ! $this->has_specific_color( $prompt_lower ) ) {
			if ( ! empty( $memory['favorite_colors'] ) ) {
				$suggestions['suggested_color'] = $memory['favorite_colors'][0];
			}
		}

		// Suggest border-radius if prompt mentions rounded/corners.
		if ( strpos( $prompt_lower, 'round' ) !== false || strpos( $prompt_lower, 'corner' ) !== false ) {
			if ( ! empty( $memory['border_radius_style'] ) ) {
				$suggestions['suggested_border_radius'] = $memory['border_radius_style'];
			}
		}

		// Suggest shadow style if prompt mentions shadow.
		if ( strpos( $prompt_lower, 'shadow' ) !== false ) {
			if ( ! empty( $memory['shadow_preference'] ) ) {
				$suggestions['suggested_shadow'] = $memory['shadow_preference'];
			}
		}

		// Include top preferences for reference.
		$suggestions['preferences'] = array(
			'colors' => $memory['favorite_colors'],
			'fonts'  => $memory['favorite_fonts'],
		);

		return $suggestions;
	}

	/**
	 * Get favorite colors.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, string> Favorite colors.
	 */
	public function get_favorite_colors( int $user_id ): array {
		$memory = $this->get_memory( $user_id );
		return $memory['favorite_colors'];
	}

	/**
	 * Get favorite fonts.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, string> Favorite fonts.
	 */
	public function get_favorite_fonts( int $user_id ): array {
		$memory = $this->get_memory( $user_id );
		return $memory['favorite_fonts'];
	}

	/**
	 * Get style history.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Maximum items to return.
	 * @return array<int, array<string, mixed>> Style history.
	 */
	public function get_history( int $user_id, int $limit = 10 ): array {
		$memory = $this->get_memory( $user_id );
		return array_slice( $memory['style_history'], 0, $limit );
	}

	/**
	 * Add a color to favorites.
	 *
	 * @param int    $user_id User ID.
	 * @param string $color   Color value.
	 * @return bool True on success.
	 */
	public function add_favorite_color( int $user_id, string $color ): bool {
		$memory = $this->get_memory( $user_id );

		$color = strtolower( trim( $color ) );

		// Normalize color name to hex if needed.
		if ( isset( self::COLOR_MAP[ $color ] ) ) {
			$color = self::COLOR_MAP[ $color ];
		}

		if ( ! in_array( $color, $memory['favorite_colors'], true ) ) {
			array_unshift( $memory['favorite_colors'], $color );
			$memory['favorite_colors'] = array_slice( $memory['favorite_colors'], 0, 10 );
		}

		return $this->save_memory( $user_id, $memory );
	}

	/**
	 * Remove a color from favorites.
	 *
	 * @param int    $user_id User ID.
	 * @param string $color   Color value.
	 * @return bool True on success.
	 */
	public function remove_favorite_color( int $user_id, string $color ): bool {
		$memory = $this->get_memory( $user_id );

		$memory['favorite_colors'] = array_values(
			array_filter(
				$memory['favorite_colors'],
				fn( $c ) => strtolower( $c ) !== strtolower( $color )
			)
		);

		return $this->save_memory( $user_id, $memory );
	}

	/**
	 * Clear all style memory.
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success.
	 */
	public function clear_memory( int $user_id ): bool {
		return delete_user_meta( $user_id, self::META_KEY );
	}

	/**
	 * Save a custom pattern (premium feature).
	 *
	 * @param int                  $user_id User ID.
	 * @param string               $name    Pattern name.
	 * @param array<string, mixed> $css     CSS properties.
	 * @param string               $prompt  Original prompt that generated this pattern.
	 * @return array{success: bool, id?: string, error?: string} Result with pattern ID.
	 */
	public function save_custom_pattern( int $user_id, string $name, array $css, string $prompt = '' ): array {
		$memory = $this->get_memory( $user_id );

		// Check limit.
		if ( count( $memory['custom_patterns'] ) >= self::MAX_CUSTOM_PATTERNS ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %d: maximum number of patterns allowed */
					__( 'Maximum of %d custom patterns reached. Delete some patterns first.', 'wyvern-ai-styling' ),
					self::MAX_CUSTOM_PATTERNS
				),
			);
		}

		// Generate unique ID.
		$pattern_id = 'cp_' . wp_generate_uuid4();

		// Sanitize name.
		$name = sanitize_text_field( $name );
		if ( empty( $name ) ) {
			$name = __( 'Untitled Pattern', 'wyvern-ai-styling' );
		}

		// Build pattern entry.
		$pattern = array(
			'id'         => $pattern_id,
			'name'       => $name,
			'css'        => $this->sanitize_css_array( $css ),
			'prompt'     => substr( sanitize_text_field( $prompt ), 0, 200 ),
			'created_at' => time(),
		);

		// Add to beginning of list.
		array_unshift( $memory['custom_patterns'], $pattern );

		$saved = $this->save_memory( $user_id, $memory );

		if ( $saved ) {
			return array(
				'success' => true,
				'id'      => $pattern_id,
			);
		}

		return array(
			'success' => false,
			'error'   => __( 'Failed to save pattern.', 'wyvern-ai-styling' ),
		);
	}

	/**
	 * Get all custom patterns for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>> List of custom patterns.
	 */
	public function get_custom_patterns( int $user_id ): array {
		$memory = $this->get_memory( $user_id );
		return $memory['custom_patterns'] ?? array();
	}

	/**
	 * Get a single custom pattern by ID.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $pattern_id Pattern ID.
	 * @return array<string, mixed>|null Pattern data or null if not found.
	 */
	public function get_custom_pattern( int $user_id, string $pattern_id ): ?array {
		$patterns = $this->get_custom_patterns( $user_id );

		foreach ( $patterns as $pattern ) {
			if ( $pattern['id'] === $pattern_id ) {
				return $pattern;
			}
		}

		return null;
	}

	/**
	 * Delete a custom pattern.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $pattern_id Pattern ID to delete.
	 * @return bool True if deleted successfully.
	 */
	public function delete_custom_pattern( int $user_id, string $pattern_id ): bool {
		$memory = $this->get_memory( $user_id );

		$original_count = count( $memory['custom_patterns'] );

		$memory['custom_patterns'] = array_values(
			array_filter(
				$memory['custom_patterns'],
				fn( $p ) => $p['id'] !== $pattern_id
			)
		);

		// Only save if something was deleted.
		if ( count( $memory['custom_patterns'] ) < $original_count ) {
			return $this->save_memory( $user_id, $memory );
		}

		return false;
	}

	/**
	 * Update a custom pattern name.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $pattern_id Pattern ID.
	 * @param string $new_name   New pattern name.
	 * @return bool True if updated successfully.
	 */
	public function update_custom_pattern_name( int $user_id, string $pattern_id, string $new_name ): bool {
		$memory = $this->get_memory( $user_id );

		foreach ( $memory['custom_patterns'] as &$pattern ) {
			if ( $pattern['id'] === $pattern_id ) {
				$pattern['name'] = sanitize_text_field( $new_name );
				return $this->save_memory( $user_id, $memory );
			}
		}

		return false;
	}

	/**
	 * Sanitize CSS array values.
	 *
	 * @param array<string, mixed> $css CSS properties.
	 * @return array<string, string> Sanitized CSS.
	 */
	private function sanitize_css_array( array $css ): array {
		$sanitized = array();

		foreach ( $css as $property => $value ) {
			// Only allow valid CSS property names.
			$property = preg_replace( '/[^a-z\-]/', '', strtolower( $property ) );
			if ( empty( $property ) || ! is_string( $value ) ) {
				continue;
			}

			// Sanitize value - remove anything that looks like code injection.
			$value = wp_kses( $value, array() );
			$value = preg_replace( '/[<>{}]/', '', $value );

			if ( ! empty( $value ) ) {
				$sanitized[ $property ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Export style memory.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed> Exportable style memory.
	 */
	public function export_memory( int $user_id ): array {
		$memory = $this->get_memory( $user_id );

		return array(
			'version'         => '1.0',
			'exported_at'     => gmdate( 'c' ),
			'favorite_colors' => $memory['favorite_colors'],
			'favorite_fonts'  => $memory['favorite_fonts'],
			'border_radius'   => $memory['border_radius_style'],
			'shadow'          => $memory['shadow_preference'],
			'history_count'   => count( $memory['style_history'] ),
		);
	}

	/**
	 * Import style memory.
	 *
	 * @param int                  $user_id     User ID.
	 * @param array<string, mixed> $import_data Data to import.
	 * @return bool True on success.
	 */
	public function import_memory( int $user_id, array $import_data ): bool {
		$memory = $this->get_memory( $user_id );

		if ( isset( $import_data['favorite_colors'] ) && is_array( $import_data['favorite_colors'] ) ) {
			$memory['favorite_colors'] = array_slice( $import_data['favorite_colors'], 0, 10 );
		}

		if ( isset( $import_data['favorite_fonts'] ) && is_array( $import_data['favorite_fonts'] ) ) {
			$memory['favorite_fonts'] = array_slice( $import_data['favorite_fonts'], 0, 5 );
		}

		if ( isset( $import_data['border_radius'] ) ) {
			$memory['border_radius_style'] = sanitize_text_field( $import_data['border_radius'] );
		}

		if ( isset( $import_data['shadow'] ) ) {
			$memory['shadow_preference'] = sanitize_text_field( $import_data['shadow'] );
		}

		return $this->save_memory( $user_id, $memory );
	}

	/**
	 * Extract colors from CSS properties.
	 *
	 * @param array<string, mixed> $css CSS properties.
	 * @return array<int, string> Extracted colors.
	 */
	private function extract_colors_from_css( array $css ): array {
		$colors      = array();
		$color_props = array( 'color', 'background-color', 'border-color', 'background' );

		foreach ( $color_props as $prop ) {
			if ( isset( $css[ $prop ] ) && is_string( $css[ $prop ] ) ) {
				$extracted = $this->parse_color_value( $css[ $prop ] );
				if ( null !== $extracted ) {
					$colors[] = $extracted;
				}
			}
		}

		return array_unique( $colors );
	}

	/**
	 * Parse a color value from CSS.
	 *
	 * @param string $value CSS value.
	 * @return string|null Normalized color or null.
	 */
	private function parse_color_value( string $value ): ?string {
		$value = trim( strtolower( $value ) );

		// Check for hex color.
		if ( preg_match( '/^#[0-9a-f]{3,8}$/i', $value ) ) {
			return $value;
		}

		// Check for rgb/rgba.
		if ( preg_match( '/^rgba?\([^)]+\)$/i', $value ) ) {
			return $value;
		}

		// Check for named color.
		if ( isset( self::COLOR_MAP[ $value ] ) ) {
			return self::COLOR_MAP[ $value ];
		}

		return null;
	}

	/**
	 * Extract fonts from CSS properties.
	 *
	 * @param array<string, mixed> $css CSS properties.
	 * @return array<int, string> Extracted fonts.
	 */
	private function extract_fonts_from_css( array $css ): array {
		$fonts = array();

		if ( isset( $css['font-family'] ) && is_string( $css['font-family'] ) ) {
			// Split by comma and get first font.
			$font_list = explode( ',', $css['font-family'] );
			$first     = trim( $font_list[0], " '\"" );

			if ( ! empty( $first ) ) {
				$fonts[] = $first;
			}
		}

		return $fonts;
	}

	/**
	 * Categorize border-radius value.
	 *
	 * @param string $value Border-radius value.
	 * @return string Category (none, small, medium, large, pill).
	 */
	private function categorize_border_radius( string $value ): string {
		$value = strtolower( trim( $value ) );

		if ( '0' === $value || 'none' === $value ) {
			return 'none';
		}

		// Try to parse pixel value.
		if ( preg_match( '/^(\d+)px$/', $value, $matches ) ) {
			$px = (int) $matches[1];

			if ( $px <= 4 ) {
				return 'small';
			}

			if ( $px <= 12 ) {
				return 'medium';
			}

			if ( $px <= 24 ) {
				return 'large';
			}

			return 'pill';
		}

		// 50% or 9999px = pill.
		if ( strpos( $value, '50%' ) !== false || strpos( $value, '9999' ) !== false ) {
			return 'pill';
		}

		return 'medium';
	}

	/**
	 * Categorize shadow value.
	 *
	 * @param string $value Shadow value.
	 * @return string Category (none, subtle, medium, heavy).
	 */
	private function categorize_shadow( string $value ): string {
		$value = strtolower( trim( $value ) );

		if ( empty( $value ) || 'none' === $value ) {
			return 'none';
		}

		// Count blur radius size - handle both "0" and "0px" formats.
		// Pattern matches: offset-x offset-y blur-radius, e.g., "0 2px 4px" or "0px 2px 4px".
		if ( preg_match( '/(\d+)(?:px)?\s+(\d+)(?:px)?\s+(\d+)(?:px)?/', $value, $matches ) ) {
			$blur = (int) $matches[3];

			if ( $blur <= 4 ) {
				return 'subtle';
			}

			if ( $blur <= 15 ) {
				return 'medium';
			}

			return 'heavy';
		}

		return 'medium';
	}

	/**
	 * Increment usage counter for an item.
	 *
	 * @param array<string, mixed> $memory Memory data.
	 * @param string               $type   Item type (colors, fonts).
	 * @param string               $item   Item value.
	 * @return array<string, mixed> Updated memory.
	 */
	private function increment_usage( array $memory, string $type, string $item ): array {
		if ( ! isset( $memory['usage_counts'][ $type ] ) ) {
			$memory['usage_counts'][ $type ] = array();
		}

		// Only lowercase colors for normalization; fonts preserve original case.
		if ( 'colors' === $type ) {
			$item = strtolower( $item );
		}

		if ( ! isset( $memory['usage_counts'][ $type ][ $item ] ) ) {
			$memory['usage_counts'][ $type ][ $item ] = 0;
		}

		++$memory['usage_counts'][ $type ][ $item ];

		return $memory;
	}

	/**
	 * Get top items by usage count.
	 *
	 * @param array<string, int> $counts Usage counts.
	 * @param int                $limit  Max items.
	 * @return array<int, string> Top items.
	 */
	private function get_top_items( array $counts, int $limit ): array {
		arsort( $counts );
		return array_slice( array_keys( $counts ), 0, $limit );
	}

	/**
	 * Check if prompt mentions color.
	 *
	 * @param string $prompt Lowercase prompt.
	 * @return bool True if color is mentioned.
	 */
	private function mentions_color( string $prompt ): bool {
		$color_words = array( 'color', 'colour', 'colored', 'coloured', 'colorful', 'colourful' );

		foreach ( $color_words as $word ) {
			if ( strpos( $prompt, $word ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if prompt has a specific color.
	 *
	 * @param string $prompt Lowercase prompt.
	 * @return bool True if specific color is mentioned.
	 */
	private function has_specific_color( string $prompt ): bool {
		// Check for hex codes.
		if ( preg_match( '/#[0-9a-f]{3,8}/i', $prompt ) ) {
			return true;
		}

		// Check for named colors.
		foreach ( array_keys( self::COLOR_MAP ) as $color ) {
			if ( strpos( $prompt, $color ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
