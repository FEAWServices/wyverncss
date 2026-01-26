<?php
/**
 * License REST API Controller
 *
 * Handles REST API endpoints for license management and validation.
 *
 * @package WyvernCSS\API\REST
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\API\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WyvernCSS\Freemius\Freemius_Integration;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class License_Controller
 *
 * REST API controller for license operations.
 */
class License_Controller extends WP_REST_Controller {

	/**
	 * API namespace
	 */
	private const NAMESPACE = 'wyvernpress/v1';

	/**
	 * Freemius integration instance
	 *
	 * @var Freemius_Integration
	 */
	private Freemius_Integration $freemius;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->freemius = Freemius_Integration::get_instance();
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /license/status - Get current license status with cache info.
		register_rest_route(
			self::NAMESPACE,
			'/license/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_license_status' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST /license/refresh - Clear cache and re-validate.
		register_rest_route(
			self::NAMESPACE,
			'/license/refresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'refresh_license' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Get license status
	 *
	 * @param WP_REST_Request $request Request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Response with license status.
	 */
	public function get_license_status( WP_REST_Request $request ): WP_REST_Response {
		$license_data = $this->freemius->get_license_data();
		$plan         = $this->freemius->get_plan();
		$features     = $this->freemius->get_tier_features();

		// Build response.
		$response = array(
			'status'                => $license_data['status'] ?? 'free',
			'tier'                  => $plan,
			'features'              => $features,
			'expires'               => $license_data['expires'] ?? null,
			'expires_formatted'     => $this->format_expiration_date( $license_data['expires'] ?? null ),
			'cached_at'             => $license_data['cached_at'] ?? null,
			'last_validated_at'     => $license_data['last_validated_at'] ?? null,
			'is_offline_mode'       => $this->freemius->is_offline_mode(),
			'is_active'             => $license_data['is_active'] ?? false,
			'is_expired'            => $license_data['is_expired'] ?? false,
			'days_until_expiration' => $this->freemius->get_days_until_expiration(),
		);

		// Add premium-specific info.
		if ( $this->freemius->is_premium() ) {
			$response['activations'] = $license_data['activations'] ?? 0;
			$response['max_sites']   = $license_data['max_sites'] ?? 1;
			$response['rate_limit']  = $this->freemius->get_rate_limit();
			$response['period']      = $this->freemius->get_period();
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Refresh license
	 *
	 * Clears all license caches and forces re-validation from Freemius.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function refresh_license( WP_REST_Request $request ) {
		// Clear all caches.
		$this->freemius->clear_all_caches();

		// Force re-fetch by calling get_license_data().
		$license_data = $this->freemius->get_license_data();

		// Check if refresh was successful.
		if ( isset( $license_data['is_offline'] ) && $license_data['is_offline'] ) {
			return new WP_Error(
				'refresh_failed',
				__( 'Unable to refresh license. Please check your internet connection and try again.', 'wyvern-ai-styling' ),
				array( 'status' => 503 )
			);
		}

		$plan     = $this->freemius->get_plan();
		$features = $this->freemius->get_tier_features();

		// Build response.
		$response = array(
			'success'               => true,
			'message'               => __( 'License refreshed successfully', 'wyvern-ai-styling' ),
			'status'                => $license_data['status'] ?? 'free',
			'tier'                  => $plan,
			'features'              => $features,
			'expires'               => $license_data['expires'] ?? null,
			'expires_formatted'     => $this->format_expiration_date( $license_data['expires'] ?? null ),
			'cached_at'             => $license_data['cached_at'] ?? null,
			'last_validated_at'     => $license_data['last_validated_at'] ?? null,
			'is_offline_mode'       => false,
			'is_active'             => $license_data['is_active'] ?? false,
			'is_expired'            => $license_data['is_expired'] ?? false,
			'days_until_expiration' => $this->freemius->get_days_until_expiration(),
		);

		// Add premium-specific info.
		if ( $this->freemius->is_premium() ) {
			$response['activations'] = $license_data['activations'] ?? 0;
			$response['max_sites']   = $license_data['max_sites'] ?? 1;
			$response['rate_limit']  = $this->freemius->get_rate_limit();
			$response['period']      = $this->freemius->get_period();
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Check if user has permission to manage license
	 *
	 * @return bool True if user can manage options, false otherwise.
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Format expiration date for display
	 *
	 * @param int|null $timestamp Unix timestamp or null.
	 * @return string|null Formatted date or null.
	 */
	private function format_expiration_date( ?int $timestamp ): ?string {
		if ( null === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
