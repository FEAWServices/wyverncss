<?php
/**
 * PSR-4 Autoloader for WyvernCSS plugin.
 *
 * Maps the WyvernCSS namespace to the includes/ directory.
 * Converts PascalCase class names to kebab-case file names.
 *
 * @package WyvernCSS
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Manually require traits that don't follow the _Trait naming convention.
require_once __DIR__ . '/API/REST/trait-controller-helpers.php';
require_once __DIR__ . '/API/REST/trait-rest-controller-helpers.php';
require_once __DIR__ . '/API/REST/Traits/trait-cloud-service-proxy.php';
require_once __DIR__ . '/MCP/Tools/Content/trait-block-operations.php';

spl_autoload_register(
	function ( string $class_name ): void {
		// Project namespace prefix.
		$prefix = 'WyvernCSS\\';

		// Base directory for the namespace prefix.
		$base_dir = __DIR__ . '/';

		// Does the class use the namespace prefix?
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			// No, move to the next registered autoloader.
			return;
		}

		// Get the relative class name.
		$relative_class = substr( $class_name, $len );

		// Replace namespace separators with directory separators.
		$relative_class = str_replace( '\\', '/', $relative_class );

		// Convert PascalCase class names to kebab-case file names.
		// Example: JSONRPCParser -> jsonrpc-parser.php, MCP_Tool_Base -> mcp-tool-base.php.
		$parts      = explode( '/', $relative_class );
		$class_file = array_pop( $parts );

		// Ensure class_file is a string.
		if ( ! is_string( $class_file ) || '' === $class_file ) {
			return;
		}

		// Handle interfaces separately (e.g., MCP_Tool_Interface -> interface-mcp-tool.php).
		$is_interface = str_ends_with( $class_file, 'Interface' ) || str_ends_with( $class_file, '_Interface' );

		// Replace underscores with hyphens (WordPress naming convention).
		$class_file = str_replace( '_', '-', $class_file );
		// Insert dash before uppercase that follows lowercase or digit.
		$class_file = (string) preg_replace( '/([a-z\d])([A-Z])/', '$1-$2', $class_file );
		// Insert dash before uppercase followed by lowercase (handles consecutive capitals).
		$class_file = (string) preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1-$2', $class_file );
		$class_file = strtolower( $class_file );

		// Add appropriate prefix for WordPress naming standards.
		if ( $is_interface ) {
			// Remove '-interface' suffix and add 'interface-' prefix.
			$class_file = 'interface-' . str_replace( '-interface', '', $class_file );
		} else {
			// Add 'class-' prefix for regular classes.
			$class_file = 'class-' . $class_file;
		}
		$parts[] = $class_file;

		$file = $base_dir . implode( '/', $parts ) . '.php';

		// If the file exists, require it (use require_once to prevent duplicate class declarations).
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
