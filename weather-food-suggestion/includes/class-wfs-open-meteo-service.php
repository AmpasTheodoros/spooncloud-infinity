<?php
/**
 * Open-Meteo geocoding and forecast client.
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFS_Open_Meteo_Service
 */
class WFS_Open_Meteo_Service {

	const GEOCODING_URL = 'https://geocoding-api.open-meteo.com/v1/search';
	const FORECAST_URL  = 'https://api.open-meteo.com/v1/forecast';

	/**
	 * Geocode a city name.
	 *
	 * @param string $city City name.
	 * @return array|WP_Error Location data with lat, lon, label.
	 */
	public function geocode_city( $city ) {
		$city = trim( $city );

		if ( strlen( $city ) < 2 ) {
			return new WP_Error(
				'wfs_city_too_short',
				__( 'Please enter at least 2 characters for the city name.', 'weather-food-suggestion' ),
				array( 'status' => 400 )
			);
		}

		$url = add_query_arg(
			array(
				'name'     => $city,
				'count'    => 1,
				'language' => wfs_get_geocoding_language(),
				'format'   => 'json',
			),
			self::GEOCODING_URL
		);

		$response = $this->remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['results'] ) || ! is_array( $response['results'] ) ) {
			return new WP_Error(
				'wfs_location_not_found',
				__( 'Location not found. Try a different city name.', 'weather-food-suggestion' ),
				array( 'status' => 404 )
			);
		}

		$result = $response['results'][0];

		return array(
			'latitude'  => (float) $result['latitude'],
			'longitude' => (float) $result['longitude'],
			'label'     => $this->build_location_label( $result ),
			'timezone'  => isset( $result['timezone'] ) ? $result['timezone'] : '',
		);
	}

	/**
	 * Get current weather with transient cache.
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @return array|WP_Error Weather data and cache flag.
	 */
	public function get_current_weather( $latitude, $longitude ) {
		$cache_key = $this->get_cache_key( $latitude, $longitude );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			$cached['cached'] = true;
			return $cached;
		}

		$weather = $this->fetch_current_weather( $latitude, $longitude );

		if ( is_wp_error( $weather ) ) {
			return $weather;
		}

		$ttl = (int) apply_filters( 'wfs_cache_ttl', wfs_get_cache_ttl() );
		set_transient( $cache_key, $weather, $ttl );

		$weather['cached'] = false;
		return $weather;
	}

	/**
	 * Fetch current weather from API (no cache).
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @return array|WP_Error
	 */
	public function fetch_current_weather( $latitude, $longitude ) {
		$url = add_query_arg(
			array(
				'latitude'  => $latitude,
				'longitude' => $longitude,
				'current'   => 'temperature_2m,apparent_temperature,relative_humidity_2m,precipitation,weather_code,wind_speed_10m,is_day',
				'timezone'  => 'auto',
			),
			self::FORECAST_URL
		);

		$response = $this->remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['current'] ) || ! is_array( $response['current'] ) ) {
			return new WP_Error(
				'wfs_weather_invalid',
				__( 'Could not read weather data. Please try again later.', 'weather-food-suggestion' ),
				array( 'status' => 502 )
			);
		}

		$current = $response['current'];

		$temp        = isset( $current['temperature_2m'] ) ? (float) $current['temperature_2m'] : 0.0;
		$apparent    = isset( $current['apparent_temperature'] ) ? (float) $current['apparent_temperature'] : $temp;
		$precip      = isset( $current['precipitation'] ) ? (float) $current['precipitation'] : 0.0;
		$wind        = isset( $current['wind_speed_10m'] ) ? (float) $current['wind_speed_10m'] : 0.0;
		$code        = isset( $current['weather_code'] ) ? (int) $current['weather_code'] : 0;
		$humidity    = isset( $current['relative_humidity_2m'] ) ? (int) $current['relative_humidity_2m'] : 0;
		$is_day      = isset( $current['is_day'] ) ? (int) $current['is_day'] : 1;
		$condition   = $this->weather_code_to_key( $code );
		$summary     = $this->build_weather_summary( $temp, $code, $precip, $wind );

		return array(
			'summary'                => $summary,
			'temperature_c'          => $temp,
			'apparent_temperature_c' => $apparent,
			'precipitation_mm'       => $precip,
			'wind_speed_kmh'         => $wind,
			'weather_code'           => $code,
			'condition_key'          => $condition,
			'relative_humidity'      => $humidity,
			'is_day'                 => (bool) $is_day,
		);
	}

	/**
	 * Build transient cache key.
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @return string
	 */
	public function get_cache_key( $latitude, $longitude ) {
		return 'wfs_weather_' . md5( round( $latitude, 2 ) . '_' . round( $longitude, 2 ) );
	}

	/**
	 * HTTP GET helper.
	 *
	 * @param string $url URL.
	 * @return array|WP_Error
	 */
	private function remote_get( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => 'WeatherFoodSuggestion/' . WFS_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'wfs_api_unreachable',
				__( 'Weather service is temporarily unavailable.', 'weather-food-suggestion' ),
				array( 'status' => 502 )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error(
				'wfs_api_error',
				__( 'Weather service returned an error.', 'weather-food-suggestion' ),
				array( 'status' => 502 )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return new WP_Error(
				'wfs_api_invalid_json',
				__( 'Invalid response from weather service.', 'weather-food-suggestion' ),
				array( 'status' => 502 )
			);
		}

		if ( ! empty( $body['error'] ) ) {
			$reason = isset( $body['reason'] ) ? $body['reason'] : __( 'Unknown API error.', 'weather-food-suggestion' );
			return new WP_Error(
				'wfs_api_error',
				$reason,
				array( 'status' => 502 )
			);
		}

		return $body;
	}

	/**
	 * Build human-readable location label.
	 *
	 * @param array $result Geocoding result row.
	 * @return string
	 */
	private function build_location_label( $result ) {
		$parts = array();

		if ( ! empty( $result['name'] ) ) {
			$parts[] = $result['name'];
		}
		if ( ! empty( $result['admin1'] ) && ( empty( $result['name'] ) || $result['admin1'] !== $result['name'] ) ) {
			$parts[] = $result['admin1'];
		}
		if ( ! empty( $result['country'] ) ) {
			$parts[] = $result['country'];
		}

		$parts = array_unique( array_filter( $parts ) );

		return implode( ', ', $parts );
	}

	/**
	 * Map WMO weather code to internal key.
	 *
	 * @param int $code WMO code.
	 * @return string
	 */
	public function weather_code_to_key( $code ) {
		if ( in_array( $code, array( 0, 1 ), true ) ) {
			return 'clear';
		}
		if ( in_array( $code, array( 2, 3 ), true ) ) {
			return 'partly_cloudy';
		}
		if ( in_array( $code, array( 45, 48 ), true ) ) {
			return 'fog';
		}
		if ( in_array( $code, array( 51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82 ), true ) ) {
			return 'rain';
		}
		if ( in_array( $code, array( 71, 73, 75, 77, 85, 86 ), true ) ) {
			return 'snow';
		}
		if ( in_array( $code, array( 95, 96, 99 ), true ) ) {
			return 'thunderstorm';
		}
		return 'overcast';
	}

	/**
	 * Build short weather summary string.
	 *
	 * @param float $temp   Temperature C.
	 * @param int   $code   Weather code.
	 * @param float $precip Precipitation mm.
	 * @param float $wind   Wind km/h.
	 * @return string
	 */
	private function build_weather_summary( $temp, $code, $precip, $wind ) {
		$labels = array(
			0  => __( 'Clear sky', 'weather-food-suggestion' ),
			1  => __( 'Mainly clear', 'weather-food-suggestion' ),
			2  => __( 'Partly cloudy', 'weather-food-suggestion' ),
			3  => __( 'Overcast', 'weather-food-suggestion' ),
			45 => __( 'Foggy', 'weather-food-suggestion' ),
			48 => __( 'Depositing rime fog', 'weather-food-suggestion' ),
			51 => __( 'Light drizzle', 'weather-food-suggestion' ),
			53 => __( 'Drizzle', 'weather-food-suggestion' ),
			55 => __( 'Dense drizzle', 'weather-food-suggestion' ),
			61 => __( 'Slight rain', 'weather-food-suggestion' ),
			63 => __( 'Rain', 'weather-food-suggestion' ),
			65 => __( 'Heavy rain', 'weather-food-suggestion' ),
			71 => __( 'Slight snow', 'weather-food-suggestion' ),
			73 => __( 'Snow', 'weather-food-suggestion' ),
			75 => __( 'Heavy snow', 'weather-food-suggestion' ),
			80 => __( 'Rain showers', 'weather-food-suggestion' ),
			95 => __( 'Thunderstorm', 'weather-food-suggestion' ),
		);

		$condition = isset( $labels[ $code ] ) ? $labels[ $code ] : $this->weather_code_to_key( $code );

		/* translators: 1: condition label, 2: temperature */
		$summary = sprintf(
			__( '%1$s, %2$d°C', 'weather-food-suggestion' ),
			$condition,
			round( $temp )
		);

		if ( $precip > 0.2 ) {
			$summary .= ', ' . __( 'precipitation', 'weather-food-suggestion' );
		}

		if ( $wind >= 20 ) {
			$summary .= ', ' . __( 'windy', 'weather-food-suggestion' );
		}

		return $summary;
	}
}

/**
 * Get cache TTL from settings or default.
 *
 * @return int
 */
function wfs_get_cache_ttl() {
	$settings = get_option( 'wfs_settings', array() );
	if ( ! empty( $settings['cache_ttl'] ) ) {
		$ttl = absint( $settings['cache_ttl'] );
		if ( $ttl >= 300 && $ttl <= 3600 ) {
			return $ttl;
		}
	}
	return WFS_CACHE_TTL;
}

/**
 * Get plugin settings with defaults.
 *
 * @return array
 */
function wfs_get_settings() {
	$defaults = array(
		'cache_ttl'           => WFS_CACHE_TTL,
		'default_dietary'     => 'none',
		'default_meal_type'   => '',
		'enable_geolocation'  => 'yes',
		'temp_hot'            => 28,
		'temp_warm'           => 20,
		'temp_mild'           => 10,
		'wind_threshold'      => 30,
		'wind_temp_max'       => 15,
		'precip_threshold'    => 0.2,
		'custom_css'          => '',
	);

	$settings = get_option( 'wfs_settings', array() );

	return wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
}
