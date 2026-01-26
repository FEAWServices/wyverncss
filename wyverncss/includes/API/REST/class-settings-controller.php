<?php
/**
 * Settings REST API Controller
 *
 * Handles REST API endpoints for user settings and preferences.
 *
 * @package WyvernCSS\API\REST
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\API\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\Settings\Settings_Service;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Settings_Controller
 *
 * REST API controller for settings operations.
 */
class Settings_Controller extends WP_REST_Controller {

	/**
	 * API namespace
	 */
	private const NAMESPACE = 'wyverncss/v1';

	/**
	 * Settings service instance
	 *
	 * @var Settings_Service
	 */
	private Settings_Service $settings_service;

	/**
	 * Constructor
	 *
	 * @param Settings_Service $settings_service Settings service instance.
	 */
	public function __construct( Settings_Service $settings_service ) {
		$this->settings_service = $settings_service;
	}

	/**
	 * Register REST API routes
	 *
	 * @return void */
	public function register_routes(): void {
		// GET /settings - Get user settings.
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'check_settings_permission' ),
			)
		);

		// POST /settings/preferences - Save preferences.
		register_rest_route(
			self::NAMESPACE,
			'/settings/preferences',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_preferences' ),
				'permission_callback' => array( $this, 'check_settings_permission' ),
				'args'                => $this->get_preferences_args(),
			)
		);

		// GET /settings/cache-stats - Get cache statistics.
		register_rest_route(
			self::NAMESPACE,
			'/settings/cache-stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cache_stats' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// POST /settings/cache-clear - Clear cache.
		register_rest_route(
			self::NAMESPACE,
			'/settings/cache-clear',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'clear_cache' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// GET /settings/logs - Get log entries.
		register_rest_route(
			self::NAMESPACE,
			'/settings/logs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'limit'  => array(
						'type'              => 'integer',
						'default'           => 100,
						'sanitize_callback' => 'absint',
					),
					'offset' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'level'  => array(
						'type'              => 'string',
						'default'           => 'all',
						'enum'              => array( 'all', 'error', 'warning', 'info', 'debug' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// POST /settings/logs-clear - Clear logs.
		register_rest_route(
			self::NAMESPACE,
			'/settings/logs-clear',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'clear_logs' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// POST /settings/export - Export settings.
		register_rest_route(
			self::NAMESPACE,
			'/settings/export',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'export_settings' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// POST /settings/import - Import settings.
		register_rest_route(
			self::NAMESPACE,
			'/settings/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import_settings' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'settings' => array(
						'required'          => true,
						'type'              => 'object',
						'validate_callback' => array( $this, 'validate_import_settings' ),
					),
				),
			)
		);
	}

	/**
	 * Get user settings
	 *
	 * @param WP_REST_Request $request Request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response or error.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function get_settings( WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		$preferences   = $this->settings_service->get_preferences( $user_id );
		$usage_summary = $this->get_usage_summary( $user_id );
		$has_api_key   = $this->settings_service->has_valid_api_key( $user_id );

		return new WP_REST_Response(
			array(
				'preferences'   => $preferences,
				'usage_summary' => $usage_summary,
				'has_api_key'   => $has_api_key,
			),
			200
		);
	}

	/**
	 * Save user preferences
	 *
	 * @param WP_REST_Request $request Request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response or error.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function save_preferences( WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		$preferences = array();

		// Extract preferences from request.
		if ( null !== $request->get_param( 'default_model' ) ) {
			$preferences['default_model'] = sanitize_text_field( $request->get_param( 'default_model' ) );
		}

		if ( null !== $request->get_param( 'auto_save_conversations' ) ) {
			$preferences['auto_save_conversations'] = (bool) $request->get_param( 'auto_save_conversations' );
		}

		if ( null !== $request->get_param( 'show_tool_executions' ) ) {
			$preferences['show_tool_executions'] = (bool) $request->get_param( 'show_tool_executions' );
		}

		if ( null !== $request->get_param( 'max_context_messages' ) ) {
			$max_context = (int) $request->get_param( 'max_context_messages' );
			if ( $max_context < 5 || $max_context > 50 ) {
				return new WP_Error(
					'invalid_parameter',
					__( 'max_context_messages must be between 5 and 50', 'wyverncss' ),
					array( 'status' => 400 )
				);
			}
			$preferences['max_context_messages'] = $max_context;
		}

		// Merge with existing preferences.
		$existing    = $this->settings_service->get_preferences( $user_id );
		$preferences = array_merge( $existing, $preferences );

		$result = $this->settings_service->save_preferences( $user_id, $preferences );

		if ( ! $result ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to save preferences', 'wyverncss' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'success'     => true,
				'preferences' => $preferences,
			),
			200
		);
	}

	/**
	 * Check if user has permission to manage settings
	 *
	 * @return bool True if user can access settings, false otherwise.
	 */
	public function check_settings_permission(): bool {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Get arguments for preferences endpoint
	 *
	 * @return array<string, array<string, mixed>> Endpoint arguments.
	 */
	private function get_preferences_args(): array {
		return array(
			'default_model'           => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'auto_save_conversations' => array(
				'required' => false,
				'type'     => 'boolean',
			),
			'show_tool_executions'    => array(
				'required' => false,
				'type'     => 'boolean',
			),
			'max_context_messages'    => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Get usage summary for user
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string, int|float> Usage summary data.
	 */
	private function get_usage_summary( int $user_id ): array {
		global $wpdb;

		// Get conversation count.
		$conversations_table = $wpdb->prefix . 'wyverncss_conversations';
		$messages_table      = $wpdb->prefix . 'wyverncss_messages';

		$total_conversations = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE user_id = %d',
				$conversations_table,
				$user_id
			)
		);

		// Get message count.
		$total_messages = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(m.id)
				FROM %i m
				INNER JOIN %i c ON m.conversation_id = c.id
				WHERE c.user_id = %d',
				$messages_table,
				$conversations_table,
				$user_id
			)
		);

		// Get total cost this month.
		$total_cost_month = (float) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(m.cost_usd), 0)
				FROM %i m
				INNER JOIN %i c ON m.conversation_id = c.id
				WHERE c.user_id = %d
				AND m.created_at >= DATE_FORMAT(NOW(), \'%%Y-%%m-01\')',
				$messages_table,
				$conversations_table,
				$user_id
			)
		);

		// Get requests today.
		$requests_today = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(m.id)
				FROM %i m
				INNER JOIN %i c ON m.conversation_id = c.id
				WHERE c.user_id = %d
				AND m.role = \'user\'
				AND DATE(m.created_at) = CURDATE()',
				$messages_table,
				$conversations_table,
				$user_id
			)
		);

		return array(
			'total_conversations' => $total_conversations,
			'total_messages'      => $total_messages,
			'total_cost_month'    => round( $total_cost_month, 2 ),
			'requests_today'      => $requests_today,
		);
	}

	/**
	 * Get cache statistics
	 *
	 * @param WP_REST_Request $request Request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Response with cache statistics.
	 */
	public function get_cache_stats( WP_REST_Request $request ): WP_REST_Response {
		global $wp_object_cache;

		$stats = array(
			'object_cache_enabled' => wp_using_ext_object_cache(),
			'transients_count'     => 0,
			'cache_hits'           => (int) ( $wp_object_cache->cache_hits ?? 0 ),
			'cache_misses'         => (int) ( $wp_object_cache->cache_misses ?? 0 ),
		);

		// Count WyvernCSS transients.
		global $wpdb;
		$stats['transients_count'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_wyverncss_%'"
		);

		// Calculate hit rate.
		$total_requests    = $stats['cache_hits'] + $stats['cache_misses'];
		$stats['hit_rate'] = $total_requests > 0 ? round( ( $stats['cache_hits'] / $total_requests ) * 100, 2 ) : 0;

		return new WP_REST_Response( $stats, 200 );
	}

	/**
	 * Clear cache
	 *
	 * @param WP_REST_Request $request Request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function clear_cache( WP_REST_Request $request ) {
		global $wpdb;

		// Clear WordPress object cache.
		wp_cache_flush();

		// Delete WyvernCSS transients.
		$deleted = $wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wyverncss_%' OR option_name LIKE '_transient_timeout_wyverncss_%'"
		);

		return new WP_REST_Response(
			array(
				'success'       => true,
				'message'       => __( 'Cache cleared successfully', 'wyverncss' ),
				'items_deleted' => $deleted,
			),
			200
		);
	}

	/**
	 * Get log entries
	 *
	 * @param WP_REST_Request $request Request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_logs( WP_REST_Request $request ) {
		$limit  = $request->get_param( 'limit' ) ?? 100;
		$offset = $request->get_param( 'offset' ) ?? 0;
		$level  = $request->get_param( 'level' ) ?? 'all';

		// For now, return WordPress debug log if available.
		$debug_log = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $debug_log ) || ! is_readable( $debug_log ) ) {
			return new WP_REST_Response(
				array(
					'logs'  => array(),
					'total' => 0,
				),
				200
			);
		}

		// Read last N lines from log file.
		$lines = $this->tail_file( $debug_log, $limit + $offset );

		// Filter by level if specified.
		if ( 'all' !== $level ) {
			$lines = array_filter(
				$lines,
				function ( $line ) use ( $level ) {
					return false !== stripos( $line, '[' . $level . ']' );
				}
			);
		}

		// Apply pagination.
		$logs = array_slice( array_values( $lines ), $offset, $limit );

		return new WP_REST_Response(
			array(
				'logs'  => $logs,
				'total' => count( $lines ),
			),
			200
		);
	}

	/**
	 * Clear log entries
	 *
	 * @param WP_REST_Request $request Request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function clear_logs( WP_REST_Request $request ) {
		$debug_log = WP_CONTENT_DIR . '/debug.log';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Direct file check for debug log.
		if ( file_exists( $debug_log ) && is_writable( $debug_log ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct file write for debug log.
			file_put_contents( $debug_log, '' );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Logs cleared successfully', 'wyverncss' ),
				),
				200
			);
		}

		return new WP_Error(
			'clear_failed',
			__( 'Unable to clear logs. File may not exist or is not writable.', 'wyverncss' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * Export settings
	 *
	 * @param WP_REST_Request $request Request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Response with exported settings.
	 */
	public function export_settings( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();

		$export = array(
			'version'     => WYVERNCSS_VERSION,
			'exported_at' => current_time( 'mysql' ),
			'preferences' => $this->settings_service->get_preferences( $user_id ),
			// Note: API keys are NOT exported for security reasons.
		);

		return new WP_REST_Response(
			array(
				'success'  => true,
				'settings' => $export,
			),
			200
		);
	}

	/**
	 * Import settings
	 *
	 * @param WP_REST_Request $request Request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function import_settings( WP_REST_Request $request ) {
		$user_id  = get_current_user_id();
		$settings = $request->get_param( 'settings' );

		if ( ! is_array( $settings ) ) {
			return new WP_Error(
				'invalid_data',
				__( 'Invalid settings data', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		// Import preferences if provided.
		if ( isset( $settings['preferences'] ) && is_array( $settings['preferences'] ) ) {
			$this->settings_service->save_preferences( $user_id, $settings['preferences'] );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Settings imported successfully', 'wyverncss' ),
			),
			200
		);
	}

	/**
	 * Validate import settings
	 *
	 * @param array<string, mixed> $param Settings to validate.
	 * @return bool True if valid.
	 */
	public function validate_import_settings( $param ): bool {
		if ( ! is_array( $param ) ) {
			return false;
		}

		// Check for required version field.
		if ( ! isset( $param['version'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if user has admin permission
	 *
	 * @return bool True if user is admin, false otherwise.
	 */
	public function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Read last N lines from a file
	 *
	 * @param string $file File path.
	 * @param int    $lines Number of lines to read.
	 * @return array<int, string> Array of lines.
	 */
	private function tail_file( string $file, int $lines ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file );
		if ( false === $content ) {
			return array();
		}

		$lines_array = explode( "\n", $content );
		return array_slice( $lines_array, -$lines );
	}
}
