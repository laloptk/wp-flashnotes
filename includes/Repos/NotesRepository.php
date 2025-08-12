<?php

namespace WPFlashNotes\Repos;

defined('ABSPATH') || exit;

use WPFlashNotes\BaseClasses\BaseRepository;

/**
 * NotesRepository
 *
 * CRUD for the wpfn_notes table.
 * - Enforces non-empty title (when provided).
 * - Supports partial updates safely.
 */
final class NotesRepository extends BaseRepository
{
    /**
     * Fully-qualified table name.
     */
    protected function get_table_name(): string
    {
        return $this->wpdb->prefix . 'wpfn_notes';
    }

    /**
     * Sanitize and validate a data payload for insert/update.
     * Only fields present in $data are processed (safe for partial updates).
     *
     * @param array $data
     * @return array Sanitized subset of $data, ready for wpdb insert/update.
     * @throws \Exception On invalid field values.
     */
    protected function sanitize_data(array $data): array
    {
        $sanitized_data = [];

        foreach ($data as $field_name => $field_value) {
            switch ($field_name) {
                case 'user_id':
                    $uid = (int) $field_value;
                    if ($uid <= 0) {
                        $uid = (int) get_current_user_id();
                    }
                    if ($uid <= 0) {
                        throw new \Exception('user_id is required.');
                    }
                    $sanitized_data['user_id'] = $uid;
                    break;
                case 'title':
                    $title = trim(wp_strip_all_tags((string) $field_value));
                    if ($title === '') {
                        throw new \Exception('Title cannot be empty.');
                    }
                    // Fit VARCHAR(255)
                    $sanitized_data['title'] = mb_substr($title, 0, 255);
                    break;

                case 'block_id':
                    $sanitized_data['block_id'] = $field_value === null
                        ? null
                        : sanitize_text_field((string) $field_value);
                    break;

                case 'content':
                    $sanitized_data['content'] = wp_kses_post((string) $field_value);
                    break;

                case 'deleted_at':
                case 'created_at':
                case 'updated_at':
                    $sanitized_data[$field_name] = self::normalize_datetime($field_value);
                    break;

                default:
                    // Ignore unknown fields silently.
                    break;
            }
        }

        return $sanitized_data;
    }

    /**
     * wpdb format map. All strings by default; override if needed.
     *
     * @return array<string,string>
     */
    protected function fieldFormats(): array
    {
        return [
            'id'         => '%d', // not used on insert, fine to list
            'user_id'    => '%d',
            'title'      => '%s',
            'content'    => '%s',   // or content_html/content_raw if that’s your column
            'deleted_at' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
        ];
    }

    /**
     * Normalize a datetime-ish input (timestamp/int/string) to "Y-m-d H:i:s" GMT or NULL.
     *
     * @param mixed $value
     * @return string|null
     */
    private static function normalize_datetime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if (!$timestamp) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
