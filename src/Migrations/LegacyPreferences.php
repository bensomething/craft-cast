<?php

declare(strict_types=1);

namespace bensomething\cast\Migrations;

use bensomething\cast\Themes;

/**
 * Turns a user's Cast 1.x preferences into what they mean on 2.x.
 *
 * 1.x had one main choice, `castTheme`, which also decided light or dark; on Craft 6
 * Craft's own Theme preference decides that. So a 1.x choice becomes Craft's Theme
 * plus Cast's appearance for that mode, and either high contrast theme becomes the
 * contrast switch. Anything the user has already set on 2.x, or in Craft's own Theme,
 * is left alone, and nothing is deleted.
 */
final class LegacyPreferences
{
    /**
     * What each 1.x main choice becomes: Craft's Theme, and the 2.x appearance for that
     * mode (null to leave it), and whether it turns contrast on. 1.x's `auto` and
     * `inherit` already mean what 2.x's defaults do, so they aren't here; nor are
     * themes-folder handles, whose 1.x stylesheets don't work on Craft 6.
     */
    private const array MAIN = [
        '' => ['light', null, false],
        'dark' => ['dark', '', false],
        'dim' => ['dark', 'dim', false],
        'stone' => ['light', 'stone', false],
        'stone-dark' => ['dark', 'stone', false],
        'high-contrast' => ['light', null, true],
        'high-contrast-dark' => ['dark', null, true],
    ];

    private const array HIGH_CONTRAST = ['high-contrast', 'high-contrast-dark'];

    /**
     * @param  array<string, mixed>  $prefs  A user's stored preferences.
     * @return array<string, mixed> The same, with 1.x's choices carried over.
     */
    public static function carryOver(array $prefs): array
    {
        $main = $prefs['castTheme'] ?? null;
        $contrast = in_array($main, self::HIGH_CONTRAST, true)
            || in_array($prefs[Themes::PREF_LIGHT] ?? null, self::HIGH_CONTRAST, true)
            || in_array($prefs[Themes::PREF_DARK] ?? null, self::HIGH_CONTRAST, true);

        if ($contrast) {
            $prefs[Themes::PREF_CONTRAST] ??= true;
        }

        if (! is_string($main) || ! array_key_exists($main, self::MAIN)) {
            return $prefs;
        }

        [$mode, $appearance] = self::MAIN[$main];

        $prefs['theme'] ??= $mode;

        // A main choice was what the user actually saw in that mode. 1.x's pair only
        // applied under Auto, so the main choice wins for its mode.
        if ($appearance !== null) {
            $prefs[$mode === Themes::SCHEME_DARK ? Themes::PREF_DARK : Themes::PREF_LIGHT] = $appearance;
        }

        return $prefs;
    }

    /**
     * Whether there's anything from 1.x to carry over.
     *
     * @param  array<string, mixed>  $prefs
     */
    public static function hasLegacy(array $prefs): bool
    {
        return array_key_exists('castTheme', $prefs)
            || in_array($prefs[Themes::PREF_LIGHT] ?? null, self::HIGH_CONTRAST, true)
            || in_array($prefs[Themes::PREF_DARK] ?? null, self::HIGH_CONTRAST, true);
    }
}
