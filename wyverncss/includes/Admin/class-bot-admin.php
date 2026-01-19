<?php
/**
 * Bot Administration Page
 *
 * Handles the WordPress admin interface for multi-bot management.
 * Registers admin menu, enqueues React app, and manages bot admin pages.
 *
 * @package    WyvernCSS
 * @subpackage WyvernCSS/includes/Admin
 * @since      1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Bot Admin Class
 *
 * Manages the bot management admin interface.
 */
class Bot_Admin {

	/**
	 * The page slug for the bot management page.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'wyvernpress-bots';

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the bot management admin menu.
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		$page_title = __( 'Bot Management', 'wyvern-ai-styling' );
		$menu_title = __( 'Bots', 'wyvern-ai-styling' );

		add_submenu_page(
			'wyvern-ai-styling',
			$page_title,
			$menu_title,
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts and styles for the bot management page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only load on our bot management page.
		if ( 'wyverncss_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		// Enqueue WordPress components and dependencies.
		wp_enqueue_style( 'wp-components' );

		// Enqueue our React app.
		$asset_file = WYVERNCSS_PLUGIN_DIR . 'assets/build/bot-admin.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			// Development fallback - assets not built yet.
			wp_die(
				esc_html__(
					'Bot management assets not found. Please run npm run build.',
					'wyvern-ai-styling'
				)
			);
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wyvernpress-bot-admin',
			WYVERNCSS_PLUGIN_URL . 'assets/build/bot-admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'wyvernpress-bot-admin',
			WYVERNCSS_PLUGIN_URL . 'assets/build/bot-admin.css',
			array( 'wp-components' ),
			$asset['version']
		);

		// Pass configuration to JavaScript.
		wp_localize_script(
			'wyvernpress-bot-admin',
			'wyvernPressBotAdmin',
			array(
				'restUrl'         => esc_url_raw( rest_url( 'wyverncss/v1/bots' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'licenseKey'      => get_option( 'wyverncss_license_key', '' ),
				'cloudServiceUrl' => get_option( 'wyverncss_cloud_service_url', '' ),
				'availableModels' => $this->get_available_models(),
			)
		);
	}

	/**
	 * Get list of available AI models.
	 *
	 * @return array<string, string> Model slug => Display name.
	 */
	private function get_available_models(): array {
		/**
		 * Filter the list of available AI models.
		 *
		 * @param array<string, string> $models Model slug => Display name.
		 */
		return apply_filters(
			'wyverncss_available_models',
			array(
				'claude-3-haiku'    => __( 'Claude 3 Haiku (Fast, Cost-Effective)', 'wyvern-ai-styling' ),
				'claude-3-sonnet'   => __( 'Claude 3 Sonnet (Balanced)', 'wyvern-ai-styling' ),
				'claude-3-opus'     => __( 'Claude 3 Opus (Most Capable)', 'wyvern-ai-styling' ),
				'claude-3.5-sonnet' => __( 'Claude 3.5 Sonnet (Latest)', 'wyvern-ai-styling' ),
				'gpt-4o'            => __( 'GPT-4o (OpenAI)', 'wyvern-ai-styling' ),
				'gpt-4-turbo'       => __( 'GPT-4 Turbo (OpenAI)', 'wyvern-ai-styling' ),
				'gpt-3.5-turbo'     => __( 'GPT-3.5 Turbo (OpenAI)', 'wyvern-ai-styling' ),
			)
		);
	}

	/**
	 * Render the bot management page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'You do not have sufficient permissions to access this page.',
					'wyvern-ai-styling'
				)
			);
		}

		// Check if license key is configured.
		$license_key = get_option( 'wyverncss_license_key', '' );

		if ( empty( $license_key ) ) {
			$this->render_no_license_notice();
			return;
		}

		// Render the React app container.
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			// Check for cloud service connection.
			$cloud_service_url = get_option( 'wyverncss_cloud_service_url', '' );
			if ( empty( $cloud_service_url ) ) {
				?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: %s: Settings page URL */
							esc_html__(
								'Cloud service not configured. Multi-bot management requires the cloud service. Please %1$sconfigure your cloud service URL%2$s.',
								'wyvern-ai-styling'
							),
							'<a href="' . esc_url( admin_url( 'admin.php?page=wyvernpress-settings' ) ) . '">',
							'</a>'
						);
						?>
					</p>
				</div>
				<?php
			}
			?>

			<!-- React app will mount here -->
			<div id="wyvernpress-bot-admin-root"></div>
		</div>
		<?php
	}

	/**
	 * Render notice when license key is not configured.
	 *
	 * @return void
	 */
	private function render_no_license_notice(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'License Key Required', 'wyvern-ai-styling' ); ?></strong>
				</p>
				<p>
					<?php
					printf(
						/* translators: %s: Settings page URL */
						esc_html__(
							'Please %1$sconfigure your license key%2$s to use multi-bot management.',
							'wyvern-ai-styling'
						),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wyvernpress-settings' ) ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}
}
