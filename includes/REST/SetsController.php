<?php
namespace WPFlashNotes\REST;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\BaseClasses\BaseController;

class SetsController extends BaseController
{
    protected SetsRepository $repo;

    public function __construct()
    {
        parent::__construct();
        $this->rest_base = 'sets';
        $this->repo = new SetsRepository();
    }

    public function register_routes(): void
    {
        // GET /wpfn/v1/sets
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'list_sets'],
                'permission_callback' => [$this, 'require_logged_in'],
                'args'                => [
                    'user'     => ['type'=>'string','required'=>false], // 'me' or numeric string
                    'per_page' => ['type'=>'integer','minimum'=>1,'default'=>20],
                    'offset'   => ['type'=>'integer','minimum'=>0,'default'=>0],
                ],
            ],
        ]);

        // GET /wpfn/v1/sets/by-set-post/{set_post_id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/by-set-post/(?P<set_post_id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_by_set_post'],
                'permission_callback' => [$this, 'perm_edit_setpost_from_param'],
            ],
        ]);

        // POST /wpfn/v1/sets/upsert-by-set-post
        register_rest_route($this->namespace, '/' . $this->rest_base . '/upsert-by-set-post', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'upsert_by_set_post'],
                'permission_callback' => [$this, 'perm_edit_setpost_from_body'],
                'args'                => [
                    'set_post_id' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'minimum'           => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'title' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'post_id' => [
                        'type'              => 'integer',
                        'required'          => false,
                        'minimum'           => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'user_id' => [
                        'type'              => 'integer',
                        'required'          => false,
                        'minimum'           => 1,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        // PATCH /wpfn/v1/sets/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'update_set'],
                'permission_callback' => [$this, 'perm_edit_set_from_id'],
                'args'                => [
                    'title'   => ['type'=>'string','required'=>false],
                    'post_id' => ['type'=>'integer','minimum'=>1,'required'=>false],
                ],
            ],
        ]);

        // DELETE /wpfn/v1/sets/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'delete_set'],
                'permission_callback' => [$this, 'perm_delete_set_from_id'],
            ],
        ]);
    }

    // Permissions

    public function perm_edit_setpost_from_body(WP_REST_Request $req)
    {
        $auth = $this->require_logged_in();
        if ($auth !== true) return $auth;
        $set_post_id = absint($req->get_param('set_post_id'));
        if ($set_post_id <= 0) return $this->err('invalid_post', 'Invalid set_post_id.', 400);
        return $this->can_edit_post($set_post_id);
    }

    public function perm_edit_setpost_from_param(WP_REST_Request $req)
    {
        $auth = $this->require_logged_in();
        if ($auth !== true) return $auth;
        return $this->can_edit_post(absint($req['set_post_id']));
    }

    public function perm_edit_set_from_id(WP_REST_Request $req)
    {
        $auth = $this->require_logged_in();
        if ($auth !== true) return $auth;

        $id  = absint($req['id']);
        $row = $this->repo->read($id);
        if (!$row) return $this->err('not_found', 'Set not found.', 404);

        return $this->can_edit_post((int) $row['set_post_id']);
    }

    public function perm_delete_set_from_id(WP_REST_Request $req)
    {
        $auth = $this->require_logged_in();
        if ($auth !== true) return $auth;

        $id  = absint($req['id']);
        $row = $this->repo->read($id);
        if (!$row) return $this->err('not_found', 'Set not found.', 404);

        return $this->can_delete_post((int) $row['set_post_id']);
    }

    // Handlers

    public function list_sets(WP_REST_Request $req)
    {
        $userParam = $req->get_param('user');
        $per       = absint($req->get_param('per_page')) ?: 20;
        $off       = absint($req->get_param('offset')) ?: 0;

        $user_id = null;
        if ($userParam === 'me' || $userParam === null) {
            $user_id = get_current_user_id();
        } elseif (is_numeric($userParam)) {
            $user_id = absint($userParam);
        }

        if (!$user_id) {
            return $this->ok(['items' => [], 'count' => 0]);
        }

        $rows = $this->repo->list_by_user($user_id, $per, $off);
        return $this->ok(['items' => $rows, 'count' => count($rows)]);
    }

    public function get_by_set_post(WP_REST_Request $req)
    {
        $set_post_id = absint($req['set_post_id']);
        $row = $this->repo->get_by_set_post_id($set_post_id);
        return $this->ok(['item' => $row ?: null]);
    }

    public function upsert_by_set_post(WP_REST_Request $req)
    {
        $payload = [
            'set_post_id' => absint($req['set_post_id']),
        ];
        if ($req->offsetExists('title'))   $payload['title']   = (string) $req['title'];
        if ($req->offsetExists('post_id')) $payload['post_id'] = absint($req['post_id']);
        $payload['user_id'] = $this->absint_or_null($req->get_param('user_id')) ?: get_current_user_id();

        $id  = $this->repo->upsert_by_set_post_id($payload);
        $row = $this->repo->read((int) $id);
        return $this->ok(['id' => (int) $id, 'item' => $row], 201);
    }

    public function update_set(WP_REST_Request $req)
    {
        $id   = absint($req['id']);
        $data = [];
        if ($req->offsetExists('title'))   $data['title']   = (string) $req['title'];
        if ($req->offsetExists('post_id')) $data['post_id'] = absint($req['post_id']);

        if (!$data) return $this->err('no_changes', 'No data to update.', 400);

        $ok  = $this->repo->update($id, $data);
        $row = $this->repo->read($id);
        return $this->ok(['updated' => (int) $ok, 'item' => $row]);
    }

    public function delete_set(WP_REST_Request $req)
    {
        $id = absint($req['id']);
        $ok = $this->repo->delete($id);
        return $this->ok(['deleted' => (int) $ok]);
    }

    // Schema (optional; helps discovery/clients)
    public function get_item_schema()
    {
        return [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'set',
            'type'       => 'object',
            'properties' => [
                'id'          => ['type'=>'integer','minimum'=>1],
                'title'       => ['type'=>'string'],
                'post_id'     => ['type'=>'integer','minimum'=>1,'nullable'=>true],
                'set_post_id' => ['type'=>'integer','minimum'=>1],
                'user_id'     => ['type'=>'integer','minimum'=>1],
                'created_at'  => ['type'=>'string'],
                'updated_at'  => ['type'=>'string'],
            ],
            'required' => ['id','title','set_post_id','user_id'],
        ];
    }
}
