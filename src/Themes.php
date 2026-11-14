<?php

declare(strict_types=1);

namespace bensomething\cast;

use bensomething\cast\Events\ThemesResolving;
use CraftCms\Aliases\Aliases;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Support\Str;

use function CraftCms\Cms\t;

/**
 * The appearances Cast offers for each of Craft's modes, and the contrast switch.
 *
 * Craft owns the mode: Light, Dark, or System following the OS. Cast picks what each
 * mode looks like. An empty handle is Craft's own appearance for that mode, so "Dark"
 * is simply Craft's dark. Increased contrast is separate from both, and sits on top of
 * the appearance in whichever mode is showing.
 *
 * Themes come from three places, in order: the bundled ones, stylesheets in the themes
 * folder, and {@see ThemesResolving} listeners, which see the first two and can add,
 * replace or remove. A handle the bundled themes use can't be taken by a folder file.
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

    /** How much of a folder theme is read for its header. */
    private const int HEADER_BYTES = 8192;

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

    /** @var array<string, Theme>|null Resolved once per request. */
    private ?array $themes = null;

    /** @var list<string> Folder stylesheets passed over, by filename, with why. */
    private array $skipped = [];

    public function __construct(private readonly Settings $settings) {}

    /**
     * Every theme, by handle.
     *
     * @return array<string, Theme>
     */
    public function all(): array
    {
        if ($this->themes !== null) {
            return $this->themes;
        }

        // Bundled first, so a folder file can't take one of their handles.
        event($event = new ThemesResolving($this->bundled() + $this->folderThemes()));

        // Keyed afresh by handle, so a listener appending with `[]` still lands right.
        $themes = [];

        foreach ($event->themes as $theme) {
            // A listener can put anything in the array, whatever its type says.
            // @phpstan-ignore instanceof.alwaysTrue
            if ($theme instanceof Theme && $theme->isValid()) {
                $themes[$theme->handle] = $theme;
            }
        }

        return $this->themes = $themes;
    }

    /**
     * The handles a scheme can take, Craft's own (empty) first: what the site defaults
     * may be set to. Users are offered {@see offered()}, which can be fewer.
     *
     * @return list<string>
     */
    public function handles(string $scheme): array
    {
        return ['', ...array_keys(array_filter($this->all(), fn (Theme $theme) => $theme->dresses($scheme)))];
    }

    /**
     * The handles a user may pick for a scheme: those the site has enabled, plus Craft's
     * own and the site default, which are always available.
     *
     * @return list<string>
     */
    public function offered(string $scheme): array
    {
        $default = $this->siteDefault($scheme);

        return array_values(array_filter(
            $this->handles($scheme),
            fn (string $handle) => $handle === '' || $handle === $default || $this->isEnabled($handle),
        ));
    }

    /** Whether the site offers a theme to users, per the Available Themes setting. */
    public function isEnabled(string $handle): bool
    {
        $enabled = $this->settings->enabledThemes;

        // Nothing ticked means All, as validation saves it.
        return $enabled === '*' || $enabled === '' || $enabled === []
            || (is_array($enabled) && in_array($handle, $enabled, true));
    }

    /**
     * The stylesheets every CP page loads besides the bundled ones, by theme handle:
     * those registered with a URL, and folder themes, which Cast serves itself.
     *
     * @return array<string, string>
     */
    public function stylesheets(): array
    {
        $urls = [];

        foreach ($this->all() as $handle => $theme) {
            if ($theme->url !== null) {
                $urls[$handle] = $theme->url;
            } elseif ($theme->path !== null && is_file($theme->path)) {
                // The hash changes when the file does, so it can be cached for good.
                $urls[$handle] = Url::cpUrl("cast/themes/$handle.css", [
                    'v' => substr(md5(filemtime($theme->path).':'.filesize($theme->path)), 0, 10),
                ]);
            }
        }

        return $urls;
    }

    /**
     * Folder stylesheets that aren't offered, with why, for the settings screen.
     *
     * @return list<string>
     */
    public function skippedFiles(): array
    {
        $this->all();

        return $this->skipped;
    }

    /** The themes folder as an absolute path, or null if it doesn't resolve. */
    public function themesPath(): ?string
    {
        $path = Aliases::get($this->settings->themesPath, false);

        return is_string($path) && $path !== '' ? rtrim($path, '/') : null;
    }

    /** @return array<string, Theme> */
    private function bundled(): array
    {
        return [
            'stone' => new Theme('stone', t('Stone', category: 'cast'), [self::SCHEME_LIGHT, self::SCHEME_DARK]),
            'dim' => new Theme('dim', t('Dim', category: 'cast'), [self::SCHEME_DARK]),
        ];
    }

    /**
     * Stylesheets in the themes folder. The filename is the handle, and a header comment
     * names the theme and the modes it dresses:
     *
     * ```css
     * /**
     *  * Theme Name: Midnight
     *  * Modes: dark
     *  * Description: Deep blue, for late nights.
     *  *\/
     * ```
     *
     * `Modes` is required (`light`, `dark`, or `light, dark`): it's what tells a 2.x theme
     * from a 1.x one, whose Craft 5 variables Craft 6 no longer reads. Files starting
     * with an underscore are partials and skipped quietly.
     *
     * @return array<string, Theme>
     */
    private function folderThemes(): array
    {
        $this->skipped = [];
        $dir = $this->themesPath();

        if ($dir === null || ! is_dir($dir)) {
            return [];
        }

        $themes = [];

        foreach (glob("$dir/*.css") ?: [] as $file) {
            $handle = basename($file, '.css');

            if (str_starts_with($handle, '_')) {
                continue;
            }

            $header = $this->parseHeader($file);
            $modes = preg_split('/[\s,]+/', strtolower($header['modes'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $theme = new Theme(
                handle: $handle,
                name: $header['theme name'] ?? $header['name'] ?? Str::headline($handle),
                modes: array_values(array_unique($modes)),
                path: $file,
                description: $header['description'] ?? null,
            );

            if ($modes === []) {
                $this->skipped[] = t('{file}: no `Modes` in its header. A Cast 1.x theme needs rewriting for Craft 6.', ['file' => basename($file)], 'cast');
            } elseif (! $theme->isValid()) {
                $this->skipped[] = t('{file}: its filename isn’t a valid handle (lower-case letters, numbers and dashes, starting with a letter), or its modes aren’t `light` and `dark`.', ['file' => basename($file)], 'cast');
            } else {
                $themes[$handle] = $theme;
            }
        }

        return $themes;
    }

    /**
     * The `Key: value` lines of a stylesheet's first comment, keys lower-cased.
     *
     * @return array<string, string>
     */
    private function parseHeader(string $file): array
    {
        $chunk = (string) @file_get_contents($file, length: self::HEADER_BYTES);

        if (! preg_match('#/\*(.*?)\*/#s', $chunk, $comment)) {
            return [];
        }

        $header = [];

        foreach (preg_split('/\R/', $comment[1]) ?: [] as $line) {
            // Leading asterisks are decoration in a docblock-style header.
            if (preg_match('/^([a-z][a-z ]*):\s*(\S.*)$/i', ltrim($line, " \t*"), $field)) {
                $header[strtolower(trim($field[1]))] = trim($field[2]);
            }
        }

        return $header;
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

        foreach ($this->offered($scheme) as $handle) {
            if ($handle !== '' || $default !== '') {
                $options[] = ['value' => $handle, 'label' => $this->name($handle)];
            }
        }

        return $options;
    }

    public function name(string $handle): string
    {
        return $handle === ''
            ? t('Craft default', category: 'cast')
            : ($this->all()[$handle]->name ?? $handle);
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

    /** Whether a user may pick a handle for a scheme: one of {@see offered()}. */
    public function isValid(string $handle, string $scheme): bool
    {
        return in_array($this->normalize($handle), $this->offered($scheme), true);
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
