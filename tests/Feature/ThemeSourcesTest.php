<?php

declare(strict_types=1);

namespace bensomething\cast\tests\Feature;

use bensomething\cast\Events\ThemesResolving;
use bensomething\cast\Plugin;
use bensomething\cast\Settings;
use bensomething\cast\tests\TestCase;
use bensomething\cast\Theme;
use bensomething\cast\ThemeGenerator;
use bensomething\cast\Themes;
use Illuminate\Support\Facades\Event;
use Override;

/** Where themes come from: bundled, the themes folder, and the registration event. */
final class ThemeSourcesTest extends TestCase
{
    /** Cast's own themes, which come first: Dim, Stone and the hues. */
    private const array BUNDLED = ['dim', 'stone', 'red', 'amber', 'green', 'teal', 'blue', 'violet', 'pink'];

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
        $this->assertSame(self::BUNDLED, array_keys($this->themes()->all()));
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

        $this->assertSame(self::BUNDLED, array_keys($themes->all()));

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

        $this->assertSame(['stone', ...array_keys(Themes::HUES), 'midnight'], array_keys($all));
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

        $this->assertSame([...self::BUNDLED, 'appended'], array_keys($this->themes()->all()));
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

    public function test_all_as_the_settings_screen_posts_it_offers_everything(): void
    {
        $this->file('midnight.css', ' * Modes: dark');
        $this->setSettings(['enabledThemes' => ['*']]);

        $this->assertSame(['', ...self::BUNDLED, 'midnight'], $this->themes()->offered(Themes::SCHEME_DARK));
    }

    public function test_users_are_offered_only_the_enabled_themes_and_the_defaults(): void
    {
        $this->file('midnight.css', ' * Modes: dark');
        $this->setSettings(['enabledThemes' => ['midnight'], 'autoDarkTheme' => 'stone']);

        $this->assertSame(['', 'stone', 'midnight'], $this->themes()->offered(Themes::SCHEME_DARK));
        $this->assertSame(['', ...self::BUNDLED, 'midnight'], $this->themes()->handles(Themes::SCHEME_DARK), 'defaults may still be any');
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

        $this->assertSame(['', ...self::BUNDLED], $this->themes()->offered(Themes::SCHEME_DARK));

        $settings = new Settings;
        $settings->setAttributes(['enabledThemes' => []]);
        $this->assertSame(['*'], $settings->validationData()['enabledThemes']);
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

    public function test_the_hues_are_bundled_for_both_modes(): void
    {
        $theme = $this->themes()->all()['teal'];

        $this->assertSame('Teal', $theme->name);
        $this->assertSame([Themes::SCHEME_LIGHT, Themes::SCHEME_DARK], $theme->modes);
        $this->assertArrayNotHasKey('teal', $this->themes()->stylesheets(), 'in the one hue stylesheet, not one of its own');
    }

    public function test_the_hues_are_served_at_the_hue_tint_and_their_url_moves_with_it(): void
    {
        $this->signIn();
        $medium = $this->themes()->hueStylesheetUrl();

        $this->assertMatchesRegularExpression('#/admin/cast/hues\.css\?v=[0-9a-f]{10}#', $medium);

        $response = $this->get('/admin/cast/hues.css')->assertOk();
        $this->assertStringStartsWith('text/css', (string) $response->headers->get('Content-Type'));
        $this->assertSame(ThemeGenerator::hueThemes('medium'), $response->getContent(), 'Medium by default');

        foreach (array_keys(Themes::HUES) as $handle) {
            $this->assertStringContainsString("[data-cast-dark='$handle']", (string) $response->getContent());
        }

        $this->setSettings(['hueTint' => 'strong']);

        $this->assertNotSame($medium, $this->themes()->hueStylesheetUrl());
        $this->assertSame(ThemeGenerator::hueThemes('strong'), $this->get('/admin/cast/hues.css')->getContent());
    }

    public function test_every_page_loads_the_hues(): void
    {
        $this->signIn();

        $html = (string) $this->get('/admin/dashboard')->getContent();

        $this->assertMatchesRegularExpression('#/admin/cast/hues\.css\?v=[0-9a-f]{10}#', $html);
    }

    public function test_guests_cant_fetch_the_hues(): void
    {
        $this->get('/admin/cast/hues.css')->assertRedirect();
    }

    public function test_custom_themes_are_offered_for_both_modes(): void
    {
        $this->assertSame(self::BUNDLED, array_keys($this->themes()->all()));

        $this->setSettings(['customThemes' => [
            ['rowId' => 'b99938a4-0a50-48ea-8d0e-c53d65126729', 'name' => 'Sea', 'color' => '#0d9488', 'tint' => 'medium'],
            ['name' => 'Ink Wash', 'color' => '#1e3a8a', 'tint' => 'subtle'],
        ]]);
        $themes = $this->themes()->all();

        $this->assertSame('Sea', $themes['custom-b99938a4']->name, 'named for its row, so renaming keeps it');
        $this->assertSame('Ink Wash', $themes['custom-ink-wash']->name, 'a config file row has no ID, so its name');
        $this->assertSame([Themes::SCHEME_LIGHT, Themes::SCHEME_DARK], $themes['custom-b99938a4']->modes);
        $this->assertContains('custom-ink-wash', $this->themes()->handles(Themes::SCHEME_DARK));
    }

    public function test_rows_that_wouldnt_validate_are_passed_over(): void
    {
        $this->setSettings(['customThemes' => [
            ['name' => 'No Colour', 'color' => '', 'tint' => 'subtle'],
            ['name' => 'Bad Tint', 'color' => '#0d9488', 'tint' => 'loud'],
            ['name' => 'Twice', 'color' => '#0d9488', 'tint' => 'subtle'],
            ['name' => 'Twice', 'color' => '#7c3aed', 'tint' => 'subtle'],
        ]]);

        $this->assertSame([...self::BUNDLED, 'custom-twice'], array_keys($this->themes()->all()));
        $this->assertStringContainsString('#0d9488', (string) $this->themes()->all()['custom-twice']->description, 'the first of two');
    }

    public function test_a_folder_file_cant_take_a_custom_handle(): void
    {
        $this->file('custom-sea.css', " * Theme Name: Impostor\n * Modes: dark");
        $this->setSettings(['customThemes' => [['name' => 'Sea', 'color' => '#0d9488', 'tint' => 'subtle']]]);

        $this->assertSame('Sea', $this->themes()->all()['custom-sea']->name);
        $this->assertNotNull($this->themes()->generatedCss('custom-sea'));
    }

    public function test_a_custom_theme_is_served_generated_and_its_url_moves_with_it(): void
    {
        $this->signIn();
        $this->setSettings(['customThemes' => [['name' => 'Sea', 'color' => '#0d9488', 'tint' => 'subtle']]]);
        $before = $this->themes()->stylesheets()['custom-sea'];

        $this->assertMatchesRegularExpression('#/admin/cast/themes/custom-sea\.css\?v=[0-9a-f]{10}#', $before);

        $response = $this->get('/admin/cast/themes/custom-sea.css')->assertOk();
        $this->assertStringStartsWith('text/css', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString("[data-cast-dark='custom-sea']", (string) $response->getContent());

        $this->setSettings(['customThemes' => [['name' => 'Sea', 'color' => '#0d9488', 'tint' => 'strong']]]);
        $this->assertNotSame($before, $this->themes()->stylesheets()['custom-sea']);
    }

    public function test_a_listener_can_replace_a_custom_theme(): void
    {
        $this->setSettings(['customThemes' => [['name' => 'Sea', 'color' => '#0d9488', 'tint' => 'subtle']]]);
        Event::listen(ThemesResolving::class, function (ThemesResolving $event): void {
            $event->add(new Theme('custom-sea', 'Theirs', [Themes::SCHEME_DARK], url: 'https://example.com/theirs.css'));
        });

        $this->assertSame('Theirs', $this->themes()->all()['custom-sea']->name);
        $this->assertNull($this->themes()->generatedCss('custom-sea'));
        $this->assertSame('https://example.com/theirs.css', $this->themes()->stylesheets()['custom-sea']);
    }

    public function test_the_preview_is_generated_from_the_query(): void
    {
        $this->signIn();

        $css = (string) $this->get('/admin/cast/preview.css?color=0d9488&tint=medium')->assertOk()->getContent();

        $this->assertStringContainsString("[data-cast-light='cast-preview']", $css);
        $this->assertStringContainsString("[data-cast-dark='cast-preview']", $css);
        $this->get('/admin/cast/preview.css?color=teal')->assertNotFound();
        $this->get('/admin/cast/preview.css?color=0d9488&tint=loud')->assertNotFound();
    }

    public function test_guests_cant_fetch_a_preview(): void
    {
        $this->get('/admin/cast/preview.css?color=0d9488')->assertRedirect();
    }
}
