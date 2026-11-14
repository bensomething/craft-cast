<?php

declare(strict_types=1);

use bensomething\cast\Migrations\LegacyPreferences;
use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Support\Json;
use Illuminate\Support\Facades\DB;

/**
 * Carries each user's Cast 1.x preferences over to what they mean on 2.x. Runs only on
 * sites upgrading from 1.x: a fresh install marks it as already applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table(Table::USERPREFERENCES)->orderBy('userId')->each(function (object $row): void {
            $prefs = Json::decode((string) $row->preferences);

            if (! is_array($prefs) || ! LegacyPreferences::hasLegacy($prefs)) {
                return;
            }

            $carried = LegacyPreferences::carryOver($prefs);

            if ($carried !== $prefs) {
                DB::table(Table::USERPREFERENCES)
                    ->where('userId', $row->userId)
                    ->update(['preferences' => Json::encode($carried)]);
            }
        });
    }
};
