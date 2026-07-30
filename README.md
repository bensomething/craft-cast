# Cast

Colour modes for the Craft CMS control panel. **Dark**, **Dim**, **Stone** and **High Contrast**, an **Auto** mode that follows the operating system, and a per-user picker.

A theme is a stylesheet redeclaring the custom properties Craft already ships in `ThemeAsset` — no forked templates, no rewritten layouts, so a Craft upgrade comes through untouched. The active theme is stamped onto `<html>` by an inline head script, so there's no flash, and picking one repaints immediately without a save.

> [!NOTE]
> Cast is in beta. The theme APIs are settled — `Themes::EVENT_REGISTER_THEMES` and the `cast-themes/` file format won't change before 1.0.0 — but the plugin's own settings may still move.

## Requirements

- Craft CMS 5.10+
- PHP 8.2+

## Installation

```bash
composer require bensomething/craft-cast:^1.0.0-beta
```

If Composer refuses, your project's `minimum-stability` is `stable`. Set it to `beta` with `"prefer-stable": true`, or pin the exact version.

## Usage

**Settings → Plugins → Cast** sets the site-wide default and which themes users can pick from. Users choose their own under **Account → Preferences → Colour mode**.

**Auto is a pair, not a theme.** Anyone set to Auto gets the two themes configured on the **Auto** tab, so the **Default theme** has no effect on them. That pair is the starting point rather than the last word: a user who can pick their own theme can also name their own light or dark half, and either half falls back to the admin's if the theme it names goes away.

> **If the picker doesn't appear on the preferences screen**, run `php craft clear-caches/compiled-templates`. Cast renders it through Craft's `cp.users.edit.prefs` hook, and a stale compiled template silently skips it.

### Bundled themes

| Theme | Scheme | Notes |
| --- | --- | --- |
| Dark | Dark | Neutral dark CP. The reference implementation. |
| Dim | Dark | Softer and lower contrast, for long sessions. |
| Stone | Light | A warm neutral, based on Tailwind's stone ramp. |
| Stone Dark | Dark | The same warm neutral, but.. dark. |
| High Contrast | Light | Near-black text, solid borders, widened focus ring. |
| High Contrast Dark | Dark | The same treatment on a near-black canvas. |

## Button colour

Repoints the fill of Craft's primary buttons at one of nine colours. Beyond primary buttons, `--bg-primary` reaches only the Plugin Store's cart badge and the installer's step dots.

Each is shaded per mode so the white label clears WCAG AA against the fill (≥4.5:1 light, ≥4:1 dark). **Black/White** carries its own label colour so it can swap ends with the mode. **Amber** is a knowing compromise at 3.19:1 — the only warm colour distinct enough from the default that keeps a white label. Status colours stay red.

## Adding your own theme

Drop a `.css` file in `cast-themes/`, alongside `config/` and `templates/`. The filename is the handle, a header comment supplies the rest.

```css
/**
 * Theme Name: Midnight
 * Color Scheme: dark
 * Description: Near-black, for late sessions.
 */

html[data-cast-theme="midnight"] {
    --body-bg: #05070d;
    --text-color: #e6ecff;
}
```

Only **Color Scheme** really matters: it decides whether `_dark-base.css` loads ahead of your theme, and which side of **Auto** it sits on. The name falls back to the filename and the scheme to light, so a file with no header is still a theme. Files starting with an underscore are partials and skipped.

The file *is* the registration, so a theme can't exist in one environment and not another. **Settings → Plugins → Cast → Themes** lists what was found and any file that couldn't be read, with the reason. The folder is published to `cpresources` — it needn't sit in the web root, and editing a theme busts its own cache. Point elsewhere with `themesPath` in `config/cast.php`.

Bundled handles win, so a `dark.css` won't quietly redefine **Dark** for everyone already using it. To genuinely replace one, register it in code.

### What it costs

Craft's palette derives almost entirely from a `--gray-*-hsl` ramp plus semantic properties (`--pane-bg`, `--text-color`, …), and most themes are a page of variables. Dark themes get `_dark-base.css` ahead of them — the inverted ramp plus patches — so they only declare deltas. Cast also sets `data-cast-scheme` to `light` or `dark`, so several of your own themes can share a base the same way.

> Never `@import` a base from a theme. The preview screens load every theme at once, and a second import would re-declare the base *after* the first theme's overrides, flattening it.

The expense isn't light versus dark, and it isn't how much of the ramp you redeclare. It's **how far you move the hue**. Dim rewrites all fourteen ramp steps but keeps Craft's blue-grey, and needs two extra rules. Stone shifts to a warm neutral and needs nine — as does Stone Dark, base or no base. Craft and the dark base both write blues straight into rules in a few dozen places, and those don't follow a retinted ramp. The most visible is `--fg-input`, which every control background derives from at 25%, 30% and 50% alpha.

Move the hue and `stone.css` is the worked example; each patch carries a note on what was pinned and why.

## Registering a theme in code

For a plugin or module shipping its own:

```php
use bensomething\cast\events\RegisterThemesEvent;
use bensomething\cast\models\Theme;
use bensomething\cast\services\Themes;
use yii\base\Event;

Event::on(Themes::class, Themes::EVENT_REGISTER_THEMES, function(RegisterThemesEvent $event) {
    $event->themes['midnight'] = new Theme([
        'handle' => 'midnight',
        'name' => 'Midnight',
        'colorScheme' => Theme::SCHEME_DARK,
        'url' => Craft::$app->getAssetManager()->getPublishedUrl(
            '@mymodule/resources', true, 'midnight.css',
        ),
    ]);
});
```

Use a bundled handle as the key to replace that theme.

## Opting a screen out

Most plugin UIs get Cast for free, building on Craft's components. One shipping its own compiled stylesheet with colours baked in doesn't, and lands as dark text on a dark canvas.

```twig
<div data-cast-ignore>
    {# your app #}
</div>
```

Everything inside gets Craft's palette back, plus `color-scheme: light` so native controls follow. It's the whole subtree, so mark the outermost element the region owns. Menus opened from inside come with it — Garnish moves them to `<body>`, which would otherwise hand them back to the theme. Craft's Plugin Store is exempted this way out of the box.

Reach for it only when a region genuinely can't follow the palette; fixing the stylesheet to read Craft's properties earns dark mode rather than opting out of it.

> **Caveat:** the region is restored through Cast's variables, so anything reading them corrects itself. A few patches carry literal colours — Prism syntax highlighting mainly — and those still need overriding by hand inside an ignored region.

## Config

Override per environment in `config/cast.php`:

```php
return [
    'buttonColor' => 'red',
    'defaultTheme' => 'auto',
    'allowUserOverride' => true,
    'enabledThemes' => ['dark', 'high-contrast'],
    'autoLightTheme' => '',
    'autoDarkTheme' => 'dark',
    'themesPath' => '@root/cast-themes',
];
```

An empty handle means Craft's stock appearance. Copy [`src/config.php`](src/config.php) for a commented starting point.

## Licence

MIT.
