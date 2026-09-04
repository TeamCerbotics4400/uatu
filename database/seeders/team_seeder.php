<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class team_seeder extends Seeder
{
    public function run(): void
    {
        $countries = ['Mexico', 'USA', 'Canada', 'Japan', 'Brazil', 'Kazahstan'];

        foreach ($countries as $country) {
            Team::create([
                'name' => $country,
                'priority' => '1',
                'required_service' => 'NONE',
                'current_service_status' => 'NOT_HELPED',
            ]);
        }
    }
}