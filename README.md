# Cast

Colour modes for the Craft CMS control panel. Cast adds **Dark**, **Dim**, and **High Contrast** modes, an **Auto** mode that follows the operating system, and lets each user pick their own.

## Why Cast

- **No forks, no overrides:** themes are stylesheets that override the CSS custom properties Craft already declares in `ThemeAsset`. Nothing is patched, and a Craft upgrade can't break a layout.
- **Per user:** everyone chooses their own from **Account → Preferences**, or you pin the whole team to one.
- **No flash:** the active theme is stamped onto `<html>` by an inline head script, before first paint.
- **Live update:** picking a theme repaints the page immediately, no save needed.
- **Your own themes:** drop a `.css` file in `cast-themes/` and it's automatically registered. Plugins and modules can register in code.

## Requirements

Craft CMS 5.10+ and PHP 8.2+.

## Installation

```bash
composer require bensomething/craft-cast
```

## Usage

**Settings → Plugins → Cast** sets the site-wide default and which themes users can pick from. Users then choose their own under **Account → Preferences → Colour mode**, where **Site default** hands the choice back to the admin setting.

> **If the picker doesn't appear on the preferences screen**, run `php craft clear-caches/compiled-templates`. Cast renders it through Craft's `cp.users.edit.prefs` template hook, and a stale compiled copy of Craft's preferences template will silently skip it.

Note that **Auto is a pair, not a theme**: anyone set to Auto gets the two themes configured on the **Auto** tab, so changing the **Default theme** has no effect on them.

### Bundled themes

| Theme | Scheme | Notes |
| --- | --- | --- |
| Dark | Dark | Neutral dark CP. The reference implementation. |
| Dim | Dark | Softer and lower contrast, for long sessions. |
| High Contrast | Light | Near-black text, solid borders, widened focus ring. |
| High Contrast (Dark) | Dark | The same treatment on a near-black canvas. |

**Auto** follows the browser's `prefers-color-scheme` and switches live when the OS does. Which two themes it picks between is configurable on the **Auto** tab.

## How a theme works

Craft's CP palette is almost entirely derived from a `--gray-*-hsl` ramp plus a set of semantic properties (`--pane-bg`, `--text-color`, `--border-hairline`, …). A theme is a stylesheet that redeclares those, scoped to the attribute Cast sets on `<html>`:

```css
html[data-cast-theme="midnight"] {
    --body-bg: #05070d;
    --text-color: #e6ecff;
}
```

Cast also sets `data-cast-scheme` to `light` or `dark`, so several themes can share a base stylesheet. Every theme with `colorScheme: dark` gets `_dark-base.css` loaded ahead of it — the inverted grey ramp plus patches for the colours Craft hardcodes — so a dark theme only needs to declare its deltas.

> Never `@import` the base from a theme. The preview screens load every theme at once, and a second import would re-declare the base *after* the first theme's overrides. With equal specificity, the base would win and flatten it.

## Button colour

**Settings → Plugins → Cast → Button colour** repoints the fill of Craft's primary buttons at one of nine colours, previewed live as you pick. It's named for what it does: beyond primary buttons, `--bg-primary` reaches only the Plugin Store's cart badge and the installer's step dots.

Most options are shades of a Craft ramp, chosen per mode so the white label clears WCAG AA against the fill (≥4.5:1 in light, ≥4:1 in dark) while staying distinct from a dark pane. **Black/White** is the exception, carrying its own label colour so it can swap ends with the mode. **Amber** is a knowing compromise — the only warm colour distinct enough from the default that still keeps a white label, at 3.19:1.

Status colours stay red, since a disabled indicator shouldn't follow your button colour.

## Adding your own theme

Drop a `.css` file in `cast-themes/`, alongside `config/` and `templates/`, and Cast registers it. The filename is the handle, the header comment supplies the rest.

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

Of those, only **Color Scheme** really matters: it decides whether `_dark-base.css` loads ahead of your theme, and which side of **Auto** it sits on. The name falls back to the filename and the scheme to light, so a stylesheet with no header at all is still a theme. Files starting with an underscore are treated as partials and skipped, as `_dark-base.css` is.

There's nothing to configure and nothing to install, the file *is* the registration, so a theme can't exist in one environment and not another, and the control panel can't hold a reference to a stylesheet that isn't there. **Settings → Plugins → Cast → Themes** lists what was found, where each theme came from, and any file that couldn't be read as one, with the reason.

The folder is published to `cpresources`, so it needn't sit in the web root, and every theme's URL is hashed on the folder's modification time. Edit a theme and the control panel picks it up without a cache clear. Point Cast somewhere else with `themesPath` in `config/cast.php`.

Bundled handles win, so naming a file `dark.css` won't quietly redefine **Dark** for everyone already set to it. To genuinely replace a bundled theme, register it in code.

## Registering a theme in code

For a plugin or module shipping its own theme, rather than a project adding one:

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

Replacing a bundled theme is a matter of using its handle as the key.

## Opting a screen out

Most plugin UIs get Cast for free, because they build on Craft's own components and read its custom properties. A screen that ships its own compiled stylesheet with colours baked in as literal values doesn't, and lands as dark text on a dark canvas.

Mark that region and Cast renders it as stock Craft, leaving the CP around it themed:

```twig
<div data-cast-ignore>
    {# your app #}
</div>
```

Everything inside gets Craft's palette back — the grey ramp, panes, inputs, status colours — plus `color-scheme: light`, so native controls and scrollbars follow. It's the whole subtree, so put it on the outermost element the region owns.

Craft's own Plugin Store is exempted this way out of the box.

Reach for it only when a region genuinely can't follow the palette. Fixing the stylesheet to read Craft's properties is better where that's an option, since it earns dark mode rather than opting out of it.

> **A caveat:** the region's own colours are restored through Cast's variables, so anything that reads them corrects itself. A handful of Cast's patches carry literal colours — Prism syntax highlighting is the main one — and those still need overriding by hand inside an ignored region.

## Config

Settings can be overridden per environment in `config/cast.php`:

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

An empty theme handle means Craft's stock appearance. Copy [`src/config.php`](src/config.php) to `config/cast.php` for a commented starting point.

## Licence

MIT.
