<?php

declare(strict_types=1);

namespace bensomething\cast;

use CraftCms\Cms\Plugin\PluginSettings;
use Illuminate\Validation\Rule;
use Override;

use function CraftCms\Cms\t;

/**
 * Site-wide defaults for what each user can choose in their own preferences, edited
 * under Settings → Plugins → Cast or in `config/cast.php`.
 *
 * Several 1.x settings are kept only so a 1.x `config/cast.php` doesn't fail: Craft 6
 * decides light or dark itself, and the colour vocabulary 1.x themed is gone. See
 * `docs/craft-6.md`.
 */
class Settings extends PluginSettings
{
    /** Pseudo-handle: follow the OS colour-scheme preference. */
    public const string THEME_AUTO = 'auto';

    /** Pseudo-handle: leave Craft's own styling alone. */
    public const string THEME_NONE = '';

    /**
     * Pseudo-handle, valid as a user preference only, meaning "use the admin default".
     * Distinct from never having chosen, which resolves the same way, so a user who has
     * picked a theme can hand control back.
     */
    public const string THEME_INHERIT = 'inherit';

    /**
     * Retired in 2.x. 1.x stored `red` here for everyone, red being Craft 5's own, so
     * reading it would turn every upgraded site's buttons red. Still accepted so a 1.x
     * `config/cast.php` doesn't fail, and otherwise ignored for `defaultButtonColor`.
     */
    public string $buttonColor = 'red';

    /**
     * The primary button colour for users who haven't chosen one: one of
     * {@see Themes::BUTTON_COLORS}, or empty for Craft's own (blue on Craft 6).
     */
    public string $defaultButtonColor = self::THEME_NONE;

    /**
     * Retired in 2.x: Craft now decides light or dark, from its own Theme preference.
     * Still accepted so a 1.x `config/cast.php` doesn't fail, and otherwise ignored.
     */
    public string $defaultTheme = self::THEME_AUTO;

    /** Whether users may pick their own theme. */
    public bool $allowUserOverride = true;

    /**
     * Theme handles offered in the picker, or `'*'` for all. The default is always
     * available regardless of this list.
     *
     * @var string[]|string
     */
    public array|string $enabledThemes = '*';

    /** Folder scanned for theme stylesheets, as a path or alias. */
    public string $themesPath = '@root/cast-themes';

    /**
     * The dark appearance for users who haven't chosen one, used whenever Craft is dark.
     * Empty means Craft's own dark. Named for 1.x's Auto pair, which this generalises;
     * 1.x's `dark` is read as Craft's own.
     */
    public string $autoDarkTheme = self::THEME_NONE;

    /** The light appearance for users who haven't chosen one. Empty means Craft's own. */
    public string $autoLightTheme = self::THEME_NONE;

    /** Whether users who haven't set the switch themselves get increased contrast. */
    public bool $highContrast = false;

    #[Override]
    public function getRules(): array
    {
        return [
            'autoLightTheme' => ['string', Rule::in(Themes::handlesFor(Themes::SCHEME_LIGHT))],
            'autoDarkTheme' => ['string', Rule::in(Themes::handlesFor(Themes::SCHEME_DARK))],
            'defaultButtonColor' => ['string', Rule::in(['', ...Themes::BUTTON_COLORS])],
            'highContrast' => ['boolean'],
            'allowUserOverride' => ['boolean'],
        ];
    }

    /** Names for validation messages, matching the settings screen. */
    #[Override]
    public function attributeLabels(): array
    {
        return [
            'autoLightTheme' => t('Light Appearance', category: 'cast'),
            'autoDarkTheme' => t('Dark Appearance', category: 'cast'),
            'defaultButtonColor' => t('Button Colour', category: 'cast'),
            'highContrast' => t('Increase Contrast', category: 'cast'),
            'allowUserOverride' => t('Let Users Choose', category: 'cast'),
        ];
    }

    /**
     * 1.x's handles read as 2.x's, so a 1.x value like `dark` shows, validates and
     * saves as what it now means rather than failing.
     */
    #[Override]
    public function validationData(): array
    {
        $data = parent::validationData();

        foreach (['autoLightTheme', 'autoDarkTheme'] as $key) {
            $data[$key] = Themes::normalizeHandle((string) ($data[$key] ?? ''));
        }

        return $data;
    }
}
