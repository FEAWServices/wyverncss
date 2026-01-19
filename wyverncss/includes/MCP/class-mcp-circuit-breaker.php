<?php
/**
 * MCP Circuit Breaker Class
 *
 * Implements circuit breaker pattern for MCP service health monitoring.
 *
 * @package WyvernCSS
 * @subpackage MCP
 * @since 2.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * MCP Circuit Breaker Class
 *
 * Prevents cascading failures by monitoring service health and temporarily
 * disabling requests when service is down.
 *
 * States:
 * - CLOSED: Service healthy, requests allowed
 * - OPEN: Service unhealthy (5 failures), requests blocked for 1 hour
 * - HALF_OPEN: Testing recovery, allow 1 test request
 *
 * State Transitions:
 * - CLOSED → OPEN: After 5 failures within 60 seconds
 * - OPEN → HALF_OPEN: After 1 hour cooldown period
 * - HALF_OPEN → CLOSED: After 1 successful request
 * - HALF_OPEN → OPEN: After any failure
 *
 * @since 2.0.0
 */
class MCP_Circuit_Breaker {

	/**
	 * Circuit breaker states.
	 */
	private const STATE_CLOSED    = 'closed';
	private const STATE_OPEN      = 'open';
	private const STATE_HALF_OPEN = 'half_open';

	/**
	 * Failure threshold before opening circuit.
	 *
	 * @var int
	 */
	private const FAILURE_THRESHOLD = 5;

	/**
	 * Time window for counting failures (seconds).
	 *
	 * @var int
	 */
	private const FAILURE_WINDOW = 60;

	/**
	 * Cooldown period before attempting recovery (seconds).
	 *
	 * @var int
	 */
	private const COOLDOWN_PERIOD = 3600; // 1 hour

	/**
	 * Transient key prefix for circuit state.
	 *
	 * @var string
	 */
	private const STATE_KEY_PREFIX = 'wyverncss_circuit_state_';

	/**
	 * Transient key prefix for failure count.
	 *
	 * @var string
	 */
	private const FAILURES_KEY_PREFIX = 'wyverncss_circuit_failures_';

	/**
	 * Transient key prefix for opened timestamp.
	 *
	 * @var string
	 */
	private const OPENED_AT_KEY_PREFIX = 'wyverncss_circuit_opened_at_';

	/**
	 * Service name (for multiple circuit breakers).
	 *
	 * @var string
	 */
	private string $service_name;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $service_name Service name (default: 'mcp_service').
	 */
	public function __construct( string $service_name = 'mcp_service' ) {
		$this->service_name = $service_name;
	}

	/**
	 * Check if service is available for requests.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if requests allowed, false if circuit is open.
	 */
	public function is_available(): bool {
		$state = $this->get_state();

		switch ( $state ) {
			case self::STATE_CLOSED:
				return true;

			case self::STATE_OPEN:
				// Check if cooldown period has passed.
				if ( $this->should_attempt_recovery() ) {
					$this->transition_to_half_open();
					return true; // Allow one test request.
				}
				return false;

			case self::STATE_HALF_OPEN:
				return true; // Allow test request.

			default:
				// Invalid state - default to closed.
				$this->set_state( self::STATE_CLOSED );
				return true;
		}
	}

	/**
	 * Record successful request.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function record_success(): void {
		$state = $this->get_state();

		if ( self::STATE_HALF_OPEN === $state ) {
			// Recovery successful - close circuit.
			$this->transition_to_closed();
			return;
		}

		if ( self::STATE_CLOSED === $state ) {
			// Reset failure count on success.
			$this->reset_failures();
		}
	}

	/**
	 * Record failed request.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function record_failure(): void {
		$state = $this->get_state();

		if ( self::STATE_HALF_OPEN === $state ) {
			// Recovery failed - re-open circuit.
			$this->transition_to_open();
			return;
		}

		if ( self::STATE_CLOSED === $state ) {
			// Increment failure count.
			$failures = $this->increment_failures();

			// Check if threshold exceeded.
			if ( $failures >= self::FAILURE_THRESHOLD ) {
				$this->transition_to_open();
			}
		}
	}

	/**
	 * Get number of seconds until retry is allowed.
	 *
	 * @since 2.0.0
	 *
	 * @return int Seconds until retry (0 if available now).
	 */
	public function get_retry_after(): int {
		$state = $this->get_state();

		if ( self::STATE_OPEN !== $state ) {
			return 0;
		}

		$opened_at = $this->get_opened_at();
		if ( 0 === $opened_at ) {
			return 0;
		}

		$elapsed     = time() - $opened_at;
		$retry_after = self::COOLDOWN_PERIOD - $elapsed;

		return max( 0, $retry_after );
	}

	/**
	 * Get current circuit state.
	 *
	 * @since 2.0.0
	 *
	 * @return string Circuit state (closed, open, half_open).
	 */
	public function get_state(): string {
		$state = get_transient( $this->get_state_key() );

		if ( false === $state || ! is_string( $state ) ) {
			return self::STATE_CLOSED;
		}

		return $state;
	}

	/**
	 * Set circuit state.
	 *
	 * @since 2.0.0
	 *
	 * @param string $state Circuit state.
	 * @return void
	 */
	private function set_state( string $state ): void {
		// State is persistent until manually changed.
		set_transient( $this->get_state_key(), $state, DAY_IN_SECONDS );
	}

	/**
	 * Check if enough time has passed to attempt recovery.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if recovery should be attempted.
	 */
	private function should_attempt_recovery(): bool {
		$opened_at = $this->get_opened_at();

		if ( 0 === $opened_at ) {
			return true;
		}

		$elapsed = time() - $opened_at;

		return $elapsed >= self::COOLDOWN_PERIOD;
	}

	/**
	 * Get timestamp when circuit was opened.
	 *
	 * @since 2.0.0
	 *
	 * @return int Unix timestamp or 0 if not set.
	 */
	private function get_opened_at(): int {
		$timestamp = get_transient( $this->get_opened_at_key() );

		if ( false === $timestamp || ! is_int( $timestamp ) ) {
			return 0;
		}

		return $timestamp;
	}

	/**
	 * Set timestamp when circuit was opened.
	 *
	 * @since 2.0.0
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return void
	 */
	private function set_opened_at( int $timestamp ): void {
		set_transient( $this->get_opened_at_key(), $timestamp, DAY_IN_SECONDS );
	}

	/**
	 * Get current failure count.
	 *
	 * @since 2.0.0
	 *
	 * @return int Number of failures.
	 */
	private function get_failures(): int {
		$failures = get_transient( $this->get_failures_key() );

		if ( false === $failures || ! is_int( $failures ) ) {
			return 0;
		}

		return $failures;
	}

	/**
	 * Increment failure count.
	 *
	 * @since 2.0.0
	 *
	 * @return int New failure count.
	 */
	private function increment_failures(): int {
		$failures = $this->get_failures() + 1;
		set_transient( $this->get_failures_key(), $failures, self::FAILURE_WINDOW );
		return $failures;
	}

	/**
	 * Reset failure count.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function reset_failures(): void {
		delete_transient( $this->get_failures_key() );
	}

	/**
	 * Transition to CLOSED state.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function transition_to_closed(): void {
		$this->set_state( self::STATE_CLOSED );
		$this->reset_failures();
		delete_transient( $this->get_opened_at_key() );

		// Log recovery if WP_DEBUG enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'WyvernCSS Circuit Breaker: %s recovered (CLOSED)',
					$this->service_name
				)
			);
		}
	}

	/**
	 * Transition to OPEN state.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function transition_to_open(): void {
		$this->set_state( self::STATE_OPEN );
		$this->set_opened_at( time() );

		// Log circuit opening if WP_DEBUG enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'WyvernCSS Circuit Breaker: %s is DOWN (OPEN) - cooldown %d seconds',
					$this->service_name,
					self::COOLDOWN_PERIOD
				)
			);
		}

		// Send admin notification.
		$this->notify_admin();
	}

	/**
	 * Transition to HALF_OPEN state.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function transition_to_half_open(): void {
		$this->set_state( self::STATE_HALF_OPEN );

		// Log recovery attempt if WP_DEBUG enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'WyvernCSS Circuit Breaker: %s testing recovery (HALF_OPEN)',
					$this->service_name
				)
			);
		}
	}

	/**
	 * Send admin notification when circuit opens.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function notify_admin(): void {
		// Check if notification was recently sent (don't spam).
		$notification_key = 'wyverncss_circuit_notified_' . $this->service_name;
		if ( get_transient( $notification_key ) ) {
			return;
		}

		// Set flag to prevent duplicate notifications within 1 hour.
		set_transient( $notification_key, true, HOUR_IN_SECONDS );

		// Get admin email.
		$admin_email = get_option( 'admin_email' );
		if ( empty( $admin_email ) ) {
			return;
		}

		// Prepare email.
		$subject = sprintf(
			/* translators: %s: service name */
			__( 'WyvernCSS: %s is unavailable', 'wyvern-ai-styling' ),
			ucwords( str_replace( '_', ' ', $this->service_name ) )
		);

		$message = sprintf(
			/* translators: 1: service name, 2: failure threshold, 3: cooldown period in minutes */
			__(
				'The %1$s has been automatically disabled due to %2$d consecutive failures.

The service will be tested again after %3$d minutes.

This is an automated notification from WyvernCSS. No action is required unless this issue persists.

Technical Details:
- Service: %1$s
- State: OPEN (disabled)
- Cooldown: %3$d minutes
- Timestamp: %4$s',
				'wyvern-ai-styling'
			),
			ucwords( str_replace( '_', ' ', $this->service_name ) ),
			self::FAILURE_THRESHOLD,
			(int) ( self::COOLDOWN_PERIOD / 60 ),
			gmdate( 'Y-m-d H:i:s' )
		);

		// Send email (non-blocking).
		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Get transient key for circuit state.
	 *
	 * @since 2.0.0
	 *
	 * @return string Transient key.
	 */
	private function get_state_key(): string {
		return self::STATE_KEY_PREFIX . $this->service_name;
	}

	/**
	 * Get transient key for failure count.
	 *
	 * @since 2.0.0
	 *
	 * @return string Transient key.
	 */
	private function get_failures_key(): string {
		return self::FAILURES_KEY_PREFIX . $this->service_name;
	}

	/**
	 * Get transient key for opened timestamp.
	 *
	 * @since 2.0.0
	 *
	 * @return string Transient key.
	 */
	private function get_opened_at_key(): string {
		return self::OPENED_AT_KEY_PREFIX . $this->service_name;
	}
}
