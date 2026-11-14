<?php

declare(strict_types=1);

namespace bensomething\cast\tests\Feature;

use bensomething\cast\Migrations\LegacyPreferences;
use bensomething\cast\tests\TestCase;
use bensomething\cast\Themes;
use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Support\Json;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

final class LegacyPreferencesTest extends TestCase
{
    /** @return array<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function mainChoices(): array
    {
        return [
            'Dim' => [['castTheme' => 'dim'], ['theme' => 'dark', Themes::PREF_DARK => 'dim']],
            'Dark' => [['castTheme' => 'dark'], ['theme' => 'dark', Themes::PREF_DARK => '']],
            'Stone' => [['castTheme' => 'stone'], ['theme' => 'light', Themes::PREF_LIGHT => 'stone']],
            'Stone Dark' => [['castTheme' => 'stone-dark'], ['theme' => 'dark', Themes::PREF_DARK => 'stone']],
            'Craft stock' => [['castTheme' => ''], ['theme' => 'light']],
            'High Contrast' => [['castTheme' => 'high-contrast'], ['theme' => 'light', Themes::PREF_CONTRAST => true]],
            'High Contrast Dark' => [['castTheme' => 'high-contrast-dark'], ['theme' => 'dark', Themes::PREF_CONTRAST => true]],
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $expected
     */
    #[DataProvider('mainChoices')]
    public function test_a_1x_main_choice_becomes_crafts_theme_and_the_appearance(array $before, array $expected): void
    {
        $after = LegacyPreferences::carryOver($before);

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $after[$key] ?? null, $key);
        }

        $this->assertSame($before['castTheme'], $after['castTheme'], 'nothing is deleted');
    }

    public function test_auto_inherit_and_folder_themes_change_nothing(): void
    {
        foreach (['auto', 'inherit', 'midnight'] as $main) {
            $this->assertSame(['castTheme' => $main], LegacyPreferences::carryOver(['castTheme' => $main]), $main);
        }
    }

    public function test_a_high_contrast_half_of_the_auto_pair_turns_contrast_on(): void
    {
        $after = LegacyPreferences::carryOver(['castTheme' => 'auto', Themes::PREF_DARK => 'high-contrast-dark']);

        $this->assertTrue($after[Themes::PREF_CONTRAST]);
        $this->assertArrayNotHasKey('theme', $after);
    }

    public function test_what_the_user_already_set_on_craft_6_is_left_alone(): void
    {
        $after = LegacyPreferences::carryOver([
            'castTheme' => 'high-contrast-dark',
            'theme' => 'system',
            Themes::PREF_CONTRAST => false,
        ]);

        $this->assertSame('system', $after['theme']);
        $this->assertFalse($after[Themes::PREF_CONTRAST]);
    }

    public function test_the_main_choice_wins_over_the_pair_for_its_mode(): void
    {
        $after = LegacyPreferences::carryOver(['castTheme' => 'dim', Themes::PREF_DARK => 'stone-dark', Themes::PREF_LIGHT => 'stone']);

        $this->assertSame('dim', $after[Themes::PREF_DARK]);
        $this->assertSame('stone', $after[Themes::PREF_LIGHT]);
    }

    public function test_preferences_without_1x_choices_are_untouched(): void
    {
        $prefs = ['language' => 'en', Themes::PREF_DARK => 'dim'];

        $this->assertFalse(LegacyPreferences::hasLegacy($prefs));
        $this->assertSame($prefs, LegacyPreferences::carryOver($prefs));
    }

    public function test_the_migration_carries_stored_preferences_over(): void
    {
        $this->setPrefs(['castTheme' => 'dim', 'weekStartDay' => 1]);

        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_14_000000_carry_over_1x_preferences.php';
        $migration->up();

        $stored = Json::decode((string) DB::table(Table::USERPREFERENCES)->where('userId', $this->admin()->id)->value('preferences'));

        $this->assertSame('dark', $stored['theme']);
        $this->assertSame('dim', $stored[Themes::PREF_DARK]);
        $this->assertSame(1, $stored['weekStartDay']);
    }
}
