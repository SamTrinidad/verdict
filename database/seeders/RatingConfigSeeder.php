<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RatingConfigSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the two built-in system rating configurations.
     *
     * 1. Numeric  — a 1-10 numeric scale; individual values need no tier rows.
     * 2. Tier     — the classic S/A/B/C/D tier list with hex colour codes.
     */
    public function run(): void
    {
        // ── 1. Numeric: 1-10 Scale ────────────────────────────────────────────
        // Numeric configs store the allowed range in session settings at runtime;
        // no rating_tier rows are required for a numeric scale.
        DB::table('rating_configs')->insertOrIgnore([
            'type'       => 'numeric',
            'name'       => '1-10 Scale',
            'is_system'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── 2. Tier: S/A/B/C/D ───────────────────────────────────────────────
        $tierId = DB::table('rating_configs')->insertGetId([
            'type'       => 'tier',
            'name'       => 'S-Tier',
            'is_system'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // rank_order: 1 = best (S), 5 = worst (D)
        $tiers = [
            ['label' => 'S', 'color' => '#FF7F7F', 'rank_order' => 1], // salmon-red
            ['label' => 'A', 'color' => '#FFBF7F', 'rank_order' => 2], // orange
            ['label' => 'B', 'color' => '#FFFF7F', 'rank_order' => 3], // yellow
            ['label' => 'C', 'color' => '#7FFF7F', 'rank_order' => 4], // green
            ['label' => 'D', 'color' => '#7FBFFF', 'rank_order' => 5], // blue
        ];

        foreach ($tiers as $tier) {
            DB::table('rating_tiers')->insertOrIgnore([
                'rating_config_id' => $tierId,
                'label'            => $tier['label'],
                'color'            => $tier['color'],
                'rank_order'       => $tier['rank_order'],
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }
}
