<?php
/**
 * Settings Controller
 *
 * REST endpoints for managing plugin settings.
 *
 * @package WyvernCSS
 * @subpackage API
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\API;
use WyvernCSS\API\REST\Controller_Helpers;
use WyvernCSS\Settings\Settings_Service;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Settings Controller Class
 *
 * Handles:
 * - GET /wyverncss/v1/settings - Retrieve settings
 * - POST /wyverncss/v1/settings - Update settings
 * - POST /wyverncss/v1/settings/export - Export settings
 *
 * Settings include:
 * - API key for cloud services
 * - Subscription tier
 * - AI model preference
 * - Cache settings
 * - Feature flags
 *
 * @since 1.0.0
 */
class Settings_Controller extends RESTController {

	use Controller_Helpers;

	/**
	 * Settings service instance.
	 *
	 * @var Settings_Service
	 */
	private Settings_Service $settings_service;

	/**
	 * Settings option key.
	 *
	 * @var string
	 */
	private const SETTINGS_KEY = 'wyverncss_settings';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Settings_Service $settings_service Settings service instance.
	 */
	public function __construct( Settings_Service $settings_service ) {
		$this->settings_service = $settings_service;
	}

	/**
	 * Default settings.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULT_SETTINGS = array(
		'api_key'          => '',
		'tier'             => 'free',
		'ai_model'         => 'gpt-4',
		'cache_enabled'    => true,
		'cache_duration'   => 86400, // 24 hours in seconds.
		'min_confidence'   => 70,
		'enable_analytics' => true,
		'enable_telemetry' => false,
	);

	/**
	 * Valid subscription tiers.
	 *
	 * @var array<int, string>
	 */
	private const VALID_TIERS = array( 'free', 'starter', 'pro' );

	/**
	 * Valid AI models.
	 *
	 * @var array<int, string>
	 */
	private const VALID_MODELS = array( 'gpt-4', 'gpt-3.5-turbo', 'claude-3' );

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 * @return void */
	public function register_routes(): void {
		// GET endpoint.
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		// POST endpoint.
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_endpoint_args(),
				),
			)
		);

		// Export endpoint.
		register_rest_route(
			$this->namespace,
			'/settings/export',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);
	}

	/**
	 * Get plugin settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Response with settings.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$user_id  = get_current_user_id();
		$settings = $this->get_all_settings();

		// Check if user has an API key stored (via Settings_Service).
		$has_api_key = $this->settings_service->has_valid_api_key( $user_id );

		// Add has_api_key flag to response.
		$settings['has_api_key'] = $has_api_key;

		// Mask API key for security (only show last 4 characters).
		if ( ! empty( $settings['api_key'] ) ) {
			$settings['api_key'] = $this->mask_api_key( $settings['api_key'] );
		}

		return $this->success_response( $settings );
	}

	/**
	 * Update plugin settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function update_settings( WP_REST_Request $request ) {
		$current_settings = $this->get_all_settings();
		$updated_settings = array();

		// Process each setting parameter.
		foreach ( self::DEFAULT_SETTINGS as $key => $default_value ) {
			$param = $request->get_param( $key );

			// If parameter not provided, keep current value.
			if ( null === $param ) {
				$updated_settings[ $key ] = $current_settings[ $key ];
				continue;
			}

			// Validate and sanitize based on setting type.
			$validated_value = $this->validate_setting( $key, $param );

			if ( is_wp_error( $validated_value ) ) {
				return $validated_value;
			}

			$updated_settings[ $key ] = $validated_value;
		}

		// Update settings.
		update_option( self::SETTINGS_KEY, $updated_settings, false );

		// Also update user meta for tier if changed.
		if ( isset( $updated_settings['tier'] ) && $updated_settings['tier'] !== $current_settings['tier'] ) {
			update_user_meta( get_current_user_id(), 'wyverncss_tier', $updated_settings['tier'] );
		}

		// Mask API key in response.
		$response_settings = $updated_settings;
		if ( ! empty( $response_settings['api_key'] ) ) {
			$response_settings['api_key'] = $this->mask_api_key( $response_settings['api_key'] );
		}

		return $this->success_response(
			array(
				'settings' => $response_settings,
				'message'  => esc_html__( 'Settings updated successfully.', 'wyvern-ai-styling' ),
			)
		);
	}

	/**
	 * Get all settings with defaults.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Settings.
	 */
	private function get_all_settings(): array {
		$settings = get_option( self::SETTINGS_KEY, array() );
		return wp_parse_args( $settings, self::DEFAULT_SETTINGS );
	}

	/**
	 * Validate a setting value.
	 *
	 * @since 1.0.0
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @return mixed|WP_Error Validated value or error.
	 */
	private function validate_setting( string $key, $value ) {
		switch ( $key ) {
			case 'api_key':
				$sanitized = $this->sanitize_text( $value );
				if ( strlen( $sanitized ) > 0 && strlen( $sanitized ) < 10 ) {
					return $this->error_response(
						'invalid_api_key',
						esc_html__( 'API key must be at least 10 characters.', 'wyvern-ai-styling' ),
						400
					);
				}
				return $sanitized;

			case 'tier':
				$tier = $this->sanitize_text( $value );
				if ( ! in_array( $tier, self::VALID_TIERS, true ) ) {
					return $this->error_response(
						'invalid_tier',
						sprintf(
							/* translators: %s: valid tier options */
							esc_html__( 'Invalid tier. Must be one of: %s', 'wyvern-ai-styling' ),
							implode( ', ', self::VALID_TIERS )
						),
						400
					);
				}
				return $tier;

			case 'ai_model':
				$model = $this->sanitize_text( $value );
				if ( ! in_array( $model, self::VALID_MODELS, true ) ) {
					return $this->error_response(
						'invalid_model',
						sprintf(
							/* translators: %s: valid model options */
							esc_html__( 'Invalid AI model. Must be one of: %s', 'wyvern-ai-styling' ),
							implode( ', ', self::VALID_MODELS )
						),
						400
					);
				}
				return $model;

			case 'cache_duration':
				$duration = (int) $value;
				if ( $duration < 0 || $duration > 604800 ) { // Max 7 days.
					return $this->error_response(
						'invalid_cache_duration',
						esc_html__( 'Cache duration must be between 0 and 604800 seconds (7 days).', 'wyvern-ai-styling' ),
						400
					);
				}
				return $duration;

			case 'min_confidence':
				$confidence = (int) $value;
				if ( $confidence < 0 || $confidence > 100 ) {
					return $this->error_response(
						'invalid_confidence',
						esc_html__( 'Minimum confidence must be between 0 and 100.', 'wyvern-ai-styling' ),
						400
					);
				}
				return $confidence;

			case 'cache_enabled':
			case 'enable_analytics':
			case 'enable_telemetry':
				return (bool) $value;

			default:
				return $value;
		}
	}

	/**
	 * Check admin permission.
	 *
	 * Requires manage_options capability.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function check_admin_permission( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to manage settings.', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get endpoint arguments.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Endpoint arguments.
	 */
	private function get_endpoint_args(): array {
		return array(
			'api_key'          => array(
				'description'       => __( 'API key for cloud services.', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_text' ),
			),
			'tier'             => array(
				'description' => __( 'Subscription tier.', 'wyvern-ai-styling' ),
				'type'        => 'string',
				'enum'        => self::VALID_TIERS,
			),
			'ai_model'         => array(
				'description' => __( 'AI model preference.', 'wyvern-ai-styling' ),
				'type'        => 'string',
				'enum'        => self::VALID_MODELS,
			),
			'cache_enabled'    => array(
				'description' => __( 'Enable pattern cache.', 'wyvern-ai-styling' ),
				'type'        => 'boolean',
			),
			'cache_duration'   => array(
				'description' => __( 'Cache duration in seconds.', 'wyvern-ai-styling' ),
				'type'        => 'integer',
				'minimum'     => 0,
				'maximum'     => 604800,
			),
			'min_confidence'   => array(
				'description' => __( 'Minimum confidence threshold for pattern matching.', 'wyvern-ai-styling' ),
				'type'        => 'integer',
				'minimum'     => 0,
				'maximum'     => 100,
			),
			'enable_analytics' => array(
				'description' => __( 'Enable usage analytics.', 'wyvern-ai-styling' ),
				'type'        => 'boolean',
			),
			'enable_telemetry' => array(
				'description' => __( 'Enable telemetry data collection.', 'wyvern-ai-styling' ),
				'type'        => 'boolean',
			),
		);
	}

	/**
	 * Get schema for settings endpoint.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Schema definition.
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'settings',
			'type'       => 'object',
			'properties' => array(
				'api_key'          => array(
					'description' => __( 'API key for cloud services (masked).', 'wyvern-ai-styling' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'tier'             => array(
					'description' => __( 'Subscription tier.', 'wyvern-ai-styling' ),
					'type'        => 'string',
					'enum'        => self::VALID_TIERS,
					'context'     => array( 'view', 'edit' ),
				),
				'ai_model'         => array(
					'description' => __( 'AI model preference.', 'wyvern-ai-styling' ),
					'type'        => 'string',
					'enum'        => self::VALID_MODELS,
					'context'     => array( 'view', 'edit' ),
				),
				'cache_enabled'    => array(
					'description' => __( 'Whether pattern cache is enabled.', 'wyvern-ai-styling' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
				),
				'cache_duration'   => array(
					'description' => __( 'Cache duration in seconds.', 'wyvern-ai-styling' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'min_confidence'   => array(
					'description' => __( 'Minimum confidence threshold.', 'wyvern-ai-styling' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'enable_analytics' => array(
					'description' => __( 'Whether analytics are enabled.', 'wyvern-ai-styling' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
				),
				'enable_telemetry' => array(
					'description' => __( 'Whether telemetry is enabled.', 'wyvern-ai-styling' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Export settings for backup/migration.
	 *
	 * Excludes sensitive data like API keys (they are masked).
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Response with export data.
	 * @phpstan-ignore-next-line WP_REST_Request does not support generics
	 */
	public function export_settings( WP_REST_Request $request ): WP_REST_Response {
		$user_id  = get_current_user_id();
		$settings = $this->get_all_settings();

		// Get user-specific settings from Settings_Service.
		$user_settings = $this->settings_service->get_all_settings( $user_id );

		// Mask API keys (already masked by get_all_settings).
		// API keys from Settings_Service are already shown as [ENCRYPTED].

		$export_data = array(
			'version'       => defined( 'WYVERNCSS_VERSION' ) ? WYVERNCSS_VERSION : '2.0.0',
			'exported_at'   => gmdate( 'c' ),
			'settings'      => $settings,
			'user_settings' => $user_settings,
		);

		return $this->success_response( $export_data );
	}
}
