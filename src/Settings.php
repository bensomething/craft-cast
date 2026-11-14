<?php

declare(strict_types=1);

namespace bensomething\cast;

use Closure;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Validation\Rules\ColorRule;
use Illuminate\Validation\Rule;
use Override;

use function CraftCms\Cms\t;

/**
 * Site-wide defaults for what each user can choose in their own preferences, edited
 * under Settings → Plugins → Cast or in `config/cast.php`.
 *
 * `defaultTheme` and `buttonColor` are kept only so a 1.x config doesn't fail: Craft 6
 * decides light or dark itself, and 1.x's `buttonColor` was Craft 5's red for everyone.
 * See `docs/craft-6.md`.
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
     * Theme handles users may pick from, or `['*']` (or `'*'`) for all, including any
     * added later.
     * The site defaults are always available regardless.
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

    /**
     * How strongly the colour themes ({@see Themes::HUES}) come through: a key of
     * {@see ThemeGenerator::TINTS}. Custom themes set their own.
     */
    public string $hueTint = 'medium';

    /**
     * Themes generated from one colour each, as rows of `name`, `color` (`#rrggbb`, only
     * its hue is used) and `tint` (a key of {@see ThemeGenerator::TINTS}). The settings
     * screen adds a `rowId`, which the theme's handle comes from, so renaming a theme
     * doesn't lose the users who picked it. See {@see Themes::customHandle()}.
     *
     * @var list<array<string, mixed>>
     */
    public array $customThemes = [];

    #[Override]
    public function getRules(): array
    {
        return [
            'autoLightTheme' => ['string', Rule::in(app(Themes::class)->handles(Themes::SCHEME_LIGHT))],
            'autoDarkTheme' => ['string', Rule::in(app(Themes::class)->handles(Themes::SCHEME_DARK))],
            'defaultButtonColor' => ['string', Rule::in(['', ...Themes::BUTTON_COLORS])],
            'enabledThemes' => [function (string $attribute, mixed $value, Closure $fail): void {
                $known = array_keys(app(Themes::class)->all());

                if ($value !== ['*'] && (! is_array($value) || array_diff($value, $known) !== [])) {
                    $fail(t('Pick from the themes listed.', category: 'cast'));
                }
            }],
            'highContrast' => ['boolean'],
            'allowUserOverride' => ['boolean'],
            'hueTint' => ['string', Rule::in(array_keys(ThemeGenerator::TINTS))],
            'customThemes' => ['array'],
            'customThemes.*.name' => ['required', 'string', 'max:40'],
            'customThemes.*.color' => ['required', 'string', 'regex:/^#[0-9a-f]{6}$/'],
            'customThemes.*.tint' => ['required', 'string', Rule::in(array_keys(ThemeGenerator::TINTS))],
        ];
    }

    /** Names for validation messages, matching the settings screen. */
    #[Override]
    public function attributeLabels(): array
    {
        return [
            'autoLightTheme' => t('Light Appearance', category: 'cast'),
            'autoDarkTheme' => t('Dark Appearance', category: 'cast'),
            'defaultButtonColor' => t('Primary Button Colour', category: 'cast'),
            'highContrast' => t('Increase Contrast', category: 'cast'),
            'allowUserOverride' => t('Let Users Choose', category: 'cast'),
            'enabledThemes' => t('Available Themes', category: 'cast'),
            'hueTint' => t('Hue Tint', category: 'cast'),
            'customThemes' => t('Custom Themes', category: 'cast'),
            'customThemes.*.name' => t('Name', category: 'cast'),
            'customThemes.*.color' => t('Base Colour', category: 'cast'),
            'customThemes.*.tint' => t('Tint', category: 'cast'),
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

        // Sky was a button colour in 2.0.0-beta.1, dropped as too close to Craft's own blue,
        // which a saved Sky now reads as.
        if (($data['defaultButtonColor'] ?? '') === 'sky') {
            $data['defaultButtonColor'] = '';
        }

        $data['customThemes'] = self::normalizeCustomThemes($data['customThemes'] ?? []);

        // All is `['*']`, the checkbox list's single token for it, which is also what the
        // settings screen needs to show All ticked: these values are what it renders. A
        // config file's `'*'` reads the same, and so does none ticked, which would
        // otherwise read as offering nothing while every theme stayed on offer, as 1.x's did.
        $enabled = $data['enabledThemes'] ?? '*';

        if ($enabled === '*' || $enabled === [] || $enabled === '' || (is_array($enabled) && in_array('*', $enabled, true))) {
            $data['enabledThemes'] = ['*'];
        }

        return $data;
    }

    /**
     * Custom theme rows as they're saved: colours written as Craft's colour fields write
     * them (`#ABC` as `#aabbcc`), names trimmed, wholly empty rows dropped, and the
     * Preview checkbox left out, as it's a look at the theme rather than part of it.
     *
     * @return list<array<string, mixed>>
     */
    public static function normalizeCustomThemes(mixed $rows): array
    {
        $normalized = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $color = trim((string) ($row['color'] ?? ''));

            if ($name === '' && $color === '') {
                continue;
            }

            $normalized[] = array_filter([
                'rowId' => isset($row['rowId']) ? (string) $row['rowId'] : null,
                'name' => $name,
                'color' => $color === '' ? '' : ColorRule::normalizeColor($color),
                'tint' => (string) ($row['tint'] ?? 'subtle'),
            ], fn ($value) => $value !== null);
        }

        return $normalized;
    }
}
