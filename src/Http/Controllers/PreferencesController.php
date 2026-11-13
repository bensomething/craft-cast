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
            $value = $request->input($scheme);

            if ($value === null) {
                continue;
            }

            if (! is_string($value) || ($value !== Settings::THEME_INHERIT && ! $this->themes->isValid($value, $scheme))) {
                return response()->json(['message' => t('That theme isn’t available.', category: 'cast')], 422);
            }

            $prefs[$this->themes->prefKey($scheme)] = $value;
        }

        if ($request->has('buttonColor')) {
            $value = $request->input('buttonColor');

            // An empty string is Craft's own colour.
            if (! is_string($value) || ($value !== Settings::THEME_INHERIT && ! $this->themes->isValidButtonColor($value))) {
                return response()->json(['message' => t('That colour isn’t available.', category: 'cast')], 422);
            }

            $prefs[Themes::PREF_BUTTON] = $value;
        }

        if ($request->has('highContrast')) {
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
}
