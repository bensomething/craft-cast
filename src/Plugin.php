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
use craft\helpers\Json;
use craft\web\Controller;
use craft\web\View;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\theme\ThemeAsset;
use yii\base\ActionEvent;
use yii\base\Event;

/**
 * Cast — colour modes for the control panel.
 *
 * Craft 5's CP ships light-only, but it declares its entire palette as CSS custom
 * properties (see `craft\web\assets\theme\ThemeAsset`). A Cast theme is just a
 * stylesheet that overrides those properties, scoped to `html[data-cast-theme="…"]`.
 * The active handle is stamped onto `<html>` by a small head script, which also
 * resolves "Auto" against the OS colour-scheme preference and follows it live.
 *
 * @property-read Themes $themes
 * @method Settings getSettings()
 */
class Plugin extends \craft\base\Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

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
     * Deferred to render time rather than done at init: the identity isn't reliably
     * available that early, and this way the resources directory is only published
     * on requests that actually render a page.
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
            }
        );
    }

    /**
     * Register theme stylesheets and the head script that activates one of them.
     *
     * @param Theme[] $themes Stylesheets to load.
     * @param string|null $handle Theme to activate — `auto`, a handle, or an empty
     * string for Craft's stock appearance. Null loads the stylesheets without
     * activating anything, which is what the live preview on the settings and
     * preferences screens needs.
     */
    private function registerThemes(array $themes, ?string $handle): void
    {
        $view = Craft::$app->getView();
        $schemes = [];

        // Depending on the CP's own bundles guarantees we land after everything we're
        // overriding, whatever order they resolve in.
        $depends = ['depends' => [ThemeAsset::class, CpAsset::class]];

        // Every dark theme is a delta on the shared dark base, so the base has to load
        // exactly once and before all of them. It used to be `@import`ed by each theme
        // instead, which broke whenever two dark themes loaded together (the preview
        // screens): the second theme's import re-declared the base *after* the first
        // theme's overrides, and since both scopes have the same specificity, the base
        // won — Dim rendered as plain Dark.
        foreach ($themes as $theme) {
            if ($theme->getIsDark()) {
                $view->registerCssFile($this->themes->getBaseUrl() . '/themes/_dark-base.css', $depends);
                break;
            }
        }

        foreach ($themes as $theme) {
            $view->registerCssFile($theme->url, $depends);
            $schemes[$theme->handle] = $theme->colorScheme;
        }

        // Keyed so repeat registrations on a single request collapse into one tag.
        $view->registerJs($this->bootstrapJs(), View::POS_HEAD, 'cast-bootstrap');

        $view->registerJs(
            sprintf('Cast.learn(%s);', Json::encode($schemes)),
            View::POS_HEAD,
            'cast-schemes-' . implode('-', array_keys($schemes)),
        );

        // "Auto" resolves against the configured pair rather than whatever dark theme
        // happens to be loaded — the preview loads all of them.
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
     * Put a theme switcher in the account menu, top right.
     *
     * Craft's account menu is hardcoded in `_layouts/cp.twig` with no template hook or
     * event, so the group is placed by JS. The injection is purely additive and bails
     * out if the menu isn't found, so a future Craft restructure costs the switcher,
     * not the page.
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

        // What the user has actually chosen, which is what the check mark should sit
        // against — `$current` is the *resolved* handle, so someone on "Site default"
        // would otherwise see the tick on whatever it resolves to.
        $chosen = $user->getPreference(Themes::PREF_KEY) ?? Settings::THEME_INHERIT;

        $view = Craft::$app->getView();
        $themes = $this->themes->getEnabledThemes();

        $options = [[
            'value' => Settings::THEME_INHERIT,
            // Applying "Site default" means applying whatever it resolves to.
            'applies' => $settings->defaultTheme,
            'label' => Craft::t('cast', 'Site default'),
        ], [
            'value' => Settings::THEME_AUTO,
            'applies' => Settings::THEME_AUTO,
            'label' => Craft::t('cast', 'Auto'),
        ], [
            'value' => Settings::THEME_NONE,
            'applies' => Settings::THEME_NONE,
            'label' => Craft::t('cast', 'Craft default'),
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

        // Switching has to be instant, so every selectable theme's stylesheet needs to
        // be present — but loading all of them on every CP page to service a menu most
        // people never open is a poor trade. They're appended on first open instead.
        $urls = [$this->themes->getBaseUrl() . '/themes/_dark-base.css'];

        foreach ($themes as $theme) {
            $urls[] = $theme->url;
        }

        $view->registerJs($this->bootstrapJs(), View::POS_HEAD, 'cast-bootstrap');
        $view->registerJs(sprintf('Cast.learnStyles(%s);', Json::encode($urls)), View::POS_HEAD, 'cast-styles');
        $view->registerJs($this->accountMenuJs($html));
    }

    /**
     * The script that places the account-menu group and wires it up.
     */
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

    // Ahead of the last divider, so the group lands above "Sign out" rather than
    // under it — and still does if Craft adds groups of its own.
    var anchor = menu.querySelector('hr:last-of-type');

    while (group.firstChild) {
        menu.insertBefore(group.firstChild, anchor);
    }

    // The stylesheets are only needed once someone goes looking for the menu.
    var trigger = document.getElementById('user-info');

    if (trigger) {
        trigger.addEventListener('mousedown', function() {
            Cast.ensureStyles();
        });
    }

    var select = menu.querySelector('#cast-theme-select');

    if (select) {
        // Clicks inside the menu would otherwise reach the disclosure and close it
        // mid-choice, taking the open select with it.
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
                // Repainting can't reach everything: Craft resolves some custom
                // properties into JS at component-init time (the image editor caches
                // `--blue-500`, for one) and renders others into markup already on the
                // page. Those only re-derive on a fresh load. Reload to settle them —
                // unless a form would lose work, in which case say so and leave it.
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
     * `--bg-primary` and the three `--primary-button-*` properties are all Craft
     * resolves to shades of `--red-*`; swapping them is the whole feature. Craft also
     * derives the Plugin Store cart badge and the installer's step dots from
     * `--bg-primary`, and nothing else in the CP touches it. Status colours reference
     * `--red-600` directly, so they stay red as they should.
     *
     * Emitted inline rather than as a file: it's a handful of declarations, and a
     * stylesheet request would be a round trip for something that has to be in place
     * before first paint. `html:root` out-specifies Craft's `:root`, and the dark rule
     * out-specifies a theme's, so it wins wherever it lands in the cascade.
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
                // the moment a fill needs a dark label. Tie it to the label instead.
                '.menu-toggle.btn.submit:after,.menubtn:not(.action-btn).btn.submit:after' .
                '{border-color:var(--primary-button-text-color)!important}',
            ),
            [],
            'cast-button-color',
        );
    }

    /**
     * The declarations for one button colour in one mode.
     */
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
     * A button colour's fill, hover, active, and label values for one mode — either
     * literal, or resolved to shades of the matching Craft ramp.
     *
     * @return string[]
     */
    private function buttonColorValues(string $handle, string $mode): array
    {
        $spec = Settings::BUTTON_COLORS[$handle];

        if (isset($spec['values'])) {
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
     * Same bargain as the theme picker: a button colour is a "how does that look"
     * decision, so it shouldn't need a save to find out. The Save button on the
     * settings screen is the nearest primary button, so it's what visibly repaints.
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
     * Bound with jQuery, not `addEventListener`: the colour picker is a selectize,
     * which hides the real `<select>` and announces changes by way of a jQuery
     * trigger — which native listeners don't see.
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
     * Kept inline (rather than in a published file) so it runs before the browser has
     * to fetch anything — a separate request would mean a flash of the stock light CP
     * on every page load. `data-cast-scheme` rides along so themes can share a base
     * stylesheet keyed on light/dark.
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
    // account menu is instant. Idempotent, and cheap after the first call.
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

    // Set as inline custom properties so they beat the stylesheet the server emitted
    // for the *saved* colour. Always written, never removed: picking the default back
    // has to override a saved non-default, not just fall through to it.
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

        // Only the server-chosen theme follows the OS; a preview shouldn't repaint
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
JS;
    }

    /**
     * Wire a `<select>` up as a live theme preview: every enabled theme's stylesheet is
     * loaded, and picking one repaints the page immediately rather than on save.
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
            // editing someone else shouldn't be shown a control that would silently
            // change their own theme.
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

                $handle = Craft::$app->getRequest()->getBodyParam(Themes::PREF_KEY);
                $user = Craft::$app->getUser()->getIdentity();

                if ($handle === null || !$user) {
                    return;
                }

                // An unknown handle would silently fall back to the default on every
                // render; don't persist it.
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

        return $controller->renderTemplate('cast/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'themes' => $this->themes->getAllThemes(),
            'buttonColorOptions' => $this->buttonColorOptions(),
        ]);
    }

    /**
     * Picker options for the button colour.
     *
     * Handles are Craft's own colour names, so `colorSelectField` draws each swatch
     * itself. Two are labelled here rather than by Craft: `red` is what Craft already
     * ships, so it reads as "Default", and `gray` is black-or-white depending on mode,
     * which no single colour name describes.
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
