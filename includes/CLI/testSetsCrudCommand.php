<?php
/**
 * File: includes/CLI/TestSetsCrudCommand.php
 */

namespace WPFlashNotes\CLI;

defined( 'ABSPATH' ) || exit;

use WP_CLI;
use WPFlashNotes\Repos\SetsRepository;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'wpfn test:sets',
		function ( $args, $assoc_args ) {

			$results = array(
				'insert'            => array(),
				'read'              => array(),
				'update'            => array(),
				'read_after_update' => array(),
				'upsert_update'     => array(),
				'upsert_insert'     => array(),
				'list_by_user'      => array(),
				'get_by_post_id'    => array(),
				'delete'            => array(),
				'cascade_delete'    => array(),
			);

			try {
				$repo = new SetsRepository();

				// 0) Ensure table exists (no calls to protected repo methods)
				global $wpdb;
				$table = $wpdb->prefix . 'wpfn_sets';
				$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
				if ( $found !== $table && function_exists( 'wpfn_schema_tasks' ) ) {
					foreach ( wpfn_schema_tasks() as $task ) {
						if ( ( $task['slug'] ?? '' ) === 'wpfn_sets' ) {
							( $task['run'] )();
							break;
						}
					}
				}

				// Resolve a user ID
				$user_id = get_current_user_id();
				if ( ! $user_id ) {
					$any = get_users(
						array(
							'number' => 1,
							'fields' => 'ID',
						)
					);
					if ( ! empty( $any ) ) {
						$user_id = (int) $any[0];
					} else {
						// Create a temp admin user if none exist
						$new_id = wp_insert_user(
							array(
								'user_login' => 'wpfn_tester_' . wp_generate_uuid4(),
								'user_pass'  => wp_generate_password( 12 ),
								'user_email' => 'wpfn_tester+' . wp_generate_uuid4() . '@example.com',
								'role'       => 'administrator',
							)
						);
						if ( is_wp_error( $new_id ) ) {
							WP_CLI::error( 'Failed to create a temporary user: ' . $new_id->get_error_message() );
						}
						$user_id = (int) $new_id;
					}
				}

				// Create a Study Set CPT post (or fallback to 'post' if CPT not present)
				$studyset_type = post_type_exists( 'studyset' ) ? 'studyset' : 'post';
				$set_post_id_1 = wp_insert_post(
					array(
						'post_title'  => 'Smoke Study Set A ' . wp_generate_uuid4(),
						'post_type'   => $studyset_type,
						'post_status' => 'publish',
						'post_author' => $user_id,
					)
				);

				// Optional "source content" post for post_id (can be NULL)
				$content_post_id = wp_insert_post(
					array(
						'post_title'  => 'Smoke Source Content ' . wp_generate_uuid4(),
						'post_type'   => 'post',
						'post_status' => 'publish',
						'post_author' => $user_id,
					)
				);

				// 1) INSERT
				$insert_id         = $repo->insert(
					array(
						'title'       => 'Smoke Set Title',
						'post_id'     => $content_post_id,
						'set_post_id' => $set_post_id_1,
						'user_id'     => $user_id,
					)
				);
				$results['insert'] = array( 'id' => $insert_id );

				// 2) READ
				$row             = $repo->read( $insert_id );
				$results['read'] = array(
					'found' => (int) ( isset( $row['id'] ) && (int) $row['id'] === $insert_id ),
					'row'   => $row,
				);

				// 3) UPDATE (mutable fields only)
				$updated = $repo->update(
					$insert_id,
					array(
						'title' => 'Smoke Set Title (updated)',
					// 'set_post_id' and 'user_id' are immutable by design in this repo
					)
				);
				$results['update'] = array( 'updated' => (int) $updated );

				// 4) READ AFTER UPDATE
				$row_after                    = $repo->read( $insert_id );
				$results['read_after_update'] = array(
					'title' => $row_after['title'] ?? null,
				);

				// 5) UPSERT (UPDATE path) using the same set_post_id
				$repo->upsert_by_set_post_id(
					array(
						'set_post_id' => $set_post_id_1,
						'title'       => 'Smoke Set Title (upsert-updated)',
						'post_id'     => $content_post_id,
					)
				);
				$row_upd                  = $repo->get_by_set_post_id( $set_post_id_1 );
				$results['upsert_update'] = array(
					'id_unchanged' => (int) ( (int) $row_upd['id'] === $insert_id ),
					'title'        => $row_upd['title'] ?? null,
				);

				// 6) UPSERT (INSERT path) with a new study set post
				$set_post_id_2 = wp_insert_post(
					array(
						'post_title'  => 'Smoke Study Set B ' . wp_generate_uuid4(),
						'post_type'   => $studyset_type,
						'post_status' => 'publish',
						'post_author' => $user_id,
					)
				);
				$insert_id_2   = $repo->upsert_by_set_post_id(
					array(
						'set_post_id' => $set_post_id_2,
						'title'       => 'Smoke Set B Title',
						'user_id'     => $user_id,
					// no post_id (NULL)
					)
				);
				$results['upsert_insert'] = array( 'id' => $insert_id_2 );

				// 7) LIST BY USER
				$list                    = $repo->list_by_user( $user_id, 50, 0 );
				$results['list_by_user'] = array(
					'count'     => count( $list ),
					'has_first' => (int) (bool) array_filter( $list, fn( $r ) => (int) $r['id'] === $insert_id ),
				);

				// 8) GET BY post_id
				$list_by_post              = $repo->get_by_post_id( $content_post_id );
				$results['get_by_post_id'] = array(
					'count' => count( $list_by_post ),
				);

				// 9) DELETE (hard delete) for the first set
				$deleted           = $repo->delete( $insert_id );
				$after_delete      = $repo->read( $insert_id );
				$results['delete'] = array(
					'deleted' => (int) $deleted,
					'gone'    => (int) ( is_null( $after_delete ) ),
				);

				// 10) CASCADE DELETE: deleting the second Study Set CPT post should remove the set row
				wp_delete_post( $set_post_id_2, true ); // force delete
				$after_cascade             = $repo->read( $insert_id_2 );
				$results['cascade_delete'] = array(
					'gone' => (int) ( is_null( $after_cascade ) ),
				);

				// Cleanup auxiliary posts
				wp_delete_post( $set_post_id_1, true );
				wp_delete_post( $content_post_id, true );

				// Summarize
				$ok =
					$results['read']['found'] === 1
					&& $results['update']['updated'] === 1
					&& $results['read_after_update']['title'] === 'Smoke Set Title (updated)'
					&& $results['upsert_update']['id_unchanged'] === 1
					&& $results['upsert_update']['title'] === 'Smoke Set Title (upsert-updated)'
					&& $results['delete']['deleted'] === 1
					&& $results['delete']['gone'] === 1
					&& $results['cascade_delete']['gone'] === 1;

				WP_CLI::line(
					sprintf(
						"entity\tinserted_id\tok\nsets\t%d\t%s",
						$insert_id,
						$ok ? 'yes' : 'no'
					)
				);
				WP_CLI::line( print_r( $results, true ) );
			} catch ( \Throwable $e ) {
				// Print partial results for easier debugging, then fail the command
				WP_CLI::warning( 'Partial results: ' . print_r( $results, true ) );
				WP_CLI::error( $e->getMessage() );
			}
		}
	);
}
