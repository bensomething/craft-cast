<?php

declare(strict_types=1);

namespace bensomething\cast;

use CraftCms\Cms\User\Elements\User;

use function CraftCms\Cms\t;

/**
 * The appearances Cast offers for each of Craft's modes.
 *
 * Craft owns the mode: Light, Dark, or System following the OS. Cast picks what each
 * mode looks like. An empty handle is Craft's own appearance for that mode, so "Dark"
 * is simply Craft's dark.
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

    /** Bundled themes, by handle, and the mode each one dresses. */
    private const array BUNDLED = [
        'dim' => self::SCHEME_DARK,
    ];

    public function __construct(private readonly Settings $settings) {}

    /**
     * The handles a user may choose for a scheme, Craft's own (empty) first.
     *
     * @return string[]
     */
    public function handles(string $scheme): array
    {
        return ['', ...array_keys(array_filter(self::BUNDLED, fn (string $s) => $s === $scheme))];
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

    /**
     * 1.x's `dark` was Cast's own dark theme. On Craft 6 Craft's dark plays that part,
     * so a saved `dark` means Craft's default.
     */
    private function normalize(string $handle): string
    {
        return $handle === 'dark' ? '' : $handle;
    }
}
