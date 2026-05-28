<?php
/**
 * Shortcode handler.
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFS_Shortcode
 */
class WFS_Shortcode {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'weather_food_suggestion', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
	}

	/**
	 * Render shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'            => '',
				'dietary'          => '',
				'meal_type'        => '',
				'show_geolocation' => '',
				'button_label'     => '',
			),
			$atts,
			'weather_food_suggestion'
		);

		$settings = wfs_get_settings();

		$args = array(
			'title'     => sanitize_text_field( $atts['title'] ),
			'dietary'   => '' !== $atts['dietary'] ? sanitize_text_field( $atts['dietary'] ) : $settings['default_dietary'],
			'meal_type' => sanitize_text_field( $atts['meal_type'] ),
			'button_label' => '' !== $atts['button_label']
				? sanitize_text_field( $atts['button_label'] )
				: __( 'Suggest Food', 'weather-food-suggestion' ),
		);

		if ( '' !== $atts['show_geolocation'] ) {
			$args['show_geolocation'] = ( 'yes' === strtolower( $atts['show_geolocation'] ) );
		} else {
			$args['show_geolocation'] = ( 'yes' === $settings['enable_geolocation'] );
		}

		return WFS_Template::render( $args );
	}

	/**
	 * Pre-enqueue when shortcode is present in post content.
	 */
	public function maybe_enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}

		global $post;
		if ( $post && has_shortcode( $post->post_content, 'weather_food_suggestion' ) ) {
			WFS_Assets::enqueue_frontend();
		}
	}
}
