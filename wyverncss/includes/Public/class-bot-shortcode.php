<?php
/**
 * Bot Shortcode Handler
 *
 * @package WyvernCSS
 */

declare(strict_types=1);

namespace WyvernCSS\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Bot Shortcode Handler
 *
 * Handles [wyverncss_bot] shortcode for embedding chat bots.
 * Supports both floating (bottom-right bubble) and inline modes.
 *
 * Usage:
 * [wyverncss_bot id="bot-uuid" position="floating" theme="light"]
 * [wyverncss_bot id="bot-uuid" position="inline"]
 * [wyverncss_bot] - Uses default bot
 */
class Bot_Shortcode {

	/**
	 * Flag to track if assets have been enqueued
	 *
	 * @var bool
	 */
	private bool $assets_enqueued = false;

	/**
	 * Constructor - registers the shortcode
	 */
	public function __construct() {
		add_shortcode( 'wyverncss_bot', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode
	 *
	 * @param array<string, string> $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'       => '',
				'position' => 'floating',
				'theme'    => 'light',
				'width'    => '400',
				'height'   => '500',
			),
			$atts,
			'wyverncss_bot'
		);

		// If no ID, get default bot.
		if ( empty( $atts['id'] ) ) {
			$atts['id'] = $this->get_default_bot_id();
		}

		// Enqueue assets.
		$this->enqueue_assets();

		// Render container div with data attributes.
		return sprintf(
			'<div class="wyverncss-bot-widget" data-bot-id="%s" data-position="%s" data-theme="%s" data-width="%s" data-height="%s"></div>',
			esc_attr( $atts['id'] ),
			esc_attr( $atts['position'] ),
			esc_attr( $atts['theme'] ),
			esc_attr( $atts['width'] ),
			esc_attr( $atts['height'] )
		);
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
			WYVERNCSS_PLUGIN_URL . 'assets/dist/public.js',
			array( 'wp-element', 'wp-i18n' ),
			WYVERNCSS_VERSION,
			true
		);

		wp_enqueue_style(
			'wyverncss-chat-widget',
			WYVERNCSS_PLUGIN_URL . 'assets/dist/public.css',
			array(),
			WYVERNCSS_VERSION
		);

		// Pass configuration to JavaScript.
		wp_localize_script(
			'wyverncss-chat-widget',
			'wyvernPressBotConfig',
			array(
				'apiUrl'  => rest_url( 'wyverncss/v1/public/chat' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'siteUrl' => site_url(),
			)
		);

		$this->assets_enqueued = true;
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
}
