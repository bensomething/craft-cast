<?php

declare(strict_types=1);

namespace bensomething\cast\Http\Controllers;

use bensomething\cast\Settings;
use bensomething\cast\Themes;
use CraftCms\Cms\User\Users;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use function CraftCms\Cms\t;

/**
 * Saves a user's light and dark appearances.
 *
 * Craft's preferences page posts only the fields its own form knows, so the two selects
 * Cast adds there save themselves through this action as they change.
 */
final readonly class PreferencesController
{
    public function __construct(
        private Settings $settings,
        private Themes $themes,
        private Users $users,
    ) {}

    public function save(Request $request): JsonResponse
    {
        $user = $request->craftUser();

        if (! $user || ! $this->settings->allowUserOverride) {
            return response()->json(['message' => t('Theme can’t be changed.', category: 'cast')], 403);
        }

        $prefs = [];

        foreach ([Themes::SCHEME_LIGHT, Themes::SCHEME_DARK] as $scheme) {
            if (! $request->exists($scheme)) {
                continue;
            }

            $value = $this->choice($request, $scheme);

            if (! is_string($value) || ($value !== Settings::THEME_INHERIT && ! $this->themes->isValid($value, $scheme))) {
                return response()->json(['message' => t('That theme isn’t available.', category: 'cast')], 422);
            }

            $prefs[$this->themes->prefKey($scheme)] = $value;
        }

        if ($request->exists('buttonColor')) {
            $value = $this->choice($request, 'buttonColor');

            if (! is_string($value) || ($value !== Settings::THEME_INHERIT && ! $this->themes->isValidButtonColor($value))) {
                return response()->json(['message' => t('That colour isn’t available.', category: 'cast')], 422);
            }

            $prefs[Themes::PREF_BUTTON] = $value;
        }

        if ($request->exists('highContrast')) {
            $value = $request->input('highContrast');

            if (! is_bool($value)) {
                return response()->json(['message' => t('Contrast must be on or off.', category: 'cast')], 422);
            }

            $prefs[Themes::PREF_CONTRAST] = $value;
        }

        if ($prefs !== []) {
            // Merges into what's stored, so Craft's own preferences are untouched.
            $this->users->saveUserPreferences($user, $prefs);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * A posted choice, where an empty string means Craft's own.
     *
     * Laravel's default `ConvertEmptyStringsToNull` middleware, which a Craft 6 project
     * may well run, turns that empty string into null before it gets here. A key that
     * was posted but came through null is read back as the empty string it was.
     */
    private function choice(Request $request, string $key): mixed
    {
        return $request->input($key) ?? '';
    }
}
