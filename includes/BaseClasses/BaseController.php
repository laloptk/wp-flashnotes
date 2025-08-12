<?php

namespace WPFlashNotes\BaseClasses;

defined('ABSPATH') || exit;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

abstract class BaseController extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wpfn/v1'; // All endpoints live under /wp-json/wpfn/v1/...
        // Child classes should set $this->rest_base
    }

    protected function ok($data, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response($data, $status);
    }

    protected function err(string $code, string $message, int $status = 400): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }

    public function require_logged_in()
    {
        return is_user_logged_in() ? true : $this->err('not_logged_in', 'Authentication required.', 401);
    }

    public function can_edit_post(int $post_id)
    {
        return current_user_can('edit_post', $post_id) ? true : $this->err('forbidden', 'You cannot edit this post.', 403);
    }

    public function can_delete_post(int $post_id)
    {
        return current_user_can('delete_post', $post_id) ? true : $this->err('forbidden', 'You cannot delete this post.', 403);
    }

    public function absint_or_null($v): ?int
    {
        $i = absint($v);
        return $i > 0 ? $i : null;
    }

    // Lightweight defaults to keep OPTIONS quiet; override when useful.
    public function get_item_schema()
    {
        return [];
    }

    public function get_collection_params()
    {
        return [];
    }

    public function register_routes() {}
}
