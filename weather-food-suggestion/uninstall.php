<?php
/**
 * Uninstall Weather Food Suggestion.
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wfs_settings' );

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_wfs_weather_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wfs_weather_' ) . '%'
	)
);
