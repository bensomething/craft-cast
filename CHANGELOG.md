# Release Notes for Cast

## 1.0.0-beta.6 - 2026-07-29

### Fixed
- Craft writes a `--gray-*` step out as a literal in a few dozen rules, which a theme moving the ramp then leaves behind. The sticky header and footer, live preview's headers, `h6`, optgroup and autosuggest headers, header button hovers, pane headers, dashed buttons, flex-field dividers and disabled table cells now read the ramp — in a new `_shared.css`, so a project theme gets them too.
- Colour swatches and transparent asset thumbnails drew their chequerboard in the same literal, so an unset colour read as a dark blob. The card drag placeholder took it as a dashed border.
- `--fg-input` is written out in four rules, leaving their outlines on Craft's blue while every control fill around them followed the theme: dashed buttons like an editable table's **Add a row**, grouped and hairline panes, the card view designer's preview, and the details pane's unfocused inputs.
- Three declarations in the dark base mixed `hsl()`'s comma and slash syntaxes, so browsers threw them out whole. The dark sticky header, the element index's refresh veil and live preview's headers had been falling back to Craft's own colour.

## 1.0.0-beta.5 - 2026-07-29

### Fixed
- CKEditor fields kept a faint hairline under the high contrast themes, where the inputs beside them take a solid border.
- Stone and Stone Dark drew text inputs, password fields, CodeMirror, multiselects and CKEditor fields with Craft's blue-grey border, pinned past the ramp.
- Stone drew hairline buttons, context labels and menus, and the condition footer in the same blue-grey.

## 1.0.0-beta.4 - 2026-07-25

### Changed
- The high contrast themes are rebuilt on pure white and pure black, a desaturated ramp, and a solid border on every container.

### Fixed
- JSON fields stayed white on every dark theme, CodeMirror painting the editor from the same selector Craft points at `--input-bg`.
- Matrix blocks and cards sank below the pane they're inset into on every dark theme.
- The field layout designer lost most of its edges on dark themes, and field handles picked up the code block fill.
- Under high contrast, cards, chips, Matrix blocks, grouped fields, selects, menus and the Settings tiles' hover state had no border to speak of.

## 1.0.0-beta.3 - 2026-07-21

### Added
- **Stone** and **Stone Dark** themes, based on Tailwind's stone ramp, a warm neutral.

### Fixed
- `data-cast-ignore` did nothing under a light theme, the reset being scoped to dark. Same for the Plugin Store, exempted through the same rules.

## 1.0.0-beta.2 - 2026-07-20

### Fixed
- Colour-coded Matrix blocks, cards and relation chips kept their light tint in dark modes.
- The CKEditor toolbar and its floating panels stayed white, being pinned past Craft's own tokens.
- Fields in a `.flex-fields` row drew a brighter divider wherever one landed on the container's edge.
- The icon preview took a browser-default border, having asked for a variable Craft never defines.

## 1.0.0-beta.1 - 2026-07-20

### Added
- Colour modes for the control panel, driven by Craft's own CSS custom properties. An inline head script stamps the active theme onto `<html>`, so there's no flash.
- Bundled themes: **Dark**, **Dim**, **High Contrast** and **High Contrast (Dark)**.
- **Auto** mode, a configurable light/dark pair following `prefers-color-scheme`. It's not a theme — anyone set to Auto ignores the default.
- Per-user picker on **Account → Preferences** with live preview, and a switcher in the account menu.
- Site-wide default theme and an **Available themes** allowlist in the plugin settings.
- **Button colour** setting — nine fills for primary buttons. Most clear WCAG AA for their white label; amber-600 is a deliberate exception at 3.19:1.
- Project themes: drop a `.css` file in `cast-themes/`, handle from the filename and the rest from a header comment. Underscore-prefixed files are partials. Relocatable with `themesPath`.
- Read-only **Themes** tab in the settings, listing every registered theme and any file that couldn't be read as one.
- `Themes::EVENT_REGISTER_THEMES`, so other plugins and modules can register colour modes.
- A commented `src/config.php` to copy to `config/cast.php`.
