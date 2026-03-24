<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentItemSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $setId = DB::table('content_sets')->where('slug', 'baby-boy-names-2024')->value('id');

        $names = [
            'Oliver', 'Liam', 'Noah', 'William', 'James', 'Benjamin', 'Lucas', 'Henry',
            'Alexander', 'Mason', 'Ethan', 'Daniel', 'Jacob', 'Logan', 'Jackson',
            'Sebastian', 'Jack', 'Aiden', 'Owen', 'Samuel', 'Elijah', 'Matthew',
            'Ryan', 'Nathan', 'Dylan', 'Caleb', 'Luke', 'Isaac', 'Gabriel', 'Julian',
            'Carter', 'Wyatt', 'Jayden', 'Hunter', 'Eli', 'Isaiah', 'Connor', 'Andrew',
            'Christian', 'Jonathan', 'Cameron', 'Nicholas', 'Adrian', 'Nolan', 'Thomas',
            'Grayson', 'Brayden', 'Colton', 'Zachary', 'Angel', 'Austin', 'Jaxon',
            'Dominic', 'Leonardo', 'Levi', 'Asher', 'Josiah', 'Hudson', 'Ezra',
            'Axel', 'Everett', 'Declan', 'Parker', 'Miles', 'Sawyer', 'Landon',
            'Gavin', 'Jordan', 'Ian', 'Diego', 'Evan', 'Chase', 'Jason', 'Cooper',
            'Xavier', 'Jace', 'Roman', 'Greyson', 'Ezekiel', 'Weston', 'Silas',
            'Carson', 'Micah', 'Luca', 'Bryson', 'Cole', 'Braxton', 'Theodore',
            'Harrison', 'Tyler', 'Brody', 'Bennett', 'Damian', 'Ashton', 'Spencer',
            'Tristan', 'Bentley', 'Giovanni', 'Kai', 'Colin', 'Caden', 'Hayden',
            'Beckett', 'George', 'Kingston', 'Zane', 'Ryder', 'Emmanuel', 'Jesus',
            'Max', 'Jaden', 'Maximus', 'Emmett', 'Ryker', 'Mateo', 'Ivan', 'Jonah',
            'Marcus', 'Xander', 'Jasper', 'Jaxson', 'Rowan', 'Malachi', 'Jesse',
            'Maddox', 'Beau', 'Theo', 'Elias', 'Tobias', 'Finn', 'Rhys', 'Archer',
            'Calvin', 'Abel', 'Milo', 'Felix', 'Elliot', 'Adrián', 'Omar', 'Josue',
            'Simon', 'Bryce', 'Lukas', 'Leon', 'Devin', 'Brendan', 'Gage', 'Vincent',
            'Micaiah', 'Knox', 'Tanner', 'Reid', 'Corey', 'Seth', 'Zion', 'Arlo',
            'Sterling', 'Phoenix', 'Kyrie', 'Griffin', 'Nash', 'Wade', 'Lane',
            'Drake', 'Brooks', 'Reid', 'Clayton', 'Paxton', 'Lennox', 'Darius',
            'Dorian', 'Emilio', 'Rodrigo', 'Rafael', 'Santiago', 'Marco', 'Antonio',
            'Cruz', 'Sergio', 'Eduardo', 'Alejandro', 'Nico', 'Carlos', 'Miguel',
            'Armando', 'Javier', 'Pedro', 'Felipe', 'Pablo', 'Andres', 'Cristian',
            'Maximiliano', 'Killian', 'Colt', 'Jett', 'Crew', 'Soren', 'Emmitt',
            'Bodhi', 'Caspian', 'Thaddeus', 'Remington', 'Sage', 'Orion', 'Atlas',
            'Zephyr', 'Idris', 'Alaric', 'Leandro', 'Ignacio', 'Aurelio', 'Florian',
            'Bastian', 'Raffael', 'Levin', 'Moritz', 'Emil', 'Oskar', 'Leopold',
            'Rupert', 'Clement', 'Barnaby', 'Rafferty', 'Stellan', 'Caius', 'Piers',
        ];

        // Deduplicate while preserving order
        $names = array_values(array_unique($names));

        $rows = [];
        foreach ($names as $index => $name) {
            $rows[] = [
                'content_set_id' => $setId,
                'display_value'  => $name,
                'meta'           => null,
                'sort_order'     => $index + 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        DB::table('content_items')->insertOrIgnore($rows);
    }
}
