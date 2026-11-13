<?php

declare(strict_types=1);

namespace bensomething\cast;

use CraftCms\Cms\Plugin\PluginSettings;

/**
 * Carried over from 1.x so `config/cast.php` keeps working. What each setting means on
 * Craft 6 is still open: Craft now has its own Light, Dark and System preference, and
 * the colour vocabulary 1.x themed is gone. See `docs/craft-6.md`.
 *
 * 1.x validated these through Yii `rules()`. On Craft 6 that belongs to a settings
 * request class, which arrives with the settings UI.
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
}
