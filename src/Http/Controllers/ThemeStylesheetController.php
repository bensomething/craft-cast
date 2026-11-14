<?php

declare(strict_types=1);

namespace bensomething\cast\Http\Controllers;

use bensomething\cast\Themes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a themes-folder stylesheet. The folder needn't be in the web root, and
 * shouldn't be: there, a theme would be reachable at two URLs, only one of them
 * carrying the hash that busts caches when it's edited.
 */
final readonly class ThemeStylesheetController
{
    public function __construct(private Themes $themes) {}

    public function show(string $handle): BinaryFileResponse
    {
        $path = $this->themes->all()[$handle]->path ?? null;

        // Only files discovery found: a handle never reaches the filesystem directly.
        abort_if($path === null || ! is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'text/css; charset=utf-8',
            // The URL carries a hash of the file, so a changed file gets a new URL.
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
