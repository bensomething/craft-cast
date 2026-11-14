<?php

declare(strict_types=1);

use bensomething\cast\Http\Controllers\ThemeStylesheetController;
use bensomething\cast\Theme;
use Illuminate\Support\Facades\Route;

// Under the CP trigger, behind Craft's CP auth.
Route::get('cast/themes/{handle}.css', [ThemeStylesheetController::class, 'show'])
    ->where('handle', trim(Theme::HANDLE_PATTERN, '/^$'))
    ->name('cast.themes.stylesheet');
