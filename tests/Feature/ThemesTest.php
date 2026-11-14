<?php

declare(strict_types=1);

namespace bensomething\cast\tests\Feature;

use bensomething\cast\Plugin;
use bensomething\cast\Settings;
use bensomething\cast\tests\TestCase;
use bensomething\cast\Themes;

final class ThemesTest extends TestCase
{
    /** Built fresh, so it reads settings changed during the test. */
    private function themes(): Themes
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();

        return new Themes($settings);
    }

    public function test_each_mode_offers_its_own_themes_after_crafts(): void
    {
        $this->assertSame(['', 'stone'], $this->themes()->handles(Themes::SCHEME_LIGHT));
        $this->assertSame(['', 'stone', 'dim'], $this->themes()->handles(Themes::SCHEME_DARK));
    }

    public function test_site_default_folds_into_craft_default_while_they_match(): void
    {
        $options = $this->themes()->options(Themes::SCHEME_DARK);

        $this->assertSame(
            [['value' => 'inherit', 'label' => 'Craft default'], ['value' => 'stone', 'label' => 'Stone'], ['value' => 'dim', 'label' => 'Dim']],
            $options,
        );
    }

    public function test_a_theme_as_site_default_gets_its_own_row(): void
    {
        $this->setSettings(['autoDarkTheme' => 'dim']);

        $values = array_column($this->themes()->options(Themes::SCHEME_DARK), 'label', 'value');

        $this->assertSame('Site default (Dim)', $values['inherit']);
        $this->assertSame('Craft default', $values['']);
    }

    public function test_a_users_own_choice_wins(): void
    {
        $this->setPrefs([Themes::PREF_DARK => 'dim']);

        $this->assertSame('dim', $this->themes()->forUser($this->admin(), Themes::SCHEME_DARK));
    }

    public function test_without_a_choice_the_site_default_applies(): void
    {
        $this->setSettings(['autoDarkTheme' => 'stone']);

        $this->assertSame('stone', $this->themes()->forUser($this->admin(), Themes::SCHEME_DARK));
    }

    public function test_handles_saved_by_1x_read_as_what_they_now_mean(): void
    {
        $this->setPrefs([Themes::PREF_DARK => 'stone-dark', Themes::PREF_LIGHT => 'high-contrast']);
        $user = $this->admin();

        $this->assertSame('stone', $this->themes()->forUser($user, Themes::SCHEME_DARK));
        $this->assertSame('', $this->themes()->forUser($user, Themes::SCHEME_LIGHT));

        $this->setPrefs([Themes::PREF_DARK => 'dark']);
        $this->assertSame('', $this->themes()->forUser($this->admin(), Themes::SCHEME_DARK));
    }

    public function test_an_unknown_or_wrong_mode_choice_falls_back_to_the_site_default(): void
    {
        $this->setSettings(['autoLightTheme' => 'stone']);
        $this->setPrefs([Themes::PREF_LIGHT => 'dim']);

        $this->assertSame('stone', $this->themes()->forUser($this->admin(), Themes::SCHEME_LIGHT));
    }

    public function test_users_looks_are_ignored_when_they_may_not_choose(): void
    {
        $this->setPrefs([Themes::PREF_DARK => 'dim', Themes::PREF_BUTTON => 'teal', Themes::PREF_CONTRAST => true]);
        $this->setSettings(['allowUserOverride' => false, 'autoDarkTheme' => 'stone', 'defaultButtonColor' => 'pink']);
        $user = $this->admin();

        $this->assertSame('stone', $this->themes()->forUser($user, Themes::SCHEME_DARK));
        $this->assertSame('pink', $this->themes()->buttonColorForUser($user));
        $this->assertTrue($this->themes()->highContrastForUser($user), 'contrast is always the user\'s');
    }

    public function test_a_stored_craft_default_shows_as_the_folded_row(): void
    {
        $this->setPrefs([Themes::PREF_DARK => '']);

        $this->assertSame('inherit', $this->themes()->storedChoice($this->admin(), Themes::SCHEME_DARK));

        $this->setSettings(['autoDarkTheme' => 'dim']);
        $this->assertSame('', $this->themes()->storedChoice($this->admin(), Themes::SCHEME_DARK));
    }

    public function test_button_colour_resolves_like_the_appearances(): void
    {
        $themes = $this->themes();

        $this->assertSame('', $themes->buttonColorForUser($this->admin()));

        $this->setSettings(['defaultButtonColor' => 'teal']);
        $this->assertSame('teal', $themes->buttonColorForUser($this->admin()));

        $this->setPrefs([Themes::PREF_BUTTON => 'red']);
        $this->assertSame('red', $themes->buttonColorForUser($this->admin()));

        $this->setPrefs([Themes::PREF_BUTTON => 'orange']);
        $this->assertSame('teal', $themes->buttonColorForUser($this->admin()));
    }

    public function test_craft_red_is_offered_by_its_hex_and_labelled(): void
    {
        $options = $this->themes()->buttonColorOptions();

        $this->assertSame('blue', $options['colors'][0]);
        $this->assertContains(Themes::CRAFT_RED, $options['colors']);
        $this->assertNotContains('red', $options['colors']);
        $this->assertSame('Craft Red', $options['labels'][Themes::CRAFT_RED]);
    }

    public function test_the_site_default_colour_is_labelled_as_such(): void
    {
        $this->setSettings(['defaultButtonColor' => 'red']);

        $this->assertSame('Craft Red (site default)', $this->themes()->buttonColorOptions()['labels'][Themes::CRAFT_RED]);
    }

    public function test_contrast_follows_the_users_switch_then_the_site_default(): void
    {
        $themes = $this->themes();

        $this->assertFalse($themes->highContrastForUser($this->admin()));

        $this->setSettings(['highContrast' => true]);
        $this->assertTrue($themes->highContrastForUser($this->admin()));

        $this->setPrefs([Themes::PREF_CONTRAST => false]);
        $this->assertFalse($themes->highContrastForUser($this->admin()));
    }
}
