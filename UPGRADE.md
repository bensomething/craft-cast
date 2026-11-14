# Upgrading from Cast 1.x

Cast 2 is for Craft 6. Cast 1.x on Craft 5 doesn't run there, even through Craft's Yii adapter: Craft 6 no longer reads the Craft 5 colour variables 1.x themed. Upgrade Craft first, then Cast.

## What Craft 6 changed underneath

Craft 6 has its own **Theme** preference: Light, Dark, or System following the OS. Cast 2 doesn't replace it. Craft decides whether the control panel is light or dark; Cast decides what light and dark each look like, and adds a contrast switch and a button colour.

So the 1.x picker of one theme, with Auto as a pair, is now:

| 1.x | 2.x |
| --- | --- |
| Picking a dark theme | Craft's **Theme** set to Dark, plus a **Dark Appearance** |
| Picking a light theme | Craft's **Theme** set to Light, plus a **Light Appearance** |
| **Auto** and its light/dark pair | Craft's **Theme** set to System, plus both appearances |
| **Dark** | Craft's own dark |
| **Stone Dark** | **Stone** as the dark appearance |
| **High Contrast**, **High Contrast Dark** | The **Increase contrast** switch under **Preferences → Accessibility**, in either mode |
| **Button colour** (site-wide) | **Button Colour** per user, with a site default |

## Requirements

- Craft CMS 6.0.0-beta.1 or later
- PHP 8.5 or later

## Steps

1. Upgrade the project to Craft 6 with Cast 1.x still installed.
2. Require Cast 2: `composer require bensomething/craft-cast:^2.0.0-beta`.
3. Run `php artisan craft:up`. This carries each user's choices over (below).
4. If you had a `config/cast.php`, move it and update it (below).
5. If you had themes in `cast-themes/`, rewrite them (below).

## Users' choices are carried over

`craft:up` runs a migration that turns each user's 1.x choice into Craft's Theme plus the matching Cast appearance, as in the table above. Someone on Dim gets Craft's Dark with the Dim appearance; someone on either high contrast theme gets the contrast switch on. Users on Auto, or on the site default, already mean what 2.x's defaults do and are left as they are.

Anything a user has already set on Craft 6, including Craft's own Theme, is left alone, and no 1.x preference is deleted.

A user whose 1.x choice was a theme from `cast-themes/` falls back to the site default until that theme is rewritten for 2.x.

## Settings

Settings saved in the control panel carry over, with these changes:

| Setting | 2.x |
| --- | --- |
| `autoLightTheme`, `autoDarkTheme` | Now the default **Light Appearance** and **Dark Appearance** for everyone, not just users on Auto. 1.x's `dark` reads as Craft's own dark; `stone-dark` as Stone. |
| `enabledThemes` | Unchanged: **Available Themes**. |
| `themesPath` | Unchanged. Still `@root/cast-themes` by default. |
| `allowUserOverride` | Unchanged: **Let Users Choose**. |
| `defaultButtonColor` | New: the default **Button Colour**. Starts as Craft's own blue. |
| `highContrast` | New: whether **Increase contrast** is on for users who haven't set it. |
| `buttonColor` | Retired. 1.x saved `red`, Craft 5's own, for everyone, so it's not read: every upgraded site would otherwise turn its buttons red. Set `defaultButtonColor` to `red` (Craft Red) to keep them. |
| `defaultTheme` | Retired. Craft's own Theme preference decides light or dark. |

Retired settings are still accepted, so an old config file or project config doesn't fail.

## The config file has moved

Craft 6 reads plugin config from `config/craft/<handle>.php`. Move `config/cast.php` to `config/craft/cast.php`, or publish a fresh commented one with:

```bash
php artisan vendor:publish --tag=cast
```

Craft 5's per-environment keys (`'*'`, `'dev'`, `'production'`) aren't read. Use `env()` for values that differ by environment:

```php
return [
    'defaultButtonColor' => env('CAST_BUTTON_COLOR', ''),
];
```

A setting the file sets shows as read-only on the settings screen, as in 1.x.

## Themes in `cast-themes/` need rewriting

A 1.x theme redeclared Craft 5's variables (`--gray-*-hsl`, `--body-bg`, …), which Craft 6 doesn't read. A 2.x theme overrides Craft 6's colour tokens instead, scoped to the mode it dresses, and its header says which modes those are:

```css
/**
 * Theme Name: Midnight
 * Modes: dark
 */

html[data-theme='dark'][data-cast-dark='midnight'] {
    --c-surface-default: #0b1020;
    --c-surface-sunken: #060914;
    --c-text-default: #e6ecff;
}
```

`Modes` is required. A file without it is taken to be a 1.x theme, skipped, and listed on the settings screen with the reason. See [Adding your own theme](README.md#adding-your-own-theme).

## For developers

### Registering themes

`Themes::EVENT_REGISTER_THEMES` and `RegisterThemesEvent` are replaced by a Laravel event, `bensomething\cast\Events\ThemesResolving`.

Before:

```php
Event::on(Themes::class, Themes::EVENT_REGISTER_THEMES, function(RegisterThemesEvent $event) {
    $event->themes['midnight'] = new Theme([
        'handle' => 'midnight',
        'name' => 'Midnight',
        'colorScheme' => Theme::SCHEME_DARK,
        'url' => $url,
    ]);
});
```

After:

```php
use bensomething\cast\Events\ThemesResolving;
use bensomething\cast\Theme;
use Illuminate\Support\Facades\Event;

Event::listen(function (ThemesResolving $event) {
    $event->add(new Theme(
        handle: 'midnight',
        name: 'Midnight',
        modes: ['dark'],
        url: $url,
    ));
});
```

A theme's stylesheet needs rewriting too, as above.

### Renamed and removed classes

| 1.x | 2.x |
| --- | --- |
| `bensomething\cast\models\Settings` | `bensomething\cast\Settings` |
| `bensomething\cast\models\Theme` | `bensomething\cast\Theme`, now immutable, with `modes` in place of `colorScheme` |
| `bensomething\cast\services\Themes` | `bensomething\cast\Themes`, reached through the container rather than `Plugin::getInstance()->themes` |
| `bensomething\cast\events\RegisterThemesEvent` | `bensomething\cast\Events\ThemesResolving` |
| `bensomething\cast\controllers\PreferencesController` | `bensomething\cast\Http\Controllers\PreferencesController` |

### Removed

- **`data-cast-ignore`.** 1.x's opt-out restored Craft's stock look inside a region, mostly for UIs with colours baked into their own stylesheets. Craft 6 builds its control panel from its own tokens, so a region that follows those follows Cast too.
- **The `cast/preferences/save-theme` action.** Preferences save through `cast/preferences` now. Only Cast's own script called it.
- **`data-cast-theme` and `data-cast-scheme`** on `<html>`. 2.x stamps `data-cast-light`, `data-cast-dark`, `data-cast-button` and `data-cast-contrast`, and Craft stamps `data-theme`.
