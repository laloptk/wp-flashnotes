<?php
namespace WPFlashNotes\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\BaseClasses\BaseController;

/**
 * NotesController
 *
 * CRUD for notes in {prefix}wpfn_notes via NotesRepository (BaseRepository).
 * Permissions: owner (row.user_id) or site admin.
 */
class NotesController extends BaseController {

	protected NotesRepository $repo;

	public function __construct() {
		parent::__construct();
		$this->rest_base = 'notes';
		$this->repo      = new NotesRepository();
	}

	public function register_routes(): void {
		// GET /wpfn/v1/notes
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
					'args'                => array(
						'user'     => array(
							'type'     => 'string',
							'required' => false,
						), // 'me' or numeric string
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 20,
						),
						'offset'   => array(
							'type'    => 'integer',
							'minimum' => 0,
							'default' => 0,
						),
					),
				),
			)
		);

		// GET /wpfn/v1/notes/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'perm_row_from_id' ),
				),
			)
		);

		// GET /wpfn/v1/notes/find
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/find',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'find_notes' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
				),
			)
		);

		// POST /wpfn/v1/notes
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
				),
			)
		);

		// PATCH /wpfn/v1/notes/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'perm_row_from_id' ),
				),
			)
		);

		// DELETE /wpfn/v1/notes/{id}?hard=1
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'perm_row_from_id' ),
					'args'                => array(
						'hard' => array(
							'type'     => 'boolean',
							'required' => false,
						),
					),
				),
			)
		);
	}

	// ---- Permissions -------------------------------------------------------

	public function perm_row_from_id( $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) {
			return $auth;
		}

		$id  = absint( $req['id'] );
		$row = $this->repo->read( $id );
		if ( ! $row ) {
			return $this->err( 'not_found', 'Note not found.', 404 );
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$uid = get_current_user_id();
		return ( (int) ( $row['user_id'] ?? 0 ) === (int) $uid )
			? true
			: $this->err( 'forbidden', 'You cannot access this note.', 403 );
	}

	// ---- Handlers ----------------------------------------------------------

	public function list_items( $req ) {
		$per = absint( $req->get_param( 'per_page' ) ) ?: 20;
		$off = absint( $req->get_param( 'offset' ) ) ?: 0;

		$userParam = $req->get_param( 'user' );
		$user_id   = null;
		if ( $userParam === 'me' || $userParam === null ) {
			$user_id = get_current_user_id();
		} elseif ( is_numeric( $userParam ) ) {
			$user_id = absint( $userParam );
		}
		if ( ! $user_id ) {
			return $this->ok(
				array(
					'items' => array(),
					'count' => 0,
				)
			);
		}

		$rows = $this->repo->find( array( 'user_id' => (int) $user_id ), $per, $off );
		return $this->ok(
			array(
				'items' => $rows,
				'count' => count( $rows ),
			)
		);
	}

	public function get_item( $req ) {
		$id  = absint( $req['id'] );
		$row = $this->repo->read( $id );
		return $row ? $this->ok( array( 'item' => $row ) ) : $this->err( 'not_found', 'Note not found.', 404 );
	}

	public function find_notes( \WP_REST_Request $request ) {
		$params = $request->get_params();

		$args = array(
			'where'  => array(),
			'search' => array(),
			'limit'  => null,
			'offset' => null,
		);

		foreach ( $params as $param_key => $param_value ) {
			if ( 'limit' === $param_key ) {
				$limit = absint( $param_value );
				if ( $limit > 0 ) {
					$args['limit'] = $limit;
				}
				continue;
			}

			if ( 'offset' === $param_key ) {
				$offset = absint( $param_value );
				if ( $offset > 0 ) { // do not include OFFSET 0
					$args['offset'] = $offset;
				}
				continue;
			}

			if ( 's' === $param_key && is_string( $param_value ) ) {
				$term = trim( $param_value );
				if ( '' !== $term ) {
					$args['search'] = array(
						'title'    => $term,
						'content'  => $term,
					);
				}
				continue;
			}

			if ( '' === $param_value || null === $param_value ) {
				continue;
			}

			if ( is_string( $param_value ) && preg_match( '/^-?\d+$/', $param_value ) ) {
				$param_value = (int) $param_value;
			}

			$args['where'][ $param_key ] = $param_value;
		}

		$rows = $this->repo->find( $args );

		return $this->ok(
			array(
				'items' => $rows,
			)
		);
	}

	public function create_item( $req ) {
		$uid             = get_current_user_id();
		$data            = $req->get_params();
		$data['user_id'] = $this->absint_or_null( $data['user_id'] ?? null ) ?: $uid;

		// Minimal expected: title + content
		$id  = $this->repo->insert( $data );
		$row = $this->repo->read( (int) $id );
		return $this->ok(
			array(
				'id'   => (int) $id,
				'item' => $row,
			),
			201
		);
	}

	public function update_item( $req ) {
		$id   = absint( $req['id'] );
		$data = $req->get_params();
		unset( $data['id'], $data['_method'] );

		if ( ! $data ) {
			return $this->err( 'no_changes', 'No fields to update.', 400 );
		}

		$ok  = $this->repo->update( $id, $data );
		$row = $this->repo->read( $id );
		return $this->ok(
			array(
				'updated' => (int) $ok,
				'item'    => $row,
			)
		);
	}

	public function delete_item( $req ) {
		$id   = absint( $req['id'] );
		$hard = (bool) $req->get_param( 'hard' );

		$ok = $hard ? $this->repo->delete( $id ) : $this->repo->soft_delete( $id );
		return $this->ok(
			array(
				'deleted' => (int) $ok,
				'hard'    => (int) $hard,
			)
		);
	}

	// ---- Schema (optional) -------------------------------------------------

	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'note',
			'type'       => 'object',
			'properties' => array(
				'id'         => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'user_id'    => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'title'      => array( 'type' => 'string' ),
				'content'    => array( 'type' => 'string' ),
				'created_at' => array( 'type' => 'string' ),
				'updated_at' => array( 'type' => 'string' ),
				'deleted_at' => array(
					'type'     => 'string',
					'nullable' => true,
				),
			),
		);
	}
}
