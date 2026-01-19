<?php
/**
 * CSS Generator
 *
 * Main orchestrator for CSS generation using pattern library and AI proxy.
 *
 * @package WyvernCSS
 * @since 1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

namespace WyvernCSS\Generator;
use WyvernCSS\AI\CostTracker;
use WyvernCSS\AI\ModelSelector;
use WyvernCSS\AI\PromptBuilder;
use WyvernCSS\AI\ResponseParser;
use WyvernCSS\Patterns\PatternLibrary;
use WyvernCSS\Freemius\Freemius_Integration;
use WP_Error;

/**
 * Class CSSGenerator
 *
 * Coordinates CSS generation through pattern matching and AI proxy fallback.
 * All AI requests go through the WyvernCSS cloud proxy for both free and premium tiers.
 */
class CSSGenerator {

	/**
	 * Pattern library instance
	 *
	 * @var PatternLibrary
	 */
	private PatternLibrary $pattern_library;

	/**
	 * Model selector
	 *
	 * @var ModelSelector
	 */
	private ModelSelector $model_selector;

	/**
	 * Prompt builder
	 *
	 * @var PromptBuilder
	 */
	private PromptBuilder $prompt_builder;

	/**
	 * Response parser
	 *
	 * Reserved for future use when direct AI integration (without proxy) is needed.
	 * Currently unused as the proxy returns pre-parsed CSS.
	 *
	 * @var ResponseParser
	 * @phpstan-ignore-next-line
	 */
	private ResponseParser $response_parser;

	/**
	 * Cost tracker
	 *
	 * @var CostTracker
	 */
	private CostTracker $cost_tracker;

	/**
	 * Response cache
	 *
	 * @var ResponseCache
	 */
	private ResponseCache $cache;

	/**
	 * Freemius Integration instance.
	 *
	 * @var Freemius_Integration
	 */
	private Freemius_Integration $freemius;

	/**
	 * Minimum confidence for pattern matching
	 *
	 * @var int
	 */
	private const PATTERN_CONFIDENCE_THRESHOLD = 70;

	/**
	 * Cloud proxy URL (Cloudflare Worker)
	 *
	 * @var string
	 */
	private const PROXY_URL = 'https://wyvernpress-proxy.feaw-account.workers.dev/v1/generate';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->pattern_library = new PatternLibrary();
		$this->model_selector  = new ModelSelector();
		$this->prompt_builder  = new PromptBuilder();
		$this->response_parser = new ResponseParser();
		$this->cost_tracker    = new CostTracker();
		$this->cache           = new ResponseCache();
		$this->freemius        = Freemius_Integration::get_instance();
	}

	/**
	 * Generate CSS for user request
	 *
	 * @param string               $prompt User's natural language request.
	 * @param array<string, mixed> $element_context Element context data.
	 * @param array<string, mixed> $options Generation options (user_tier, prefer_speed, etc.).
	 *
	 * @return array<string, mixed>|WP_Error Generation result or error.
	 */
	public function generate( string $prompt, array $element_context, array $options = array() ) {
		// 1. Try pattern library first (zero cost, instant).
		$pattern_result = $this->pattern_library->match( $prompt );

		if ( $pattern_result['confidence'] >= self::PATTERN_CONFIDENCE_THRESHOLD ) {
			return array(
				'css'              => $pattern_result['css'],
				'source'           => 'pattern',
				'confidence'       => $pattern_result['confidence'],
				'matched_patterns' => $pattern_result['matched_patterns'] ?? array(),
				'cost'             => 0.0,
				'tokens'           => 0,
			);
		}

		// 2. Check cache for AI-generated results.
		$cache_key = $this->cache->generate_key( $prompt, $element_context );
		$cached    = $this->cache->get( $cache_key );

		if ( is_array( $cached ) ) {
			$cached['source'] = 'cache';
			return $cached;
		}

		// 3. Fall back to AI generation via proxy.
		$ai_result = $this->generate_with_proxy( $prompt, $element_context, $options );

		if ( is_wp_error( $ai_result ) ) {
			// If AI fails, return pattern result with low confidence indicator.
			return array(
				'css'              => $pattern_result['css'],
				'source'           => 'pattern_fallback',
				'confidence'       => $pattern_result['confidence'],
				'matched_patterns' => $pattern_result['matched_patterns'] ?? array(),
				'cost'             => 0.0,
				'tokens'           => 0,
				'ai_error'         => $ai_result->get_error_message(),
			);
		}

		// 4. Cache the AI result.
		if ( is_array( $ai_result ) ) {
			$this->cache->set( $cache_key, $ai_result );
		}

		return $ai_result;
	}

	/**
	 * Generate CSS using the WyvernCSS cloud proxy
	 *
	 * Both free and premium tiers use the proxy. The proxy handles:
	 * - Rate limiting per tier
	 * - Model selection (cheaper models for free, better for premium)
	 * - API key management (users never see API keys)
	 *
	 * @param string               $prompt User request.
	 * @param array<string, mixed> $element_context Element context.
	 * @param array<string, mixed> $options Generation options.
	 *
	 * @return array<string, mixed>|WP_Error Generation result or error.
	 */
	private function generate_with_proxy( string $prompt, array $element_context, array $options ) {
		$user_id   = get_current_user_id();
		$user_tier = $options['user_tier'] ?? 'free';

		// Determine complexity for prompt building.
		$complexity = $this->model_selector->determine_complexity( $prompt );

		// Use pre-built messages if provided (for refinement), otherwise build new ones.
		$messages = $options['messages'] ?? $this->prompt_builder->build( $prompt, $element_context, $complexity );

		// Get AI configuration from Freemius (tier-specific models).
		$ai_config = $this->freemius->get_ai_config();

		// Get site license key (if premium).
		$license_key = $this->freemius->get_license_key();

		// Build proxy request.
		$request_body = array(
			'messages'    => $messages,
			'tier'        => $user_tier,
			'complexity'  => $complexity,
			'site_url'    => get_site_url(),
			'version'     => defined( 'WYVERNCSS_VERSION' ) ? WYVERNCSS_VERSION : '1.0.0',
			'ai_provider' => $ai_config['provider'] ?? 'ollama',
			'ai_model'    => $ai_config['model'] ?? null,
		);

		// Add license key for premium tier.
		if ( ! empty( $license_key ) ) {
			$request_body['license_key'] = $license_key;
		}

		$encoded_body = wp_json_encode( $request_body );
		if ( false === $encoded_body ) {
			return new WP_Error(
				'json_encode_error',
				__( 'Failed to encode request.', 'wyvern-ai-styling' )
			);
		}

		// Make proxy request.
		$response = wp_remote_post(
			$this->get_proxy_url(),
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'WyvernCSS/' . ( defined( 'WYVERNCSS_VERSION' ) ? WYVERNCSS_VERSION : '1.0.0' ),
				),
				'body'    => $encoded_body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'proxy_connection_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Could not connect to AI service: %s', 'wyvern-ai-styling' ),
					$response->get_error_message()
				),
				array( 'status' => 503 )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		// Handle rate limiting.
		if ( 429 === $status_code ) {
			return new WP_Error(
				'quota_exceeded',
				__( 'Daily AI request limit reached. Try again tomorrow! Premium plans coming soon.', 'wyvern-ai-styling' ),
				array(
					'status' => 429,
					'tier'   => $user_tier,
				)
			);
		}

		// Handle other errors.
		if ( $status_code >= 400 ) {
			$error_data = json_decode( $body, true );
			return new WP_Error(
				'proxy_error',
				$error_data['message'] ?? __( 'AI service error. Please try again.', 'wyvern-ai-styling' ),
				array( 'status' => $status_code )
			);
		}

		// Parse successful response.
		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $data['css'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response from AI service.', 'wyvern-ai-styling' )
			);
		}

		// Parse CSS - the proxy returns it as a JSON string that needs to be decoded.
		$css = $data['css'];
		if ( is_string( $css ) ) {
			// Try to parse as JSON (AI returns CSS properties as JSON object).
			$parsed_css = json_decode( $css, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $parsed_css ) ) {
				$css = $parsed_css;
			} else {
				// If not JSON, it might be raw CSS text - try to parse it.
				$css = $this->parse_raw_css( $css );
			}
		}

		if ( ! is_array( $css ) || empty( $css ) ) {
			return new WP_Error(
				'invalid_css_format',
				__( 'AI returned invalid CSS format.', 'wyvern-ai-styling' )
			);
		}

		// Filter out unrequested properties (AI often adds background-color etc.).
		$css = $this->response_parser->filter_unrequested_properties( $css, $prompt );

		if ( empty( $css ) ) {
			return new WP_Error(
				'no_css_after_filter',
				__( 'No valid CSS properties for your request.', 'wyvern-ai-styling' )
			);
		}

		// Track usage locally.
		$usage = array(
			'prompt_tokens'     => $data['usage']['prompt_tokens'] ?? 0,
			'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
			'total_tokens'      => $data['usage']['total_tokens'] ?? 0,
		);

		$this->cost_tracker->log_usage(
			$user_id,
			$data['model'] ?? 'proxy',
			$usage,
			0.0, // Cost is handled by proxy.
			'css_generation_proxy'
		);

		return array(
			'css'        => $css,
			'source'     => 'ai',
			'model'      => $data['model'] ?? 'ai',
			'cost'       => 0.0, // Free for user, we pay.
			'tokens'     => $usage['total_tokens'],
			'complexity' => $complexity,
			'confidence' => 100,
		);
	}

	/**
	 * Refine existing CSS
	 *
	 * @param string               $original_request Original request.
	 * @param array<string, mixed> $current_css Current CSS properties.
	 * @param string               $refinement_request Refinement request.
	 * @param array<string, mixed> $options Options.
	 *
	 * @return array<string, mixed>|WP_Error Refined CSS or error.
	 */
	public function refine( string $original_request, array $current_css, string $refinement_request, array $options = array() ) {
		// Build refinement prompt with full context.
		$messages = $this->prompt_builder->build_refinement_prompt(
			$original_request,
			$current_css,
			$refinement_request
		);

		// Pass pre-built messages to proxy.
		$options['messages'] = $messages;

		$result = $this->generate_with_proxy(
			$refinement_request,
			array( 'current_css' => $current_css ),
			$options
		);

		// Update source to indicate this was a refinement.
		if ( is_array( $result ) && isset( $result['source'] ) && 'ai' === $result['source'] ) {
			$result['source'] = 'ai_refinement';
		}

		return $result;
	}

	/**
	 * Get the proxy URL
	 *
	 * Allows override via constant for development/testing.
	 *
	 * @return string Proxy URL.
	 */
	private function get_proxy_url(): string {
		if ( defined( 'WYVERNCSS_PROXY_URL' ) ) {
			return WYVERNCSS_PROXY_URL;
		}

		return self::PROXY_URL;
	}

	/**
	 * Parse raw CSS text into an associative array
	 *
	 * Handles CSS in the format: "color: blue; background-color: red;"
	 *
	 * @param string $css_text Raw CSS text.
	 * @return array<string, string> Parsed CSS properties.
	 */
	private function parse_raw_css( string $css_text ): array {
		$css = array();

		// Remove any wrapping braces and whitespace.
		$css_text = trim( $css_text, " \t\n\r\0\x0B{}" );

		// Split by semicolon.
		$declarations = explode( ';', $css_text );

		foreach ( $declarations as $declaration ) {
			$declaration = trim( $declaration );
			if ( empty( $declaration ) ) {
				continue;
			}

			// Split by first colon only (value might contain colons).
			$colon_pos = strpos( $declaration, ':' );
			if ( false === $colon_pos ) {
				continue;
			}

			$property = trim( substr( $declaration, 0, $colon_pos ) );
			$value    = trim( substr( $declaration, $colon_pos + 1 ) );

			if ( ! empty( $property ) && ! empty( $value ) ) {
				$css[ $property ] = $value;
			}
		}

		return $css;
	}

	/**
	 * Clear cache
	 *
	 * @return bool True on success.
	 */
	public function clear_cache(): bool {
		return $this->cache->clear();
	}
}
