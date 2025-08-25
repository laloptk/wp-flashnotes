<?php
/**
 * Bootstrap REST controllers for WP FlashNotes.
 *
 * - Registers all controllers on `rest_api_init`.
 * - Supports extension via the `wpfn_rest_controllers` filter.
 */

namespace WPFlashNotes\REST;

defined( 'ABSPATH' ) || exit;

/**
 * Default controllers shipped with the plugin.
 * You can filter this list with `wpfn_rest_controllers`.
 *
 * @return array<class-string>
 */
function wpfn_default_rest_controllers(): array {
	return array(
		\WPFlashNotes\REST\ObjectUsageController::class,
		\WPFlashNotes\REST\SetsController::class,
		\WPFlashNotes\REST\CardsController::class,
		\WPFlashNotes\REST\NotesController::class,
		\WPFlashNotes\REST\CardSetRelationsController::class,
		\WPFlashNotes\REST\NoteSetRelationsController::class,
		\WPFlashNotes\REST\TaxonomyRelationsController::class,
	);
}

/**
 * Hook into REST API init and register routes for each controller.
 */
add_action(
	'rest_api_init',
	function () {
		if ( ! function_exists( 'register_rest_route' ) ) {
			// Shouldn’t happen, but be defensive.
			error_log( '[WPFlashNotes][REST] register_rest_route() missing.' );
			return;
		}

		/**
		 * Filter: modify the list of controllers to register.
		 * Each item must be a class-string with a no-arg constructor,
		 * or an array like ['class' => MyController::class, 'args' => [...], 'enabled' => true].
		 *
		 * @param array $controllers
		 */
		$controllers = apply_filters( 'wpfn_rest_controllers', wpfn_default_rest_controllers() );

		foreach ( $controllers as $entry ) {
			$cls     = $entry;
			$args    = array();
			$enabled = true;

			if ( is_array( $entry ) ) {
				$cls     = $entry['class'] ?? '';
				$args    = $entry['args'] ?? array();
				$enabled = $entry['enabled'] ?? true;
			}

			if ( ! $enabled ) {
				continue;
			}

			if ( ! is_string( $cls ) || $cls === '' ) {
				error_log( '[WPFlashNotes][REST] Invalid controller entry: ' . print_r( $entry, true ) );
				continue;
			}

			if ( ! class_exists( $cls ) ) {
				error_log( '[WPFlashNotes][REST] Controller class not found: ' . $cls );
				continue;
			}

			try {
				$controller = new $cls( ...(array) $args );

				if ( ! method_exists( $controller, 'register_routes' ) ) {
					error_log( '[WPFlashNotes][REST] Missing register_routes() on controller: ' . $cls );
					continue;
				}

				$controller->register_routes();
			} catch ( \Throwable $e ) {
				error_log( '[WPFlashNotes][REST] Failed registering ' . $cls . ': ' . $e->getMessage() );
			}
		}
	},
	10
);
