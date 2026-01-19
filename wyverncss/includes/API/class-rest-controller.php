<?php
/**
 * REST Controller Base Class
 *
 * Base controller providing authentication, rate limiting, and common REST functionality.
 *
 * @package WyvernCSS
 * @subpackage API
 */

declare(strict_types=1);

namespace WyvernCSS\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST Controller Base Class
 *
 * Provides:
 * - Nonce-based authentication
 * - Capability checking (edit_posts required)
 * - Rate limiting per subscription tier
 * - Common REST response helpers
 * - Input sanitization utilities
 *
 * @since 1.0.0
 */
abstract class RESTController extends WP_REST_Controller {

	/**
	 * The namespace for REST routes.
	 *
	 * @var string
	 */
	protected $namespace = 'wyverncss/v1';

	/**
	 * Get tier configuration instance.
	 *
	 * @return \WyvernCSS\Config\Tier_Config
	 */
	private function get_tier_config(): \WyvernCSS\Config\Tier_Config {
		return \WyvernCSS\Config\Tier_Config::get_instance();
	}

	/**
	 * Rate limit option key prefix.
	 *
	 * @var string
	 */
	private const RATE_LIMIT_PREFIX = 'wyverncss_rate_limit_';

	/**
	 * Register routes for this controller.
	 *
	 * Must be implemented by child classes.
	 *
	 * @since 1.0.0
	 * @return void */
	public function register_routes(): void {
		// Must be implemented by child classes.
	}

	/**
	 * Check if current user has permission for REST requests.
	 *
	 * Requires user to be logged in and have edit_posts capability.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The REST request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return bool|WP_Error True if user has permission, WP_Error otherwise.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function check_permission( WP_REST_Request $request ) {
		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You must be logged in to access this endpoint.', 'wyvern-ai-styling' ),
				array( 'status' => 401 )
			);
		}

		// User must have edit_posts capability.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to access this endpoint.', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check rate limit for current user.
	 *
	 * @since 1.0.0
	 * @param int|null $user_id User ID (defaults to current user).
	 * @return bool|WP_Error True if under limit, WP_Error if limit exceeded.
	 */
	protected function check_rate_limit( ?int $user_id = null ): bool|WP_Error {
		$user_id = $user_id ?? get_current_user_id();

		if ( ! $user_id ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'User not found.', 'wyvern-ai-styling' ),
				array( 'status' => 403 )
			);
		}

		// Get user's subscription tier.
		$tier = $this->get_user_tier( $user_id );

		// Get rate limit for tier.
		$limit = $this->get_tier_config()->get_rate_limit( $tier );

		// Get current usage count.
		$usage_key = $this->get_rate_limit_key( $user_id );
		$usage     = (int) get_option( $usage_key, 0 );

		// Unlimited tier (-1) always passes.
		if ( $limit < 0 ) {
			return true;
		}

		// Check if limit exceeded.
		if ( $usage >= $limit ) {
			$tier_config = $this->get_tier_config();
			$period      = $tier_config->is_daily_reset( $tier ) ? 'today' : 'this month';

			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %1$d: current usage, %2$d: rate limit, %3$s: time period (today/this month) */
					esc_html__( 'Rate limit exceeded. You have used %1$d of %2$d requests %3$s.', 'wyvern-ai-styling' ),
					$usage,
					$limit,
					$period
				),
				array(
					'status'   => 429,
					'usage'    => $usage,
					'limit'    => $limit,
					'tier'     => $tier,
					'reset_at' => $this->get_rate_limit_reset_time(),
				)
			);
		}

		return true;
	}

	/**
	 * Increment rate limit counter for current user.
	 *
	 * @since 1.0.0
	 * @param int|null $user_id User ID (defaults to current user).
	 * @return void */
	protected function increment_rate_limit( ?int $user_id = null ): void {
		$user_id = $user_id ?? get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$usage_key = $this->get_rate_limit_key( $user_id );
		$usage     = (int) get_option( $usage_key, 0 );
		update_option( $usage_key, $usage + 1, false );
	}

	/**
	 * Get rate limit key for user.
	 *
	 * Daily reset tiers use daily key (Y-m-d), monthly tiers use monthly key (Y-m).
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return string Option key for rate limit tracking.
	 */
	private function get_rate_limit_key( int $user_id ): string {
		$tier        = $this->get_user_tier( $user_id );
		$tier_config = $this->get_tier_config();

		// Check if tier uses daily reset.
		if ( $tier_config->is_daily_reset( $tier ) ) {
			$date_key = gmdate( 'Y-m-d' );
		} else {
			// Monthly reset.
			$date_key = gmdate( 'Y-m' );
		}

		return self::RATE_LIMIT_PREFIX . $user_id . '_' . $date_key;
	}

	/**
	 * Get user's subscription tier.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return string Tier name (free, starter, pro).
	 */
	protected function get_user_tier( int $user_id ): string {
		$tier = get_user_meta( $user_id, 'wyverncss_tier', true );
		return in_array( $tier, array( 'free', 'starter', 'pro' ), true ) ? $tier : 'free';
	}

	/**
	 * Get rate limit reset time.
	 *
	 * Daily reset tiers reset at 00:00 UTC tomorrow.
	 * Monthly reset tiers reset on first day of next month at 00:00 UTC.
	 *
	 * @since 1.0.0
	 * @param int|null $user_id User ID (defaults to current user).
	 * @return string ISO 8601 timestamp of reset time.
	 */
	protected function get_rate_limit_reset_time( ?int $user_id = null ): string {
		$user_id     = $user_id ?? get_current_user_id();
		$tier        = $this->get_user_tier( $user_id );
		$tier_config = $this->get_tier_config();

		if ( $tier_config->is_unlimited( $tier ) ) {
			// Unlimited tier - no reset needed.
			return '';
		}

		if ( $tier_config->is_daily_reset( $tier ) ) {
			// Daily reset at 00:00 UTC tomorrow.
			$reset_time = strtotime( 'tomorrow 00:00:00 UTC' );
		} else {
			// Monthly reset on first of next month.
			$reset_time = strtotime( 'first day of next month 00:00:00 UTC' );
		}

		return gmdate( 'c', $reset_time );
	}

	/**
	 * Get rate limit for user's tier.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return int Rate limit (-1 for unlimited).
	 */
	protected function get_rate_limit_for_user( int $user_id ): int {
		$tier = $this->get_user_tier( $user_id );
		return $this->get_tier_config()->get_rate_limit( $tier );
	}

	/**
	 * Get current usage for user.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return int Current usage count.
	 */
	protected function get_current_usage( int $user_id ): int {
		$usage_key = $this->get_rate_limit_key( $user_id );
		return (int) get_option( $usage_key, 0 );
	}

	/**
	 * Create a success response.
	 *
	 * @since 1.0.0
	 * @param mixed $data Response data.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response */
	protected function success_response( $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Create an error response.
	 *
	 * @since 1.0.0
	 * @param string               $code Error code.
	 * @param string               $message Error message.
	 * @param int                  $status HTTP status code.
	 * @param array<string, mixed> $data Additional error data.
	 * @return WP_Error */
	protected function error_response( string $code, string $message, int $status = 400, array $data = array() ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			array_merge( array( 'status' => $status ), $data )
		);
	}

	/**
	 * Sanitize text input.
	 *
	 * @since 1.0.0
	 * @param mixed $value The value to sanitize.
	 * @return string Sanitized text.
	 */
	public function sanitize_text( $value ): string {
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Sanitize prompt input.
	 *
	 * Allows more characters than sanitize_text_field for natural language.
	 *
	 * @since 1.0.0
	 * @param mixed $value The value to sanitize.
	 * @return string Sanitized prompt.
	 */
	public function sanitize_prompt( $value ): string {
		$prompt = wp_unslash( (string) $value );
		// Allow letters, numbers, spaces, and common punctuation.
		$prompt = preg_replace( '/[^a-zA-Z0-9\s\.\,\!\?\-\'\"\(\)]/', '', $prompt );
		return trim( $prompt ?? '' );
	}

	/**
	 * Validate prompt parameter.
	 *
	 * @since 1.0.0
	 * @param mixed $param The parameter value.
	 * @return bool True if valid.
	 */
	public function validate_prompt( $param ): bool {
		if ( ! is_string( $param ) ) {
			return false;
		}

		$sanitized = $this->sanitize_prompt( $param );

		// Prompt must be between 3 and 500 characters.
		$length = strlen( $sanitized );
		return $length >= 3 && $length <= 500;
	}

	/**
	 * Validate integer parameter.
	 *
	 * Accepts both integer values and numeric strings that represent integers (without decimals).
	 *
	 * @since 1.0.0
	 * @param mixed $param The parameter value.
	 * @return bool True if valid integer or integer string.
	 */
	public function validate_integer( $param ): bool {
		// Must be numeric (int or numeric string).
		if ( ! is_numeric( $param ) ) {
			return false;
		}

		// Convert to string for decimal check.
		$param_string = (string) $param;

		// Reject if contains decimal point (e.g., '123.45').
		if ( strpos( $param_string, '.' ) !== false ) {
			return false;
		}

		// Ensure the integer conversion matches the original value.
		// This handles both actual integers and numeric strings.
		// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Intentional loose comparison to accept both int and string.
		return (int) $param == $param;
	}

	/**
	 * Validate boolean parameter.
	 *
	 * @since 1.0.0
	 * @param mixed $param The parameter value.
	 * @return bool True if valid.
	 */
	public function validate_boolean( $param ): bool {
		return is_bool( $param ) || in_array( $param, array( '0', '1', 'true', 'false' ), true );
	}
}
