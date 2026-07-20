<?php

namespace bensomething\cast;

use bensomething\cast\models\Settings;
use bensomething\cast\models\Theme;
use bensomething\cast\services\Themes;
use Craft;
use craft\base\Model;
use craft\controllers\UsersController;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\TemplateEvent;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\pluginstore\PluginStoreAsset;
use craft\web\assets\theme\ThemeAsset;
use craft\web\Controller;
use craft\web\Request;
use craft\web\View;
use yii\base\ActionEvent;
use yii\base\Event;

/**
 * Cast: colour modes for the control panel.
 *
 * Craft 5's CP is light-only but declares its palette as CSS custom properties (see
 * `craft\web\assets\theme\ThemeAsset`). A Cast theme overrides those properties,
 * scoped to `html[data-cast-theme="…"]`. A head script stamps the active handle onto
 * `<html>` and resolves "Auto" against the OS colour-scheme preference.
 *
 * @property-read Themes $themes
 * @method Settings getSettings()
 */
class Plugin extends \craft\base\Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
            'components' => ['themes' => Themes::class],
        ];
    }

    public function init(): void
    {
        parent::init();

        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            static function(RegisterTemplateRootsEvent $event) {
                $event->roots['cast'] = __DIR__ . '/templates';
            }
        );

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->attachTheme();
            $this->attachUserPreference();
        }
    }

    /**
     * Load the active theme's stylesheet(s) on every CP page render.
     *
     * Deferred to render time because the user identity isn't reliably available at
     * init, and this only publishes the resources directory on requests that render.
     */
    private function attachTheme(): void
    {
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function(TemplateEvent $event) {
                if ($event->templateMode !== View::TEMPLATE_MODE_CP) {
                    return;
                }

                $handle = $this->themes->getThemeHandleForUser();

                $this->registerThemes($this->themes->getThemesToLoad($handle), $handle);
                $this->registerButtonColor();
                $this->registerAccountMenu($handle);
                $this->ignorePluginStore($event);
            }
        );
    }

    /**
     * Register theme stylesheets and the head script that activates one of them.
     *
     * @param Theme[] $themes Stylesheets to load.
     * @param string|null $handle Theme to activate: `auto`, a handle, or an empty
     * string for Craft's stock appearance. Null loads the stylesheets without
     * activating anything, which is what the live previews need.
     */
    private function registerThemes(array $themes, ?string $handle): void
    {
        $view = Craft::$app->getView();
        $schemes = [];

        // Depending on the CP's own bundles guarantees we land after everything we're
        // overriding.
        $depends = ['depends' => [ThemeAsset::class, CpAsset::class]];

        // Dark themes are deltas on the shared dark base, so it must load exactly once
        // and first. Importing it per theme broke when two dark themes loaded together,
        // as the second import re-declared the base after the first theme's overrides.
        foreach ($themes as $theme) {
            if ($theme->getIsDark()) {
                $view->registerCssFile($this->themes->getBaseUrl() . '/themes/_dark-base.css', $depends);
                break;
            }
        }

        foreach ($themes as $theme) {
            // Themes reach here through Themes::getAllThemes(), which fills in any URL a
            // registering plugin left unset. One without a stylesheet still contributes
            // its scheme, so Auto and the head script can resolve it.
            if ($theme->url !== null) {
                $view->registerCssFile($theme->url, $depends);
            }

            $schemes[$theme->handle] = $theme->colorScheme;
        }

        // Keyed so repeat registrations on a single request collapse into one tag.
        $view->registerJs($this->bootstrapJs(), View::POS_HEAD, 'cast-bootstrap');

        $view->registerJs(
            sprintf('Cast.learn(%s);', Json::encode($schemes)),
            View::POS_HEAD,
            'cast-schemes-' . implode('-', array_keys($schemes)),
        );

        // "Auto" resolves against the configured pair, not whatever dark theme happens
        // to be loaded. The preview loads all of them.
        $settings = $this->getSettings();
        $view->registerJs(
            sprintf('Cast.auto(%s, %s);',
                Json::encode($settings->autoLightTheme),
                Json::encode($settings->autoDarkTheme),
            ),
            View::POS_HEAD,
            'cast-auto',
        );

        if ($handle === null) {
            return;
        }

        $view->registerJs(
            sprintf('Cast.apply(%s, true);', Json::encode($handle)),
            View::POS_HEAD,
            'cast-apply',
        );
    }

    /**
     * Exempt Craft's Plugin Store from the active theme.
     *
     * The Plugin Store is a Vue app carrying its own compiled Tailwind stylesheet, whose
     * greys are baked in as literal values rather than read from Craft's custom
     * properties. The inverted ramp never reaches them, so on a dark theme it renders
     * dark text on a dark canvas. `_dark-base.css` resets the region to Craft's stock
     * values; this marks it.
     *
     * Marked with a body class rather than `data-cast-ignore` on the wrapper itself,
     * which is Craft's markup with no template hook inside it. Setting the attribute
     * from JS would land only after Vue had mounted and painted.
     *
     * Keyed off the asset bundle instead of the template name: the bundle is registered
     * immediately before the template renders, and it's the thing that actually causes
     * the problem.
     */
    private function ignorePluginStore(TemplateEvent $event): void
    {
        if (!isset(Craft::$app->getView()->assetBundles[PluginStoreAsset::class])) {
            return;
        }

        // `bodyClass` is normalised by `_layouts/base.twig`, which accepts a string or an
        // array and merges its own classes in, so appending is safe either way.
        $classes = $event->variables['bodyClass'] ?? [];

        if (is_string($classes)) {
            $classes = explode(' ', $classes);
        }

        $classes[] = 'cast-ignore-plugin-store';
        $event->variables['bodyClass'] = $classes;
    }

    /**
     * Put a theme switcher in the account menu.
     *
     * Craft's account menu is hardcoded in `_layouts/cp.twig` with no hook or event, so
     * the group is placed by JS. The injection is additive and bails out if the menu
     * isn't found, so a future Craft restructure costs the switcher, not the page.
     *
     * @param string $current The handle in effect, for the check mark.
     */
    private function registerAccountMenu(string $current): void
    {
        $settings = $this->getSettings();
        $user = Craft::$app->getUser()->getIdentity();

        if (!$user || !$settings->allowUserOverride) {
            return;
        }

        // The check mark belongs against what the user chose, not `$current`, which is
        // already resolved. Otherwise "Site default" ticks whatever it resolves to.
        $chosen = $user->getPreference(Themes::PREF_KEY) ?? Settings::THEME_INHERIT;

        $view = Craft::$app->getView();
        $themes = $this->themes->getEnabledThemes();

        $options = [[
            'value' => Settings::THEME_INHERIT,
            'applies' => $settings->defaultTheme,
            'label' => Craft::t('cast', 'Site default'),
        ], [
            'value' => Settings::THEME_AUTO,
            'applies' => Settings::THEME_AUTO,
            'label' => Craft::t('cast', 'Auto'),
        ], [
            'value' => Settings::THEME_NONE,
            'applies' => Settings::THEME_NONE,
            'label' => Craft::t('cast', 'Craft Default'),
        ]];

        foreach ($themes as $theme) {
            $options[] = [
                'value' => $theme->handle,
                'applies' => $theme->handle,
                'label' => $theme->name,
            ];
        }

        $html = $view->renderTemplate('cast/_account-menu.twig', [
            'options' => $options,
            'current' => $chosen,
        ], View::TEMPLATE_MODE_CP);

        // Instant switching needs every selectable theme's stylesheet present, but
        // loading them on every CP page is wasteful. They're appended on first open.
        $urls = [$this->themes->getBaseUrl() . '/themes/_dark-base.css'];

        foreach ($themes as $theme) {
            $urls[] = $theme->url;
        }

        $view->registerJs($this->bootstrapJs(), View::POS_HEAD, 'cast-bootstrap');
        $view->registerJs(sprintf('Cast.learnStyles(%s);', Json::encode($urls)), View::POS_HEAD, 'cast-styles');
        $view->registerJs($this->accountMenuJs($html));
    }

    private function accountMenuJs(string $html): string
    {
        return sprintf(<<<'JS'
(function() {
    var menu = document.getElementById('account-menu');

    if (!menu || menu.querySelector('#cast-theme-select')) {
        return;
    }

    var group = document.createElement('div');
    group.innerHTML = %s;

    // Ahead of the last divider, so the group lands above "Sign out" even if Craft
    // adds groups of its own.
    var anchor = menu.querySelector('hr:last-of-type');

    while (group.firstChild) {
        menu.insertBefore(group.firstChild, anchor);
    }

    var trigger = document.getElementById('user-info');

    if (trigger) {
        trigger.addEventListener('mousedown', function() {
            Cast.ensureStyles();
        });
    }

    var select = menu.querySelector('#cast-theme-select');

    if (select) {
        // Clicks would otherwise reach the disclosure and close the menu mid-choice,
        // taking the open select with it.
        select.addEventListener('click', function(event) {
            event.stopPropagation();
        });

        select.addEventListener('change', function() {
            var option = select.options[select.selectedIndex];

            Cast.ensureStyles();
            Cast.apply(option.getAttribute('data-cast-applies'), true);

            Craft.sendActionRequest('POST', 'cast/preferences/save-theme', {
                data: { theme: select.value },
            }).then(function() {
                // Craft resolves some custom properties into JS at component-init time
                // (the image editor caches `--blue-500`) and renders others into markup,
                // so repainting can't reach them. Reload, unless a form would lose work.
                var dirty = Craft.cp.$confirmUnloadForms && Craft.cp.$confirmUnloadForms.length;

                if (dirty) {
                    Craft.cp.displayNotice(Craft.t('cast', 'Theme saved. Some parts of this page will catch up on your next page load.'));
                } else {
                    window.location.reload();
                }
            }).catch(function() {
                Craft.cp.displayError(Craft.t('cast', 'Couldn’t save your theme.'));
            });
        });
    }
})();
JS, Json::encode($html));
    }

    /**
     * Repoint Craft's primary-button fill at the configured button colour.
     *
     * `--bg-primary` and the three `--primary-button-*` properties are the only ones
     * Craft resolves to `--red-*` shades. `--bg-primary` also drives the Plugin Store
     * cart badge and the installer's step dots. Status colours reference `--red-600`
     * directly, so they stay red.
     *
     * Emitted inline because it must be in place before first paint. `html:root`
     * out-specifies Craft's `:root`, and the dark rule out-specifies a theme's.
     */
    private function registerButtonColor(): void
    {
        $handle = $this->getSettings()->buttonColor;

        if (!isset(Settings::BUTTON_COLORS[$handle])) {
            return;
        }

        Craft::$app->getView()->registerCss(
            sprintf(
                'html:root{%s}html[data-cast-scheme="dark"]:root{%s}%s',
                $this->buttonColorDeclarations($handle, 'light'),
                $this->buttonColorDeclarations($handle, 'dark'),
                // Craft hard-codes the split-button chevron to white, which disappears
                // when a fill needs a dark label. Tie it to the label instead.
                '.menu-toggle.btn.submit:after,.menubtn:not(.action-btn).btn.submit:after' .
                '{border-color:var(--primary-button-text-color)!important}',
            ),
            [],
            'cast-button-color',
        );
    }

    private function buttonColorDeclarations(string $handle, string $mode): string
    {
        [$fill, $hover, $active, $label] = $this->buttonColorValues($handle, $mode);

        return implode(';', [
            "--bg-primary:$fill",
            "--primary-button-bg:$fill",
            "--primary-button-bg--hover:$hover",
            "--primary-button-bg--active:$active",
            "--primary-button-text-color:$label",
        ]);
    }

    /**
     * A button colour's fill, hover, active, and label values for one mode, either
     * literal or resolved to shades of the matching Craft ramp.
     *
     * @return string[]
     */
    private function buttonColorValues(string $handle, string $mode): array
    {
        $spec = Settings::BUTTON_COLORS[$handle];

        // Black/White carries its own values, including a label colour, since it swaps
        // ends with the mode rather than sitting on a ramp.
        if (!isset($spec['shades'])) {
            return $spec['values'][$mode];
        }

        // One and two shades darker for hover and active, matching Craft's progression.
        $steps = ['400', '500', '600', '700', '800', '900'];
        $shade = $spec['shades'][$mode === 'dark' ? 1 : 0];
        $i = (int)array_search($shade, $steps, true);
        $last = count($steps) - 1;

        return [
            "var(--$handle-{$steps[$i]})",
            "var(--$handle-{$steps[min($i + 1, $last)]})",
            "var(--$handle-{$steps[min($i + 2, $last)]})",
            'var(--white)',
        ];
    }

    /**
     * Wire a colour `<select>` up as a live preview.
     *
     * Values come from PHP rather than being rebuilt in JS, so
     * {@see self::buttonColorValues()} stays the only place they're defined.
     */
    public function registerButtonColorPreview(string $selector): void
    {
        $map = [];

        foreach (array_keys(Settings::BUTTON_COLORS) as $handle) {
            $map[$handle] = [
                'light' => $this->buttonColorValues($handle, 'light'),
                'dark' => $this->buttonColorValues($handle, 'dark'),
            ];
        }

        $view = Craft::$app->getView();
        $view->registerJs($this->bootstrapJs(), View::POS_HEAD, 'cast-bootstrap');
        $view->registerJs(sprintf('Cast.learnButtonColors(%s);', Json::encode($map)), View::POS_HEAD, 'cast-button-colors');

        $this->bindPreview($selector, 'Cast.buttonColor(this.value)');
    }

    /**
     * Run a snippet whenever a control's value changes.
     *
     * Bound with jQuery, not `addEventListener`. The colour picker is a selectize,
     * which hides the real `<select>` and announces changes with a jQuery trigger that
     * native listeners don't see.
     */
    private function bindPreview(string $selector, string $js): void
    {
        Craft::$app->getView()->registerJs(
            sprintf('$(%s).on("change", function() { %s; });', Json::encode($selector), $js),
        );
    }

    /**
     * The head script that stamps the active theme onto `<html>`.
     *
     * Kept inline so it runs before any fetch. A separate request would mean a flash of
     * the stock light CP on every page load. `data-cast-scheme` rides along so themes
     * can share a base stylesheet keyed on light/dark.
     */
    private function bootstrapJs(): string
    {
        return <<<'JS'
window.Cast = {
    schemes: {},
    pair: { light: '', dark: '' },
    watching: false,
    buttonColors: {},
    buttonColorHandle: null,
    styles: [],

    learn: function(schemes) {
        for (var h in schemes) {
            this.schemes[h] = schemes[h];
        }
    },

    learnButtonColors: function(map) {
        this.buttonColors = map;
    },

    learnStyles: function(urls) {
        this.styles = urls;
    },

    // Appends any theme stylesheet the page didn't already load, so switching from the
    // account menu is instant. Idempotent.
    ensureStyles: function() {
        this.styles.forEach(function(url) {
            if (document.querySelector('link[href="' + url + '"]')) {
                return;
            }

            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = url;
            document.head.appendChild(link);
        });

        this.styles = [];
    },

    buttonColor: function(handle) {
        this.buttonColorHandle = handle;
        this.paintButtonColor();
    },

    // Inline custom properties, so they beat the stylesheet the server emitted for the
    // saved colour. Always written, never removed, because picking the default back has
    // to override a saved non-default rather than fall through to it.
    paintButtonColor: function() {
        var modes = this.buttonColors[this.buttonColorHandle];

        if (!modes) {
            return;
        }

        var root = document.documentElement;
        var scheme = root.getAttribute('data-cast-scheme') === 'dark' ? 'dark' : 'light';
        var values = modes[scheme];

        root.style.setProperty('--bg-primary', values[0]);
        root.style.setProperty('--primary-button-bg', values[0]);
        root.style.setProperty('--primary-button-bg--hover', values[1]);
        root.style.setProperty('--primary-button-bg--active', values[2]);
        root.style.setProperty('--primary-button-text-color', values[3]);
    },

    auto: function(light, dark) {
        this.pair = { light: light, dark: dark };
    },

    // Monaco (nystudio107/craft-code-editor) defaults to its light `vs` theme. Plugins
    // that embed it for settings — CKEditor's config editors, say — pass no theme at
    // all, so those follow the CP. A caller that names one is honoured as-is, which
    // leaves the Code Field plugin's own theme setting in charge of its fields.
    //
    // The property is defined ahead of the editor's own bundle, which assigns to it.
    watchMonaco: function() {
        var self = this;
        var real = null;

        var wrapped = function(elementId, fieldType, monacoOptions) {
            var args = Array.prototype.slice.call(arguments);
            args[2] = self.monacoTheme(monacoOptions);

            return real.apply(this, args);
        };

        Object.defineProperty(window, 'makeMonacoEditor', {
            configurable: true,
            get: function() {
                return real ? wrapped : undefined;
            },
            set: function(fn) {
                real = fn;
            },
        });
    },

    // Options arrive as a JSON string, so a malformed one is passed straight through
    // rather than risking an editor that never renders.
    monacoTheme: function(monacoOptions) {
        if (document.documentElement.getAttribute('data-cast-scheme') !== 'dark') {
            return monacoOptions;
        }

        var options;

        try {
            options = JSON.parse(monacoOptions || '{}');
        } catch (e) {
            return monacoOptions;
        }

        if (options.theme) {
            return monacoOptions;
        }

        options.theme = 'vs-dark';

        return JSON.stringify(options);
    },

    resolve: function(handle) {
        if (handle !== 'auto') {
            return handle;
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches
            ? this.pair.dark
            : this.pair.light;
    },

    apply: function(handle, follow) {
        var root = document.documentElement;
        var active = this.resolve(handle);

        if (active) {
            root.setAttribute('data-cast-theme', active);
            root.setAttribute('data-cast-scheme', this.schemes[active] || 'light');
        } else {
            root.removeAttribute('data-cast-theme');
            root.removeAttribute('data-cast-scheme');
        }

        // Button colours resolve per mode, so a previewed theme that flips the scheme
        // has to re-resolve. No-ops unless a colour is being previewed.
        this.paintButtonColor();

        // Only the server-chosen theme follows the OS. A preview shouldn't repaint
        // under the user mid-decision.
        if (follow && handle === 'auto' && !this.watching) {
            this.watching = true;
            var self = this;
            window.matchMedia('(prefers-color-scheme: dark)')
                .addEventListener('change', function() {
                    self.apply('auto', false);
                });
        }
    },
};

window.Cast.watchMonaco();
JS;
    }

    /**
     * Wire a `<select>` up as a live theme preview. Every enabled theme's stylesheet is
     * loaded so picking one repaints immediately rather than on save.
     */
    public function registerPreview(string $selector): void
    {
        $this->registerThemes($this->themes->getEnabledThemes(), null);

        $this->bindPreview($selector, 'Cast.apply(this.value, false)');
    }

    /**
     * Add a theme picker to Account → Preferences, and persist it.
     *
     * Craft's `users/save-preferences` action only reads the keys it knows about, so
     * ours is saved alongside it. `saveUserPreferences()` merges into what's already
     * stored, so writing just our key leaves the rest intact.
     */
    private function attachUserPreference(): void
    {
        if (!$this->getSettings()->allowUserOverride) {
            return;
        }

        Craft::$app->getView()->hook('cp.users.edit.prefs', function(array &$context) {
            // Craft only ever posts preferences for the logged-in user, so an admin
            // editing someone else would silently change their own theme.
            $user = Craft::$app->getUser()->getIdentity();

            if (!$user || (isset($context['user']) && $context['user']->id !== $user->id)) {
                return '';
            }

            $this->registerPreview('#castTheme');

            return Craft::$app->getView()->renderTemplate('cast/_prefs.twig', [
                'user' => $user,
                'themes' => $this->themes->getEnabledThemes(),
                'settings' => $this->getSettings(),
            ], View::TEMPLATE_MODE_CP);
        });

        Event::on(
            UsersController::class,
            Controller::EVENT_AFTER_ACTION,
            function(ActionEvent $event) {
                if ($event->action->id !== 'save-preferences') {
                    return;
                }

                $request = Craft::$app->getRequest();

                // A console request can't reach UsersController, so this is narrowing the
                // union Craft returns rather than a case that happens.
                if (!$request instanceof Request) {
                    return;
                }

                $handle = $request->getBodyParam(Themes::PREF_KEY);
                $user = Craft::$app->getUser()->getIdentity();

                if ($handle === null || !$user) {
                    return;
                }

                // An unknown handle would silently fall back to the default on every
                // render, so don't persist it.
                if (
                    $handle !== Settings::THEME_AUTO &&
                    $handle !== Settings::THEME_NONE &&
                    $handle !== Settings::THEME_INHERIT &&
                    !$this->themes->getThemeByHandle($handle)
                ) {
                    return;
                }

                Craft::$app->getUsers()->saveUserPreferences($user, [Themes::PREF_KEY => $handle]);
            }
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * Rendered as a full CP page rather than the default fragment so the theme picker
     * can be a proper card grid. Inputs are namespaced under `settings` to match how
     * Craft's default plugin-settings response posts them.
     */
    public function getSettingsResponse(): mixed
    {
        /** @var Controller $controller */
        $controller = Craft::$app->controller;

        $this->registerPreview('#settings-defaultTheme');
        $this->registerButtonColorPreview('#settings-buttonColor');

        $themesPath = $this->themes->getThemesPath();

        return $controller->renderTemplate('cast/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'themes' => $this->themes->getAllThemes(),
            'themeSources' => $this->themeSources(),
            'themesPath' => $this->themesPathAlias($themesPath),
            'themesPathExists' => $themesPath !== null && is_dir($themesPath),
            'ignoredFiles' => $this->themes->getIgnoredFiles(),
            'buttonColorOptions' => $this->buttonColorOptions(),
        ]);
    }

    /**
     * The themes folder written relative to the project root, for display.
     *
     * The absolute path is whatever Craft sees, which under Docker is a mount point that
     * exists nowhere on the machine reading the screen. The alias is the one form that's
     * both recognisable and what you'd paste into `config/cast.php`.
     *
     * Derived from `@root` rather than `Craft::alias()`, which walks every alias and will
     * happily match a `@web` that's empty on a console request.
     */
    private function themesPathAlias(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = FileHelper::normalizePath($path, '/');
        $root = Craft::getAlias('@root', false);
        $root = is_string($root) && $root !== '' ? FileHelper::normalizePath($root, '/') : null;

        // Outside the project, so there's nothing to relate it to. Show it as it is.
        if ($root === null || !str_starts_with($path . '/', $root . '/')) {
            return $path;
        }

        return rtrim('@root/' . trim(substr($path, strlen($root)), '/'), '/');
    }

    /**
     * A readable origin for each registered theme, keyed by handle, for the read-only
     * Themes tab.
     *
     * @return array<string, string>
     */
    private function themeSources(): array
    {
        $labels = [
            Themes::SOURCE_BUNDLED => Craft::t('cast', 'Bundled with Cast'),
            Themes::SOURCE_FOLDER => Craft::t('cast', 'Themes folder'),
            Themes::SOURCE_PLUGIN => Craft::t('cast', 'Registered in code'),
        ];

        return array_map(
            fn(Theme $theme) => $labels[$this->themes->getThemeSource($theme->handle)],
            $this->themes->getAllThemes(),
        );
    }

    /**
     * Picker options for the button colour.
     *
     * Handles are Craft's own colour names, so `colorSelectField` draws each swatch
     * itself. `red` is relabelled "Default" because it's what Craft ships, and `gray`
     * is black or white depending on mode.
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function buttonColorOptions(): array
    {
        $labels = [
            Settings::BUTTON_COLOR_DEFAULT => Craft::t('cast', 'Default'),
            // Craft's `amber` ramp, but "Orange" is what it reads as on a button.
            'amber' => Craft::t('cast', 'Orange'),
            'gray' => Craft::t('cast', 'Black/White'),
        ];

        return array_map(static fn(string $handle) => [
            'label' => $labels[$handle] ?? Craft::t('app', ucfirst($handle)),
            'value' => $handle,
        ], array_keys(Settings::BUTTON_COLORS));
    }
}
