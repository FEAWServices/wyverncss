<?php
/**
 * Style Preset Manager
 *
 * Manages user-defined style presets for quick CSS application.
 * Premium feature - allows users to save and reapply style collections.
 *
 * @package WyvernCSS
 * @subpackage Presets
 * @since 1.1.0
 */

declare(strict_types=1);

namespace WyvernCSS\Presets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_Error;

/**
 * Class StylePreset
 *
 * Handles CRUD operations for user style presets.
 * Presets are stored in WordPress user meta for easy access.
 *
 * @since 1.1.0
 */
class StylePreset {

	/**
	 * User meta key for storing presets.
	 *
	 * @var string
	 */
	private const META_KEY = 'wyverncss_style_presets';

	/**
	 * Maximum number of presets per user (premium).
	 *
	 * @var int
	 */
	private const MAX_PRESETS = 50;

	/**
	 * Maximum preset name length.
	 *
	 * @var int
	 */
	private const MAX_NAME_LENGTH = 100;

	/**
	 * Maximum CSS properties per preset.
	 *
	 * @var int
	 */
	private const MAX_CSS_PROPERTIES = 50;

	/**
	 * User ID for the current instance.
	 *
	 * @var int
	 */
	private int $user_id;

	/**
	 * Constructor.
	 *
	 * @param int|null $user_id Optional user ID. Defaults to current user.
	 */
	public function __construct( ?int $user_id = null ) {
		$this->user_id = $user_id ?? get_current_user_id();
	}

	/**
	 * Get all presets for the current user.
	 *
	 * @return array<string, array<string, mixed>> Array of presets keyed by ID.
	 */
	public function get_all(): array {
		if ( 0 === $this->user_id ) {
			return array();
		}

		$presets = get_user_meta( $this->user_id, self::META_KEY, true );

		if ( ! is_array( $presets ) ) {
			return array();
		}

		return $presets;
	}

	/**
	 * Get a single preset by ID.
	 *
	 * @param string $preset_id The preset ID.
	 * @return array<string, mixed>|null The preset data or null if not found.
	 */
	public function get( string $preset_id ): ?array {
		$presets = $this->get_all();

		return $presets[ $preset_id ] ?? null;
	}

	/**
	 * Create a new preset.
	 *
	 * @param string               $name   Preset name.
	 * @param array<string, mixed> $css    CSS properties.
	 * @param string               $category Optional category for organization.
	 * @return string|WP_Error Preset ID on success, WP_Error on failure.
	 */
	public function create( string $name, array $css, string $category = 'custom' ) {
		if ( 0 === $this->user_id ) {
			return new WP_Error(
				'not_logged_in',
				__( 'You must be logged in to create presets.', 'wyverncss' )
			);
		}

		// Validate name.
		$name = sanitize_text_field( $name );
		if ( empty( $name ) ) {
			return new WP_Error(
				'invalid_name',
				__( 'Preset name cannot be empty.', 'wyverncss' )
			);
		}

		if ( strlen( $name ) > self::MAX_NAME_LENGTH ) {
			return new WP_Error(
				'name_too_long',
				sprintf(
					/* translators: %d: maximum character count */
					__( 'Preset name cannot exceed %d characters.', 'wyverncss' ),
					self::MAX_NAME_LENGTH
				)
			);
		}

		// Validate CSS.
		$validated_css = $this->validate_css( $css );
		if ( is_wp_error( $validated_css ) ) {
			return $validated_css;
		}

		// Check preset limit.
		$presets = $this->get_all();
		if ( count( $presets ) >= self::MAX_PRESETS ) {
			return new WP_Error(
				'preset_limit_reached',
				sprintf(
					/* translators: %d: maximum preset count */
					__( 'You have reached the maximum of %d presets.', 'wyverncss' ),
					self::MAX_PRESETS
				)
			);
		}

		// Generate unique ID.
		$preset_id = $this->generate_preset_id();

		// Create preset data.
		$preset = array(
			'id'         => $preset_id,
			'name'       => $name,
			'css'        => $validated_css,
			'category'   => sanitize_key( $category ),
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		// Save.
		$presets[ $preset_id ] = $preset;
		$result                = update_user_meta( $this->user_id, self::META_KEY, $presets );

		if ( false === $result ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to save preset.', 'wyverncss' )
			);
		}

		return $preset_id;
	}

	/**
	 * Update an existing preset.
	 *
	 * @param string               $preset_id Preset ID.
	 * @param array<string, mixed> $data      Data to update (name, css, category).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update( string $preset_id, array $data ) {
		if ( 0 === $this->user_id ) {
			return new WP_Error(
				'not_logged_in',
				__( 'You must be logged in to update presets.', 'wyverncss' )
			);
		}

		$presets = $this->get_all();

		if ( ! isset( $presets[ $preset_id ] ) ) {
			return new WP_Error(
				'preset_not_found',
				__( 'Preset not found.', 'wyverncss' )
			);
		}

		$preset = $presets[ $preset_id ];

		// Update name if provided.
		if ( isset( $data['name'] ) ) {
			$name = sanitize_text_field( $data['name'] );
			if ( empty( $name ) ) {
				return new WP_Error(
					'invalid_name',
					__( 'Preset name cannot be empty.', 'wyverncss' )
				);
			}
			if ( strlen( $name ) > self::MAX_NAME_LENGTH ) {
				return new WP_Error(
					'name_too_long',
					sprintf(
						/* translators: %d: maximum character count */
						__( 'Preset name cannot exceed %d characters.', 'wyverncss' ),
						self::MAX_NAME_LENGTH
					)
				);
			}
			$preset['name'] = $name;
		}

		// Update CSS if provided.
		if ( isset( $data['css'] ) ) {
			$validated_css = $this->validate_css( $data['css'] );
			if ( is_wp_error( $validated_css ) ) {
				return $validated_css;
			}
			$preset['css'] = $validated_css;
		}

		// Update category if provided.
		if ( isset( $data['category'] ) ) {
			$preset['category'] = sanitize_key( $data['category'] );
		}

		$preset['updated_at'] = current_time( 'mysql' );

		// Save.
		$presets[ $preset_id ] = $preset;
		$result                = update_user_meta( $this->user_id, self::META_KEY, $presets );

		if ( false === $result ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to update preset.', 'wyverncss' )
			);
		}

		return true;
	}

	/**
	 * Delete a preset.
	 *
	 * @param string $preset_id Preset ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete( string $preset_id ) {
		if ( 0 === $this->user_id ) {
			return new WP_Error(
				'not_logged_in',
				__( 'You must be logged in to delete presets.', 'wyverncss' )
			);
		}

		$presets = $this->get_all();

		if ( ! isset( $presets[ $preset_id ] ) ) {
			return new WP_Error(
				'preset_not_found',
				__( 'Preset not found.', 'wyverncss' )
			);
		}

		unset( $presets[ $preset_id ] );
		$result = update_user_meta( $this->user_id, self::META_KEY, $presets );

		if ( false === $result ) {
			return new WP_Error(
				'delete_failed',
				__( 'Failed to delete preset.', 'wyverncss' )
			);
		}

		return true;
	}

	/**
	 * Get presets by category.
	 *
	 * @param string $category Category to filter by.
	 * @return array<string, array<string, mixed>> Filtered presets.
	 */
	public function get_by_category( string $category ): array {
		$presets  = $this->get_all();
		$category = sanitize_key( $category );

		return array_filter(
			$presets,
			function ( array $preset ) use ( $category ): bool {
				return ( $preset['category'] ?? '' ) === $category;
			}
		);
	}

	/**
	 * Get available categories.
	 *
	 * @return array<int, string> List of categories.
	 */
	public function get_categories(): array {
		$presets    = $this->get_all();
		$categories = array();

		foreach ( $presets as $preset ) {
			$category = $preset['category'] ?? 'custom';
			if ( ! in_array( $category, $categories, true ) ) {
				$categories[] = $category;
			}
		}

		sort( $categories );

		return $categories;
	}

	/**
	 * Duplicate a preset.
	 *
	 * @param string $preset_id Preset ID to duplicate.
	 * @return string|WP_Error New preset ID on success, WP_Error on failure.
	 */
	public function duplicate( string $preset_id ) {
		$preset = $this->get( $preset_id );

		if ( null === $preset ) {
			return new WP_Error(
				'preset_not_found',
				__( 'Preset not found.', 'wyverncss' )
			);
		}

		/* translators: %s: original preset name */
		$new_name = sprintf( __( '%s (Copy)', 'wyverncss' ), $preset['name'] );

		return $this->create(
			$new_name,
			$preset['css'],
			$preset['category'] ?? 'custom'
		);
	}

	/**
	 * Export presets to JSON.
	 *
	 * @return string JSON string of all presets.
	 */
	public function export(): string {
		$presets = $this->get_all();
		$json    = wp_json_encode( $presets, JSON_PRETTY_PRINT );

		return false !== $json ? $json : '{}';
	}

	/**
	 * Import presets from JSON.
	 *
	 * @param string $json    JSON string of presets.
	 * @param bool   $replace Whether to replace existing presets.
	 * @return array{imported: int, skipped: int, errors: array<int, string>}|WP_Error Import result.
	 */
	public function import( string $json, bool $replace = false ) {
		$data = json_decode( $json, true );

		if ( null === $data || ! is_array( $data ) ) {
			return new WP_Error(
				'invalid_json',
				__( 'Invalid JSON data.', 'wyverncss' )
			);
		}

		$result = array(
			'imported' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		if ( $replace ) {
			// Clear existing presets.
			delete_user_meta( $this->user_id, self::META_KEY );
		}

		foreach ( $data as $preset ) {
			if ( ! is_array( $preset ) ||
				! isset( $preset['name'] ) ||
				! isset( $preset['css'] ) ) {
				++$result['skipped'];
				continue;
			}

			$create_result = $this->create(
				$preset['name'],
				$preset['css'],
				$preset['category'] ?? 'custom'
			);

			if ( is_wp_error( $create_result ) ) {
				$result['errors'][] = sprintf(
					/* translators: 1: preset name, 2: error message */
					__( 'Failed to import "%1$s": %2$s', 'wyverncss' ),
					$preset['name'],
					$create_result->get_error_message()
				);
				++$result['skipped'];
			} else {
				++$result['imported'];
			}
		}

		return $result;
	}

	/**
	 * Get preset count.
	 *
	 * @return int Number of presets.
	 */
	public function get_count(): int {
		return count( $this->get_all() );
	}

	/**
	 * Validate CSS properties.
	 *
	 * @param array<string, mixed> $css CSS properties to validate.
	 * @return array<string, string>|WP_Error Validated CSS or error.
	 */
	private function validate_css( array $css ) {
		if ( empty( $css ) ) {
			return new WP_Error(
				'empty_css',
				__( 'CSS properties cannot be empty.', 'wyverncss' )
			);
		}

		if ( count( $css ) > self::MAX_CSS_PROPERTIES ) {
			return new WP_Error(
				'too_many_properties',
				sprintf(
					/* translators: %d: maximum property count */
					__( 'CSS properties cannot exceed %d.', 'wyverncss' ),
					self::MAX_CSS_PROPERTIES
				)
			);
		}

		$validated          = array();
		$allowed_properties = $this->get_allowed_css_properties();

		foreach ( $css as $property => $value ) {
			$property = sanitize_key( str_replace( '_', '-', $property ) );
			$value    = sanitize_text_field( (string) $value );

			// Check if property is allowed.
			if ( ! in_array( $property, $allowed_properties, true ) ) {
				continue; // Skip disallowed properties silently.
			}

			// Basic validation for common dangerous values.
			if ( preg_match( '/expression|javascript|url\s*\(/i', $value ) ) {
				continue; // Skip potentially dangerous values.
			}

			$validated[ $property ] = $value;
		}

		if ( empty( $validated ) ) {
			return new WP_Error(
				'no_valid_properties',
				__( 'No valid CSS properties found.', 'wyverncss' )
			);
		}

		return $validated;
	}

	/**
	 * Get list of allowed CSS properties.
	 *
	 * @return array<int, string> Allowed property names.
	 */
	private function get_allowed_css_properties(): array {
		return array(
			// Colors.
			'color',
			'background-color',
			'border-color',
			'outline-color',
			// Typography.
			'font-family',
			'font-size',
			'font-weight',
			'font-style',
			'line-height',
			'letter-spacing',
			'text-align',
			'text-decoration',
			'text-transform',
			// Spacing.
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
			// Border.
			'border',
			'border-width',
			'border-style',
			'border-radius',
			'border-top-left-radius',
			'border-top-right-radius',
			'border-bottom-left-radius',
			'border-bottom-right-radius',
			// Layout.
			'display',
			'width',
			'height',
			'min-width',
			'min-height',
			'max-width',
			'max-height',
			// Flexbox.
			'flex',
			'flex-direction',
			'flex-wrap',
			'justify-content',
			'align-items',
			'align-content',
			'gap',
			// Grid.
			'grid-template-columns',
			'grid-template-rows',
			'grid-gap',
			// Position.
			'position',
			'top',
			'right',
			'bottom',
			'left',
			'z-index',
			// Effects.
			'opacity',
			'box-shadow',
			'text-shadow',
			'filter',
			'backdrop-filter',
			// Transitions.
			'transition',
			'transition-duration',
			'transition-timing-function',
			// Transform.
			'transform',
			// Overflow.
			'overflow',
			'overflow-x',
			'overflow-y',
			// Background.
			'background',
			'background-image',
			'background-size',
			'background-position',
			'background-repeat',
		);
	}

	/**
	 * Generate a unique preset ID.
	 *
	 * @return string Unique ID.
	 */
	private function generate_preset_id(): string {
		return 'preset_' . wp_generate_uuid4();
	}
}
