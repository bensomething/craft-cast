# Cast on Craft 6

Notes towards a 6.x version. Nothing here is committed to — Craft 6 is unreleased and
its token names are still moving.

Checked against `craftcms/cms` `6.x` at `18e2816` (30 July 2026), the Storybook build at
`2b72dd74.craftcms-ui-storybook.pages.dev`, and PRs
[#18745](https://github.com/craftcms/cms/pull/18745) (merged 1 May 2026) and
[#18791](https://github.com/craftcms/cms/pull/18791) (merged 5 June 2026).

## What Craft 6 ships

A generated palette. `packages/craftcms-ui/src/styles/color-definitions.js` feeds colour
keys through [Leonardo](https://leonardocolor.io) against arrays of target contrast
ratios, producing three themes:

| Theme | Lightness | Ratio range |
| --- | --- | --- |
| `light` | 97 | 1.08 – 13.94 |
| `dark` | 26 | 1.08 – 10.18 |
| `static` | 100 | 1.45 – 15.91 |

21 hues at 11 stops (50–950), plus a `base` ramp on its own ratio array. `static` exists
for things that must not follow the theme — logos, brand marks, the fills under a fixed
white label.

The generator writes `shared/color-palette.css`: light under `:root, :host`, dark under
`[data-theme='dark']`. Above it sit `tokens.css` (`--c-surface-*`, `--c-text-*`,
`--c-status-*`, `--c-form-control-*`, `--c-pane-*`, `--c-modal-*`) and a generated
`colorable.css` — `--c-color-{hue}-{fill|border|on}-{quiet|normal|loud}` across 21 hues
and six semantics (neutral, accent, info, success, warning, danger).

Contrast is structural rather than checked after the fact: stop 400 and above clears 3:1
against the theme background, 600 and above clears 4.5:1. That is the guarantee Cast
currently makes by hand, per colour, per mode.

## What it doesn't ship

**Nothing sets `data-theme`.** The only occurrence in the entire 6.x source is
`.storybook/preview.ts:56` — the `withThemeByDataAttribute` decorator. `prefers-color-scheme`
appears once, in the Plugin Store's `RatingStars.vue`.

No colour-mode user preference. `UserPreferencesViewModel` and `resources/js/pages/users/Preferences.vue`
carry language, locale, week start day, timezone, notification duration and dev settings,
and nothing else.

No picker, no persistence, no site-wide default, no OS pairing, no flash-free
application, no registration point for a third-party theme. Two modes, and only in
Storybook. One `forced-colors: active` block in the codebase, in `slide-picker`.

Craft 6 has built the paint. Cast is the switch.

## The compat layer

`packages/craftcms-legacy/cp/src/css/_compat.scss` (621 lines, first import in
`craft.scss`) re-points Craft 5's variables at the new tokens — `--body-bg` at
`--c-surface-default`, `--text-color` at `--c-text-default`, and on down. This matters
more than anything else in this document: **if something stamped `data-theme="dark"`,
the legacy CP would largely follow it**, because the legacy variables now resolve
through the adaptive palette rather than holding their own values.

That is the nine stylesheets of hand-mapping in `src/resources/themes/` collapsing
towards one attribute.

It is not finished, though. Referenced by `_compat.scss` and defined nowhere in the
repo — 32 properties:

- **The entire button token family** (10): `--c-button-default-{fill,fill-hover,border,border-hover,text}`
  and `--c-button-primary-{…}`. So `--primary-button-bg`, which Cast's button-colour
  feature repoints, currently resolves to nothing.
- **`--c-color-brand-border-loud`**, behind `--border-primary`. `brand` isn't among the
  six semantics in `generate-colors.js`; only `--c-color-static-brand-*` exists.
- **14 gray `-hsl` variants** (`--color-gray-50-hsl` … `--color-gray-1000-hsl`). The new
  palette emits hex only — zero occurrences of `hsl` in `color-palette.css`.
- **Stops the new ramp doesn't have**: `--color-gray-{150,350,550,1000}`,
  `--color-emerald-550`, `--color-teal-550`, `--color-yellow-750`.

Plus 32 `var(--CHANGE)` placeholders — `--CHANGE: red` is declared at the top of the
file — covering every focus ring (11 of them), `--header-height`, `--global-sidebar-width`,
the status label colours, custom titlebar colours, the disclosure and arrow transforms,
and a run of layout widths.

The generated half of that (the `-hsl` variants, the missing stops, the button family)
is mechanical. The `--CHANGE` half needs design decisions that are Craft's to make.
Worth raising with Craft either way.

Worth knowing: 189 legacy Twig templates and ~14.3k lines of legacy SCSS still render
most of the CP, against 41 Inertia pages. `ThemeAsset` survives as
`CraftCms\Cms\View\LegacyAssets\ThemeAsset`, marked `@deprecated @internal`, still
serving the same 100-line `cp.css` it served in Craft 5 — which references zero new
tokens.

## What Cast would own, and what it would inherit

| | Craft 6 | Cast |
| --- | --- | --- |
| Light ramp | ✅ | — |
| Dark ramp | ✅ | — |
| Contrast guarantees | ✅ structural | — |
| Static/theme-independent colours | ✅ | — |
| Semantic token layer | ✅ | — |
| Legacy variable bridge | ⚠️ partial | patch the gaps, or wait |
| Stamping the mode | ❌ | ✅ |
| Per-user preference | ❌ | ✅ |
| Site-wide default | ❌ | ✅ |
| Auto / OS pairing | ❌ | ✅ |
| Flash-free application | ❌ | ✅ (blocked, see below) |
| Modes beyond light/dark | ❌ | ✅ |
| Third-party theme registration | ❌ | ✅ |
| Button colour | ❌ | ✅ (needs re-basing) |

The shape of the plugin changes. Today Cast is mostly a palette that happens to have a
picker attached. On 6.x it is mostly a picker that happens to generate palettes.

## How a theme reaches inside a component

Components don't author against colours. They author against nine **generic** properties —
`--c-color-{fill,border,on}-{loud,normal,quiet}` — and a `variant` attribute on the host
remaps those nine onto a semantic group, in `styles/variants.styles.ts`. The groups
themselves live in the generated `colorable.css`: 486 properties at `:root`, being 21 hues
and six semantics at nine each.

All of that is declared outside the shadow root and inherits inward. So a plugin
stylesheet does reach into every component, for colour — override
`--c-color-danger-fill-loud` at `:root` and every danger surface follows, component or
not. `colorable.css` carries no dark block of its own; it resolves through `--color-*`,
which flips under `[data-theme='dark']`, so it adapts for free.

That is the entry point a 6.x Cast would theme through, and it's a much better one than
selectors: 486 properties, declared in one generated file, covering surfaces Cast can't
currently reach at all.

The system is early, though. Four of 58 components import `variants.styles.ts`; six
declare a `variant` property. `static-variants.styles.ts` is imported by nothing.
And the `default` → `neutral` rename from #18791 landed in `constants/variants.ts` but not
in `variants.styles.ts`, which still keys on `[variant~='default']` — so that block matches
nothing, and `variant="neutral"` gets no mapping. Callout hides it with `:host` fallbacks
to the neutral group. The Storybook page documents the intended system rather than the
built one; read the source alongside it.

## Two structural problems

**Shadow DOM, for everything that isn't colour.** 58 Lit components with constructed
stylesheets in shadow roots. Custom properties inherit in, as above — but nothing else
does, so any patch needing an actual selector is out of reach. Cast currently carries
~206 selector-based patches:

| File | Rule blocks | Selector patches |
| --- | --- | --- |
| `_dark-base.css` | 143 | 141 |
| `_shared.css` | 18 | 18 |
| `high-contrast.css` | 18 | 16 |
| `high-contrast-dark.css` | 18 | 16 |
| `stone.css` | 11 | 9 |
| `_stock.css` | 4 | 4 |
| `dim.css` | 3 | 2 |
| `stone-dark.css` | 2 | 0 |
| `dark.css` | 0 | 0 |

Any of those targeting something now shipping as a component — `combobox` (selectize),
`select`, `input`, `checkbox`, `radio`, `slide-picker` (range inputs), `tab`, `tabs`,
`dialog`, `popover`, `tooltip`, `card`, `chip`, `badge`, `pane`, `nav-item`, `status` —
needs to become a token override or go. There is no selector that reaches inside, and if
Craft exposes neither a token nor a `::part()` for something, the answer is to file for
one.

The audit is worth doing early, and it should sort the 206 into three piles: colour, which
the token layer absorbs; geometry and layout, which needs a `::part()` or an upstream fix;
and rules that were only ever working around a Craft literal, which the compat layer may
already have retired.

**No inline head hook on the new shell.** `Plugins::addStyle()` and `addScript()` take
URLs only; `getAssetsHtml()` emits `<link>` and `<script src … defer>`. A deferred script
cannot stamp `data-theme` before first paint, so the anti-FOUC technique in
`Plugin::bootstrapJs()` has no equivalent in `resources/views/app.blade.php`. The Twig
shell still has `{% hook "cp.layouts.base" %}` and `head()`, so the legacy half is fine.

Flash-free is currently an unconditional claim in the README. It cannot be made on
Inertia pages as things stand. Worth raising upstream now — a way to contribute raw head
HTML, or a first-class `data-theme` the plugin can set server-side, would solve it.

## What gets cheaper

The README's central caveat — that the expense isn't light versus dark but how far you
move the hue — largely goes away on Craft-6-native surfaces. Everything derives from
`--color-*`, so a retint is a set of colour keys through Leonardo rather than nine hand-found
patch rules. `stone.css` as a worked example of pinning Craft's stray blues stops being
necessary there.

It persists on the legacy half wherever Craft still writes literals. Re-audit rather than
assume: `_compat.scss` may already have caught most of them.

**Button colour is the clearest win.** `button.styles.ts` derives its active state as
`hsl(from var(--c-color-fill-loud) h s calc(l - 10))` — relative colour syntax off the
token — and takes its label from the ramp's foreground stop, where contrast is structural.
The README currently has to explain that each of the nine colours is shaded per mode so
the white label clears AA, with Amber a knowing compromise at 3.19:1. On 6.x that becomes
picking a hue. And `colorable.css` already generates 21 of them behind
`[data-color='…']`, so Cast's nine are a subset of something Craft ships.

Concretely:

- **Dim** becomes a ratio array with a compressed range, not 14 hand-set ramp steps.
- **Stone / Stone Dark** become a colour-key change. The nine patch rules each were for
  blues written past the ramp; those now route through the palette.
- **High Contrast** stops being hand-tuning and becomes a ratio bump — arguably the
  single biggest win, since it's the mode where hand-tuning is least defensible.
- **A theme in `cast-themes/`** could plausibly ship as colour keys plus ratios rather
  than CSS, with Cast generating the stylesheet. That's a bigger design question and
  probably not for the first 6.x release, but the door is open in a way it wasn't.

## Watch list

- Token names are pre-1.0 and already drifting. `cp.css` reaches for `--c-bg-body` and
  `--c-fg-text`; `tokens.css` says surface and text tokens replaced both. Don't build
  against these names until they settle.
- Whether Craft ships a native toggle. Nothing announced — no issue or PR proposes one —
  but they built a dark palette for a reason. If it lands, Cast's headline moves from
  "dark mode for the CP" to "more modes, plus a theme API", which is a smaller but still
  real product. Better to position for that than be caught by it.
- Whether `--CHANGE` and the 32 dangling references get resolved before 6.0, or whether a
  plugin has to carry them.
- Whether the Inertia shell grows a head hook.
- The Leonardo dependency (`@adobe/leonardo-contrast-colors`) is build-time only in
  Craft's setup. If Cast generates ramps it needs the same, or to ship pre-generated CSS.
