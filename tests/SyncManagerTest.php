<?php
/**
 * SyncManager tests
 *
 * @group wpflashnotes
 */

use WPFlashNotes\Managers\SyncManager;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\NoteSetRelationsRepository;
use WPFlashNotes\Repos\CardSetRelationsRepository;
use WPFlashNotes\Repos\ObjectUsageRepository;
use WPFlashNotes\Helpers\BlockParser;

class SyncManagerTest extends WP_UnitTestCase {

	protected function make_manager( $overrides = [] ) {
		$defaults = [
			'notes'         => $this->createMock( NotesRepository::class ),
			'cards'         => $this->createMock( CardsRepository::class ),
			'sets'          => $this->createMock( SetsRepository::class ),
			'noteRelations' => $this->createMock( NoteSetRelationsRepository::class ),
			'cardRelations' => $this->createMock( CardSetRelationsRepository::class ),
			'usage'         => $this->createMock( ObjectUsageRepository::class ),
		];
		$repos = array_merge( $defaults, $overrides );

		return new SyncManager(
			$repos['notes'],
			$repos['cards'],
			$repos['sets'],
			$repos['noteRelations'],
			$repos['cardRelations'],
			$repos['usage']
		);
	}

	public function test_ensure_set_creates_new_studyset() {
		$setsRepo = $this->createMock( SetsRepository::class );
		$setsRepo->method( 'get_by_post_id' )->willReturn( [] );
		$setsRepo->expects( $this->once() )
			->method( 'upsert_by_set_post_id' )
			->with( $this->arrayHasKey( 'set_post_id' ) );

		$manager = $this->make_manager( [ 'sets' => $setsRepo ] );

		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        $post_id = self::factory()->post->create( [
            'post_title'  => 'Origin Post',
            'post_author' => $user_id,
            'post_status' => 'publish',
        ] );
        
		$set_post_id = $manager->ensure_set_for_post( $post_id, 'Some content' );

		$this->assertIsInt( $set_post_id );
		$this->assertNotEmpty( get_post( $set_post_id ) );
	}

	public function test_sync_studyset_calls_repositories() {
		$cardsRepo = $this->createMock( CardsRepository::class );
		$cardsRepo->expects( $this->once() )
			->method( 'upsert_from_block' )
			->willReturn( 11 );

		$notesRepo = $this->createMock( NotesRepository::class );
		$notesRepo->expects( $this->once() )
			->method( 'upsert_from_block' )
			->willReturn( 22 );

		$setsRepo = $this->createMock( SetsRepository::class );
		$setsRepo->method( 'get_by_set_post_id' )
			->willReturn( [ 'id' => 99 ] );

		$cardRelations = $this->createMock( CardSetRelationsRepository::class );
		$cardRelations->expects( $this->once() )
			->method( 'attach' )
			->with( 11, 99 );

		$noteRelations = $this->createMock( NoteSetRelationsRepository::class );
		$noteRelations->expects( $this->once() )
			->method( 'attach' )
			->with( 22, 99 );

		$usageRepo = $this->createMock( ObjectUsageRepository::class );
		$usageRepo->expects( $this->exactly( 2 ) )
			->method( 'attach' );

		$manager = $this->make_manager( [
			'cards'         => $cardsRepo,
			'notes'         => $notesRepo,
			'sets'          => $setsRepo,
			'cardRelations' => $cardRelations,
			'noteRelations' => $noteRelations,
			'usage'         => $usageRepo,
		] );

		// Fake block parser
		$blocks = [
			[ 'blockName' => 'wpfn/card', 'block_id' => 'c1', 'attrs' => [ 'question' => 'Q' ] ],
			[ 'blockName' => 'wpfn/note', 'block_id' => 'n1', 'attrs' => [ 'title' => 'T' ] ],
		];
		BlockParser::set_mock_blocks( $blocks );

		$manager->sync_studyset( 555, 'dummy' );
	}
}
