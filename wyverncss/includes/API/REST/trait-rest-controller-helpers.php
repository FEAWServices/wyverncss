<?php
/**
 * REST Controller Helpers Trait
 *
 * Provides common REST controller helper methods to eliminate duplication.
 *
 * @package WyvernCSS\API\REST
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\API\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\MCP\ToolRegistry;

/**
 * RestControllerHelpersTrait
 *
 * Common functionality for REST controllers that interact with the MCP system.
 */
trait RestControllerHelpersTrait {

	/**
	 * Get MCP tools information.
	 *
	 * Consolidates common MCP tools retrieval logic.
	 *
	 * @since 1.0.0
	 *
	 * @param ToolRegistry $tool_registry The tool registry instance.
	 * @return array{tools_count: int, tools: array<int, array{name: string, description: string, class: string}>} Tools information with details.
	 */
	protected function get_mcp_tools_info( ToolRegistry $tool_registry ): array {
		$tools       = $tool_registry->get_tools();
		$tools_count = count( $tools );

		$tool_details = array();
		foreach ( $tools as $name => $tool ) {
			$tool_details[] = array(
				'name'        => $name,
				'description' => $tool->get_description(),
				'class'       => get_class( $tool ),
			);
		}

		return array(
			'tools_count' => $tools_count,
			'tools'       => $tool_details,
		);
	}

	/**
	 * Get analysis of recent errors from WordPress debug log.
	 *
	 * Consolidates common error log retrieval logic.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> Array of recent error messages.
	 */
	protected function get_recent_errors_from_log(): array {
		$debug_log = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $debug_log ) || ! is_readable( $debug_log ) ) {
			return array( 'Debug log not found or not readable' );
		}

		$handle = fopen( $debug_log, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return array();
		}

		fseek( $handle, -5000, SEEK_END ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fseek
		$content = fread( $handle, 5000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( false === $content ) {
			return array();
		}

		$lines  = explode( "\n", $content );
		$lines  = array_slice( $lines, -50 );
		$errors = array();

		foreach ( $lines as $line ) {
			if ( ! empty( $line ) && ( strpos( $line, 'Error' ) !== false || strpos( $line, 'Warning' ) !== false ) ) {
				$errors[] = $line;
			}
		}

		return array_slice( $errors, -20 );
	}
}
