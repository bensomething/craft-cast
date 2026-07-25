<?php

namespace bensomething\cast\tests\support;

use craft\elements\User;
use craft\services\Users;

/**
 * Craft's users service, reduced to the one call the controller makes: saving a
 * preference. The real thing merges into the `userpreferences` table; this records what
 * it was handed and mirrors the merge onto a {@see TestUser}, so a test can assert the
 * controller passed only its own key and left the rest for Craft to preserve.
 */
class UsersService extends Users
{
    public ?User $savedUser = null;

    /** @var array<string, mixed>|null */
    public ?array $savedPreferences = null;

    /**
     * @param array<string, mixed> $preferences
     */
    public function saveUserPreferences(User $user, array $preferences): void
    {
        $this->savedUser = $user;
        $this->savedPreferences = $preferences;

        // The `+` mirrors Craft: the posted key wins, anything already stored survives.
        if ($user instanceof TestUser) {
            $user->testPreferences = $preferences + $user->testPreferences;
        }
    }

    /**
     * Forgets the last save, so each test starts from nothing recorded.
     */
    public function reset(): void
    {
        $this->savedUser = null;
        $this->savedPreferences = null;
    }
}
