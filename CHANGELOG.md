# Release Notes for Cast

## 1.0.0-beta.2 - 2026-07-20

### Fixed
- Colour-coded Matrix blocks, cards, and relation chips kept their light tint in dark modes. The hue is kept and the surfaces rebuilt around it, including a distinct selected state.
- The CKEditor toolbar and its floating panels stayed white, being pinned past Craft's own tokens.
- Fields in a `.flex-fields` row drew a brighter divider wherever one landed on the container's edge.
- The icon preview took a browser-default border, having asked for a variable Craft never defines.

## 1.0.0-beta.1 - 2026-07-20

### Added
- Colour modes for the control panel, driven by Craft's own CSS custom properties. An inline head script stamps the active theme onto `<html>`, so there's no flash.
- Bundled themes: **Dark**, **Dim**, **High Contrast**, and **High Contrast (Dark)**.
- **Auto** mode, following `prefers-color-scheme` and switching live with the OS. It's a configurable light/dark pair, not a theme — anyone set to Auto ignores the default theme.
- Per-user theme picker on **Account → Preferences**, with live preview and a **Site default** option that hands the choice back to the admin setting.
- Theme switcher in the account menu.
- Site-wide default theme and an **Available themes** allowlist in the plugin settings.
- **Button colour** setting — nine fills for primary buttons, using Craft's colour names. Most clear WCAG AA for their white label; amber-600 is a deliberate exception at 3.19:1, as warmer hues can't stay distinct from the default red and carry white text.
- Project themes: drop a `.css` file in `cast-themes/` and Cast registers it, taking the handle from the filename and the rest from a header comment. Underscore-prefixed files are treated as partials. Relocatable with `themesPath` in `config/cast.php`.
- Read-only **Themes** tab in the settings, listing every registered theme with its scheme and origin, plus any file that couldn't be read as one and why.
- `Themes::EVENT_REGISTER_THEMES`, so other plugins and modules can register colour modes.
- A commented `src/config.php` to copy to `config/cast.php`.
