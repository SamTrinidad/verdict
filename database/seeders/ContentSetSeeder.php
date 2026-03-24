<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentSetSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $typeId = DB::table('content_types')->where('slug', 'name')->value('id');

        DB::table('content_sets')->insertOrIgnore([
            [
                'content_type_id' => $typeId,
                'name'            => 'Baby Boy Names 2024',
                'slug'            => 'baby-boy-names-2024',
                'description'     => 'A curated list of popular baby boy names for 2024.',
                'user_id'         => null,
                'visibility'      => 'system',
                'meta'            => json_encode(['year' => 2024, 'gender' => 'boy']),
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ]);
    }
}
