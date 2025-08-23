<?php

namespace WPFlashNotes\Helpers;

/**
 * Absolute root for view files.
 * Maps slug 'organisms/card' -> <plugin>/includes/views/organisms/card.php
 */
function views_root(): string
{
    return rtrim(WPFN_PLUGIN_DIR, "/\\") . '/includes/views';
}

/**
 * Resolve a slug (e.g., 'organisms/card') to a full PHP file path.
 * Throws on invalid slug or missing file.
 */
function part_path(string $slug): string
{
    // allow only letters, numbers, underscore, hyphen, and slashes
    $clean = preg_replace('#[^a-zA-Z0-9/_\-]#', '', trim($slug)) ?? '';
    if ($clean === '' || str_contains($clean, '..')) {
        throw new \RuntimeException("WPFlashNotes: invalid view slug '{$slug}'.");
    }

    $path = views_root() . '/' . $clean . '.php';
    if (!is_readable($path)) {
        throw new \RuntimeException("WPFlashNotes: view not found for slug '{$slug}' at {$path}.");
    }

    return $path;
}

/**
 * Render a PHP view partial and return its HTML.
 * The $ctx array is available inside the partial as $ctx.
 * Throws if the slug/path is invalid or missing.
 */
function render_part(string $slug, array $ctx = []): string
{
    $path = part_path($slug); // may throw

    // isolate scope so the partial sees only $__path and $ctx (plus globals)
    $renderer = static function (string $__path, array $ctx): string {
        ob_start();
        include $__path; // partial can use $ctx
        return (string) ob_get_clean();
    };

    return $renderer($path, $ctx);
}

/** Echoing convenience wrapper (also throws on errors). */
function echo_part(string $slug, array $ctx = []): void
{
    echo render_part($slug, $ctx);
}
