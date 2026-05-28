=== Weather Food Suggestion ===
Contributors: spooncloud
Tags: weather, food, recipes, elementor, shortcode
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Suggest meals based on local weather and ingredients in your fridge.

== Description ==

Display a card-style widget that asks for city or geolocation, fridge items, dietary preference, and meal type. Uses the free Open-Meteo API (no API key). Works as shortcode `[weather_food_suggestion]` and as an Elementor widget.

== Installation ==

1. Upload the `weather-food-suggestion` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu.
3. Add the shortcode to any page or use the Elementor widget.

== Greek language ==

Set WordPress site language to Ελληνικά (el_GR) under Settings → General. The plugin UI, weather summaries, recipe titles, steps, and ingredient labels will appear in Greek. You can type fridge items in Greek (e.g. ντομάτα, αυγά) or English.

Optional: compile `languages/weather-food-suggestion-el_GR.po` to `.mo` with Loco Translate or `msgfmt` for standard WordPress i18n loading.

== Shortcode ==

`[weather_food_suggestion]`

Attributes: title, dietary, meal_type, show_geolocation, button_label

== Changelog ==

= 1.0.0 =
* Initial release.
