<?php
/**
 * MCP Tool Base
 *
 * Provides shared functionality for MCP tool implementations including
 * schema validation, sanitization, and capability checks.
 *
 * @package WyvernCSS\MCP\Tools
 */

declare(strict_types=1);

namespace WyvernCSS\MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\MCP\Interfaces\MCP_Tool_Interface;
use WP_Error;

/**
 * Abstract base class for MCP tools.
 *
 * Implements common behaviors defined by MCP_Tool_Interface so concrete tools
 * can focus on business logic.
 */
abstract class MCP_Tool_Base implements MCP_Tool_Interface {

	/**
	 * Tool name.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Tool description.
	 *
	 * @var string
	 */
	protected string $description = '';

	/**
	 * Tool input schema definition.
	 *
	 * @var array<string, mixed>
	 */
	protected array $input_schema = array();

	/**
	 * Required WordPress capabilities.
	 *
	 * @var array<int, string>
	 */
	protected array $required_capabilities = array();

	/**
	 * Cache TTL in seconds.
	 *
	 * @var int
	 */
	protected int $cache_ttl = 0;

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema(): array {
		return $this->input_schema;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_required_capabilities(): array {
		return $this->required_capabilities;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_cache_ttl(): int {
		return $this->cache_ttl;
	}

		/**
		 * Validate passed parameters against the tool input schema.
		 *
		 * @param array<string, mixed> $params Parameters to validate.
		 * @return true|WP_Error
		 */
	public function validate_params( array $params ) {
		$schema = $this->get_input_schema();

		if ( empty( $schema ) ) {
			return new WP_Error(
				'invalid_tool_schema',
				__( 'Tool schema is not defined', 'wyvern-ai-styling' )
			);
		}

		// Validate required fields.
		if ( isset( $schema['required'] ) && is_array( $schema['required'] ) ) {
			foreach ( $schema['required'] as $required_field ) {
				if ( ! array_key_exists( $required_field, $params ) ) {
					return new WP_Error(
						'missing_required_field',
						sprintf(
							/* translators: %s: field name */
							__( 'Required field "%s" is missing', 'wyvern-ai-styling' ),
							$required_field
						),
						array( 'field' => $required_field )
					);
				}
			}
		}

		// Validate provided parameters against schema.
		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $params as $field => $value ) {
				if ( ! isset( $schema['properties'][ $field ] ) ) {
					return new WP_Error(
						'unknown_parameter',
						sprintf(
							/* translators: %s: field name */
							__( 'Unknown parameter "%s"', 'wyvern-ai-styling' ),
							$field
						),
						array( 'field' => $field )
					);
				}

				$field_schema = $schema['properties'][ $field ];
				$validation   = $this->validate_field( $value, $field_schema, $field );

				if ( is_wp_error( $validation ) ) {
					return $validation;
				}
			}
		}

		return true;
	}

	/**
	 * Validate a single field against schema rules.
	 *
	 * @param mixed                $value       Field value.
	 * @param array<string, mixed> $schema      Schema definition.
	 * @param string               $field_name  Field name for error messaging.
	 * @return true|WP_Error
	 */
	protected function validate_field( $value, array $schema, string $field_name ) {
		$type = $schema['type'] ?? null;

		if ( $type ) {
			$is_valid_type = match ( $type ) {
				'string'  => is_string( $value ),
				'integer' => is_int( $value ),
				'number'  => is_int( $value ) || is_float( $value ),
				'boolean' => is_bool( $value ),
				'array'   => is_array( $value ),
				'object'  => is_array( $value ) || is_object( $value ),
				default   => true,
			};

			if ( ! $is_valid_type ) {
				return new WP_Error(
					'invalid_type',
					sprintf(
						/* translators: 1: field name, 2: expected type */
						__( 'Field "%1$s" must be of type %2$s', 'wyvern-ai-styling' ),
						$field_name,
						$type
					),
					array(
						'field'         => $field_name,
						'expected_type' => $type,
					)
				);
			}
		}

		if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) ) {
			if ( ! in_array( $value, $schema['enum'], true ) ) {
				return new WP_Error(
					'invalid_enum_value',
					sprintf(
						/* translators: 1: field name, 2: allowed values */
						__( 'Field "%1$s" must be one of: %2$s', 'wyvern-ai-styling' ),
						$field_name,
						implode( ', ', $schema['enum'] )
					),
					array(
						'field'          => $field_name,
						'allowed_values' => $schema['enum'],
					)
				);
			}
		}

		if ( 'string' === $type ) {
			if ( isset( $schema['minLength'] ) && strlen( $value ) < (int) $schema['minLength'] ) {
				return new WP_Error(
					'string_too_short',
					sprintf(
						/* translators: 1: field name, 2: minimum length */
						__( 'Field "%1$s" must be at least %2$d characters', 'wyvern-ai-styling' ),
						$field_name,
						(int) $schema['minLength']
					),
					array( 'field' => $field_name )
				);
			}

			if ( isset( $schema['maxLength'] ) && strlen( $value ) > (int) $schema['maxLength'] ) {
				return new WP_Error(
					'string_too_long',
					sprintf(
						/* translators: 1: field name, 2: max length */
						__( 'Field "%1$s" must be no more than %2$d characters', 'wyvern-ai-styling' ),
						$field_name,
						(int) $schema['maxLength']
					),
					array( 'field' => $field_name )
				);
			}

			if ( isset( $schema['pattern'] ) && ! preg_match( $schema['pattern'], (string) $value ) ) {
				return new WP_Error(
					'pattern_mismatch',
					sprintf(
						/* translators: %s: field name */
						__( 'Field "%s" does not match required pattern', 'wyvern-ai-styling' ),
						$field_name
					),
					array( 'field' => $field_name )
				);
			}
		}

		if ( in_array( $type, array( 'integer', 'number' ), true ) ) {
			if ( isset( $schema['minimum'] ) && $value < $schema['minimum'] ) {
				return new WP_Error(
					'value_too_small',
					sprintf(
						/* translators: 1: field name, 2: minimum value */
						__( 'Field "%1$s" must be at least %2$s', 'wyvern-ai-styling' ),
						$field_name,
						$schema['minimum']
					),
					array( 'field' => $field_name )
				);
			}

			if ( isset( $schema['maximum'] ) && $value > $schema['maximum'] ) {
				return new WP_Error(
					'value_too_large',
					sprintf(
						/* translators: 1: field name, 2: maximum value */
						__( 'Field "%1$s" must be no more than %2$s', 'wyvern-ai-styling' ),
						$field_name,
						$schema['maximum']
					),
					array( 'field' => $field_name )
				);
			}
		}

		return true;
	}

	/**
	 * Check current user capabilities.
	 *
	 * @return true|WP_Error
	 */
	protected function check_capabilities() {
		$required_caps = $this->get_required_capabilities();

		if ( empty( $required_caps ) ) {
			return true;
		}

		foreach ( $required_caps as $capability ) {
			if ( ! current_user_can( $capability ) ) {
				return new WP_Error(
					'insufficient_permissions',
					sprintf(
						/* translators: %s: capability name */
						__( 'You do not have permission to perform this action. Required capability: %s', 'wyvern-ai-styling' ),
						$capability
					),
					array( 'required_capability' => $capability )
				);
			}
		}

		return true;
	}

	/**
	 * Sanitize parameters according to schema.
	 *
	 * @param array<string, mixed> $params Parameters to sanitize.
	 * @return array<string, mixed>
	 */
	protected function sanitize_params( array $params ): array {
		$schema     = $this->get_input_schema();
		$sanitized  = array();
		$properties = $schema['properties'] ?? array();

		foreach ( $params as $field => $value ) {
			$field_schema        = $properties[ $field ] ?? array();
			$sanitized[ $field ] = $this->sanitize_field( $value, $field_schema );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a single field.
	 *
	 * @param mixed                $value  Field value.
	 * @param array<string, mixed> $schema Field schema.
	 * @return mixed
	 */
	protected function sanitize_field( $value, array $schema ) {
		$type = $schema['type'] ?? 'string';

		return match ( $type ) {
			'string'  => sanitize_text_field( $value ),
			'integer' => absint( $value ),
			'number'  => (float) $value,
			'boolean' => (bool) $value,
			'array'   => is_array( $value )
				? array_map(
					fn( $item ) => $this->sanitize_field(
						$item,
						array( 'type' => $schema['items']['type'] ?? 'string' )
					),
					$value
				)
				: array(),
			default   => $value,
		};
	}

	/**
	 * Execute the tool. Concrete classes must implement this.
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	abstract public function execute( array $params );
}
