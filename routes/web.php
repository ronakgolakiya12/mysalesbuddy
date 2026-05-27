<?php

use Illuminate\Support\Facades\Route;
use Laravel\Horizon\Horizon;

Horizon::auth(function () {
    return app()->environment('local');
});

Route::view('/{any}', 'app')
    ->where('any', '^(?!(api|horizon|broadcasting)(/.*)?$).*')
    ->name('spa');
