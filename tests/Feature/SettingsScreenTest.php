<?php

declare(strict_types=1);

namespace bensomething\cast\tests\Feature;

use bensomething\cast\Plugin;
use bensomething\cast\Settings;
use bensomething\cast\tests\TestCase;
use CraftCms\Cms\ProjectConfig\ProjectConfig;

final class SettingsScreenTest extends TestCase
{
    private const string URL = '/admin/settings/plugins/cast';

    public function test_it_opens(): void
    {
        $this->signIn();

        $html = (string) $this->get(self::URL)->assertOk()->getContent();

        foreach (['Light Appearance', 'Dark Appearance', 'Button Colour', 'Craft Red', 'Increase Contrast', 'Let Users Choose'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    public function test_it_saves(): void
    {
        $this->signIn();

        $this->postJson(self::URL, ['settings' => [
            'autoLightTheme' => 'stone',
            'autoDarkTheme' => 'dim',
            'defaultButtonColor' => 'teal',
            'highContrast' => '1',
            'allowUserOverride' => '',
        ]])->assertOk();

        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();

        $this->assertSame('stone', $settings->autoLightTheme);
        $this->assertSame('dim', $settings->autoDarkTheme);
        $this->assertSame('teal', $settings->defaultButtonColor);
        $this->assertTrue($settings->highContrast);
        $this->assertFalse($settings->allowUserOverride);
    }

    public function test_it_rejects_what_isnt_offered(): void
    {
        $this->signIn();

        $this->postJson(self::URL, ['settings' => ['autoDarkTheme' => 'nope', 'defaultButtonColor' => 'orange']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.autoDarkTheme', 'settings.defaultButtonColor']);

        // Craft leaves the rejected values on the in-memory model; what matters is that
        // none of them were saved.
        $this->assertNull(app(ProjectConfig::class)->get('plugins.cast.settings.autoDarkTheme'));
    }
}
