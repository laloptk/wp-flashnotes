<?php

use WPFlashNotes\Events\EventHandler;
use WPFlashNotes\Managers\SyncManager;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\NoteSetRelationsRepository;
use WPFlashNotes\Repos\CardSetRelationsRepository;
use WPFlashNotes\Repos\ObjectUsageRepository;

defined( 'ABSPATH' ) || exit;

function wpflashnotes_bootstrap(): void {
	$notes_repo          = new NotesRepository();
	$cards_repo          = new CardsRepository();
	$sets_repo           = new SetsRepository();
	$note_relations_repo = new NoteSetRelationsRepository();
	$card_relations_repo = new CardSetRelationsRepository();
	$usage_repo          = new ObjectUsageRepository();

	$sync_manager = new SyncManager(
		$notes_repo,
		$cards_repo,
		$sets_repo,
		$note_relations_repo,
		$card_relations_repo,
		$usage_repo
	);

	$event_handler = new EventHandler( $sync_manager );

	// One hook handles posts, pages, CPTs, and studysets.
	add_action( 'save_post', array( $event_handler, 'on_post_save' ), 10, 3 );
}

wpflashnotes_bootstrap();
