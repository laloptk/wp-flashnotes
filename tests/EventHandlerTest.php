<?php
/**
 * EventHandler tests
 *
 * @group wpflashnotes
 */

use WPFlashNotes\Events\EventHandler;
use WPFlashNotes\Managers\SyncManager;

class EventHandlerTest extends WP_UnitTestCase {

	public function test_on_post_save_for_studyset_calls_sync_only() {
		$sync = $this->createMock( SyncManager::class );
		$sync->expects( $this->once() )->method( 'sync_studyset' );

		$handler = new EventHandler( $sync );

		$studyset = self::factory()->post->create_and_get( [ 'post_type' => 'studyset' ] );
		$handler->on_post_save( $studyset->ID, $studyset, true );
	}

	public function test_on_post_save_for_normal_post_creates_set_and_syncs() {
		$sync = $this->createMock( SyncManager::class );
		$sync->expects( $this->once() )->method( 'ensure_set_for_post' )->willReturn( 999 );
		$sync->expects( $this->once() )->method( 'sync_studyset' );

		$handler = new EventHandler( $sync );

		$post = self::factory()->post->create_and_get();
		$handler->on_post_save( $post->ID, $post, true );
	}
}
