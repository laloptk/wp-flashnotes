<?php 

namespace Tests\Repositories;

use WP_UnitTestCase;

class CardsControllerTest extends WP_UnitTestCase {

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 2) . '/wp-flashnotes.php';
        require_once WPFN_PLUGIN_DIR . 'includes/DataBase/Schema/tasks.php';

        $tasks = wpfn_schema_tasks();

        $done = [];
        $bySlug = [];
        foreach ($tasks as $t) {
            $bySlug[$t['slug']] = $t;
        }

        $run = function ($slug) use (&$run, &$done, $bySlug) {
            if (in_array($slug, $done, true)) {
                return;
            }
            $t = $bySlug[$slug] ?? null;
            if (!$t) {
                return;
            }
            foreach ($t['deps'] as $dep) {
                $run($dep);
            }
            ($t['run'])();
            $done[] = $slug;
        };

        foreach ($tasks as $t) {
            $run($t['slug']);
        }
    }

    public function test_create_item() {
        $user_id = $this->factory()->user->create([ 'role' => 'administrator' ]);
        wp_set_current_user($user_id);
        $request = new \WP_REST_Request( 'POST', '/wpfn/v1/cards' );
        $request->set_body_params([
            'question'     => 'Being created?',
            'answers_json' => json_encode(['Yes, we are trying.']),
            'explanation'  => 'This request would create a DB row, a card.',
            'user_id'      => $user_id,
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();
        $id = $data['id'];
        $this->assertNotNull($id);
        $this->assertGreaterThan(0, $id);
    }

    public function test_get_item() {
        $user_id = $this->factory()->user->create([ 'role' => 'administrator' ]);
        wp_set_current_user($user_id);

        $request = new \WP_REST_Request( 'POST', '/wpfn/v1/cards' );
        $request->set_body_params([
            'question'     => 'Being created?',
            'answers_json' => json_encode(['Yes, we are trying.']),
            'explanation'  => 'This request would create a DB row, a card.',
            'user_id'      => $user_id,
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();
        $id = $data['id'];
        $this->assertNotNull($id);
        $this->assertGreaterThan(0, $id);

        $req = new \WP_REST_Request('GET', "/wpfn/v1/cards/{$id}");
        $res = rest_do_request($req);
        $data = $res->get_data();
        $row = $data['item'];
        $this->assertNotNull($row);
        $this->assertSame('Being created?', $row['question']);
        $this->assertSame(json_encode(['Yes, we are trying.']), $row['answers_json']);
        $this->assertSame('This request would create a DB row, a card.', $row['explanation']);
    }
}