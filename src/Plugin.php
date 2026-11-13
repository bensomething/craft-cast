<?php

declare(strict_types=1);

namespace bensomething\cast;

use bensomething\cast\Http\Middleware\RegisterHeadScript;
use CraftCms\Cms\Cp\Enums\Theme;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\View\TemplateHooks;
use CraftCms\Cms\View\TemplateMode;
use Illuminate\Routing\Router;
use Override;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\template;

/**
 * Cast: colour modes for the control panel, Craft 6 line.
 *
 * Craft 6 owns light, dark and following the OS. Cast picks what light and dark each
 * look like: Craft's own, or one of Cast's themes, which override Craft's colour tokens
 * while Craft's mode matches. See `docs/craft-6.md` for how 2.x got here.
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '2.0.0';

    /**
     * Every theme loads on every CP page. They're small and scoped to their own
     * attribute, and it's what lets a change repaint without a reload. Appearances stand
     * down under high contrast by selector, so order doesn't decide between them.
     */
    protected array $styles = [
        __DIR__.'/../resources/themes/stone.css' => 'themes/stone.css',
        __DIR__.'/../resources/themes/dim.css' => 'themes/dim.css',
        __DIR__.'/../resources/themes/high-contrast.css' => 'themes/high-contrast.css',
        __DIR__.'/../resources/themes/buttons.css' => 'themes/buttons.css',
    ];

    public function boot(Router $router, TemplateHooks $hooks): void
    {
        $this->app->bind(Settings::class, fn () => $this->getSettings());
        $this->app->scoped(Themes::class);

        $router->pushMiddlewareToGroup('craft.cp', RegisterHeadScript::class);

        $hooks->register('cp.users.edit.prefs', fn (array &$context) => $this->preferencesHtml());
    }

    #[Override]
    protected static function createSettings(): ?PluginSettings
    {
        return new Settings;
    }

    /** The appearance and button colour fields beside Craft's Theme field, and the contrast switch. */
    private function preferencesHtml(): string
    {
        /** @var Settings $settings */
        $settings = $this->getSettings();

        if (! $settings->allowUserOverride) {
            return '';
        }

        $themes = $this->app->make(Themes::class);
        $user = currentUserElement();

        return template('cast/_prefs.twig', [
            'lightOptions' => $themes->options(Themes::SCHEME_LIGHT),
            'darkOptions' => $themes->options(Themes::SCHEME_DARK),
            'lightValue' => $themes->storedChoice($user, Themes::SCHEME_LIGHT),
            'darkValue' => $themes->storedChoice($user, Themes::SCHEME_DARK),
            'lightDefault' => $themes->siteDefault(Themes::SCHEME_LIGHT),
            'darkDefault' => $themes->siteDefault(Themes::SCHEME_DARK),
            'highContrast' => $themes->highContrastForUser($user),
            'craftTheme' => Theme::forUser($user)->value,
            'buttonOptions' => $themes->buttonColorOptions(),
            // The swatch shown as chosen: Craft's own reads as its blue.
            'buttonValue' => $themes->pickerValue($themes->buttonColorForUser($user) ?: Themes::CRAFT_BUTTON_COLOR),
            // The picker's values that aren't colour names, back to the names Cast stores.
            'buttonHandles' => [Themes::CRAFT_RED => 'red'],
            'buttonDefault' => $themes->siteButtonColor(),
            'saveUrl' => Url::actionUrl('cast/preferences'),
            'csrfToken' => csrf_token(),
        ], templateMode: TemplateMode::Cp);
    }
}
