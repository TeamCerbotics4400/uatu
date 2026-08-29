<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Orden importante:
        // 1. Users (sin dependencias)
        // 2. Teams (sin dependencias)
        // 3. Matches (sin dependencias)
        // 4. ServiceTasks (depende de Users y Teams)
        // 5. MXTasks (depende de Users y Teams)

        $this->call([
            user_seeder::class,
            team_seeder::class,
            // Agregar más seeders aquí cuando existan
            // matches_seeder::class,
            // service_tasks_seeder::class,
            // mx_tasks_seeder::class,
        ]);
    }
}