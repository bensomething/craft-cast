# Draft: upstream issue for craftcms/cms

Not part of the plugin — a draft to file at
<https://github.com/craftcms/cms/issues>. Delete once it's filed.

Suggested title: **[6.x] `_compat.scss` references 32 undefined custom properties, plus 32 `--CHANGE` placeholders**

---

### Description

`packages/craftcms-legacy/cp/src/css/_compat.scss` maps the legacy CSS variables onto the
new `@craftcms/ui` token system, and is the first import in `craft.scss`. It's doing a lot
of load-bearing work — with it in place, the legacy control panel largely follows the
adaptive palette rather than holding its own colour values, which is a genuinely nice
result.

It looks unfinished in two distinguishable ways, and I wasn't sure whether that's known,
so: two lists below. The second half needs decisions that are yours; the first half looks
mechanical, and I'm happy to open a PR for it if that's useful.

Checked against `6.x` at `18e2816`.

### 1. Referenced but never defined (32)

Diffed every `var(--…)` reference in `_compat.scss` against every custom property
*defined* anywhere in `packages/craftcms-ui/src`, `packages/craftcms-legacy/cp/src/css`,
`packages/craftcms-legacy/theme/src`, `packages/craftcms-sass` and `resources/js`.

**The button token family (10)** — nothing in the repo declares these, so
`--button-bg`, `--primary-button-bg`, `--secondary-button-bg` and their hover, border and
text counterparts resolve to nothing:

```
--c-button-default-fill          --c-button-primary-fill
--c-button-default-fill-hover    --c-button-primary-fill-hover
--c-button-default-border        --c-button-primary-border
--c-button-default-border-hover  --c-button-primary-border-hover
--c-button-default-text          --c-button-primary-text
```

**A missing semantic (1)** — `--border-primary` maps to `--c-color-brand-border-loud`, but
`brand` isn't among the semantics in `scripts/generate-colors.js` (`neutral`, `accent`,
`info`, `success`, `warning`, `danger`). Only the static form, `--c-color-static-brand-*`,
exists.

**HSL variants of the gray ramp (14)** — `--gray-050-hsl` … `--gray-1000-hsl` map onto
`--color-gray-*-hsl`, but the generated palette emits hex only; there are no `hsl`
occurrences in `shared/color-palette.css`. Craft 5's CP used `hsl(var(--gray-XXX-hsl))`
in a number of places to vary alpha, so these have real callers.

**Stops the new ramp doesn't have (7)** — the palette runs 50–950 in eleven steps, while
the legacy ramp had intermediate stops:

```
--color-gray-150   --color-gray-350   --color-gray-550   --color-gray-1000
--color-emerald-550   --color-teal-550   --color-yellow-750
```

### 2. `--CHANGE` placeholders (32)

`--CHANGE: red` is declared at the top of the file, and 32 properties still point at it.
Grouped:

- **Focus rings (11)** — `--focus-ring`, `--focus-ring-light`, `--focus-ring-medium`,
  `--focus-ring-dark`, `--focus-ring-outset`, `--focus-ring-inner`,
  `--focus-ring-inner-light`, `--light-focus-ring`, `--medium-focus-ring`,
  `--dark-focus-ring`, `--inner-focus-ring`. These are composite `box-shadow` values, so
  they can't map onto a single colour token.
- **Layout (7)** — `--header-height`, `--global-sidebar-width`, `--details-width`,
  `--heading-width`, `--page-title-columns`, `--size-main-content`, `--width`
- **Status labels (2)** — `--status-label-bg-color`, `--status-label-text-color`
- **Customisable sources (3)** — `--custom-titlebar-bg-color`,
  `--custom-sel-titlebar-bg-color`, `--custom-sel-tab-shadow-color`
- **Transforms and arrow geometry (8)** — `--disclosure-icon-transform`,
  `--disclosure-icon-transform-active`, `--arrow-angle`, `--arrow-c`, `--arrow-height`,
  `--arrow-width`, `--background-position-x`, `--background-position-y`
- **Misc (1)** — `--max-lines`

The geometry and transform ones look like they were swept up by whatever generated the
file rather than genuinely needing a token — several are set per-component at the point of
use, so they may just want removing from the compat layer.

### Why it matters from outside

I maintain [Cast](https://github.com/bensomething/craft-cast), a colour-modes plugin for
the 5.x CP, and I've been working out what a 6.x version looks like. The compat layer is
the difference between a plugin re-declaring a few hundred properties and one setting a
single attribute — so it'd be good to know whether these gaps are on the list, or whether
a plugin should expect to carry them.

### Steps to reproduce

```
git clone --depth 1 --branch 6.x https://github.com/craftcms/cms.git

# 31 — the 32nd, --global-sidebar-width, has its value on the following line
grep -c 'var(--CHANGE)' cms/packages/craftcms-legacy/cp/src/css/_compat.scss

# 0 — the generated palette emits hex only
grep -c 'hsl' cms/packages/craftcms-ui/src/styles/shared/color-palette.css

# no matches, for any of the ten button tokens
grep -rn -- '--c-button-primary-fill:' cms/packages cms/src cms/resources
```

### Additional info

- Craft version: `6.x` @ `18e2816`
- Related: #18745, #18791
