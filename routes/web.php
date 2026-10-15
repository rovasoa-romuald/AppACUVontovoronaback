<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EnvelopeJubileeController;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});
