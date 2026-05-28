<?php
/**
 * REST API endpoints.
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFS_REST_API
 */
class WFS_REST_API {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			WFS_REST_NAMESPACE,
			'/suggest',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_suggest' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => $this->get_endpoint_args(),
			)
		);
	}

	/**
	 * Public endpoint; nonce verified in handler.
	 *
	 * @return bool
	 */
	public function permission_check() {
		return true;
	}

	/**
	 * REST argument schemas.
	 *
	 * @return array
	 */
	private function get_endpoint_args() {
		return array(
			'city' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return is_string( $value );
				},
			),
			'latitude' => array(
				'type'              => 'number',
				'validate_callback' => array( $this, 'validate_latitude' ),
			),
			'longitude' => array(
				'type'              => 'number',
				'validate_callback' => array( $this, 'validate_longitude' ),
			),
			'fridge_items' => array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_fridge_items' ),
			),
			'fridge_text' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'dietary' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_dietary' ),
				'default'           => 'none',
			),
			'meal_type' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_meal_type' ),
				'default'           => '',
			),
			'nonce' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Handle POST /suggest.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_suggest( WP_REST_Request $request ) {
		if ( ! $this->verify_nonce( $request ) ) {
			return new WP_Error(
				'wfs_invalid_nonce',
				__( 'Invalid security token. Please refresh the page.', 'weather-food-suggestion' ),
				array( 'status' => 403 )
			);
		}

		$city      = trim( (string) $request->get_param( 'city' ) );
		$latitude  = $request->get_param( 'latitude' );
		$longitude = $request->get_param( 'longitude' );

		$has_city = strlen( $city ) >= 2;
		$has_coords = is_numeric( $latitude ) && is_numeric( $longitude );

		if ( ! $has_city && ! $has_coords ) {
			return new WP_Error(
				'wfs_missing_location',
				__( 'Please enter a city or use your current location.', 'weather-food-suggestion' ),
				array( 'status' => 400 )
			);
		}

		$meteo = new WFS_Open_Meteo_Service();
		$label = '';

		if ( $has_city ) {
			$geo = $meteo->geocode_city( $city );
			if ( is_wp_error( $geo ) ) {
				return $geo;
			}
			$latitude  = $geo['latitude'];
			$longitude = $geo['longitude'];
			$label     = $geo['label'];
		} else {
			$latitude  = (float) $latitude;
			$longitude = (float) $longitude;
			/* translators: 1: latitude, 2: longitude */
			$label = sprintf(
				__( 'Your location (%.2f, %.2f)', 'weather-food-suggestion' ),
				$latitude,
				$longitude
			);
		}

		$weather = $meteo->get_current_weather( $latitude, $longitude );
		if ( is_wp_error( $weather ) ) {
			return $weather;
		}

		$cached = ! empty( $weather['cached'] );
		unset( $weather['cached'] );

		$engine = new WFS_Suggestion_Engine();
		$fridge = $request->get_param( 'fridge_items' );

		if ( empty( $fridge ) || ! is_array( $fridge ) ) {
			$fridge_text = (string) $request->get_param( 'fridge_text' );
			$fridge      = $engine->parse_fridge_text( $fridge_text );
		} else {
			$fridge = $engine->normalize_fridge_items( $fridge );
		}

		if ( count( $fridge ) > 20 ) {
			$fridge = array_slice( $fridge, 0, 20 );
		}

		$dietary   = $this->sanitize_dietary( (string) $request->get_param( 'dietary' ) );
		$meal_type = $this->sanitize_meal_type( (string) $request->get_param( 'meal_type' ) );

		$context = array(
			'fridge_items'    => $fridge,
			'dietary'         => $dietary,
			'meal_type'       => $meal_type,
			'location_label'  => $label,
		);

		do_action( 'wfs_before_suggest', $context );

		$provider   = wfs_get_suggestion_provider();
		$suggestion = $provider->suggest( $weather, $context );

		$response = array(
			'location_label' => $label,
			'weather'          => $weather,
			'suggestion'       => $suggestion,
			'meta'             => array(
				'cached_weather' => $cached,
				'engine'         => 'rules_v1',
			),
		);

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			unset( $response['meta']['cached_weather'] );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Verify REST nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private function verify_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( 'nonce' );
		}
		return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Validate latitude.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function validate_latitude( $value ) {
		if ( null === $value || '' === $value ) {
			return true;
		}
		return is_numeric( $value ) && (float) $value >= -90 && (float) $value <= 90;
	}

	/**
	 * Validate longitude.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function validate_longitude( $value ) {
		if ( null === $value || '' === $value ) {
			return true;
		}
		return is_numeric( $value ) && (float) $value >= -180 && (float) $value <= 180;
	}

	/**
	 * Validate dietary enum.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public function validate_dietary( $value ) {
		return in_array( $value, $this->get_dietary_options(), true );
	}

	/**
	 * Validate meal type enum.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public function validate_meal_type( $value ) {
		if ( '' === $value ) {
			return true;
		}
		return in_array( $value, array( 'breakfast', 'lunch', 'dinner', 'snack' ), true );
	}

	/**
	 * Sanitize fridge items array.
	 *
	 * @param mixed $value Value.
	 * @return array
	 */
	public function sanitize_fridge_items( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$engine = new WFS_Suggestion_Engine();
		return $engine->normalize_fridge_items( $value );
	}

	/**
	 * Get dietary options.
	 *
	 * @return array
	 */
	public function get_dietary_options() {
		return array( 'none', 'vegetarian', 'vegan', 'high_protein', 'light_meal', 'comfort_food' );
	}

	/**
	 * Sanitize dietary value.
	 *
	 * @param string $dietary Dietary.
	 * @return string
	 */
	private function sanitize_dietary( $dietary ) {
		return in_array( $dietary, $this->get_dietary_options(), true ) ? $dietary : 'none';
	}

	/**
	 * Sanitize meal type.
	 *
	 * @param string $meal_type Meal type.
	 * @return string
	 */
	private function sanitize_meal_type( $meal_type ) {
		$allowed = array( 'breakfast', 'lunch', 'dinner', 'snack', '' );
		return in_array( $meal_type, $allowed, true ) ? $meal_type : '';
	}
}
