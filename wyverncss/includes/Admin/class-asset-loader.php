<?php
/**
 * Asset Loader for WyvernCSS Gutenberg Integration
 *
 * Handles enqueuing of JavaScript and CSS assets for the block editor.
 *
 * @package WyvernCSS
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\Config\Tier_Config;

/**
 * AssetLoader class
 *
 * Registers and enqueues editor scripts and styles.
 */
class AssetLoader {
	/**
	 * Script handle for the editor
	 */
	private const EDITOR_SCRIPT_HANDLE = 'wyvernpress-editor';

	/**
	 * Style handle for the editor
	 */
	private const EDITOR_STYLE_HANDLE = 'wyvernpress-editor-style';

	/**
	 * Initialize the asset loader
	 *
	 * @return void */
	public function init(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Enqueue block editor assets
	 *
	 * @return void */
	public function enqueue_editor_assets(): void {
		// Only load on post edit screens.
		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}

		$this->enqueue_editor_script();
		$this->enqueue_editor_styles();
	}

	/**
	 * Enqueue editor JavaScript
	 *
	 * @return void */
	private function enqueue_editor_script(): void {
		$asset_file = $this->get_asset_file();

		wp_enqueue_script(
			self::EDITOR_SCRIPT_HANDLE,
			WYVERNCSS_PLUGIN_URL . 'assets/build/index.js',
			$asset_file['dependencies'] ?? array(),
			$asset_file['version'] ?? WYVERNCSS_VERSION,
			true
		);

		// Localize script with API settings.
		wp_localize_script(
			self::EDITOR_SCRIPT_HANDLE,
			'wyverncssApiSettings',
			array(
				'root'  => esc_url_raw( rest_url() ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);

		// Localize script with pricing configuration.
		wp_localize_script(
			self::EDITOR_SCRIPT_HANDLE,
			'wyvernpressPricing',
			$this->get_pricing_config()
		);

		// Add inline script for development mode detection.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			wp_add_inline_script(
				self::EDITOR_SCRIPT_HANDLE,
				'window.WYVERNCSS_DEBUG = true;',
				'before'
			);
		}

		// Set script translations.
		wp_set_script_translations(
			self::EDITOR_SCRIPT_HANDLE,
			'wyvern-ai-styling',
			WYVERNCSS_PLUGIN_DIR . 'languages'
		);
	}

	/**
	 * Enqueue editor styles
	 *
	 * @return void */
	private function enqueue_editor_styles(): void {
		$asset_file = $this->get_asset_file();

		// Check if CSS file exists (it might not in development mode).
		$css_file = WYVERNCSS_PLUGIN_DIR . 'assets/build/index.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				self::EDITOR_STYLE_HANDLE,
				WYVERNCSS_PLUGIN_URL . 'assets/build/index.css',
				array( 'wp-components' ),
				$asset_file['version'] ?? WYVERNCSS_VERSION
			);
		}
	}

	/**
	 * Get asset file with dependencies and version
	 *
	 * @return array{dependencies?: array<string>, version?: string}.
	 */
	private function get_asset_file(): array {
		$asset_file_path = WYVERNCSS_PLUGIN_DIR . 'assets/build/index.asset.php';

		if ( file_exists( $asset_file_path ) ) {
			$asset_file = require $asset_file_path;
			if ( is_array( $asset_file ) ) {
				return $asset_file;
			}
		}

		// Fallback to default dependencies if asset file not found.
		return array(
			'dependencies' => array(
				'wp-element',
				'wp-plugins',
				'wp-edit-post',
				'wp-editor',
				'wp-i18n',
				'wp-components',
				'wp-data',
				'wp-notices',
				'wp-icons',
			),
			'version'      => WYVERNCSS_VERSION,
		);
	}

	/**
	 * Check if assets are built
	 *
	 * @return bool */
	public function are_assets_built(): bool {
		return file_exists( WYVERNCSS_PLUGIN_DIR . 'assets/build/index.js' );
	}

	/**
	 * Get admin notice for missing assets
	 *
	 * @return void */
	public function show_build_notice(): void {
		if ( ! $this->are_assets_built() && current_user_can( 'manage_options' ) ) {
			add_action(
				'admin_notices',
				function () {
					?>
					<div class="notice notice-warning">
						<p>
							<strong><?php esc_html_e( 'WyvernCSS:', 'wyvern-ai-styling' ); ?></strong>
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: %s: build command */
									__( 'Editor assets need to be built. Run %s in the plugin directory.', 'wyvern-ai-styling' ),
									'<code>cd assets && npm install && npm run build</code>'
								)
							);
							?>
						</p>
					</div>
					<?php
				}
			);
		}
	}

	/**
	 * Get pricing configuration for frontend
	 *
	 * Loads tier configuration from tiers.json and returns it
	 * in a format suitable for wp_localize_script.
	 *
	 * @return array<string, mixed> Pricing configuration.
	 */
	private function get_pricing_config(): array {
		$tier_config = Tier_Config::get_instance();
		return $tier_config->get_raw_config();
	}
}
