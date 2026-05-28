<?php
/**
 * Plugin Name:       Weather Food Suggestion
 * Plugin URI:        https://github.com/spooncloud-infinity/weather-food-suggestion
 * Description:       Suggests meals based on local weather and fridge ingredients. Shortcode and Elementor widget.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Spooncloud
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       weather-food-suggestion
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WFS_VERSION', '1.0.0' );
define( 'WFS_PLUGIN_FILE', __FILE__ );
define( 'WFS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WFS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WFS_PREFIX', 'wfs' );
define( 'WFS_REST_NAMESPACE', 'weather-food-suggestion/v1' );
define( 'WFS_CACHE_TTL', 1800 );

/**
 * Load plugin classes.
 */
function wfs_load_classes() {
	$includes = WFS_PLUGIN_DIR . 'includes/';

	require_once $includes . 'class-wfs-i18n.php';
	require_once $includes . 'interface-wfs-suggestion-provider.php';
	require_once $includes . 'class-wfs-open-meteo-service.php';
	require_once $includes . 'class-wfs-suggestion-engine.php';
	require_once $includes . 'class-wfs-rule-based-provider.php';
	require_once $includes . 'class-wfs-assets.php';
	require_once $includes . 'class-wfs-template.php';
	require_once $includes . 'class-wfs-rest-api.php';
	require_once $includes . 'class-wfs-shortcode.php';
	require_once $includes . 'class-wfs-plugin.php';

	if ( is_admin() ) {
		require_once $includes . 'admin/class-wfs-admin-settings.php';
	}
}

/**
 * Bootstrap plugin.
 */
function wfs_init_plugin() {
	wfs_load_classes();
	WFS_Plugin::instance()->init();
}

add_action( 'plugins_loaded', 'wfs_init_plugin', 20 );
