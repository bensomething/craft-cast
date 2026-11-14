<?php

declare(strict_types=1);

use bensomething\cast\Http\Controllers\ThemeStylesheetController;
use bensomething\cast\Theme;
use Illuminate\Support\Facades\Route;

// Under the CP trigger, behind Craft's CP auth. Preview first: it's outside the theme
// handles' pattern only by its path.
Route::get('cast/preview.css', [ThemeStylesheetController::class, 'preview'])
    ->name('cast.themes.preview');

Route::get('cast/themes/{handle}.css', [ThemeStylesheetController::class, 'show'])
    ->where('handle', trim(Theme::HANDLE_PATTERN, '/^$'))
    ->name('cast.themes.stylesheet');
