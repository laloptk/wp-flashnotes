<?php
namespace WPFlashNotes\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPFlashNotes\Repos\ObjectUsageRepository;
use WPFlashNotes\BaseClasses\BaseController;

class ObjectUsageController extends BaseController {

	protected ObjectUsageRepository $repo;

	public function __construct() {
		parent::__construct();
		$this->rest_base = 'usage';
		$this->repo      = new ObjectUsageRepository();
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/attach',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'attach' ),
					'permission_callback' => array( $this, 'perm_for_post_body' ),
					'args'                => array(
						'object_type' => array(
							'type'     => 'string',
							'enum'     => array( 'card', 'note' ),
							'required' => true,
						),
						'object_id'   => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
						'post_id'     => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
						'block_id'    => array(
							'type'     => 'string',
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
					'permission_callback' => array( $this, 'perm_for_post_body' ),
					'args'                => array(
						'object_type' => array(
							'type'     => 'string',
							'enum'     => array( 'card', 'note' ),
							'required' => true,
						),
						'object_id'   => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
						'post_id'     => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
						'block_id'    => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/block/sync',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'sync_block' ),
					'permission_callback' => array( $this, 'perm_for_post_body' ),
					'args'                => array(
						'post_id'     => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
						'block_id'    => array(
							'type'     => 'string',
							'required' => true,
						),
						'object_type' => array(
							'type'     => 'string',
							'enum'     => array( 'card', 'note' ),
							'required' => true,
						),
						'object_ids'  => array(
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
			'/' . $this->rest_base . '/post/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_for_post' ),
					'permission_callback' => array( $this, 'perm_for_post_param' ),
					'args'                => array(
						'object_type' => array(
							'type'     => 'string',
							'enum'     => array( 'card', 'note' ),
							'required' => false,
						),
					),
				),
			)
		);
	}

	// Permissions

	public function perm_for_post_body( WP_REST_Request $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) {
			return $auth;
		}
		$post_id = absint( $req->get_param( 'post_id' ) );
		if ( $post_id <= 0 ) {
			return $this->err( 'invalid_post', 'Missing/invalid post_id.', 400 );
		}
		return $this->can_edit_post( $post_id );
	}

	public function perm_for_post_param( WP_REST_Request $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) {
			return $auth;
		}
		return $this->can_edit_post( absint( $req['post_id'] ) );
	}

	// Handlers

	public function attach( WP_REST_Request $req ) {
		$ok = $this->repo->attach(
			(string) $req['object_type'],
			absint( $req['object_id'] ),
			absint( $req['post_id'] ),
			(string) $req['block_id']
		);
		return $this->ok( array( 'attached' => (int) $ok ) );
	}

	public function detach( WP_REST_Request $req ) {
		$ok = $this->repo->detach(
			(string) $req['object_type'],
			absint( $req['object_id'] ),
			absint( $req['post_id'] ),
			(string) $req['block_id']
		);
		return $this->ok( array( 'detached' => (int) $ok ) );
	}

	public function sync_block( WP_REST_Request $req ) {
		$res = $this->repo->sync_block_objects(
			absint( $req['post_id'] ),
			(string) $req['block_id'],
			(string) $req['object_type'],
			(array) ( $req->get_param( 'object_ids' ) ?: array() )
		);
		return $this->ok( $res );
	}

	public function list_for_post( WP_REST_Request $req ) {
		$pid  = absint( $req['post_id'] );
		$type = $req->get_param( 'object_type' );

		$out = array(
			'post_id' => $pid,
			'cards'   => array(),
			'notes'   => array(),
		);

		if ( $type === 'card' ) {
			$out['cards'] = $this->repo->get_relationships( 'card', $pid );
		} elseif ( $type === 'note' ) {
			$out['notes'] = $this->repo->get_relationships( 'note', $pid );
		} else {
			$out['cards'] = $this->repo->get_relationships( 'card', $pid );
			$out['notes'] = $this->repo->get_relationships( 'note', $pid );
		}

		return $this->ok( $out );
	}

	// Schema (optional but nice for OPTIONS/discovery)

	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'object_usage',
			'type'       => 'object',
			'properties' => array(
				'object_type' => array(
					'type' => 'string',
					'enum' => array( 'card', 'note' ),
				),
				'object_id'   => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'post_id'     => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'block_id'    => array( 'type' => 'string' ),
			),
			'required'   => array( 'object_type', 'object_id', 'post_id', 'block_id' ),
		);
	}
}
