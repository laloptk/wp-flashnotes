<?php

namespace WPFlashNotes\REST;

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\BaseClasses\BaseController;
use WPFlashNotes\Events\EventHandler;
use WP_REST_Server;

/**
 * SyncController
 *
 * Handles orchestration-level REST commands such as creating or syncing studysets.
 * Delegates actual logic to the EventHandler and DataPropagation layers.
 */
class SyncController extends BaseController {

	private EventHandler $event_handler;

	public function __construct( EventHandler $event_handler ) {
		parent::__construct();
		$this->event_handler = $event_handler;
	}

	/**
	 * Register REST API routes for studyset synchronization commands.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/studyset/sync',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_generate_studyset' ],
					'permission_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
					'args'                => [
						'origin_post_id' => [
							'type'     => 'integer',
							'required' => true,
						],
					],
				],
			]
		);
	}

	/**
	 * Handle creation or update of a studyset from an origin post.
	 */
	public function handle_generate_studyset( $req ) {
		return $this->safe( function() use ( $req ) {
			$origin_post_id = $this->absint_or_null( $req['origin_post_id'] );

			if ( ! $origin_post_id ) {
				return $this->err( 'invalid_origin_post_id', 'Origin post ID required.', 400 );
			}

			$origin_post = get_post( $origin_post_id );
			if ( ! $origin_post ) {
				return $this->err( 'invalid_post', 'Origin post not found.', 404 );
			}

			$author_id = get_current_user_id();
			$title     = get_the_title( $origin_post );

			$result = $this->event_handler->generate_studyset_from_origin(
				$origin_post_id,
				$title,
				$author_id
			);

			if ( empty( $result['ok'] ) ) {
				return $this->err(
					'studyset_generation_failed',
					'Studyset could not be generated.',
					400
				);
			}

			return $this->ok( $result );
		});
	}
}
