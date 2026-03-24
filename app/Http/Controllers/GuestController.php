<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GuestController extends Controller
{
    /**
     * POST /guest/enter
     *
     * Accepts a display_name, generates a UUID guest_token, stores it in
     * a signed HttpOnly cookie, and returns the token in the JSON response.
     */
    public function enter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'min:1', 'max:50'],
        ]);

        $guestToken = (string) Str::uuid();

        $cookie = cookie(
            name: 'guest_token',
            value: $guestToken,
            minutes: 60 * 24 * 30,   // 30 days
            path: '/',
            domain: null,
            secure: $request->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'Lax',
        );

        return response()->json([
            'guest_token' => $guestToken,
            'display_name' => $validated['display_name'],
        ])->withCookie($cookie);
    }
}
