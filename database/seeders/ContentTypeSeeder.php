<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::table('content_types')->insertOrIgnore([
            [
                'slug'       => 'name',
                'label'      => 'Baby Names',
                'config'     => json_encode(['max_length' => 64]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
