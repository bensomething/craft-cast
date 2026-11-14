<?php

declare(strict_types=1);

namespace bensomething\cast\Http\Controllers;

use bensomething\cast\ThemeGenerator;
use bensomething\cast\Themes;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a themes-folder stylesheet, or a custom theme, generated from the settings.
 *
 * The folder needn't be in the web root, and
 * shouldn't be: there, a theme would be reachable at two URLs, only one of them
 * carrying the hash that busts caches when it's edited.
 */
final readonly class ThemeStylesheetController
{
    public function __construct(private Themes $themes) {}

    /** The URL carries a hash of what the stylesheet is made from, so a change gets a new URL. */
    private const array HEADERS = [
        'Content-Type' => 'text/css; charset=utf-8',
        'Cache-Control' => 'private, max-age=31536000, immutable',
    ];

    public function show(string $handle): Response
    {
        if (($css = $this->themes->customCss($handle)) !== null) {
            return response($css, 200, self::HEADERS);
        }

        $path = $this->themes->all()[$handle]->path ?? null;

        // Only files discovery found: a handle never reaches the filesystem directly.
        abort_if($path === null || ! is_file($path), 404);

        return response()->file($path, self::HEADERS);
    }

    /**
     * A theme generated from a colour and tint not yet saved, for the settings screen's
     * Preview. Stamped with {@see Themes::PREVIEW}, so it can't clash with a real theme.
     */
    public function preview(Request $request): Response
    {
        $color = strtolower('#'.ltrim((string) $request->query('color', ''), '#'));
        $tint = (string) $request->query('tint', 'subtle');

        abort_unless(preg_match('/^#[0-9a-f]{6}$/', $color) && isset(ThemeGenerator::TINTS[$tint]), 404);

        return response(ThemeGenerator::css(Themes::PREVIEW, 'Preview', $color, $tint), 200, self::HEADERS);
    }
}
