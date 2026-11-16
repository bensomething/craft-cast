<?php

declare(strict_types=1);

namespace bensomething\cast;

use CraftCms\Cms\Cp\CommandPalette\CommandPaletteItem;
use CraftCms\Cms\Cp\Enums\Theme as CraftTheme;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Contracts\CraftUser;

use function CraftCms\Cms\t;

/**
 * Cast's actions in Craft's command palette: each appearance the user could switch to,
 * and the contrast switch.
 *
 * They mirror the preferences screen. Appearances only for the modes Craft's Theme
 * shows (one for Light or Dark, both for System) and only with Let Users Choose on; the
 * contrast switch always. Each is an `event` action rather than an `http` one, so
 * `resources/js/command-palette.js` can repaint at once and save behind it, where an
 * `http` action would need a reload to show the change.
 *
 * Every item carries "cast" as a keyword, so searching for it lists them all. The
 * appearances are search-only, as there can be a dozen or more of them.
 */
final readonly class CommandPaletteActions
{
    /** The window event the actions dispatch, which the script listens for. */
    public const string EVENT = 'cast:set-preference';

    /** Craft's palette colours, for the colour themes' icons. */
    private const array ICON_COLORS = ['red', 'amber', 'green', 'teal', 'blue', 'violet', 'pink'];

    public function __construct(
        private Settings $settings,
        private Themes $themes,
    ) {}

    /** @return list<CommandPaletteItem> */
    public function items(CraftUser $user): array
    {
        $element = $user->asElement();
        $items = [];

        if ($this->settings->allowUserOverride) {
            $craftTheme = CraftTheme::forUser($element);

            foreach ([Themes::SCHEME_LIGHT, Themes::SCHEME_DARK] as $scheme) {
                if ($craftTheme !== CraftTheme::System && $craftTheme->value !== $scheme) {
                    continue;
                }

                $current = $this->themes->forUser($element, $scheme);

                foreach ($this->themes->offered($scheme) as $handle) {
                    if ($handle !== $current) {
                        $items[] = $this->appearance($scheme, $handle);
                    }
                }
            }
        }

        $on = $this->themes->highContrastForUser($element);

        $items[] = $this->item(
            'cast-toggle-contrast',
            $on ? t('Turn off increased contrast', category: 'cast') : t('Increase contrast', category: 'cast'),
            ['highContrast' => ! $on],
        )->icon('circle-half-stroke');

        return $items;
    }

    private function appearance(string $scheme, string $handle): CommandPaletteItem
    {
        $theme = $this->themes->name($handle);

        $label = $scheme === Themes::SCHEME_DARK
            ? t('Set dark appearance to {theme}', ['theme' => $theme], 'cast')
            : t('Set light appearance to {theme}', ['theme' => $theme], 'cast');

        return $this->item("cast-set-$scheme-".($handle ?: 'craft'), $label, ['scheme' => $scheme, 'handle' => $handle])
            ->icon($scheme === Themes::SCHEME_DARK ? 'moon' : 'sun')
            ->iconColor(in_array($handle, self::ICON_COLORS, true) ? $handle : null)
            ->searchOnly();
    }

    /** @param array<string, mixed> $detail */
    private function item(string $id, string $label, array $detail): CommandPaletteItem
    {
        return new CommandPaletteItem()
            ->id($id)
            ->label($label)
            ->description(t('Preferences'))
            ->keywords('cast theme appearance colour color mode')
            ->action([
                'type' => 'event',
                'name' => self::EVENT,
                'detail' => [
                    ...$detail,
                    'url' => Url::actionUrl('cast/preferences'),
                    'token' => csrf_token(),
                ],
            ]);
    }
}
