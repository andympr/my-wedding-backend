<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (!User::where('email','admin@mywedding.local')->exists()) {
            User::create([
                'name' => 'Admin',
                'email' => 'admin@mywedding.local',
                // bcrypt seguro usando PHP nativo
                'password' => password_hash('M&we3dd1ng', PASSWORD_BCRYPT),
                'role' => 'admin',
            ]);
        }
    }
}
