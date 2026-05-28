<?php
/**
 * Locale helpers and ingredient translation.
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'gettext_weather-food-suggestion', 'wfs_filter_gettext', 10, 2 );

/**
 * Load Greek UI strings when .mo is not compiled.
 *
 * @param string $translated Translated.
 * @param string $text       Original.
 * @return string
 */
function wfs_filter_gettext( $translated, $text ) {
	if ( ! wfs_is_greek() ) {
		return $translated;
	}

	static $map = null;
	if ( null === $map ) {
		$file = WFS_PLUGIN_DIR . 'languages/translations-el.php';
		$map  = file_exists( $file ) ? include $file : array();
	}

	return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
}

/**
 * Whether the site/user locale is Greek.
 *
 * @return bool
 */
function wfs_is_greek() {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	return 0 === strpos( $locale, 'el' );
}

/**
 * Open-Meteo geocoding language code.
 *
 * @return string
 */
function wfs_get_geocoding_language() {
	return wfs_is_greek() ? 'el' : 'en';
}

/**
 * Ingredient alias map (variants => canonical English key used in recipes).
 *
 * @return array
 */
function wfs_get_ingredient_aliases() {
	static $aliases = null;
	if ( null !== $aliases ) {
		return $aliases;
	}
	$aliases = include WFS_PLUGIN_DIR . 'includes/data/ingredient-aliases.php';
	return $aliases;
}

/**
 * Greek display labels for canonical ingredient keys.
 *
 * @return array
 */
function wfs_get_ingredient_labels_el() {
	static $labels = null;
	if ( null !== $labels ) {
		return $labels;
	}
	$labels = include WFS_PLUGIN_DIR . 'includes/data/ingredient-labels-el.php';
	return $labels;
}

/**
 * Greek recipe text overlays by recipe id.
 *
 * @return array
 */
function wfs_get_recipes_i18n_el() {
	static $i18n = null;
	if ( null !== $i18n ) {
		return $i18n;
	}
	$i18n = include WFS_PLUGIN_DIR . 'includes/data/recipes-i18n-el.php';
	return $i18n;
}

/**
 * Normalize a single fridge/ingredient token to canonical English key.
 *
 * @param string $item Raw item.
 * @return string
 */
function wfs_canonical_ingredient( $item ) {
	$item = wfs_normalize_ingredient_token( $item );
	if ( '' === $item ) {
		return '';
	}

	$aliases = wfs_get_ingredient_aliases();
	if ( isset( $aliases[ $item ] ) ) {
		return $aliases[ $item ];
	}

	return $item;
}

/**
 * Normalize ingredient string (Unicode-safe).
 *
 * @param string $item Item.
 * @return string
 */
function wfs_normalize_ingredient_token( $item ) {
	$item = wp_strip_all_tags( (string) $item );
	$item = trim( $item );
	$item = mb_strtolower( $item, 'UTF-8' );
	$item = preg_replace( '/[^\p{L}\p{N}\s\-]/u', '', $item );
	$item = preg_replace( '/\s+/u', ' ', $item );
	return $item;
}

/**
 * Display label for an ingredient key in current locale.
 *
 * @param string $canonical Canonical English key.
 * @return string
 */
function wfs_ingredient_label( $canonical ) {
	if ( wfs_is_greek() ) {
		$labels = wfs_get_ingredient_labels_el();
		if ( isset( $labels[ $canonical ] ) ) {
			return $labels[ $canonical ];
		}
	}
	return $canonical;
}

/**
 * Translate ingredient list for API response.
 *
 * @param array $canonical_list Canonical keys.
 * @return array
 */
function wfs_translate_ingredient_list( $canonical_list ) {
	return array_map( 'wfs_ingredient_label', $canonical_list );
}

/**
 * Apply Greek overlay to a recipe for display.
 *
 * @param array $recipe Recipe row.
 * @return array
 */
function wfs_localize_recipe( $recipe ) {
	if ( ! wfs_is_greek() ) {
		return $recipe;
	}

	$i18n = wfs_get_recipes_i18n_el();
	$id   = isset( $recipe['id'] ) ? $recipe['id'] : '';

	if ( ! isset( $i18n[ $id ] ) ) {
		return $recipe;
	}

	$overlay = $i18n[ $id ];
	if ( ! empty( $overlay['title'] ) ) {
		$recipe['title'] = $overlay['title'];
	}
	if ( ! empty( $overlay['steps'] ) ) {
		$recipe['steps'] = $overlay['steps'];
	}
	if ( ! empty( $overlay['base_reason'] ) ) {
		$recipe['base_reason'] = $overlay['base_reason'];
	}

	return $recipe;
}
