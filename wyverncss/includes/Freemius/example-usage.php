<?php
/**
 * Freemius Integration Usage Examples
 *
 * This file demonstrates how to use the Freemius_Integration class
 * in various parts of the WyvernCSS plugin.
 *
 * DO NOT INCLUDE THIS FILE IN PRODUCTION CODE.
 * This is for documentation purposes only.
 *
 * @package WyvernCSS
 * @subpackage Freemius
 *
 * phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
 * phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Exit if accessed directly.
use WyvernCSS\Freemius\Freemius_Integration;

// =============================================================================
// EXAMPLE 1: Basic Premium Check in a REST API Controller
// =============================================================================

/**
 * Example: Check if user can access premium styling features.
 */
function wyverncss_example_premium_check(): void {
	$freemius = Freemius_Integration::get_instance();

	if ( $freemius->is_premium() ) {
		// User has premium - enable advanced features.
		$css = wyverncss_generate_advanced_css();
	} else {
		// User is free - show basic features.
		$css = wyverncss_generate_basic_css();
	}
}

// =============================================================================
// EXAMPLE 2: Rate Limiting in Style Controller
// =============================================================================

/**
 * Example: Implement rate limiting based on user's tier.
 */
function wyverncss_example_rate_limiting( int $user_id ): bool {
	$freemius = Freemius_Integration::get_instance();

	// Get rate limit for current user's tier.
	$rate_limit = $freemius->get_rate_limit();

	if ( -1 === $rate_limit ) {
		// Unlimited tier - no rate limiting.
		return true;
	}

	// Get current usage from database.
	$current_usage = get_user_meta( $user_id, 'wyverncss_daily_usage', true );
	$current_usage = is_numeric( $current_usage ) ? (int) $current_usage : 0;

	if ( $current_usage >= $rate_limit ) {
		// Rate limit exceeded.
		return false;
	}

	// Increment usage.
	update_user_meta( $user_id, 'wyverncss_daily_usage', $current_usage + 1 );

	return true;
}

// =============================================================================
// EXAMPLE 3: Display Upgrade Notice in Admin
// =============================================================================

/**
 * Example: Show upgrade notice when user approaches rate limit.
 */
function wyverncss_example_upgrade_notice(): void {
	$freemius = Freemius_Integration::get_instance();

	if ( $freemius->is_premium() ) {
		return; // No notice for premium users.
	}

	$user_id       = get_current_user_id();
	$current_usage = (int) get_user_meta( $user_id, 'wyverncss_daily_usage', true );
	$rate_limit    = $freemius->get_rate_limit();

	if ( $current_usage >= $rate_limit * 0.8 ) { // 80% of limit.
		$message     = $freemius->get_upgrade_message( 'approaching_limit' );
		$upgrade_url = $freemius->get_upgrade_url();
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php echo esc_html( $message ); ?>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Upgrade Now', 'wyvern-ai-styling' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}

// =============================================================================
// EXAMPLE 4: Feature Gating in Settings Page
// =============================================================================

/**
 * Example: Show/hide features based on tier in admin settings.
 */
function wyverncss_example_feature_gating(): void {
	$freemius = Freemius_Integration::get_instance();

	?>
	<div class="wyvernpress-settings">
		<h2><?php esc_html_e( 'AI Styling Settings', 'wyvern-ai-styling' ); ?></h2>

		<!-- Basic features - available to all -->
		<div class="wyvernpress-section">
			<h3><?php esc_html_e( 'Pattern Library', 'wyvern-ai-styling' ); ?></h3>
			<p><?php esc_html_e( 'Choose from 100+ pre-built CSS patterns.', 'wyvern-ai-styling' ); ?></p>
		</div>

		<!-- Premium features -->
		<?php if ( $freemius->has_feature( 'openrouter-ai' ) ) : ?>
			<div class="wyvernpress-section">
				<h3><?php esc_html_e( 'AI Models', 'wyvern-ai-styling' ); ?></h3>
				<p><?php esc_html_e( 'Access Claude 3.5 Haiku and GPT-4o Mini for complex styling.', 'wyvern-ai-styling' ); ?></p>
				<!-- Model selection UI -->
			</div>
		<?php else : ?>
			<div class="wyvernpress-section wyvernpress-locked">
				<h3>
					<?php esc_html_e( 'AI Models', 'wyvern-ai-styling' ); ?>
					<span class="dashicons dashicons-lock"></span>
				</h3>
				<p>
					<?php esc_html_e( 'Upgrade to Premium to access advanced AI models.', 'wyvern-ai-styling' ); ?>
					<a href="<?php echo esc_url( $freemius->get_upgrade_url() ); ?>">
						<?php esc_html_e( 'Learn More', 'wyvern-ai-styling' ); ?>
					</a>
				</p>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

// =============================================================================
// EXAMPLE 5: REST API with Rate Limiting
// =============================================================================

/**
 * Example REST endpoint with rate limiting and tier-based features.
 */
class WyvernCSS_Example_Style_Controller extends WP_REST_Controller {

	/**
	 * Freemius integration instance.
	 *
	 * @var Freemius_Integration
	 */
	private Freemius_Integration $freemius;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->freemius = Freemius_Integration::get_instance();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'wyverncss/v1',
			'/style/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'generate_style' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Check permission.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Generate style CSS.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function generate_style( WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		// Check rate limit.
		if ( ! $this->check_rate_limit( $user_id ) ) {
			return new WP_Error(
				'rate_limit_exceeded',
				$this->freemius->get_upgrade_message( 'at_limit' ),
				array(
					'status'      => 429,
					'upgrade_url' => $this->freemius->get_upgrade_url(),
				)
			);
		}

		// Get AI config for user's tier.
		$ai_config = $this->freemius->get_ai_config();
		$prompt    = sanitize_text_field( $request->get_param( 'prompt' ) );

		// Generate CSS using tier-appropriate AI model.
		$css = $this->generate_css( $prompt, $ai_config );

		// Track usage.
		$this->increment_usage( $user_id );

		return new WP_REST_Response(
			array(
				'css'   => $css,
				'usage' => $this->get_usage_info( $user_id ),
				'tier'  => $this->freemius->get_plan(),
			),
			200
		);
	}

	/**
	 * Check if user is within rate limit.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if within limit.
	 */
	private function check_rate_limit( int $user_id ): bool {
		$rate_limit = $this->freemius->get_rate_limit();

		if ( -1 === $rate_limit ) {
			return true; // Unlimited.
		}

		$current_usage = (int) get_user_meta( $user_id, 'wyverncss_daily_usage', true );
		return $current_usage < $rate_limit;
	}

	/**
	 * Increment usage counter.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function increment_usage( int $user_id ): void {
		$current_usage = (int) get_user_meta( $user_id, 'wyverncss_daily_usage', true );
		update_user_meta( $user_id, 'wyverncss_daily_usage', $current_usage + 1 );
	}

	/**
	 * Get usage info for response.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed> Usage info.
	 */
	private function get_usage_info( int $user_id ): array {
		$rate_limit    = $this->freemius->get_rate_limit();
		$current_usage = (int) get_user_meta( $user_id, 'wyverncss_daily_usage', true );

		return array(
			'used'      => $current_usage,
			'limit'     => $rate_limit,
			'remaining' => -1 === $rate_limit ? -1 : max( 0, $rate_limit - $current_usage ),
			'period'    => $this->freemius->get_period(),
		);
	}

	/**
	 * Generate CSS (stub).
	 *
	 * @param string               $prompt    User prompt.
	 * @param array<string, mixed> $ai_config AI configuration.
	 * @return string CSS code.
	 */
	private function generate_css( string $prompt, array $ai_config ): string {
		// Implementation here...
		return '.element { color: blue; }';
	}
}

// =============================================================================
// EXAMPLE 6: License Information Display
// =============================================================================

/**
 * Example: Display license information in admin.
 */
function wyverncss_example_license_display(): void {
	$freemius     = Freemius_Integration::get_instance();
	$license_data = $freemius->get_license_data();
	$is_premium   = $freemius->is_premium();

	?>
	<div class="wyvernpress-license-info">
		<h3><?php esc_html_e( 'License Information', 'wyvern-ai-styling' ); ?></h3>

		<?php if ( $is_premium ) : ?>
			<table class="widefat">
				<tr>
					<th><?php esc_html_e( 'Plan', 'wyvern-ai-styling' ); ?></th>
					<td><?php echo esc_html( $license_data['plan'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Status', 'wyvern-ai-styling' ); ?></th>
					<td>
						<span class="wyvernpress-status wyvernpress-status-<?php echo esc_attr( $license_data['status'] ); ?>">
							<?php echo esc_html( ucfirst( $license_data['status'] ) ); ?>
						</span>
					</td>
				</tr>
				<?php if ( $license_data['expires'] ) : ?>
					<tr>
						<th><?php esc_html_e( 'Expires', 'wyvern-ai-styling' ); ?></th>
						<td>
							<?php
							echo esc_html( $freemius->get_license_expiration() );
							$days = $freemius->get_days_until_expiration();
							if ( $days > 0 && $days < 30 ) {
								printf(
									' <span class="wyvernpress-warning">(%s)</span>',
									esc_html(
										sprintf(
											/* translators: %d: Number of days */
											_n( '%d day remaining', '%d days remaining', $days, 'wyvern-ai-styling' ),
											$days
										)
									)
								);
							}
							?>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'Activations', 'wyvern-ai-styling' ); ?></th>
					<td>
						<?php
						printf(
							'%d / %d',
							absint( $license_data['activations'] ),
							absint( $license_data['max_sites'] )
						);
						?>
					</td>
				</tr>
			</table>

			<p>
				<a href="<?php echo esc_url( $freemius->get_account_url() ); ?>" class="button">
					<?php esc_html_e( 'Manage License', 'wyvern-ai-styling' ); ?>
				</a>
			</p>
		<?php else : ?>
			<p>
				<?php esc_html_e( 'You are currently using the free version of WyvernCSS.', 'wyvern-ai-styling' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Free Plan Includes:', 'wyvern-ai-styling' ); ?></strong>
			</p>
			<ul>
				<li><?php esc_html_e( '20 requests per day', 'wyvern-ai-styling' ); ?></li>
				<li><?php esc_html_e( '100+ CSS patterns', 'wyvern-ai-styling' ); ?></li>
				<li><?php esc_html_e( 'Local Ollama AI support', 'wyvern-ai-styling' ); ?></li>
			</ul>
			<p>
				<a href="<?php echo esc_url( $freemius->get_upgrade_url() ); ?>" class="button button-primary">
					<?php esc_html_e( 'Upgrade to Premium', 'wyvern-ai-styling' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

// =============================================================================
// EXAMPLE 7: Admin Notice for Expiring License
// =============================================================================

/**
 * Example: Show notice when license is expiring soon.
 */
function wyverncss_example_expiration_notice(): void {
	$freemius = Freemius_Integration::get_instance();

	if ( ! $freemius->is_premium() ) {
		return;
	}

	$days = $freemius->get_days_until_expiration();

	if ( $days > 0 && $days <= 7 ) {
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					esc_html(
						/* translators: %d: Number of days until license expiration. */
						_n(
							'Your WyvernCSS Premium license expires in %d day!',
							'Your WyvernCSS Premium license expires in %d days!',
							$days,
							'wyvern-ai-styling'
						)
					),
					absint( $days )
				);
				?>
				<a href="<?php echo esc_url( $freemius->get_account_url() ); ?>">
					<?php esc_html_e( 'Renew Now', 'wyvern-ai-styling' ); ?>
				</a>
			</p>
		</div>
		<?php
	} elseif ( $days < 0 ) {
		?>
		<div class="notice notice-error">
			<p>
				<?php esc_html_e( 'Your WyvernCSS Premium license has expired.', 'wyvern-ai-styling' ); ?>
				<a href="<?php echo esc_url( $freemius->get_account_url() ); ?>">
					<?php esc_html_e( 'Renew Now', 'wyvern-ai-styling' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}

// =============================================================================
// EXAMPLE 8: JavaScript Checkout Integration
// =============================================================================

/**
 * Example: Add upgrade button with JavaScript checkout.
 */
function wyverncss_example_checkout_button(): void {
	$freemius = Freemius_Integration::get_instance();

	if ( $freemius->is_premium() ) {
		return; // Already premium.
	}

	$plan_id     = '12345'; // Replace with actual plan ID from Freemius.
	$checkout_js = $freemius->get_checkout_js( $plan_id, true );
	?>
	<button id="wyvernpress-upgrade-btn" class="button button-primary button-hero">
		<?php esc_html_e( 'Upgrade to Premium', 'wyvern-ai-styling' ); ?>
	</button>

	<script>
		document.getElementById('wyvernpress-upgrade-btn').addEventListener('click', function(e) {
			e.preventDefault();
			<?php echo $checkout_js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by Freemius. ?>
		});
	</script>
	<?php
}

// =============================================================================
// EXAMPLE 9: Cron Job to Reset Daily Usage
// =============================================================================

/**
 * Example: Reset daily usage counter at midnight.
 */
function wyverncss_example_reset_daily_usage(): void {
	$freemius = Freemius_Integration::get_instance();

	// Only reset for users with daily limits.
	if ( $freemius->get_period() !== 'day' ) {
		return;
	}

	// Get all users.
	$users = get_users( array( 'fields' => 'ID' ) );

	foreach ( $users as $user_id ) {
		delete_user_meta( $user_id, 'wyverncss_daily_usage' );
	}

	// Clear license cache to refresh any changed data.
	$freemius->clear_license_cache();
}

// Schedule cron job.
if ( ! wp_next_scheduled( 'wyverncss_reset_daily_usage' ) ) {
	wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', 'wyverncss_reset_daily_usage' );
}
add_action( 'wyverncss_reset_daily_usage', 'wyverncss_example_reset_daily_usage' );

// =============================================================================
// EXAMPLE 10: Integration with Existing StyleController
// =============================================================================

/**
 * Example: Integrate Freemius into existing StyleController.
 *
 * Add this to your existing StyleController class:
 */

// phpcs:disable Squiz.PHP.CommentedOutCode.Found, Squiz.Commenting.BlockComment
/*
 * Example StyleController integration code:
 *
class StyleController extends WP_REST_Controller {

	private Freemius_Integration $freemius;

	public function __construct() {
		$this->freemius = Freemius_Integration::get_instance();
	}

	public function create_style( WP_REST_Request $request ) {
		// Check rate limit first.
		if ( ! $this->check_rate_limit() ) {
			return new WP_REST_Response(
				array(
					'error'       => 'rate_limit_exceeded',
					'message'     => $this->freemius->get_upgrade_message( 'at_limit' ),
					'upgrade_url' => $this->freemius->get_upgrade_url(),
					'tier'        => $this->freemius->get_plan(),
				),
				429
			);
		}

		// Use tier-specific AI config.
		$ai_config = $this->freemius->get_ai_config();

		// Generate CSS...
	}

	private function check_rate_limit(): bool {
		if ( $this->freemius->is_unlimited() ) {
			return true;
		}

		$user_id = get_current_user_id();
		$usage   = (int) get_user_meta( $user_id, 'wyverncss_daily_usage', true );
		$limit   = $this->freemius->get_rate_limit();

		return $usage < $limit;
	}
}
*/
// phpcs:enable
