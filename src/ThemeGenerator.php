<?php

declare(strict_types=1);

namespace bensomething\cast;

use InvalidArgumentException;

/**
 * Builds an appearance from one colour: Craft's neutrals turned to that colour's hue.
 *
 * Craft's surfaces (the base ramp) and its text and borders (the slate ramp) carry a
 * blue-grey cast. Each generated step keeps the relative luminance of the Craft step it
 * replaces, so every contrast ratio in the control panel stays exactly Craft's whatever
 * colour is picked. Only the hue moves, and the chroma by the tint's factor: Craft's own
 * chroma at that step, so the profile of the ramp is kept.
 *
 * The amount of colour is then shaped so the result reads as tinted rather than coloured:
 * less in dark, where dark surfaces show it far more than light ones; less in text and
 * borders than in surfaces, so the hue lives in the surfaces; and less for warm hues in
 * dark, where a weak dark orange is simply brown.
 *
 * A saturated colour gives a tinted neutral, not coloured surfaces. Only its hue is used,
 * and a colour without one (black, white, a grey) gives a true neutral: Craft's greys with
 * the blue taken out.
 *
 * The colour itself comes back as an accent: Craft's blue ramp turned to the hue at full
 * strength, each step again at Craft's luminance. The focus ring uses it, so it keeps
 * exactly Craft's contrast. Primary buttons don't: they're the user's Primary Button
 * Colour alone, so that picker always shows what the buttons are.
 */
final class ThemeGenerator
{
    /** Bumped whenever the output changes, so a generated stylesheet's cached URL moves on. */
    public const string VERSION = '2';

    /**
     * How strongly the hue comes through in each mode, as a multiple of Craft's own chroma.
     *
     * @var array<string, array<string, float>>
     */
    public const array TINTS = [
        'subtle' => [Themes::SCHEME_LIGHT => 1.0, Themes::SCHEME_DARK => 0.75],
        'medium' => [Themes::SCHEME_LIGHT => 2.0, Themes::SCHEME_DARK => 1.25],
        'strong' => [Themes::SCHEME_LIGHT => 3.5, Themes::SCHEME_DARK => 2.0],
    ];

    /**
     * Below this OKLCH chroma a colour has no hue worth following (black, white, greys),
     * and the theme is a true neutral instead.
     */
    private const float NEUTRAL_CHROMA = 0.02;

    /** The share of the surfaces' colour that text and borders (the slate ramp) get. */
    public const float SLATE_SHARE = 0.6;

    /** Warm hues in dark: the OKLCH hue they centre on, how far either side, and the most taken off there. */
    private const float WARM_CENTRE = 45.0;

    private const float WARM_REACH = 45.0;

    private const float WARM_DAMPING = 0.4;

    /**
     * Craft 6.0.0-beta.1's ramps. Light is `:root`, dark is `[data-theme=dark]`.
     *
     * @var array<string, array<string, string>>
     */
    public const array CRAFT = [
        Themes::SCHEME_LIGHT => [
            'base-50' => '#ffffff', 'base-100' => '#f2f6fb', 'base-200' => '#e6ecf4', 'base-300' => '#c9d4e0',
            'base-400' => '#a7b2bf', 'base-500' => '#6f7b89', 'base-600' => '#5f6c79', 'base-700' => '#4d5b68',
            'base-800' => '#313e49', 'base-900' => '#1f2934', 'base-950' => '#10151b',
            'slate-50' => '#e8eef5', 'slate-100' => '#cfd9e5', 'slate-200' => '#bcc7d3', 'slate-300' => '#97a3b0',
            'slate-400' => '#84909d', 'slate-500' => '#727d8b', 'slate-600' => '#5e6b78', 'slate-700' => '#4b5866',
            'slate-800' => '#3a4754', 'slate-900' => '#2b3642', 'slate-950' => '#1e2731',
        ],
        Themes::SCHEME_DARK => [
            'base-50' => '#26313c', 'base-100' => '#313e49', 'base-200' => '#374450', 'base-300' => '#465461',
            'base-400' => '#5e6b78', 'base-500' => '#929dab', 'base-600' => '#a5b0bd', 'base-700' => '#bdc9d5',
            'base-800' => '#f4f7fc', 'base-900' => '#ffffff', 'base-950' => '#ffffff',
            'slate-50' => '#36434f', 'slate-100' => '#4e5b69', 'slate-200' => '#5d6977', 'slate-300' => '#6d7886',
            'slate-400' => '#7e8997', 'slate-500' => '#909ba9', 'slate-600' => '#a6b2be', 'slate-700' => '#b6c1ce',
            'slate-800' => '#c9d5e1', 'slate-900' => '#dfe7ef', 'slate-950' => '#f3f7fc',
        ],
    ];

    /**
     * Craft 6.0.0-beta.1's blue ramps, which the accent follows.
     *
     * @var array<string, array<int, string>>
     */
    public const array CRAFT_BLUE = [
        Themes::SCHEME_LIGHT => [
            '50' => '#e7eefd', '100' => '#c6d7fb', '200' => '#afc7fa', '300' => '#78a0f8', '400' => '#5a8bf7',
            '500' => '#3c75f1', '600' => '#2660e0', '700' => '#2251b6', '800' => '#1f418d', '900' => '#1b3368',
            '950' => '#162547',
        ],
        Themes::SCHEME_DARK => [
            '50' => '#1e3e83', '100' => '#2253bd', '200' => '#245fdf', '300' => '#3670ec', '400' => '#4e83f6',
            '500' => '#6c98f7', '600' => '#8eb0f9', '700' => '#a6c0fa', '800' => '#c1d4fb', '900' => '#dce6fd',
            '950' => '#f3f6fe',
        ],
    ];

    /**
     * Both modes' accent ramps for a colour: Craft's blue, turned to its hue.
     *
     * @return array<string, array<int, string>> Mode, then step (`600`) to hex.
     */
    public static function accents(string $color): array
    {
        [, $chroma, $hue] = self::oklch(self::hexToRgb($color));
        $factor = $chroma < self::NEUTRAL_CHROMA ? 0.0 : 1.0;
        $ramps = [];

        foreach (self::CRAFT_BLUE as $mode => $steps) {
            foreach ($steps as $step => $hex) {
                $ramps[$mode][$step] = self::retint($hex, $hue, $factor);
            }
        }

        return $ramps;
    }

    /**
     * Both modes' ramps for a colour.
     *
     * @return array<string, array<string, string>> Mode, then token (`base-100`) to hex.
     */
    public static function ramps(string $color, string $tint = 'subtle'): array
    {
        if (! isset(self::TINTS[$tint])) {
            throw new InvalidArgumentException("Unknown tint `{$tint}`.");
        }

        [, $chroma, $hue] = self::oklch(self::hexToRgb($color));
        $neutral = $chroma < self::NEUTRAL_CHROMA;
        $ramps = [];

        foreach (self::CRAFT as $mode => $steps) {
            // No hue to follow, so no colour at all: Craft's greys without their blue.
            $factor = $neutral ? 0.0 : self::TINTS[$tint][$mode];

            if ($mode === Themes::SCHEME_DARK) {
                $factor *= self::warmth($hue);
            }

            foreach ($steps as $token => $hex) {
                $share = str_starts_with($token, 'slate-') ? self::SLATE_SHARE : 1.0;
                $ramps[$mode][$token] = self::retint($hex, $hue, $factor * $share);
            }
        }

        return $ramps;
    }

    /** A themes-folder stylesheet for a colour, dressing both modes. */
    public static function css(string $handle, string $name, string $color, string $tint = 'subtle'): string
    {
        return "/**\n * Theme Name: {$name}\n * Modes: light, dark\n * Description: Generated from {$color}, {$tint} tint.\n */\n"
            .self::rules($handle, $color, $tint);
    }

    /**
     * A theme's rules for both modes, without a header: the ramps, the accent and the
     * focus ring, each scoped to the handle and to Craft's mode.
     */
    public static function rules(string $handle, string $color, string $tint = 'subtle'): string
    {
        if (! preg_match(Theme::HANDLE_PATTERN, $handle)) {
            throw new InvalidArgumentException("Invalid handle `{$handle}`.");
        }

        $selectors = [
            Themes::SCHEME_LIGHT => "html:not([data-theme='dark'])[data-cast-light='{$handle}']",
            Themes::SCHEME_DARK => "html[data-theme='dark'][data-cast-dark='{$handle}']",
        ];

        $css = '';
        $accents = self::accents($color);

        foreach (self::ramps($color, $tint) as $mode => $steps) {
            $root = $selectors[$mode];
            $css .= "\n{$root} {\n";

            foreach ($steps as $token => $hex) {
                $css .= "    --color-{$token}: {$hex};\n";
            }

            foreach ($accents[$mode] as $step => $hex) {
                $css .= "    --cast-accent-{$step}: {$hex};\n";
            }

            $css .= "}\n";

            // Craft declares the focus colour again on every component's host, so it's
            // set on every element rather than inherited from the root.
            $css .= "\n{$root},\n{$root} * {\n    --c-color-focus-outline: var(--cast-accent-500);\n}\n";
        }

        return $css;
    }

    /** The stylesheet of the hue themes Cast ships, {@see Themes::HUES}, at a tint. */
    public static function hueThemes(string $tint): string
    {
        $css = "/* Cast: hues, at the {$tint} tint. Generated; see `ThemeGenerator`. */\n";

        foreach (Themes::HUES as $handle => [$color, $name]) {
            $css .= "\n/* {$name}, from {$color} */\n".self::rules($handle, $color, $tint);
        }

        return $css;
    }

    /**
     * The chroma kept for a hue in dark: all of it, easing to the least at the warm centre.
     *
     * Reds through ambers go brown when weak and dark, so they get less to stay warm grey.
     */
    private static function warmth(float $hue): float
    {
        $distance = abs(fmod($hue - self::WARM_CENTRE + 540.0, 360.0) - 180.0);

        if ($distance >= self::WARM_REACH) {
            return 1.0;
        }

        return 1.0 - self::WARM_DAMPING * (0.5 + 0.5 * cos(M_PI * $distance / self::WARM_REACH));
    }

    /** WCAG relative luminance of a hex colour. */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(self::toLinear(...), self::hexToRgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * One step: Craft's luminance, the new hue, Craft's chroma scaled.
     *
     * Lightness is solved for rather than carried over, as OKLab lightness and WCAG
     * luminance part ways once the hue moves. Where the chroma won't fit sRGB at that
     * lightness, it's reduced until it does.
     */
    private static function retint(string $hex, float $hue, float $factor): string
    {
        $target = self::luminance($hex);

        // White stays white, and black black: there's no hue to give them.
        if ($target >= 0.9999 || $target <= 0.0001) {
            return $hex;
        }

        $chroma = self::oklch(self::hexToRgb($hex))[1] * $factor;
        $best = $hex;

        for ($attempt = 0; $attempt < 40; $attempt++) {
            $low = 0.0;
            $high = 1.0;
            $rgb = [0.0, 0.0, 0.0];

            for ($i = 0; $i < 40; $i++) {
                $l = ($low + $high) / 2;
                $rgb = self::fromOklch($l, $chroma, $hue);
                $y = 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];

                if ($y < $target) {
                    $low = $l;
                } else {
                    $high = $l;
                }
            }

            if (min($rgb) >= -0.0005 && max($rgb) <= 1.0005) {
                $best = self::linearToHex($rgb);
                break;
            }

            $chroma *= 0.9;
        }

        return $best;
    }

    /**
     * @param  array{float, float, float}  $rgb  Gamma-encoded, 0 to 1.
     * @return array{float, float, float} L, C, h in degrees.
     */
    private static function oklch(array $rgb): array
    {
        [$r, $g, $b] = array_map(self::toLinear(...), $rgb);

        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;

        [$l, $m, $s] = [$l ** (1 / 3), $m ** (1 / 3), $s ** (1 / 3)];

        $L = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $bb = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        $h = rad2deg(atan2($bb, $a));

        return [$L, sqrt($a * $a + $bb * $bb), $h < 0 ? $h + 360 : $h];
    }

    /** @return array{float, float, float} Linear sRGB, possibly out of gamut. */
    private static function fromOklch(float $L, float $C, float $h): array
    {
        $a = $C * cos(deg2rad($h));
        $b = $C * sin(deg2rad($h));

        $l = ($L + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($L - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($L - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];
    }

    /** @return array{float, float, float} */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            throw new InvalidArgumentException("Invalid colour `#{$hex}`.");
        }

        return [hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255];
    }

    private static function toLinear(float $c): float
    {
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    /** @param array{float, float, float} $rgb */
    private static function linearToHex(array $rgb): string
    {
        return '#'.implode('', array_map(function (float $c): string {
            $c = max(0.0, min(1.0, $c));
            $c = $c <= 0.0031308 ? $c * 12.92 : 1.055 * $c ** (1 / 2.4) - 0.055;

            return sprintf('%02x', (int) round($c * 255));
        }, $rgb));
    }
}
