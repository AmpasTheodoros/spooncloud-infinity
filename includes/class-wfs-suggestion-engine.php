<?php
/**
 * Rule-based meal suggestion engine.
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFS_Suggestion_Engine
 */
class WFS_Suggestion_Engine {

	const RAIN_CODES = array( 51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82, 95, 96, 99 );
	const SNOW_CODES = array( 71, 73, 75, 77, 85, 86 );
	const CLEAR_CODES = array( 0, 1 );

	/**
	 * Recipe catalog.
	 *
	 * @var array
	 */
	private $recipes = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->recipes = include WFS_PLUGIN_DIR . 'includes/data/recipes.php';
	}

	/**
	 * Generate suggestion.
	 *
	 * @param array $weather Weather snapshot.
	 * @param array $context User context.
	 * @return array
	 */
	public function suggest( array $weather, array $context ) {
		$fridge_items = $this->normalize_fridge_items( isset( $context['fridge_items'] ) ? $context['fridge_items'] : array() );
		$dietary      = isset( $context['dietary'] ) ? $context['dietary'] : 'none';
		$meal_type    = $this->resolve_meal_type(
			isset( $context['meal_type'] ) ? $context['meal_type'] : '',
			isset( $weather['is_day'] ) ? $weather['is_day'] : true
		);

		$weather_tags = $this->derive_weather_tags( $weather, $meal_type, $dietary );
		$pool         = $this->filter_recipes( $dietary, $meal_type );

		if ( empty( $pool ) ) {
			$pool = $this->recipes;
		}

		$scored = $this->score_recipes( $pool, $weather_tags, $fridge_items, $dietary );

		if ( empty( $scored ) ) {
			$scored = $this->score_recipes( $this->recipes, $weather_tags, $fridge_items, $dietary, true );
		}

		usort(
			$scored,
			function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					if ( $a['match_count'] === $b['match_count'] ) {
						return strcmp( $a['recipe']['id'], $b['recipe']['id'] );
					}
					return $b['match_count'] - $a['match_count'];
				}
				return $b['score'] - $a['score'];
			}
		);

		$winner = $scored[0];
		$recipe = wfs_localize_recipe( $winner['recipe'] );

		$matched  = $winner['matched'];
		$missing  = array_values( array_diff( $winner['recipe']['ingredients'], $matched ) );
		$location = isset( $context['location_label'] ) ? $context['location_label'] : '';

		$result = array(
			'title'               => $recipe['title'],
			'reason'              => $this->build_reason( $weather, $weather_tags, $matched, $dietary, $meal_type, $location, $recipe, $winner['best_effort'] ),
			'ingredients_used'    => wfs_translate_ingredient_list( $matched ),
			'ingredients_missing' => wfs_translate_ingredient_list( $missing ),
			'steps'               => $recipe['steps'],
		);

		return apply_filters( 'wfs_suggestion_result', $result, $weather, $context );
	}

	/**
	 * Normalize fridge item strings.
	 *
	 * @param array $items Raw items.
	 * @return array
	 */
	public function normalize_fridge_items( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $items as $item ) {
			$item = wfs_canonical_ingredient( (string) $item );
			$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $item, 'UTF-8' ) : strlen( $item );

			if ( $len >= 2 && $len <= 64 ) {
				$normalized[] = $item;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Parse fridge textarea into items.
	 *
	 * @param string $text Raw textarea.
	 * @return array
	 */
	public function parse_fridge_text( $text ) {
		$text = wp_strip_all_tags( $text );
		$parts = preg_split( '/[\n,;]+/', $text );
		return $this->normalize_fridge_items( $parts );
	}

	/**
	 * Resolve meal type from input or time of day.
	 *
	 * @param string $meal_type User meal type.
	 * @param bool   $is_day    Whether it is day per weather API.
	 * @return string
	 */
	public function resolve_meal_type( $meal_type, $is_day ) {
		$allowed = array( 'breakfast', 'lunch', 'dinner', 'snack' );
		if ( in_array( $meal_type, $allowed, true ) ) {
			return $meal_type;
		}

		$tz   = wp_timezone();
		$now  = new DateTime( 'now', $tz );
		$hour = (int) $now->format( 'G' );

		if ( $hour >= 5 && $hour <= 10 ) {
			return 'breakfast';
		}
		if ( $hour >= 11 && $hour <= 15 ) {
			return 'lunch';
		}
		if ( $hour >= 16 && $hour <= 21 ) {
			return 'dinner';
		}

		return $is_day ? 'snack' : 'dinner';
	}

	/**
	 * Derive weighted weather tags.
	 *
	 * @param array  $weather   Weather data.
	 * @param string $meal_type Meal type.
	 * @param string $dietary   Dietary preference.
	 * @return array Tag => weight.
	 */
	public function derive_weather_tags( array $weather, $meal_type, $dietary ) {
		$settings = wfs_get_settings();
		$tags     = array();

		$temp     = isset( $weather['apparent_temperature_c'] ) ? (float) $weather['apparent_temperature_c'] : (float) $weather['temperature_c'];
		$precip   = isset( $weather['precipitation_mm'] ) ? (float) $weather['precipitation_mm'] : 0.0;
		$wind     = isset( $weather['wind_speed_kmh'] ) ? (float) $weather['wind_speed_kmh'] : 0.0;
		$code     = isset( $weather['weather_code'] ) ? (int) $weather['weather_code'] : 0;
		$humidity = isset( $weather['relative_humidity'] ) ? (int) $weather['relative_humidity'] : 0;

		$hot   = (float) $settings['temp_hot'];
		$warm  = (float) $settings['temp_warm'];
		$mild  = (float) $settings['temp_mild'];

		if ( $temp >= $hot ) {
			$tags['hot']   = 3;
			$tags['light'] = 2;
		} elseif ( $temp >= $warm ) {
			$tags['warm']  = 2;
			$tags['light'] = 1;
		} elseif ( $temp >= $mild ) {
			$tags['mild'] = 2;
		} else {
			$tags['cold']      = 3;
			$tags['warm_meal'] = 2;
		}

		if ( $humidity >= 75 && $temp >= $warm ) {
			$tags['light'] = isset( $tags['light'] ) ? $tags['light'] + 1 : 1;
		}

		$precip_threshold = (float) $settings['precip_threshold'];
		if ( $precip > $precip_threshold || in_array( $code, self::RAIN_CODES, true ) ) {
			$tags['rainy']   = 3;
			$tags['comfort'] = 2;
		}

		if ( in_array( $code, self::SNOW_CODES, true ) ) {
			$tags['snow']      = 2;
			$tags['cold']      = isset( $tags['cold'] ) ? $tags['cold'] + 1 : 2;
			$tags['comfort']   = isset( $tags['comfort'] ) ? $tags['comfort'] + 1 : 2;
			$tags['warm_meal'] = isset( $tags['warm_meal'] ) ? $tags['warm_meal'] + 1 : 2;
		}

		if ( in_array( $code, self::CLEAR_CODES, true ) && $precip <= $precip_threshold ) {
			$tags['sunny'] = 2;
			$tags['fresh'] = 2;
		}

		$wind_threshold = (float) $settings['wind_threshold'];
		$wind_temp_max  = (float) $settings['wind_temp_max'];
		if ( $wind >= $wind_threshold && $temp < $wind_temp_max ) {
			$tags['windy']         = 2;
			$tags['high_calorie']  = 2;
			$tags['warm_meal']     = isset( $tags['warm_meal'] ) ? $tags['warm_meal'] + 1 : 1;
		}

		$meal_weights = array(
			'breakfast' => 3,
			'lunch'     => 2,
			'dinner'    => 2,
			'snack'     => 2,
		);
		if ( isset( $meal_weights[ $meal_type ] ) ) {
			$tags[ $meal_type ] = $meal_weights[ $meal_type ];
		}

		if ( 'high_protein' === $dietary ) {
			$tags['high_protein'] = 2;
		}
		if ( 'light_meal' === $dietary ) {
			$tags['light'] = isset( $tags['light'] ) ? $tags['light'] + 2 : 2;
			$tags['fresh'] = isset( $tags['fresh'] ) ? $tags['fresh'] + 1 : 1;
		}
		if ( 'comfort_food' === $dietary ) {
			$tags['comfort'] = isset( $tags['comfort'] ) ? $tags['comfort'] + 2 : 2;
		}

		return $tags;
	}

	/**
	 * Filter recipes by dietary and meal type.
	 *
	 * @param string $dietary   Dietary key.
	 * @param string $meal_type Meal type.
	 * @return array
	 */
	private function filter_recipes( $dietary, $meal_type ) {
		$filtered = array();

		foreach ( $this->recipes as $recipe ) {
			if ( ! $this->recipe_matches_diet( $recipe, $dietary ) ) {
				continue;
			}
			if ( ! in_array( $meal_type, $recipe['meal_types'], true ) ) {
				continue;
			}
			$filtered[] = $recipe;
		}

		return $filtered;
	}

	/**
	 * Check dietary compatibility.
	 *
	 * @param array  $recipe  Recipe.
	 * @param string $dietary Dietary key.
	 * @return bool
	 */
	private function recipe_matches_diet( $recipe, $dietary ) {
		if ( 'none' === $dietary ) {
			return true;
		}

		$diet_map = array(
			'vegetarian'   => array( 'none', 'vegetarian' ),
			'vegan'        => array( 'vegan' ),
			'high_protein' => array( 'none', 'vegetarian', 'high_protein' ),
			'light_meal'   => array( 'none', 'vegetarian', 'vegan', 'light_meal' ),
			'comfort_food' => array( 'none', 'vegetarian', 'comfort_food' ),
		);

		if ( ! isset( $diet_map[ $dietary ] ) ) {
			return in_array( 'none', $recipe['diets'], true );
		}

		foreach ( $diet_map[ $dietary ] as $allowed ) {
			if ( in_array( $allowed, $recipe['diets'], true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Score recipes.
	 *
	 * @param array $pool         Recipe pool.
	 * @param array $weather_tags Tag weights.
	 * @param array $fridge_items Normalized fridge items.
	 * @param string $dietary     Dietary preference.
	 * @param bool  $force        Skip zero-match exclusion.
	 * @return array
	 */
	private function score_recipes( $pool, $weather_tags, $fridge_items, $dietary, $force = false ) {
		$scored = array();
		$has_fridge = ! empty( $fridge_items );

		foreach ( $pool as $recipe ) {
			$matched = $this->match_ingredients( $fridge_items, $recipe['ingredients'] );
			$match_count = count( $matched );

			if ( $has_fridge && 0 === $match_count && ! $force ) {
				continue;
			}

			$score = 0;

			foreach ( $weather_tags as $tag => $weight ) {
				if ( in_array( $tag, $recipe['tags'], true ) ) {
					$score += (int) $weight;
				}
			}

			$score += $match_count * 10;
			if ( ! empty( $recipe['ingredients'] ) ) {
				$score += ( $match_count / count( $recipe['ingredients'] ) ) * 15;
			}

			$core = array_slice( $recipe['ingredients'], 0, 3 );
			$core_matched = array_intersect( $matched, $core );
			if ( count( $core_matched ) === count( $core ) && count( $core ) > 0 ) {
				$score += 8;
			}

			if ( $has_fridge && 0 === $match_count ) {
				$score -= 50;
			}

			$scored[] = array(
				'recipe'      => $recipe,
				'score'       => $score,
				'matched'     => $matched,
				'match_count' => $match_count,
				'best_effort' => $force && 0 === $match_count,
			);
		}

		return $scored;
	}

	/**
	 * Match fridge items to recipe ingredients (fuzzy contains).
	 *
	 * @param array $fridge       Fridge items.
	 * @param array $ingredients   Recipe ingredients.
	 * @return array Matched ingredient names from recipe list.
	 */
	private function match_ingredients( $fridge, $ingredients ) {
		$matched = array();

		foreach ( $ingredients as $ingredient ) {
			foreach ( $fridge as $item ) {
				if ( $item === $ingredient || false !== strpos( $item, $ingredient ) || false !== strpos( $ingredient, $item ) ) {
					$matched[] = $ingredient;
					break;
				}
			}
		}

		return array_values( array_unique( $matched ) );
	}

	/**
	 * Build human-readable reason string.
	 *
	 * @param array  $weather      Weather.
	 * @param array  $tags         Weather tags.
	 * @param array  $matched      Matched ingredients.
	 * @param string $dietary      Dietary.
	 * @param string $meal_type    Meal type.
	 * @param string $location     Location label.
	 * @param array  $recipe       Recipe.
	 * @param bool   $best_effort  Whether best-effort match.
	 * @return string
	 */
	private function build_reason( $weather, $tags, $matched, $dietary, $meal_type, $location, $recipe, $best_effort ) {
		$temp   = round( isset( $weather['temperature_c'] ) ? $weather['temperature_c'] : 0 );
		$parts  = array();

		if ( $location ) {
			/* translators: 1: temperature, 2: location */
			$parts[] = sprintf(
				__( "It's %1\$d°C in %2\$s.", 'weather-food-suggestion' ),
				$temp,
				$location
			);
		} else {
			/* translators: %d: temperature */
			$parts[] = sprintf( __( "It's %d°C where you are.", 'weather-food-suggestion' ), $temp );
		}

		if ( ! empty( $tags['rainy'] ) || ! empty( $tags['snow'] ) ) {
			$parts[] = __( 'Rainy or snowy weather pairs well with comforting, warming dishes.', 'weather-food-suggestion' );
		} elseif ( ! empty( $tags['hot'] ) ) {
			$parts[] = __( 'Hot weather calls for lighter, refreshing meals.', 'weather-food-suggestion' );
		} elseif ( ! empty( $tags['cold'] ) ) {
			$parts[] = __( 'Cold weather is perfect for warm, satisfying food.', 'weather-food-suggestion' );
		} elseif ( ! empty( $tags['sunny'] ) ) {
			$parts[] = __( 'Clear skies are great for fresh, vibrant flavors.', 'weather-food-suggestion' );
		}

		if ( ! empty( $tags['windy'] ) ) {
			$parts[] = __( 'Windy conditions suggest something hearty and energizing.', 'weather-food-suggestion' );
		}

		if ( ! empty( $matched ) ) {
			$matched_labels = wfs_translate_ingredient_list( $matched );
			/* translators: %s: comma-separated ingredients */
			$parts[] = sprintf(
				__( 'We prioritized your %s.', 'weather-food-suggestion' ),
				implode( ', ', $matched_labels )
			);
		} elseif ( $best_effort ) {
			$parts[] = __( 'None of your listed items matched closely; here is the best weather-fit suggestion.', 'weather-food-suggestion' );
		}

		if ( 'none' !== $dietary ) {
			$labels = array(
				'vegetarian'   => __( 'vegetarian', 'weather-food-suggestion' ),
				'vegan'        => __( 'vegan', 'weather-food-suggestion' ),
				'high_protein' => __( 'high-protein', 'weather-food-suggestion' ),
				'light_meal'   => __( 'light', 'weather-food-suggestion' ),
				'comfort_food' => __( 'comfort', 'weather-food-suggestion' ),
			);
			if ( isset( $labels[ $dietary ] ) ) {
				/* translators: %s: dietary label */
				$parts[] = sprintf( __( 'Filtered for a %s preference.', 'weather-food-suggestion' ), $labels[ $dietary ] );
			}
		}

		$meal_labels = array(
			'breakfast' => __( 'breakfast', 'weather-food-suggestion' ),
			'lunch'     => __( 'lunch', 'weather-food-suggestion' ),
			'dinner'    => __( 'dinner', 'weather-food-suggestion' ),
			'snack'     => __( 'snack', 'weather-food-suggestion' ),
		);
		if ( isset( $meal_labels[ $meal_type ] ) ) {
			/* translators: %s: meal type */
			$parts[] = sprintf( __( 'Suggested as a %s option.', 'weather-food-suggestion' ), $meal_labels[ $meal_type ] );
		}

		if ( ! empty( $recipe['base_reason'] ) ) {
			$parts[] = $recipe['base_reason'];
		}

		return implode( ' ', $parts );
	}
}
