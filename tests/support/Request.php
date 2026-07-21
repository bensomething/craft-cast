<?php

namespace bensomething\cast\tests\support;

use yii\console\Request as ConsoleRequest;

/**
 * A console request that also answers `getIsCpRequest()`, which `Plugin::init()` checks.
 *
 * False, so init() stops before registering the view hooks and event handlers that a
 * rendering control panel needs. What those handlers do is the plugin's web layer;
 * what they're handed is the service, which is what these tests are about.
 */
class Request extends ConsoleRequest
{
    public function getIsCpRequest(): bool
    {
        return false;
    }
}
