<?php

use Illuminate\Support\Str;

/**
 * Guest token endpoint — POST /guest/enter
 *
 * Covered:
 *   ✓ Returns 200 with guest_token (valid UUID) and display_name
 *   ✓ Sets an HttpOnly guest_token cookie matching the JSON token
 *   ✓ Validates required display_name (422 when absent)
 *   ✓ Validates max length of 50 characters (422 when exceeded)
 */
it('issues a UUID guest_token and returns the display_name', function (): void {
    $response = $this->postJson('/guest/enter', [
        'display_name' => 'Tester McTestface',
    ]);

    $response
        ->assertStatus(200)
        ->assertJsonStructure(['guest_token', 'display_name'])
        ->assertJsonPath('display_name', 'Tester McTestface');

    expect(Str::isUuid($response->json('guest_token')))->toBeTrue();
});

it('sets an HttpOnly guest_token cookie that matches the JSON token', function (): void {
    $response = $this->postJson('/guest/enter', [
        'display_name' => 'Cookie Monster',
    ]);

    $response->assertStatus(200);

    $token = $response->json('guest_token');

    // assertCookie decrypts automatically in the test environment
    $response->assertCookie('guest_token', $token);
});

it('returns 422 when display_name is missing', function (): void {
    $this->postJson('/guest/enter', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['display_name']);
});

it('returns 422 when display_name exceeds 50 characters', function (): void {
    $this->postJson('/guest/enter', [
        'display_name' => str_repeat('x', 51),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['display_name']);
});

it('returns 422 when display_name is an empty string', function (): void {
    $this->postJson('/guest/enter', [
        'display_name' => '',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['display_name']);
});

it('issues a different token on each call', function (): void {
    $tokenA = $this->postJson('/guest/enter', ['display_name' => 'Alice'])->json('guest_token');
    $tokenB = $this->postJson('/guest/enter', ['display_name' => 'Bob'])->json('guest_token');

    expect($tokenA)->not->toBe($tokenB);
});
