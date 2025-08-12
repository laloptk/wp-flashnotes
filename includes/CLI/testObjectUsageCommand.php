<?php
/**
 * File: includes/CLI/TestObjectUsageCommand.php
 */

namespace WPFlashNotes\CLI;

defined('ABSPATH') || exit;

use WP_CLI;
use WPFlashNotes\Repos\ObjectUsageRepository;

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('wpfn test:object-usage', function () {

        $results = [
            'ensure_tables' => [],
            'setup'         => [],
            'attach'        => [],
            'exists'        => [],
            'lists'         => [],
            'counts'        => [],
            'sync'          => [],
            'detach_one'    => [],
            'detach_block'  => [],
            'clear_object'  => [],
            'clear_post'    => [],
            'ok'            => false,
        ];

        try {
            // Ensure class exists / autoloaded
            if (!class_exists(ObjectUsageRepository::class)) {
                WP_CLI::error('ObjectUsageRepository not found. Check autoloading / namespaces.');
            }

            $repo = new ObjectUsageRepository();

            // Ensure table exists
            global $wpdb;
            $ran = [];
            $table = $wpdb->prefix . 'wpfn_object_usage';
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($found !== $table && function_exists('wpfn_schema_tasks')) {
                foreach (wpfn_schema_tasks() as $task) {
                    if (($task['slug'] ?? '') === 'wpfn_object_usage') {
                        ($task['run'])();
                        $ran[] = 'wpfn_object_usage';
                    }
                }
            }
            $results['ensure_tables']['ran'] = $ran;

            // -----------------------------------------------------------------
            // Create a post to track usage in
            // -----------------------------------------------------------------
            $user_id = get_current_user_id();
            if (!$user_id) {
                $ids = get_users(['number' => 1, 'fields' => 'ID']);
                if (!empty($ids)) {
                    $user_id = (int) $ids[0];
                } else {
                    $new_id = wp_insert_user([
                        'user_login' => 'wpfn_usage_tester_' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid()),
                        'user_pass'  => wp_generate_password(12),
                        'user_email' => 'wpfn_usage_tester+' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid()) . '@example.com',
                        'role'       => 'administrator',
                    ]);
                    if (is_wp_error($new_id)) {
                        WP_CLI::error('Failed to create a temporary user: ' . $new_id->get_error_message());
                    }
                    $user_id = (int) $new_id;
                }
            }

            $post_id = wp_insert_post([
                'post_title'  => 'Usage Smoke Post ' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid()),
                'post_type'   => 'post',
                'post_status' => 'publish',
                'post_author' => $user_id,
            ]);
            if (is_wp_error($post_id) || !$post_id) {
                WP_CLI::error('Failed to create test post.');
            }

            // Two block IDs (allowed chars only)
            $block1 = 'blk_' . substr(md5(uniqid('', true)), 0, 8);
            $block2 = 'blk_' . substr(md5(uniqid('', true)), 0, 8);

            // -----------------------------------------------------------------
            // Ensure a few cards/notes exist (create via repos if needed)
            // -----------------------------------------------------------------
            // Cards
            $cardsRepoClass = 'WPFlashNotes\\Repos\\CardsRepository';
            if (!class_exists($cardsRepoClass)) {
                WP_CLI::error('CardsRepository not found. Make sure it is autoloaded.');
            }
            /** @var \WPFlashNotes\Repos\CardsRepository $cardsRepo */
            $cardsRepo = new $cardsRepoClass();

            $card_ids = [];
            // Create 3 cards (payload tolerant: title+answer OR question+answer)
            $card_defs = [
                ['title' => 'Usage Card A', 'answer' => 'A'],
                ['title' => 'Usage Card B', 'answer' => 'B'],
                ['title' => 'Usage Card C', 'answer' => 'C'],
            ];
            foreach ($card_defs as $def) {
                try {
                    $card_ids[] = (int) $cardsRepo->insert($def);
                } catch (\Throwable $e) {
                    // Fallback if schema expects 'question'
                    $alt = ['question' => $def['title'], 'answer' => $def['answer']];
                    $card_ids[] = (int) $cardsRepo->insert($alt);
                }
            }

            // Notes
            $notesRepoClass = 'WPFlashNotes\\Repos\\NotesRepository';
            if (!class_exists($notesRepoClass)) {
                WP_CLI::error('NotesRepository not found. Make sure it is autoloaded.');
            }
            /** @var \WPFlashNotes\Repos\NotesRepository $notesRepo */
            $notesRepo = new $notesRepoClass();

            $note_ids = [];
            for ($i = 0; $i < 2; $i++) {
                $note_ids[] = (int) $notesRepo->insert([
                    'title'   => 'Usage Note ' . chr(65 + $i),
                    'content' => '<p>Example content ' . $i . '</p>',
                ]);
            }

            $results['setup'] = [
                'post_id'  => (int) $post_id,
                'block1'   => $block1,
                'block2'   => $block2,
                'cards'    => $card_ids,
                'notes'    => $note_ids,
            ];

            // -----------------------------------------------------------------
            // ATTACH
            // -----------------------------------------------------------------
            // Cards A & B in block1
            $repo->attach('card', $card_ids[0], $post_id, $block1);
            $repo->attach('card', $card_ids[1], $post_id, $block1);
            // Note A in block1, Note B in block2
            $repo->attach('note', $note_ids[0], $post_id, $block1);
            $repo->attach('note', $note_ids[1], $post_id, $block2);

            $results['attach'] = ['done' => 1];

            // -----------------------------------------------------------------
            // EXISTS
            // -----------------------------------------------------------------
            $results['exists'] = [
                'cardA_block1' => (int) $repo->exists('card', $card_ids[0], $post_id, $block1),
                'cardA_block2' => (int) $repo->exists('card', $card_ids[0], $post_id, $block2), // 0
                'noteA_block1' => (int) $repo->exists('note', $note_ids[0], $post_id, $block1),
                'noteB_block2' => (int) $repo->exists('note', $note_ids[1], $post_id, $block2),
            ];

            // -----------------------------------------------------------------
            // LISTS
            // -----------------------------------------------------------------
            $blocks_for_cardA = $repo->get_block_ids_for_object_in_post('card', $card_ids[0], $post_id);
            $posts_for_cardA  = $repo->get_post_ids_for_object('card', $card_ids[0]);
            $cards_in_post    = $repo->get_object_ids_for_post('card', $post_id);
            $cards_in_block1  = $repo->get_object_ids_for_block($post_id, $block1, 'card');

            $results['lists'] = [
                'blocks_for_cardA' => $blocks_for_cardA,
                'posts_for_cardA'  => $posts_for_cardA,
                'cards_in_post'    => $cards_in_post,
                'cards_in_block1'  => $cards_in_block1,
            ];

            // -----------------------------------------------------------------
            // COUNTS
            // -----------------------------------------------------------------
            $results['counts'] = [
                'for_cardA'      => $repo->count_for_object('card', $card_ids[0]),
                'for_post_cards' => $repo->count_for_post($post_id, 'card'),
                'for_post_all'   => $repo->count_for_post($post_id, null), // should be 4
            ];

            // -----------------------------------------------------------------
            // SYNC (block1, cards -> keep B & C)
            // -----------------------------------------------------------------
            $sync = $repo->sync_block_objects($post_id, $block1, 'card', [$card_ids[1], $card_ids[2]]);
            $after_sync_cards_block1 = $repo->get_object_ids_for_block($post_id, $block1, 'card');

            $results['sync'] = [
                'result'        => $sync,
                'after_block1'  => $after_sync_cards_block1,
            ];

            // -----------------------------------------------------------------
            // DETACH one (noteA from block1)
            // -----------------------------------------------------------------
            $det_one = $repo->detach('note', $note_ids[0], $post_id, $block1);
            $results['detach_one'] = [
                'detached'   => (int) $det_one,
                'exists_now' => (int) $repo->exists('note', $note_ids[0], $post_id, $block1),
            ];

            // -----------------------------------------------------------------
            // DETACH whole block (block1, cards only)
            // -----------------------------------------------------------------
            $deleted_block_cards = $repo->detach_block($post_id, $block1, 'card');
            $after_block_cards   = $repo->get_object_ids_for_block($post_id, $block1, 'card');
            $results['detach_block'] = [
                'deleted'      => (int) $deleted_block_cards,
                'after_block1' => $after_block_cards,
            ];

            // -----------------------------------------------------------------
            // CLEAR object (cardB everywhere)
            // -----------------------------------------------------------------
            $cleared_cardB = $repo->clear_object('card', $card_ids[1]);
            $count_cardB   = $repo->count_for_object('card', $card_ids[1]);
            $results['clear_object'] = [
                'cleared_rows' => (int) $cleared_cardB,
                'count_after'  => (int) $count_cardB,
            ];

            // -----------------------------------------------------------------
            // CLEAR post (everything left in this post)
            // -----------------------------------------------------------------
            $before_clear_post = $repo->count_for_post($post_id, null);
            $cleared_post      = $repo->clear_post($post_id);
            $after_clear_post  = $repo->count_for_post($post_id, null);
            $results['clear_post'] = [
                'before' => (int) $before_clear_post,
                'deleted'=> (int) $cleared_post,
                'after'  => (int) $after_clear_post,
            ];

            // -----------------------------------------------------------------
            // Final checks
            // -----------------------------------------------------------------
            $ok =
                $results['exists']['cardA_block1'] === 1 &&
                $results['exists']['cardA_block2'] === 0 &&
                in_array($block1, $results['lists']['blocks_for_cardA'], true) &&
                in_array((int) $post_id, $results['lists']['posts_for_cardA'], true) &&
                count(array_intersect($results['lists']['cards_in_post'], [$card_ids[0], $card_ids[1]])) >= 2 &&
                count(array_intersect($results['lists']['cards_in_block1'], [$card_ids[0], $card_ids[1]])) >= 2 &&
                $results['counts']['for_post_all'] >= 4 &&
                in_array($card_ids[1], $results['sync']['after_block1'], true) &&  // B kept
                in_array($card_ids[2], $results['sync']['after_block1'], true) &&  // C added
                $results['detach_one']['detached'] === 1 &&
                $results['detach_one']['exists_now'] === 0 &&
                $results['detach_block']['after_block1'] === [] &&
                $results['clear_object']['count_after'] === 0 &&
                $results['clear_post']['after'] === 0;

            $results['ok'] = (bool) $ok;

            // Cleanup post (in case anything remains, FK CASCADE will also handle it on hard delete)
            wp_delete_post($post_id, true);

            WP_CLI::line("entity\tok\nobject_usage\t" . ($ok ? 'yes' : 'no'));
            WP_CLI::line(print_r($results, true));

        } catch (\Throwable $e) {
            WP_CLI::warning('Partial results: ' . print_r($results, true));
            WP_CLI::error($e->getMessage());
        }
    });
}
