<?php

namespace WPFlashNotes\CPT;

use WPFlashNotes\BaseClasses\BaseCPT;

final class StudySetCPT extends BaseCPT {
    
    protected function set_type(): string {
        return 'studyset';
    }

    protected function set_singular(): string {
        return 'Study Set';
    }

    protected function set_plural(): string {
        return 'Study Sets';
    }

    /** Ajustes específicos para Study Set */
    protected function args(): array {
        return [
            'menu_icon'           => 'dashicons-index-card',
            'supports'            => ['title', 'editor', 'revisions'],
            'show_in_rest'        => true,
            'public'              => true,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'rewrite'             => false,
            'rest_base'           => 'studyset',
        ];
    }
}
