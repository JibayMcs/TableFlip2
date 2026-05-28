<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // No seeding needed — TableFlip authenticates against the
        // database server directly. There is no application-side user
        // store to populate.
    }
}
