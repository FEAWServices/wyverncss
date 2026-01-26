<?php
/**
 * Style Controller
 *
 * REST endpoint for pattern matching and CSS generation.
 *
 * @package WyvernCSS
 * @subpackage API
 */

declare(strict_types=1);

namespace WyvernCSS\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WyvernCSS\Generator\CSSGenerator;
use WyvernCSS\Generator\ContextExtractor;
use WyvernCSS\Generator\ElementTransformer;
use WyvernCSS\Generator\ContentDetector;
use WyvernCSS\Generator\BlockGenerator;
use WyvernCSS\Freemius\Freemius_Integration;
use WyvernCSS\Config\Tier_Config;
use WyvernCSS\Styles\Style_Memory;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Style Controller Class
 *
 * Handles POST /wyverncss/v1/style endpoint.
 *
 * Features:
 * - Pattern matching against library (60% of requests, instant, free)
 * - AI generation via cloud proxy (40% of requests)
 * - Rate limiting: Free 20/day, Premium unlimited
 * - Input validation and sanitization
 *
 * @since 1.0.0
 */
class StyleController extends RESTController {

	/**
	 * CSS Generator instance.
	 *
	 * @var CSSGenerator
	 */
	private CSSGenerator $css_generator;

	/**
	 * Context Extractor instance.
	 *
	 * @var ContextExtractor
	 */
	private ContextExtractor $context_extractor;

	/**
	 * Element Transformer instance.
	 *
	 * @var ElementTransformer
	 */
	private ElementTransformer $element_transformer;

	/**
	 * Content Detector instance.
	 *
	 * @var ContentDetector
	 */
	private ContentDetector $content_detector;

	/**
	 * Block Generator instance.
	 *
	 * @var BlockGenerator
	 */
	private BlockGenerator $block_generator;

	/**
	 * Freemius Integration instance.
	 *
	 * @var Freemius_Integration
	 */
	private Freemius_Integration $freemius;

	/**
	 * Style Memory instance.
	 *
	 * @var Style_Memory
	 */
	private Style_Memory $style_memory;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->context_extractor   = new ContextExtractor();
		$this->element_transformer = new ElementTransformer();
		$this->content_detector    = new ContentDetector();
		$this->block_generator     = new BlockGenerator();
		$this->freemius            = Freemius_Integration::get_instance();
		$this->style_memory        = new Style_Memory();

		// Initialize CSS Generator - uses cloud proxy for AI.
		$this->css_generator = new CSSGenerator();
	}

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		$endpoint_args = array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_style' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_endpoint_args(),
			),
			'schema' => array( $this, 'get_item_schema' ),
		);

		// Primary endpoint.
		register_rest_route(
			$this->namespace,
			'/style',
			$endpoint_args,
			true
		);

		// Backwards-compatible alias expected by integration tests.
		register_rest_route(
			$this->namespace,
			'/style/generate',
			$endpoint_args,
			true
		);

		// Status endpoint - check quota and service availability.
		register_rest_route(
			$this->namespace,
			'/style/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Get service status.
	 *
	 * Returns quota information and service availability.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response Status response.
	 */
	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		$user_id   = get_current_user_id();
		$tier      = $this->get_user_tier( $user_id );
		$quota     = $this->check_quota( $user_id, $tier );
		$ai_config = $this->freemius->get_ai_config();

		$response = new WP_REST_Response(
			array(
				'ai_available'    => true, // Always available via cloud proxy.
				'ai_provider'     => $ai_config['provider'] ?? 'wyverncss_cloud',
				'ai_model'        => $ai_config['model'] ?? null,
				'pattern_library' => true, // Always available.
				'tier'            => $tier,
				'tier_name'       => $this->freemius->get_plan(),
				'is_premium'      => $this->freemius->is_premium(),
				'is_trial'        => $this->freemius->is_trial(),
				'quota'           => array(
					'used'      => $quota['used'],
					'limit'     => $quota['limit'],
					'remaining' => $quota['remaining'],
					'reset_at'  => $quota['reset_at'],
					'unlimited' => $quota['limit'] < 0,
				),
			),
			200
		);

		// Add rate limit headers.
		$this->add_rate_limit_headers( $response, $quota );

		return $response;
	}

	/**
	 * Generate CSS styles from natural language prompt.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function generate_style( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$tier    = $this->get_user_tier( $user_id );

		// Get and sanitize prompt.
		$prompt = $this->sanitize_prompt( $request->get_param( 'prompt' ) );

		if ( empty( $prompt ) ) {
			return $this->error_response(
				'invalid_prompt',
				esc_html__( 'Prompt cannot be empty.', 'wyverncss' ),
				400
			);
		}

		// Check prompt length against tier limit.
		$tier_config     = Tier_Config::get_instance();
		$max_length      = $tier_config->get_prompt_max_length( $tier );
		$prompt_length   = strlen( $prompt );

		if ( $prompt_length > $max_length ) {
			return $this->error_response(
				'prompt_too_long',
				sprintf(
					/* translators: 1: Current prompt length, 2: Maximum allowed length */
					esc_html__( 'Prompt is too long (%1$d characters). Maximum allowed for your tier is %2$d characters.', 'wyverncss' ),
					$prompt_length,
					$max_length
				),
				400
			);
		}

		// Get element context if provided.
		$element_data    = $request->get_param( 'element_context' ) ?? array();
		$element_context = $this->context_extractor->extract( $element_data );

		// Get block context if provided (for Gutenberg integration).
		$block_context = $request->get_param( 'block_context' ) ?? array();

		// Check for content generation requests FIRST (before style).
		$content_detection = $this->content_detector->detect( $prompt, $block_context );

		if ( null !== $content_detection && true === $content_detection['detected'] ) {
			// Generate block specification.
			$block = $this->block_generator->generate(
				$content_detection['type'],
				$content_detection['params']
			);

			return $this->success_response(
				array(
					'generateContent' => array(
						'detected'               => true,
						'type'                   => $content_detection['type'],
						'action'                 => $content_detection['action'],
						'blockName'              => $content_detection['blockName'],
						'params'                 => $content_detection['params'],
						'warning'                => $content_detection['warning'] ?? null,
						'replacesCurrentElement' => $content_detection['replacesCurrentElement'],
						'block'                  => $block,
					),
				)
			);
		}

		// Check for element transformation requests.
		$transformation = $this->element_transformer->detect_transformation( $prompt, $element_context );

		// Check if complex CSS (hover, animations) is required.
		$requires_complex_css = $this->element_transformer->requires_complex_css( $prompt );
		$custom_class_name    = null;
		$stylesheet           = null;

		// Extract border-radius early (not hover-specific, applies to element).
		$border_radius = $this->element_transformer->extract_border_radius( $prompt );

		if ( $requires_complex_css ) {
			$custom_class_name = $this->element_transformer->generate_unique_class();
			$hover_bg_color    = $this->element_transformer->extract_hover_color( $prompt );
			$hover_text_color  = $this->element_transformer->extract_hover_text_color( $prompt );

			// Build stylesheet if we extracted any hover properties.
			if ( null !== $hover_bg_color || null !== $hover_text_color ) {
				$stylesheet = $this->build_hover_stylesheet(
					$custom_class_name,
					$hover_bg_color,
					$hover_text_color
				);
			}
		}

		// Use CSS Generator (handles patterns + AI proxy).
		$result = $this->css_generator->generate(
			$prompt,
			$element_context,
			array(
				'user_tier'    => $tier,
				'prefer_speed' => $request->get_param( 'prefer_speed' ) ?? false,
			)
		);

		if ( is_wp_error( $result ) ) {
			// Check if it's a quota error from the proxy.
			if ( 'quota_exceeded' === $result->get_error_code() ) {
				$quota = $this->check_quota( $user_id, $tier );
				return new WP_Error(
					'quota_exceeded',
					esc_html__( 'You\'ve used all your free AI requests for today. Try again tomorrow! Premium plans with unlimited requests coming soon.', 'wyverncss' ),
					array(
						'status' => 429,
						'usage'  => $this->build_usage_info( $tier, $quota['used'], $quota['limit'], $quota['reset_at'] ),
					)
				);
			}
			return $result;
		}

		// Only count successful AI requests against quota (patterns are free).
		// pattern_fallback = AI failed, fell back to patterns, so don't charge.
		if ( 'ai' === $result['source'] ) {
			// Check quota BEFORE incrementing (for free tier).
			if ( 'free' === $tier ) {
				$quota = $this->check_quota( $user_id, $tier );
				if ( ! $quota['allowed'] ) {
					return new WP_Error(
						'quota_exceeded',
						sprintf(
							/* translators: %d: Number of requests allowed per day */
							esc_html__( 'You\'ve used all %d free AI requests for today. Try again tomorrow! Premium plans coming soon.', 'wyverncss' ),
							$quota['limit']
						),
						array(
							'status' => 429,
							'usage'  => $this->build_usage_info( $tier, $quota['used'], $quota['limit'], $quota['reset_at'] ),
						)
					);
				}
			}

			// Increment usage counter after successful AI generation.
			$this->increment_usage( $user_id, $tier );
		}

		// Get current quota.
		$quota = $this->check_quota( $user_id, $tier );

		$css = $this->apply_prompt_css_enhancements( $result['css'], $prompt, $border_radius );

		// AI was required if AI was actually used (source is 'ai' or 'pattern_fallback'),
		// or if this is a cached AI result (source is 'cache' but has a model).
		$ai_was_required = in_array( $result['source'], array( 'ai', 'pattern_fallback' ), true )
			|| ( 'cache' === $result['source'] && ! empty( $result['model'] ) );

		$response_data = array(
			'requires_ai'      => $ai_was_required,
			'css'              => $css,
			'confidence'       => $result['confidence'],
			'matched_patterns' => $result['matched_patterns'] ?? array(),
			'source'           => $result['source'],
			'model'            => $result['model'] ?? null,
			'cost'             => $result['cost'] ?? 0,
			'tokens'           => $result['tokens'] ?? 0,
			'usage'            => $this->build_usage_info( $tier, $quota['used'], $quota['limit'], $quota['reset_at'] ),
		);

		// Pass through AI error if present (helps debugging when AI fails).
		if ( isset( $result['ai_error'] ) ) {
			$response_data['ai_error'] = $result['ai_error'];
		}

		$response_data = $this->add_upgrade_prompt_if_needed( $response_data, $quota, $tier );
		$response_data = $this->add_transformation_data( $response_data, $transformation, $custom_class_name, $stylesheet );

		// Learn from successful style generation (for premium users).
		if ( ! empty( $css ) && $user_id > 0 ) {
			$this->style_memory->learn( $user_id, $prompt, $css );
		}

		// Create response and add rate limit headers.
		$response = $this->success_response( $response_data );
		$this->add_rate_limit_headers( $response, $quota );

		return $response;
	}

	/**
	 * Build hover stylesheet for complex CSS.
	 *
	 * @param string      $class_name      Custom class name.
	 * @param string|null $hover_bg_color  Hover background color.
	 * @param string|null $hover_text_color Hover text color.
	 * @return string Stylesheet content.
	 */
	private function build_hover_stylesheet( string $class_name, ?string $hover_bg_color, ?string $hover_text_color ): string {
		$hover_rules           = array();
		$transition_properties = array();

		if ( null !== $hover_bg_color ) {
			$hover_rules[]           = sprintf( '  background-color: %s !important;', esc_attr( $hover_bg_color ) );
			$transition_properties[] = 'background-color';
		}

		if ( null !== $hover_text_color ) {
			$hover_rules[]           = sprintf( '  color: %s !important;', esc_attr( $hover_text_color ) );
			$transition_properties[] = 'color';
		}

		if ( ! empty( $transition_properties ) ) {
			$hover_rules[] = sprintf(
				'  transition: %s 0.2s ease;',
				implode( ', ', $transition_properties )
			);
		}

		$hover_rules_str = implode( "\n", $hover_rules );
		$class_escaped   = esc_attr( $class_name );

		$transition_rule = '';
		if ( ! empty( $transition_properties ) ) {
			$transition_rule = sprintf(
				"  transition: %s 0.2s ease;\n",
				implode( ', ', $transition_properties )
			);
		}

		// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found
		return sprintf(
			"/* Base transition for smooth hover */\n" .
			".%s,\n.%s .wp-block-button__link {\n%s}\n\n" .
			"/* Direct element hover */\n" .
			"button.%s:hover,\na.%s:hover {\n%s\n}\n\n" .
			"/* Gutenberg button inner link hover */\n" .
			".%s .wp-block-button__link:hover {\n%s\n}",
			$class_escaped,
			$class_escaped,
			$transition_rule,
			$class_escaped,
			$class_escaped,
			$hover_rules_str,
			$class_escaped,
			$hover_rules_str
		);
		// phpcs:enable
	}

	/**
	 * Get user's subscription tier.
	 *
	 * Uses Freemius Integration for license validation.
	 *
	 * @param int $user_id User ID to get tier for (unused but kept for compatibility).
	 * @return string User tier ('free' or 'premium').
	 */
	protected function get_user_tier( int $user_id ): string {
		// Check for constant override (development/testing).
		if ( defined( 'WYVERNCSS_TIER' ) ) {
			return WYVERNCSS_TIER;
		}

		if ( ! $user_id ) {
			return 'free';
		}

		// Use Freemius Integration (with caching).
		$plan = $this->freemius->get_plan();

		// Map trial to premium for feature access.
		if ( $this->freemius->is_trial() ) {
			return 'premium';
		}

		return $plan;
	}

	/**
	 * Check if user has quota remaining for their tier.
	 *
	 * Uses the parent RESTController's rate limiting system for consistency
	 * with the /usage endpoint. Rate limits are configured via Freemius Integration.
	 *
	 * @param int    $user_id User ID.
	 * @param string $tier    User's tier.
	 * @return array{allowed: bool, used: int, limit: int, remaining: int, reset_at: string}
	 */
	protected function check_quota( int $user_id, string $tier ): array {
		// Check if tier has unlimited requests via Freemius.
		if ( $this->freemius->is_unlimited() ) {
			return array(
				'allowed'   => true,
				'used'      => 0,
				'limit'     => -1, // Unlimited.
				'remaining' => -1,
				'reset_at'  => '',
			);
		}

		// Get tier-specific rate limit via Freemius.
		$limit = $this->freemius->get_rate_limit();

		// Use parent's rate limit system for consistency with /usage endpoint.
		$used      = $this->get_current_usage( $user_id );
		$remaining = max( 0, $limit - $used );

		return array(
			'allowed'   => $used < $limit,
			'used'      => $used,
			'limit'     => $limit,
			'remaining' => $remaining,
			'reset_at'  => $this->get_rate_limit_reset_time( $user_id ),
		);
	}

	/**
	 * Increment usage counter for user.
	 *
	 * Uses the parent RESTController's rate limiting system for consistency
	 * with the /usage endpoint. Only tracks usage for limited tiers.
	 *
	 * @param int    $user_id User ID.
	 * @param string $tier    User's tier.
	 * @return void
	 */
	protected function increment_usage( int $user_id, string $tier ): void {
		// Don't track usage for unlimited tiers.
		if ( $this->freemius->is_unlimited() ) {
			return;
		}

		// Use parent's rate limit increment for consistency.
		$this->increment_rate_limit( $user_id );
	}

	/**
	 * Apply prompt-based CSS enhancements.
	 *
	 * @param array<string, mixed> $css           CSS properties to enhance.
	 * @param string               $prompt        User prompt.
	 * @param string|null          $border_radius Border radius if detected.
	 * @return array<string, mixed> Enhanced CSS properties.
	 */
	protected function apply_prompt_css_enhancements( array $css, string $prompt, ?string $border_radius ): array {
		if ( null !== $border_radius ) {
			$css['border-radius'] = $border_radius;
		}

		// Only add background-color if user explicitly mentions "background".
		// Don't add it just because they mentioned a color (e.g., "border in blue").
		$prompt_lower     = strtolower( $prompt );
		$wants_background = strpos( $prompt_lower, 'background' ) !== false;

		if ( $wants_background ) {
			$base_color = $this->element_transformer->extract_base_color( $prompt );
			if ( null !== $base_color && ! isset( $css['background-color'] ) ) {
				$css['background-color'] = $base_color;
			}
		}

		$base_text_color = $this->element_transformer->extract_base_text_color( $prompt );
		if ( null !== $base_text_color && ! isset( $css['color'] ) ) {
			$css['color'] = $base_text_color;
		}

		return $css;
	}

	/**
	 * Add upgrade prompt to response data if needed.
	 *
	 * @param array<string, mixed> $response_data Response data to modify.
	 * @param array<string, mixed> $quota         Quota information.
	 * @param string               $tier          User's tier.
	 * @return array<string, mixed> Response data with upgrade prompt if applicable.
	 */
	protected function add_upgrade_prompt_if_needed( array $response_data, array $quota, string $tier ): array {
		// Only show upgrade prompt for free tier when approaching limit.
		if ( 'free' !== $tier || $quota['limit'] < 0 ) {
			return $response_data;
		}

		// Show when 80% of quota used (about 4 requests remaining).
		if ( $quota['used'] >= ( $quota['limit'] * 0.8 ) ) {
			$response_data['show_upgrade_prompt'] = true;
			$response_data['upgrade_message']     = sprintf(
				/* translators: 1: Remaining requests, 2: Total limit */
				esc_html__( 'You have %1$d of %2$d free AI requests remaining today. Premium plans with unlimited requests coming soon!', 'wyverncss' ),
				$quota['remaining'],
				$quota['limit']
			);
		}

		return $response_data;
	}

	/**
	 * Add transformation and complex CSS data to response.
	 *
	 * @param array<string, mixed>      $response_data     Response data to modify.
	 * @param array<string, mixed>|null $transformation    Transformation data.
	 * @param string|null               $custom_class_name Custom class name.
	 * @param string|null               $stylesheet        Stylesheet content.
	 * @return array<string, mixed> Response data with transformation data if applicable.
	 */
	protected function add_transformation_data( array $response_data, ?array $transformation, ?string $custom_class_name, ?string $stylesheet ): array {
		if ( null !== $transformation && true === $transformation['transform'] ) {
			$response_data['transformElement'] = array(
				'newTag'             => $transformation['newTag'],
				'attributes'         => $transformation['attributes'] ?? array(),
				'preserveAttributes' => $transformation['preserveAttributes'] ?? true,
				'textContent'        => $transformation['textContent'] ?? null,
			);
		}

		if ( null !== $custom_class_name && null !== $stylesheet ) {
			$response_data['customClassName'] = $custom_class_name;
			$response_data['stylesheet']      = $stylesheet;
		}

		return $response_data;
	}

	/**
	 * Add rate limit headers to REST response.
	 *
	 * @param WP_REST_Response     $response REST response object.
	 * @param array<string, mixed> $quota    Quota information.
	 * @return void
	 */
	protected function add_rate_limit_headers( WP_REST_Response $response, array $quota ): void {
		// Add standard rate limit headers.
		$response->header( 'X-RateLimit-Limit', (string) $quota['limit'] );
		$response->header( 'X-RateLimit-Remaining', (string) $quota['remaining'] );
		$response->header( 'X-RateLimit-Used', (string) $quota['used'] );

		// Add reset time if available.
		if ( ! empty( $quota['reset_at'] ) ) {
			$response->header( 'X-RateLimit-Reset', $quota['reset_at'] );
		}

		// Add unlimited indicator.
		if ( $quota['limit'] < 0 ) {
			$response->header( 'X-RateLimit-Unlimited', 'true' );
		}
	}

	/**
	 * Get the upgrade URL for premium features.
	 *
	 * Uses Freemius SDK upgrade URL when available.
	 *
	 * @return string The upgrade URL.
	 */
	protected function get_upgrade_url(): string {
		return $this->freemius->get_upgrade_url();
	}

	/**
	 * Build usage info array for response.
	 *
	 * @param string $tier  User's tier.
	 * @param int    $used  Requests used.
	 * @param int    $limit Request limit (-1 for unlimited).
	 * @param string $reset Reset date/time.
	 * @return array<string, mixed> Usage info.
	 */
	protected function build_usage_info( string $tier, int $used, int $limit, string $reset ): array {
		$usage = array(
			'tier'               => $tier,
			'requests_used'      => $used,
			'requests_limit'     => $limit,
			'requests_remaining' => $limit > 0 ? max( 0, $limit - $used ) : -1,
			'unlimited'          => $limit < 0,
		);

		if ( ! empty( $reset ) ) {
			$usage['reset_at'] = $reset;
		}

		return $usage;
	}

	/**
	 * Get endpoint arguments.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Endpoint arguments.
	 */
	private function get_endpoint_args(): array {
		return array(
			'prompt'          => array(
				'description'       => __( 'Natural language style description.', 'wyverncss' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => array( $this, 'sanitize_prompt' ),
				'validate_callback' => array( $this, 'validate_prompt' ),
			),
			'element_context' => array(
				'description' => __( 'Element context data (tag, classes, current styles, etc.).', 'wyverncss' ),
				'type'        => 'object',
				'required'    => false,
				'default'     => array(),
			),
			'prefer_speed'    => array(
				'description' => __( 'Prefer faster models over higher quality.', 'wyverncss' ),
				'type'        => 'boolean',
				'required'    => false,
				'default'     => false,
			),
			'block_context'   => array(
				'description' => __( 'Block context data for Gutenberg integration.', 'wyverncss' ),
				'type'        => 'object',
				'required'    => false,
				'default'     => array(),
				'properties'  => array(
					'clientId'        => array( 'type' => 'string' ),
					'blockName'       => array( 'type' => 'string' ),
					'blockAttributes' => array( 'type' => 'object' ),
					'blockIndex'      => array( 'type' => 'integer' ),
				),
			),
		);
	}

	/**
	 * Get schema for style endpoint.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Schema definition.
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'style',
			'type'       => 'object',
			'properties' => array(
				'requires_ai'      => array(
					'description' => __( 'Whether AI processing is required.', 'wyverncss' ),
					'type'        => 'boolean',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'css'              => array(
					'description' => __( 'Generated CSS properties.', 'wyverncss' ),
					'type'        => 'object',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'confidence'       => array(
					'description' => __( 'Match confidence score (0-100).', 'wyverncss' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'matched_patterns' => array(
					'description' => __( 'List of matched pattern keys.', 'wyverncss' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'source'           => array(
					'description' => __( 'Source of the CSS (pattern, ai, cache).', 'wyverncss' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'usage'            => array(
					'description' => __( 'Current usage statistics.', 'wyverncss' ),
					'type'        => 'object',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'ai_error'         => array(
					'description' => __( 'AI error message if AI generation failed.', 'wyverncss' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
