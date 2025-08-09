<?php

namespace WPFlashNotes\Repositories;

final class CardsRepository extends BaseRepository
{
    protected function get_table_name(): string
    {
        return $this->wpdb->prefix . 'wpfn_cards';
    }

    protected function sanitize_data(array $data): array
    {
        $out = [];

        if (isset($data['block_id'])) {
            $out['block_id'] = sanitize_text_field($data['block_id']);
        }
        if (isset($data['front'])) {
            $out['front'] = wp_kses_post($data['front']);
        }
        if (isset($data['back'])) {
            $out['back'] = wp_kses_post($data['back']);
        }
        if (isset($data['explanation'])) {
            $out['explanation'] = wp_kses_post($data['explanation']);
        }
        if (isset($data['card_type'])) {
            $allowed = ['flip','fill_in_blank','multiple_choice'];
            $type = sanitize_key($data['card_type']);
            if (!in_array($type, $allowed, true)) {
                throw new \Exception('Invalid card_type.');
            }
            $out['card_type'] = $type;
        }
        foreach (['correct_count','incorrect_count'] as $count) {
            if (isset($data[$count])) {
                $v = (int) $data[$count];
                if ($v < 0) { throw new \Exception("{$count} must be >= 0."); }
                $out[$count] = $v;
            }
        }
        if (isset($data['is_mastered'])) {
            $out['is_mastered'] = (int) !!$data['is_mastered'];
        }
        if (isset($data['last_seen'])) {
            $out['last_seen'] = gmdate('Y-m-d H:i:s', strtotime($data['last_seen']));
        }

        // Timestamps (optional: let DB defaults handle create, always set update)
        if (isset($data['created_at'])) {
            $out['created_at'] = gmdate('Y-m-d H:i:s', strtotime($data['created_at']));
        }
        if (isset($data['updated_at'])) {
            $out['updated_at'] = gmdate('Y-m-d H:i:s', strtotime($data['updated_at']));
        }

        return $out;
    }

    protected function fieldFormats(): array
    {
        return [
            'correct_count'  => '%d',
            'incorrect_count'=> '%d',
            'is_mastered'    => '%d',
        ];
    }
}
