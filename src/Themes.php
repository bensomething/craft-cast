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
 * is simply Craft's dark. Increased contrast is separate from both, and replaces the
 * appearance in whichever mode is showing.
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
     * @return string[]
     */
    public function handles(string $scheme): array
    {
        return ['', ...array_keys(array_filter(self::BUNDLED, fn (array $schemes) => in_array($scheme, $schemes, true)))];
    }

    /**
     * Options for a scheme's picker, led by a row that defers to the site default.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function options(string $scheme): array
    {
        $options = [[
            'value' => Settings::THEME_INHERIT,
            'label' => t('Site default ({theme})', ['theme' => $this->name($this->siteDefault($scheme))], 'cast'),
        ]];

        foreach ($this->handles($scheme) as $handle) {
            $options[] = ['value' => $handle, 'label' => $this->name($handle)];
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

    /** What a user's choice is stored as: a handle, or `inherit` for the site default. */
    public function storedChoice(?User $user, string $scheme): string
    {
        $chosen = $user?->getPreference($this->prefKey($scheme));

        return is_string($chosen) && $this->isValid($chosen, $scheme) ? $this->normalize($chosen) : Settings::THEME_INHERIT;
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

    private function normalize(string $handle): string
    {
        return self::LEGACY[$handle] ?? $handle;
    }
}
