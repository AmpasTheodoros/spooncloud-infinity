<?php
/**
 * Elementor widget.
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFS_Elementor_Widget
 */
class WFS_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'weather_food_suggestion';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Weather Food Suggestion', 'weather-food-suggestion' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-cloud-check';
	}

	/**
	 * Categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'wfs-widgets', 'general' );
	}

	/**
	 * Keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'weather', 'food', 'recipe', 'meal', 'suggestion' );
	}

	/**
	 * Script dependencies.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( 'wfs-frontend' );
	}

	/**
	 * Style dependencies.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( 'wfs-frontend' );
	}

	/**
	 * Register controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => esc_html__( 'Content', 'weather-food-suggestion' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => esc_html__( 'Widget title', 'weather-food-suggestion' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => esc_html__( 'Weather Food Suggestion', 'weather-food-suggestion' ),
			)
		);

		$dietary_options = array();
		foreach ( WFS_Template::get_dietary_options() as $value => $label ) {
			$dietary_options[ $value ] = $label;
		}

		$this->add_control(
			'dietary',
			array(
				'label'   => esc_html__( 'Default dietary preference', 'weather-food-suggestion' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'none',
				'options' => $dietary_options,
			)
		);

		$meal_options = array( '' => esc_html__( 'Auto (time of day)', 'weather-food-suggestion' ) );
		foreach ( WFS_Template::get_meal_options() as $value => $label ) {
			$meal_options[ $value ] = $label;
		}

		$this->add_control(
			'meal_type',
			array(
				'label'   => esc_html__( 'Default meal type', 'weather-food-suggestion' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $meal_options,
			)
		);

		$this->add_control(
			'show_geolocation',
			array(
				'label'        => esc_html__( 'Show geolocation button', 'weather-food-suggestion' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'weather-food-suggestion' ),
				'label_off'    => esc_html__( 'No', 'weather-food-suggestion' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'button_label',
			array(
				'label'   => esc_html__( 'Submit button label', 'weather-food-suggestion' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__( 'Suggest Food', 'weather-food-suggestion' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$is_edit = \Elementor\Plugin::$instance->editor->is_edit_mode();

		echo WFS_Template::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'title'             => isset( $settings['title'] ) ? $settings['title'] : '',
				'dietary'           => isset( $settings['dietary'] ) ? $settings['dietary'] : 'none',
				'meal_type'         => isset( $settings['meal_type'] ) ? $settings['meal_type'] : '',
				'show_geolocation'  => ( isset( $settings['show_geolocation'] ) && 'yes' === $settings['show_geolocation'] ),
				'button_label'      => ! empty( $settings['button_label'] ) ? $settings['button_label'] : __( 'Suggest Food', 'weather-food-suggestion' ),
				'is_elementor_edit' => $is_edit,
			)
		);
	}
}
