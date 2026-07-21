<?php

namespace bensomething\cast\tests\unit;

use bensomething\cast\models\Settings;
use bensomething\cast\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SettingsTest extends TestCase
{
    public function testDefaultsToAutoWithUserOverridesOn(): void
    {
        $settings = new Settings();

        self::assertSame(Settings::THEME_AUTO, $settings->defaultTheme);
        self::assertTrue($settings->allowUserOverride);
        self::assertSame('*', $settings->enabledThemes);
        self::assertSame(Settings::BUTTON_COLOR_DEFAULT, $settings->buttonColor);
    }

    /**
     * @param array<int, string>|string $enabled
     */
    #[DataProvider('emptyThemeLists')]
    public function testAnEmptyThemeListMeansAll(array|string $enabled): void
    {
        $settings = new Settings(['enabledThemes' => $enabled]);

        self::assertTrue($settings->validate());
        self::assertSame('*', $settings->enabledThemes);
    }

    /**
     * @return array<string, array{array<int, string>|string}>
     */
    public static function emptyThemeLists(): array
    {
        return [
            'nothing ticked' => [[]],
            'empty string' => [''],
        ];
    }

    public function testKeepsANonEmptyThemeList(): void
    {
        $settings = new Settings(['enabledThemes' => ['dark', 'dim']]);

        self::assertTrue($settings->validate());
        self::assertSame(['dark', 'dim'], $settings->enabledThemes);
    }

    #[DataProvider('buttonColorHandles')]
    public function testAcceptsEveryOfferedButtonColor(string $handle): void
    {
        $settings = new Settings(['buttonColor' => $handle]);

        self::assertTrue($settings->validate(), print_r($settings->getErrors(), true));
    }

    public function testRejectsAButtonColorItDoesntOffer(): void
    {
        $settings = new Settings(['buttonColor' => 'chartreuse']);

        self::assertFalse($settings->validate());
        self::assertArrayHasKey('buttonColor', $settings->getErrors());
    }

    /**
     * Both shapes are read positionally by `Plugin::buttonColorValues()`, which indexes
     * `shades` by mode and destructures `values` into four. A malformed entry would only
     * show up as a broken button in the CP.
     */
    #[DataProvider('buttonColorHandles')]
    public function testEveryButtonColorIsOneOfTheTwoShapes(string $handle): void
    {
        $spec = Settings::BUTTON_COLORS[$handle];

        if (isset($spec['shades'])) {
            self::assertCount(2, $spec['shades'], 'Expected a light and a dark shade.');
            self::assertSame(array_values($spec['shades']), $spec['shades']);

            return;
        }

        self::assertSame(['light', 'dark'], array_keys($spec['values']));

        foreach ($spec['values'] as $mode => $values) {
            self::assertCount(4, $values, "Expected fill, hover, active and label for $mode.");
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function buttonColorHandles(): array
    {
        return array_combine(
            array_keys(Settings::BUTTON_COLORS),
            array_map(static fn(string $handle) => [$handle], array_keys(Settings::BUTTON_COLORS)),
        );
    }

    public function testTheDefaultButtonColorIsOffered(): void
    {
        self::assertArrayHasKey(Settings::BUTTON_COLOR_DEFAULT, Settings::BUTTON_COLORS);
    }
}
