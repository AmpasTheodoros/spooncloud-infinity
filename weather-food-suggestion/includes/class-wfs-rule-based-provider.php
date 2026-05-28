<?php
/**
 * Rule-based suggestion provider.
 *
 * @package WeatherFoodSuggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFS_Rule_Based_Provider
 */
class WFS_Rule_Based_Provider implements WFS_Suggestion_Provider_Interface {

	/**
	 * Suggestion engine.
	 *
	 * @var WFS_Suggestion_Engine
	 */
	private $engine;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->engine = new WFS_Suggestion_Engine();
	}

	/**
	 * {@inheritdoc}
	 */
	public function suggest( array $weather, array $context ) {
		return $this->engine->suggest( $weather, $context );
	}
}

/**
 * Get active suggestion provider (filterable for future LLM).
 *
 * @return WFS_Suggestion_Provider_Interface
 */
function wfs_get_suggestion_provider() {
	$provider = new WFS_Rule_Based_Provider();
	$provider = apply_filters( 'wfs_suggestion_provider', $provider );

	if ( ! $provider instanceof WFS_Suggestion_Provider_Interface ) {
		return new WFS_Rule_Based_Provider();
	}

	return $provider;
}
