<?php

namespace bensomething\cast\tests;

use bensomething\cast\models\Settings;
use bensomething\cast\Plugin;
use bensomething\cast\services\Themes;
use bensomething\cast\tests\support\Application;
use bensomething\cast\tests\support\Dir;
use bensomething\cast\tests\support\TestUser;
use Craft;
use PHPUnit\Framework\TestCase as BaseTestCase;
use yii\base\Event;

/**
 * A fresh plugin instance and an empty themes folder per test.
 *
 * The service caches everything it discovers on first call, and the plugin registers
 * event handlers in its constructor, so both are rebuilt rather than reset between tests.
 */
abstract class TestCase extends BaseTestCase
{
    protected Plugin $plugin;

    protected Settings $settings;

    protected Themes $themes;

    /** Absolute path to this test's themes folder. */
    protected string $themesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themesPath = Craft::getAlias('@root') . '/' . $this->name() . '-' . uniqid();
        Dir::create($this->themesPath);

        $this->plugin = new Plugin('cast', null, Plugin::config() + [
            'basePath' => dirname(__DIR__) . '/src',
        ]);

        /** @var Settings $settings */
        $settings = $this->plugin->getSettings();
        $this->settings = $settings;
        $this->settings->themesPath = $this->themesPath;

        $this->themes = $this->plugin->themes;
    }

    protected function tearDown(): void
    {
        // Registered by the plugin constructor and by tests listening for
        // EVENT_REGISTER_THEMES. Class-level handlers outlive the objects that added them.
        Event::offAll();

        $this->app()->setIdentity(null);

        Dir::remove($this->themesPath);

        parent::tearDown();
    }

    protected function app(): Application
    {
        /** @var Application $app */
        $app = Craft::$app;

        return $app;
    }

    /**
     * Writes a stylesheet into this test's themes folder.
     */
    protected function writeTheme(string $filename, string $contents = ''): string
    {
        $path = $this->themesPath . '/' . $filename;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * Signs a user in with the given preferences.
     *
     * @param array<string, mixed> $preferences
     */
    protected function signIn(array $preferences = []): TestUser
    {
        $user = new TestUser(['id' => 1, 'testPreferences' => $preferences]);

        $this->app()->setIdentity($user);

        return $user;
    }
}
