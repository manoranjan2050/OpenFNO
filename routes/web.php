<?php

use App\Http\Controllers\ProfileController;
use App\Models\Trade;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/trades', fn () => Inertia::render('Trades/Index'))->name('trades.index');
    Route::get('/trades/create', fn () => Inertia::render('Trades/Create'))->name('trades.create');
    Route::get('/trades/{trade}', fn (Trade $trade) => Inertia::render('Trades/Show', [
        'tradeId' => $trade->id,
    ]))->can('view', 'trade')->name('trades.show');
    Route::get('/trades/{trade}/edit', fn (Trade $trade) => Inertia::render('Trades/Edit', [
        'tradeId' => $trade->id,
    ]))->can('update', 'trade')->name('trades.edit');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
