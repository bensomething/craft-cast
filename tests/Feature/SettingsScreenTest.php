<?php

declare(strict_types=1);

namespace bensomething\cast\tests\Feature;

use bensomething\cast\Plugin;
use bensomething\cast\Settings;
use bensomething\cast\tests\TestCase;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;

final class SettingsScreenTest extends TestCase
{
    private const string URL = '/admin/settings/plugins/cast';

    public function test_it_opens(): void
    {
        $this->signIn();

        $html = (string) $this->get(self::URL)->assertOk()->getContent();

        foreach (['Light Appearance', 'Dark Appearance', 'Primary Button Colour', 'Craft Red', 'Increase Contrast', 'Let Users Choose', 'Themes', 'Available Themes', 'Custom Themes', 'Base Colour', 'Tint', 'Preview'] as $label) {
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

    public function test_it_saves_custom_themes_without_the_preview(): void
    {
        $this->signIn();

        $this->postJson(self::URL, ['settings' => ['customThemes' => [
            ['rowId' => 'b99938a4-0a50-48ea-8d0e-c53d65126729', 'name' => 'Sea', 'color' => '#0D9488', 'tint' => 'medium', 'preview' => ['dark']],
        ]]])->assertOk();

        $this->assertSame(
            [['rowId' => 'b99938a4-0a50-48ea-8d0e-c53d65126729', 'name' => 'Sea', 'color' => '#0d9488', 'tint' => 'medium']],
            ProjectConfigHelper::unpackAssociativeArrays(app(ProjectConfig::class)->get('plugins.cast.settings.customThemes')),
        );
    }

    public function test_it_rejects_a_custom_theme_without_a_colour(): void
    {
        $this->signIn();

        $this->postJson(self::URL, ['settings' => ['customThemes' => [['name' => 'Sea', 'color' => '', 'tint' => 'subtle']]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.customThemes.0.color']);
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

    public function test_settings_from_the_config_file_are_read_only_and_say_why(): void
    {
        $this->signIn();
        config(['craft.cast' => ['autoDarkTheme' => 'dim', 'highContrast' => true]]);

        $fields = $this->fields();

        $this->assertSame('readOnly', $fields['autoDarkTheme']['mode']);
        $this->assertStringContainsString('config/craft/cast.php', $fields['autoDarkTheme']['warning']);
        $this->assertSame('readOnly', $fields['highContrast']['mode']);

        $this->assertNotSame('readOnly', $fields['autoLightTheme']['mode']);
        $this->assertNull($fields['autoLightTheme']['warning']);
    }

    public function test_the_config_sample_is_where_craft_publishes_from(): void
    {
        $sample = dirname(__DIR__, 2).'/config/cast.php';

        $this->assertFileExists($sample);
        $this->assertIsArray(require $sample);
    }

    /**
     * Each field on the settings screen, by setting, with its control's mode and the
     * field's warning.
     *
     * @return array<string, array{mode: ?string, warning: ?string}>
     */
    private function fields(): array
    {
        $html = (string) $this->get(self::URL)->assertOk()->getContent();
        preg_match('/<script data-page="app"[^>]*>(.*?)<\/script>/s', $html, $m);
        $nodes = json_decode($m[1], true)['props']['ui']['nodes'];

        $fields = [];

        foreach ($nodes as $node) {
            if (isset($node['control']['path'])) {
                $fields[end($node['control']['path'])] = [
                    'mode' => $node['control']['mode'] ?? null,
                    'warning' => $node['props']['warning'] ?? null,
                ];
            }
        }

        return $fields;
    }
}
