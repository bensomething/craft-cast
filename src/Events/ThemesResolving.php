<?php

declare(strict_types=1);

namespace bensomething\cast\Events;

use bensomething\cast\Theme;

/**
 * Lets other plugins and modules add, replace or remove the themes Cast offers.
 *
 * Fired once per request, the first time Cast needs its themes. `$themes` already holds
 * the bundled themes and those from the themes folder, keyed by handle.
 *
 * ```php
 * Event::listen(function (ThemesResolving $event) {
 *     $event->add(new Theme(
 *         handle: 'midnight',
 *         name: 'Midnight',
 *         modes: ['dark'],
 *         url: asset('vendor/acme/cast-midnight/midnight.css'),
 *     ));
 * });
 * ```
 *
 * A theme that isn't valid (see {@see Theme::isValid()}) is dropped.
 */
final class ThemesResolving
{
    /** @param  array<string, Theme>  $themes */
    public function __construct(
        public array $themes = [],
    ) {}

    /** Adds a theme, replacing any with the same handle. */
    public function add(Theme $theme): void
    {
        $this->themes[$theme->handle] = $theme;
    }
}
