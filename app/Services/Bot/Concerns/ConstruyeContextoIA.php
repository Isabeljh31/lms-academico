<?php

namespace App\Services\Bot\Concerns;

use App\Models\Curso;

/**
 * Lógica compartida entre proveedores de IA (Groq, Claude, ...): el
 * prompt de sistema con el contexto del curso actual, y la respuesta de
 * modo simulado cuando el proveedor no tiene credenciales configuradas.
 * Se extrajo a un trait para que ambos proveedores se comporten igual
 * frente al mismo curso/pregunta, sin duplicar el texto ni el criterio.
 */
trait ConstruyeContextoIA
{
    protected function promptSistema(?Curso $curso): string
    {
        $base = 'Eres el asistente de un LMS académico. Responde preguntas frecuentes de estudiantes y '
            .'profesores de forma breve, clara y en español. No inventes datos de cursos que no te den.';

        if (! $curso) {
            return $base;
        }

        $unidades = $curso->modulos->pluck('titulo')->implode(', ');

        return $base."\n\nContexto del curso actual:\nNombre: {$curso->nombre}\n"
            .'Descripción: '.($curso->descripcion ?? 'sin descripción').
            "\nUnidades: {$unidades}";
    }

    protected function respuestaSimulada(string $pregunta, ?Curso $curso, string $variableFaltante): string
    {
        $contexto = $curso ? " sobre el curso \"{$curso->nombre}\"" : '';

        return "[MODO SIMULADO — configura {$variableFaltante} para respuestas reales]\n\n"
            ."Recibí tu pregunta{$contexto}: \"{$pregunta}\". "
            .'En modo simulado no puedo generar una respuesta real, pero así se vería el flujo completo: '
            .'el bot leería el contexto del curso (unidades, tareas) y te daría una respuesta breve y concreta.';
    }
}
