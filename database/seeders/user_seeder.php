<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class user_seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Raul',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2853',
            ],
            [
                'name' => 'Abril',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2854',
            ],
            [
                'name' => 'Isabella',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2855',
            ],
            [
                'name' => 'Martha',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2856',
            ],
            [
                'name' => 'Santi',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2857',
            ],
            [
                'name' => 'Barbie',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2858',
            ],
            [
                'name' => 'Danna',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2859',
            ],
            [
                'name' => 'Enevi',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2860',
            ],
            [
                'name' => 'Ernesto',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2861',
            ],
            [
                'name' => 'Mike',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2862',
            ],
            [
                'name' => 'Hugo',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2863',
            ],
            [
                'name' => 'Ivan',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2864',
            ],
            [
                'name' => 'David',
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2865',
            ],
        ];

        foreach ($users as $user) {
            User::create([
                'id' => Str::uuid(),
                'name' => $user['name'],
                'status' => $user['status'],
                'phone_number' => $user['phone_number'],
            ]);
        }
    }
}