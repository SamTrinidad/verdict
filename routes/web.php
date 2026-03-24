<?php

use App\Http\Controllers\ContentSetController;
use App\Http\Controllers\GuestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ─── Public landing page ──────────────────────────────────────────────────────
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name'),
    ]);
});

// ─── Guest identity ───────────────────────────────────────────────────────────
// Open endpoint — no auth required. Issues a UUID token + HttpOnly cookie.
Route::post('/guest/enter', [GuestController::class, 'enter'])->name('guest.enter');

// ─── Content sets (index + show, visible to guests and authenticated users) ───
Route::resource('content_sets', ContentSetController::class)->only(['index', 'show']);

// ─── Breeze authentication routes (login, register, password reset, logout) ───
require __DIR__.'/auth.php';

// ─── Authenticated users only (auth:web) ─────────────────────────────────────
// Phase 2+: session creation and management routes go here.
Route::middleware('auth:web')->group(function (): void {
    // placeholder
});

// ─── Any participant (authenticated user OR guest with valid token) ────────────
// Phase 3+: session-scoped routes (rating, leaderboard, real-time, etc.)
Route::middleware('participant.identity')->group(function (): void {
    // placeholder
});
