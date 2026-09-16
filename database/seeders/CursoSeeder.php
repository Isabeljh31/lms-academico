<?php

namespace Database\Seeders;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Database\Seeder;

class CursoSeeder extends Seeder
{
    public function run(): void
    {
        $profesor = User::where('email', 'profesor@lms.edu.pe')->first();
        $estudiante = User::where('email', 'estudiante@lms.edu.pe')->first();

        if (! $profesor || ! $estudiante) {
            return;
        }

        $cursos = [
            ['nombre' => 'Matemática I', 'descripcion' => 'Álgebra y funciones básicas.'],
            ['nombre' => 'Historia del Perú', 'descripcion' => 'De las culturas prehispánicas a la república.'],
            ['nombre' => 'Introducción a la Programación', 'descripcion' => 'Fundamentos de lógica y PHP.'],
        ];

        foreach ($cursos as $datos) {
            $curso = Curso::firstOrCreate(
                ['nombre' => $datos['nombre'], 'profesor_id' => $profesor->id],
                ['descripcion' => $datos['descripcion']]
            );

            if ($curso->modulos()->count() === 0) {
                foreach (['Unidad 1: Introducción', 'Unidad 2: Desarrollo', 'Unidad 3: Cierre y evaluación'] as $i => $titulo) {
                    $curso->modulos()->create(['titulo' => $titulo, 'orden' => $i + 1, 'materiales' => []]);
                }
            }

            $curso->inscripciones()->firstOrCreate([
                'estudiante_id' => $estudiante->id,
            ], [
                'fecha_inscripcion' => now()->subWeeks(2),
            ]);

            if ($curso->tareas()->count() === 0) {
                $modulos = $curso->modulos()->orderBy('orden')->get();

                $tareaVencida = $curso->tareas()->create([
                    'modulo_id' => $modulos[0]->id,
                    'titulo' => 'Práctica calificada 1',
                    'descripcion' => 'Resuelve los ejercicios de la unidad 1.',
                    'fecha_entrega' => now()->subWeek(),
                    'peso_nota' => 5,
                ]);
                $tareaVencida->entregas()->create([
                    'estudiante_id' => $estudiante->id,
                    'archivo_url' => 'https://ui-avatars.com/api/?name=Entrega+1',
                    'comentario' => 'Aquí mi entrega.',
                    'estado' => 'calificado',
                    'nota' => 16.5,
                    'feedback' => 'Buen trabajo, revisa el ejercicio 3.',
                ]);

                $curso->tareas()->create([
                    'modulo_id' => $modulos[1]->id,
                    'titulo' => 'Práctica calificada 2',
                    'descripcion' => 'Ejercicios de la unidad 2.',
                    'fecha_entrega' => now()->addWeek(),
                    'peso_nota' => 5,
                ]);

                $curso->tareas()->create([
                    'modulo_id' => $modulos[2]->id,
                    'titulo' => 'Trabajo final',
                    'descripcion' => 'Entrega el proyecto integrador del curso.',
                    'fecha_entrega' => now()->addWeeks(3),
                    'peso_nota' => 10,
                ]);

                // Tarea vencida y SIN entrega, solo en el primer curso, para
                // que el bot de sugerencias (Módulo 7) tenga algo que detectar.
                if ($datos['nombre'] === 'Matemática I') {
                    $curso->tareas()->create([
                        'modulo_id' => $modulos[0]->id,
                        'titulo' => 'Ejercicios de repaso',
                        'descripcion' => 'Ejercicios de repaso de la unidad 1.',
                        'fecha_entrega' => now()->subDays(3),
                        'peso_nota' => 2,
                    ]);
                }
            }

            if ($curso->quizzes()->count() === 0) {
                $modulos = $curso->modulos()->orderBy('orden')->get();

                $curso->quizzes()->create([
                    'modulo_id' => $modulos[0]->id,
                    'tema' => 'Autoevaluación unidad 1',
                    'preguntas' => [
                        [
                            'enunciado' => '¿Cuál de estas opciones es correcta?',
                            'opciones' => ['Opción A', 'Opción B', 'Opción C'],
                            'respuesta_correcta' => 0,
                        ],
                        [
                            'enunciado' => '2 + 2 = ?',
                            'opciones' => ['3', '4', '5'],
                            'respuesta_correcta' => 1,
                        ],
                    ],
                ]);
            }
        }
    }
}
