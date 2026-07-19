<?php

namespace bensomething\cast\models;

use craft\base\Model;

class Settings extends Model
{
    /** Pseudo-handle: follow the OS colour-scheme preference. */
    public const THEME_AUTO = 'auto';

    /** Pseudo-handle: leave Craft's own styling alone. */
    public const THEME_NONE = '';

    /**
     * Pseudo-handle, valid as a user preference only, meaning "use the admin default".
     * Distinct from never having chosen, which resolves the same way, so a user who has
     * picked a theme can hand control back.
     */
    public const THEME_INHERIT = 'inherit';

    /**
     * Selectable button colours, in picker order.
     *
     * Handles are Craft's own `craft\enums\Color` names, so `colorSelectField` draws each
     * swatch itself. An entry is one of:
     *
     * - `shades`: `[light, dark]` steps on the matching `--<handle>-*` ramp, with Craft's
     *   white label. Hover and active derive one and two shades darker.
     * - `values`: literal `[fill, hover, active, label]` per mode, for colours no ramp
     *   shade can express.
     *
     * Every label clears WCAG AA against its fill (>=4.5:1 light, >=4:1 dark).
     */
    public const BUTTON_COLORS = [
        'red' => ['shades' => ['600', '600']],

        // A deliberate compromise: white on amber-600 is 3.19:1, short of the 4.5:1 used
        // elsewhere here. Alternatives were worse. amber-700 clears 5:1 but is only
        // ΔE2000 16 from the default red, and yellow-700 reaches 25. amber-600 sits 24.8
        // away and keeps the white label. Revisit if Craft adds per-colour button labels.
        'amber' => ['shades' => ['600', '600']],

        'green' => ['shades' => ['700', '700']],
        'teal' => ['shades' => ['700', '700']],
        'sky' => ['shades' => ['700', '600']],
        'blue' => ['shades' => ['600', '600']],
        'violet' => ['shades' => ['600', '500']],
        'pink' => ['shades' => ['600', '600']],

        // Black and white, swapping with the mode. Neither can be a ramp shade, as Cast
        // leaves `--white`/`--black` alone. Hover and active step towards the opposite
        // end, there being nowhere to go past either extreme.
        'gray' => ['values' => [
            'light' => ['#000', '#1a1a1a', '#333', '#fff'],
            'dark' => ['#fff', '#e6e6e6', '#ccc', '#000'],
        ]],
    ];

    /** The button colour Craft ships with. */
    public const BUTTON_COLOR_DEFAULT = 'red';

    /**
     * @var string Fill for primary buttons. Craft also derives the Plugin Store cart
     * badge and the installer step dots from it, and nothing else in the CP uses it.
     */
    public string $buttonColor = self::BUTTON_COLOR_DEFAULT;

    /**
     * @var string Theme applied to users who haven't chosen one. A theme handle, `auto`,
     * or an empty string for Craft's stock appearance.
     */
    public string $defaultTheme = self::THEME_AUTO;

    /** @var bool Whether users may pick their own theme in their account preferences. */
    public bool $allowUserOverride = true;

    /**
     * @var string[]|string Theme handles offered in the picker, or `'*'` for all, the
     * value Craft's `checkboxSelectField` posts for its "All" option. The saved default
     * is always available regardless of this list.
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
     * An empty theme list snaps back to "All", because an empty list is treated as
     * unrestricted downstream. Otherwise the checkboxes would show nothing ticked while
     * every theme stayed on offer.
     */
    public function beforeValidate(): bool
    {
        if ($this->enabledThemes === [] || $this->enabledThemes === '') {
            $this->enabledThemes = '*';
        }

        return parent::beforeValidate();
    }
}
