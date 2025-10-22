<?php
namespace WPFlashNotes\Helpers;

class BlockFormatter {

	private static array $mock_blocks = [];

	public static function set_mock_blocks( array $blocks ): void {
		self::$mock_blocks = $blocks;
	}

	public static function from_post_content( string $content ): array {
		return self::$mock_blocks;
	}
}
