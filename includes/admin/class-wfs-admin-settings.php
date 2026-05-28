<?php
/**
 * Admin settings page.
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFS_Admin_Settings
 */
class WFS_Admin_Settings {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wfs_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings submenu.
	 */
	public function add_menu() {
		add_options_page(
			__( 'Weather Food Suggestion', 'weather-food-suggestion' ),
			__( 'Weather Food Suggestion', 'weather-food-suggestion' ),
			'manage_options',
			'weather-food-suggestion',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'wfs_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => wfs_get_settings(),
			)
		);

		add_settings_section(
			'wfs_general',
			__( 'General', 'weather-food-suggestion' ),
			'__return_false',
			'weather-food-suggestion'
		);

		$fields = array(
			'cache_ttl'          => array( 'label' => __( 'Weather cache TTL (seconds)', 'weather-food-suggestion' ), 'type' => 'number' ),
			'default_dietary'    => array( 'label' => __( 'Default dietary preference', 'weather-food-suggestion' ), 'type' => 'dietary' ),
			'default_meal_type'  => array( 'label' => __( 'Default meal type', 'weather-food-suggestion' ), 'type' => 'meal' ),
			'enable_geolocation' => array( 'label' => __( 'Enable geolocation button', 'weather-food-suggestion' ), 'type' => 'yesno' ),
			'temp_hot'           => array( 'label' => __( 'Hot temperature threshold (°C)', 'weather-food-suggestion' ), 'type' => 'number' ),
			'temp_warm'          => array( 'label' => __( 'Warm temperature threshold (°C)', 'weather-food-suggestion' ), 'type' => 'number' ),
			'temp_mild'          => array( 'label' => __( 'Mild temperature threshold (°C)', 'weather-food-suggestion' ), 'type' => 'number' ),
			'wind_threshold'     => array( 'label' => __( 'Wind speed threshold (km/h)', 'weather-food-suggestion' ), 'type' => 'number' ),
			'wind_temp_max'      => array( 'label' => __( 'Wind rule max temperature (°C)', 'weather-food-suggestion' ), 'type' => 'number' ),
			'precip_threshold'   => array( 'label' => __( 'Precipitation threshold (mm)', 'weather-food-suggestion' ), 'type' => 'number' ),
			'custom_css'         => array( 'label' => __( 'Custom CSS (scoped to .wfs-wrap)', 'weather-food-suggestion' ), 'type' => 'textarea' ),
		);

		foreach ( $fields as $id => $field ) {
			add_settings_field(
				$id,
				$field['label'],
				array( $this, 'render_field' ),
				'weather-food-suggestion',
				'wfs_general',
				array(
					'id'   => $id,
					'type' => $field['type'],
				)
			);
		}
	}

	/**
	 * Sanitize settings on save.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = wfs_get_settings();
		$output   = $defaults;

		if ( ! is_array( $input ) ) {
			return $output;
		}

		if ( isset( $input['cache_ttl'] ) ) {
			$ttl = absint( $input['cache_ttl'] );
			$output['cache_ttl'] = max( 300, min( 3600, $ttl ) );
		}

		$dietary_keys = array_keys( WFS_Template::get_dietary_options() );
		if ( isset( $input['default_dietary'] ) && in_array( $input['default_dietary'], $dietary_keys, true ) ) {
			$output['default_dietary'] = $input['default_dietary'];
		}

		$meal_keys = array_merge( array( '' ), array_keys( WFS_Template::get_meal_options() ) );
		if ( isset( $input['default_meal_type'] ) && in_array( $input['default_meal_type'], $meal_keys, true ) ) {
			$output['default_meal_type'] = $input['default_meal_type'];
		}

		$output['enable_geolocation'] = ( isset( $input['enable_geolocation'] ) && 'yes' === $input['enable_geolocation'] ) ? 'yes' : 'no';

		foreach ( array( 'temp_hot', 'temp_warm', 'temp_mild', 'wind_threshold', 'wind_temp_max', 'precip_threshold' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$output[ $key ] = is_numeric( $input[ $key ] ) ? (float) $input[ $key ] : $defaults[ $key ];
			}
		}

		if ( isset( $input['custom_css'] ) ) {
			$output['custom_css'] = wp_strip_all_tags( $input['custom_css'] );
		}

		return $output;
	}

	/**
	 * Render a settings field.
	 *
	 * @param array $args Field args.
	 */
	public function render_field( $args ) {
		$settings = wfs_get_settings();
		$id       = $args['id'];
		$value    = isset( $settings[ $id ] ) ? $settings[ $id ] : '';
		$name     = self::OPTION_NAME . '[' . $id . ']';

		switch ( $args['type'] ) {
			case 'number':
				printf(
					'<input type="number" name="%1$s" id="%2$s" value="%3$s" class="regular-text" step="any" />',
					esc_attr( $name ),
					esc_attr( $id ),
					esc_attr( $value )
				);
				break;

			case 'dietary':
				echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '">';
				foreach ( WFS_Template::get_dietary_options() as $opt_value => $label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $opt_value ),
						selected( $value, $opt_value, false ),
						esc_html( $label )
					);
				}
				echo '</select>';
				break;

			case 'meal':
				echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '">';
				echo '<option value="">' . esc_html__( 'Auto (time of day)', 'weather-food-suggestion' ) . '</option>';
				foreach ( WFS_Template::get_meal_options() as $opt_value => $label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $opt_value ),
						selected( $value, $opt_value, false ),
						esc_html( $label )
					);
				}
				echo '</select>';
				break;

			case 'yesno':
				printf(
					'<label><input type="checkbox" name="%1$s" value="yes" %2$s /> %3$s</label>',
					esc_attr( $name ),
					checked( $value, 'yes', false ),
					esc_html__( 'Show “Use my current location” button', 'weather-food-suggestion' )
				);
				break;

			case 'textarea':
				printf(
					'<textarea name="%1$s" id="%2$s" rows="6" class="large-text code">%3$s</textarea>',
					esc_attr( $name ),
					esc_attr( $id ),
					esc_textarea( $value )
				);
				break;
		}
	}

	/**
	 * Render settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Configure defaults and weather thresholds for the suggestion engine. No user location or fridge data is stored.', 'weather-food-suggestion' ); ?></p>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'wfs_settings_group' );
				do_settings_sections( 'weather-food-suggestion' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
