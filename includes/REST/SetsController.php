<?php
namespace WPFlashNotes\REST;

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\BaseClasses\BaseController;

class SetsController extends BaseController {

	protected SetsRepository $repo;

	public function __construct() {
		parent::__construct();
		$this->rest_base = 'sets';
		$this->repo      = new SetsRepository();
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_sets' ],
				'permission_callback' => [ $this, 'require_logged_in' ],
				'args'                => [
					'user'     => [ 'type' => 'string', 'required' => false ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'default' => 20 ],
					'offset'   => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
				],
			]]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/by-set-post-id/(?P<set_post_id>\d+)',
			[[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_by_set_post_id' ],
				'permission_callback' => [ $this, 'perm_edit_post_from_param' ],
			]]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/by-post-id/(?P<post_id>\d+)',
			[[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_by_post_id' ],
				'permission_callback' => [ $this, 'perm_edit_post_from_param' ],
			]]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/upsert-by-set-post',
			[[
				'methods'             => 'POST',
				'callback'            => [ $this, 'upsert_by_set_post' ],
				'permission_callback' => [ $this, 'perm_edit_setpost_from_body' ],
				'args'                => [
					'set_post_id' => [
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					],
					'title'       => [ 'type' => 'string', 'required' => false ],
					'post_id'     => [
						'type'              => 'integer',
						'minimum'           => 1,
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
					'user_id'     => [
						'type'              => 'integer',
						'minimum'           => 1,
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
				],
			]]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			[[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_set' ],
				'permission_callback' => [ $this, 'perm_edit_set_from_id' ],
				'args'                => [
					'title'   => [ 'type' => 'string', 'required' => false ],
					'post_id' => [ 'type' => 'integer', 'minimum' => 1, 'required' => false ],
				],
			],[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_set' ],
				'permission_callback' => [ $this, 'perm_delete_set_from_id' ],
			]]
		);
	}

	// ---------------------------------------------------------------------
	// Permissions
	// ---------------------------------------------------------------------

	public function perm_edit_setpost_from_body( $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) return $auth;

		$set_post_id = absint( $req->get_param( 'set_post_id' ) );
		if ( $set_post_id <= 0 ) {
			return $this->err( 'invalid_post', 'Invalid set_post_id.', 400 );
		}

		return $this->can_edit_post( $set_post_id );
	}

	public function perm_edit_post_from_param( $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) return $auth;

		$id = $req['set_post_id'] ?? $req['post_id'] ?? null;
		return $this->can_edit_post( absint( $id ) );
	}

	public function perm_edit_set_from_id( $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) return $auth;

		$row = $this->repo->read( absint( $req['id'] ) );
		if ( ! $row ) {
			return $this->err( 'not_found', 'Set not found.', 404 );
		}
		return $this->can_edit_post( (int) $row['set_post_id'] );
	}

	public function perm_delete_set_from_id( $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) return $auth;

		$row = $this->repo->read( absint( $req['id'] ) );
		if ( ! $row ) {
			return $this->err( 'not_found', 'Set not found.', 404 );
		}
		return $this->can_delete_post( (int) $row['set_post_id'] );
	}

	// ---------------------------------------------------------------------
	// Handlers
	// ---------------------------------------------------------------------

	public function list_sets( $req ) {
		return $this->safe( function() use ( $req ) {
			$userParam = $req->get_param( 'user' );
			$per       = absint( $req->get_param( 'per_page' ) ) ?: 20;
			$off       = absint( $req->get_param( 'offset' ) ) ?: 0;

			$user_id = null;
			if ( $userParam === 'me' || $userParam === null ) {
				$user_id = get_current_user_id();
			} elseif ( is_numeric( $userParam ) ) {
				$user_id = absint( $userParam );
			}

			if ( ! $user_id ) {
				return $this->ok([ 'items' => [], 'count' => 0 ]);
			}

			$rows = $this->repo->list_by_user( $user_id, $per, $off );
			return $this->ok([ 'items' => $rows, 'count' => count( $rows ) ]);
		});
	}

	public function get_by_set_post_id( $req ) {
		return $this->safe( function() use ( $req ) {
			$row = $this->repo->get_by_set_post_id( absint( $req['set_post_id'] ) );
			return $this->ok([ 'item' => $row ?: null ]);
		});
	}

	public function get_by_post_id( $req ) {
		return $this->safe( function() use ( $req ) {
			$row = $this->repo->get_by_post_id( absint( $req['post_id'] ) );
			return $this->ok([ 'item' => $row ?: null ]);
		});
	}

	public function upsert_by_set_post( $req ) {
		return $this->safe( function() use ( $req ) {
			$payload = [ 'set_post_id' => absint( $req['set_post_id'] ) ];

			if ( $req->offsetExists( 'title' ) ) {
				$payload['title'] = (string) $req['title'];
			}
			if ( $req->offsetExists( 'post_id' ) ) {
				$payload['post_id'] = absint( $req['post_id'] );
			}
			$payload['user_id'] = $this->absint_or_null( $req->get_param( 'user_id' ) )
				?: get_current_user_id();

			$id  = $this->repo->upsert_by_set_post_id( $payload );
			$row = $this->repo->read( (int) $id );

			return $this->ok(
				[ 'id' => (int) $id, 'item' => $row ],
				201
			);
		});
	}

	public function update_set( $req ) {
		return $this->safe( function() use ( $req ) {
			$id   = absint( $req['id'] );
			$data = [];

			if ( $req->offsetExists( 'title' ) ) {
				$data['title'] = (string) $req['title'];
			}
			if ( $req->offsetExists( 'post_id' ) ) {
				$data['post_id'] = absint( $req['post_id'] );
			}

			if ( ! $data ) {
				return $this->err( 'no_changes', 'No data to update.', 400 );
			}

			$ok  = $this->repo->update( $id, $data );
			$row = $this->repo->read( $id );
			return $this->ok([ 'updated' => (int) $ok, 'item' => $row ]);
		});
	}

	public function delete_set( $req ) {
		return $this->safe( function() use ( $req ) {
			$ok = $this->repo->delete( absint( $req['id'] ) );
			return $this->ok([ 'deleted' => (int) $ok ]);
		});
	}

	// ---------------------------------------------------------------------
	// Schema
	// ---------------------------------------------------------------------

	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'set',
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer', 'minimum' => 1 ],
				'title'       => [ 'type' => 'string' ],
				'post_id'     => [ 'type' => 'integer', 'minimum' => 1, 'nullable' => true ],
				'set_post_id' => [ 'type' => 'integer', 'minimum' => 1 ],
				'user_id'     => [ 'type' => 'integer', 'minimum' => 1 ],
				'created_at'  => [ 'type' => 'string' ],
				'updated_at'  => [ 'type' => 'string' ],
			],
			'required'   => [ 'id', 'title', 'set_post_id', 'user_id' ],
		];
	}
}
