<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {

            User::updateOrCreate(
                [
                    'email' => "testuser{$i}@test.com",
                ],
                [
                    'name'     => "Test User {$i}",
                    'password' => Hash::make('123456'),
                    'balance'  => number_format(1000000 * $i, 8, '.', ''),
                ]
            );
        }
    }
}
