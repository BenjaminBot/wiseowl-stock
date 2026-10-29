<?php

use App\Http\Controllers\BookLookupController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/intake/book-lookup/{isbn}', BookLookupController::class)
    ->middleware(['auth', 'can:receive-stock', 'throttle:60,1'])->name('intake.book-lookup');
