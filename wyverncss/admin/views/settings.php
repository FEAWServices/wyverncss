<?php
/**
 * Settings Page Template
 *
 * @package WyvernCSS
 * @subpackage Admin
 *
 * @var array<string, mixed> $settings Settings array.
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="wrap wyvernpress-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Configure your WyvernCSS settings.', 'wyvern-ai-styling' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wyverncss_save_settings' ); ?>
		<input type="hidden" name="action" value="wyverncss_save_settings">

		<h2><?php esc_html_e( 'Analytics Settings', 'wyvern-ai-styling' ); ?></h2>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Enable Analytics', 'wyvern-ai-styling' ); ?>
					</th>
					<td>
						<label for="wyverncss_enable_analytics">
							<input type="checkbox"
									id="wyverncss_enable_analytics"
									name="wyverncss_enable_analytics"
									value="1"
									<?php checked( $settings['enable_analytics'] ?? true ); ?>>
							<?php esc_html_e( 'Track usage statistics and analytics', 'wyvern-ai-styling' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Disable this to stop tracking usage data. Existing data will be retained.', 'wyvern-ai-styling' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wyverncss_retention_days">
							<?php esc_html_e( 'Data Retention', 'wyvern-ai-styling' ); ?>
						</label>
					</th>
					<td>
						<input type="number"
								id="wyverncss_retention_days"
								name="wyverncss_retention_days"
								class="small-text"
								min="30"
								max="365"
								value="<?php echo esc_attr( (string) ( $settings['retention_days'] ?? 90 ) ); ?>">
						<span><?php esc_html_e( 'days', 'wyvern-ai-styling' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'How long to keep usage data before automatic cleanup (30-365 days).', 'wyvern-ai-styling' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Settings', 'wyvern-ai-styling' ) ); ?>
	</form>

</div>
