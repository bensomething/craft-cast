<?php

declare(strict_types=1);

namespace bensomething\cast\tests\Feature;

use bensomething\cast\ThemeGenerator;
use bensomething\cast\Themes;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** One colour made into an appearance that keeps Craft's contrast. */
final class ThemeGeneratorTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function colours(): array
    {
        return [
            'teal, medium' => ['#0d9488', 'medium'],
            'violet, strong' => ['#7c3aed', 'strong'],
            'rose, subtle' => ['#e11d48', 'subtle'],
            'amber, strong' => ['#d97706', 'strong'],
            'yellow, strong' => ['#facc15', 'strong'],
        ];
    }

    private static function ratio(string $a, string $b): float
    {
        $la = ThemeGenerator::luminance($a);
        $lb = ThemeGenerator::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    #[DataProvider('colours')]
    public function test_every_step_keeps_crafts_luminance(string $colour, string $tint): void
    {
        foreach (ThemeGenerator::ramps($colour, $tint) as $mode => $steps) {
            foreach ($steps as $token => $hex) {
                $craft = ThemeGenerator::CRAFT[$mode][$token];

                // Against white, so every step's contrast is compared; within hex rounding.
                $this->assertEqualsWithDelta(
                    self::ratio($craft, '#ffffff'),
                    self::ratio($hex, '#ffffff'),
                    self::ratio($craft, '#ffffff') * 0.02,
                    "$mode $token: $hex for Craft's $craft",
                );
            }
        }
    }

    #[DataProvider('colours')]
    public function test_the_accent_keeps_crafts_focus_and_label_contrast(string $colour, string $tint): void
    {
        foreach (ThemeGenerator::accents($colour) as $mode => $steps) {
            $blue = ThemeGenerator::CRAFT_BLUE[$mode];

            $this->assertEqualsWithDelta(self::ratio($blue[50], $blue[600]), self::ratio($steps[50], $steps[600]), 0.1, "$mode label");
            $this->assertEqualsWithDelta(
                self::ratio($blue[500], ThemeGenerator::CRAFT[$mode]['base-50']),
                self::ratio($steps[500], ThemeGenerator::CRAFT[$mode]['base-50']),
                0.1,
                "$mode focus ring",
            );
        }
    }

    public function test_the_hue_comes_through_more_with_a_stronger_tint(): void
    {
        $chroma = function (string $hex): int {
            $rgb = sscanf($hex, '#%02x%02x%02x');

            return max($rgb) - min($rgb);
        };

        $subtle = ThemeGenerator::ramps('#0d9488', 'subtle')[Themes::SCHEME_LIGHT]['base-300'];
        $strong = ThemeGenerator::ramps('#0d9488', 'strong')[Themes::SCHEME_LIGHT]['base-300'];

        $this->assertGreaterThan($chroma($subtle), $chroma($strong));
        $this->assertNotSame(ThemeGenerator::CRAFT[Themes::SCHEME_LIGHT]['base-300'], $subtle);
    }

    public function test_a_colour_without_a_hue_gives_true_neutrals(): void
    {
        foreach (['#000000', '#ffffff', '#808080'] as $colour) {
            foreach ([...ThemeGenerator::ramps($colour, 'strong'), ...ThemeGenerator::accents($colour)] as $steps) {
                foreach ($steps as $hex) {
                    $rgb = sscanf($hex, '#%02x%02x%02x');
                    $this->assertLessThanOrEqual(1, max($rgb) - min($rgb), "$colour gave $hex");
                }
            }
        }
    }

    public function test_white_stays_white(): void
    {
        $this->assertSame('#ffffff', ThemeGenerator::ramps('#e11d48', 'strong')[Themes::SCHEME_LIGHT]['base-50']);
    }

    public function test_the_stylesheet_dresses_both_modes_and_the_focus_ring(): void
    {
        $css = ThemeGenerator::css('custom', 'Sea', '#0d9488', 'medium');

        $this->assertStringContainsString('Theme Name: Sea', $css);
        $this->assertStringContainsString('Modes: light, dark', $css);
        $this->assertStringContainsString("html:not([data-theme='dark'])[data-cast-light='custom'] {", $css);
        $this->assertStringContainsString("html[data-theme='dark'][data-cast-dark='custom'] {", $css);
        $this->assertStringContainsString('--c-color-focus-outline: var(--cast-accent-500);', $css);
        $this->assertStringNotContainsString('craft-button', $css, 'buttons are the Primary Button Colour’s alone');
    }

    public function test_bad_input_is_refused(): void
    {
        foreach ([
            fn () => ThemeGenerator::ramps('teal'),
            fn () => ThemeGenerator::ramps('#0d9488', 'loud'),
            fn () => ThemeGenerator::css('Not A Handle', 'X', '#0d9488'),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected it to be refused.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
