<?php
/**
 * Dashboard Template
 *
 * @package WyvernCSS
 * @subpackage Admin
 *
 * @var array<string, mixed> $stats      Statistics array.
 * @var string               $period     Current period (daily/weekly/monthly).
 * @var string               $export_url CSV export URL.
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="wrap wyverncss-dashboard">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Monitor your WyvernCSS usage, costs, and performance metrics.', 'wyverncss' ); ?>
	</p>

	<!-- Period Selector -->
	<div class="wyverncss-period-selector">
		<h2 class="nav-tab-wrapper">
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=wyverncss-dashboard&period=daily' ) ); ?>"
				class="nav-tab <?php echo 'daily' === $period ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Daily', 'wyverncss' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=wyverncss-dashboard&period=weekly' ) ); ?>"
				class="nav-tab <?php echo 'weekly' === $period ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Weekly', 'wyverncss' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=wyverncss-dashboard&period=monthly' ) ); ?>"
				class="nav-tab <?php echo 'monthly' === $period ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Monthly', 'wyverncss' ); ?>
			</a>
		</h2>
	</div>

	<!-- Stats Cards -->
	<div class="wyverncss-stats-grid">
		<div class="wyverncss-stat-card">
			<div class="stat-icon">
				<span class="dashicons dashicons-chart-line"></span>
			</div>
			<div class="stat-content">
				<h3><?php esc_html_e( 'This Month', 'wyverncss' ); ?></h3>
				<div class="stat-value"><?php echo esc_html( number_format( $stats['current_month'] ) ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Total Requests', 'wyverncss' ); ?></div>
			</div>
		</div>

		<div class="wyverncss-stat-card">
			<div class="stat-icon">
				<span class="dashicons dashicons-money-alt"></span>
			</div>
			<div class="stat-content">
				<h3><?php esc_html_e( 'Cost Saved', 'wyverncss' ); ?></h3>
				<div class="stat-value">$<?php echo esc_html( number_format( $stats['cost_saved'], 2 ) ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Via Pattern Matching', 'wyverncss' ); ?></div>
			</div>
		</div>

		<div class="wyverncss-stat-card">
			<div class="stat-icon">
				<span class="dashicons dashicons-performance"></span>
			</div>
			<div class="stat-content">
				<h3><?php esc_html_e( 'Cache Hit Rate', 'wyverncss' ); ?></h3>
				<div class="stat-value"><?php echo esc_html( $stats['cache_hit_rate'] ); ?>%</div>
				<div class="stat-label"><?php esc_html_e( 'Pattern Match Efficiency', 'wyverncss' ); ?></div>
			</div>
		</div>

		<div class="wyverncss-stat-card">
			<div class="stat-icon">
				<span class="dashicons dashicons-admin-generic"></span>
			</div>
			<div class="stat-content">
				<h3><?php esc_html_e( 'API Cost', 'wyverncss' ); ?></h3>
				<div class="stat-value">$<?php echo esc_html( number_format( $stats['total_cost'], 2 ) ); ?></div>
				<div class="stat-label">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: period */
							__( 'This %s', 'wyverncss' ),
							ucfirst( $period )
						)
					);
					?>
				</div>
			</div>
		</div>
	</div>

	<!-- Charts Row -->
	<div class="wyverncss-charts-row">
		<div class="wyverncss-chart-container">
			<h2><?php esc_html_e( 'Request Trends', 'wyverncss' ); ?></h2>
			<canvas id="wyverncss-requests-chart"></canvas>
		</div>

		<div class="wyverncss-chart-container">
			<h2><?php esc_html_e( 'Model Usage Distribution', 'wyverncss' ); ?></h2>
			<canvas id="wyverncss-model-chart"></canvas>
		</div>
	</div>

	<!-- Model Breakdown Table -->
	<div class="wyverncss-table-section">
		<h2><?php esc_html_e( 'Model Usage Breakdown', 'wyverncss' ); ?></h2>

		<?php if ( ! empty( $stats['model_breakdown'] ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Model', 'wyverncss' ); ?></th>
						<th><?php esc_html_e( 'Requests', 'wyverncss' ); ?></th>
						<th><?php esc_html_e( 'Total Tokens', 'wyverncss' ); ?></th>
						<th><?php esc_html_e( 'Total Cost', 'wyverncss' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Loop variable in template.
					foreach ( $stats['model_breakdown'] as $model_stat ) :
						?>
						<tr>
							<td><strong><?php echo esc_html( $model_stat['model_name'] ); ?></strong></td>
							<td><?php echo esc_html( number_format( $model_stat['request_count'] ) ); ?></td>
							<td><?php echo esc_html( number_format( $model_stat['total_tokens'] ) ); ?></td>
							<td>$<?php echo esc_html( number_format( $model_stat['total_cost'], 4 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No model usage data available for this period.', 'wyverncss' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Top Prompts Section -->
	<div class="wyverncss-table-section">
		<h2><?php esc_html_e( 'Most Used Prompts', 'wyverncss' ); ?></h2>

		<?php if ( ! empty( $stats['top_prompts'] ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Prompt Hash', 'wyverncss' ); ?></th>
						<th><?php esc_html_e( 'Usage Count', 'wyverncss' ); ?></th>
						<th><?php esc_html_e( 'Last Used', 'wyverncss' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Loop variable in template.
					foreach ( $stats['top_prompts'] as $prompt ) :
						?>
						<tr>
							<td><code><?php echo esc_html( substr( $prompt['prompt_hash'], 0, 12 ) ); ?>...</code></td>
							<td><?php echo esc_html( number_format( (int) $prompt['count'] ) ); ?></td>
							<td><?php echo esc_html( gmdate( 'M j, Y g:i A', strtotime( $prompt['last_used'] ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No prompt data available.', 'wyverncss' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Export Section -->
	<div class="wyverncss-export-section">
		<h2><?php esc_html_e( 'Export Data', 'wyverncss' ); ?></h2>
		<p><?php esc_html_e( 'Download your usage data as a CSV file for external analysis.', 'wyverncss' ); ?></p>

		<a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary">
			<span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
			<?php esc_html_e( 'Export to CSV', 'wyverncss' ); ?>
		</a>
	</div>
</div>
