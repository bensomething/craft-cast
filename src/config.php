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

use bensomething\cast\models\Settings;

return [
    // Fill for primary buttons: red, amber, green, teal, sky, blue, violet, pink or gray.
    'buttonColor' => 'red',

    // Theme applied to users who haven't chosen one. A theme handle — dark, dim,
    // high-contrast or high-contrast-dark for the bundled ones — or 'auto' to follow the
    // OS colour-scheme preference, or '' for Craft's stock appearance.
    'defaultTheme' => Settings::THEME_AUTO,

    // Whether users may pick their own theme in their account preferences.
    'allowUserOverride' => true,

    // Theme handles offered in the picker, or '*' for all. The default theme above is
    // always available, whether or not it's listed here.
    'enabledThemes' => '*',

    // Themes used when 'auto' resolves to dark and to light. An empty handle means
    // Craft's stock light control panel.
    'autoDarkTheme' => 'dark',
    'autoLightTheme' => Settings::THEME_NONE,

    // Folder scanned for theme stylesheets, as a path or alias. Every `.css` file in it
    // is a theme. It's published to `cpresources`, so it needn't sit in the web root.
    'themesPath' => '@root/cast-themes',
];
