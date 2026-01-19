<?php
/**
 * Settings Page Controller
 *
 * Manages the WyvernCSS settings page in WordPress admin.
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
 * Settings Page Controller
 *
 * Manages the WyvernCSS settings page in WordPress admin.
 * Handles API key encryption, model selection, and settings persistence.
 *
 * @package WyvernCSS\Admin
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Option name for API key storage
	 */
	private const OPTION_API_KEY = 'wyverncss_api_key';

	/**
	 * Option name for model selection
	 */
	private const OPTION_MODEL = 'wyverncss_model';

	/**
	 * Option name for default bot
	 */
	private const OPTION_DEFAULT_BOT = 'wyverncss_default_bot';

	/**
	 * Option name for general settings
	 */
	private const OPTION_SETTINGS = 'wyverncss_settings';

	/**
	 * Settings page slug
	 */
	private const PAGE_SLUG = 'wyvernpress-settings';

	/**
	 * Settings group name
	 */
	private const SETTINGS_GROUP = 'wyverncss_settings_group';

	/**
	 * Nonce action for settings form
	 */
	private const NONCE_ACTION = 'wyverncss_save_settings';

	/**
	 * Notice manager instance
	 *
	 * @var NoticeManager
	 */
	private NoticeManager $notice_manager;

	/**
	 * Constructor
	 *
	 * @param NoticeManager $notice_manager Notice manager instance.
	 */
	public function __construct( NoticeManager $notice_manager ) {
		$this->notice_manager = $notice_manager;
	}

	/**
	 * Initialize the settings page
	 *
	 * @return void */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wyverncss_save_settings', array( $this, 'handle_settings_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the admin menu
	 *
	 * Creates a top-level menu for WyvernCSS settings.
	 * This is the main admin interface for the CSS styling plugin.
	 *
	 * @return void */
	public function register_menu(): void {
		add_menu_page(
			__( 'WyvernCSS', 'wyvern-ai-styling' ),
			__( 'WyvernCSS', 'wyvern-ai-styling' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-art',
			30
		);
	}

	/**
	 * Register settings with WordPress
	 *
	 * @return void */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_API_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_MODEL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_model' ),
				'default'           => 'claude-3-5-sonnet-20241022',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_DEFAULT_BOT,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize and encrypt API key
	 *
	 * @param string $value API key value.
	 * @return string Encrypted API key.
	 */
	public function sanitize_api_key( string $value ): string {
		$clean_value = sanitize_text_field( $value );

		if ( empty( $clean_value ) ) {
			return '';
		}

		// Encrypt the API key using WordPress password hashing.
		return wp_hash_password( $clean_value );
	}

	/**
	 * Sanitize model selection
	 *
	 * @param string $value Model identifier.
	 * @return string Sanitized model.
	 */
	public function sanitize_model( string $value ): string {
		$allowed_models = $this->get_allowed_models();
		$clean_value    = sanitize_text_field( $value );

		if ( ! array_key_exists( $clean_value, $allowed_models ) ) {
			return 'claude-3-5-sonnet-20241022';
		}

		return $clean_value;
	}

	/**
	 * Sanitize settings array
	 *
	 * @param array<string, mixed> $value Settings array.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public function sanitize_settings( array $value ): array {
		$sanitized = array();

		if ( isset( $value['enable_analytics'] ) ) {
			$sanitized['enable_analytics'] = (bool) $value['enable_analytics'];
		}

		if ( isset( $value['retention_days'] ) ) {
			$sanitized['retention_days'] = absint( $value['retention_days'] );
		}

		return $sanitized;
	}

	/**
	 * Handle settings form submission
	 *
	 * @return void */
	public function handle_settings_save(): void {
		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			$this->notice_manager->add_error( __( 'Security check failed.', 'wyvern-ai-styling' ) );
			$this->redirect_to_settings();
			return;
		}

		// Verify capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->notice_manager->add_error( __( 'You do not have permission to access this page.', 'wyvern-ai-styling' ) );
			$this->redirect_to_settings();
			return;
		}

		// Save API key.
		if ( isset( $_POST['wyverncss_api_key'] ) ) {
			$api_key = sanitize_text_field( wp_unslash( $_POST['wyverncss_api_key'] ) );
			update_option( self::OPTION_API_KEY, $api_key );
		}

		// Save model.
		if ( isset( $_POST['wyverncss_model'] ) ) {
			$model = $this->sanitize_model( sanitize_text_field( wp_unslash( $_POST['wyverncss_model'] ) ) );
			update_option( self::OPTION_MODEL, $model );
		}

		// Save default bot.
		if ( isset( $_POST['wyverncss_default_bot'] ) ) {
			$default_bot = sanitize_text_field( wp_unslash( $_POST['wyverncss_default_bot'] ) );
			update_option( self::OPTION_DEFAULT_BOT, $default_bot );
		}

		// Save general settings.
		$settings = array(
			'enable_analytics' => isset( $_POST['wyverncss_enable_analytics'] ),
			'retention_days'   => isset( $_POST['wyverncss_retention_days'] ) ? absint( $_POST['wyverncss_retention_days'] ) : 90,
		);
		update_option( self::OPTION_SETTINGS, $settings );

		$this->notice_manager->add_success( __( 'Settings saved successfully.', 'wyvern-ai-styling' ) );
		$this->redirect_to_settings();
	}

	/**
	 * Redirect to settings page
	 *
	 * @return void */
	private function redirect_to_settings(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return void */
	public function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wyvernpress-admin',
			WYVERNCSS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WYVERNCSS_VERSION
		);
	}

	/**
	 * Render the settings page
	 *
	 * @return void */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wyvern-ai-styling' ) );
		}

		$api_key     = $this->get_api_key();
		$model       = $this->get_model();
		$default_bot = $this->get_default_bot();
		$settings    = $this->get_settings();
		$models      = $this->get_allowed_models();

		require_once WYVERNCSS_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Get stored API key (encrypted)
	 *
	 * @return string Encrypted API key.
	 */
	public function get_api_key(): string {
		return get_option( self::OPTION_API_KEY, '' );
	}

	/**
	 * Get selected model
	 *
	 * @return string Model identifier.
	 */
	public function get_model(): string {
		return get_option( self::OPTION_MODEL, 'claude-3-5-sonnet-20241022' );
	}

	/**
	 * Get default bot slug
	 *
	 * @return string Default bot slug (empty if not set).
	 */
	public function get_default_bot(): string {
		return get_option( self::OPTION_DEFAULT_BOT, '' );
	}

	/**
	 * Get settings array
	 *
	 * @return array<string, mixed> Settings array.
	 */
	public function get_settings(): array {
		return get_option(
			self::OPTION_SETTINGS,
			array(
				'enable_analytics' => true,
				'retention_days'   => 90,
			)
		);
	}

	/**
	 * Get allowed models
	 *
	 * @return array<string, string> Model ID => Model name.
	 */
	private function get_allowed_models(): array {
		return array(
			'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
			'claude-3-opus-20240229'     => 'Claude 3 Opus',
			'claude-3-sonnet-20240229'   => 'Claude 3 Sonnet',
			'claude-3-haiku-20240307'    => 'Claude 3 Haiku',
		);
	}

	/**
	 * Get settings page URL
	 *
	 * @return string Settings page URL.
	 */
	public static function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}
}
