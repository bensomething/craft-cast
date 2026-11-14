# Cast

More looks for the Craft CMS control panel. Craft 6 decides whether the control panel is light or dark; Cast decides what each looks like, with **Stone** and **Dim** themes, an **Increase contrast** switch, and a **Button Colour**, chosen per user with site-wide defaults.

A theme is a stylesheet overriding the colour tokens Craft 6 already draws everything from, so there are no forked templates and a Craft upgrade comes through untouched. Choices are stamped onto `<html>` ahead of Craft's styles, so there's no flash, and changing one repaints at once without a save.

> [!NOTE]
> Cast 2 is in beta, as is Craft 6. Cast's themes are built on Craft 6's colour tokens, which may still change before Craft 6.0.

Cast 2 is for Craft 6. For Craft 5, use [Cast 1.x](https://github.com/bensomething/craft-cast/tree/develop). Upgrading? See [UPGRADE.md](UPGRADE.md).

## Requirements

- Craft CMS 6.0.0-beta.1 or later
- PHP 8.5 or later

## Installation

```bash
composer require bensomething/craft-cast:^2.0.0-beta
php artisan craft:plugin:install cast
```

If Composer refuses, your project's `minimum-stability` is `stable`. Set it to `beta` with `"prefer-stable": true`, or pin the exact version.

## Usage

Each user picks their own in **Account → Preferences**:

- **Light Appearance** and **Dark Appearance** sit beside Craft's own **Theme** field. Only the ones Craft's Theme can use are shown: one for Light or Dark, both for System.
- **Button Colour** sits beside them, and applies in either mode.
- **Increase contrast** is under **Accessibility**, with the rest of the display settings.

**Settings → Plugins → Cast** sets the defaults for anyone who hasn't chosen their own, which themes they can pick from, and whether they can choose at all.

### Themes

| Theme | Modes | Notes |
| --- | --- | --- |
| Stone | Light, dark | Craft's neutrals warmed to its own stone hue. Every step keeps the luminance of Craft's, so every contrast ratio is exactly Craft's. |
| Dim | Dark | Craft's dark with lifted surfaces and softer text, for long sessions. Body text 7.8:1, quiet text 5.4:1. |

**Craft default** is Craft's own look for that mode.

### Increase contrast

Moves text, borders and links up Craft's own colour ramps, makes hairline borders solid, widens the focus ring, and deepens dark surfaces. It applies in light and dark alike, on top of the appearance:

| | Light | Craft light | Dark | Craft dark |
| --- | --- | --- | --- | --- |
| Body text | 15.1:1 | 12.3:1 | 16.8:1 | 11.2:1 |
| Quiet text | 9.5:1 | 5.5:1 | 12.1:1 | 6.5:1 |
| Form-control borders | 5.5:1 | 3.3:1 | 8.4:1 | 4.0:1 |
| Links | 7.2:1 | 5.5:1 | 12.1:1 | 6.5:1 |

### Button colour

Recolours primary buttons only; selections, focus rings and the rest of Craft's accent colour stay Craft's. The colours are Craft's own blue, **Craft Red** (Craft 5's primary button), and Amber, Green, Teal, Sky, Violet, Pink and Gray from Craft 6's palette. Each takes its fill, border and label from Craft's generated colours, which hold the label at 4.6:1 or better in both modes. Craft Red is Craft 5's `#dc2626` with a white label, 4.83:1.

## Adding your own theme

Drop a `.css` file in `cast-themes/`, alongside `config/` in your project. The filename is the handle; a header comment names the theme and says which of Craft's modes it dresses:

```css
/**
 * Theme Name: Midnight
 * Modes: dark
 * Description: Near-black, for late sessions.
 */

html[data-theme='dark'][data-cast-dark='midnight'] {
    --c-surface-default: #0b1020;
    --c-surface-sunken: #060914;
    --c-text-default: #e6ecff;
}
```

- **`Modes`** is required: `light`, `dark`, or `light, dark`. A file without it is taken to be a Cast 1.x theme and skipped.
- **The selector** pairs Craft's `data-theme` with Cast's `data-cast-dark` (or `data-cast-light`), so the theme applies only while Craft is in that mode and the user has picked it. For a light theme: `html:not([data-theme='dark'])[data-cast-light='midnight']`.
- **What to override** is Craft 6's colour tokens: `--c-surface-*`, `--c-text-*`, `--c-border-*`, and the `--color-base-*` and `--color-slate-*` ramps they're drawn from. Craft's own `tokens.css` and `color-palette.css` list them. [`stone.css`](resources/themes/stone.css) and [`dim.css`](resources/themes/dim.css) are worked examples.
- **Handles** are lower-case letters, numbers and dashes, starting with a letter. Files starting with an underscore are skipped as partials. A bundled theme's handle can't be taken by a file.

The name falls back to the filename. The file is the registration, so a theme can't exist in one environment and not another. **Settings → Plugins → Cast** lists any file that was skipped, with why.

Cast serves the folder itself, with a hash of each file in its URL, so the folder needn't be in the web root and an edited theme busts its own cache. Point elsewhere with `themesPath`.

## Registering a theme in code

For a plugin or module shipping its own, listen for `ThemesResolving`:

```php
use bensomething\cast\Events\ThemesResolving;
use bensomething\cast\Theme;
use Illuminate\Support\Facades\Event;

Event::listen(function (ThemesResolving $event) {
    $event->add(new Theme(
        handle: 'midnight',
        name: 'Midnight',
        modes: ['dark'],
        url: asset('vendor/acme/midnight/midnight.css'),
    ));
});
```

| | |
| --- | --- |
| **Class** | `bensomething\cast\Events\ThemesResolving` |
| **Fires** | Once per request, the first time Cast needs its themes |
| **`$themes`** | The themes so far, by handle: the bundled ones, then the themes folder's |
| **`add(Theme $theme)`** | Adds a theme, replacing any with the same handle |

Listeners can also replace a theme by adding one with its handle, or remove one with `unset($event->themes['dim'])`. A theme with an invalid handle, no modes, or a mode other than `light` and `dark` is dropped.

`Theme` takes either a `url`, for a stylesheet you serve, or a `path`, for a file Cast serves the way it serves the themes folder. Every theme's stylesheet loads on every control panel page, so a choice repaints at once.

## Config

Override any setting in `config/craft/cast.php`. Publish a commented one with:

```bash
php artisan vendor:publish --tag=cast
```

```php
return [
    'autoLightTheme' => '',          // a light theme's handle, or '' for Craft's own
    'autoDarkTheme' => 'dim',        // a dark theme's handle, or '' for Craft's own
    'defaultButtonColor' => 'red',   // red (Craft Red), amber, green, teal, sky, violet, pink, gray, or '' for Craft's blue
    'highContrast' => false,
    'allowUserOverride' => true,
    'enabledThemes' => '*',          // or a list of handles
    'themesPath' => '@root/cast-themes',
];
```

A setting the file sets shows as read-only on the settings screen. Use `env()` for values that differ by environment.

> [!WARNING]
> The `cast` publish tag also covers Cast's stylesheets. `vendor:publish --tag=cast --force`, to refresh those, overwrites your `config/craft/cast.php` too.

## Development

```bash
composer test        # PHPUnit, against a fresh Craft install per test
composer phpstan     # level 8
composer check-cs    # Pint
npm test             # the preferences script, in jsdom
```

## Licence

MIT.
