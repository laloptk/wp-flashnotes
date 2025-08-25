<?php
namespace WPFlashNotes\Helpers;

defined('ABSPATH') || exit;

class BlockParser
{
    /**
     * Entry point: parse post_content and return only normalized FlashNotes blocks.
     *
     * @param string $content Raw post_content
     * @return array Normalized FlashNotes blocks
     */
    public static function from_post_content(string $content): array
    {
        $blocks = \parse_blocks($content);
        return self::extract_flashnotes_blocks($blocks);
    }

    /**
     * Extract only wpflashnotes blocks from parsed block tree.
     *
     * @param array $blocks Output of parse_blocks().
     * @return array Normalized FlashNotes blocks
     */
    private static function extract_flashnotes_blocks(array $blocks): array
    {
        $results = [];

        foreach ($blocks as $block) {
            if (isset($block['blockName']) && str_starts_with($block['blockName'], 'wpflashnotes/')) {
                $results[] = self::normalize_block($block);
            }

            // Recurse into children
            if (!empty($block['innerBlocks'])) {
                $childResults = self::extract_flashnotes_blocks($block['innerBlocks']);
                $results = array_merge($results, $childResults);
            }
        }

        return $results;
    }

    /**
     * Normalize a block so we can rely on consistent keys.
     *
     * @param array $block Raw block array from parse_blocks().
     * @return array Normalized block.
     */
    private static function normalize_block(array $block): array
    {
        return [
            'blockName'   => $block['blockName'] ?? '',
            'attrs'       => $block['attrs'] ?? [],
            'block_id'    => $block['attrs']['block_id'] ?? null,
            'type'        => $block['attrs']['type'] ?? null, // e.g. card subtype
            'innerBlocks' => $block['innerBlocks'] ?? [],
        ];
    }
}
