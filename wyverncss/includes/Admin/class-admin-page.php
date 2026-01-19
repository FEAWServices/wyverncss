<?php
/**
 * AI Admin Console Page
 *
 * Registers and renders the AI Admin Console admin page.
 *
 * @package WyvernCSS\Admin
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Admin_Page
 *
 * Handles registration and rendering of the AI Admin Console page.
 */
class Admin_Page {

	/**
	 * Page slug
	 */
	private const PAGE_SLUG = 'wyvern-ai-styling';

	/**
	 * Register admin page hooks
	 *
	 * @return void */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register admin menu page
	 *
	 * @return void */
	public function register_menu_page(): void {
		add_menu_page(
			__( 'WyvernCSS AI', 'wyvern-ai-styling' ),
			__( 'WyvernCSS AI', 'wyvern-ai-styling' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( $this, 'render' ),
			'dashicons-art',
			30
		);
	}

	/**
	 * Render admin page
	 *
	 * @return void */
	public function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wyvern-ai-styling' ) );
		}

		?>
		<div class="wrap">
			<div id="wyvernpress-admin-root"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only load on AI Console page.
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		// Enqueue React app (will be built by webpack).
		$this->enqueue_react_app();
		$this->localize_script_data();
	}

	/**
	 * Enqueue React application
	 *
	 * @return void */
	private function enqueue_react_app(): void {
		$asset_file = WYVERNCSS_PLUGIN_DIR . 'assets/build/ai-console.asset.php';

		// Check if asset file exists.
		if ( ! file_exists( $asset_file ) ) {
			// Asset not built yet, show notice.
			add_action(
				'admin_notices',
				function () {
					?>
					<div class="notice notice-warning">
						<p>
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: %s: build command */
									__( 'WyvernCSS AI Console assets are not built. Please run: <code>%s</code>', 'wyvern-ai-styling' ),
									'cd assets && npm run build'
								)
							);
							?>
						</p>
					</div>
					<?php
				}
			);
			return;
		}

		$asset = include $asset_file;

		// Enqueue JavaScript.
		wp_enqueue_script(
			'wyvernpress-ai-console',
			WYVERNCSS_PLUGIN_URL . 'assets/build/ai-console.js',
			$asset['dependencies'] ?? array( 'wp-element', 'wp-components', 'wp-api-fetch' ),
			$asset['version'] ?? WYVERNCSS_VERSION,
			true
		);

		// Enqueue CSS (if exists).
		$css_file = WYVERNCSS_PLUGIN_DIR . 'assets/build/ai-console.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'wyvernpress-ai-console',
				WYVERNCSS_PLUGIN_URL . 'assets/build/ai-console.css',
				array( 'wp-components' ),
				$asset['version'] ?? WYVERNCSS_VERSION
			);
		}
	}

	/**
	 * Localize script data for React app
	 *
	 * @return void */
	private function localize_script_data(): void {
		$user_id = get_current_user_id();

		wp_localize_script(
			'wyvernpress-ai-console',
			'wyvernPressAI',
			array(
				'apiUrl'        => esc_url_raw( rest_url( 'wyverncss/v1' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'userId'        => $user_id,
				'userCan'       => array(
					'editPosts'     => current_user_can( 'edit_posts' ),
					'publishPosts'  => current_user_can( 'publish_posts' ),
					'manageOptions' => current_user_can( 'manage_options' ),
				),
				'pluginVersion' => WYVERNCSS_VERSION,
				'wpVersion'     => get_bloginfo( 'version' ),
				'siteUrl'       => get_site_url(),
				'adminUrl'      => admin_url(),
			)
		);
	}

	/**
	 * Get page slug
	 *
	 * @return string Page slug.
	 */
	public static function get_page_slug(): string {
		return self::PAGE_SLUG;
	}
}
