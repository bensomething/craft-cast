# Cast

Colour modes for the Craft CMS control panel. Craft 5 ships a light-only CP; Cast adds **Dark**, **Dim**, and **High contrast** modes, an **Auto** mode that follows the operating system, and lets each user pick their own.

## Why Cast

- **No forks, no overrides:** themes are stylesheets that override the CSS custom properties Craft already declares in `ThemeAsset`. Nothing is patched, and a Craft upgrade can't break a layout.
- **Per user:** everyone chooses their own from **Account → Preferences**, or you pin the whole team to one.
- **No flash:** the active theme is stamped onto `<html>` by an inline head script, before first paint.
- **Live preview:** picking a theme repaints the page immediately, no save needed.
- **Extensible:** register your own theme from any plugin or module.

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
| High contrast | Light | Near-black text, solid borders, widened focus ring. |
| High contrast (dark) | Dark | The same treatment on a near-black canvas. |

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

> Never `@import` the base from a theme. The preview screens load every theme at once, and a second import would re-declare the base *after* the first theme's overrides; with equal specificity, the base would win and flatten it.

## Button colour

**Settings → Plugins → Cast → Button colour** repoints the fill of Craft's primary buttons at one of nine colours, previewed live as you pick. It's named for what it does: beyond primary buttons, `--bg-primary` reaches only the Plugin Store's cart badge and the installer's step dots.

Most options are shades of a Craft ramp, chosen per mode so the white label clears WCAG AA against the fill (≥4.5:1 in light, ≥4:1 in dark) while staying distinct from a dark pane. **Black/White** is the exception, carrying its own label colour so it can swap ends with the mode. **Amber** is a knowing compromise — the only warm colour distinct enough from the default that still keeps a white label, at 3.19:1.

Status colours stay red, since a disabled indicator shouldn't follow your button colour.

## Registering your own theme

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

## Config

Settings can be overridden per environment in `config/cast.php`:

```php
return [
    'defaultTheme' => 'auto',
    'allowUserOverride' => true,
    'enabledThemes' => ['dark', 'high-contrast'],
    'autoLightTheme' => '',
    'autoDarkTheme' => 'dark',
];
```

An empty theme handle means Craft's stock appearance.

## Licence

MIT.
