<?php
/**
 * Admin Notice Manager
 *
 * Manages admin notices with transient storage.
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
 * Admin Notice Manager
 *
 * Manages admin notices with transient storage for post-redirect display.
 *
 * @package WyvernCSS\Admin
 * @since 1.0.0
 */
class NoticeManager {

	/**
	 * Transient key for notices
	 */
	private const TRANSIENT_KEY = 'wyverncss_admin_notices';

	/**
	 * Transient expiration (60 seconds)
	 */
	private const TRANSIENT_EXPIRATION = 60;

	/**
	 * Initialize the notice manager
	 *
	 * @return void */
	public function init(): void {
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
	}

	/**
	 * Add a success notice
	 *
	 * @param string $message Notice message.
	 * @return void */
	public function add_success( string $message ): void {
		$this->add_notice( $message, 'success' );
	}

	/**
	 * Add an error notice
	 *
	 * @param string $message Notice message.
	 * @return void */
	public function add_error( string $message ): void {
		$this->add_notice( $message, 'error' );
	}

	/**
	 * Add a warning notice
	 *
	 * @param string $message Notice message.
	 * @return void */
	public function add_warning( string $message ): void {
		$this->add_notice( $message, 'warning' );
	}

	/**
	 * Add an info notice
	 *
	 * @param string $message Notice message.
	 * @return void */
	public function add_info( string $message ): void {
		$this->add_notice( $message, 'info' );
	}

	/**
	 * Add a notice to transient storage
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type (success, error, warning, info).
	 * @return void */
	private function add_notice( string $message, string $type ): void {
		$notices = $this->get_notices();

		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);

		set_transient( self::TRANSIENT_KEY, $notices, self::TRANSIENT_EXPIRATION );
	}

	/**
	 * Get all stored notices
	 *
	 * @return array<int, array<string, string>> Array of notices.
	 */
	private function get_notices(): array {
		$notices = get_transient( self::TRANSIENT_KEY );

		if ( ! is_array( $notices ) ) {
			return array();
		}

		return $notices;
	}

	/**
	 * Display all notices and clear them
	 *
	 * @return void */
	public function display_notices(): void {
		$notices = $this->get_notices();

		if ( empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			$this->render_notice( $notice['message'], $notice['type'] );
		}

		// Clear notices after display.
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Render a single notice
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type.
	 * @return void */
	private function render_notice( string $message, string $type ): void {
		$allowed_types = array( 'success', 'error', 'warning', 'info' );
		$type          = in_array( $type, $allowed_types, true ) ? $type : 'info';

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
