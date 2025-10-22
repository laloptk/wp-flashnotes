<?php
namespace WPFlashNotes\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
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
		// GET /wpfn/v1/cards
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

		// GET /wpfn/v1/cards/find
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

		// GET /wpfn/v1/cards/{id}
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

		// POST /wpfn/v1/cards
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

		// PATCH /wpfn/v1/cards/{id}
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

		// DELETE /wpfn/v1/cards/{id}?hard=1
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

	/** Owner-or-admin gate for routes receiving {id} */
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

	public function perm_row_from_block_id( \WP_REST_Request $request ) {
		$block_id = $request['block_id'];

		if ( ! is_string( $block_id ) || $block_id === '' ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Invalid block_id parameter.', 'wp-flashnotes' ),
				array( 'status' => 400 )
			);
		}

		// Try to load the row
		$row = $this->repo->get_by_block_id( $block_id );

		if ( ! $row ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Card not found.', 'wp-flashnotes' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'read' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view cards.', 'wp-flashnotes' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}


	// ---- Handlers ----------------------------------------------------------

	public function list_items( WP_REST_Request $req ) {
		$user_param = $req->get_param( 'user' );
		$user_id    = null;
		if ( $user_param === 'me' || $user_param === null ) {
			$user_id = get_current_user_id();
		} elseif ( is_numeric( $user_param ) ) {
			$user_id = absint( $user_param );
		}
		if ( ! $user_id ) {
			return $this->ok(
				array(
					'items' => array(),
					'count' => 0,
				)
			);
		}

		$search_query = $req->get_param( 's' ) ?: '';
		$args         = array(
			'where'  => array(
				'user_id' => (int) $user_id,
			),
			'search' => array(
				'question'     => $search_query,
				'answers_json' => $search_query,
			),
			'limit'  => absint( $req->get_param( 'per_page' ) ) ?: 20,
			'offset' => absint( $req->get_param( 'offset' ) ) ?: 0,
		);

		$rows = $this->repo->find( $args );

		return $this->ok(
			array(
				'items' => $rows,
				'count' => count( $rows ),
			)
		);
	}

	public function get_item( $req ) {
		$id  = absint( $req->get_param( 'id' ) );
		$row = $this->repo->read( $id );

		if ( ! $row ) {
			return $this->err( 'not_found', 'Card not found.', 404 );
		}

		return $this->ok( array( 'item' => $row ) );
	}

	public function find_cards( \WP_REST_Request $request ) {
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
						'question'    => $term,
						'answer'      => $term,
						'explanation' => $term,
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
		$uid = get_current_user_id();
		// Accept either 'question' or 'title' as prompt/title, and 'answer'
		$data            = $req->get_params();
		$data['user_id'] = $this->absint_or_null( $data['user_id'] ?? null ) ?: $uid;

		try {
			$id = $this->repo->insert( $data );
		} catch ( \Throwable $e ) {
			// Fallback: map title->question or question->title, then retry
			if ( ! isset( $data['question'] ) && isset( $data['title'] ) ) {
				$data['question'] = $data['title'];
			} elseif ( ! isset( $data['title'] ) && isset( $data['question'] ) ) {
				$data['title'] = $data['question'];
			}
			$id = $this->repo->insert( $data );
		}

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
		unset( $data['id'], $data['_method'] ); // cleanliness

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
			'title'      => 'card',
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
				'question'   => array( 'type' => 'string' ),
				'answer'     => array( 'type' => 'string' ),
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
