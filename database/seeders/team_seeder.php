<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class team_seeder extends Seeder
{
    public function run(): void
    {
        $countries = ['Mexico', 'USA', 'Canada', 'Japan', 'Brazil', 'Kazakhstan'];

        foreach ($countries as $country) {
            Team::create([
                'name' => $country,
                'current_service_status' => 'NOT_HELPED',
            ]);
        }
    }
}