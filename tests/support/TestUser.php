<?php

namespace bensomething\cast\tests\support;

use craft\elements\User;

/**
 * A user whose preferences are set rather than stored.
 *
 * Craft reads preferences straight out of the `userpreferences` table, behind a
 * `can('accessCp')` check that's another query again. This is the one thing Cast asks a
 * user, so it's the one thing overridden; everything else is a real User element.
 */
class TestUser extends User
{
    /** @var array<string, mixed> */
    public array $testPreferences = [];

    /**
     * @return array<string, mixed>
     */
    public function getPreferences(): array
    {
        return $this->testPreferences;
    }
}
