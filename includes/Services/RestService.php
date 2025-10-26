<?php
namespace WPFlashNotes\Services;

use WPFlashNotes\Core\ServiceInterface;

// Data layer
use WPFlashNotes\DataBase\PropagationService;
use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\CardSetRelationsRepository;
use WPFlashNotes\Repos\NoteSetRelationsRepository;
use WPFlashNotes\Repos\ObjectUsageRepository;

// Events
use WPFlashNotes\Events\EventHandler;

// REST controllers
use WPFlashNotes\REST\ObjectUsageController;
use WPFlashNotes\REST\SetsController;
use WPFlashNotes\REST\CardsController;
use WPFlashNotes\REST\NotesController;
use WPFlashNotes\REST\CardSetRelationsController;
use WPFlashNotes\REST\NoteSetRelationsController;
use WPFlashNotes\REST\TaxonomyRelationsController;
use WPFlashNotes\REST\SyncController;

defined( 'ABSPATH' ) || exit;

/**
 * RestService
 *
 * Registers all REST controllers for WP FlashNotes.
 */
final class RestService implements ServiceInterface {

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_rest_controllers' ] );
	}

	public function register_rest_controllers(): void {
		if ( ! function_exists( 'register_rest_route' ) ) {
			error_log( '[WPFlashNotes][REST] register_rest_route() missing.' );
			return;
		}

		$controllers = apply_filters( 'wpfn_rest_controllers', $this->default_controllers() );

		foreach ( $controllers as $entry ) {
			$cls     = $entry;
			$args    = [];
			$enabled = true;

			if ( is_array( $entry ) ) {
				$cls     = $entry['class'] ?? '';
				$args    = $entry['args'] ?? [];
				$enabled = $entry['enabled'] ?? true;
			}

			if ( ! $enabled || ! is_string( $cls ) || $cls === '' ) {
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
	}

	private function default_controllers(): array {
		$propagation = new PropagationService(
			new CardsRepository(),
			new NotesRepository(),
			new SetsRepository(),
			new CardSetRelationsRepository(),
			new NoteSetRelationsRepository(),
			new ObjectUsageRepository()
		);

		$event_handler = new EventHandler( $propagation );

		return [
			ObjectUsageController::class,
			SetsController::class,
			CardsController::class,
			NotesController::class,
			CardSetRelationsController::class,
			NoteSetRelationsController::class,
			TaxonomyRelationsController::class,
			[
				'class' => SyncController::class,
				'args'  => [ $event_handler ],
			],
		];
	}
}
