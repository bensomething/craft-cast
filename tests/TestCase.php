<?php

declare(strict_types=1);

namespace bensomething\cast\tests;

use bensomething\cast\Plugin;
use bensomething\cast\Settings;
use CraftCms\Cms\Plugin\Testing\PluginTestCase;
use CraftCms\Cms\User\Elements\User as UserElement;
use CraftCms\Cms\User\Models\User as UserModel;
use CraftCms\Cms\User\Users;
use Override;

/**
 * Boots Craft 6 on an in-memory SQLite database, runs its install, and installs Cast
 * from this package's composer.json, through Craft's own plugin test case.
 */
abstract class TestCase extends PluginTestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $config = $app->make('config');
        $config->set('database.default', 'sqlite');
        $config->set('database.connections.sqlite.database', ':memory:');
    }

    /** Signs in as the admin Craft's install created. */
    protected function signIn(): UserModel
    {
        $user = UserModel::first();
        $this->actingAs($user, 'craft');

        return $user;
    }

    protected function admin(): UserElement
    {
        return UserElement::findOne();
    }

    /** @param array<string, mixed> $prefs */
    protected function setPrefs(array $prefs): void
    {
        app(Users::class)->saveUserPreferences($this->admin(), $prefs);
    }

    /** A fresh read of one of the admin's preferences. */
    protected function pref(string $key): mixed
    {
        return UserElement::findOne()->getPreference($key);
    }

    /** @param array<string, mixed> $values */
    protected function setSettings(array $values): Settings
    {
        $plugin = Plugin::getInstance();
        $plugin->setSettings($values);

        /** @var Settings */
        return $plugin->getSettings();
    }

    /** The CP URL of one of Cast's actions. */
    protected function castAction(string $action): string
    {
        return "/admin/actions/cast/$action";
    }
}
