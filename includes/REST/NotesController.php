<?php
namespace WPFlashNotes\REST;

defined('ABSPATH') || exit;

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
class NotesController extends BaseController
{
    protected NotesRepository $repo;

    public function __construct()
    {
        parent::__construct();
        $this->rest_base = 'notes';
        $this->repo = new NotesRepository();
    }

    public function register_routes(): void
    {
        // GET /wpfn/v1/notes
        register_rest_route($this->namespace, '/' . $this->rest_base, [[
            'methods'             => 'GET',
            'callback'            => [$this, 'list_items'],
            'permission_callback' => [$this, 'require_logged_in'],
            'args'                => [
                'user'     => ['type'=>'string','required'=>false], // 'me' or numeric string
                'per_page' => ['type'=>'integer','minimum'=>1,'default'=>20],
                'offset'   => ['type'=>'integer','minimum'=>0,'default'=>0],
            ],
        ]]);

        // GET /wpfn/v1/notes/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [[
            'methods'             => 'GET',
            'callback'            => [$this, 'get_item'],
            'permission_callback' => [$this, 'perm_row_from_id'],
        ]]);

        // POST /wpfn/v1/notes
        register_rest_route($this->namespace, '/' . $this->rest_base, [[
            'methods'             => 'POST',
            'callback'            => [$this, 'create_item'],
            'permission_callback' => [$this, 'require_logged_in'],
        ]]);

        // PATCH /wpfn/v1/notes/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [[
            'methods'             => 'PATCH',
            'callback'            => [$this, 'update_item'],
            'permission_callback' => [$this, 'perm_row_from_id'],
        ]]);

        // DELETE /wpfn/v1/notes/{id}?hard=1
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [[
            'methods'             => 'DELETE',
            'callback'            => [$this, 'delete_item'],
            'permission_callback' => [$this, 'perm_row_from_id'],
            'args'                => [
                'hard' => ['type'=>'boolean','required'=>false],
            ],
        ]]);
    }

    // ---- Permissions -------------------------------------------------------

    public function perm_row_from_id($req)
    {
        $auth = $this->require_logged_in();
        if ($auth !== true) return $auth;

        $id  = absint($req['id']);
        $row = $this->repo->read($id);
        if (!$row) return $this->err('not_found', 'Note not found.', 404);

        if (current_user_can('manage_options')) return true;
        $uid = get_current_user_id();
        return ((int) ($row['user_id'] ?? 0) === (int) $uid)
            ? true
            : $this->err('forbidden', 'You cannot access this note.', 403);
    }

    // ---- Handlers ----------------------------------------------------------

    public function list_items($req)
    {
        $per = absint($req->get_param('per_page')) ?: 20;
        $off = absint($req->get_param('offset'))   ?: 0;

        $userParam = $req->get_param('user');
        $user_id = null;
        if ($userParam === 'me' || $userParam === null) {
            $user_id = get_current_user_id();
        } elseif (is_numeric($userParam)) {
            $user_id = absint($userParam);
        }
        if (!$user_id) return $this->ok(['items' => [], 'count' => 0]);

        $rows = $this->repo->find(['user_id' => (int) $user_id], $per, $off);
        return $this->ok(['items' => $rows, 'count' => count($rows)]);
    }

    public function get_item($req)
    {
        $id  = absint($req['id']);
        $row = $this->repo->read($id);
        return $row ? $this->ok(['item' => $row]) : $this->err('not_found', 'Note not found.', 404);
    }

    public function create_item($req)
    {
        $uid = get_current_user_id();
        $data = $req->get_params();
        $data['user_id'] = $this->absint_or_null($data['user_id'] ?? null) ?: $uid;

        // Minimal expected: title + content
        $id  = $this->repo->insert($data);
        $row = $this->repo->read((int) $id);
        return $this->ok(['id' => (int) $id, 'item' => $row], 201);
    }

    public function update_item($req)
    {
        $id   = absint($req['id']);
        $data = $req->get_params();
        unset($data['id'], $data['_method']);

        if (!$data) return $this->err('no_changes', 'No fields to update.', 400);

        $ok  = $this->repo->update($id, $data);
        $row = $this->repo->read($id);
        return $this->ok(['updated' => (int) $ok, 'item' => $row]);
    }

    public function delete_item($req)
    {
        $id   = absint($req['id']);
        $hard = (bool) $req->get_param('hard');

        $ok = $hard ? $this->repo->delete($id) : $this->repo->soft_delete($id);
        return $this->ok(['deleted' => (int) $ok, 'hard' => (int) $hard]);
    }

    // ---- Schema (optional) -------------------------------------------------

    public function get_item_schema()
    {
        return [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'note',
            'type'       => 'object',
            'properties' => [
                'id'         => ['type'=>'integer','minimum'=>1],
                'user_id'    => ['type'=>'integer','minimum'=>1],
                'title'      => ['type'=>'string'],
                'content'    => ['type'=>'string'],
                'created_at' => ['type'=>'string'],
                'updated_at' => ['type'=>'string'],
                'deleted_at' => ['type'=>'string','nullable'=>true],
            ],
        ];
    }
}
