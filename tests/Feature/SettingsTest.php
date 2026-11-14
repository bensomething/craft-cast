<?php

declare(strict_types=1);

namespace bensomething\cast\tests\Feature;

use bensomething\cast\Settings;
use bensomething\cast\tests\TestCase;

final class SettingsTest extends TestCase
{
    /** @param array<string, mixed> $values */
    private function make(array $values): Settings
    {
        $settings = new Settings;
        $settings->setAttributes($values);

        return $settings;
    }

    public function test_the_defaults_are_valid(): void
    {
        $this->assertTrue(new Settings()->validate());
    }

    public function test_bundled_themes_and_colours_are_valid(): void
    {
        $settings = $this->make([
            'autoLightTheme' => 'stone',
            'autoDarkTheme' => 'dim',
            'defaultButtonColor' => 'red',
            'highContrast' => true,
        ]);

        $this->assertTrue($settings->validate(), json_encode($settings->errors()->toArray()));
    }

    public function test_a_theme_offered_for_the_other_mode_is_not(): void
    {
        $settings = $this->make(['autoLightTheme' => 'dim']);

        $this->assertFalse($settings->validate());
        $this->assertSame(['The selected Light Appearance is invalid.'], $settings->errors()->get('autoLightTheme'));
    }

    public function test_unknown_values_are_not(): void
    {
        $settings = $this->make(['autoDarkTheme' => 'nope', 'defaultButtonColor' => 'orange']);

        $this->assertFalse($settings->validate());
        $this->assertTrue($settings->errors()->has('autoDarkTheme'));
        $this->assertTrue($settings->errors()->has('defaultButtonColor'));
    }

    public function test_custom_themes_validate_and_are_saved_tidied(): void
    {
        $settings = $this->make(['customThemes' => [
            ['rowId' => 'abc', 'name' => '  Sea  ', 'color' => '#0D9', 'tint' => 'strong', 'preview' => true],
            ['name' => '', 'color' => ''],
        ]]);

        $this->assertTrue($settings->validate(), json_encode($settings->errors()->toArray()));
        $this->assertSame(
            [['rowId' => 'abc', 'name' => 'Sea', 'color' => '#00dd99', 'tint' => 'strong']],
            $settings->validationData()['customThemes'],
            'colour normalised, name trimmed, Preview and the empty row dropped',
        );
    }

    public function test_a_bad_custom_theme_is_refused(): void
    {
        $settings = $this->make(['customThemes' => [
            ['name' => str_repeat('x', 41), 'color' => 'not a colour', 'tint' => 'loud'],
            ['name' => '', 'color' => '#0d9488', 'tint' => 'subtle'],
        ]]);

        $this->assertFalse($settings->validate());

        foreach (['customThemes.0.name', 'customThemes.0.color', 'customThemes.0.tint', 'customThemes.1.name'] as $key) {
            $this->assertTrue($settings->errors()->has($key), $key);
        }
    }

    public function test_1x_handles_read_as_what_they_now_mean_and_validate(): void
    {
        $settings = $this->make(['autoDarkTheme' => 'stone-dark', 'autoLightTheme' => 'high-contrast']);

        $this->assertSame('stone', $settings->validationData()['autoDarkTheme']);
        $this->assertSame('', $settings->validationData()['autoLightTheme']);
        $this->assertTrue($settings->validate());

        $this->assertSame('', $this->make(['autoDarkTheme' => 'dark'])->validationData()['autoDarkTheme']);
    }

    public function test_a_1x_config_file_still_loads(): void
    {
        // Every key 1.x's config.php sample set, including the ones 2.x ignores.
        $settings = $this->make([
            'buttonColor' => 'red',
            'defaultTheme' => 'auto',
            'allowUserOverride' => true,
            'enabledThemes' => '*',
            'autoDarkTheme' => 'dark',
            'autoLightTheme' => '',
            'themesPath' => '@root/cast-themes',
        ]);

        $this->assertTrue($settings->validate());
        $this->assertSame('', $settings->defaultButtonColor, '1.x red, Craft 5 default, is not carried over');
    }
}
