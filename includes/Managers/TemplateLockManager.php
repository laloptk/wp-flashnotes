<?php
namespace WPFlashNotes\Managers;

use WPFlashNotes\Repos\SetsRepository;

/**
 * TemplateLockManager
 *
 * Handles locking the block template and restricting allowed blocks
 * for studysets created from origin posts.
 */
class TemplateLockManager {

    protected SetsRepository $sets;

    public function __construct( SetsRepository $sets ) {
        $this->sets = $sets;
    }

    public function register_hooks(): void {
        add_filter( 'block_editor_settings_all', [ $this, 'apply_template_lock' ], 10, 2 );
        add_filter( 'allowed_block_types_all', [ $this, 'restrict_allowed_blocks' ], 10, 2 );
    }

    public function apply_template_lock( array $settings, $context ): array {
        if ( $context->post && $context->post->post_type === 'studyset' ) {
            $set_row = $this->sets->get_by_set_post_id( (int) $context->post->ID );

            if ( $set_row && (int) $set_row['post_id'] !== (int) $set_row['set_post_id'] ) {
                $settings['templateLock'] = 'all';
            } else {
                $settings['templateLock'] = false;
            }
        }
        return $settings;
    }

    public function restrict_allowed_blocks( $allowed_blocks, $editor_context ) {
        if ( $editor_context->post && $editor_context->post->post_type === 'studyset' ) {
            return [ 'wpfn/note', 'wpfn/card', 'wpfn/inserter' ];
        }
        return $allowed_blocks;
    }
}
