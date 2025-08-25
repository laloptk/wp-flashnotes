<?php
namespace WPFlashNotes\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WPFlashNotes\Repos\NoteSetRelationsRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\BaseClasses\BaseController;

class NoteSetRelationsController extends BaseController {

	protected NoteSetRelationsRepository $repo;
	protected SetsRepository $sets;
	protected NotesRepository $notes;

	public function __construct() {
		parent::__construct();
		$this->rest_base = 'set-notes';
		$this->repo      = new NoteSetRelationsRepository();
		$this->sets      = new SetsRepository();
		$this->notes     = new NotesRepository();
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/attach',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'attach' ),
					'permission_callback' => array( $this, 'perm_edit_set_from_body' ),
					'args'                => array(
						'set_id'  => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
						'note_id' => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/detach',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'detach' ),
					'permission_callback' => array( $this, 'perm_edit_set_from_body' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sync',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'sync' ),
					'permission_callback' => array( $this, 'perm_edit_set_from_body' ),
					'args'                => array(
						'set_id'   => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
						'note_ids' => array(
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/set/(?P<set_id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_notes_for_set' ),
					'permission_callback' => array( $this, 'perm_edit_set_from_param' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/note/(?P<note_id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_sets_for_note' ),
					'permission_callback' => array( $this, 'perm_note_from_param' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/set/(?P<set_id>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'clear_set' ),
					'permission_callback' => array( $this, 'perm_edit_set_from_param' ),
				),
			)
		);
	}

	// Permissions

	public function perm_edit_set_from_body( WP_REST_Request $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) {
			return $auth;
		}
		$set_id = absint( $req->get_param( 'set_id' ) );
		if ( $set_id <= 0 ) {
			return $this->err( 'invalid_set', 'Invalid set_id.', 400 );
		}

		$row = $this->sets->read( $set_id );
		if ( ! $row ) {
			return $this->err( 'not_found', 'Set not found.', 404 );
		}

		return $this->can_edit_post( (int) $row['set_post_id'] );
	}

	public function perm_edit_set_from_param( WP_REST_Request $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) {
			return $auth;
		}

		$row = $this->sets->read( absint( $req['set_id'] ) );
		if ( ! $row ) {
			return $this->err( 'not_found', 'Set not found.', 404 );
		}
		return $this->can_edit_post( (int) $row['set_post_id'] );
	}

	public function perm_note_from_param( WP_REST_Request $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) {
			return $auth;
		}

		$row = $this->notes->read( absint( $req['note_id'] ) );
		if ( ! $row ) {
			return $this->err( 'not_found', 'Note not found.', 404 );
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return ( (int) ( $row['user_id'] ?? 0 ) === (int) get_current_user_id() )
			? true
			: $this->err( 'forbidden', 'You cannot access this note.', 403 );
	}

	// Handlers

	public function attach( WP_REST_Request $req ) {
		$ok = $this->repo->attach( absint( $req['note_id'] ), absint( $req['set_id'] ) );
		return $this->ok( array( 'attached' => (int) $ok ) );
	}

	public function detach( WP_REST_Request $req ) {
		$ok = $this->repo->detach( absint( $req['note_id'] ), absint( $req['set_id'] ) );
		return $this->ok( array( 'detached' => (int) $ok ) );
	}

	public function sync( WP_REST_Request $req ) {
		$res = $this->repo->sync_set_notes( absint( $req['set_id'] ), (array) ( $req->get_param( 'note_ids' ) ?: array() ) );
		return $this->ok( $res );
	}

	public function list_notes_for_set( WP_REST_Request $req ) {
		$list = $this->repo->get_note_ids_for_set( absint( $req['set_id'] ) );
		return $this->ok(
			array(
				'set_id'   => absint( $req['set_id'] ),
				'note_ids' => $list,
				'count'    => count( $list ),
			)
		);
	}

	public function list_sets_for_note( WP_REST_Request $req ) {
		$list = $this->repo->get_set_ids_for_note( absint( $req['note_id'] ) );
		return $this->ok(
			array(
				'note_id' => absint( $req['note_id'] ),
				'set_ids' => $list,
				'count'   => count( $list ),
			)
		);
	}

	public function clear_set( WP_REST_Request $req ) {
		$deleted = $this->repo->clear_set( absint( $req['set_id'] ) );
		return $this->ok( array( 'deleted' => (int) $deleted ) );
	}
}
