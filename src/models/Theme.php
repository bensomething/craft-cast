<?php

namespace bensomething\cast\models;

use craft\base\Model;

/**
 * A selectable control-panel colour mode.
 *
 * A theme is nothing more than a stylesheet that overrides Craft's CP CSS custom
 * properties (the ones declared by `craft\web\assets\theme\ThemeAsset`), scoped to
 * `html[data-cast-theme="<handle>"]` so it only applies when that theme is active.
 */
class Theme extends Model
{
    public const SCHEME_LIGHT = 'light';
    public const SCHEME_DARK = 'dark';

    /**
     * @var string Unique handle. Doubles as the `data-cast-theme` value and, for
     * bundled themes, the stylesheet filename.
     */
    public string $handle = '';

    /** @var string Human-readable name, shown in the theme picker. */
    public string $name = '';

    /** @var string|null Optional one-liner shown under the name in the picker. */
    public ?string $description = null;

    /**
     * @var string Whether the theme is fundamentally light or dark. Drives the CSS
     * `color-scheme` declaration (form controls, scrollbars, canvas) and which theme
     * "Auto" resolves to for a given OS preference.
     */
    public string $colorScheme = self::SCHEME_LIGHT;

    /**
     * @var string|null Absolute URL to the theme's stylesheet. Bundled themes leave
     * this null and are resolved against the plugin's published resources; third-party
     * themes registered via {@see \bensomething\cast\services\Themes::EVENT_REGISTER_THEMES}
     * must set it.
     */
    public ?string $url = null;

    public function rules(): array
    {
        return [
            [['handle', 'name', 'colorScheme'], 'required'],
            [['handle'], 'match', 'pattern' => '/^[a-z][a-z0-9\-]*$/'],
            [['colorScheme'], 'in', 'range' => [self::SCHEME_LIGHT, self::SCHEME_DARK]],
            [['url', 'description'], 'string'],
        ];
    }

    public function getIsDark(): bool
    {
        return $this->colorScheme === self::SCHEME_DARK;
    }
}
