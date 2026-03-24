<?php

use App\Models\ContentItem;
use App\Models\ContentSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Index ────────────────────────────────────────────────────────────────────

it('guest sees system and public sets on the index page', function (): void {
    $system = ContentSet::factory()->system()->create();
    $public = ContentSet::factory()->public()->create();

    $response = $this->get(route('content_sets.index'));

    $response->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('ContentSets/Index')
                ->has('sets.data', 2)
                ->where('sets.data', fn ($data) => in_array(
                    $system->slug,
                    collect($data)->pluck('slug')->all(),
                    true,
                ) && in_array(
                    $public->slug,
                    collect($data)->pluck('slug')->all(),
                    true,
                ))
        );
});

it('guest cannot see private sets on the index page', function (): void {
    $owner  = User::factory()->create();
    ContentSet::factory()->private()->for($owner)->create();
    $system = ContentSet::factory()->system()->create();

    $response = $this->get(route('content_sets.index'));

    $response->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('ContentSets/Index')
                ->has('sets.data', 1)
                ->where('sets.data.0.slug', $system->slug)
        );
});

it('authenticated user sees system, public, and their own private sets', function (): void {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $system       = ContentSet::factory()->system()->create();
    $public       = ContentSet::factory()->public()->create();
    $ownPrivate   = ContentSet::factory()->private()->for($user)->create();
    $otherPrivate = ContentSet::factory()->private()->for($other)->create();

    $this->actingAs($user)
        ->get(route('content_sets.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('ContentSets/Index')
                ->has('sets.data', 3)
                ->where('sets.data', function ($data) use ($system, $public, $ownPrivate, $otherPrivate): bool {
                    $slugs = collect($data)->pluck('slug')->all();

                    return in_array($system->slug, $slugs, true)
                        && in_array($public->slug, $slugs, true)
                        && in_array($ownPrivate->slug, $slugs, true)
                        && ! in_array($otherPrivate->slug, $slugs, true);
                })
        );
});

it('authenticated user cannot see another user\'s private sets', function (): void {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    ContentSet::factory()->private()->for($other)->create();

    $this->actingAs($user)
        ->get(route('content_sets.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('ContentSets/Index')
                ->has('sets.data', 0)
        );
});

// ─── Show ─────────────────────────────────────────────────────────────────────

it('show page returns the correct set and its items', function (): void {
    $set = ContentSet::factory()->system()->create();
    ContentItem::factory(3)->for($set)->create();

    $this->get(route('content_sets.show', $set->slug))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('ContentSets/Show')
                ->where('set.data.slug', $set->slug)
                ->has('items.data', 3)
        );
});

it('show page paginates items correctly', function (): void {
    $set = ContentSet::factory()->system()->create();
    ContentItem::factory(25)->for($set)->create();

    $this->get(route('content_sets.show', $set->slug))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('ContentSets/Show')
                ->has('items.data', 20)             // first page: 20 of 25
                ->where('items.meta.total', 25)
        );
});

it('show page returns 404 for a non-existent slug', function (): void {
    $this->get(route('content_sets.show', 'does-not-exist'))
        ->assertNotFound();
});

it('guest cannot access a private set via the show page', function (): void {
    $owner = User::factory()->create();
    $set   = ContentSet::factory()->private()->for($owner)->create();

    $this->get(route('content_sets.show', $set->slug))
        ->assertNotFound();
});

it('owner can access their own private set via the show page', function (): void {
    $owner = User::factory()->create();
    $set   = ContentSet::factory()->private()->for($owner)->create();
    ContentItem::factory(2)->for($set)->create();

    $this->actingAs($owner)
        ->get(route('content_sets.show', $set->slug))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('ContentSets/Show')
                ->where('set.data.slug', $set->slug)
                ->has('items.data', 2)
        );
});
