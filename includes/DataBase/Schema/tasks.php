<?php

function wpfn_schema_tasks(): array {
    global $wpdb;

    return [

        // 1) dbDelta: cards
        [
            'slug' => 'wpfn_cards',
            'deps' => [],
            'run'  => function () use ($wpdb) {
                (new CardsDbDelta())->install_table();
                update_option($wpdb->prefix.'wpfn_cards_version', '1.0.0');
            },
        ],

        // 2) dbDelta: notes
        [
            'slug' => 'wpfn_notes',
            'deps' => [],
            'run'  => function () use ($wpdb) {
                (new NotesDbDelta())->install_table();
                update_option($wpdb->prefix.'wpfn_notes_version', '1.0.0');
            },
        ],

        // 3) TableBuilder: sets (FK a core)
        [
            'slug' => 'wpfn_sets',
            'deps' => [],
            'run'  => function () use ($wpdb) {
                (new TableBuilder('wpfn_sets'))
                    ->set_version('1.0.0')
                    ->add_column('id',          'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT')
                    ->add_column('title',       'VARCHAR(255) NOT NULL')
                    ->add_column('post_id',     'BIGINT UNSIGNED DEFAULT NULL')
                    ->add_column('set_post_id', 'BIGINT UNSIGNED NOT NULL')
                    ->add_column('user_id',     'BIGINT UNSIGNED NOT NULL')
                    ->add_column('created_at',  'DATETIME DEFAULT CURRENT_TIMESTAMP')
                    ->add_column('updated_at',  'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')
                    ->add_primary('id')
                    ->add_unique('uq_set_post_id', ['set_post_id'])
                    ->add_index('idx_post_id', ['post_id'])
                    ->add_foreign_key('set_post_id', $wpdb->prefix.'posts', 'ID', 'CASCADE', 'RESTRICT')
                    ->add_foreign_key('user_id',     $wpdb->prefix.'users', 'ID', 'CASCADE', 'RESTRICT')
                    ->createOrUpdate();
            },
        ],

        // 4) TableBuilder: card_set_relations
        [
            'slug' => 'wpfn_card_set_relations',
            'deps' => ['wpfn_cards','wpfn_sets'],
            'run'  => function () use ($wpdb) {
                (new TableBuilder('wpfn_card_set_relations'))
                    ->set_version('1.0.0')
                    ->add_column('card_id', 'BIGINT UNSIGNED NOT NULL')
                    ->add_column('set_id',  'BIGINT UNSIGNED NOT NULL')
                    ->add_primary_compound(['card_id','set_id'])
                    ->add_foreign_key('card_id', $wpdb->prefix.'wpfn_cards', 'id', 'CASCADE', 'RESTRICT')
                    ->add_foreign_key('set_id',  $wpdb->prefix.'wpfn_sets',  'id', 'CASCADE', 'RESTRICT')
                    ->createOrUpdate();
            },
        ],

        // 5) TableBuilder: note_set_relations
        [
            'slug' => 'wpfn_note_set_relations',
            'deps' => ['wpfn_notes','wpfn_sets'],
            'run'  => function () use ($wpdb) {
                (new TableBuilder('wpfn_note_set_relations'))
                    ->set_version('1.0.0')
                    ->add_column('note_id', 'BIGINT UNSIGNED NOT NULL')
                    ->add_column('set_id',  'BIGINT UNSIGNED NOT NULL')
                    ->add_primary_compound(['note_id','set_id'])
                    ->add_foreign_key('note_id', $wpdb->prefix.'wpfn_notes', 'id', 'CASCADE', 'RESTRICT')
                    ->add_foreign_key('set_id',  $wpdb->prefix.'wpfn_sets',  'id', 'CASCADE', 'RESTRICT')
                    ->createOrUpdate();
            },
        ],

        // 6) TableBuilder: taxonomy_relations (FK a core)
        [
            'slug' => 'wpfn_taxonomy_relations',
            'deps' => [],
            'run'  => function () use ($wpdb) {
                (new TableBuilder('wpfn_taxonomy_relations'))
                    ->set_version('1.0.0')
                    ->add_column('object_type',      "ENUM('set','card','note') NOT NULL")
                    ->add_column('object_id',        'BIGINT UNSIGNED NOT NULL')
                    ->add_column('term_taxonomy_id', 'BIGINT UNSIGNED NOT NULL')
                    ->add_primary_compound(['object_type','object_id','term_taxonomy_id'])
                    ->add_foreign_key('term_taxonomy_id', $wpdb->prefix.'term_taxonomy', 'term_taxonomy_id', 'CASCADE', 'RESTRICT')
                    ->createOrUpdate();
            },
        ],

        // 7) TableBuilder: object_usage (FK a core)
        [
            'slug' => 'wpfn_object_usage',
            'deps' => [],
            'run'  => function () use ($wpdb) {
                (new TableBuilder('wpfn_object_usage'))
                    ->set_version('1.0.0')
                    ->add_column('object_type', "ENUM('card','note') NOT NULL")
                    ->add_column('object_id',   'BIGINT UNSIGNED NOT NULL')
                    ->add_column('post_id',     'BIGINT UNSIGNED NOT NULL')
                    ->add_column('block_id',    'VARCHAR(128) NOT NULL')
                    ->add_primary_compound(['object_type','object_id','post_id','block_id'])
                    ->add_foreign_key('post_id', $wpdb->prefix.'posts', 'ID', 'CASCADE', 'RESTRICT')
                    ->createOrUpdate();
            },
        ],
    ];
}