<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Models\Sede;
use App\Models\TipoSalon;
use Illuminate\Database\Seeder;

/**
 * Siembra inicial del catálogo de reprogramación: sedes, tipos de salón y
 * los 9 salones de Los Olivos con los códigos exactos de las aulas que ya
 * usan las clases («Aula: A-201», «Aula: Lab 1», ...).
 *
 * Idempotente (updateOrCreate por código o nombre): se puede volver a
 * ejecutar sin duplicar filas. Solo escribe en sedes, tipos_salon y
 * salones; no toca cursos ni clases. No está en DatabaseSeeder: se ejecuta
 * a mano con `php artisan db:seed --class=SedesYTiposSalonSeeder`.
 */
class SedesYTiposSalonSeeder extends Seeder
{
    /** @var array<string, string> código => nombre */
    public const SEDES = [
        'LO' => 'Los Olivos',
        'CO' => 'Comas',
        'BR' => 'Breña',
        'CH' => 'Chorrillos',
        'SJL' => 'San Juan de Lurigancho',
        'ATE' => 'Ate',
    ];

    /** @var list<string> */
    public const TIPOS = [
        'Aula teórica',
        'Aula multimedia',
        'Laboratorio de cómputo',
        'Laboratorio de redes',
        'Laboratorio de electrónica y mecatrónica',
        'Laboratorio de ciencias',
        'Simulación clínica',
        'Taller',
        'Sala especial',
    ];

    /** @var array<string, array{nombre: string, tipo: string}> código => datos (sede Los Olivos) */
    public const SALONES_LOS_OLIVOS = [
        'A-201' => ['nombre' => 'Aula A-201', 'tipo' => 'Aula teórica'],
        'B-208' => ['nombre' => 'Aula B-208', 'tipo' => 'Aula teórica'],
        'B-105' => ['nombre' => 'Aula B-105', 'tipo' => 'Aula teórica'],
        'C-302' => ['nombre' => 'Aula C-302', 'tipo' => 'Aula teórica'],
        'A-110' => ['nombre' => 'Aula A-110', 'tipo' => 'Aula teórica'],
        'Lab 1' => ['nombre' => 'Laboratorio 1', 'tipo' => 'Laboratorio de cómputo'],
        'Lab 2' => ['nombre' => 'Laboratorio 2', 'tipo' => 'Laboratorio de cómputo'],
        'Lab 3' => ['nombre' => 'Laboratorio 3', 'tipo' => 'Laboratorio de cómputo'],
        'Lab 4' => ['nombre' => 'Laboratorio 4', 'tipo' => 'Laboratorio de cómputo'],
    ];

    public const CAPACIDAD = 40;

    public function run(): void
    {
        $sedes = collect(self::SEDES)->map(
            fn (string $nombre, string $codigo) => Sede::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre]),
        );

        $tipos = collect(self::TIPOS)->mapWithKeys(
            fn (string $nombre) => [$nombre => TipoSalon::updateOrCreate(['nombre' => $nombre])],
        );

        foreach (self::SALONES_LOS_OLIVOS as $codigo => $datos) {
            Salon::updateOrCreate(['codigo' => $codigo], [
                'sede_id' => $sedes['LO']->id,
                'nombre' => $datos['nombre'],
                'tipo_salon_id' => $tipos[$datos['tipo']]->id,
                'capacidad' => self::CAPACIDAD,
            ]);
        }
    }
}
