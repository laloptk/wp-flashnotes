<?php
/**
 * File: includes/CLI/TestCardSetRelationsCommand.php
 */

namespace WPFlashNotes\CLI;

defined('ABSPATH') || exit;

use WP_CLI;
use WPFlashNotes\Repos\CardSetRelationsRepository;
use WPFlashNotes\Repos\SetsRepository;

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('wpfn test:card-set-relations', function () {

        $results = [
            'ensure_tables' => [],
            'setup'         => [],
            'attach'        => [],
            'exists'        => [],
            'list_cards'    => [],
            'count'         => [],
            'detach'        => [],
            'sync'          => [],
            'ok'            => false,
        ];

        try {
            // --- Ensure required classes exist --------------------------------
            $needs = [
                'sets'  => 'WPFlashNotes\\Repos\\SetsRepository',
                'cards' => 'WPFlashNotes\\Repos\\CardsRepository',
            ];
            foreach ($needs as $label => $class) {
                if (!class_exists($class)) {
                    WP_CLI::error("Missing {$label} repo class: {$class}. Make sure it is autoloaded.");
                }
            }

            $setRepo   = new SetsRepository();
            $pivotRepo = new CardSetRelationsRepository();

            // --- Ensure tables exist via schema tasks --------------------------
            global $wpdb;
            $required = ['wpfn_cards', 'wpfn_sets', 'wpfn_card_set_relations'];
            $ran = [];
            if (function_exists('wpfn_schema_tasks')) {
                $tasks = wpfn_schema_tasks();
                foreach ($required as $slug) {
                    $table = $wpdb->prefix . $slug;
                    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
                    if ($found !== $table) {
                        foreach ($tasks as $t) {
                            if (($t['slug'] ?? '') === $slug) {
                                ($t['run'])();
                                $ran[] = $slug;
                                break;
                            }
                        }
                    }
                }
            }
            $results['ensure_tables'] = ['ran' => $ran];

            // --- Resolve a user ------------------------------------------------
            $user_id = get_current_user_id();
            if (!$user_id) {
                $ids = get_users(['number' => 1, 'fields' => 'ID']);
                if (!empty($ids)) {
                    $user_id = (int) $ids[0];
                } else {
                    $new_id = wp_insert_user([
                        'user_login' => 'wpfn_rel_tester_' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid()),
                        'user_pass'  => wp_generate_password(12),
                        'user_email' => 'wpfn_rel_tester+' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid()) . '@example.com',
                        'role'       => 'administrator',
                    ]);
                    if (is_wp_error($new_id)) {
                        WP_CLI::error('Failed to create a temporary user: ' . $new_id->get_error_message());
                    }
                    $user_id = (int) $new_id;
                }
            }

            // --- Create Study Set CPT + Set row --------------------------------
            $studyset_type = post_type_exists('studyset') ? 'studyset' : 'post';
            $set_post_id = wp_insert_post([
                'post_title'  => 'Rel Smoke Study Set ' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid()),
                'post_type'   => $studyset_type,
                'post_status' => 'publish',
                'post_author' => $user_id,
            ]);
            if (is_wp_error($set_post_id) || !$set_post_id) {
                WP_CLI::error('Failed to create Study Set CPT post.');
            }

            $set_id = $setRepo->upsert_by_set_post_id([
                'set_post_id' => $set_post_id,
                'title'       => 'Rel Smoke Set',
                'user_id'     => $user_id,
            ]);

            // --- Ensure we have at least 3 cards -------------------------------
            $cardsTable = $wpdb->prefix . 'wpfn_cards';
            $card_ids = $wpdb->get_col("SELECT id FROM {$cardsTable} ORDER BY id DESC LIMIT 3");
            $created_cards = [];

            if (count($card_ids) < 3) {
                $cardsRepoClass = 'WPFlashNotes\\Repos\\CardsRepository';
                /** @var \WPFlashNotes\Repos\CardsRepository $cardsRepo */
                $cardsRepo = new $cardsRepoClass();

                // Create minimal valid cards (adjust fields to your CardsRepository)
                // These fields assume a simple (title, answer) or (question, answer) structure.
                // If your repo expects different keys, tweak here accordingly.
                $tryDefs = [
                    ['title' => 'Rel Card A', 'answer' => 'A'],
                    ['title' => 'Rel Card B', 'answer' => 'B'],
                    ['title' => 'Rel Card C', 'answer' => 'C'],
                ];

                foreach ($tryDefs as $def) {
                    try {
                        $newId = $cardsRepo->insert($def);
                        $created_cards[] = $newId;
                    } catch (\Throwable $e) {
                        // Fallback variant if your repo uses 'question' instead of 'title'
                        $alt = [
                            'question' => $def['title'],
                            'answer'   => $def['answer'],
                        ];
                        $newId = $cardsRepo->insert($alt);
                        $created_cards[] = $newId;
                    }
                }
                $card_ids = array_reverse($created_cards); // use newly created
            } else {
                $card_ids = array_map('intval', array_reverse($card_ids)); // latest first
            }

            $results['setup'] = [
                'set_id'       => (int) $set_id,
                'card_ids'     => $card_ids,
                'created_cards'=> $created_cards,
            ];

            // --- Attach first two cards ----------------------------------------
            $pivotRepo->attach($card_ids[0], $set_id);
            $pivotRepo->attach($card_ids[1], $set_id);
            $results['attach'] = ['first_two_attached' => 1];

            // --- Exists ---------------------------------------------------------
            $results['exists'] = [
                'first'  => (int) $pivotRepo->exists($card_ids[0], $set_id),
                'second' => (int) $pivotRepo->exists($card_ids[1], $set_id),
                'third'  => (int) $pivotRepo->exists($card_ids[2], $set_id), // should be 0 now
            ];

            // --- List + count ---------------------------------------------------
            $listed = $pivotRepo->get_card_ids_for_set($set_id);
            $results['list_cards'] = [
                'listed' => $listed,
            ];
            $results['count'] = [
                'count_for_set' => $pivotRepo->count_for_set($set_id),
            ];

            // --- Detach one -----------------------------------------------------
            $det = $pivotRepo->detach($card_ids[0], $set_id);
            $results['detach'] = [
                'detached_first' => (int) $det,
                'exists_after'   => (int) $pivotRepo->exists($card_ids[0], $set_id),
            ];

            // --- Sync: keep second + third only --------------------------------
            $sync = $pivotRepo->sync_set_cards($set_id, [$card_ids[1], $card_ids[2]]);
            $results['sync'] = $sync;

            // --- Final check ----------------------------------------------------
            $final = $pivotRepo->get_card_ids_for_set($set_id);
            $ok =
                $results['exists']['first'] === 1 &&
                $results['exists']['second'] === 1 &&
                $results['exists']['third'] === 0 &&
                $results['count']['count_for_set'] >= 2 &&
                $results['detach']['detached_first'] === 1 &&
                in_array($card_ids[1], $final, true) &&
                in_array($card_ids[2], $final, true) &&
                !in_array($card_ids[0], $final, true);

            $results['ok'] = (bool) $ok;

            // Cleanup: delete the study set post (cascades the set row)
            wp_delete_post($set_post_id, true);

            WP_CLI::line(sprintf(
                "entity\tok\ncard_set_relations\t%s",
                $ok ? 'yes' : 'no'
            ));
            WP_CLI::line(print_r($results, true));

        } catch (\Throwable $e) {
            WP_CLI::warning('Partial results: ' . print_r($results, true));
            WP_CLI::error($e->getMessage());
        }
    });
}
