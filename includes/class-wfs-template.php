<?php
/**
 * Shared frontend template.
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFS_Template
 */
class WFS_Template {

	/**
	 * Render widget markup.
	 *
	 * @param array $args Template arguments.
	 * @return string
	 */
	public static function render( $args = array() ) {
		$settings = wfs_get_settings();

		$defaults = array(
			'title'             => '',
			'dietary'           => $settings['default_dietary'],
			'meal_type'         => $settings['default_meal_type'],
			'show_geolocation'  => ( 'yes' === $settings['enable_geolocation'] ),
			'button_label'      => __( 'Suggest Food', 'weather-food-suggestion' ),
			'instance_id'       => wp_unique_id( 'wfs-' ),
			'is_elementor_edit' => false,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( 'yes' === $args['show_geolocation'] || true === $args['show_geolocation'] ) {
			$args['show_geolocation'] = true;
		} elseif ( 'no' === $args['show_geolocation'] ) {
			$args['show_geolocation'] = false;
		}

		$args['dietary']   = self::sanitize_dietary( $args['dietary'] );
		$args['meal_type'] = self::sanitize_meal_type( $args['meal_type'] );

		WFS_Assets::enqueue_frontend();

		ob_start();
		self::render_markup( $args );
		return ob_get_clean();
	}

	/**
	 * Output HTML.
	 *
	 * @param array $args Arguments.
	 */
	private static function render_markup( $args ) {
		$id = esc_attr( $args['instance_id'] );
		?>
		<div class="wfs-wrap" id="<?php echo $id; ?>" data-wfs-instance>
			<div class="wfs-card">
				<?php if ( ! empty( $args['title'] ) ) : ?>
					<h2 class="wfs-title"><?php echo esc_html( $args['title'] ); ?></h2>
				<?php endif; ?>

				<p class="wfs-intro"><?php esc_html_e( 'Tell us where you are and what is in your fridge — we will suggest something that fits the weather.', 'weather-food-suggestion' ); ?></p>

				<?php if ( ! empty( $args['is_elementor_edit'] ) ) : ?>
					<div class="wfs-notice wfs-notice--info">
						<?php esc_html_e( 'Preview: submit the form on the live site to see weather-based suggestions.', 'weather-food-suggestion' ); ?>
					</div>
				<?php endif; ?>

				<form class="wfs-form" novalidate>
					<div class="wfs-field">
						<label class="wfs-label" for="<?php echo $id; ?>-city"><?php esc_html_e( 'City / location', 'weather-food-suggestion' ); ?></label>
						<input
							type="text"
							class="wfs-input"
							id="<?php echo $id; ?>-city"
							name="city"
							placeholder="<?php esc_attr_e( 'e.g. Berlin, London', 'weather-food-suggestion' ); ?>"
							autocomplete="address-level2"
						/>
					</div>

					<?php if ( $args['show_geolocation'] ) : ?>
						<div class="wfs-field wfs-field--inline">
							<button type="button" class="wfs-btn wfs-btn--secondary wfs-geolocate">
								<?php esc_html_e( 'Use my current location', 'weather-food-suggestion' ); ?>
							</button>
						</div>
					<?php endif; ?>

					<div class="wfs-field">
						<label class="wfs-label" for="<?php echo $id; ?>-fridge"><?php esc_html_e( 'What is in your fridge?', 'weather-food-suggestion' ); ?></label>
						<textarea
							class="wfs-textarea"
							id="<?php echo $id; ?>-fridge"
							name="fridge_text"
							rows="4"
							placeholder="<?php esc_attr_e( 'tomato, eggs, spinach (comma or line separated)', 'weather-food-suggestion' ); ?>"
						></textarea>
					</div>

					<div class="wfs-field-row">
						<div class="wfs-field">
							<label class="wfs-label" for="<?php echo $id; ?>-dietary"><?php esc_html_e( 'Dietary preference', 'weather-food-suggestion' ); ?></label>
							<select class="wfs-select" id="<?php echo $id; ?>-dietary" name="dietary">
								<?php foreach ( self::get_dietary_options() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $args['dietary'], $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="wfs-field">
							<label class="wfs-label" for="<?php echo $id; ?>-meal"><?php esc_html_e( 'Meal type', 'weather-food-suggestion' ); ?></label>
							<select class="wfs-select" id="<?php echo $id; ?>-meal" name="meal_type">
								<option value=""><?php esc_html_e( 'Auto (time of day)', 'weather-food-suggestion' ); ?></option>
								<?php foreach ( self::get_meal_options() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $args['meal_type'], $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="wfs-actions">
						<button type="submit" class="wfs-btn wfs-btn--primary wfs-submit">
							<span class="wfs-submit-label"><?php echo esc_html( $args['button_label'] ); ?></span>
							<span class="wfs-spinner" aria-hidden="true"></span>
						</button>
					</div>

					<div class="wfs-message wfs-error" role="alert" hidden></div>
				</form>

				<div class="wfs-result" hidden>
					<h3 class="wfs-result-title"></h3>
					<div class="wfs-weather-summary"></div>
					<p class="wfs-reason"></p>
					<div class="wfs-ingredients-block">
						<h4 class="wfs-subheading"><?php esc_html_e( 'From your fridge', 'weather-food-suggestion' ); ?></h4>
						<ul class="wfs-list wfs-used-list"></ul>
					</div>
					<div class="wfs-ingredients-block wfs-missing-block" hidden>
						<h4 class="wfs-subheading"><?php esc_html_e( 'Optional extras', 'weather-food-suggestion' ); ?></h4>
						<ul class="wfs-list wfs-missing-list"></ul>
					</div>
					<div class="wfs-steps-block">
						<h4 class="wfs-subheading"><?php esc_html_e( 'Preparation', 'weather-food-suggestion' ); ?></h4>
						<ol class="wfs-steps-list"></ol>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Dietary select options.
	 *
	 * @return array
	 */
	public static function get_dietary_options() {
		return array(
			'none'          => __( 'No preference', 'weather-food-suggestion' ),
			'vegetarian'    => __( 'Vegetarian', 'weather-food-suggestion' ),
			'vegan'         => __( 'Vegan', 'weather-food-suggestion' ),
			'high_protein'  => __( 'High protein', 'weather-food-suggestion' ),
			'light_meal'    => __( 'Light meal', 'weather-food-suggestion' ),
			'comfort_food'  => __( 'Comfort food', 'weather-food-suggestion' ),
		);
	}

	/**
	 * Meal type options.
	 *
	 * @return array
	 */
	public static function get_meal_options() {
		return array(
			'breakfast' => __( 'Breakfast', 'weather-food-suggestion' ),
			'lunch'     => __( 'Lunch', 'weather-food-suggestion' ),
			'dinner'    => __( 'Dinner', 'weather-food-suggestion' ),
			'snack'     => __( 'Snack', 'weather-food-suggestion' ),
		);
	}

	/**
	 * Sanitize dietary attribute.
	 *
	 * @param string $dietary Dietary.
	 * @return string
	 */
	private static function sanitize_dietary( $dietary ) {
		$keys = array_keys( self::get_dietary_options() );
		return in_array( $dietary, $keys, true ) ? $dietary : 'none';
	}

	/**
	 * Sanitize meal type attribute.
	 *
	 * @param string $meal_type Meal type.
	 * @return string
	 */
	private static function sanitize_meal_type( $meal_type ) {
		$keys = array_keys( self::get_meal_options() );
		return in_array( $meal_type, $keys, true ) ? $meal_type : '';
	}
}
