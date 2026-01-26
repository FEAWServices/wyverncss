<?php
/**
 * MCP Tool Registry
 *
 * Central registry for all MCP tools. Handles tool registration, discovery,
 * validation, and execution.
 *
 * @package WyvernCSS\MCP
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\MCP\Interfaces\MCP_Tool_Interface;
use WP_Error;

/**
 * Class ToolRegistry
 *
 * Manages the lifecycle of MCP tools including registration, discovery,
 * parameter validation, and execution routing.
 */
class ToolRegistry {

	/**
	 * Registered tools
	 *
	 * @var array<string, MCP_Tool_Interface>
	 */
	private array $tools = array();

	/**
	 * Whether tools have been auto-discovered
	 *
	 * @var bool
	 */
	private bool $auto_discovered = false;

	/**
	 * Whether auto-discovery is enabled
	 *
	 * @var bool
	 */
	private bool $auto_discovery_enabled = true;

	/**
	 * Constructor
	 *
	 * @param bool $enable_auto_discovery Whether to enable automatic tool discovery (default: true).
	 */
	public function __construct( bool $enable_auto_discovery = true ) {
		$this->auto_discovery_enabled = $enable_auto_discovery;
	}

	/**
	 * Register a new MCP tool
	 *
	 * Validates the tool and adds it to the registry if valid.
	 *
	 * @param mixed $tool Tool instance to register.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function register_tool( $tool ) {
		// Validate tool implements required interface.
		if ( ! $tool instanceof MCP_Tool_Interface ) {
			return new WP_Error(
				'invalid_tool_interface',
				__( 'Tool must implement MCP_Tool_Interface', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		// Get tool metadata.
		$name        = $tool->get_name();
		$description = $tool->get_description();
		$schema      = $tool->get_input_schema();

		// Validate tool name is not empty.
		if ( empty( $name ) ) {
			return new WP_Error(
				'invalid_tool_name',
				__( 'Tool name cannot be empty', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		// Validate tool name follows wp_* pattern.
		if ( ! preg_match( '/^wp_[a-z_]+$/', $name ) ) {
			return new WP_Error(
				'invalid_tool_name_pattern',
				sprintf(
					/* translators: %s: tool name pattern */
					__( 'Tool name must follow the pattern: %s', 'wyverncss' ),
					'wp_[a-z_]+'
				),
				array( 'status' => 400 )
			);
		}

		// Validate description is not empty.
		if ( empty( $description ) ) {
			return new WP_Error(
				'missing_tool_description',
				__( 'Tool must have a description', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		// Validate schema is not empty.
		if ( empty( $schema ) || ! is_array( $schema ) ) {
			return new WP_Error(
				'invalid_tool_schema',
				__( 'Tool must have a valid input schema', 'wyverncss' ),
				array( 'status' => 400 )
			);
		}

		// Check for duplicate registration.
		if ( isset( $this->tools[ $name ] ) ) {
			return new WP_Error(
				'tool_already_registered',
				sprintf(
					/* translators: %s: tool name */
					__( 'Tool "%s" is already registered', 'wyverncss' ),
					$name
				),
				array( 'status' => 400 )
			);
		}

		// Register the tool.
		$this->tools[ $name ] = $tool;

		return true;
	}

	/**
	 * Check if a tool is registered
	 *
	 * @param string $name Tool name.
	 * @return bool True if tool exists, false otherwise.
	 */
	public function has_tool( string $name ): bool {
		// Ensure tools are discovered before checking.
		if ( $this->auto_discovery_enabled && ! $this->auto_discovered ) {
			$this->auto_discover_tools();
		}

		return isset( $this->tools[ $name ] );
	}

	/**
	 * Auto-discover and register all built-in MCP tools
	 *
	 * Scans the Tools directory and registers all tool classes.
	 *
	 * @return void
	 */
	public function auto_discover_tools(): void {
		// Only run once.
		if ( $this->auto_discovered ) {
			return;
		}

		$this->auto_discovered = true;

		// Define tool classes to register.
		$tool_classes = array(
			// Content tools - Basic CRUD.
			'WyvernCSS\MCP\Tools\Content\GetPostsTool',
			'WyvernCSS\MCP\Tools\Content\GetPostTool',
			'WyvernCSS\MCP\Tools\Content\CreatePostTool',
			'WyvernCSS\MCP\Tools\Content\UpdatePostTool',
			'WyvernCSS\MCP\Tools\Content\DeletePostTool',
			// Content tools - Block editing (Gutenberg).
			'WyvernCSS\MCP\Tools\Content\GetBlocksTool',
			'WyvernCSS\MCP\Tools\Content\UpdateBlocksTool',
			'WyvernCSS\MCP\Tools\Content\InsertBlockTool',
			'WyvernCSS\MCP\Tools\Content\DeleteBlockTool',
			'WyvernCSS\MCP\Tools\Content\ReplaceBlockTool',
			'WyvernCSS\MCP\Tools\Content\UpdatePostMetaTool',
			// Media tools.
			'WyvernCSS\MCP\Tools\Media\GetMediaTool',
			'WyvernCSS\MCP\Tools\Media\UploadMediaTool',
		);

		foreach ( $tool_classes as $class_name ) {
			// Check if class exists.
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			try {
				// Instantiate the tool.
				$tool = new $class_name();

				// Register the tool.
				$result = $this->register_tool( $tool );

				// Log errors if registration fails.
				if ( is_wp_error( $result ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log(
							sprintf(
								'Failed to register MCP tool %s: %s',
								$class_name,
								$result->get_error_message()
							)
						);
					}
				}
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log(
						sprintf(
							'Exception while instantiating MCP tool %s: %s',
							$class_name,
							$e->getMessage()
						)
					);
				}
			}
		}
	}

	/**
	 * Get all registered tools
	 *
	 * @return array<string, MCP_Tool_Interface> Associative array of tools keyed by name.
	 */
	public function get_tools(): array {
		// Ensure tools are discovered before returning.
		if ( $this->auto_discovery_enabled && ! $this->auto_discovered ) {
			$this->auto_discover_tools();
		}

		return $this->tools;
	}

	/**
	 * Get a specific tool by name
	 *
	 * @param string $name Tool name.
	 * @return MCP_Tool_Interface|null Tool instance or null if not found.
	 */
	public function get_tool( string $name ): ?MCP_Tool_Interface {
		// Ensure tools are discovered before getting.
		if ( $this->auto_discovery_enabled && ! $this->auto_discovered ) {
			$this->auto_discover_tools();
		}

		return $this->tools[ $name ] ?? null;
	}

	/**
	 * Get complete metadata for a tool
	 *
	 * @param string $name Tool name.
	 * @return array<string, mixed> Tool metadata including name, description, schema, and capabilities.
	 */
	public function get_tool_metadata( string $name ): array {
		$tool = $this->get_tool( $name );

		if ( ! $tool ) {
			return array();
		}

		return array(
			'name'                 => $tool->get_name(),
			'description'          => $tool->get_description(),
			'inputSchema'          => $tool->get_input_schema(),
			'requiredCapabilities' => $tool->get_required_capabilities(),
		);
	}

	/**
	 * Get tools list formatted for API endpoint
	 *
	 * @return array<int, array<string, mixed>> Array of tool metadata for all registered tools.
	 */
	public function get_tools_list(): array {
		$tools_list = array();

		foreach ( $this->tools as $tool ) {
			$tools_list[] = array(
				'name'        => $tool->get_name(),
				'description' => $tool->get_description(),
				'inputSchema' => $tool->get_input_schema(),
			);
		}

		return $tools_list;
	}

	/**
	 * List all registered tools (alias for get_tools_list)
	 *
	 * @return array<int, array<string, mixed>> Array of tool metadata for all registered tools.
	 */
	public function list_tools(): array {
		return $this->get_tools_list();
	}

	/**
	 * Validate that a tool exists
	 *
	 * @param string $name Tool name.
	 * @return bool True if tool exists, false otherwise.
	 */
	public function validate_tool_exists( string $name ): bool {
		return $this->has_tool( $name );
	}

	/**
	 * Validate tool parameters against schema
	 *
	 * @param string               $name   Tool name.
	 * @param array<string, mixed> $params Parameters to validate.
	 * @return true|WP_Error True if valid, WP_Error on validation failure.
	 */
	public function validate_tool_params( string $name, array $params ) {
		$tool = $this->get_tool( $name );

		if ( ! $tool ) {
			return new WP_Error(
				'tool_not_found',
				sprintf(
					/* translators: %s: tool name */
					__( 'Tool "%s" not found', 'wyverncss' ),
					$name
				)
			);
		}

		// Delegate validation to the tool itself (DRY principle).
		// This avoids code duplication with MCP_Tool_Base::validate_params().
		return $tool->validate_params( $params );
	}

	/**
	 * Get tool input schema
	 *
	 * @param string $name Tool name.
	 * @return array<string, mixed> Tool input schema.
	 */
	public function get_tool_schema( string $name ): array {
		$tool = $this->get_tool( $name );

		if ( ! $tool ) {
			return array();
		}

		return $tool->get_input_schema();
	}

	/**
	 * Get required capabilities for a tool
	 *
	 * @param string $name Tool name.
	 * @return array<int, string> Required capabilities.
	 */
	public function get_tool_capabilities( string $name ): array {
		$tool = $this->get_tool( $name );

		if ( ! $tool ) {
			return array();
		}

		return $tool->get_required_capabilities();
	}

	/**
	 * Get tool description
	 *
	 * @param string $name Tool name.
	 * @return string Tool description.
	 */
	public function get_tool_description( string $name ): string {
		$tool = $this->get_tool( $name );

		if ( ! $tool ) {
			return '';
		}

		return $tool->get_description();
	}

	/**
	 * Get cache TTL for a tool
	 *
	 * @param string $name Tool name.
	 * @return int Cache TTL in seconds.
	 */
	public function get_tool_cache_ttl( string $name ): int {
		$tool = $this->get_tool( $name );

		if ( ! $tool ) {
			return 0;
		}

		return $tool->get_cache_ttl();
	}

	/**
	 * Execute a tool with parameters
	 *
	 * @param string               $name   Tool name.
	 * @param array<string, mixed> $params Tool parameters.
	 * @return array<string, mixed>|WP_Error Tool execution result or error.
	 */
	public function call_tool( string $name, array $params ) {
		// Check if tool exists.
		$tool = $this->get_tool( $name );

		if ( ! $tool ) {
			return new WP_Error(
				'tool_not_found',
				sprintf(
					/* translators: %s: tool name */
					__( 'Tool "%s" not found', 'wyverncss' ),
					$name
				)
			);
		}

		// Validate parameters.
		$validation = $this->validate_tool_params( $name, $params );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Check capabilities.
		$required_capabilities = $tool->get_required_capabilities();

		foreach ( $required_capabilities as $capability ) {
			if ( ! current_user_can( $capability ) ) {
				return new WP_Error(
					'insufficient_permissions',
					sprintf(
						/* translators: %s: required capability */
						__( 'You do not have permission to perform this action. Required capability: %s', 'wyverncss' ),
						$capability
					),
					array( 'required_capability' => $capability )
				);
			}
		}

		// Execute the tool.
		$result = $tool->execute( $params );

		// If tool returned an error, propagate it.
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result;
	}

	/**
	 * Reset the registry (for testing purposes)
	 *
	 * Clears all registered tools and resets the auto-discovery flag.
	 * This method should only be used in test environments.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->tools           = array();
		$this->auto_discovered = false;
	}
}
