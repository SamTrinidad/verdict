<?php

use App\Http\Controllers\ContentSetController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\SessionController;
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

// ─── Session Management — authenticated users only (auth:web) ────────────────
Route::middleware('auth:web')->group(function (): void {
    Route::get('/sessions/create', [SessionController::class, 'create'])->name('sessions.create');
    Route::post('/sessions', [SessionController::class, 'store'])->name('sessions.store');
});

// ─── Session join — public (anyone with the ULID/join-code can view & enter) ──
Route::get('/sessions/{ulid}/join', [SessionController::class, 'join'])->name('sessions.join');
Route::post('/sessions/{ulid}/enter', [SessionController::class, 'enter'])->name('sessions.enter');

// ─── Session-scoped routes — caller must be an enrolled participant ───────────
// EnsureParticipantIdentity performs a DB lookup when {ulid} is present and
// attaches the full SessionParticipant model to $request->attributes('participant').
Route::middleware('participant.identity')->group(function (): void {
    Route::get('/sessions/{ulid}/lobby', [SessionController::class, 'lobby'])->name('sessions.lobby');
    Route::patch('/sessions/{ulid}/start', [SessionController::class, 'start'])->name('sessions.start');
});
