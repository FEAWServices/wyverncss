<?php
/**
 * Main Plugin Class
 *
 * The core plugin orchestrator that coordinates all plugin functionality.
 *
 * @package WyvernCSS
 * @subpackage Core
 */

declare(strict_types=1);

namespace WyvernCSS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main Plugin Class
 *
 * This class is responsible for:
 * - Loading plugin dependencies
 * - Defining hooks for admin and public-facing functionality
 * - Coordinating plugin components
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * The plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * The plugin text domain.
	 *
	 * @var string
	 */
	private string $text_domain;

	/**
	 * Array to store loaded components.
	 *
	 * @var array<string, object>
	 */
	private array $components = array();

	/**
	 * Initialize the plugin.
	 *
	 * Sets the plugin version and text domain.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->version     = defined( 'WYVERNCSS_VERSION' ) ? WYVERNCSS_VERSION : '1.0.0';
		$this->text_domain = 'wyverncss';

		$this->load_dependencies();
		$this->define_hooks();
	}

	/**
	 * Load required dependencies.
	 *
	 * @since 1.0.0
	 * @return void */
	private function load_dependencies(): void {
		// Dependencies will be loaded here as we build more components.
		// For now, we just ensure the core is initialized.
	}

	/**
	 * Define WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void */
	private function define_hooks(): void {
		// Note: load_plugin_textdomain() is not needed since WordPress 4.6.
		// WordPress automatically loads translations for plugins hosted on WordPress.org.

		// Initialize plugin on WordPress init.
		add_action( 'init', array( $this, 'init' ) );

		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Add plugin action links.
		add_filter( 'plugin_action_links_' . WYVERNCSS_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Initialize plugin functionality.
	 *
	 * RESCOPED FOR LAUNCH: Focus on CSS styling only.
	 * Removed: Chat, Bots, RAG, Observability, Public Chat Widget
	 *
	 * @since 1.0.0
	 * @return void */
	public function init(): void {
		// Initialize AssetLoader for Gutenberg integration (CORE - CSS Styling UI).
		$asset_loader = new \WyvernCSS\Admin\AssetLoader();
		$asset_loader->init();
		$asset_loader->show_build_notice();
		$this->add_component( 'asset_loader', $asset_loader );

		// Initialize Admin components (CORE - Settings).
		$notice_manager = new \WyvernCSS\Admin\NoticeManager();
		$notice_manager->init();
		$this->add_component( 'notice_manager', $notice_manager );

		$settings_page = new \WyvernCSS\Admin\SettingsPage( $notice_manager );
		$settings_page->init();
		$this->add_component( 'settings_page', $settings_page );

		// Initialize Settings Service (CORE - API key storage).
		$settings_service = new \WyvernCSS\Settings\Settings_Service();
		$this->add_component( 'settings_service', $settings_service );

		// Initialize REST API Controllers (CORE - Style endpoints).
		$this->register_rest_controllers();

		// =============================================================
		// DISABLED FOR LAUNCH - Out of scope features
		// =============================================================
		// The following features have been disabled to focus on the core
		// CSS styling use case. See docs/business/competitor-analysis.md
		// for the rationale behind this decision.
		//
		// - Analytics components (CostCalculator, UsageTracker, etc.)
		// - MCP Circuit Breaker, Client, Transport (external services)
		// - Conversation Service (chat feature)
		// - Admin Console Page (chat UI)
		// - Bot Admin Page (multi-bot management)
		// - Bot Shortcode (public chat widget)
		// - Chat Bot Block (Gutenberg chat block)
		// =============================================================
	}

	/**
	 * Register REST API controllers
	 *
	 * RESCOPED FOR LAUNCH: Only core CSS styling endpoints.
	 *
	 * @since 1.0.0
	 * @return void */
	private function register_rest_controllers(): void {
		add_action(
			'rest_api_init',
			function () {
				// Get required services.
				$settings_service = $this->get_component( 'settings_service' );

				if ( ! $settings_service instanceof \WyvernCSS\Settings\Settings_Service ) {
					return;
				}

				// =============================================================
				// CORE ENDPOINTS - CSS Styling
				// =============================================================

				// Register Style Controller for natural language styling (CORE).
				$style_controller = new \WyvernCSS\API\StyleController();
				$style_controller->register_routes();

				// Register Settings Controller (CORE - preferences and usage).
				$settings_controller = new \WyvernCSS\API\REST\Settings_Controller(
					$settings_service
				);
				$settings_controller->register_routes();

				// Register Usage Controller for tracking requests and quotas (CORE).
				$usage_controller = new \WyvernCSS\API\UsageController();
				$usage_controller->register_routes();

				// Register Style Memory Controller for user preferences (PREMIUM).
				$style_memory_controller = new \WyvernCSS\API\REST\Style_Memory_Controller();
				$style_memory_controller->register_routes();

				// Register Bulk Style Controller for batch operations (PREMIUM).
				$bulk_style_controller = new \WyvernCSS\API\REST\Bulk_Style_Controller();
				$bulk_style_controller->register_routes();

				// Register CSS Debug Controller for analyzing and fixing CSS (PREMIUM).
				$css_debug_controller = new \WyvernCSS\API\REST\CSS_Debug_Controller();
				$css_debug_controller->register_routes();

				// Register Accessibility Controller for WCAG compliance checking (PREMIUM).
				$accessibility_controller = new \WyvernCSS\API\REST\Accessibility_Controller();
				$accessibility_controller->register_routes();

				// Register Style Extractor Controller for "match this site" feature (PREMIUM).
				$style_extractor_controller = new \WyvernCSS\API\REST\Style_Extractor_Controller();
				$style_extractor_controller->register_routes();

				// Register Admin AI Controller for natural language admin operations (PREMIUM).
				$admin_ai_controller = new \WyvernCSS\Admin\Admin_AI_Controller();
				$admin_ai_controller->register_routes();

				// Register License Controller for license management and validation (CORE).
				$license_controller = new \WyvernCSS\API\REST\License_Controller();
				$license_controller->register_routes();

				// =============================================================
				// DISABLED FOR LAUNCH - Out of scope endpoints
				// =============================================================
				// - Chat Controller (chat/message endpoints)
				// - Health Controller (MCP service health)
				// - MCP Status Controller (MCP tools status)
				// - Observability Controller (analytics dashboard)
				// - Debug Controller (debugging tools)
				// - Bot Proxy Controller (multi-bot management)
				// - Bot Frontend API (public chat widget)
				// =============================================================
			}
		);
	}


	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		// Only load on WyvernCSS admin pages.
		if ( strpos( $hook_suffix, 'wyverncss' ) === false ) {
			return;
		}

		// Admin assets will be enqueued here as we build them.
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 1.0.0
	 * @param array<int, string> $links Existing plugin action links.
	 * @return array<int, string> Modified plugin action links.
	 */
	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wyverncss' ) ),
			esc_html__( 'Settings', 'wyverncss' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Run the plugin.
	 *
	 * Execute all plugin hooks.
	 *
	 * @since 1.0.0
	 * @return void */
	public function run(): void {
		// All hooks are already registered in __construct via define_hooks().
		// This method exists for future extensibility.
	}

	/**
	 * Get the plugin version.
	 *
	 * @since 1.0.0
	 * @return string The plugin version.
	 */
	public function get_version(): string {
		return $this->version;
	}

	/**
	 * Get the plugin text domain.
	 *
	 * @since 1.0.0
	 * @return string The plugin text domain.
	 */
	public function get_text_domain(): string {
		return $this->text_domain;
	}

	/**
	 * Add a component to the plugin.
	 *
	 * @since 1.0.0
	 * @param string $key The component identifier.
	 * @param object $component The component instance.
	 * @return void */
	public function add_component( string $key, object $component ): void {
		$this->components[ $key ] = $component;
	}

	/**
	 * Get a component by key.
	 *
	 * @since 1.0.0
	 * @param string $key The component identifier.
	 * @return object|null The component instance or null if not found.
	 */
	public function get_component( string $key ): ?object {
		return $this->components[ $key ] ?? null;
	}
}
