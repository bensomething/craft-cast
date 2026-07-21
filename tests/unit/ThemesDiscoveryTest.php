<?php

namespace bensomething\cast\tests\unit;

use bensomething\cast\models\Theme;
use bensomething\cast\services\Themes;
use bensomething\cast\tests\TestCase;
use Craft;

/**
 * What the service makes of the themes folder: which files become themes, what their
 * headers say about them, and what happens to the ones that can't.
 */
class ThemesDiscoveryTest extends TestCase
{
    public function testShipsWithTheBundledThemes(): void
    {
        $themes = $this->themes->getAllThemes();

        self::assertSame(
            ['dark', 'dim', 'high-contrast', 'high-contrast-dark'],
            array_keys($themes),
        );

        foreach ($themes as $handle => $theme) {
            self::assertSame($handle, $theme->handle, 'Themes are keyed by handle.');
            self::assertSame(Themes::SOURCE_BUNDLED, $this->themes->getThemeSource($handle));
        }
    }

    public function testReadsAThemesHeader(): void
    {
        $this->writeTheme('midnight.css', <<<'CSS'
/**
 * Theme Name: Midnight Blue
 * Description: Blue-black, for the small hours.
 * Color Scheme: dark
 */
html[data-cast-theme="midnight"] { --gray-100: #000; }
CSS);

        $theme = $this->themes->getThemeByHandle('midnight');

        self::assertNotNull($theme);
        self::assertSame('Midnight Blue', $theme->name);
        self::assertSame('Blue-black, for the small hours.', $theme->description);
        self::assertSame(Theme::SCHEME_DARK, $theme->colorScheme);
        self::assertTrue($theme->getIsDark());
        self::assertSame(Themes::SOURCE_FOLDER, $this->themes->getThemeSource('midnight'));
    }

    public function testATitleCasedFilenameStandsInForAMissingHeader(): void
    {
        $this->writeTheme('warm-paper.css', 'html { }');

        $theme = $this->themes->getThemeByHandle('warm-paper');

        self::assertNotNull($theme);
        self::assertSame('Warm Paper', $theme->name);
        self::assertNull($theme->description);
        self::assertSame(Theme::SCHEME_LIGHT, $theme->colorScheme, 'Themes are light unless they say otherwise.');
    }

    public function testHeaderFieldsAreCaseInsensitive(): void
    {
        $this->writeTheme('shouty.css', <<<'CSS'
/*
    THEME NAME:   Shouty
    color scheme: DARK
*/
CSS);

        $theme = $this->themes->getThemeByHandle('shouty');

        self::assertNotNull($theme);
        self::assertSame('Shouty', $theme->name);
        self::assertSame(Theme::SCHEME_DARK, $theme->colorScheme);
    }

    /**
     * Only the first comment is the header, and only the first 8KB of the file is read
     * looking for it. A theme is mostly declarations; a `Color Scheme:` further down is
     * someone's note to themselves, not a header field.
     */
    public function testOnlyTheFirstCommentCounts(): void
    {
        $this->writeTheme('first.css', <<<'CSS'
/*
 * Theme Name: First
 */
html { }
/*
 * Theme Name: Second
 * Color Scheme: dark
 */
CSS);

        $theme = $this->themes->getThemeByHandle('first');

        self::assertNotNull($theme);
        self::assertSame('First', $theme->name);
        self::assertSame(Theme::SCHEME_LIGHT, $theme->colorScheme);
    }

    public function testIgnoresUnderscoredPartialsAndNonStylesheets(): void
    {
        $this->writeTheme('_shared.css', '/* Theme Name: Shared */');
        $this->writeTheme('notes.txt', '/* Theme Name: Notes */');

        self::assertNull($this->themes->getThemeByHandle('_shared'));
        self::assertNull($this->themes->getThemeByHandle('notes'));

        self::assertSame([], $this->themes->getIgnoredFiles(), 'Neither is a failure worth reporting.');
    }

    public function testReportsAFileWhoseNameCantBeAHandle(): void
    {
        $this->writeTheme('Midnight Blue.css', '/* Theme Name: Midnight Blue */');

        self::assertCount(1, $this->themes->getIgnoredFiles());
        self::assertSame('Midnight Blue.css', $this->themes->getIgnoredFiles()[0]['file']);
        self::assertNotSame('', $this->themes->getIgnoredFiles()[0]['reason']);
    }

    public function testReportsAnInvalidColorSchemeRatherThanGuessing(): void
    {
        $this->writeTheme('sepia.css', "/*\n * Color Scheme: warm\n */");

        self::assertNull($this->themes->getThemeByHandle('sepia'));
        self::assertCount(1, $this->themes->getIgnoredFiles());
        self::assertSame('sepia.css', $this->themes->getIgnoredFiles()[0]['file']);
    }

    /**
     * A dropped-in `dark.css` mustn't redefine the Dark everyone's already set to.
     */
    public function testABundledHandleWinsAndTheClashIsReported(): void
    {
        $this->writeTheme('dark.css', "/*\n * Theme Name: Not Craft Dark\n */");

        $theme = $this->themes->getThemeByHandle('dark');

        self::assertNotNull($theme);
        self::assertSame('Dark', $theme->name);
        self::assertSame(Themes::SOURCE_BUNDLED, $this->themes->getThemeSource('dark'));
        self::assertSame('dark.css', $this->themes->getIgnoredFiles()[0]['file']);
    }

    public function testAMissingThemesFolderIsntAnError(): void
    {
        $this->settings->themesPath = $this->themesPath . '/nowhere';

        self::assertCount(4, $this->themes->getAllThemes());
        self::assertSame([], $this->themes->getIgnoredFiles());
    }

    public function testResolvesTheThemesPathThroughAliases(): void
    {
        $this->settings->themesPath = '@root/cast-themes/';

        self::assertSame(Craft::getAlias('@root') . '/cast-themes', $this->themes->getThemesPath());
    }

    public function testAnEmptyThemesPathIsNoPath(): void
    {
        $this->settings->themesPath = '';

        self::assertNull($this->themes->getThemesPath());
    }

    public function testBundledThemesResolveToTheirPublishedStylesheets(): void
    {
        $theme = $this->themes->getThemeByHandle('dark');

        self::assertNotNull($theme);
        self::assertSame($this->themes->getBaseUrl() . '/themes/dark.css', $theme->url);
    }

    public function testDiscoveredThemesResolveToTheirOwnPublishedFile(): void
    {
        $this->writeTheme('midnight.css', '');

        $theme = $this->themes->getThemeByHandle('midnight');

        self::assertNotNull($theme);
        self::assertStringEndsWith('/midnight.css', (string)$theme->url);
        self::assertStringNotContainsString('/themes/midnight.css', (string)$theme->url);
    }
}
