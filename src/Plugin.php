<?php

declare(strict_types=1);

namespace bensomething\cast;

use bensomething\cast\Http\Middleware\RegisterHeadScript;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Plugin\PluginSettings;
use Illuminate\Routing\Router;
use Override;

/**
 * Cast: colour modes for the control panel, Craft 6 line.
 *
 * Starting point only. 1.x lives on `develop`; `docs/craft-6.md` records why it doesn't
 * carry over and what's still Cast's to do on Craft 6.
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '2.0.0';

    public function boot(Router $router): void
    {
        $router->pushMiddlewareToGroup('craft.cp', RegisterHeadScript::class);
    }

    #[Override]
    protected static function createSettings(): ?PluginSettings
    {
        return new Settings;
    }
}
