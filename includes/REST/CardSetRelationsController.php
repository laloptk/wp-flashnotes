<?php
namespace WPFlashNotes\REST;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WPFlashNotes\Repos\CardSetRelationsRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\BaseClasses\BaseController;

class CardSetRelationsController extends BaseController
{
    protected CardSetRelationsRepository $repo;
    protected SetsRepository $sets;
    protected CardsRepository $cards;

    public function __construct()
    {
        parent::__construct();
        $this->rest_base = 'set-cards';
        $this->repo  = new CardSetRelationsRepository();
        $this->sets  = new SetsRepository();
        $this->cards = new CardsRepository();
    }

    public function register_routes(): void
    {
        // POST /wpfn/v1/set-cards/attach
        register_rest_route($this->namespace, '/' . $this->rest_base . '/attach', [[
            'methods'             => 'POST',
            'callback'            => [$this, 'attach'],
            'permission_callback' => [$this, 'perm_edit_set_from_body'],
            'args'                => [
                'set_id'  => ['type'=>'integer','minimum'=>1,'required'=>true],
                'card_id' => ['type'=>'integer','minimum'=>1,'required'=>true],
            ],
        ]]);

        // POST /wpfn/v1/set-cards/detach
        register_rest_route($this->namespace, '/' . $this->rest_base . '/detach', [[
            'methods'             => 'POST',
            'callback'            => [$this, 'detach'],
            'permission_callback' => [$this, 'perm_edit_set_from_body'],
            'args'                => [
                'set_id'  => ['type'=>'integer','minimum'=>1,'required'=>true],
                'card_id' => ['type'=>'integer','minimum'=>1,'required'=>true],
            ],
        ]]);

        // POST /wpfn/v1/set-cards/sync
        register_rest_route($this->namespace, '/' . $this->rest_base . '/sync', [[
            'methods'             => 'POST',
            'callback'            => [$this, 'sync'],
            'permission_callback' => [$this, 'perm_edit_set_from_body'],
            'args'                => [
                'set_id'   => ['type'=>'integer','minimum'=>1,'required'=>true],
                'card_ids' => ['type'=>'array','items'=>['type'=>'integer'],'required'=>true],
            ],
        ]]);

        // GET /wpfn/v1/set-cards/set/{set_id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/set/(?P<set_id>\d+)', [[
            'methods'             => 'GET',
            'callback'            => [$this, 'list_cards_for_set'],
            'permission_callback' => [$this, 'perm_edit_set_from_param'],
        ]]);

        // GET /wpfn/v1/set-cards/card/{card_id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/card/(?P<card_id>\d+)', [[
            'methods'             => 'GET',
            'callback'            => [$this, 'list_sets_for_card'],
            'permission_callback' => [$this, 'perm_card_from_param'],
        ]]);

        // DELETE /wpfn/v1/set-cards/set/{set_id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/set/(?P<set_id>\d+)', [[
            'methods'             => 'DELETE',
            'callback'            => [$this, 'clear_set'],
            'permission_callback' => [$this, 'perm_edit_set_from_param'],
        ]]);
    }

    // Permissions

    public function perm_edit_set_from_body(WP_REST_Request $req)
    {
        $auth = $this->require_logged_in();
        if ($auth !== true) return $auth;

        $set_id = absint($req->get_param('set_id'));
        if ($set_id <= 0) return $this->err('invalid_set', 'Invalid set_id.', 400);

        $row = $this->sets->read($set_id);
        if (!$row) return $this->err('not_found', 'Set not found.', 404);

        return $this->can_edit_post((int) $row['set_post_id']);
    }

    public function perm_edit_set_from_param(WP_REST_Request $req)
    {
        $auth = $this->require_logged_in();
        if ($auth !== true) return $auth;

        $row = $this->sets->read(absint($req['set_id']));
        if (!$row) return $this->err('not_found', 'Set not found.', 404);

        return $this->can_edit_post((int) $row['set_post_id']);
    }

    public function perm_card_from_param(WP_REST_Request $req)
    {
        $auth = $this->require_logged_in();
        if ($auth !== true) return $auth;

        $row = $this->cards->read(absint($req['card_id']));
        if (!$row) return $this->err('not_found', 'Card not found.', 404);

        if (current_user_can('manage_options')) return true;
        return ((int) ($row['user_id'] ?? 0) === (int) get_current_user_id())
            ? true
            : $this->err('forbidden', 'You cannot access this card.', 403);
    }

    // Handlers

    public function attach(WP_REST_Request $req)
    {
        $ok = $this->repo->attach(absint($req['card_id']), absint($req['set_id']));
        return $this->ok(['attached' => (int) $ok]);
    }

    public function detach(WP_REST_Request $req)
    {
        $ok = $this->repo->detach(absint($req['card_id']), absint($req['set_id']));
        return $this->ok(['detached' => (int) $ok]);
    }

    public function sync(WP_REST_Request $req)
    {
        $res = $this->repo->sync_set_cards(absint($req['set_id']), (array) ($req->get_param('card_ids') ?: []));
        return $this->ok($res);
    }

    public function list_cards_for_set(WP_REST_Request $req)
    {
        $list = $this->repo->get_card_ids_for_set(absint($req['set_id']));
        return $this->ok(['set_id' => absint($req['set_id']), 'card_ids' => $list, 'count' => count($list)]);
    }

    public function list_sets_for_card(WP_REST_Request $req)
    {
        $list = $this->repo->get_set_ids_for_card(absint($req['card_id']));
        return $this->ok(['card_id' => absint($req['card_id']), 'set_ids' => $list, 'count' => count($list)]);
    }

    public function clear_set(WP_REST_Request $req)
    {
        $deleted = $this->repo->clear_set(absint($req['set_id']));
        return $this->ok(['deleted' => (int) $deleted]);
    }
}
