<?php

namespace WPFlashNotes\Dev;

defined('ABSPATH') || exit;

use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\Repos\CardsRepository;

/**
 * Run CRUD smoke tests for notes or cards.
 *
 * @param string $entity 'notes'|'cards'
 * @return array{entity:string,inserted_id:int|null,steps:array<string,mixed>,ok:bool}
 */
function wpfn_crud_smoke_test(string $entity = 'notes'): array
{
    $entity = strtolower($entity);
    $steps  = [];
    $ok     = true;
    $inserted_id = null;

    try {
        if ($entity === 'notes') {
            $repo = new NotesRepository();

            // INSERT
            $inserted_id = $repo->insert([
                'title'   => 'Smoke Note ' . wp_generate_uuid4(),
                'content' => '<p>CRUD smoke test content.</p>',
            ]);
            $steps['insert'] = ['id' => $inserted_id];
            error_log("[WPFlashNotes][CRUD] notes insert id={$inserted_id}");

            // READ
            $fetched = $repo->read($inserted_id);
            $steps['read'] = ['found' => (bool) $fetched, 'row' => $fetched];
            if (!$fetched || (int) $fetched['id'] !== $inserted_id) {
                throw new \RuntimeException('notes read failed');
            }

            // UPDATE (partial)
            $updated = $repo->update($inserted_id, [
                'title' => 'Smoke Note (updated)',
            ]);
            $steps['update'] = ['updated' => $updated];
            $fetched2 = $repo->read($inserted_id);
            $steps['read_after_update'] = ['title' => $fetched2['title'] ?? null];

            // SOFT DELETE
            $soft_deleted = $repo->soft_delete($inserted_id);
            $steps['soft_delete'] = ['updated' => $soft_deleted];
            $fetched3 = $repo->read($inserted_id);
            $steps['read_after_soft_delete'] = ['deleted_at' => $fetched3['deleted_at'] ?? null];

            // HARD DELETE
            $hard_deleted = $repo->delete($inserted_id);
            $steps['delete'] = ['deleted' => $hard_deleted];
            $fetched4 = $repo->read($inserted_id);
            $steps['read_after_delete'] = ['found' => (bool) $fetched4];
        }

        elseif ($entity === 'cards') {
            $repo = new CardsRepository();

            // INSERT (multiple_choice example)
            $inserted_id = $repo->insert([
                'question'           => '<p>Capital of France?</p>',
                'answers_json'       => ['Paris','Berlin','Rome','Madrid'],
                'right_answers_json' => ['Paris'],
                'card_type'          => 'multiple_choice',
            ]);
            $steps['insert'] = ['id' => $inserted_id];
            error_log("[WPFlashNotes][CRUD] cards insert id={$inserted_id}");

            // READ
            $fetched = $repo->read($inserted_id);
            $steps['read'] = ['found' => (bool) $fetched, 'row' => $fetched];
            if (!$fetched || (int) $fetched['id'] !== $inserted_id) {
                throw new \RuntimeException('cards read failed');
            }

            // UPDATE (partial)
            $updated = $repo->update($inserted_id, [
                'explanation' => 'Paris is the capital.',
                'streak'      => 1,
                'ease_factor' => 2.6,
            ]);
            $steps['update'] = ['updated' => $updated];

            // RECORD REVIEW (optional convenience)
            $reviewed = $repo->record_review($inserted_id, 4); // treat as correct
            $steps['record_review'] = ['updated' => $reviewed];

            $fetched2 = $repo->read($inserted_id);
            $steps['read_after_update'] = [
                'explanation' => $fetched2['explanation'] ?? null,
                'streak'      => $fetched2['streak'] ?? null,
                'ease_factor' => $fetched2['ease_factor'] ?? null,
                'next_due'    => $fetched2['next_due'] ?? null,
            ];

            // SOFT DELETE
            $soft_deleted = $repo->soft_delete($inserted_id);
            $steps['soft_delete'] = ['updated' => $soft_deleted];
            $fetched3 = $repo->read($inserted_id);
            $steps['read_after_soft_delete'] = ['deleted_at' => $fetched3['deleted_at'] ?? null];

            // HARD DELETE
            $hard_deleted = $repo->delete($inserted_id);
            $steps['delete'] = ['deleted' => $hard_deleted];
            $fetched4 = $repo->read($inserted_id);
            $steps['read_after_delete'] = ['found' => (bool) $fetched4];
        }

        else {
            throw new \InvalidArgumentException('Unknown entity. Use "notes" or "cards".');
        }
    } catch (\Throwable $e) {
        $ok = false;
        $steps['error'] = $e->getMessage();
        error_log('[WPFlashNotes][CRUD][error] ' . $e->getMessage());
    }

    return [
        'entity'      => $entity,
        'inserted_id' => $inserted_id,
        'steps'       => $steps,
        'ok'          => $ok,
    ];
}
