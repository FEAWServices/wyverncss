<?php
/**
 * MCP Tool Interface
 *
 * All MCP tools must implement this interface.
 * This interface defines the contract for MCP tools to ensure consistency.
 *
 * @package WyvernCSS\MCP\Interfaces
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\MCP\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WP_Error;

/**
 * Interface MCP_Tool_Interface
 *
 * Defines the contract that all MCP tools must follow.
 */
interface MCP_Tool_Interface {
	/**
	 * Get tool name (unique identifier)
	 *
	 * Tool names must follow the pattern: wp_[a-z_]+
	 * Example: wp_create_post, wp_get_posts
	 *
	 * @return string Tool name (e.g., 'wp_create_post')
	 */
	public function get_name(): string;

	/**
	 * Get tool description
	 *
	 * Human-readable description of what the tool does.
	 * This will be shown to AI models and in documentation.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string;

	/**
	 * Get input schema (JSON Schema format)
	 *
	 * Returns a JSON Schema definition that describes the parameters
	 * this tool accepts. Used for validation and documentation.
	 *
	 * @return array<string, mixed> JSON Schema definition.
	 */
	public function get_input_schema(): array;

	/**
	 * Get required WordPress capabilities
	 *
	 * Returns an array of WordPress capability strings that the current
	 * user must possess to execute this tool.
	 *
	 * @return array<int, string> Array of capability strings (e.g., ['edit_posts', 'publish_posts'])
	 */
	public function get_required_capabilities(): array;

	/**
	 * Validate tool parameters
	 *
	 * Validates the provided parameters against the tool's input schema.
	 * Should check:
	 * - Required fields are present
	 * - Types are correct
	 * - Values meet constraints (min/max, enum, pattern, etc.)
	 *
	 * @param array<string, mixed> $params Tool parameters to validate.
	 * @return true|WP_Error True if valid, WP_Error with details if invalid.
	 */
	public function validate_params( array $params );

	/**
	 * Execute the tool
	 *
	 * Performs the actual tool operation with the provided parameters.
	 * Parameters should already be validated before calling this method.
	 *
	 * @param array<string, mixed> $params Validated tool parameters.
	 * @return array<string, mixed>|WP_Error Tool execution result or error.
	 */
	public function execute( array $params );

	/**
	 * Get cache TTL for this tool's results
	 *
	 * Returns the number of seconds to cache this tool's results.
	 * Return 0 for no caching (e.g., for mutation operations).
	 *
	 * @return int Cache TTL in seconds (0 = no cache).
	 */
	public function get_cache_ttl(): int;
}
