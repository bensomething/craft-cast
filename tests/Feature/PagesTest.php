<?php

declare(strict_types=1);

namespace bensomething\cast\tests\Feature;

use bensomething\cast\Plugin;
use bensomething\cast\tests\TestCase;
use bensomething\cast\Themes;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;

/** What Cast puts on control panel pages: the head stamp, and its preferences fields. */
final class PagesTest extends TestCase
{
    private function stamp(string $path = '/admin/dashboard'): string
    {
        $html = (string) $this->get($path)->assertOk()->getContent();

        return preg_match('/var d=document\.documentElement\.dataset;[^<]*/', $html, $m) ? $m[0] : '';
    }

    public function test_nothing_is_stamped_while_everything_is_crafts_own(): void
    {
        $this->signIn();

        $this->assertSame('', $this->stamp());
    }

    public function test_each_choice_is_stamped(): void
    {
        $this->signIn();
        $this->setPrefs([
            Themes::PREF_LIGHT => 'stone',
            Themes::PREF_DARK => 'dim',
            Themes::PREF_BUTTON => 'red',
            Themes::PREF_CONTRAST => true,
        ]);

        $stamp = $this->stamp();

        $this->assertStringContainsString('d.castLight="stone";', $stamp);
        $this->assertStringContainsString('d.castDark="dim";', $stamp);
        $this->assertStringContainsString('d.castButton="red";', $stamp);
        $this->assertStringContainsString('d.castContrast="more";', $stamp);
    }

    public function test_site_defaults_are_stamped_for_users_without_choices(): void
    {
        $this->signIn();
        $this->setSettings(['autoDarkTheme' => 'stone', 'defaultButtonColor' => 'teal']);

        $stamp = $this->stamp();

        $this->assertStringContainsString('d.castDark="stone";', $stamp);
        $this->assertStringContainsString('d.castButton="teal";', $stamp);
    }

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return [
            'dashboard' => ['/admin/dashboard'],
            'preferences' => ['/admin/myaccount/preferences'],
            'a settings screen' => ['/admin/settings/sites'],
        ];
    }

    /**
     * One page per test: within a test, Craft's per-request HTML stack isn't reset
     * between requests, so only the first would carry the script.
     */
    #[DataProvider('pages')]
    public function test_the_stamp_reaches_every_kind_of_page(string $path): void
    {
        $this->signIn();
        $this->setPrefs([Themes::PREF_DARK => 'dim']);

        $this->assertStringContainsString('d.castDark="dim";', $this->stamp($path));
    }

    /**
     * Testbench installs the plugin after Craft has registered plugin assets, so the
     * stylesheets aren't on its pages; this checks each one Cast lists exists.
     */
    public function test_every_listed_stylesheet_exists(): void
    {
        $styles = (fn () => $this->styles)->call(Plugin::getInstance());

        $this->assertNotEmpty($styles);

        foreach (array_keys($styles) as $source) {
            $this->assertFileExists($source);
        }
    }

    public function test_the_preferences_page_carries_the_fields(): void
    {
        $this->signIn();

        $this->get('/admin/myaccount/preferences')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('users/Preferences', false)
                ->where('prefsHook.html', function (string $html): bool {
                    foreach (['id="cast-light"', 'id="cast-dark"', 'id="cast-button"', 'id="cast-contrast"', 'Light Appearance', 'Dark Appearance', 'Primary Button Colour', 'Craft Red'] as $needle) {
                        $this->assertStringContainsString($needle, $html);
                    }

                    return true;
                })
                ->where('prefsHook.bodyHtml', fn (string $js): bool => str_contains($js, 'CastPreferences(')));
    }

    public function test_the_fields_show_what_the_user_chose(): void
    {
        $this->signIn();
        $this->setPrefs([Themes::PREF_DARK => 'dim', Themes::PREF_BUTTON => 'red', Themes::PREF_CONTRAST => true]);

        $this->get('/admin/myaccount/preferences')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('prefsHook.html', function (string $html): bool {
                    $this->assertMatchesRegularExpression('/<option value="dim"\s+selected/', $html);
                    $this->assertStringContainsString('value="'.Themes::CRAFT_RED.'"', $html);
                    $this->assertMatchesRegularExpression('/id="cast-contrast"\s+checked/', $html);

                    return true;
                }));
    }

    public function test_no_fields_when_users_may_not_choose(): void
    {
        $this->signIn();
        $this->setSettings(['allowUserOverride' => false]);

        $this->get('/admin/myaccount/preferences')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('prefsHook', null));
    }
}
