<?php

declare(strict_types=1);

namespace bensomething\cast;

use CraftCms\Cms\User\Elements\User;

use function CraftCms\Cms\t;

/**
 * The appearances Cast offers for each of Craft's modes, and the contrast switch.
 *
 * Craft owns the mode: Light, Dark, or System following the OS. Cast picks what each
 * mode looks like. An empty handle is Craft's own appearance for that mode, so "Dark"
 * is simply Craft's dark. Increased contrast is separate from both, and sits on top of
 * the appearance in whichever mode is showing.
 *
 * Bundled themes only for now. Folder discovery and the registration event come back
 * once the bundled set has settled on Craft 6's tokens.
 */
final class Themes
{
    public const string SCHEME_LIGHT = 'light';

    public const string SCHEME_DARK = 'dark';

    /** The user preference holding their light appearance. Shared with 1.x's Auto pair. */
    public const string PREF_LIGHT = 'castThemeLight';

    /** The user preference holding their dark appearance. Shared with 1.x's Auto pair. */
    public const string PREF_DARK = 'castThemeDark';

    /** The user preference holding their contrast switch: true, false, or unset. */
    public const string PREF_CONTRAST = 'castHighContrast';

    /** The user preference holding their button colour: a colour, `''`, or `inherit`. */
    public const string PREF_BUTTON = 'castButtonColor';

    /**
     * Colours primary buttons can take besides Craft's own, which is blue. All but `red`
     * are Craft 6 palette hues, so fill, border and label come from Craft's generated
     * tokens, which hold the label at 4.6:1 or better in both modes. `red` is Craft Red,
     * Craft 5's primary button, as 1.x's `red` always was.
     */
    public const array BUTTON_COLORS = ['red', 'amber', 'green', 'teal', 'sky', 'violet', 'pink', 'gray'];

    /** Craft 5's primary button fill, which the picker shows Craft Red as. */
    public const string CRAFT_RED = '#dc2626';

    /** The colour Craft's own primary buttons are, as a palette name. */
    public const string CRAFT_BUTTON_COLOR = 'blue';

    /** Bundled themes, by handle, and the modes each one can dress. */
    private const array BUNDLED = [
        'stone' => [self::SCHEME_LIGHT, self::SCHEME_DARK],
        'dim' => [self::SCHEME_DARK],
    ];

    /**
     * Handles 1.x stored that mean something else here. Its `dark` was Cast's own dark,
     * which Craft's now plays; `stone-dark` is Stone offered for dark; and the two high
     * contrast themes are the contrast switch now, so as an appearance they're Craft's.
     */
    private const array LEGACY = [
        'dark' => '',
        'stone-dark' => 'stone',
        'high-contrast' => '',
        'high-contrast-dark' => '',
    ];

    public function __construct(private readonly Settings $settings) {}

    /**
     * The handles a user may choose for a scheme, Craft's own (empty) first.
     *
     * @return list<string>
     */
    public function handles(string $scheme): array
    {
        return self::handlesFor($scheme);
    }

    /**
     * {@see handles()}, for callers that can't take the service, such as the settings'
     * own validation, which the service depends on.
     *
     * @return list<string>
     */
    public static function handlesFor(string $scheme): array
    {
        return ['', ...array_keys(array_filter(self::BUNDLED, fn (array $schemes) => in_array($scheme, $schemes, true)))];
    }

    /**
     * Options for a scheme's picker, led by a row that defers to the site default.
     *
     * While the site default is Craft's own, that row is simply "Craft default": a
     * separate one would do the same thing. It still stores `inherit`, so an admin who
     * later sets a default reaches everyone who never chose otherwise.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function options(string $scheme): array
    {
        $default = $this->siteDefault($scheme);

        $options = [[
            'value' => Settings::THEME_INHERIT,
            'label' => $default === ''
                ? $this->name('')
                : t('Site default ({theme})', ['theme' => $this->name($default)], 'cast'),
        ]];

        foreach ($this->handles($scheme) as $handle) {
            if ($handle !== '' || $default !== '') {
                $options[] = ['value' => $handle, 'label' => $this->name($handle)];
            }
        }

        return $options;
    }

    public function name(string $handle): string
    {
        return match ($handle) {
            '' => t('Craft default', category: 'cast'),
            'stone' => t('Stone', category: 'cast'),
            'dim' => t('Dim', category: 'cast'),
            default => $handle,
        };
    }

    /** The appearance a user gets for a scheme: their own choice, or the site default. */
    public function forUser(?User $user, string $scheme): string
    {
        $chosen = $user?->getPreference($this->prefKey($scheme));

        if ($this->settings->allowUserOverride && is_string($chosen) && $this->isValid($chosen, $scheme)) {
            return $this->normalize($chosen);
        }

        return $this->siteDefault($scheme);
    }

    /**
     * The option a user's picker should show as chosen: a handle, or `inherit` for the
     * site default. A stored Craft default shows as `inherit` while the two are one row.
     */
    public function storedChoice(?User $user, string $scheme): string
    {
        $chosen = $user?->getPreference($this->prefKey($scheme));

        if (! is_string($chosen) || ! $this->isValid($chosen, $scheme)) {
            return Settings::THEME_INHERIT;
        }

        $chosen = $this->normalize($chosen);

        return $chosen === '' && $this->siteDefault($scheme) === '' ? Settings::THEME_INHERIT : $chosen;
    }

    public function isValid(string $handle, string $scheme): bool
    {
        return in_array($this->normalize($handle), $this->handles($scheme), true);
    }

    public function siteDefault(string $scheme): string
    {
        $handle = $this->normalize($scheme === self::SCHEME_DARK ? $this->settings->autoDarkTheme : $this->settings->autoLightTheme);

        return in_array($handle, $this->handles($scheme), true) ? $handle : '';
    }

    public function prefKey(string $scheme): string
    {
        return $scheme === self::SCHEME_DARK ? self::PREF_DARK : self::PREF_LIGHT;
    }

    /** Whether a user gets increased contrast: their own switch, or the site default. */
    public function highContrastForUser(?User $user): bool
    {
        $chosen = $user?->getPreference(self::PREF_CONTRAST);

        if ($this->settings->allowUserOverride && is_bool($chosen)) {
            return $chosen;
        }

        return $this->settings->highContrast;
    }

    public function isValidButtonColor(string $color): bool
    {
        return $color === '' || in_array($color, self::BUTTON_COLORS, true);
    }

    /** The site's default button colour: one of {@see BUTTON_COLORS}, or `''` for Craft's. */
    public function siteButtonColor(): string
    {
        $color = $this->settings->defaultButtonColor;

        return $this->isValidButtonColor($color) ? $color : '';
    }

    /** The button colour a user gets: their own choice, or the site default. */
    public function buttonColorForUser(?User $user): string
    {
        $chosen = $user?->getPreference(self::PREF_BUTTON);

        if ($this->settings->allowUserOverride && is_string($chosen) && $this->isValidButtonColor($chosen)) {
            return $chosen;
        }

        return $this->siteButtonColor();
    }

    /**
     * The picker's value for a colour: its palette name, except Craft Red, which isn't a
     * Craft 6 palette colour and goes by its hex.
     */
    public function pickerValue(string $color): string
    {
        return $color === 'red' ? self::CRAFT_RED : $color;
    }

    public function buttonColorName(string $color): string
    {
        return match ($color) {
            'red' => t('Craft Red', category: 'cast'),
            default => t(ucfirst($color), category: 'cast'),
        };
    }

    /**
     * The colours the picker offers, in order, with Craft's own first, and labels for
     * those that carry one: Craft's own, Craft Red, and the site default when it's
     * another colour.
     *
     * The site default's colour stands for following the site default, so a user can
     * hand control back without a separate row; see `_prefs.js`.
     *
     * @return array{colors: string[], labels: array<string, string>}
     */
    public function buttonColorOptions(): array
    {
        $labels = [
            self::CRAFT_BUTTON_COLOR => t('Blue (Craft default)', category: 'cast'),
            self::CRAFT_RED => $this->buttonColorName('red'),
        ];

        $default = $this->siteButtonColor();

        if ($default !== '') {
            $labels[$this->pickerValue($default)] = t('{color} (site default)', ['color' => $this->buttonColorName($default)], 'cast');
        }

        return [
            'colors' => array_map($this->pickerValue(...), [self::CRAFT_BUTTON_COLOR, ...self::BUTTON_COLORS]),
            'labels' => $labels,
        ];
    }

    private function normalize(string $handle): string
    {
        return self::normalizeHandle($handle);
    }

    /** A handle as 2.x reads it, 1.x's handles translated. */
    public static function normalizeHandle(string $handle): string
    {
        return self::LEGACY[$handle] ?? $handle;
    }
}
