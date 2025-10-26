<?php
namespace WPFlashNotes\Helpers;

defined('ABSPATH') || exit;

/**
 * BlocksMerger
 *
 * Rules:
 * - Non-flashnotes are ignored (filtered elsewhere).
 * - Deleted in origin → keep in studyset.
 * - Deleted in studyset → reinsert from origin.
 * - Updated in origin → replace in studyset.
 * - New in origin → insert immediately after previous origin block.
 *
 * Additionally:
 * - Adds meta['source'] flag to each block: 'origin' or 'studyset'.
 */
class BlocksMerger {

    /**
     * Merge origin (transformed) flashnotes block tree with studyset block tree.
     * Adds meta.source flag for transformation context.
     *
     * @param array $origin_blocks   Transformed origin flashnotes blocks.
     * @param array $studyset_blocks Existing studyset flashnotes blocks.
     * @return array Merged flashnotes block tree ready for serialization.
     */
    public static function merge(array $origin_blocks, array $studyset_blocks): array {
        $result = [];

        // Map origin blocks by canonical origin ID
        $origin_index = [];
        foreach ($origin_blocks as $i => $block) {
            $id = self::get_origin_id($block);
            if ($id) {
                // Tag origin blocks early
                $block['meta']['source'] = 'origin';
                $origin_index[$id] = [
                    'index' => $i,
                    'block' => $block,
                ];
            }
        }

        $origin_count = count($origin_blocks);
        $seen_ids     = [];

        foreach ($studyset_blocks as $studyset_block) {
            $s_id = self::get_origin_id($studyset_block);

            // Tag everything from the studyset by default
            $studyset_block['meta']['source'] = 'studyset';

            if ($s_id && isset($seen_ids[$s_id])) {
                continue;
            }

            // Case 1: Block exists in origin → replace with origin version
            if ($s_id && isset($origin_index[$s_id])) {
                $origin_pos   = $origin_index[$s_id]['index'];
                $origin_block = $origin_index[$s_id]['block'];
                $result[]     = $origin_block;
                $seen_ids[$s_id] = true;

                // Inline insert next origin block if missing from studyset
                $next_pos = $origin_pos + 1;
                if ($next_pos < $origin_count) {
                    $next_block = $origin_blocks[$next_pos];
                    $next_id    = self::get_origin_id($next_block);
                    if ($next_id && !isset($seen_ids[$next_id])) {
                        $next_block['meta']['source'] = 'origin';
                        $result[] = $next_block;
                        $seen_ids[$next_id] = true;
                    }
                }

            // Case 2: Exists only in studyset → keep as-is
            } else {
                $result[] = $studyset_block;
                if ($s_id) {
                    $seen_ids[$s_id] = true;
                }
            }
        }

        // Append any origin blocks still unseen
        foreach ($origin_index as $id => $data) {
            if (!isset($seen_ids[$id])) {
                $block = $data['block'];
                $block['meta']['source'] = 'origin';
                $result[] = $block;
            }
        }

        return $result;
    }

    /**
     * Resolve the canonical origin ID for a block.
     * Handles cards, notes, and any inserter subtype.
     *
     * @param array $block
     * @return string|null
     */
    private static function get_origin_id(array $block): ?string {
        $attrs = $block['attrs'] ?? [];

        // Prefer explicit origin references if present (for inserters)
        if (!empty($attrs['card_block_id'])) {
            return $attrs['card_block_id'];
        }
        if (!empty($attrs['note_block_id'])) {
            return $attrs['note_block_id'];
        }

        // Fall back to the block’s own ID
        return $attrs['block_id'] ?? null;
    }
}
