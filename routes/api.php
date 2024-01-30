<?php

use Illuminate\Support\Facades\Route;
use ChijiokeIbekwe\Raven\Http\Controllers\NotificationContextController;


Route::get('/notification-contexts', [NotificationContextController::class, 'index'])->name('contexts.index');