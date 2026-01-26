<?php
/**
 * Settings Operations Handler
 *
 * Handles WordPress settings optimization for Admin AI.
 *
 * @package WyvernCSS
 * @subpackage Admin\Handlers
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Admin\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_Error;

/**
 * Settings Operations Handler Class
 *
 * Provides methods for:
 * - Getting performance recommendations
 * - Getting security recommendations
 * - Applying recommendations
 *
 * All methods check user capabilities and return structured results.
 *
 * @since 1.0.0
 */
class Settings_Operations_Handler {

	/**
	 * Get performance recommendations.
	 *
	 * Analyzes site settings and provides optimization suggestions.
	 *
	 * @return array<string, mixed>|WP_Error Result array or error.
	 */
	public function get_performance_recommendations() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'permission_denied',
				esc_html__( 'You do not have permission to manage settings.', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		$recommendations = array();

		// Check if object cache is enabled.
		if ( ! wp_using_ext_object_cache() ) {
			$recommendations[] = array(
				'id'           => 'enable_object_cache',
				'title'        => esc_html__( 'Enable Object Caching', 'wyvern-ai-styling' ),
				'description'  => esc_html__( 'Object caching can significantly improve performance by reducing database queries.', 'wyvern-ai-styling' ),
				'severity'     => 'medium',
				'can_auto_fix' => false,
				'action'       => esc_html__( 'Install a caching plugin like Redis Object Cache or install Redis/Memcached on your server.', 'wyvern-ai-styling' ),
			);
		}

		// Check post revisions limit.
		$post_revisions = get_option( 'WP_POST_REVISIONS', true );
		if ( true === $post_revisions || ( is_numeric( $post_revisions ) && (int) $post_revisions > 5 ) ) {
			$recommendations[] = array(
				'id'           => 'limit_post_revisions',
				'title'        => esc_html__( 'Limit Post Revisions', 'wyvern-ai-styling' ),
				'description'  => esc_html__( 'Excessive post revisions can bloat your database. Consider limiting to 5 revisions.', 'wyvern-ai-styling' ),
				'severity'     => 'low',
				'can_auto_fix' => false,
				'action'       => esc_html__( 'Add "define(\'WP_POST_REVISIONS\', 5);" to wp-config.php', 'wyvern-ai-styling' ),
			);
		}

		// Check autosave interval.
		$autosave_interval = defined( 'AUTOSAVE_INTERVAL' ) ? AUTOSAVE_INTERVAL : 60;
		if ( $autosave_interval < 120 ) {
			$recommendations[] = array(
				'id'           => 'increase_autosave_interval',
				'title'        => esc_html__( 'Increase Autosave Interval', 'wyvern-ai-styling' ),
				'description'  => esc_html__( 'Autosaving every 60 seconds can create unnecessary database writes. Consider increasing to 120 seconds.', 'wyvern-ai-styling' ),
				'severity'     => 'low',
				'can_auto_fix' => false,
				'action'       => esc_html__( 'Add "define(\'AUTOSAVE_INTERVAL\', 120);" to wp-config.php', 'wyvern-ai-styling' ),
			);
		}

		// Check if Heartbeat API is optimized.
		$recommendations[] = array(
			'id'           => 'optimize_heartbeat',
			'title'        => esc_html__( 'Optimize Heartbeat API', 'wyvern-ai-styling' ),
			'description'  => esc_html__( 'The Heartbeat API can cause unnecessary server load. Consider disabling or reducing frequency on frontend.', 'wyvern-ai-styling' ),
			'severity'     => 'low',
			'can_auto_fix' => false,
			'action'       => esc_html__( 'Use a plugin like Heartbeat Control to optimize the Heartbeat API.', 'wyvern-ai-styling' ),
		);

		// Check lazy loading.
		if ( ! get_option( 'wp_lazy_loading_enabled', true ) ) {
			$recommendations[] = array(
				'id'           => 'enable_lazy_loading',
				'title'        => esc_html__( 'Enable Lazy Loading', 'wyvern-ai-styling' ),
				'description'  => esc_html__( 'Lazy loading images can improve page load times.', 'wyvern-ai-styling' ),
				'severity'     => 'medium',
				'can_auto_fix' => true,
				'action'       => esc_html__( 'Enable lazy loading for images.', 'wyvern-ai-styling' ),
			);
		}

		return array(
			'success'         => true,
			'recommendations' => $recommendations,
			'count'           => count( $recommendations ),
			'action'          => 'get_performance_recommendations',
			'message'         => sprintf(
				/* translators: %d: number of recommendations found */
				esc_html( _n( 'Found %d performance recommendation.', 'Found %d performance recommendations.', count( $recommendations ), 'wyvern-ai-styling' ) ),
				count( $recommendations )
			),
		);
	}

	/**
	 * Get security recommendations.
	 *
	 * Analyzes security settings and provides hardening suggestions.
	 *
	 * @return array<string, mixed>|WP_Error Result array or error.
	 */
	public function get_security_recommendations() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'permission_denied',
				esc_html__( 'You do not have permission to manage settings.', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		$recommendations = array();

		// Check if debug mode is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$recommendations[] = array(
				'id'           => 'disable_debug_mode',
				'title'        => esc_html__( 'Disable Debug Mode', 'wyvern-ai-styling' ),
				'description'  => esc_html__( 'Debug mode should not be enabled on production sites as it exposes sensitive information.', 'wyvern-ai-styling' ),
				'severity'     => 'high',
				'can_auto_fix' => false,
				'action'       => esc_html__( 'Set "define(\'WP_DEBUG\', false);" in wp-config.php', 'wyvern-ai-styling' ),
			);
		}

		// Check if debug log is publicly accessible.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$recommendations[] = array(
				'id'           => 'protect_debug_log',
				'title'        => esc_html__( 'Protect Debug Log', 'wyvern-ai-styling' ),
				'description'  => esc_html__( 'The debug log file can expose sensitive information. Ensure it is not publicly accessible.', 'wyvern-ai-styling' ),
				'severity'     => 'high',
				'can_auto_fix' => false,
				'action'       => esc_html__( 'Move debug.log outside the web root or add .htaccess rules to block access.', 'wyvern-ai-styling' ),
			);
		}

		// Check file permissions (using direct PHP function as WP_Filesystem is not suitable for permission checks).
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Checking file permissions, not modifying files.
		if ( is_writable( ABSPATH . 'wp-config.php' ) ) {
			$recommendations[] = array(
				'id'           => 'fix_file_permissions',
				'title'        => esc_html__( 'Fix File Permissions', 'wyvern-ai-styling' ),
				'description'  => esc_html__( 'wp-config.php should not be writable by the web server. Set permissions to 440 or 400.', 'wyvern-ai-styling' ),
				'severity'     => 'high',
				'can_auto_fix' => false,
				'action'       => esc_html__( 'Change file permissions: chmod 440 wp-config.php', 'wyvern-ai-styling' ),
			);
		}

		// Check if XML-RPC is disabled.
		if ( get_option( 'enable_xmlrpc', true ) ) {
			$recommendations[] = array(
				'id'           => 'disable_xmlrpc',
				'title'        => esc_html__( 'Disable XML-RPC', 'wyvern-ai-styling' ),
				'description'  => esc_html__( 'XML-RPC can be exploited for brute force attacks. Disable it if not needed.', 'wyvern-ai-styling' ),
				'severity'     => 'medium',
				'can_auto_fix' => true,
				'action'       => esc_html__( 'Disable XML-RPC functionality.', 'wyvern-ai-styling' ),
			);
		}

		// Check if file editing is disabled.
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) || ! DISALLOW_FILE_EDIT ) {
			$recommendations[] = array(
				'id'           => 'disable_file_editing',
				'title'        => esc_html__( 'Disable File Editing', 'wyvern-ai-styling' ),
				'description'  => esc_html__( 'Disable the theme and plugin editor to prevent unauthorized code changes.', 'wyvern-ai-styling' ),
				'severity'     => 'high',
				'can_auto_fix' => false,
				'action'       => esc_html__( 'Add "define(\'DISALLOW_FILE_EDIT\', true);" to wp-config.php', 'wyvern-ai-styling' ),
			);
		}

		// Check SSL.
		if ( ! is_ssl() ) {
			$recommendations[] = array(
				'id'           => 'enable_ssl',
				'title'        => esc_html__( 'Enable SSL/HTTPS', 'wyvern-ai-styling' ),
				'description'  => esc_html__( 'Your site is not using SSL. HTTPS encrypts data and improves security and SEO.', 'wyvern-ai-styling' ),
				'severity'     => 'high',
				'can_auto_fix' => false,
				'action'       => esc_html__( 'Install an SSL certificate and configure WordPress to use HTTPS.', 'wyvern-ai-styling' ),
			);
		}

		return array(
			'success'         => true,
			'recommendations' => $recommendations,
			'count'           => count( $recommendations ),
			'action'          => 'get_security_recommendations',
			'message'         => sprintf(
				/* translators: %d: number of recommendations found */
				esc_html( _n( 'Found %d security recommendation.', 'Found %d security recommendations.', count( $recommendations ), 'wyvern-ai-styling' ) ),
				count( $recommendations )
			),
		);
	}

	/**
	 * Apply a specific recommendation.
	 *
	 * @param string $recommendation_id Recommendation ID.
	 * @return array<string, mixed>|WP_Error Result array or error.
	 */
	public function apply_recommendation( string $recommendation_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'permission_denied',
				esc_html__( 'You do not have permission to manage settings.', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		// Only auto-fixable recommendations can be applied.
		$applied = false;
		$message = '';

		switch ( $recommendation_id ) {
			case 'enable_lazy_loading':
				update_option( 'wp_lazy_loading_enabled', true );
				$applied = true;
				$message = esc_html__( 'Lazy loading has been enabled.', 'wyvern-ai-styling' );
				break;

			case 'disable_xmlrpc':
				update_option( 'enable_xmlrpc', false );
				$applied = true;
				$message = esc_html__( 'XML-RPC has been disabled.', 'wyvern-ai-styling' );
				break;

			default:
				return new WP_Error(
					'cannot_auto_fix',
					esc_html__( 'This recommendation cannot be automatically applied. Please follow the manual instructions.', 'wyvern-ai-styling' ),
					array( 'status' => 400 )
				);
		}

		if ( $applied ) {
			return array(
				'success'           => true,
				'recommendation_id' => $recommendation_id,
				'message'           => $message,
				'action'            => 'apply_recommendation',
			);
		}

		return new WP_Error(
			'apply_failed',
			esc_html__( 'Failed to apply recommendation.', 'wyvern-ai-styling' ),
			array( 'status' => 500 )
		);
	}
}
