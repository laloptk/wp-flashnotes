<?php
namespace WPFlashNotes\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\BaseClasses\BaseController;

/**
 * CardsController
 *
 * CRUD for cards in {prefix}wpfn_cards via CardsRepository (BaseRepository).
 * Permissions: owner (row.user_id) or site admin.
 */
class CardsController extends BaseController {

	protected CardsRepository $repo;

	public function __construct() {
		parent::__construct();
		$this->rest_base = 'cards';
		$this->repo      = new CardsRepository();
	}

	public function register_routes(): void {
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
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'per_page' => array(
							'type'              => 'integer',
							'minimum'           => 1,
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
						'offset'   => array(
							'type'              => 'integer',
							'minimum'           => 0,
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						's'        => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/find',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'find_cards' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'perm_row_from_id' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'perm_row_from_id' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'perm_row_from_id' ),
				),
			)
		);

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
	}

	// ---- Permissions -------------------------------------------------------

	public function perm_row_from_id( WP_REST_Request $req ) {
		$auth = $this->require_logged_in();
		if ( $auth !== true ) {
			return $auth;
		}

		$id  = absint( $req['id'] );
		$row = $this->repo->read( $id );
		if ( ! $row ) {
			return $this->err( 'not_found', 'Card not found.', 404 );
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$uid = get_current_user_id();
		return ( (int) ( $row['user_id'] ?? 0 ) === (int) $uid )
			? true
			: $this->err( 'forbidden', 'You cannot access this card.', 403 );
	}

	// ---- Handlers ----------------------------------------------------------

	public function list_items( WP_REST_Request $req ) {
		return $this->safe( function() use ( $req ) {
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

			$search_query = $req->get_param( 's' ) ?: '';
			$args         = [
				'where'  => [ 'user_id' => (int) $user_id ],
				'search' => [
					'question'     => $search_query,
					'answers' => $search_query,
				],
				'limit'  => absint( $req->get_param( 'per_page' ) ) ?: 20,
				'offset' => absint( $req->get_param( 'offset' ) ) ?: 0,
			];

			$rows = $this->repo->find( $args );
			return $this->ok( [ 'items' => $rows, 'count' => count( $rows ) ] );
		});
	}

	public function get_item( $req ) {
		return $this->safe( function() use ( $req ) {
			$id  = absint( $req->get_param( 'id' ) );
			$row = $this->repo->read( $id );

			if ( ! $row ) {
				return $this->err( 'not_found', 'Card not found.', 404 );
			}

			return $this->ok( [ 'item' => $row ] );
		});
	}

	public function find_cards( WP_REST_Request $req ) {
		return $this->safe( function() use ( $req ) {
			$params = $req->get_params();

			$args = [ 'where' => [], 'search' => [], 'limit' => null, 'offset' => null ];

			foreach ( $params as $key => $val ) {
				if ( $key === 'limit' ) {
					$limit = absint( $val );
					if ( $limit > 0 ) $args['limit'] = $limit;
					continue;
				}
				if ( $key === 'offset' ) {
					$offset = absint( $val );
					if ( $offset > 0 ) $args['offset'] = $offset;
					continue;
				}
				if ( $key === 's' && is_string( $val ) && trim( $val ) !== '' ) {
					$term = trim( $val );
					$args['search'] = [
						'question'    => $term,
						'answer'      => $term,
						'explanation' => $term,
					];
					continue;
				}
				if ( $val === '' || $val === null ) continue;
				if ( is_string( $val ) && preg_match( '/^-?\d+$/', $val ) ) $val = (int) $val;
				$args['where'][ $key ] = $val;
			}

			$rows = $this->repo->find( $args );
			return $this->ok( [ 'items' => $rows ] );
		});
	}

	public function create_item( $req ) {
		return $this->safe( function() use ( $req ) {
			$data            = $req->get_params();
			$data['user_id'] = $this->absint_or_null( $data['user_id'] ?? null ) ?: get_current_user_id();
			$id              = $this->repo->insert( $data );
			$row             = $this->repo->read( (int) $id );
			return $this->ok( [ 'id' => (int) $id, 'item' => $row ], 201 );
		});
	}

	public function update_item( $req ) {
		return $this->safe( function() use ( $req ) {
			$id   = absint( $req['id'] );
			$data = $req->get_params();
			unset( $data['id'], $data['_method'] );
			if ( empty( $data ) ) {
				return $this->err( 'no_changes', 'No fields to update.', 400 );
			}
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
			'title'      => 'card',
			'type'       => 'object',
			'properties' => [
				'id'         => [ 'type' => 'integer', 'minimum' => 1 ],
				'user_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
				'title'      => [ 'type' => 'string' ],
				'question'   => [ 'type' => 'string' ],
				'answer'     => [ 'type' => 'string' ],
				'created_at' => [ 'type' => 'string' ],
				'updated_at' => [ 'type' => 'string' ],
				'deleted_at' => [ 'type' => 'string', 'nullable' => true ],
			],
		];
	}
}
