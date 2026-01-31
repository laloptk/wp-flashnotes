<?php
require_once __DIR__ . '/BaseTestCase.php';
class BaseTestCaseTest extends BaseTestCase
{

	/**
	 * Test that custom database tables are created.
	 */
	public function test_custom_tables_exist()
	{
		global $wpdb;

		// Replace these with your actual table names
		$tables = array(
			$wpdb->prefix . 'wpfn_card_set_relations',
			$wpdb->prefix . 'wpfn_cards',
			$wpdb->prefix . 'wpfn_notes',
			$wpdb->prefix . 'wpfn_note_set_relations',
			$wpdb->prefix . 'wpfn_object_usage',
			$wpdb->prefix . 'wpfn_sets',
			$wpdb->prefix . 'wpfn_taxonomy_relations',
			// Add all your custom table names here
		);

		foreach ($tables as $table_name) {
			$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");

			$this->assertEquals(
				$table_name,
				$table_exists,
				"Table {$table_name} should exist after BaseTestCase setup"
			);
		}
	}

	/**
	 * Test that we can insert data into custom tables.
	 */
	public function test_can_insert_into_custom_tables()
	{
		global $wpdb;

		// Create a user and set as current user
		$user_id = $this->factory->user->create();
		wp_set_current_user($user_id);

		// Generate a unique block ID
		$block_id = 'block_' . wp_generate_uuid4();

		$post_content = '<!-- wp:wpfn/card-flip {"block_id":"' . $block_id . '","card_type":"flip","question":"What is the capital of France?","answers":["Paris"],"explanation":"Paris is the capital and largest city of France."} -->
<div class="wp-block-wpfn-card-flip wpfn-card">
	<!-- wp:wpfn/slot {"role":"question","content":"What is the capital of France?"} -->
	<div class="wp-block-wpfn-slot">What is the capital of France?</div>
	<!-- /wp:wpfn/slot -->
	
	<!-- wp:wpfn/slot {"role":"answer","content":"Paris"} -->
	<div class="wp-block-wpfn-slot">Paris</div>
	<!-- /wp:wpfn/slot -->
	
	<!-- wp:wpfn/slot {"role":"explanation","content":"Paris is the capital and largest city of France."} -->
	<div class="wp-block-wpfn-slot">Paris is the capital and largest city of France.</div>
	<!-- /wp:wpfn/slot -->
</div>
<!-- /wp:wpfn/card-flip -->';

		// No need to specify post_author - will use current user
		$post_id = $this->factory->post->create(
			array(
				'post_content' => $post_content,
				'post_status' => 'publish',
				'post_title' => 'Test Post with Card',
			)
		);

		$this->assertGreaterThan(0, $post_id, 'Post should be created');

		$table_name = $wpdb->prefix . 'wpfn_cards';

		$inserted_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE block_id = %s",
				$block_id
			)
		);

		$this->assertNotNull($inserted_row, 'Card should be automatically saved to database when post is created');
		$this->assertEquals($block_id, $inserted_row->block_id, 'Block ID should match');
		$this->assertEquals($user_id, $inserted_row->user_id, 'User ID should match');
		$this->assertEquals('flip', $inserted_row->card_type, 'Card type should match');
		$this->assertEquals('active', $inserted_row->status, 'Status should match');
	}

	/**
	 * Test that the hook callback is deleting the flashnotes when deleting a post.
	 */
	public function test_post_deletion_hook_works()
	{
		global $wpdb;

		// Create a user and set as current user
		$user_id = $this->factory->user->create();
		wp_set_current_user($user_id);

		// Generate a unique block ID
		$block_id = 'block_' . wp_generate_uuid4();

		$post_content = '<!-- wp:wpfn/card-flip {"block_id":"' . $block_id . '","card_type":"flip","question":"What is the capital of France?","answers":["Paris"],"explanation":"Paris is the capital and largest city of France."} -->
<div class="wp-block-wpfn-card-flip wpfn-card">
	<!-- wp:wpfn/slot {"role":"question","content":"What is the capital of France?"} -->
	<div class="wp-block-wpfn-slot">What is the capital of France?</div>
	<!-- /wp:wpfn/slot -->
	
	<!-- wp:wpfn/slot {"role":"answer","content":"Paris"} -->
	<div class="wp-block-wpfn-slot">Paris</div>
	<!-- /wp:wpfn/slot -->
	
	<!-- wp:wpfn/slot {"role":"explanation","content":"Paris is the capital and largest city of France."} -->
	<div class="wp-block-wpfn-slot">Paris is the capital and largest city of France.</div>
	<!-- /wp:wpfn/slot -->
</div>
<!-- /wp:wpfn/card-flip -->';

		$post_id = $this->factory->post->create(
			array(
				'post_content' => $post_content,
			)
		);

		$this->assertGreaterThan(0, $post_id, 'Post should be created');

		$table_name = $wpdb->prefix . 'wpfn_cards';

		// Verify card exists before deletion
		$card_before = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE block_id = %s",
				$block_id
			)
		);

		$this->assertNotNull($card_before, 'Card should exist before post deletion');
		$this->assertEquals('active', $card_before->status, 'Card should be active before deletion');

		// Delete the post - this should trigger the on_delete_post hook
		$deleted = wp_delete_post($post_id, true);

		$this->assertInstanceOf(WP_Post::class, $deleted, 'Post should be deleted successfully');

		// Verify card was marked as orphan (not deleted)
		$card_after = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE block_id = %s",
				$block_id
			)
		);

		$this->assertNotNull($card_after, 'Card should still exist after post deletion');
		$this->assertEquals('orphan', $card_after->status, 'Card should be marked as orphan after post deletion');
	}
}