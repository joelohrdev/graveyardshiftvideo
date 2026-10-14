<?php

use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CopyController;
use App\Http\Controllers\DirectorController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\WatchController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('collection', [CollectionController::class, 'index'])->name('collection.index');

    Route::get('aisles', [MovieController::class, 'index'])->name('movies.index');
    Route::get('movies/{movie}', [MovieController::class, 'show'])->name('movies.show');
    Route::delete('movies', [MovieController::class, 'destroyMany'])->name('movies.destroy-many');

    Route::post('movies/{movie}/copies', [CopyController::class, 'store'])->name('copies.store');
    Route::patch('copies/{copy}', [CopyController::class, 'update'])->name('copies.update');
    Route::delete('copies/{copy}', [CopyController::class, 'destroy'])->name('copies.destroy');

    Route::get('directors', [DirectorController::class, 'index'])->name('directors.index');
    Route::get('people/{person}', [PersonController::class, 'show'])->name('people.show');

    Route::get('want-list', [WatchController::class, 'index'])->name('watches.index');
    Route::post('movies/{movie}/watches', [WatchController::class, 'store'])->name('watches.store');
    Route::patch('watches/{watch}', [WatchController::class, 'update'])->name('watches.update');
    Route::delete('watches/{watch}', [WatchController::class, 'destroy'])->name('watches.destroy');
    Route::post('watches/{watch}/check', [WatchController::class, 'check'])->middleware('throttle:10,1')->name('watches.check');

    Route::get('back-room', [ImportController::class, 'index'])->name('imports.index');
    Route::post('back-room/imports', [ImportController::class, 'store'])->name('imports.store');
});

require __DIR__.'/settings.php';
