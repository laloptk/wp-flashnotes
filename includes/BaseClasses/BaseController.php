<?php

namespace WPFlashNotes\BaseClasses;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Response;
use WPFlashNotes\Errors\WPFlashNotesError;

abstract class BaseController extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = \WPFN_API_NAMESPACE;
	}

	protected function ok( $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}

	protected function err( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	public function require_logged_in() {
		return is_user_logged_in()
			? true
			: $this->err( 'not_logged_in', 'Authentication required.', 401 );
	}

	public function can_edit_post( int $post_id ) {
		return current_user_can( 'edit_post', $post_id )
			? true
			: $this->err( 'forbidden', 'You cannot edit this post.', 403 );
	}

	public function can_delete_post( int $post_id ) {
		return current_user_can( 'delete_post', $post_id )
			? true
			: $this->err( 'forbidden', 'You cannot delete this post.', 403 );
	}

	public function absint_or_null( $v ): ?int {
		$i = absint( $v );
		return $i > 0 ? $i : null;
	}

	/**
	 * Execute an operation safely and translate WPFlashNotesError / Throwable
	 * into a standardized REST response.
	 */
	protected function safe( callable $callback ): WP_REST_Response|WP_Error {
		try {
			return $callback();
		} catch ( WPFlashNotesError $e ) {
			return $this->err(
				$e->kind,
				$e->getMessage(),
				$e->getCode() ?: 400
			);
		} catch ( \Throwable $e ) {
			return $this->err(
				'internal_error',
				'Unexpected internal error: ' . $e->getMessage(),
				500
			);
		}
	}

	// --- Quiet defaults ------------------------------------------------------

	public function get_item_schema() {
		return array();
	}

	public function get_collection_params() {
		return array();
	}

	public function register_routes() {}
}
