<?php
/**
 * Main plugin orchestrator.
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFS_Plugin
 */
class WFS_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var WFS_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return WFS_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		load_plugin_textdomain(
			'weather-food-suggestion',
			false,
			dirname( plugin_basename( WFS_PLUGIN_FILE ) ) . '/languages'
		);

		new WFS_Assets();
		new WFS_REST_API();
		new WFS_Shortcode();

		if ( is_admin() ) {
			new WFS_Admin_Settings();
		}

		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_elementor_category' ) );
	}

	/**
	 * Register Elementor widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elements manager.
	 */
	public function register_elementor_category( $elements_manager ) {
		$elements_manager->add_category(
			'wfs-widgets',
			array(
				'title' => esc_html__( 'Weather Food', 'weather-food-suggestion' ),
				'icon'  => 'fa fa-plug',
			)
		);
	}

	/**
	 * Register Elementor widget when Elementor is loaded.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 */
	public function register_elementor_widget( $widgets_manager ) {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		require_once WFS_PLUGIN_DIR . 'includes/elementor/class-wfs-elementor-widget.php';
		$widgets_manager->register( new WFS_Elementor_Widget() );
	}
}
