<?php

declare(strict_types=1);

namespace bensomething\cast\tests\Feature;

use bensomething\cast\Http\Middleware\RegisterHeadScript;
use bensomething\cast\Plugin;
use bensomething\cast\tests\TestCase;
use bensomething\cast\Themes;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Plugin\Plugins;

/** How Cast boots, and that it comes and goes cleanly. */
final class PluginTest extends TestCase
{
    public function test_the_head_script_runs_on_every_cp_route(): void
    {
        $this->assertContains(
            RegisterHeadScript::class,
            app('router')->getMiddlewareGroups()['craft.cp'] ?? [],
        );
    }

    public function test_every_listed_asset_exists(): void
    {
        $plugin = Plugin::getInstance();

        foreach (['styles', 'scripts'] as $property) {
            $assets = (fn () => $this->$property)->call($plugin);
            $this->assertNotEmpty($assets, $property);

            foreach (array_keys($assets) as $source) {
                $this->assertFileExists($source);
            }
        }
    }

    public function test_it_uninstalls_and_reinstalls_cleanly(): void
    {
        $plugins = app(Plugins::class);
        $this->setPrefs([Themes::PREF_DARK => 'dim']);

        $this->assertTrue($plugins->uninstallPlugin('cast'));
        $this->assertFalse($plugins->isPluginInstalled('cast'));

        $this->assertTrue($plugins->installPlugin('cast'));
        $this->assertTrue($plugins->isPluginInstalled('cast'));

        // Users' choices live in their preferences, which uninstalling leaves alone, so
        // reinstalling picks them back up.
        $this->assertSame('dim', $this->pref(Themes::PREF_DARK));
    }

    public function test_the_settings_screen_opens_read_only_where_admin_changes_are_off(): void
    {
        $this->signIn();
        Cms::config()->allowAdminChanges(false);

        $html = (string) $this->get('/admin/settings/plugins/cast')->assertOk()->getContent();
        $this->assertStringContainsString('Default Settings', $html);
        $this->assertStringContainsString('"mode":"readOnly"', $html);

        $this->postJson('/admin/settings/plugins/cast', ['settings' => ['autoDarkTheme' => 'dim']])->assertForbidden();
    }
}
