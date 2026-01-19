<?php
/**
 * Plugin Name: WyvernCSS
 * Plugin URI: https://github.com/FEAWServices/wyverncss
 * Description: AI-powered CSS styling for Gutenberg. Select a block, describe how you want it to look, done.
 * Version: 1.0.8
 * Author: FEAW
 * Author URI: https://feaw.co.uk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wyvern-ai-styling
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 *
 * @package WyvernCSS
 */

declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin version.
 */
if ( ! defined( 'WYVERNCSS_VERSION' ) ) {
	define( 'WYVERNCSS_VERSION', '1.0.0' );
}

/**
 * Plugin directory path.
 */
if ( ! defined( 'WYVERNCSS_PLUGIN_DIR' ) ) {
	define( 'WYVERNCSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Plugin directory URL.
 */
if ( ! defined( 'WYVERNCSS_PLUGIN_URL' ) ) {
	define( 'WYVERNCSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Plugin basename.
 */
if ( ! defined( 'WYVERNCSS_PLUGIN_BASENAME' ) ) {
	define( 'WYVERNCSS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * Check WordPress version compatibility.
 *
 * @return void
 */
function wyverncss_check_wordpress_version(): void {
	global $wp_version;

	if ( version_compare( $wp_version, '6.4', '<' ) ) {
		add_action(
			'admin_notices',
			function () {
				?>
				<div class="error">
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: Required WordPress version */
								__( 'WyvernCSS requires WordPress version %s or higher. Please update WordPress.', 'wyvern-ai-styling' ),
								'6.4'
							)
						);
						?>
					</p>
				</div>
				<?php
			}
		);

		// Deactivate the plugin.
		deactivate_plugins( WYVERNCSS_PLUGIN_BASENAME );
		return;
	}
}

/**
 * Check PHP version compatibility.
 *
 * @return void
 */
function wyverncss_check_php_version(): void {
	if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
		add_action(
			'admin_notices',
			function () {
				?>
				<div class="error">
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: Required PHP version */
								__( 'WyvernCSS requires PHP version %s or higher. Please update PHP.', 'wyvern-ai-styling' ),
								'8.1'
							)
						);
						?>
					</p>
				</div>
				<?php
			}
		);

		// Deactivate the plugin.
		deactivate_plugins( WYVERNCSS_PLUGIN_BASENAME );
		return;
	}
}

// Perform version checks before loading anything.
wyverncss_check_wordpress_version();
wyverncss_check_php_version();

/**
 * Load Freemius SDK for licensing and premium features.
 * Must be loaded before the autoloader to ensure proper initialization.
 */
if ( file_exists( WYVERNCSS_PLUGIN_DIR . 'freemius/start.php' ) ) {
	require_once WYVERNCSS_PLUGIN_DIR . 'includes/freemius-init.php';
}

/**
 * Load the autoloader.
 */
require_once WYVERNCSS_PLUGIN_DIR . 'includes/autoload.php';

/**
 * The code that runs during plugin activation.
 *
 * @return void
 */
function wyverncss_activate(): void {
	// Autoloader (loaded on line 131) handles loading the Activator class automatically.
	\WyvernCSS\Core\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 *
 * @return void
 */
function wyverncss_deactivate(): void {
	// Autoloader (loaded on line 131) handles loading the Deactivator class automatically.
	\WyvernCSS\Core\Deactivator::deactivate();
}

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, 'wyverncss_activate' );
register_deactivation_hook( __FILE__, 'wyverncss_deactivate' );

/**
 * Initialize and run the plugin.
 *
 * @return void
 */
function wyverncss_run(): void {
	$plugin = new \WyvernCSS\Core\Plugin();
	$plugin->run();
}

// Start the plugin.
wyverncss_run();
