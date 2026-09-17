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
                'is_admin' => true,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2853',
            ],
            [
                'name' => 'Abril',
                'is_admin' => false,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2854',
            ],
            [
                'name' => 'Isabella',
                'is_admin' => false,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2855',
            ],
            [
                'name' => 'Martha',
                'is_admin' => false,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2856',
            ],
            [
                'name' => 'Santi',
                'is_admin' => false,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2857',
            ],
            [
                'name' => 'Barbie',
                'is_admin' => false,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2858',
            ],
            [
                'name' => 'Danna',
                'is_admin' => false,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2859',
            ],
            [
                'name' => 'Enevi',
                'is_admin' => false,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2860',
            ],
            [
                'name' => 'Ernesto',
                'is_admin' => false,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2861',
            ],
            [
                'name' => 'Mike',
                'is_admin' => true,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2862',
            ],
            [
                'name' => 'Hugo',
                'is_admin' => true,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2863',
            ],
            [
                'name' => 'Ivan',
                'is_admin' => true,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2864',
            ],
            [
                'name' => 'David',
                'is_admin' => true,
                'status' => 'AVAILABLE',
                'phone_number' => '+52 1 871 251 2865',
            ],
        ];

        foreach ($users as $user) {
            User::create([
                'id' => Str::uuid(),
                'name' => $user['name'],
                'status' => $user['status'],
                'is_admin' => $user['is_admin'],
                'phone_number' => $user['phone_number'],
            ]);
        }
    }
}