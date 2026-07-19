<?php

namespace bensomething\cast\services;

use bensomething\cast\events\RegisterThemesEvent;
use bensomething\cast\models\Settings;
use bensomething\cast\models\Theme;
use bensomething\cast\Plugin;
use Craft;
use craft\elements\User;
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

    /** @var Theme[]|null */
    private ?array $themes = null;

    private ?string $baseUrl = null;

    /**
     * Every registered theme, keyed by handle. Bundled ones plus anything added via
     * {@see self::EVENT_REGISTER_THEMES}.
     *
     * @return Theme[]
     */
    public function getAllThemes(): array
    {
        if ($this->themes !== null) {
            return $this->themes;
        }

        $event = new RegisterThemesEvent(['themes' => $this->bundledThemes()]);
        $this->trigger(self::EVENT_REGISTER_THEMES, $event);

        // Resolved lazily so the resources directory is only published on requests that
        // actually theme.
        foreach ($event->themes as $theme) {
            $theme->url ??= $this->getBaseUrl() . "/themes/{$theme->handle}.css";
        }

        return $this->themes = $event->themes;
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
                'name' => Craft::t('cast', 'High contrast'),
                'description' => Craft::t('cast', 'Light, with stronger text and borders.'),
                'colorScheme' => Theme::SCHEME_LIGHT,
            ]),
            new Theme([
                'handle' => 'high-contrast-dark',
                'name' => Craft::t('cast', 'High contrast (dark)'),
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
