<?php

/**
 * Stands a fake Craft up in place of a real one. See {@see \bensomething\cast\tests\support\Application}
 * for why, and {@see \bensomething\cast\tests\TestCase} for what each test gets on top.
 */

use bensomething\cast\tests\support\Application;
use bensomething\cast\tests\support\Dir;
use bensomething\cast\tests\support\Request;
use bensomething\cast\tests\support\UserComponent;
use craft\services\Config;
use yii\i18n\PhpMessageSource;

define('YII_DEBUG', true);

// Yii's handler would swallow the failures PHPUnit is here to report.
define('YII_ENABLE_ERROR_HANDLER', false);

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';
require $root . '/vendor/craftcms/cms/src/Craft.php';

// One tree per run, holding the project root the tests point `@root` at. Tests make their
// own directories inside it; this only has to exist and be empty at the start.
//
// Built with plain `mkdir()` rather than Craft's FileHelper, which reads its directory
// mode off the general config, and there isn't one.
$tmp = sys_get_temp_dir() . '/craft-cast-tests';

Dir::remove($tmp);
Dir::create($tmp);

new Application([
    'id' => 'craft-cast-tests',
    // Craft's own source tree, not the plugin's. Yii turns this into `@app`, and Craft
    // reads templates of its own out of it — the empty `CustomFieldBehavior` an
    // uninstalled Craft generates lives there.
    'basePath' => $root . '/vendor/craftcms/cms/src',
    'vendorPath' => $root . '/vendor',
    'aliases' => [
        // `@root` is what the default themes path is written against.
        '@root' => $tmp,
        '@webroot' => $tmp . '/web',
        '@web' => '',
    ],
    'components' => [
        'request' => ['class' => Request::class],
        'config' => ['class' => Config::class, 'configDir' => $tmp . '/config'],
        'user' => ['class' => UserComponent::class],
        // Themes are published rather than linked, so this is exercised for real: the
        // URLs the tests assert on are the ones Craft's asset manager produces.
        'assetManager' => [
            'basePath' => $tmp . '/web/cpresources',
            'baseUrl' => '/cpresources',
        ],
        'i18n' => [
            'translations' => [
                // `forceTranslation` off, so an untranslated string comes back as its
                // source and assertions can be written in English.
                'cast' => [
                    'class' => PhpMessageSource::class,
                    'basePath' => $root . '/src/translations',
                ],
                'app' => [
                    'class' => PhpMessageSource::class,
                    'basePath' => '@yii/messages',
                ],
            ],
        ],
    ],
]);
