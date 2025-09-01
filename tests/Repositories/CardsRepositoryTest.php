<?php 

namespace Tests\Repositories;

use WP_UnitTestCase;
use WPFlashNotes\Repos\CardsRepository;
class CardsRepositoryTest extends WP_UnitTestCase {

    protected CardsRepository $repo;

    public function setUp(): void {
        parent::setUp();
        $this->repo = new CardsRepository();
    }

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

    public function test_create_and_read() {
        $data = [
            'question'     => 'Q1',
            'answers_json' => json_encode(['A1']),
            'explanation'  => 'E1',
            'user_id'      => get_current_user_id(),
        ];

        $id = $this->repo->insert($data);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $row = $this->repo->read($id);

        $this->assertNotNull($row);
        $this->assertSame('Q1', $row['question']);
        $this->assertSame(json_encode(['A1']), $row['answers_json']);
        $this->assertSame('E1', $row['explanation']);
        $this->assertSame(get_current_user_id(), (int) $row['user_id']);
    }

    public function test_update() {
        $data = [
            'question'     => 'Q1',
            'answers_json' => json_encode(['A1']),
            'explanation'  => 'E1',
            'user_id'      => get_current_user_id(),
        ];

        $id = $this->repo->insert($data);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
        
        $update_data = [
            'question'     => 'Updated',
            'answers_json' => json_encode(['Updated Answer']),
        ];

        $updated = $this->repo->update($id, $update_data);
        $this->assertTrue($updated, 'Update should return true when data changes');
        
        $row = $this->repo->read($id);
        $this->assertNotNull($row);
        $this->assertSame('Updated', $row['question']);
        $this->assertSame(json_encode(['Updated Answer']), $row['answers_json']);
        $this->assertSame('E1', $row['explanation'], 'Unchanged fields should remain the same');
    }

    public function test_delete() {
        $data = [
            'question'     => 'Delete Me?',
            'answers_json' => json_encode(['Delete Me Too!']),
            'explanation'  => 'This row is going to get deleted.',
            'user_id'      => get_current_user_id(),
        ];

        $id = $this->repo->insert($data);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $deleted = $this->repo->delete($id);
        $this->assertTrue($deleted);

        $row = $this->repo->read($id);
        $this->assertNull($row);
    }

    public function test_soft_delete() {
        $data = [
            'question'     => 'I\'m not going to get deleted?',
            'answers_json' => json_encode(['Kind of, but not really, yet.']),
            'explanation'  => 'Soft delete just changes the value of the column: updated_at',
            'user_id'      => get_current_user_id(),
        ];

        $id = $this->repo->insert($data);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $soft_deleted = $this->repo->soft_delete($id);
        $this->assertTrue($soft_deleted);

        $row = $this->repo->read($id);
        $this->assertNotNull($row);
        $this->assertNotNull($row['deleted_at']);
        $this->assertSame('I\'m not going to get deleted?', $row['question']);
        $this->assertSame(json_encode(['Kind of, but not really, yet.']), $row['answers_json']);
        $this->assertSame('Soft delete just changes the value of the column: updated_at', $row['explanation']);
    }
}