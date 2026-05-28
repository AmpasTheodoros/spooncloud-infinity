<?php
/**
 * Enqueue scripts and styles.
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFS_Assets
 */
class WFS_Assets {

	/**
	 * Whether frontend assets are enqueued.
	 *
	 * @var bool
	 */
	private static $enqueued = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register script and style handles.
	 */
	public function register_assets() {
		wp_register_style(
			'wfs-frontend',
			WFS_PLUGIN_URL . 'assets/css/wfs-frontend.css',
			array(),
			WFS_VERSION
		);

		wp_register_script(
			'wfs-frontend',
			WFS_PLUGIN_URL . 'assets/js/wfs-frontend.js',
			array(),
			WFS_VERSION,
			true
		);
	}

	/**
	 * Enqueue frontend assets once.
	 */
	public static function enqueue_frontend() {
		if ( self::$enqueued ) {
			return;
		}

		wp_enqueue_style( 'wfs-frontend' );
		wp_enqueue_script( 'wfs-frontend' );

		wp_localize_script(
			'wfs-frontend',
			'wfsData',
			array(
				'restUrl' => esc_url_raw( rest_url( WFS_REST_NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'loading'             => __( 'Finding your perfect meal…', 'weather-food-suggestion' ),
					'errorGeneric'        => __( 'Something went wrong. Please try again.', 'weather-food-suggestion' ),
					'locationDenied'      => __( 'Location permission was denied. Enter a city instead.', 'weather-food-suggestion' ),
					'locationUnavailable' => __( 'Could not detect your location. Enter a city instead.', 'weather-food-suggestion' ),
					'locationTimeout'     => __( 'Location request timed out. Try again or enter a city.', 'weather-food-suggestion' ),
					'cityRequired'        => __( 'Please enter a city or use your current location.', 'weather-food-suggestion' ),
					'weatherHeading'      => __( 'Current weather', 'weather-food-suggestion' ),
					'noUsedIngredients'   => __( 'No direct matches from your list — see optional extras.', 'weather-food-suggestion' ),
				),
			)
		);

		$settings = wfs_get_settings();
		$css      = isset( $settings['custom_css'] ) ? trim( $settings['custom_css'] ) : '';
		if ( '' !== $css ) {
			wp_add_inline_style( 'wfs-frontend', wp_strip_all_tags( $css ) );
		}

		self::$enqueued = true;
	}
}
