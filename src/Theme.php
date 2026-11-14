<?php

declare(strict_types=1);

namespace bensomething\cast;

/**
 * An appearance a user can pick for one or both of Craft's modes.
 *
 * A theme is a stylesheet that overrides Craft 6's colour tokens while Craft's mode
 * and the user's choice for that mode both match:
 *
 * ```css
 * html[data-theme='dark'][data-cast-dark='midnight'] { --c-surface-default: …; }
 * html:not([data-theme='dark'])[data-cast-light='midnight'] { … }
 * ```
 *
 * Cast stamps `data-cast-light` and `data-cast-dark` on `<html>` from the user's
 * choices; Craft stamps `data-theme`. Register one with {@see Events\ThemesResolving},
 * or drop a stylesheet in the themes folder.
 */
final readonly class Theme
{
    /** What a handle may look like: it's an HTML attribute value and a URL segment. */
    public const string HANDLE_PATTERN = '/^[a-z][a-z0-9-]*$/';

    /**
     * @param  string  $handle  Unique, lower-case kebab-case. Stamped as the attribute value.
     * @param  string  $name  Shown in the pickers.
     * @param  list<string>  $modes  Which of Craft's modes it dresses: `light`, `dark` or both.
     * @param  string|null  $url  The stylesheet's URL, for a theme that serves its own.
     * @param  string|null  $path  The stylesheet's file, for a theme Cast serves. Set this or `$url`.
     * @param  string|null  $description  An optional line about it.
     */
    public function __construct(
        public string $handle,
        public string $name,
        public array $modes,
        public ?string $url = null,
        public ?string $path = null,
        public ?string $description = null,
    ) {}

    public function dresses(string $mode): bool
    {
        return in_array($mode, $this->modes, true);
    }

    /**
     * Whether it can be offered: a usable handle, at least one real mode, and nothing
     * else in its modes.
     */
    public function isValid(): bool
    {
        return preg_match(self::HANDLE_PATTERN, $this->handle) === 1
            && $this->handle !== Settings::THEME_INHERIT
            && $this->name !== ''
            && $this->modes !== []
            && array_diff($this->modes, [Themes::SCHEME_LIGHT, Themes::SCHEME_DARK]) === [];
    }
}
