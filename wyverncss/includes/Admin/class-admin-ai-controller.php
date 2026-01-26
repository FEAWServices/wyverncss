<?php
/**
 * Admin AI REST Controller
 *
 * REST API endpoints for admin AI commands - natural language control of WordPress admin operations.
 * This is a premium-only feature that allows users to execute bulk operations and administrative
 * tasks using natural language commands.
 *
 * @package WyvernCSS
 * @subpackage Admin
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WyvernCSS\API\RESTController;
use WyvernCSS\Freemius\Freemius_Integration;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Admin AI Controller Class
 *
 * Handles REST endpoints for:
 * - POST /admin-ai/execute - Execute admin AI command
 *
 * All endpoints are premium-only and require 'manage_options' capability.
 *
 * @since 1.0.0
 */
class Admin_AI_Controller extends RESTController {

	/**
	 * Admin AI Handler service.
	 *
	 * @var Admin_AI_Handler
	 */
	private Admin_AI_Handler $handler;

	/**
	 * Freemius Integration.
	 *
	 * @var Freemius_Integration
	 */
	private Freemius_Integration $freemius;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->handler  = new Admin_AI_Handler();
		$this->freemius = Freemius_Integration::get_instance();
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Execute admin AI command.
		register_rest_route(
			$this->namespace,
			'/admin-ai/execute',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'execute_command' ),
				'permission_callback' => array( $this, 'check_admin_ai_permission' ),
				'args'                => array(
					'command' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_prompt' ),
						'validate_callback' => array( $this, 'validate_command' ),
					),
					'params'  => array(
						'type'              => 'object',
						'required'          => false,
						'default'           => array(),
						'sanitize_callback' => array( $this, 'sanitize_params' ),
					),
					'confirm' => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
				),
			)
		);
	}

	/**
	 * Check admin AI permission.
	 *
	 * Admin AI is a premium feature that requires manage_options capability.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function check_admin_ai_permission( WP_REST_Request $request ) {
		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You must be logged in to access this endpoint.', 'wyverncss' ),
				array( 'status' => 401 )
			);
		}

		// Check manage_options capability (administrators only).
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to execute admin AI commands.', 'wyverncss' ),
				array( 'status' => 403 )
			);
		}

		// Check for premium tier and admin_ai_console feature.
		if ( ! $this->freemius->is_premium() ) {
			return new WP_Error(
				'premium_required',
				esc_html__( 'Admin AI is a premium feature. Upgrade to access administrative automation.', 'wyverncss' ),
				array(
					'status'      => 403,
					'upgrade_url' => $this->freemius->get_upgrade_url(),
				)
			);
		}

		// Check if admin_ai_console feature is enabled for this tier.
		$plan = $this->freemius->get_plan();
		if ( ! $this->freemius->has_feature( 'admin_ai_console' ) ) {
			return new WP_Error(
				'feature_not_available',
				esc_html__( 'Admin AI console is not available in your plan.', 'wyverncss' ),
				array(
					'status'       => 403,
					'current_plan' => $plan,
					'upgrade_url'  => $this->freemius->get_upgrade_url(),
				)
			);
		}

		return true;
	}

	/**
	 * Execute admin AI command.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function execute_command( WP_REST_Request $request ) {
		$command = $request->get_param( 'command' );
		$params  = $request->get_param( 'params' );
		$confirm = $request->get_param( 'confirm' );

		if ( ! is_string( $command ) || empty( $command ) ) {
			return $this->error_response( 'invalid_command', 'Command is required.', 400 );
		}

		if ( ! is_array( $params ) ) {
			$params = array();
		}

		if ( ! is_bool( $confirm ) ) {
			$confirm = false;
		}

		// Execute command through handler.
		$result = $this->handler->execute( $command, $params, $confirm );

		// Check for errors.
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Check if confirmation is required.
		if ( isset( $result['requires_confirmation'] ) && true === $result['requires_confirmation'] && ! $confirm ) {
			return $this->success_response(
				array(
					'success'               => false,
					'requires_confirmation' => true,
					'message'               => $result['message'] ?? esc_html__( 'This action requires confirmation.', 'wyverncss' ),
					'data'                  => $result['data'] ?? array(),
					'confirmation_prompt'   => $result['confirmation_prompt'] ?? '',
				)
			);
		}

		// Return successful result.
		return $this->success_response(
			array(
				'success' => $result['success'] ?? true,
				'message' => $result['message'] ?? esc_html__( 'Command executed successfully.', 'wyverncss' ),
				'data'    => $result['data'] ?? array(),
			)
		);
	}

	/**
	 * Validate command parameter.
	 *
	 * @param string $param Command string.
	 * @return bool True if valid.
	 */
	public function validate_command( string $param ): bool {
		$sanitized = $this->sanitize_prompt( $param );

		// Command must be between 3 and 500 characters.
		$length = strlen( $sanitized );
		return $length >= 3 && $length <= 500;
	}

	/**
	 * Sanitize params parameter.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array<string, mixed> Sanitized params.
	 */
	public function sanitize_params( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $value as $key => $val ) {
			$sanitized_key = sanitize_key( $key );

			if ( is_string( $val ) ) {
				$sanitized[ $sanitized_key ] = sanitize_text_field( $val );
			} elseif ( is_int( $val ) || is_float( $val ) ) {
				$sanitized[ $sanitized_key ] = $val;
			} elseif ( is_bool( $val ) ) {
				$sanitized[ $sanitized_key ] = $val;
			} elseif ( is_array( $val ) ) {
				// Recursive sanitization for nested arrays.
				$sanitized[ $sanitized_key ] = $this->sanitize_params( $val );
			}
		}

		return $sanitized;
	}
}
