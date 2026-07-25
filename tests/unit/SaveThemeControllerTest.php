<?php

namespace bensomething\cast\tests\unit;

use bensomething\cast\controllers\PreferencesController;
use bensomething\cast\models\Settings;
use bensomething\cast\Plugin;
use bensomething\cast\services\Themes;
use bensomething\cast\tests\support\UsersService;
use bensomething\cast\tests\support\WebRequest;
use bensomething\cast\tests\TestCase;
use yii\web\Response;

/**
 * The account-menu action that saves a user's own theme choice: who it lets through, what
 * it accepts, and what it hands Craft to store.
 */
class SaveThemeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The controller reaches for the plugin through its singleton, not the one the
        // test holds; point them at the same object.
        Plugin::setInstance($this->plugin);

        $this->users()->reset();
    }

    public function testRefusesWhenNobodyIsSignedIn(): void
    {
        $response = $this->save('dark');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Theme can’t be changed.', $response->data['message']);
        self::assertNull($this->users()->savedPreferences, 'Nothing should have been saved.');
    }

    public function testRefusesWhenOverridesAreOff(): void
    {
        $this->settings->allowUserOverride = false;
        $this->signIn();

        $response = $this->save('dark');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Theme can’t be changed.', $response->data['message']);
        self::assertNull($this->users()->savedPreferences);
    }

    public function testRejectsAThemeThatIsntOnOffer(): void
    {
        $this->signIn();

        $response = $this->save('a-theme-that-doesnt-exist');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('That theme isn’t available.', $response->data['message']);
        self::assertNull($this->users()->savedPreferences);
    }

    public function testRejectsAThemeThatWasDisabled(): void
    {
        $this->settings->enabledThemes = ['dark'];
        $this->settings->defaultTheme = 'dark';
        $this->signIn();

        // 'dim' is a real bundled theme, but the admin has left it off the enabled list.
        $response = $this->save('dim');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('That theme isn’t available.', $response->data['message']);
        self::assertNull($this->users()->savedPreferences);
    }

    public function testSavesAnEnabledTheme(): void
    {
        $user = $this->signIn();

        $response = $this->save('dark');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($user, $this->users()->savedUser);
        self::assertSame([Themes::PREF_KEY => 'dark'], $this->users()->savedPreferences);
    }

    /**
     * Inherit, auto and no-theme aren't handles the discovery ever produces, but they're
     * legitimate choices the picker offers, so the action has to let them through.
     */
    public function testAcceptsTheThreeNonThemeChoices(): void
    {
        foreach ([Settings::THEME_INHERIT, Settings::THEME_AUTO, Settings::THEME_NONE] as $choice) {
            $this->users()->reset();
            $this->signIn();

            $response = $this->save($choice);

            self::assertSame(200, $response->getStatusCode(), "Choice '$choice' should be accepted.");
            self::assertSame([Themes::PREF_KEY => $choice], $this->users()->savedPreferences);
        }
    }

    /**
     * The controller passes only its own key. Craft merges that into whatever else the
     * user has stored, which is the whole reason it doesn't reuse `users/save-preferences`.
     */
    public function testLeavesOtherPreferencesAlone(): void
    {
        $user = $this->signIn(['locale' => 'en', Themes::PREF_KEY => 'dim']);

        $this->save('dark');

        self::assertSame([Themes::PREF_KEY => 'dark'], $this->users()->savedPreferences);
        self::assertSame('en', $user->getPreferences()['locale'], 'An unrelated preference should survive.');
        self::assertSame('dark', $user->getPreferences()[Themes::PREF_KEY]);
    }

    /**
     * Runs the action with `theme` posted, and hands back the response it built.
     */
    private function save(string $theme): Response
    {
        $request = new WebRequest();
        $request->bodyParams = ['theme' => $theme];

        $controller = new PreferencesController('cast', $this->plugin, [
            'request' => $request,
            'response' => new Response(),
        ]);

        /** @var Response $response */
        $response = $controller->actionSaveTheme();

        return $response;
    }

    private function users(): UsersService
    {
        return $this->app()->getUsers();
    }
}
