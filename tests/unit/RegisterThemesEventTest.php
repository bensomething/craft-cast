<?php

namespace bensomething\cast\tests\unit;

use bensomething\cast\events\RegisterThemesEvent;
use bensomething\cast\models\Theme;
use bensomething\cast\services\Themes;
use bensomething\cast\tests\TestCase;
use yii\base\Event;

/**
 * The documented way for another plugin to add a theme. It's public API, so the shape of
 * what a listener receives and what happens to what it leaves behind both matter.
 */
class RegisterThemesEventTest extends TestCase
{
    public function testAListenerCanAddATheme(): void
    {
        $this->listen(static function(RegisterThemesEvent $event) {
            $event->themes['midnight'] = new Theme([
                'handle' => 'midnight',
                'name' => 'Midnight',
                'colorScheme' => Theme::SCHEME_DARK,
                'url' => 'https://example.test/midnight.css',
            ]);
        });

        $theme = $this->themes->getThemeByHandle('midnight');

        self::assertNotNull($theme);
        self::assertSame('Midnight', $theme->name);
        self::assertSame('https://example.test/midnight.css', $theme->url);
        self::assertSame(Themes::SOURCE_PLUGIN, $this->themes->getThemeSource('midnight'));
    }

    public function testAListenerSeesTheBundledAndDiscoveredThemes(): void
    {
        $this->writeTheme('midnight.css', '');

        $seen = [];

        $this->listen(static function(RegisterThemesEvent $event) use (&$seen) {
            $seen = array_keys($event->themes);
        });

        $this->themes->getAllThemes();

        self::assertSame(['dark', 'dim', 'high-contrast', 'high-contrast-dark', 'midnight'], $seen);
    }

    public function testAListenerCanReplaceABundledTheme(): void
    {
        $this->listen(static function(RegisterThemesEvent $event) {
            $event->themes['dark'] = new Theme([
                'handle' => 'dark',
                'name' => 'Darker',
                'colorScheme' => Theme::SCHEME_DARK,
                'url' => 'https://example.test/darker.css',
            ]);
        });

        $theme = $this->themes->getThemeByHandle('dark');

        self::assertNotNull($theme);
        self::assertSame('Darker', $theme->name);
        self::assertSame(Themes::SOURCE_PLUGIN, $this->themes->getThemeSource('dark'));
    }

    public function testAListenerCanRemoveATheme(): void
    {
        $this->listen(static function(RegisterThemesEvent $event) {
            unset($event->themes['dim']);
        });

        self::assertNull($this->themes->getThemeByHandle('dim'));
    }

    /**
     * A registered theme without a URL is assumed to sit alongside the bundled ones, so
     * one that means to point elsewhere has to say so.
     */
    public function testAThemeRegisteredWithoutAUrlGetsOne(): void
    {
        $this->listen(static function(RegisterThemesEvent $event) {
            $event->themes['midnight'] = new Theme([
                'handle' => 'midnight',
                'name' => 'Midnight',
                'colorScheme' => Theme::SCHEME_DARK,
            ]);
        });

        $theme = $this->themes->getThemeByHandle('midnight');

        self::assertNotNull($theme);
        self::assertSame($this->themes->getBaseUrl() . '/themes/midnight.css', $theme->url);
    }

    /**
     * Asking mid-event is what a listener deciding whether to register does, and the
     * answer has to arrive without re-entering the event that's already running.
     */
    public function testAskingForASourceMidEventDoesntRecurse(): void
    {
        $rounds = 0;
        $source = null;

        $this->listen(function(RegisterThemesEvent $event) use (&$rounds, &$source) {
            $rounds++;
            $source = $this->themes->getThemeSource('dark');
        });

        $this->themes->getAllThemes();

        self::assertSame(1, $rounds);
        self::assertSame(Themes::SOURCE_BUNDLED, $source);
    }

    public function testThemesAreCompiledOnce(): void
    {
        $rounds = 0;

        $this->listen(static function() use (&$rounds) {
            $rounds++;
        });

        $this->themes->getAllThemes();
        $this->themes->getAllThemes();
        $this->themes->getEnabledThemes();

        self::assertSame(1, $rounds);
    }

    private function listen(callable $handler): void
    {
        Event::on(Themes::class, Themes::EVENT_REGISTER_THEMES, $handler);
    }
}
