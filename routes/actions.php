<?php

declare(strict_types=1);

use bensomething\cast\Http\Controllers\PreferencesController;
use CraftCms\Cms\Http\Middleware\RequireCpRequest;
use Illuminate\Support\Facades\Route;

// Craft registers these under both the CP and site action prefixes, the latter reachable
// by guests, so every route requires a logged-in CP user on a CP request.
Route::middleware(['auth', 'can:accessCp', RequireCpRequest::class])->group(function () {
    Route::post('preferences', [PreferencesController::class, 'save']);
});
