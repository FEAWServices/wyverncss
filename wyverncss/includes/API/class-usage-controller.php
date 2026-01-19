<?php
/**
 * Usage Controller
 *
 * REST endpoint for usage statistics and tracking.
 *
 * @package WyvernCSS
 * @subpackage API
 */

declare(strict_types=1);

namespace WyvernCSS\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WyvernCSS\Config\Tier_Config;

/**
 * Usage Controller Class
 *
 * Handles GET /wyverncss/v1/usage endpoint.
 *
 * Features:
 * - Current usage statistics per user
 * - Subscription tier information
 * - Rate limit details
 * - Historical usage trends
 * - Date range filtering
 *
 * @since 1.0.0
 */
class UsageController extends RESTController {

	/**
	 * Usage tracking option key prefix.
	 *
	 * @var string
	 */
	private const USAGE_PREFIX = 'wyverncss_usage_';

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 * @return void */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/usage',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_usage' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_endpoint_args(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Get usage statistics for current user.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Response with usage data.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function get_usage( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();

		// Get user's subscription tier.
		$tier = $this->get_user_tier( $user_id );

		// Get rate limit for tier.
		$limit = $this->get_rate_limit_for_user( $user_id );

		// Get current usage.
		$current_usage = $this->get_current_usage( $user_id );

		// Calculate remaining requests.
		$remaining = max( 0, $limit - $current_usage );

		// Get date range parameters.
		$period_param = $request->get_param( 'period' );
		$period       = $this->sanitize_text( $period_param ? $period_param : 'current' );

		// Get historical data based on period.
		$historical_data = $this->get_historical_usage( $user_id, $period );

		// Get reset time (uses parent's method which accounts for tier).
		$reset_at = $this->get_rate_limit_reset_time( $user_id );

		return $this->success_response(
			array(
				'tier'            => $tier,
				'current_period'  => array(
					'used'      => $current_usage,
					'limit'     => $limit,
					'remaining' => $remaining,
					'percent'   => $limit > 0 ? round( ( $current_usage / $limit ) * 100, 2 ) : 0,
					'reset_at'  => $reset_at,
				),
				'historical'      => $historical_data,
				'status'          => $this->get_usage_status( $current_usage, $limit ),
				'recommendations' => $this->get_recommendations( $current_usage, $limit, $tier ),
			)
		);
	}

	/**
	 * Get historical usage data.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID.
	 * @param string $period Period type (current, last3, last6, last12).
	 * @return array<int, array<string, int|string>> Historical usage data.
	 */
	private function get_historical_usage( int $user_id, string $period ): array {
		$months_back = match ( $period ) {
			'last3'  => 3,
			'last6'  => 6,
			'last12' => 12,
			default  => 1,
		};

		$historical = array();

		for ( $i = 0; $i < $months_back; $i++ ) {
			$timestamp  = strtotime( "-{$i} months" );
			$month_key  = gmdate( 'Y-m', $timestamp );
			$month_name = gmdate( 'M Y', $timestamp );
			$option_key = self::USAGE_PREFIX . $user_id . '_' . $month_key;

			$usage = (int) get_option( $option_key, 0 );

			$historical[] = array(
				'month' => $month_name,
				'key'   => $month_key,
				'usage' => $usage,
			);
		}

		return array_reverse( $historical );
	}

	/**
	 * Get usage status indicator.
	 *
	 * @since 1.0.0
	 * @param int $usage Current usage.
	 * @param int $limit Rate limit.
	 * @return string Status (healthy, warning, critical).
	 */
	private function get_usage_status( int $usage, int $limit ): string {
		if ( 0 === $limit ) {
			return 'unlimited';
		}

		$percent = ( $usage / $limit ) * 100;

		if ( $percent >= 90 ) {
			return 'critical';
		} elseif ( $percent >= 70 ) {
			return 'warning';
		}

		return 'healthy';
	}

	/**
	 * Get usage recommendations.
	 *
	 * Uses upgrade messages from tiers.json configuration.
	 *
	 * @since 1.0.0
	 * @param int    $usage Current usage.
	 * @param int    $limit Rate limit.
	 * @param string $tier Subscription tier.
	 * @return array<int, string> List of recommendations.
	 */
	private function get_recommendations( int $usage, int $limit, string $tier ): array {
		$recommendations = array();
		$percent         = $limit > 0 ? ( $usage / $limit ) * 100 : 0;
		$tier_config     = Tier_Config::get_instance();

		// Critical usage - at limit.
		if ( $percent >= 90 ) {
			$message = $tier_config->get_upgrade_message( 'at_limit' );
			if ( ! empty( $message ) && 'free' === $tier ) {
				$recommendations[] = $message;
			}
		}

		// Warning usage - approaching limit.
		if ( $percent >= 70 && $percent < 90 ) {
			$message = $tier_config->get_upgrade_message( 'approaching_limit' );
			if ( ! empty( $message ) && 'free' === $tier ) {
				$recommendations[] = $message;
			}
		}

		// Pattern library optimization (always show).
		$recommendations[] = __( 'WyvernCSS uses pattern matching first to minimize API costs. Use common styling requests for best efficiency.', 'wyvern-ai-styling' );

		return $recommendations;
	}


	/**
	 * Get endpoint arguments.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Endpoint arguments.
	 */
	private function get_endpoint_args(): array {
		return array(
			'period' => array(
				'description'       => __( 'Period for historical data (current, last3, last6, last12).', 'wyvern-ai-styling' ),
				'type'              => 'string',
				'default'           => 'current',
				'enum'              => array( 'current', 'last3', 'last6', 'last12' ),
				'sanitize_callback' => array( $this, 'sanitize_text' ),
			),
		);
	}

	/**
	 * Get schema for usage endpoint.
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
			'title'      => 'usage',
			'type'       => 'object',
			'properties' => array(
				'tier'            => array(
					'description' => __( 'Subscription tier.', 'wyvern-ai-styling' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'current_period'  => array(
					'description' => __( 'Current period usage statistics.', 'wyvern-ai-styling' ),
					'type'        => 'object',
					'context'     => array( 'view' ),
					'readonly'    => true,
					'properties'  => array(
						'used'      => array(
							'type' => 'integer',
						),
						'limit'     => array(
							'type' => 'integer',
						),
						'remaining' => array(
							'type' => 'integer',
						),
						'percent'   => array(
							'type' => 'number',
						),
						'reset_at'  => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
					),
				),
				'historical'      => array(
					'description' => __( 'Historical usage data.', 'wyvern-ai-styling' ),
					'type'        => 'array',
					'context'     => array( 'view' ),
					'readonly'    => true,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'month' => array(
								'type' => 'string',
							),
							'key'   => array(
								'type' => 'string',
							),
							'usage' => array(
								'type' => 'integer',
							),
						),
					),
				),
				'status'          => array(
					'description' => __( 'Usage status indicator.', 'wyvern-ai-styling' ),
					'type'        => 'string',
					'enum'        => array( 'healthy', 'warning', 'critical', 'unlimited' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'recommendations' => array(
					'description' => __( 'Usage recommendations.', 'wyvern-ai-styling' ),
					'type'        => 'array',
					'context'     => array( 'view' ),
					'readonly'    => true,
					'items'       => array(
						'type' => 'string',
					),
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
