<?php 

require_once __DIR__ . '/helpers/blocks.php';

use WPFNFlashnotes\Blocks\BlockTransformer;
use WPFNFlashNotes\Blocks\Transformers\CardBlockStrategy;

class LearningModeTest extends WP_UnitTestCase {
    public function test_custom_tables_exist() {
        global $wpdb;

        $expected_tables = [
            $wpdb->prefix . 'wpfn_cards',
            $wpdb->prefix . 'wpfn_notes',
            $wpdb->prefix . 'wpfn_sets',
            $wpdb->prefix . 'wpfn_card_set_relations',
            $wpdb->prefix . 'wpfn_note_set_relations',
            $wpdb->prefix . 'wpfn_taxonomy_relations',
            $wpdb->prefix . 'wpfn_object_usage',
        ];

        foreach($expected_tables as $table_name) {
            $found = $wpdb->get_var(
                $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name )
            );

            $this->assertEquals($table_name, $found);
        }
    }

    public function test_creating_post_with_card_creates_db_row() {
        global $wpdb;

        $block_id = 'block_' . wp_generate_uuid4();
        
        $post_content = wpfn_post_content_with_one_card(
            wpfn_card_flip_block(
                array(
                    'block_id'    => $block_id,
                    'question'    => 'What is the capital of France?',
                    'answers'     => array( 'Paris' ),
                    'explanation' => 'Paris is the capital and largest city of France.',
                )
            )
        );

        $user_id = $this->factory->user->create();
		wp_set_current_user($user_id);

        $post_id = $this->factory->post->create(
            array(
                'post_title' => 'Post title',
                'post_content' => $post_content,
            )
        );

        $table_name = $wpdb->prefix . 'wpfn_cards';

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE block_id=%s", $block_id)
        );

        $this->assertNotNull($row);
    }

    public function test_creating_post_with_cards_creates_db_rows() {
        global $wpdb;
        
        $ids = [];
        $blocks = [];

        for($i = 0; $i <= 5; $i++) {
            $block_id = 'block_' . wp_generate_uuid4();
            $ids[] = $block_id;
        
            $blocks[] = wpfn_card_flip_block(
                array(
                    'block_id'    => $block_id,
                    'question'    => 'What is the capital of France?',
                    'answers'     => array( 'Paris' ),
                    'explanation' => 'Paris is the capital and largest city of France.',
                )
            );
        }

        $post_content = wpfn_serialize_blocks($blocks);

        $user_id = $this->factory->user->create();
		wp_set_current_user($user_id);

        $post_id = $this->factory->post->create(
            array(
                'post_title' => 'Post title',
                'post_content' => $post_content,
            )
        );

        $table_name = $wpdb->prefix . 'wpfn_cards';

        foreach($ids as $id) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_name} WHERE block_id=%s", $id)
            );

            $this->assertNotNull($row);
            $this->assertSame( (string) $user_id, $row->user_id );
            $this->assertSame( 'active', $row->status );
        }
    }

    public function test_updating_post_content_creates_new_db_row() {
        global $wpdb;

        $blocks = [];
        $ids = [];
        
        for($i = 0; $i <= 5; $i++) {
            $block_id = 'block_' . wp_generate_uuid4();
            $ids[] = $block_id;
            $blocks[] = wpfn_card_flip_block(
                array(
                    'block_id'    => $block_id,
                    'question'    => 'What is the capital of France?',
                    'answers'     => array( 'Paris' ),
                    'explanation' => 'Paris is the capital and largest city of France.',
                )
            );
        }

        $create_num_blocks = count($blocks);

        $post_content = wpfn_serialize_blocks($blocks);

        $user_id = $this->factory->user->create();
		wp_set_current_user($user_id);

        $post_id = $this->factory->post->create(
            array(
                'post_title' => 'Post title',
                'post_content' => $post_content,
            )
        );

        $new_block_id = 'block_' . wp_generate_uuid4();
        $ids[] = $new_block_id;

        $post_content .= wpfn_post_content_with_one_card(
            wpfn_card_flip_block(
                array(
                    'block_id'    => $new_block_id,
                    'question'    => 'What is the capital of Mexico?',
                    'answers'     => array( 'CDMX' ),
                    'explanation' => 'Paris is the capital and largest city of Mexico.',
                )
            )
        );

        $this->factory->post->update_object( $post_id, array('post_content' => $post_content) );

        $table_name = $wpdb->prefix . 'wpfn_cards';

        $new_row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE block_id=%s", $block_id)
        );

        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%s' ) );

        $sql = "SELECT * FROM {$table_name} WHERE block_id IN ($placeholders)";

        $results = $wpdb->get_results(
            $wpdb->prepare( $sql, ...$ids )
        );

        $total_cards = $results === null ? 0 : count($results);

        $this->assertNotNull($new_row, "The card row was created.");
        // Assert that there are not duplicate cards
        $this->assertSame($create_num_blocks + 1, $total_cards, "There are no duplicate rows in the table");
        $this->assertSame( (string) $user_id, $new_row->user_id, "The post was created with the current user_id");
        $this->assertSame( 'active', $new_row->status, "The card was created with status of active");
    }

    public function test_correct_card_status_when_deleting_republishing_cards() {
        global $wpdb;

        $user_id = $this->factory->user->create();

        wp_set_current_user( $user_id );

        for($i = 0; $i <= 5; $i++) {
            $block_id = 'block_' . wp_generate_uuid4();
            $ids[] = $block_id;
        
            $blocks[] = wpfn_card_flip_block(
                array(
                    'block_id'    => $block_id,
                    'question'    => 'What is the capital of France?',
                    'answers'     => array( 'Paris' ),
                    'explanation' => 'Paris is the capital and largest city of France.',
                )
            );
        }

        $post_content = wpfn_serialize_blocks($blocks);

        $post_id = $this->factory->post->create(
            array(
                'post_content' => $post_content
            )
        );

        $this->assertGreaterThan(0, $post_id);

        $table_name = $wpdb->prefix . 'wpfn_cards';

        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%s' ) );

        $sql = "SELECT * FROM {$table_name} WHERE block_id IN ($placeholders)";

        $results = $wpdb->get_results(
            $wpdb->prepare( $sql, ...$ids )
        );

        $this->assertSame(count($ids), count($results));

        foreach($ids as $id) {
            $card = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_name} WHERE block_id=%s", $id)
            );

            $this->assertSame('active', $card->status);
        }
        
        $this->factory->post->update_object($post_id, array('post_content' => ''));

        $results = $wpdb->get_results(
            $wpdb->prepare( $sql, ...$ids )
        );

        $this->assertSame(count($ids), count($results));

        foreach($ids as $id) {
            $card = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_name} WHERE block_id=%s", $id)
            );

            $this->assertSame('orphan', $card->status);
        }

        $target_block_id = $ids[0];
        $target_card = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, block_id FROM {$table_name} WHERE block_id=%s",
                $target_block_id
            )
        );

        $this->assertNotNull( $target_card, 'Target orphan card should exist before inserter reinsertion.' );

        $inserter_block = wpfn_block(
            'wpfn/inserter',
            array(
                'object_type'   => 'card',
                'id'            => (string) $target_card->id,
                'block_id'      => wp_generate_uuid4(),
                'card_block_id' => $target_card->block_id,
            )
        );

        $post_content = WPFlashNotes\Helpers\BlockFormatter::serialize( array( $inserter_block ) );

        $this->factory->post->update_object(
            $post_id,
            array(
                'post_content' => $post_content,
            )
        );

        $results = $wpdb->get_results(
            $wpdb->prepare( $sql, ...$ids )
        );

        $this->assertSame(count($ids), count($results));

        foreach($ids as $id) {
            $card = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_name} WHERE block_id=%s", $id)
            );

            $expected_status = $id === $target_block_id ? 'active' : 'orphan';
            $this->assertSame( $expected_status, $card->status );
        }
    }
}
