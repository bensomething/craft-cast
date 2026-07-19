<?php

namespace bensomething\cast\controllers;

use bensomething\cast\models\Settings;
use bensomething\cast\Plugin;
use bensomething\cast\services\Themes;
use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Saves a user's own theme choice. Craft's `users/save-preferences` posts the entire
 * preferences form, so it can't be reused for a one-field change from the account menu.
 */
class PreferencesController extends Controller
{
    public function actionSaveTheme(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $plugin = Plugin::getInstance();
        $user = Craft::$app->getUser()->getIdentity();

        if (!$user || !$plugin->getSettings()->allowUserOverride) {
            return $this->asFailure(Craft::t('cast', 'Theme can’t be changed.'));
        }

        $handle = (string)$this->request->getRequiredBodyParam('theme');
        $selectable = [
            Settings::THEME_INHERIT,
            Settings::THEME_AUTO,
            Settings::THEME_NONE,
            ...array_keys($plugin->themes->getEnabledThemes()),
        ];

        if (!in_array($handle, $selectable, true)) {
            return $this->asFailure(Craft::t('cast', 'That theme isn’t available.'));
        }

        // Merges into what's already stored, so the rest of the preferences are intact.
        Craft::$app->getUsers()->saveUserPreferences($user, [Themes::PREF_KEY => $handle]);

        return $this->asSuccess();
    }
}
