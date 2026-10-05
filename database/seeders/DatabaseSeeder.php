<?php

namespace Database\Seeders;

use App\Services\ConfiguracionPlataforma;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ERS RF-108: los datos de ejemplo se fechan en la hora de la institución.
        app(ConfiguracionPlataforma::class)->aplicarZonaHoraria();

        $this->call([
            RoleAndUserSeeder::class,
            CursoSeeder::class,
            // Datos de ejemplo completos para revisar cada requisito del ERS (idempotente).
            DemoAcademicoSeeder::class,
            // Después de los datos de ejemplo: las propuestas usan sus tareas vencidas.
            PropuestaBotSeeder::class,
        ]);
    }
}
