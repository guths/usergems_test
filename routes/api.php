<?php

use App\Http\Controllers\PersonController;
use Illuminate\Support\Facades\Route;

Route::post('/person', [PersonController::class, 'store']);
