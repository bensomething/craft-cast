<?php

namespace bensomething\cast\models;

use craft\base\Model;

class Settings extends Model
{
    /** Pseudo-handle meaning "follow the operating system's colour-scheme preference". */
    public const THEME_AUTO = 'auto';

    /** Pseudo-handle meaning "leave Craft's own styling alone". */
    public const THEME_NONE = '';

    /**
     * Pseudo-handle, valid as a *user* preference only, meaning "whatever the admin
     * has set as the default". Distinct from never having chosen (which resolves the
     * same way) so that a user who has picked a theme can hand control back.
     */
    public const THEME_INHERIT = 'inherit';

    /**
     * Selectable button colours, in picker order.
     *
     * Handles are Craft's own `craft\enums\Color` names, so `colorSelectField` draws
     * each swatch itself. An entry is one of:
     *
     * - `shades`: `[light, dark]` steps on the matching `--<handle>-*` ramp, carrying
     *   Craft's white label. Hover and active derive one and two shades darker.
     * - `values`: literal `[fill, hover, active, label]` per mode, for colours no ramp
     *   shade can express.
     *
     * Every option is picked so its label clears WCAG AA against its fill (≥4.5:1 in
     * light, ≥4:1 in dark) and the fill stays distinct from a dark pane.
     */
    public const BUTTON_COLORS = [
        'red' => ['shades' => ['600', '600']],

        // The one warm option, and the one deliberate accessibility compromise in the
        // palette: white on amber-600 is 3.19:1, short of the 4.5:1 this file otherwise
        // holds to. The alternatives were worse. Warm hues have to fall to about L*45
        // to carry a white label, and by then they've lost the chroma that separates
        // them from red — amber-700 clears 5:1 but sits ΔE2000 16 from the default,
        // the closest of anything tested, and yellow-700 only reaches 25. Amber-600 is
        // the compromise: 24.8 away, white label kept. Chosen knowingly; revisit if
        // Craft ever gives buttons a per-colour label.
        'amber' => ['shades' => ['600', '600']],

        'green' => ['shades' => ['700', '700']],
        'teal' => ['shades' => ['700', '700']],
        'sky' => ['shades' => ['700', '600']],
        'blue' => ['shades' => ['600', '600']],
        'violet' => ['shades' => ['600', '500']],
        'pink' => ['shades' => ['600', '600']],

        // Black and white, swapping with the mode. Neither can be a ramp shade: Cast
        // deliberately leaves `--white`/`--black` alone, so nothing in the palette
        // inverts to order. Hover and active step towards the opposite end, since
        // there's nowhere further to go past either extreme.
        'gray' => ['values' => [
            'light' => ['#000', '#1a1a1a', '#333', '#fff'],
            'dark' => ['#fff', '#e6e6e6', '#ccc', '#000'],
        ]],
    ];

    /** The button colour Craft ships with. */
    public const BUTTON_COLOR_DEFAULT = 'red';

    /**
     * @var string Fill for primary buttons. Craft also derives the Plugin Store's cart
     * badge and the installer's step dots from it; nothing else in the CP uses it.
     */
    public string $buttonColor = self::BUTTON_COLOR_DEFAULT;

    /**
     * @var string The theme applied to users who haven't chosen one — a theme handle,
     * `auto`, or an empty string for Craft's stock appearance.
     */
    public string $defaultTheme = self::THEME_AUTO;

    /** @var bool Whether users may pick their own theme from their account preferences. */
    public bool $allowUserOverride = true;

    /**
     * @var string[]|string Theme handles offered in the picker, or `'*'` for all
     * registered themes — the value Craft's `checkboxSelectField` posts for its "All"
     * option. The saved default is always available regardless of this list.
     */
    public array|string $enabledThemes = '*';

    /** @var string Theme used when "Auto" resolves to dark. */
    public string $autoDarkTheme = 'dark';

    /** @var string Theme used when "Auto" resolves to light. Empty = Craft's stock light CP. */
    public string $autoLightTheme = self::THEME_NONE;

    public function rules(): array
    {
        return [
            [['defaultTheme', 'autoDarkTheme', 'autoLightTheme'], 'string'],
            [['buttonColor'], 'in', 'range' => array_keys(self::BUTTON_COLORS)],
            [['allowUserOverride'], 'boolean'],
        ];
    }

    /**
     * An empty theme list snaps back to "All".
     *
     * There's no useful reading of "users may choose their own theme, from none of
     * them" — and leaving it empty would have the checkboxes show nothing ticked while
     * every theme stayed on offer, since an empty list is treated as unrestricted
     * downstream. Saving all-unticked therefore comes back all-ticked, which is at
     * least honest about what's stored.
     */
    public function beforeValidate(): bool
    {
        if ($this->enabledThemes === [] || $this->enabledThemes === '') {
            $this->enabledThemes = '*';
        }

        return parent::beforeValidate();
    }
}
