<?php

use WPFlashNotes\Events\EventHandler;
use WPFlashNotes\Managers\StudySetManager;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\NoteSetRelationsRepository;
use WPFlashNotes\Repos\CardSetRelationsRepository;
use WPFlashNotes\Repos\ObjectUsageRepository;

defined('ABSPATH') || exit;

function wpflashnotes_bootstrap(): void
{
    // Instantiate repos
    $notesRepo         = new NotesRepository();
    $cardsRepo         = new CardsRepository();
    $setsRepo          = new SetsRepository();
    $noteRelationsRepo = new NoteSetRelationsRepository();
    $cardRelationsRepo = new CardSetRelationsRepository();
    $usageRepo         = new ObjectUsageRepository();

    // Manager
    $manager = new StudySetManager(
        $notesRepo,
        $cardsRepo,
        $setsRepo,
        $noteRelationsRepo,
        $cardRelationsRepo,
        $usageRepo
    );

    // Event handler
    $eventHandler = new EventHandler($manager);

    // Register hooks
    add_action('save_post', [$eventHandler, 'on_post_save'], 10, 3);
    add_action('save_post_studyset', [$eventHandler, 'on_studyset_save'], 10, 3);
    add_action('rest_after_insert_studyset', [$eventHandler, 'on_studyset_create'], 10, 3);
    add_action('pre_post_update', [$eventHandler, 'on_studyset_before_update'], 10, 2);
    add_action('transition_post_status', function($new_status, $old_status, $post) use ($eventHandler) {
        if ($old_status === 'future' && $new_status === 'publish') {
            $eventHandler->on_studyset_publish($post->ID, $post);
        }
    }, 10, 3);
}

wpflashnotes_bootstrap();
