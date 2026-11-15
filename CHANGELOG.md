# Release Notes for Cast

## Unreleased

### Fixed
- Logging in could land on a Cast stylesheet rather than the control panel. The login screen linked the theme stylesheets, which only signed-in users can fetch, so the browser's request for one was remembered as the page to return to.

## 2.0.0-beta.2 - 2026-11-15

### Added
- Colour themes: **Red**, **Amber**, **Green**, **Teal**, **Blue**, **Violet** and **Pink**, for light and dark, made from Craft's palette hues, keeping every contrast ratio Craft's. Each also colours the focus ring. **Hue Tint**, under **Settings → Plugins → Cast**, sets how strongly they come through.
- **Custom Themes**, under **Settings → Plugins → Cast**, for brand colours the colour themes don't cover: a theme for light and dark made from one colour and a tint. Each row's **Preview** buttons show it on the settings screen, in either mode, before saving.

### Changed
- The settings screen groups **Available Themes** and **Custom Themes** under their own **Themes** heading.
- **Dim** is listed before **Stone**.
- **Sky** is no longer a **Primary Button Colour**: it was too close to Craft's own blue, which anyone who chose it now gets.
- **Increase contrast** stays in each user's preferences with **Let Users Choose** off. It's an accessibility need rather than a look, so it isn't locked along with the appearances and button colour.

### Fixed
- Saving the settings with **All** ticked under **Available Themes** failed with “Pick from the themes listed.”

## 2.0.0-beta.1 - 2026-11-14

Cast 2 is for Craft 6, and rebuilt around its own Theme preference: Craft decides whether the control panel is light or dark, and Cast decides what each looks like. See [UPGRADE.md](UPGRADE.md) for moving from 1.x.

### Added
- **Light Appearance** and **Dark Appearance**, beside Craft's **Theme** field in **Account → Preferences**. Only the ones Craft's Theme can use are shown.
- **Increase contrast**, under **Preferences → Accessibility**, on top of whichever appearance is showing, in light and dark alike.
- **Primary Button Colour** per user, with a site default. **Craft Red** is Craft 5's primary button.
- `ThemesResolving`, a Laravel event for registering, replacing or removing themes.
- Settings set in `config/craft/cast.php` show as read-only on the settings screen, saying so.

### Changed
- Requires Craft 6 and PHP 8.5.
- Themes override Craft 6's colour tokens, scoped to the mode they dress. **Stone** is offered for both modes and **Dim** for dark.
- A themes-folder file's header must declare `Modes`. Files without it, such as 1.x themes, are skipped and listed on the settings screen.
- `autoLightTheme` and `autoDarkTheme` are now the default appearances for everyone, not just users on Auto.
- The config file moves to `config/craft/cast.php`, and Craft 5's per-environment keys aren't read.
- Each user's 1.x choice is carried over by a migration: Craft's Theme, plus the matching appearance, and the contrast switch for either high contrast theme.

### Removed
- **Dark**, which is now Craft's own dark, and **Stone Dark**, which is Stone offered for dark.
- **High Contrast** and **High Contrast Dark**, which are now the **Increase contrast** switch.
- **Auto**, which is now Craft's own System theme.
- The site-wide `buttonColor` setting, in favour of `defaultButtonColor` and per-user colours.
- `data-cast-ignore`.
- `Themes::EVENT_REGISTER_THEMES` and `RegisterThemesEvent`, in favour of `ThemesResolving`.

## 1.x

Cast 1, for Craft 5, has its own [changelog](https://github.com/bensomething/craft-cast/blob/develop/CHANGELOG.md).
