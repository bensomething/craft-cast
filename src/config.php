<?php
/**
 * Cast config.php
 *
 * This file exists only as a template. Copy it to `config/cast.php` and edit it there —
 * anything set here is ignored.
 *
 * Settings in `config/cast.php` override the ones saved in the control panel, and the
 * fields they cover become read-only there. Values can be environment-aware, using
 * Craft's usual `*`, `dev`, `staging` and `production` keys.
 */

use bensomething\cast\Settings;

return [
    // Craft decides whether the control panel is light or dark, from each user's own
    // Theme preference. Cast decides what light and dark each look like.

    // The appearances for users who haven't chosen their own: a theme handle, or '' for
    // Craft's own. Light takes 'stone'; dark takes 'stone' or 'dim'.
    'autoLightTheme' => Settings::THEME_NONE,
    'autoDarkTheme' => Settings::THEME_NONE,

    // Whether users who haven't set it themselves get increased contrast, in light and
    // dark alike. It replaces the appearance while on.
    'highContrast' => false,

    // Whether users may pick their own appearances, under Craft's Theme field in
    // their account preferences.
    'allowUserOverride' => true,

    // Not yet used in 2.x: button colour, limiting the themes on offer, and a themes
    // folder all come back once the bundled themes have settled on Craft 6's tokens.
    // 'buttonColor' => 'red',
    // 'enabledThemes' => '*',
    // 'themesPath' => '@root/cast-themes',
];
