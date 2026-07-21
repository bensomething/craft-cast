<?php

namespace bensomething\cast\tests\support;

use craft\services\Config;
use yii\console\Application as ConsoleApplication;

/**
 * A stand-in for `Craft::$app`.
 *
 * Cast has no database, no migrations and no element types of its own, so booting a real
 * Craft to test it would mean a MySQL service in CI for the sake of code that never
 * issues a query. This is a Yii console application with the handful of methods Craft's
 * own classes reach for filled in, which is enough to construct the plugin, its settings
 * model, and a User to read preferences off.
 *
 * Anything not implemented here throws an unknown-method error rather than answering
 * wrongly, so a test straying into territory this can't honestly fake fails loudly.
 */
class Application extends ConsoleApplication
{
    /**
     * Craft's element base class asks on every instantiation, and `Craft::autoload()`
     * asks before generating `CustomFieldBehavior`. Answering false gets the empty
     * in-memory behaviour, which is what a plugin with no custom fields wants.
     */
    public function getIsInstalled(bool $strict = false): bool
    {
        return false;
    }

    /**
     * Craft's config service, which its User element asks for on construction. Real, but
     * pointed at an empty config directory, so it answers with the stock defaults.
     */
    public function getConfig(): Config
    {
        /** @var Config $component */
        $component = $this->get('config');

        return $component;
    }

    /**
     * Craft's user component, as far as Cast uses it: an identity, or none.
     */
    public function getUser(): UserComponent
    {
        /** @var UserComponent $component */
        $component = $this->get('user');

        return $component;
    }

    /**
     * Sets who's logged in for the rest of the test.
     */
    public function setIdentity(?TestUser $user): void
    {
        $this->getUser()->identity = $user;
    }
}
