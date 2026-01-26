<?php
/**
 * HMAC Signer Class
 *
 * Provides HMAC-SHA256 signature generation and request header preparation
 * for secure API communication with WyvernCSS services.
 *
 * @package WyvernCSS
 * @subpackage Security
 * @since 2.0.0
 */

declare(strict_types=1);

namespace WyvernCSS\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * HMAC Signer Class
 *
 * Utility class for generating HMAC-SHA256 signatures and preparing
 * authenticated request headers for WyvernCSS API requests.
 *
 * Used by MCP_Client for secure API communication.
 *
 * @since 2.0.0
 */
class HMAC_Signer {

	/**
	 * API secret for HMAC signing.
	 *
	 * @var string
	 */
	private string $api_secret;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $api_secret API secret key for HMAC signing.
	 */
	public function __construct( string $api_secret ) {
		$this->api_secret = $api_secret;
	}

	/**
	 * Generate HMAC-SHA256 signature for request.
	 *
	 * Creates a canonical string from timestamp, site URL, and request body,
	 * then generates a base64-encoded HMAC-SHA256 signature using the API secret.
	 *
	 * Canonical string format: "{timestamp}\n{site_url}\n{body_json}"
	 *
	 * @since 2.0.0
	 *
	 * @param int    $timestamp Unix timestamp.
	 * @param string $site_url  Site URL.
	 * @param string $body_json JSON-encoded request body.
	 * @return string Base64-encoded HMAC signature.
	 */
	public function generate_signature( int $timestamp, string $site_url, string $body_json ): string {
		$canonical_string = $timestamp . "\n" . $site_url . "\n" . $body_json;
		$hmac             = hash_hmac( 'sha256', $canonical_string, $this->api_secret, true );
		return base64_encode( $hmac ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Used for HMAC signature encoding, not obfuscation
	}

	/**
	 * Prepare authenticated request headers.
	 *
	 * Generates standard WyvernCSS API headers including:
	 * - Content-Type: application/json
	 * - X-WyvernCSS-Signature: HMAC signature with 'sha256=' prefix
	 * - X-WyvernCSS-Timestamp: Unix timestamp
	 * - X-WyvernCSS-Site: Site URL
	 * - User-Agent: WyvernCSS-WordPress/{version}
	 *
	 * @since 2.0.0
	 *
	 * @param string $signature HMAC signature (without 'sha256=' prefix).
	 * @param int    $timestamp Unix timestamp.
	 * @param string $site_url  Site URL.
	 * @param string $version   Plugin version (default: '2.0.0').
	 * @return array<string, string> Request headers array.
	 */
	public function prepare_headers( string $signature, int $timestamp, string $site_url, string $version = '2.0.0' ): array {
		return array(
			'Content-Type'          => 'application/json',
			'X-WyvernCSS-Signature' => 'sha256=' . $signature,
			'X-WyvernCSS-Timestamp' => (string) $timestamp,
			'X-WyvernCSS-Site'      => $site_url,
			'User-Agent'            => 'WyvernCSS-WordPress/' . $version,
		);
	}

	/**
	 * Generate signature and prepare headers in one call.
	 *
	 * Convenience method that combines signature generation and header preparation.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $timestamp Unix timestamp.
	 * @param string $site_url  Site URL.
	 * @param string $body_json JSON-encoded request body.
	 * @param string $version   Plugin version (default: '2.0.0').
	 * @return array<string, string> Request headers array with signature.
	 */
	public function sign_request( int $timestamp, string $site_url, string $body_json, string $version = '2.0.0' ): array {
		$signature = $this->generate_signature( $timestamp, $site_url, $body_json );
		return $this->prepare_headers( $signature, $timestamp, $site_url, $version );
	}
}
