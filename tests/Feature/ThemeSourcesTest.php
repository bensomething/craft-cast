<?php

declare(strict_types=1);

namespace bensomething\cast\tests\Feature;

use bensomething\cast\Events\ThemesResolving;
use bensomething\cast\Plugin;
use bensomething\cast\Settings;
use bensomething\cast\tests\TestCase;
use bensomething\cast\Theme;
use bensomething\cast\Themes;
use Illuminate\Support\Facades\Event;
use Override;

/** Where themes come from: bundled, the themes folder, and the registration event. */
final class ThemeSourcesTest extends TestCase
{
    private string $folder;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = sys_get_temp_dir().'/cast-themes-'.bin2hex(random_bytes(4));
        mkdir($this->folder);
        $this->setSettings(['themesPath' => $this->folder]);
    }

    #[Override]
    protected function tearDown(): void
    {
        array_map(unlink(...), glob("$this->folder/*") ?: []);
        rmdir($this->folder);

        parent::tearDown();
    }

    private function themes(): Themes
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();

        return new Themes($settings);
    }

    private function file(string $name, string $header, string $css = 'html{}'): string
    {
        $path = "$this->folder/$name";
        file_put_contents($path, "/**\n$header\n */\n$css\n");

        return $path;
    }

    public function test_bundled_themes_are_always_there(): void
    {
        $this->assertSame(['stone', 'dim'], array_keys($this->themes()->all()));
    }

    public function test_a_folder_theme_is_read_from_its_header(): void
    {
        $this->file('midnight.css', " * Theme Name: Midnight\n * Modes: dark\n * Description: Deep blue.");

        $theme = $this->themes()->all()['midnight'];

        $this->assertSame('Midnight', $theme->name);
        $this->assertSame(['dark'], $theme->modes);
        $this->assertSame('Deep blue.', $theme->description);
        $this->assertContains('midnight', $this->themes()->handles(Themes::SCHEME_DARK));
        $this->assertNotContains('midnight', $this->themes()->handles(Themes::SCHEME_LIGHT));
    }

    public function test_a_folder_theme_can_dress_both_modes_and_be_named_from_its_file(): void
    {
        $this->file('sea-glass.css', ' * Modes: light, dark');

        $theme = $this->themes()->all()['sea-glass'];

        $this->assertSame('Sea Glass', $theme->name);
        $this->assertSame(['light', 'dark'], $theme->modes);
    }

    public function test_files_without_modes_or_with_bad_names_are_skipped_and_reported(): void
    {
        $this->file('legacy.css', " * Theme Name: Legacy\n * Color Scheme: dark");
        $this->file('Bad_Name.css', ' * Modes: dark');
        $this->file('_partial.css', ' * Modes: dark');

        $themes = $this->themes();

        $this->assertSame(['stone', 'dim'], array_keys($themes->all()));

        $skipped = implode("\n", $themes->skippedFiles());
        $this->assertStringContainsString('legacy.css', $skipped);
        $this->assertStringContainsString('Bad_Name.css', $skipped);
        $this->assertStringNotContainsString('_partial.css', $skipped, 'partials are skipped quietly');
    }

    public function test_a_folder_file_cant_take_a_bundled_handle(): void
    {
        $this->file('dim.css', " * Theme Name: Impostor\n * Modes: light");

        $this->assertSame('Dim', $this->themes()->all()['dim']->name);
    }

    public function test_a_listener_can_add_replace_and_remove_themes(): void
    {
        Event::listen(function (ThemesResolving $event): void {
            $event->add(new Theme('midnight', 'Midnight', ['dark'], url: 'https://example.com/midnight.css'));
            $event->add(new Theme('stone', 'Stone, retuned', ['light']));
            unset($event->themes['dim']);
        });

        $all = $this->themes()->all();

        $this->assertSame(['stone', 'midnight'], array_keys($all));
        $this->assertSame('Stone, retuned', $all['stone']->name);
        $this->assertSame(['https://example.com/midnight.css'], array_values($this->themes()->stylesheets()));
    }

    public function test_invalid_registered_themes_are_dropped(): void
    {
        Event::listen(function (ThemesResolving $event): void {
            $event->themes[] = new Theme('Not A Handle', 'Bad', ['dark']);
            $event->themes[] = new Theme('no-modes', 'Bad', []);
            $event->themes[] = new Theme('sepia', 'Bad', ['sepia']);
            $event->themes[] = new Theme('inherit', 'Bad', ['dark']);
            $event->themes[] = new Theme('appended', 'Appended', ['light']);
        });

        $this->assertSame(['stone', 'dim', 'appended'], array_keys($this->themes()->all()));
    }

    public function test_folder_themes_are_served_and_stamped_on_pages(): void
    {
        $this->file('midnight.css', ' * Modes: dark', 'html[data-cast-dark="midnight"]{--x:1}');
        $this->signIn();

        $html = (string) $this->get('/admin/dashboard')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#/admin/cast/themes/midnight\.css\?v=[0-9a-f]{10}#', $html);

        $response = $this->get('/admin/cast/themes/midnight.css')->assertOk();
        $this->assertStringStartsWith('text/css', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('--x:1', (string) file_get_contents($response->baseResponse->getFile()->getPathname()));
    }

    public function test_only_folder_themes_are_served(): void
    {
        $this->signIn();

        $this->get('/admin/cast/themes/dim.css')->assertNotFound();
        $this->get('/admin/cast/themes/nope.css')->assertNotFound();
    }

    public function test_guests_cant_fetch_folder_themes(): void
    {
        $this->file('midnight.css', ' * Modes: dark');

        $this->get('/admin/cast/themes/midnight.css')->assertRedirect();
    }

    public function test_users_are_offered_only_the_enabled_themes_and_the_defaults(): void
    {
        $this->file('midnight.css', ' * Modes: dark');
        $this->setSettings(['enabledThemes' => ['midnight'], 'autoDarkTheme' => 'stone']);

        $this->assertSame(['', 'stone', 'midnight'], $this->themes()->offered(Themes::SCHEME_DARK));
        $this->assertSame(['', 'stone', 'dim', 'midnight'], $this->themes()->handles(Themes::SCHEME_DARK), 'defaults may still be any');
    }

    public function test_a_theme_taken_off_offer_falls_back_to_the_site_default(): void
    {
        $this->setPrefs([Themes::PREF_DARK => 'dim']);
        $this->setSettings(['enabledThemes' => ['stone']]);

        $this->assertSame('', $this->themes()->forUser($this->admin(), Themes::SCHEME_DARK));
    }

    public function test_the_save_action_refuses_a_theme_off_offer(): void
    {
        $this->signIn();
        $this->setSettings(['enabledThemes' => ['stone']]);

        $this->postJson($this->castAction('preferences'), ['dark' => 'dim'])->assertUnprocessable();
        $this->postJson($this->castAction('preferences'), ['dark' => 'stone'])->assertOk();
    }

    public function test_nothing_ticked_means_all(): void
    {
        $this->setSettings(['enabledThemes' => []]);

        $this->assertSame(['', 'stone', 'dim'], $this->themes()->offered(Themes::SCHEME_DARK));

        $settings = new Settings;
        $settings->setAttributes(['enabledThemes' => []]);
        $this->assertSame('*', $settings->validationData()['enabledThemes']);
    }

    public function test_available_themes_must_be_known(): void
    {
        $settings = new Settings;
        $settings->setAttributes(['enabledThemes' => ['stone', 'nope']]);

        $this->assertFalse($settings->validate());
        $this->assertTrue($settings->errors()->has('enabledThemes'));
    }

    public function test_the_settings_screen_lists_themes_and_skipped_files(): void
    {
        $this->file('midnight.css', " * Theme Name: Midnight\n * Modes: dark");
        $this->file('legacy.css', ' * Color Scheme: dark');
        $this->signIn();

        $html = (string) $this->get('/admin/settings/plugins/cast')->assertOk()->getContent();

        $this->assertStringContainsString('Available Themes', $html);
        $this->assertStringContainsString('Midnight (dark)', $html);
        $this->assertStringContainsString('Stone (light, dark)', $html);
        $this->assertStringContainsString('legacy.css', $html);
    }
}
