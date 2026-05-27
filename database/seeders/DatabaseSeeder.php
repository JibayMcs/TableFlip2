<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesSeeder::class);

        if (! User::where('email', 'admin@tableflip.local')->exists()) {
            User::create([
                'name' => 'Admin',
                'email' => 'admin@tableflip.local',
                'password' => Hash::make('password'),
            ])->assignRole('admin');
        }
    }
}
