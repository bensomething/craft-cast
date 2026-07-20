<?php

namespace bensomething\cast\services;

use bensomething\cast\events\RegisterThemesEvent;
use bensomething\cast\models\Settings;
use bensomething\cast\models\Theme;
use bensomething\cast\Plugin;
use Craft;
use craft\elements\User;
use craft\helpers\App;
use yii\base\Component;

/**
 * Registry of available control-panel colour modes, and the rules for deciding which
 * one a given user gets.
 */
class Themes extends Component
{
    /**
     * @event RegisterThemesEvent Raised when the available themes are compiled.
     *
     * ```php
     * Event::on(Themes::class, Themes::EVENT_REGISTER_THEMES, function(RegisterThemesEvent $e) {
     *     $e->themes['midnight'] = new Theme([
     *         'handle' => 'midnight',
     *         'name' => 'Midnight',
     *         'colorScheme' => Theme::SCHEME_DARK,
     *         'url' => Craft::$app->getAssetManager()->getPublishedUrl('@mymodule/themes', true, 'midnight.css'),
     *     ]);
     * });
     * ```
     */
    public const EVENT_REGISTER_THEMES = 'registerThemes';

    /** The user-preference key holding the chosen theme handle. */
    public const PREF_KEY = 'castTheme';

    /** Shipped with Cast. */
    public const SOURCE_BUNDLED = 'bundled';

    /** Found as a stylesheet in the themes folder. */
    public const SOURCE_FOLDER = 'folder';

    /** Added in code via {@see self::EVENT_REGISTER_THEMES}. */
    public const SOURCE_PLUGIN = 'plugin';

    /**
     * How much of a stylesheet to read looking for its header. The header is the first
     * thing in the file; the body can run to hundreds of declarations.
     */
    private const HEADER_BYTES = 8192;

    /** @var Theme[]|null */
    private ?array $themes = null;

    /** @var array<string, string>|null Handle => `SOURCE_*`. */
    private ?array $sources = null;

    /** @var array<int, array{file: string, reason: string}> */
    private array $ignoredFiles = [];

    private ?string $baseUrl = null;

    private ?string $themesUrl = null;

    /**
     * Every registered theme, keyed by handle. Bundled ones, whatever's in the themes
     * folder, plus anything added via {@see self::EVENT_REGISTER_THEMES}.
     *
     * @return Theme[]
     */
    public function getAllThemes(): array
    {
        if ($this->themes !== null) {
            return $this->themes;
        }

        $bundled = $this->bundledThemes();
        $discovered = $this->discoverThemes();

        // A bundled handle wins, so dropping a `dark.css` in the folder can't quietly
        // redefine Dark out from under everyone already set to it. The clash is reported
        // on the settings screen rather than swallowed.
        foreach (array_intersect_key($discovered, $bundled) as $theme) {
            $this->ignore((string)$theme->path, Craft::t('cast', 'A bundled theme already uses this handle.'));
        }

        $themes = $bundled + $discovered;

        $this->sources = array_fill_keys(array_keys($bundled), self::SOURCE_BUNDLED)
            + array_fill_keys(array_keys($themes), self::SOURCE_FOLDER);

        $event = new RegisterThemesEvent(['themes' => $themes]);
        $this->trigger(self::EVENT_REGISTER_THEMES, $event);

        foreach ($event->themes as $handle => $theme) {
            // Added by a listener, or swapped out for a different object under a handle
            // we'd already claimed.
            if (($themes[$handle] ?? null) !== $theme) {
                $this->sources[$handle] = self::SOURCE_PLUGIN;
            }

            // Resolved lazily so a directory is only published on requests that theme.
            $theme->url ??= $theme->path !== null
                ? $this->getThemesUrl() . '/' . basename($theme->path)
                : $this->getBaseUrl() . "/themes/{$theme->handle}.css";
        }

        return $this->themes = $event->themes;
    }

    /**
     * Where a theme came from, as one of the `SOURCE_*` constants.
     */
    public function getThemeSource(string $handle): string
    {
        // Guarded on the map rather than calling getAllThemes() outright, which a listener
        // asking this mid-event would recurse into.
        if ($this->sources === null) {
            $this->getAllThemes();
        }

        return $this->sources[$handle] ?? self::SOURCE_PLUGIN;
    }

    /**
     * Stylesheets in the themes folder that couldn't be registered, and why.
     *
     * Surfaced on the settings screen, because the failure mode otherwise is a file that
     * looks fine and simply never appears.
     *
     * @return array<int, array{file: string, reason: string}>
     */
    public function getIgnoredFiles(): array
    {
        if ($this->sources === null) {
            $this->getAllThemes();
        }

        return $this->ignoredFiles;
    }

    /**
     * The themes folder as an absolute path, or null if the setting doesn't resolve.
     */
    public function getThemesPath(): ?string
    {
        $path = App::parseEnv($this->settings()->themesPath);

        return is_string($path) && $path !== '' ? rtrim($path, '/\\') : null;
    }

    public function getThemeByHandle(?string $handle): ?Theme
    {
        return $handle ? ($this->getAllThemes()[$handle] ?? null) : null;
    }

    /**
     * The themes a user may choose between, honouring the Available themes setting.
     *
     * @return Theme[]
     */
    public function getEnabledThemes(): array
    {
        $enabled = $this->settings()->enabledThemes;

        if ($enabled === '*' || !$enabled) {
            return $this->getAllThemes();
        }

        $enabled = (array)$enabled;

        // The configured default stays selectable even if left off the list, otherwise
        // the picker would misrepresent what a user is actually seeing.
        $enabled[] = $this->settings()->defaultTheme;

        return array_filter(
            $this->getAllThemes(),
            static fn(Theme $theme) => in_array($theme->handle, $enabled, true),
        );
    }

    /**
     * The theme handle in effect for a user: their own choice when allowed one, otherwise
     * the site-wide default. May be `auto`, a theme handle, or an empty string for Craft's
     * stock appearance.
     */
    public function getThemeHandleForUser(?User $user = null): string
    {
        $settings = $this->settings();
        $user ??= Craft::$app->getUser()->getIdentity();

        if (!$settings->allowUserOverride || !$user) {
            return $settings->defaultTheme;
        }

        $handle = $user->getPreference(self::PREF_KEY);

        // Null means "never chose", `inherit` means "chose to follow the admin". Both
        // defer. An empty string is a deliberate "no theme".
        if ($handle === null || $handle === Settings::THEME_INHERIT) {
            return $settings->defaultTheme;
        }

        if ($handle !== Settings::THEME_AUTO && $handle !== Settings::THEME_NONE && !$this->getThemeByHandle($handle)) {
            // The chosen theme has since been uninstalled or disabled.
            return $settings->defaultTheme;
        }

        return $handle;
    }

    /**
     * The stylesheets to load for a handle: one for a concrete theme, or the light/dark
     * pair for `auto`.
     *
     * @return Theme[]
     */
    public function getThemesToLoad(string $handle): array
    {
        if ($handle !== Settings::THEME_AUTO) {
            return array_filter([$this->getThemeByHandle($handle)]);
        }

        $settings = $this->settings();

        return array_filter([
            $this->getThemeByHandle($settings->autoLightTheme),
            $this->getThemeByHandle($settings->autoDarkTheme),
        ]);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl ??= Craft::$app->getAssetManager()
            ->getPublishedUrl(dirname(__DIR__) . '/resources', true);
    }

    /**
     * Published URL of the themes folder.
     *
     * The whole folder is published in one go rather than file by file, so the hash
     * Craft derives covers every theme in it: edit any one of them and all their URLs
     * change together. Publishing also means the folder needn't sit in the web root.
     */
    private function getThemesUrl(): string
    {
        return $this->themesUrl ??= Craft::$app->getAssetManager()
            ->getPublishedUrl((string)$this->getThemesPath(), true);
    }

    /**
     * Themes found in the themes folder, keyed by handle.
     *
     * The handle is the filename and everything else comes from a header comment inside
     * the file, so there's nothing to keep in sync with what's on disk. A file that can't
     * be read as a theme is collected for {@see self::getIgnoredFiles()} rather than
     * thrown, so one bad stylesheet costs its own theme and not the control panel.
     *
     * @return Theme[]
     */
    private function discoverThemes(): array
    {
        $themes = [];
        $path = $this->getThemesPath();

        if ($path === null || !is_dir($path)) {
            return $themes;
        }

        foreach (glob($path . '/*.css') ?: [] as $file) {
            $handle = basename($file, '.css');

            // The same convention as the bundled `_dark-base.css`: a leading underscore
            // means a partial other themes build on, not a theme in its own right.
            if (str_starts_with($handle, '_')) {
                continue;
            }

            $header = $this->parseHeader($file);

            $theme = new Theme([
                'handle' => $handle,
                // Title case, so a filename-derived name sits alongside the bundled ones.
                'name' => $header['theme name'] ?? ucwords(str_replace('-', ' ', $handle)),
                'description' => $header['description'] ?? null,
                'colorScheme' => strtolower($header['color scheme'] ?? Theme::SCHEME_LIGHT),
                'path' => $file,
            ]);

            if (!$theme->validate()) {
                $this->ignore($file, implode(' ', $theme->getFirstErrors()));
                continue;
            }

            $themes[$handle] = $theme;
        }

        return $themes;
    }

    /**
     * The `Field: value` pairs from a stylesheet's first block comment, keyed lowercase.
     *
     * Deliberately lenient. A stylesheet with no header is still a theme, just one named
     * after its own filename and assumed light.
     *
     * @return array<string, string>
     */
    private function parseHeader(string $file): array
    {
        $pointer = @fopen($file, 'rb');

        if ($pointer === false) {
            return [];
        }

        $chunk = fread($pointer, self::HEADER_BYTES) ?: '';
        fclose($pointer);

        if (!preg_match('#/\*(.*?)\*/#s', $chunk, $comment)) {
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

    private function ignore(string $file, string $reason): void
    {
        $this->ignoredFiles[] = ['file' => basename($file), 'reason' => $reason];
    }

    /**
     * @return Theme[]
     */
    private function bundledThemes(): array
    {
        $themes = [
            new Theme([
                'handle' => 'dark',
                'name' => Craft::t('cast', 'Dark'),
                'description' => Craft::t('cast', 'A neutral dark control panel.'),
                'colorScheme' => Theme::SCHEME_DARK,
            ]),
            new Theme([
                'handle' => 'dim',
                'name' => Craft::t('cast', 'Dim'),
                'description' => Craft::t('cast', 'A softer, lower-contrast dark mode.'),
                'colorScheme' => Theme::SCHEME_DARK,
            ]),
            new Theme([
                'handle' => 'high-contrast',
                'name' => Craft::t('cast', 'High Contrast'),
                'description' => Craft::t('cast', 'Light, with stronger text and borders.'),
                'colorScheme' => Theme::SCHEME_LIGHT,
            ]),
            new Theme([
                'handle' => 'high-contrast-dark',
                'name' => Craft::t('cast', 'High Contrast (Dark)'),
                'description' => Craft::t('cast', 'Dark, with stronger text and borders.'),
                'colorScheme' => Theme::SCHEME_DARK,
            ]),
        ];

        return array_combine(array_map(static fn(Theme $t) => $t->handle, $themes), $themes);
    }

    private function settings(): Settings
    {
        return Plugin::getInstance()->getSettings();
    }
}
