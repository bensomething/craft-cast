<?php

namespace bensomething\cast\tests\unit;

use bensomething\cast\models\Settings;
use bensomething\cast\models\Theme;
use bensomething\cast\services\Themes;
use bensomething\cast\tests\TestCase;

/**
 * Which theme a given user ends up with, and which stylesheets that means loading.
 */
class ThemeResolutionTest extends TestCase
{
    public function testEveryThemeIsSelectableByDefault(): void
    {
        self::assertSame(
            array_keys($this->themes->getAllThemes()),
            array_keys($this->themes->getEnabledThemes()),
        );
    }

    public function testOnlyTheEnabledThemesAreSelectable(): void
    {
        $this->settings->enabledThemes = ['dark', 'dim'];
        $this->settings->defaultTheme = 'dark';

        self::assertSame(['dark', 'dim'], array_keys($this->themes->getEnabledThemes()));
    }

    /**
     * Otherwise the picker would claim a theme isn't on offer while the user is looking
     * at it.
     */
    public function testTheDefaultStaysSelectableEvenIfItWasLeftOffTheList(): void
    {
        $this->settings->enabledThemes = ['dim'];
        $this->settings->defaultTheme = 'high-contrast';

        self::assertSame(['dim', 'high-contrast'], array_keys($this->themes->getEnabledThemes()));
    }

    public function testUsesTheSiteDefaultWhenNobodyIsSignedIn(): void
    {
        $this->settings->defaultTheme = 'dim';

        self::assertSame('dim', $this->themes->getThemeHandleForUser());
    }

    public function testUsesTheUsersOwnChoice(): void
    {
        $this->settings->defaultTheme = 'dark';
        $user = $this->signIn([Themes::PREF_KEY => 'high-contrast']);

        self::assertSame('high-contrast', $this->themes->getThemeHandleForUser($user));
        self::assertSame('high-contrast', $this->themes->getThemeHandleForUser(), 'The signed-in user is the default subject.');
    }

    public function testIgnoresAUsersChoiceWhenOverridesAreOff(): void
    {
        $this->settings->allowUserOverride = false;
        $this->settings->defaultTheme = 'dark';
        $user = $this->signIn([Themes::PREF_KEY => 'high-contrast']);

        self::assertSame('dark', $this->themes->getThemeHandleForUser($user));
    }

    public function testAUserWhoNeverChoseFollowsTheDefault(): void
    {
        $this->settings->defaultTheme = 'dim';

        self::assertSame('dim', $this->themes->getThemeHandleForUser($this->signIn()));
    }

    /**
     * Distinct from never having chosen: this is a user handing control back.
     */
    public function testInheritFollowsTheDefault(): void
    {
        $this->settings->defaultTheme = 'dim';
        $user = $this->signIn([Themes::PREF_KEY => Settings::THEME_INHERIT]);

        self::assertSame('dim', $this->themes->getThemeHandleForUser($user));
    }

    public function testAThemeThatNoLongerExistsFallsBackToTheDefault(): void
    {
        $this->settings->defaultTheme = 'dark';
        $user = $this->signIn([Themes::PREF_KEY => 'uninstalled']);

        self::assertSame('dark', $this->themes->getThemeHandleForUser($user));
    }

    public function testAutoAndNoThemeAreBothDeliberateChoices(): void
    {
        $this->settings->defaultTheme = 'dark';

        self::assertSame(
            Settings::THEME_AUTO,
            $this->themes->getThemeHandleForUser($this->signIn([Themes::PREF_KEY => Settings::THEME_AUTO])),
        );

        self::assertSame(
            Settings::THEME_NONE,
            $this->themes->getThemeHandleForUser($this->signIn([Themes::PREF_KEY => Settings::THEME_NONE])),
        );
    }

    public function testAConcreteHandleLoadsOneStylesheet(): void
    {
        self::assertSame(['dim'], $this->handlesToLoad('dim'));
    }

    public function testNoThemeLoadsNothing(): void
    {
        self::assertSame([], $this->handlesToLoad(Settings::THEME_NONE));
        self::assertSame([], $this->handlesToLoad('uninstalled'));
    }

    public function testAutoLoadsTheConfiguredPair(): void
    {
        $this->settings->autoLightTheme = 'high-contrast';
        $this->settings->autoDarkTheme = 'dark';

        self::assertSame(['high-contrast', 'dark'], $this->handlesToLoad(Settings::THEME_AUTO));
    }

    /**
     * The stock light CP is a legitimate half of the pair, and it has no stylesheet.
     */
    public function testAutoLoadsOnlyTheDarkHalfWhenLightIsCraftsOwn(): void
    {
        $this->settings->autoLightTheme = Settings::THEME_NONE;
        $this->settings->autoDarkTheme = 'dark';

        self::assertSame(['dark'], $this->handlesToLoad(Settings::THEME_AUTO));
    }

    public function testAUserWithNoPairOfTheirOwnGetsTheSiteWideOne(): void
    {
        $this->settings->autoLightTheme = 'stone';
        $this->settings->autoDarkTheme = 'dim';

        self::assertSame(['stone', 'dim'], $this->themes->getAutoPairForUser($this->signIn()));
    }

    public function testAUserCanNameEitherHalfOfTheirOwnPair(): void
    {
        $this->settings->autoLightTheme = 'stone';
        $this->settings->autoDarkTheme = 'dim';

        $user = $this->signIn([Themes::PREF_LIGHT_KEY => 'high-contrast']);

        self::assertSame(['high-contrast', 'dim'], $this->themes->getAutoPairForUser($user));
        self::assertSame(['high-contrast', 'dim'], $this->themes->getAutoPairForUser(), 'The signed-in user is the default subject.');
        self::assertSame(['high-contrast', 'dim'], $this->handlesToLoad(Settings::THEME_AUTO));
    }

    /**
     * Distinct from never having chosen, exactly as it is for the theme preference.
     */
    public function testInheritHandsAHalfBackToTheAdmin(): void
    {
        $this->settings->autoDarkTheme = 'stone-dark';

        $user = $this->signIn([Themes::PREF_DARK_KEY => Settings::THEME_INHERIT]);

        self::assertSame('stone-dark', $this->themes->getAutoPairForUser($user)[1]);
    }

    /**
     * The stock light CP is a legitimate half, and the admin's own pair can hold it too.
     */
    public function testAUserCanPickCraftsOwnAppearanceForAHalf(): void
    {
        $this->settings->autoLightTheme = 'stone';

        $user = $this->signIn([Themes::PREF_LIGHT_KEY => Settings::THEME_NONE]);

        self::assertSame(Settings::THEME_NONE, $this->themes->getAutoPairForUser($user)[0]);
    }

    public function testAHalfThatNoLongerExistsFallsBackToTheSiteWideOne(): void
    {
        $this->settings->autoDarkTheme = 'dark';

        $user = $this->signIn([Themes::PREF_DARK_KEY => 'uninstalled']);

        self::assertSame('dark', $this->themes->getAutoPairForUser($user)[1]);
    }

    public function testAHalfDroppedFromTheEnabledListFallsBackToTheSiteWideOne(): void
    {
        $this->settings->enabledThemes = ['dark'];
        $this->settings->defaultTheme = 'dark';
        $this->settings->autoDarkTheme = 'dark';

        // A real bundled theme the admin has since left off the enabled list.
        $user = $this->signIn([Themes::PREF_DARK_KEY => 'dim']);

        self::assertSame('dark', $this->themes->getAutoPairForUser($user)[1]);
    }

    /**
     * A dark theme on the light side would leave one end of the pair unreachable.
     */
    public function testAHalfHoldingTheWrongSchemeIsIgnored(): void
    {
        $this->settings->autoLightTheme = 'stone';

        $user = $this->signIn([Themes::PREF_LIGHT_KEY => 'dim']);

        self::assertSame('stone', $this->themes->getAutoPairForUser($user)[0]);
    }

    public function testAUsersPairIsIgnoredWhenOverridesAreOff(): void
    {
        $this->settings->allowUserOverride = false;
        $this->settings->autoLightTheme = 'stone';
        $this->settings->autoDarkTheme = 'dark';

        $user = $this->signIn([
            Themes::PREF_LIGHT_KEY => 'high-contrast',
            Themes::PREF_DARK_KEY => 'dim',
        ]);

        self::assertSame(['stone', 'dark'], $this->themes->getAutoPairForUser($user));
    }

    /**
     * Nobody signed in means nobody to have a preference, so the pair is the admin's.
     */
    public function testTheSiteWidePairAppliesWhenNobodyIsSignedIn(): void
    {
        $this->settings->autoLightTheme = 'high-contrast';
        $this->settings->autoDarkTheme = 'dim';

        self::assertSame(['high-contrast', 'dim'], $this->themes->getAutoPairForUser());
    }

    /**
     * @return string[]
     */
    private function handlesToLoad(string $handle): array
    {
        return array_values(array_map(
            static fn(Theme $theme) => $theme->handle,
            $this->themes->getThemesToLoad($handle),
        ));
    }
}
