<?php

namespace WPFlashNotes\DataBase\Schema;

use WPFlashNotes\BaseClasses\BaseTable;

defined('ABSPATH') || exit;

final class CardsTable extends BaseTable
{
    /**
     * Table slug without prefix (full name will be {$wpdb->prefix}wpfn_cards).
     *
     * @var string|null
     */
    protected ?string $slug = 'wpfn_cards';

    /**
     * Returns the CREATE TABLE statement for dbDelta().
     *
     * @return string
     */
    protected function get_schema(): string
    {
        $table  = $this->get_table_name();
        $engine = 'ENGINE=InnoDB ' . $this->get_charset_collate();

        return "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                block_id VARCHAR(128) DEFAULT NULL,
                front TEXT NOT NULL,
                back TEXT NOT NULL,
                explanation TEXT DEFAULT NULL,
                card_type ENUM('flip','fill_in_blank','multiple_choice') DEFAULT 'flip',
                last_seen DATETIME DEFAULT NULL,
                correct_count INT DEFAULT 0,
                incorrect_count INT DEFAULT 0,
                is_mastered TINYINT(1) DEFAULT 0,
                deleted_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_block_id (block_id)
            ) {$engine};";
    }
}
