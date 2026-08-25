<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class user_seeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Raul',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 871 251 2853',
        ]);

        User::create([
            'name' => 'Danna',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 871 281 0836',
        ]);

        User::create([
            'name' => 'Enevi',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 871 469 0352',
        ]);

        User::create([
            'name' => 'Isabella',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 871 333 8496',
        ]);

        User::create([
            'name' => 'Barbie',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 871 473 4639',
        ]);

        User::create([
            'name' => 'Ernesto',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 871 508 1823',
        ]);

        User::create([
            'name' => 'Mike',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 871 274 9939',
        ]);

        User::create([
            'name' => 'Santiago',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 871 176 4519',
        ]);

        User::create([
            'name' => 'Ivan',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 871 359 8197',
        ]);

        User::create([
            'name' => 'David',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 221 165 7616',
        ]);

        User::create([
            'name' => 'Hugo',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 871 399 3462',
        ]);

        User::create([
            'name' => 'Martha',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 871 184 1361',
        ]);

        User::create([
            'name' => 'Abril',
            'status' => 'AVAILABLE',
            'phone_number' => '+52 1 871 111 4662',
        ]);
    }
}