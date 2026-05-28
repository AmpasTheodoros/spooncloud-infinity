<?php
/**
 * Suggestion provider interface (rules today, LLM later).
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WFS_Suggestion_Provider_Interface
 */
interface WFS_Suggestion_Provider_Interface {

	/**
	 * Build a suggestion from weather and user context.
	 *
	 * @param array $weather Normalized weather snapshot.
	 * @param array $context User context (fridge, dietary, meal_type, location_label).
	 * @return array Suggestion payload.
	 */
	public function suggest( array $weather, array $context );
}
