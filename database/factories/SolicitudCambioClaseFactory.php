<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solicitud pendiente de una clase futura de un curso nuevo.
 *
 * @extends Factory<SolicitudCambioClase>
 */
class SolicitudCambioClaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sesion_id' => fn () => SesionClase::create([
                'curso_id' => Curso::factory()->create(['usa_asistencia' => true])->id,
                'titulo' => 'Clase',
                'fecha' => now()->addWeek()->setTime(8, 0),
                'duracion_minutos' => 90,
                'numero_sesion' => 1,
            ])->id,
            'curso_id' => fn (array $atributos) => SesionClase::find($atributos['sesion_id'])->curso_id,
            'solicitado_por' => fn (array $atributos) => Curso::find($atributos['curso_id'])->profesor_id,
            'tipo' => 'reprogramar',
            'motivo' => fake()->randomElement(array_keys(SolicitudCambioClase::MOTIVOS)),
            'detalle' => null,
            'modalidad_solicitada' => 'presencial',
            'fecha_propuesta' => null,
            'fecha_original' => fn (array $atributos) => SesionClase::find($atributos['sesion_id'])->fecha,
            'duracion_original' => 90,
            'estado' => 'pendiente',
        ];
    }

    public function aprobada(): static
    {
        return $this->state(fn () => ['estado' => 'aprobada', 'decidido_en' => now()]);
    }

    public function rechazada(): static
    {
        return $this->state(fn () => ['estado' => 'rechazada', 'decidido_en' => now(), 'comentario_decision' => 'Sin aulas disponibles.']);
    }
}
