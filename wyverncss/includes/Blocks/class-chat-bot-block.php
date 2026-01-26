<?php
/**
 * Chat Bot Block Handler
 *
 * @package WyvernCSS
 */

declare(strict_types=1);

namespace WyvernCSS\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Chat Bot Block
 *
 * Handles registration and rendering of the wyverncss/chat-bot block.
 * This block allows embedding AI-powered chat bots in pages and posts.
 *
 * @since 1.0.0
 */
class Chat_Bot_Block {

	/**
	 * Flag to track if assets have been enqueued
	 *
	 * @var bool
	 */
	private bool $assets_enqueued = false;

	/**
	 * Constructor - registers the block
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ), 10, 2 );
	}

	/**
	 * Register the block
	 *
	 * @return void
	 */
	public function register_block(): void {
		// Register block from metadata.
		register_block_type(
			WYVERNCSS_PLUGIN_DIR . 'assets/src/blocks/chat-bot/block.json',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Register custom block category
	 *
	 * @param array<int, array<string, string>> $categories Block categories.
	 * @param \WP_Post|\WP_Block_Editor_Context $block_editor_context Block editor context (unused but required by filter).
	 * @return array<int, array<string, string>> Modified categories.
	 */
	public function register_block_category( array $categories, $block_editor_context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Check if category already exists.
		foreach ( $categories as $category ) {
			if ( 'wyverncss' === $category['slug'] ) {
				return $categories;
			}
		}

		// Add custom category at the beginning.
		// Note: icon field is omitted as it's optional and we don't need it.
		return array_merge(
			array(
				array(
					'slug'  => 'wyverncss',
					'title' => __( 'WyvernCSS', 'wyverncss' ),
				),
			),
			$categories
		);
	}

	/**
	 * Render the block
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string HTML output.
	 */
	public function render( array $attributes ): string {
		// Sanitize and validate attributes.
		$bot_id   = $this->sanitize_bot_id( $attributes['botId'] ?? '' );
		$position = $this->validate_position( $attributes['position'] ?? 'floating' );
		$theme    = $this->validate_theme( $attributes['theme'] ?? 'light' );
		$width    = $this->validate_dimension( $attributes['width'] ?? 400, 300, 800 );
		$height   = $this->validate_dimension( $attributes['height'] ?? 500, 300, 800 );

		// If no bot ID, try to get default.
		if ( empty( $bot_id ) ) {
			$bot_id = $this->get_default_bot_id();
		}

		// Enqueue assets.
		$this->enqueue_assets();

		// Render container div with data attributes.
		return sprintf(
			'<div class="wp-block-wyverncss-chat-bot wyverncss-bot-widget" data-bot-id="%s" data-position="%s" data-theme="%s" data-width="%d" data-height="%d"></div>',
			esc_attr( $bot_id ),
			esc_attr( $position ),
			esc_attr( $theme ),
			(int) $width,
			(int) $height
		);
	}

	/**
	 * Sanitize bot ID
	 *
	 * @param mixed $bot_id Bot ID to sanitize.
	 * @return string Sanitized bot ID.
	 */
	private function sanitize_bot_id( $bot_id ): string {
		if ( ! is_string( $bot_id ) ) {
			return '';
		}

		// Remove any HTML tags and special characters.
		$bot_id = sanitize_text_field( $bot_id );

		// Validate UUID format (optional but recommended).
		// Allow alphanumeric, hyphens, and underscores.
		$bot_id = preg_replace( '/[^a-zA-Z0-9\-_]/', '', $bot_id );

		return $bot_id ?? '';
	}

	/**
	 * Validate position attribute
	 *
	 * @param mixed $position Position value.
	 * @return string Valid position ('inline' or 'floating').
	 */
	private function validate_position( $position ): string {
		if ( ! is_string( $position ) ) {
			return 'floating';
		}

		$valid_positions = array( 'inline', 'floating' );

		return in_array( $position, $valid_positions, true ) ? $position : 'floating';
	}

	/**
	 * Validate theme attribute
	 *
	 * @param mixed $theme Theme value.
	 * @return string Valid theme ('light' or 'dark').
	 */
	private function validate_theme( $theme ): string {
		if ( ! is_string( $theme ) ) {
			return 'light';
		}

		$valid_themes = array( 'light', 'dark' );

		return in_array( $theme, $valid_themes, true ) ? $theme : 'light';
	}

	/**
	 * Validate dimension (width/height)
	 *
	 * @param mixed $value Dimension value.
	 * @param int   $min Minimum allowed value.
	 * @param int   $max Maximum allowed value.
	 * @return int Validated dimension.
	 */
	private function validate_dimension( $value, int $min, int $max ): int {
		if ( ! is_numeric( $value ) ) {
			return $min;
		}

		$value = (int) $value;

		// Clamp to min/max bounds.
		return max( $min, min( $max, $value ) );
	}

	/**
	 * Get the default bot ID from options
	 *
	 * @return string Default bot ID or empty string if not set.
	 */
	private function get_default_bot_id(): string {
		$default_bot_id = get_option( 'wyverncss_default_bot_id', '' );

		if ( ! is_string( $default_bot_id ) ) {
			return '';
		}

		return $default_bot_id;
	}

	/**
	 * Enqueue JavaScript and CSS assets
	 *
	 * @return void
	 */
	private function enqueue_assets(): void {
		// Only enqueue once per page load.
		if ( $this->assets_enqueued ) {
			return;
		}

		wp_enqueue_script(
			'wyverncss-chat-widget',
			WYVERNCSS_PLUGIN_URL . 'assets/build/chat-widget.js',
			array( 'wp-element', 'wp-i18n' ),
			WYVERNCSS_VERSION,
			true
		);

		wp_enqueue_style(
			'wyverncss-chat-widget',
			WYVERNCSS_PLUGIN_URL . 'assets/build/chat-widget.css',
			array(),
			WYVERNCSS_VERSION
		);

		// Pass configuration to JavaScript.
		wp_localize_script(
			'wyverncss-chat-widget',
			'wyvernPressBotConfig',
			array(
				'apiUrl'  => rest_url( 'wyverncss/v1/chat' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'siteUrl' => site_url(),
			)
		);

		$this->assets_enqueued = true;
	}
}
