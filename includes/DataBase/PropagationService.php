<?php 

namespace WPFlashNotes\DataBase;

use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\CardSetRelationsRepository;
use WPFlashNotes\Repos\NoteSetRelationsRepository;
use WPFlashNotes\Repos\ObjectUsageRepository;


class PropagationService {
    private CardsRepository $cards;
    private NotesRepository $notes;
    private SetsRepository $sets;
    private CardSetRelationsRepository $card_set;
    private NoteSetRelationsRepository $note_set;
    private ObjectUsageRepository $usage;

    public function __construct(
        CardsRepository $cards, 
        NotesRepository $notes, 
        SetsRepository $sets,
        CardSetRelationsRepository $card_set_rel,
        NoteSetRelationsRepository $note_set_rel,
        ObjectUsageRepository $usage
    ) {
        $this->cards = $cards;
        $this->notes = $notes;
        $this->sets = $sets;
        $this->card_set = $card_set_rel;
        $this->note_set = $note_set_rel;
        $this->usage = $usage;
    }

    public function propagate( int $post_id, array $blocks ): void {
        $post = get_post($post_id);
        $set_row = null;
        $set_id = null;
        
        if($post->post_type === 'studyset') {
            $set_row = $this->sets->get_by_set_post_id( $post_id );

            if( empty($set_row) ) {
                $set_id = $this->sets->upsert_by_set_post_id(array(
                    'title' => ! empty( $post->post_title ) ? $post->post_title : 'Untitled',
                    'origin_post_id' => $post_id,
                    'set_post_id' => $post_id,
                    'user_id' => $post->post_author,
                ));
            } else {
                $set_id = $set_row['id'];
            }
        }
        
        foreach ( $blocks as $block ) {
            if ( $block['object_type'] === 'card' ) {
                $card_id = $this->cards->upsert_from_block($block);
                
                if ( $post->post_type === 'studyset' && absint($set_id) > 0) {
                    $this->card_set->attach( $card_id, (int) $set_id );
                }

                $this->usage->attach( 'card', $card_id, $post_id, $block['block_id'] );

                continue;
            }

            if ( $block['object_type'] === 'note' ) {
                $note_id = $this->notes->upsert_from_block($block);
                
                if ( $post->post_type === 'studyset' && absint($set_id) > 0) {
                    $this->note_set->attach( $note_id, (int) $set_id );
                }

                $this->usage->attach( 'note', $note_id, $post_id, $block['block_id'] );
                continue;
            }

            if ( $block['object_type'] === 'inserter' ) {
                if ( ! empty( $block['attrs']['id'] ) && ! empty($block['attrs']['card_block_id']) ) {
                    $this->usage->attach( 'inserter', (int) $block['attrs']['id'], $post_id, $block['attrs']['card_block_id'] );
                }

                continue;
            }
        }
    }

    public function get_studyset_for_origin_post( int $origin_post_id ): ?int {
        return $this->sets->get_by_post_id( $origin_post_id );
    }

    public function register_post_set_relation(array $data) {
        return $this->sets->upsert_by_set_post_id($data);
    }

    public function tag_as_orphan($block) {
        if ( $block['object_type'] === 'inserter' ) {
			return;
		}

		$related_in_db = $this->usage->get_relationships_by_column( 'block_id', $block['block_id'] );
		$repo          = $block['object_type'] === 'card' ? $this->cards : $this->notes;
		$flashnote     = $repo->get_by_column( 'block_id', $block['block_id'], 1 );

		if ( count( $related_in_db ) === 0 && ! empty( $flashnote ) ) {
			$repo->update( $flashnote['id'], array( 'status' => 'orphan' ) );
		}
    }

    public function tag_as_active( array $block ): void {
        if ( $block['object_type'] !== 'inserter' ) {
            return;
        }

        // Object type must come from inserter attributes
        $type      = $block['attrs']['object_type'] ?? null;
        $object_id = $block['attrs']['id'] ?? null;

        if ( ! $type || ! $object_id ) {
            return;
        }

        $repo    = $type === 'card' ? $this->cards : $this->notes;
        $current = $repo->read( $object_id );

        if ( $current && $current['status'] === 'orphan' ) {
            $repo->update( $object_id, [ 'status' => 'active' ] );
        }
    }
}