<?php
namespace WPFlashNotes\REST;

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\BaseClasses\BaseController;

class NotesController extends BaseController {

	protected NotesRepository $repo;

	public function __construct() {
		parent::__construct();
		$this->rest_base = 'notes';
		$this->repo      = new NotesRepository();
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'list_items' ],
					'permission_callback' => [ $this, 'require_logged_in' ],
					'args'                => [
						'user'     => [ 'type' => 'string', 'required' => false ],
						'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'default' => 20 ],
						'offset'   => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
					],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'require_logged_in' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/find',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'find_notes' ],
					'permission_callback' => [ $this, 'require_logged_in' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'perm_row_from_id' ],
				],
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'perm_row_from_id' ],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'perm_row_from_id' ],
				],
			]
		);
	}

	public function perm_row_from_id( $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) return $auth;

		$id  = absint( $req['id'] );
		$row = $this->repo->read( $id );
		if ( ! $row ) return $this->err( 'not_found', 'Note not found.', 404 );

		if ( current_user_can( 'manage_options' ) ) return true;

		$uid = get_current_user_id();
		return (int) ( $row['user_id'] ?? 0 ) === $uid
			? true
			: $this->err( 'forbidden', 'You cannot access this note.', 403 );
	}

	public function list_items( $req ) {
		return $this->safe( function() use ( $req ) {
			$per = absint( $req->get_param( 'per_page' ) ) ?: 20;
			$off = absint( $req->get_param( 'offset' ) ) ?: 0;

			$user_param = $req->get_param( 'user' );
			$user_id    = null;
			if ( $user_param === 'me' || $user_param === null ) {
				$user_id = get_current_user_id();
			} elseif ( is_numeric( $user_param ) ) {
				$user_id = absint( $user_param );
			}

			if ( ! $user_id ) {
				return $this->ok( [ 'items' => [], 'count' => 0 ] );
			}

			$rows = $this->repo->find( [ 'user_id' => (int) $user_id ], $per, $off );
			return $this->ok( [ 'items' => $rows, 'count' => count( $rows ) ] );
		});
	}

	public function get_item( $req ) {
		return $this->safe( function() use ( $req ) {
			$id  = absint( $req['id'] );
			$row = $this->repo->read( $id );
			return $row
				? $this->ok( [ 'item' => $row ] )
				: $this->err( 'not_found', 'Note not found.', 404 );
		});
	}

	public function find_notes( $req ) {
		return $this->safe( function() use ( $req ) {
			$params = $req->get_params();
			$args   = [ 'where' => [], 'search' => [], 'limit' => null, 'offset' => null ];

			foreach ( $params as $k => $v ) {
				if ( $k === 'limit' ) {
					$l = absint( $v );
					if ( $l > 0 ) $args['limit'] = $l;
					continue;
				}
				if ( $k === 'offset' ) {
					$o = absint( $v );
					if ( $o > 0 ) $args['offset'] = $o;
					continue;
				}
				if ( $k === 's' && is_string( $v ) && trim( $v ) !== '' ) {
					$t = trim( $v );
					$args['search'] = [ 'title' => $t, 'content' => $t ];
					continue;
				}
				if ( $v === '' || $v === null ) continue;
				if ( is_string( $v ) && preg_match( '/^-?\d+$/', $v ) ) $v = (int) $v;
				$args['where'][ $k ] = $v;
			}

			$rows = $this->repo->find( $args );
			return $this->ok( [ 'items' => $rows ] );
		});
	}

	public function create_item( $req ) {
		return $this->safe( function() use ( $req ) {
			$uid             = get_current_user_id();
			$data            = $req->get_params();
			$data['user_id'] = $this->absint_or_null( $data['user_id'] ?? null ) ?: $uid;

			$id  = $this->repo->insert( $data );
			$row = $this->repo->read( (int) $id );

			return $this->ok( [ 'id' => (int) $id, 'item' => $row ], 201 );
		});
	}

	public function update_item( $req ) {
		return $this->safe( function() use ( $req ) {
			$id   = absint( $req['id'] );
			$data = $req->get_params();
			unset( $data['id'], $data['_method'] );
			if ( empty( $data ) ) return $this->err( 'no_changes', 'No fields to update.', 400 );

			$ok  = $this->repo->update( $id, $data );
			$row = $this->repo->read( $id );
			return $this->ok( [ 'updated' => (int) $ok, 'item' => $row ] );
		});
	}

	public function delete_item( $req ) {
		return $this->safe( function() use ( $req ) {
			$id = absint( $req['id'] );
			$ok = $this->repo->delete( $id );
			return $this->ok( [ 'deleted' => (int) $ok ] );
		});
	}

	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'note',
			'type'       => 'object',
			'properties' => [
				'id'         => [ 'type' => 'integer', 'minimum' => 1 ],
				'user_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
				'title'      => [ 'type' => 'string' ],
				'content'    => [ 'type' => 'string' ],
				'created_at' => [ 'type' => 'string' ],
				'updated_at' => [ 'type' => 'string' ],
				'deleted_at' => [ 'type' => 'string', 'nullable' => true ],
			],
		];
	}
}
