<?php
/**
 * Settings Service
 *
 * Manages user settings including encrypted API key storage and preferences.
 *
 * @package WyvernCSS\Settings
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WP_Error;

/**
 * Class Settings_Service
 *
 * Handles user-specific settings with support for encrypted storage
 * of sensitive data like API keys.
 */
class Settings_Service {

	/**
	 * Encryption cipher method
	 */
	private const CIPHER_METHOD = 'aes-256-gcm';

	/**
	 * Key derivation iterations
	 */
	private const PBKDF2_ITERATIONS = 10000;

	/**
	 * Table name for user settings
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'wyverncss_user_settings';
	}

	/**
	 * Save OpenRouter API key
	 *
	 * Encrypts and stores the API key securely.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $api_key OpenRouter API key.
	 * @return bool True on success, false on failure.
	 */
	public function save_api_key( int $user_id, string $api_key ): bool {
		// Validate API key format.
		if ( ! $this->validate_api_key_format( $api_key ) ) {
			return false;
		}

		// Encrypt the API key.
		$encrypted = $this->encrypt( $api_key, $user_id );

		if ( false === $encrypted ) {
			return false;
		}

		return $this->set_setting( $user_id, 'openrouter_api_key', $encrypted, true );
	}

	/**
	 * Get OpenRouter API key
	 *
	 * Retrieves and decrypts the API key.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string|null API key or null if not set.
	 */
	public function get_api_key( int $user_id ): ?string {
		$encrypted = $this->get_setting( $user_id, 'openrouter_api_key' );

		if ( null === $encrypted ) {
			return null;
		}

		$decrypted = $this->decrypt( $encrypted, $user_id );

		if ( false === $decrypted ) {
			return null;
		}

		return $decrypted;
	}

	/**
	 * Delete API key
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_api_key( int $user_id ): bool {
		return $this->delete_setting( $user_id, 'openrouter_api_key' );
	}

	/**
	 * Verify API key is set and valid
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool True if API key exists and has valid format, false otherwise.
	 */
	public function has_valid_api_key( int $user_id ): bool {
		$api_key = $this->get_api_key( $user_id );

		if ( null === $api_key ) {
			return false;
		}

		return $this->validate_api_key_format( $api_key );
	}

	/**
	 * Save user preferences
	 *
	 * @param int                  $user_id     WordPress user ID.
	 * @param array<string, mixed> $preferences User preferences.
	 * @return bool True on success, false on failure.
	 */
	public function save_preferences( int $user_id, array $preferences ): bool {
		$json = wp_json_encode( $preferences );

		if ( false === $json ) {
			return false;
		}

		return $this->set_setting( $user_id, 'preferences', $json, false );
	}

	/**
	 * Get user preferences
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string, mixed> User preferences.
	 */
	public function get_preferences( int $user_id ): array {
		$json = $this->get_setting( $user_id, 'preferences' );

		if ( null === $json ) {
			return $this->get_default_preferences();
		}

		$preferences = json_decode( $json, true );

		if ( ! is_array( $preferences ) ) {
			return $this->get_default_preferences();
		}

		return array_merge( $this->get_default_preferences(), $preferences );
	}

	/**
	 * Get default preferences
	 *
	 * @return array<string, mixed> Default user preferences.
	 */
	private function get_default_preferences(): array {
		return array(
			'default_model'           => 'anthropic/claude-3.5-sonnet',
			'max_context_messages'    => 20,
			'show_tool_executions'    => true,
			'auto_save_conversations' => true,
		);
	}

	/**
	 * Set a user setting
	 *
	 * @param int    $user_id       WordPress user ID.
	 * @param string $setting_key   Setting key.
	 * @param string $setting_value Setting value.
	 * @param bool   $encrypted     Whether the value is encrypted.
	 * @return bool True on success, false on failure.
	 */
	private function set_setting( int $user_id, string $setting_key, string $setting_value, bool $encrypted ): bool {
		global $wpdb;

		$result = $wpdb->replace(
			$this->table_name,
			array(
				'user_id'       => $user_id,
				'setting_key'   => $setting_key,
				'setting_value' => $setting_value,
				'encrypted'     => $encrypted ? 1 : 0,
			),
			array( '%d', '%s', '%s', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get a user setting
	 *
	 * @param int    $user_id     WordPress user ID.
	 * @param string $setting_key Setting key.
	 * @return string|null Setting value or null if not found.
	 */
	private function get_setting( int $user_id, string $setting_key ): ?string {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT setting_value FROM %i WHERE user_id = %d AND setting_key = %s',
				$this->table_name,
				$user_id,
				$setting_key
			)
		);

		return null !== $value ? $value : null;
	}

	/**
	 * Delete a user setting
	 *
	 * @param int    $user_id     WordPress user ID.
	 * @param string $setting_key Setting key.
	 * @return bool True on success, false on failure.
	 */
	private function delete_setting( int $user_id, string $setting_key ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table_name,
			array(
				'user_id'     => $user_id,
				'setting_key' => $setting_key,
			),
			array( '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Encrypt sensitive data
	 *
	 * Uses AES-256-GCM encryption with WordPress salts + user-specific derivation.
	 *
	 * @param string $data    Data to encrypt.
	 * @param int    $user_id User ID for key derivation.
	 * @return string|false Base64-encoded encrypted data or false on failure.
	 */
	private function encrypt( string $data, int $user_id ) {
		// Derive encryption key.
		$key = $this->derive_key( $user_id );

		if ( false === $key ) {
			return false;
		}

		// Generate random IV (16 bytes for AES-256-GCM).
		$iv = random_bytes( 16 );

		$tag = '';

		// Encrypt using AES-256-GCM.
		$ciphertext = openssl_encrypt(
			$data,
			self::CIPHER_METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( false === $ciphertext ) {
			return false;
		}

		// Return: base64(IV + tag + ciphertext).
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding encrypted data for storage
		return base64_encode( $iv . $tag . $ciphertext );
	}

	/**
	 * Decrypt sensitive data
	 *
	 * @param string $encrypted_data Base64-encoded encrypted data.
	 * @param int    $user_id        User ID for key derivation.
	 * @return string|false Decrypted data or false on failure.
	 */
	private function decrypt( string $encrypted_data, int $user_id ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding encrypted data for decryption
		$decoded = base64_decode( $encrypted_data, true );

		if ( false === $decoded || strlen( $decoded ) < 32 ) {
			return false;
		}

		// Extract components: IV (16 bytes) + tag (16 bytes) + ciphertext.
		$iv         = substr( $decoded, 0, 16 );
		$tag        = substr( $decoded, 16, 16 );
		$ciphertext = substr( $decoded, 32 );

		// Derive decryption key.
		$key = $this->derive_key( $user_id );

		if ( false === $key ) {
			return false;
		}

		// Decrypt.
		$plaintext = openssl_decrypt(
			$ciphertext,
			self::CIPHER_METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		return false !== $plaintext ? $plaintext : false;
	}

	/**
	 * Derive encryption key from WordPress salts and user ID
	 *
	 * @param int $user_id User ID.
	 * @return string|false 32-byte encryption key or false on failure.
	 */
	private function derive_key( int $user_id ) {
		// Check WordPress salts are defined.
		if ( ! defined( 'AUTH_KEY' ) || ! defined( 'SECURE_AUTH_KEY' ) ) {
			return false;
		}

		// Combine WordPress salts for base key material.
		$base_key = AUTH_KEY . SECURE_AUTH_KEY;

		// Use user ID as salt for key derivation.
		$salt = 'user_' . $user_id;

		// Derive key using PBKDF2.
		$key = hash_pbkdf2(
			'sha256',
			$base_key,
			$salt,
			self::PBKDF2_ITERATIONS,
			32,
			true
		);

		// hash_pbkdf2 with raw_output=true always returns a string.
		return $key;
	}

	/**
	 * Validate OpenRouter API key format
	 *
	 * OpenRouter keys should start with 'sk-or-v1-' followed by 64 alphanumeric characters.
	 *
	 * @param string $api_key API key to validate.
	 * @return bool True if valid format, false otherwise.
	 */
	private function validate_api_key_format( string $api_key ): bool {
		return (bool) preg_match( '/^sk-or-v1-[a-zA-Z0-9]{64,}$/', $api_key );
	}

	/**
	 * Get all settings for a user
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string, mixed> All user settings (excluding encrypted values).
	 */
	public function get_all_settings( int $user_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT setting_key, setting_value, encrypted FROM %i WHERE user_id = %d',
				$this->table_name,
				$user_id
			),
			ARRAY_A
		);

		if ( ! $results ) {
			return array();
		}

		$settings = array();

		foreach ( $results as $row ) {
			// Don't expose encrypted values directly.
			if ( 1 === (int) $row['encrypted'] ) {
				$settings[ $row['setting_key'] ] = '[ENCRYPTED]';
			} else {
				$settings[ $row['setting_key'] ] = $row['setting_value'];
			}
		}

		return $settings;
	}

	/**
	 * Check if table exists
	 *
	 * @return bool True if table exists, false otherwise.
	 */
	public function table_exists(): bool {
		global $wpdb;

		$table = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $this->table_name )
			)
		);

		return $table === $this->table_name;
	}
}
