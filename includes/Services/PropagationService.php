<?php
namespace WPFlashNotes\Services;

use WPFlashNotes\Core\ServiceInterface;

// Data layer
use WPFlashNotes\DataBase\DataPropagation;
use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\CardSetRelationsRepository;
use WPFlashNotes\Repos\NoteSetRelationsRepository;
use WPFlashNotes\Repos\ObjectUsageRepository;

// Events
use WPFlashNotes\Events\EventHandler;

defined('ABSPATH') || exit;

/**
 * PropagationService
 *
 * Bootstraps the event handler that manages synchronization between
 * Gutenberg blocks and the FlashNotes database.
 *
 * This class replaces the old `includes/DataBase/bootstrap.php` logic.
 */
final class PropagationService implements ServiceInterface {

	/**
	 * Registers hooks.
	 * This ensures that propagation is initialized after CPTs and REST.
	 */
	public function register(): void {
		add_action('init', [ $this, 'bootstrap' ], 20);
	}

	/**
	 * Bootstraps the propagation system and event handler.
	 * Runs only once per request.
	 */
	public function bootstrap(): void {
		static $initialized = false;
		if ($initialized) {
			return;
		}
		$initialized = true;

		$propagation = new DataPropagation(
			new CardsRepository(),
			new NotesRepository(),
			new SetsRepository(),
			new CardSetRelationsRepository(),
			new NoteSetRelationsRepository(),
			new ObjectUsageRepository()
		);

		$event_handler = new EventHandler($propagation);
		$event_handler->register();

		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('PropagationService initialized (EventHandler registered) at: init');
		}
	}
}
