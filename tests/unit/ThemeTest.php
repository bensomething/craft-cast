<?php

namespace bensomething\cast\tests\unit;

use bensomething\cast\models\Theme;
use bensomething\cast\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A theme's handle reaches the DOM as `data-cast-theme` and, for discovered themes, comes
 * straight off a filename, so the validation rules are the only thing standing between a
 * stylesheet someone dropped in a folder and the CP's markup.
 */
class ThemeTest extends TestCase
{
    public function testValidatesAWellFormedTheme(): void
    {
        $theme = new Theme([
            'handle' => 'midnight-blue',
            'name' => 'Midnight Blue',
            'colorScheme' => Theme::SCHEME_DARK,
        ]);

        self::assertTrue($theme->validate(), print_r($theme->getErrors(), true));
    }

    public function testRequiresAHandleAndName(): void
    {
        $theme = new Theme(['colorScheme' => Theme::SCHEME_LIGHT]);

        self::assertFalse($theme->validate());
        self::assertArrayHasKey('handle', $theme->getErrors());
        self::assertArrayHasKey('name', $theme->getErrors());
    }

    #[DataProvider('badHandles')]
    public function testRejectsAHandleThatCantBeAnAttributeValue(string $handle): void
    {
        $theme = new Theme(['handle' => $handle, 'name' => 'Name']);

        self::assertFalse($theme->validate(), 'Expected “' . $handle . '” to be rejected.');
        self::assertArrayHasKey('handle', $theme->getErrors());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badHandles(): array
    {
        return [
            'spaces' => ['midnight blue'],
            'uppercase' => ['Midnight'],
            'leading digit' => ['1970s'],
            'leading dash' => ['-dark'],
            'underscore' => ['dark_mode'],
            'quote' => ['dark"'],
        ];
    }

    public function testRejectsAnUnknownColorScheme(): void
    {
        $theme = new Theme([
            'handle' => 'sepia',
            'name' => 'Sepia',
            'colorScheme' => 'warm',
        ]);

        self::assertFalse($theme->validate());
        self::assertArrayHasKey('colorScheme', $theme->getErrors());
    }

    public function testIsDarkFollowsTheColorScheme(): void
    {
        self::assertTrue((new Theme(['colorScheme' => Theme::SCHEME_DARK]))->getIsDark());
        self::assertFalse((new Theme(['colorScheme' => Theme::SCHEME_LIGHT]))->getIsDark());
    }

    public function testDefaultsToLight(): void
    {
        self::assertFalse((new Theme())->getIsDark());
    }
}
