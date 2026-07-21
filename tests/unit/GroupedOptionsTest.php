<?php

namespace bensomething\cast\tests\unit;

use bensomething\cast\models\Theme;
use bensomething\cast\tests\TestCase;

/**
 * How themes are laid out for the pickers: grouped by scheme, as the flat optgroup
 * markers Craft's `forms/select` reads.
 */
class GroupedOptionsTest extends TestCase
{
    public function testGroupsTheBundledThemesByScheme(): void
    {
        $options = $this->themes->toGroupedOptions($this->themes->getAllThemes());

        self::assertSame([
            ['optgroup' => 'Light'],
            ['label' => 'Craft Default', 'value' => ''],
            ['label' => 'Stone', 'value' => 'stone'],
            ['label' => 'High Contrast', 'value' => 'high-contrast'],
            ['optgroup' => 'Dark'],
            ['label' => 'Dark', 'value' => 'dark'],
            ['label' => 'Dim', 'value' => 'dim'],
            ['label' => 'Stone Dark', 'value' => 'stone-dark'],
            ['label' => 'High Contrast Dark', 'value' => 'high-contrast-dark'],
        ], $options);
    }

    public function testCraftDefaultCanBeLeftOut(): void
    {
        $options = $this->themes->toGroupedOptions($this->themes->getAllThemes(), false);

        self::assertNotContains(['label' => 'Craft Default', 'value' => ''], $options);
        self::assertSame(['optgroup' => 'Light'], $options[0]);
    }

    /**
     * An empty heading would read as a group whose themes had gone missing, rather than
     * as one that was never there.
     */
    public function testAnEmptyGroupIsOmitted(): void
    {
        $dark = array_filter(
            $this->themes->getAllThemes(),
            static fn(Theme $theme) => $theme->getIsDark(),
        );

        $options = $this->themes->toGroupedOptions($dark, false);

        self::assertSame(['optgroup' => 'Dark'], $options[0]);
        self::assertNotContains(['optgroup' => 'Light'], $options);
    }

    /**
     * With nothing to group, the light group still has Craft's own appearance in it, so
     * the option can't vanish along with the themes.
     */
    public function testCraftDefaultSurvivesWithNoThemesAtAll(): void
    {
        self::assertSame([
            ['optgroup' => 'Light'],
            ['label' => 'Craft Default', 'value' => ''],
        ], $this->themes->toGroupedOptions([]));

        self::assertSame([], $this->themes->toGroupedOptions([], false));
    }
}
