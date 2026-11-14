<?php

/**
 * Cast config file.
 *
 * Publish it into your project with `php artisan vendor:publish --tag=cast`, which
 * copies it to `config/craft/cast.php`, and edit it there. Anything set in that file
 * overrides the value saved under Settings → Plugins → Cast, and the field shows as
 * read-only there.
 *
 * Coming from Cast 1.x on Craft 5: the file has moved from `config/cast.php` to
 * `config/craft/cast.php`, and Craft 5's per-environment keys (`'*'`, `'dev'`, …)
 * aren't read. Use `env()` for values that differ by environment.
 */

return [
    // The appearances for users who haven't chosen their own: a theme handle, or '' for
    // Craft's own. Light takes 'stone'; dark takes 'stone' or 'dim'.
    // 'autoLightTheme' => '',
    // 'autoDarkTheme' => '',

    // The primary button colour for users who haven't chosen their own: red (Craft
    // Red, Craft 5's own), amber, green, teal, sky, violet, pink or gray, or '' for
    // Craft's own blue.
    // 'defaultButtonColor' => '',

    // Whether users who haven't set it themselves get increased contrast, in light and
    // dark alike, on top of the appearance.
    // 'highContrast' => false,

    // Whether users may pick their own appearances and button colour, beside Craft's
    // Theme field in their account preferences. The contrast switch is always theirs.
    // 'allowUserOverride' => true,
];
