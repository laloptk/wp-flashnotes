<?php
namespace WPFlashNotes\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WPFlashNotes\Repos\TaxonomyRelationsRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\BaseClasses\BaseController;

class TaxonomyRelationsController extends BaseController {

	protected TaxonomyRelationsRepository $repo;
	protected SetsRepository $sets;
	protected CardsRepository $cards;
	protected NotesRepository $notes;

	public function __construct() {
		parent::__construct();
		$this->rest_base = 'taxonomy-relations';
		$this->repo      = new TaxonomyRelationsRepository();
		$this->sets      = new SetsRepository();
		$this->cards     = new CardsRepository();
		$this->notes     = new NotesRepository();
	}

	public function register_routes(): void {
		// POST /attach
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/attach',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'attach' ),
					'permission_callback' => array( $this, 'perm_object_from_body' ),
					'args'                => array(
						'object_type'      => array(
							'type'     => 'string',
							'enum'     => array( 'set', 'card', 'note' ),
							'required' => true,
						),
						'object_id'        => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
						'term_taxonomy_id' => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
					),
				),
			)
		);

		// POST /detach
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/detach',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'detach' ),
					'permission_callback' => array( $this, 'perm_object_from_body' ),
				),
			)
		);

		// POST /bulk-attach
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk-attach',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'bulk_attach' ),
					'permission_callback' => array( $this, 'perm_object_from_body' ),
					'args'                => array(
						'object_type'       => array(
							'type'     => 'string',
							'enum'     => array( 'set', 'card', 'note' ),
							'required' => true,
						),
						'object_id'         => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
						'term_taxonomy_ids' => array(
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
							'required' => true,
						),
					),
				),
			)
		);

		// POST /sync
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sync',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'sync' ),
					'permission_callback' => array( $this, 'perm_object_from_body' ),
					'args'                => array(
						'object_type'       => array(
							'type'     => 'string',
							'enum'     => array( 'set', 'card', 'note' ),
							'required' => true,
						),
						'object_id'         => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
						'term_taxonomy_ids' => array(
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
							'required' => true,
						),
					),
				),
			)
		);

		// GET /object/{object_type}/{object_id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/object/(?P<object_type>set|card|note)/(?P<object_id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_terms_for_object' ),
					'permission_callback' => array( $this, 'perm_object_from_param' ),
				),
			)
		);

		// GET /term/{tt_id}/{object_type}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/term/(?P<term_taxonomy_id>\d+)/(?P<object_type>set|card|note)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_objects_for_term' ),
					'permission_callback' => array( $this, 'require_logged_in' ), // simple: must be logged-in to inspect
				),
			)
		);

		// DELETE /object/{object_type}/{object_id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/object/(?P<object_type>set|card|note)/(?P<object_id>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'clear_object' ),
					'permission_callback' => array( $this, 'perm_object_from_param' ),
				),
			)
		);
	}

	// Permissions

	public function perm_object_from_body( WP_REST_Request $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) {
			return $auth;
		}

		$type = (string) $req->get_param( 'object_type' );
		$id   = absint( $req->get_param( 'object_id' ) );
		if ( $id <= 0 ) {
			return $this->err( 'invalid_object', 'Invalid object_id.', 400 );
		}

		return $this->check_object_access( $type, $id );
	}

	public function perm_object_from_param( WP_REST_Request $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) {
			return $auth;
		}

		return $this->check_object_access( (string) $req['object_type'], absint( $req['object_id'] ) );
	}

	protected function check_object_access( string $type, int $id ) {
		if ( $type === 'set' ) {
			$row = $this->sets->read( $id );
			if ( ! $row ) {
				return $this->err( 'not_found', 'Set not found.', 404 );
			}
			return $this->can_edit_post( (int) $row['set_post_id'] );
		}

		if ( $type === 'card' ) {
			$row = $this->cards->read( $id );
			if ( ! $row ) {
				return $this->err( 'not_found', 'Card not found.', 404 );
			}
			if ( current_user_can( 'manage_options' ) ) {
				return true;
			}
			return ( (int) ( $row['user_id'] ?? 0 ) === (int) get_current_user_id() )
				? true
				: $this->err( 'forbidden', 'You cannot access this card.', 403 );
		}

		if ( $type === 'note' ) {
			$row = $this->notes->read( $id );
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

		return $this->err( 'invalid_type', 'Invalid object_type.', 400 );
	}

	// Handlers

	public function attach( WP_REST_Request $req ) {
		$ok = $this->repo->attach(
			(string) $req['object_type'],
			absint( $req['object_id'] ),
			absint( $req['term_taxonomy_id'] )
		);
		return $this->ok( array( 'attached' => (int) $ok ) );
	}

	public function detach( WP_REST_Request $req ) {
		$ok = $this->repo->detach(
			(string) $req['object_type'],
			absint( $req['object_id'] ),
			absint( $req['term_taxonomy_id'] )
		);
		return $this->ok( array( 'detached' => (int) $ok ) );
	}

	public function bulk_attach( WP_REST_Request $req ) {
		$count = $this->repo->bulk_attach(
			(string) $req['object_type'],
			absint( $req['object_id'] ),
			(array) ( $req->get_param( 'term_taxonomy_ids' ) ?: array() )
		);
		return $this->ok( array( 'inserted' => (int) $count ) );
	}

	public function sync( WP_REST_Request $req ) {
		$res = $this->repo->sync_object_terms(
			(string) $req['object_type'],
			absint( $req['object_id'] ),
			(array) ( $req->get_param( 'term_taxonomy_ids' ) ?: array() )
		);
		return $this->ok( $res );
	}

	public function list_terms_for_object( WP_REST_Request $req ) {
		$tt_ids = $this->repo->get_tt_ids_for_object( (string) $req['object_type'], absint( $req['object_id'] ) );
		return $this->ok(
			array(
				'object_type'       => (string) $req['object_type'],
				'object_id'         => absint( $req['object_id'] ),
				'term_taxonomy_ids' => $tt_ids,
				'count'             => count( $tt_ids ),
			)
		);
	}

	public function list_objects_for_term( WP_REST_Request $req ) {
		$ids = $this->repo->get_object_ids_for_tt( (string) $req['object_type'], absint( $req['term_taxonomy_id'] ) );
		return $this->ok(
			array(
				'term_taxonomy_id' => absint( $req['term_taxonomy_id'] ),
				'object_type'      => (string) $req['object_type'],
				'object_ids'       => $ids,
				'count'            => count( $ids ),
			)
		);
	}

	public function clear_object( WP_REST_Request $req ) {
		$deleted = $this->repo->clear_object( (string) $req['object_type'], absint( $req['object_id'] ) );
		return $this->ok( array( 'deleted' => (int) $deleted ) );
	}
}
