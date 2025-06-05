<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@halifax.com'],
            [
                'name' => 'admin',
                'username' => 'admin_halifax',
                'email' => 'admin@halifax.com',
                'password' => Hash::make('@halifax83214'),
                'role' => 'admin',
            ]
        );
    }
}
