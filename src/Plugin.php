<?php

declare(strict_types=1);

namespace bensomething\cast;

use bensomething\cast\Http\Middleware\RegisterHeadScript;
use CraftCms\Cms\Cp\Enums\Theme;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Enums\FieldWidth;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\View\TemplateHooks;
use CraftCms\Cms\View\TemplateMode;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Override;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

/**
 * Cast: colour modes for the control panel, Craft 6 line.
 *
 * Craft 6 owns light, dark and following the OS. Cast picks what light and dark each
 * look like: Craft's own, or one of Cast's themes, which override Craft's colour tokens
 * while Craft's mode matches. See `docs/craft-6.md` for how 2.x got here.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '2.0.0';

    public bool $hasCpSettings = true;

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

    /**
     * Bound here rather than in `boot()`: Craft loads a plugin's route files before
     * `boot()` runs, so anything they resolve has to be bound already.
     */
    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->singleton(Settings::class, fn () => $this->getSettings());
        $this->app->scoped(Themes::class);
    }

    public function boot(Router $router, TemplateHooks $hooks): void
    {

        $router->pushMiddlewareToGroup('craft.cp', RegisterHeadScript::class);

        $hooks->register('cp.users.edit.prefs', fn (array &$context) => $this->preferencesHtml());
    }

    #[Override]
    protected static function createSettings(): ?PluginSettings
    {
        return new Settings;
    }

    /**
     * The site-wide defaults behind each user's own choices. Laid out like the fields
     * they're defaults for: the two appearances and the button colour on one row, as
     * they sit beside Theme in each user's preferences. That row's instructions go
     * under the inputs, so its selects line up however their text wraps; the switches
     * keep theirs above, as Craft's own do.
     *
     * A setting `config/craft/cast.php` sets is read-only here, with a warning naming
     * the file: Craft applies the file over the saved value, and leaves that key alone
     * when this screen saves, so editing it here would appear to save and do nothing.
     */
    #[Override]
    public function settingsUi(UiContext $context = new UiContext): ?Ui
    {
        $themes = $this->app->make(Themes::class);

        $appearances = fn (string $scheme): array => array_map(
            fn (string $handle): array => ['value' => $handle, 'label' => $themes->name($handle)],
            Themes::handlesFor($scheme),
        );

        $buttonColors = [
            ['value' => '', 'label' => t('Blue (Craft default)', category: 'cast')],
            ...array_map(
                fn (string $color): array => ['value' => $color, 'label' => $themes->buttonColorName($color)],
                Themes::BUTTON_COLORS,
            ),
        ];

        $overrides = Config::get("craft.$this->handle", []);
        $overrides = is_array($overrides) ? $overrides : [];

        $overridable = function (string $setting, Field $field) use ($overrides): Field {
            if (array_key_exists($setting, $overrides)) {
                $field->getControl()?->mode(ControlMode::ReadOnly);
                $field->warning(t('Set by `{setting}` in `config/craft/{handle}.php`, so it can’t be changed here.', [
                    'setting' => $setting,
                    'handle' => $this->handle,
                ], 'cast'));
            }

            return $field;
        };

        return Ui::make([
            $overridable('autoLightTheme', Field::make(t('Light Appearance', category: 'cast'), Choice::make('autoLightTheme')
                ->options($appearances(Themes::SCHEME_LIGHT))
                ->withoutPlaceholder())
                ->width(FieldWidth::Third)
                ->instructionsPosition('after')
                ->instructions(t('For anyone who hasn’t chosen their own.', category: 'cast'))),

            $overridable('autoDarkTheme', Field::make(t('Dark Appearance', category: 'cast'), Choice::make('autoDarkTheme')
                ->options($appearances(Themes::SCHEME_DARK))
                ->withoutPlaceholder())
                ->width(FieldWidth::Third)
                ->instructionsPosition('after')
                ->instructions(t('For anyone who hasn’t chosen their own.', category: 'cast'))),

            $overridable('defaultButtonColor', Field::make(t('Button Colour', category: 'cast'), Choice::make('defaultButtonColor')
                ->options($buttonColors)
                ->withoutPlaceholder())
                ->width(FieldWidth::Third)
                ->instructionsPosition('after')
                ->instructions(t('Primary buttons, for anyone who hasn’t chosen their own.', category: 'cast'))),

            $overridable('highContrast', Field::make(t('Increase Contrast', category: 'cast'), Lightswitch::make('highContrast'))
                ->instructions(t('On by default for anyone who hasn’t set it themselves, in light and dark alike.', category: 'cast'))),

            $overridable('allowUserOverride', Field::make(t('Let Users Choose', category: 'cast'), Lightswitch::make('allowUserOverride'))
                ->instructions(t('Offer the appearances, button colour and contrast switch in each user’s preferences. Off, everyone gets the defaults above.', category: 'cast'))),
        ]);
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
