<?php

namespace bensomething\cast\tests\support;

use yii\base\Component;

/**
 * The `user` component, reduced to the one thing Cast asks it: who's signed in.
 */
class UserComponent extends Component
{
    public ?TestUser $identity = null;

    public function getIdentity(): ?TestUser
    {
        return $this->identity;
    }
}
