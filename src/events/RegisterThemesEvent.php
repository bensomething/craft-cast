<?php

namespace bensomething\cast\events;

use bensomething\cast\models\Theme;
use yii\base\Event;

/** Lets other plugins and modules add their own CP colour modes. */
class RegisterThemesEvent extends Event
{
    /** @var Theme[] The registered themes, keyed by handle. */
    public array $themes = [];
}
