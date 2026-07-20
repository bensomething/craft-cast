<?php

namespace bensomething\cast\models;

use craft\base\Model;

/**
 * A selectable control-panel colour mode.
 *
 * A theme is just a stylesheet overriding the CP custom properties declared by
 * `craft\web\assets\theme\ThemeAsset`, scoped to `html[data-cast-theme="<handle>"]`.
 */
class Theme extends Model
{
    public const SCHEME_LIGHT = 'light';
    public const SCHEME_DARK = 'dark';

    /**
     * @var string Unique handle. Doubles as the `data-cast-theme` value and, for bundled
     * themes, the stylesheet filename.
     */
    public string $handle = '';

    /** @var string Human-readable name, shown in the theme picker. */
    public string $name = '';

    /** @var string|null Optional one-liner shown under the name in the picker. */
    public ?string $description = null;

    /**
     * @var string Whether the theme is light or dark. Drives the CSS `color-scheme`
     * declaration and which theme "Auto" resolves to for a given OS preference.
     */
    public string $colorScheme = self::SCHEME_LIGHT;

    /**
     * @var string|null Absolute URL to the theme's stylesheet. Bundled themes leave this
     * null and resolve against the plugin's published resources. Third-party themes
     * registered via {@see \bensomething\cast\services\Themes::EVENT_REGISTER_THEMES}
     * must set it.
     */
    public ?string $url = null;

    /**
     * @var string|null Absolute path to the stylesheet, for themes discovered in the
     * themes folder. Set instead of {@see self::$url}, which is then derived from it.
     */
    public ?string $path = null;

    public function rules(): array
    {
        return [
            [['handle', 'name', 'colorScheme'], 'required'],
            [['handle'], 'match', 'pattern' => '/^[a-z][a-z0-9\-]*$/'],
            [['colorScheme'], 'in', 'range' => [self::SCHEME_LIGHT, self::SCHEME_DARK]],
            [['url', 'description', 'path'], 'string'],
        ];
    }

    public function getIsDark(): bool
    {
        return $this->colorScheme === self::SCHEME_DARK;
    }
}
