<?php

declare(strict_types=1);

namespace bensomething\cast\tests\Feature;

use bensomething\cast\tests\TestCase;
use bensomething\cast\Themes;
use PHPUnit\Framework\Attributes\DataProvider;

final class PreferencesActionTest extends TestCase
{
    public function test_guests_are_turned_away(): void
    {
        $this->postJson($this->castAction('preferences'), ['dark' => 'dim'])->assertUnauthorized();
    }

    public function test_site_action_requests_are_turned_away(): void
    {
        $this->signIn();

        $this->postJson('/actions/cast/preferences', ['dark' => 'dim'])->assertUnauthorized();
        $this->assertNull($this->pref(Themes::PREF_DARK));
    }

    public function test_it_saves_each_choice(): void
    {
        $this->signIn();

        $this->postJson($this->castAction('preferences'), [
            'light' => 'stone',
            'dark' => 'dim',
            'buttonColor' => 'teal',
            'highContrast' => true,
        ])->assertOk();

        $this->assertSame('stone', $this->pref(Themes::PREF_LIGHT));
        $this->assertSame('dim', $this->pref(Themes::PREF_DARK));
        $this->assertSame('teal', $this->pref(Themes::PREF_BUTTON));
        $this->assertTrue($this->pref(Themes::PREF_CONTRAST));
    }

    public function test_it_saves_one_choice_without_touching_the_others(): void
    {
        $this->signIn();
        $this->setPrefs([Themes::PREF_LIGHT => 'stone', 'weekStartDay' => 3]);

        $this->postJson($this->castAction('preferences'), ['dark' => 'dim'])->assertOk();

        $this->assertSame('stone', $this->pref(Themes::PREF_LIGHT));
        $this->assertSame(3, $this->pref('weekStartDay'), "Craft's own preferences are left alone");
    }

    public function test_an_empty_string_saves_crafts_own(): void
    {
        $this->signIn();
        $this->setPrefs([Themes::PREF_DARK => 'dim', Themes::PREF_BUTTON => 'teal']);

        $this->postJson($this->castAction('preferences'), ['dark' => '', 'buttonColor' => ''])->assertOk();

        $this->assertSame('', $this->pref(Themes::PREF_DARK));
        $this->assertSame('', $this->pref(Themes::PREF_BUTTON));
    }

    public function test_inherit_hands_control_back(): void
    {
        $this->signIn();
        $this->setPrefs([Themes::PREF_DARK => 'dim']);

        $this->postJson($this->castAction('preferences'), ['dark' => 'inherit'])->assertOk();

        $this->assertSame('inherit', $this->pref(Themes::PREF_DARK));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalid(): array
    {
        return [
            'unknown theme' => [['dark' => 'nope']],
            'theme for the other mode' => [['light' => 'dim']],
            'unknown colour' => [['buttonColor' => 'orange']],
            'contrast not a boolean' => [['highContrast' => 'yes']],
            'theme not a string' => [['dark' => ['dim']]],
        ];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('invalid')]
    public function test_it_rejects_and_saves_nothing(array $body): void
    {
        $this->signIn();

        $this->postJson($this->castAction('preferences'), $body + ['light' => 'stone'])->assertUnprocessable();

        $this->assertNull($this->pref(Themes::PREF_LIGHT), 'nothing is half-saved');
    }

    public function test_it_refuses_looks_when_users_may_not_choose(): void
    {
        $this->signIn();
        $this->setSettings(['allowUserOverride' => false]);

        $this->postJson($this->castAction('preferences'), ['dark' => 'dim'])->assertForbidden();
        $this->postJson($this->castAction('preferences'), ['buttonColor' => 'teal'])->assertForbidden();
        $this->postJson($this->castAction('preferences'), ['highContrast' => true, 'light' => 'stone'])->assertForbidden();
        $this->assertNull($this->pref(Themes::PREF_DARK));
        $this->assertNull($this->pref(Themes::PREF_BUTTON));
        $this->assertNull($this->pref(Themes::PREF_CONTRAST), 'nothing is half-saved');
    }

    public function test_contrast_saves_when_users_may_not_choose(): void
    {
        $this->signIn();
        $this->setSettings(['allowUserOverride' => false]);

        $this->postJson($this->castAction('preferences'), ['highContrast' => true])->assertOk();
        $this->assertTrue($this->pref(Themes::PREF_CONTRAST));
    }
}
