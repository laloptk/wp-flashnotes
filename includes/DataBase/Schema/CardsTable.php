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
                question LONGTEXT NOT NULL,
                answers_json LONGTEXT DEFAULT NULL,
                user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                right_answers_json LONGTEXT DEFAULT NULL,
                explanation LONGTEXT DEFAULT NULL,
                card_type ENUM('flip','true_false','multiple_choice','multiple_select','fill_in_blank') NOT NULL DEFAULT 'flip',
                last_seen DATETIME DEFAULT NULL,
                next_due DATETIME DEFAULT NULL,
                correct_count INT UNSIGNED NOT NULL DEFAULT 0,
                incorrect_count INT UNSIGNED NOT NULL DEFAULT 0,
                streak SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                ease_factor DECIMAL(4,2) NOT NULL DEFAULT 2.50,
                is_mastered TINYINT(1) NOT NULL DEFAULT 0,
                deleted_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_block_id (block_id),
                KEY idx_user_id (user_id),
                KEY idx_card_type (card_type),
                KEY idx_due (next_due),
                KEY idx_seen (last_seen),
                KEY idx_mastered (is_mastered)
            ) {$engine};";
    }
}
