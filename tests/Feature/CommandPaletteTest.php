<?php

declare(strict_types=1);

namespace bensomething\cast\tests\Feature;

use bensomething\cast\CommandPaletteActions;
use bensomething\cast\tests\TestCase;
use bensomething\cast\Themes;
use CraftCms\Cms\Cp\CommandPalette\ActionRegistry;
use CraftCms\Cms\Cp\CommandPalette\CommandPaletteItem;

final class CommandPaletteTest extends TestCase
{
    /** @return array<string, CommandPaletteItem> Cast's actions for the admin, by ID. */
    private function actions(): array
    {
        $items = array_filter(
            app(ActionRegistry::class)->items($this->admin()),
            fn (CommandPaletteItem $item) => str_starts_with((string) $item->id, 'cast-'),
        );

        return array_column(array_map(fn ($item) => ['id' => $item->id, 'item' => $item], $items), 'item', 'id');
    }

    public function test_system_offers_both_modes_appearances_and_the_contrast_switch(): void
    {
        $this->setPrefs(['theme' => 'system']);

        $actions = $this->actions();

        $this->assertArrayHasKey('cast-set-light-stone', $actions);
        $this->assertArrayHasKey('cast-set-light-teal', $actions);
        $this->assertArrayHasKey('cast-set-dark-dim', $actions);
        $this->assertArrayHasKey('cast-toggle-contrast', $actions);
        $this->assertArrayNotHasKey('cast-set-light-dim', $actions, 'Dim is dark only');
    }

    public function test_light_or_dark_offers_only_that_modes(): void
    {
        $this->setPrefs(['theme' => 'dark']);

        $ids = array_keys($this->actions());

        $this->assertContains('cast-set-dark-stone', $ids);
        $this->assertEmpty(preg_grep('/^cast-set-light-/', $ids));
    }

    public function test_the_current_appearance_is_left_out(): void
    {
        $this->setPrefs(['theme' => 'dark', Themes::PREF_DARK => 'dim']);

        $actions = $this->actions();

        $this->assertArrayNotHasKey('cast-set-dark-dim', $actions);
        $this->assertArrayHasKey('cast-set-dark-craft', $actions, "Craft's own, to go back to");
    }

    public function test_an_appearance_is_an_event_carrying_the_change_and_how_to_save_it(): void
    {
        $this->setPrefs(['theme' => 'light']);

        $item = $this->actions()['cast-set-light-teal'];

        $this->assertSame('Set light appearance to Teal', $item->label);
        $this->assertSame('teal', $item->iconColor);
        $this->assertTrue($item->searchOnly);
        $this->assertStringContainsString('cast', (string) $item->keywords);
        $this->assertSame('event', $item->action['type']);
        $this->assertSame(CommandPaletteActions::EVENT, $item->action['name']);
        $this->assertSame('light', $item->action['detail']['scheme']);
        $this->assertSame('teal', $item->action['detail']['handle']);
        $this->assertStringEndsWith('actions/cast/preferences', $item->action['detail']['url']);
        $this->assertArrayHasKey('token', $item->action['detail'], 'the CSRF token, empty here without a session');
    }

    public function test_only_enabled_themes_are_offered(): void
    {
        $this->setPrefs(['theme' => 'light']);
        $this->setSettings(['enabledThemes' => ['stone']]);

        $ids = array_keys($this->actions());

        $this->assertContains('cast-set-light-stone', $ids);
        $this->assertNotContains('cast-set-light-teal', $ids);
    }

    public function test_with_let_users_choose_off_only_the_contrast_switch_is_offered(): void
    {
        $this->setSettings(['allowUserOverride' => false]);

        $this->assertSame(['cast-toggle-contrast'], array_keys($this->actions()));
    }

    public function test_the_contrast_switch_offers_the_opposite_of_what_the_user_has(): void
    {
        $this->assertSame('Increase contrast', $this->actions()['cast-toggle-contrast']->label);
        $this->assertTrue($this->actions()['cast-toggle-contrast']->action['detail']['highContrast']);

        $this->setPrefs([Themes::PREF_CONTRAST => true]);

        $this->assertSame('Turn off increased contrast', $this->actions()['cast-toggle-contrast']->label);
        $this->assertFalse($this->actions()['cast-toggle-contrast']->action['detail']['highContrast']);
    }

    public function test_the_palette_lists_the_contrast_switch_and_finds_every_appearance_by_searching_cast(): void
    {
        $this->signIn();
        $this->setPrefs(['theme' => 'system']);

        $listed = collect($this->getJson('/admin/command-palette')->assertOk()->json('actions'))->pluck('id');

        $this->assertContains('cast-toggle-contrast', $listed);
        $this->assertEmpty($listed->filter(fn ($id) => str_starts_with($id, 'cast-set-')), 'the appearances are search-only');

        $found = collect($this->getJson('/admin/command-palette/search/actions?query=cast')->assertOk()->json('results'))->pluck('id');
        $offered = collect(array_keys($this->actions()))->filter(fn ($id) => str_starts_with($id, 'cast-set-'));

        $this->assertNotEmpty($offered);
        $this->assertEqualsCanonicalizing($offered->values()->all(), $found->values()->all());
    }
}
