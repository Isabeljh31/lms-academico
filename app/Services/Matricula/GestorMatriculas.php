<?php

namespace App\Services\Matricula;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use App\Notifications\MatriculadoEnCurso;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Reglas de Matrícula (ERS 3.4) compartidas por la matrícula manual del
 * Supervisor y la API de integración:
 *
 * - RF-26/RF-27: matrícula manual o por sistema externo.
 * - RF-28: se registra el origen (manual | api).
 * - RF-29/RF-30: la baja es lógica y conserva el historial.
 * - RF-32/RF-105: una matrícula creada por API no se edita localmente; el
 *   Supervisor solo desactiva (o reactiva) el acceso local.
 * - RF-33 / regla 10: toda matrícula notifica al estudiante (in-app y correo).
 * - RF-96: matrículas y bajas quedan en la bitácora de auditoría.
 * - Regla 8: un curso archivado no admite cambios de matrícula.
 */
class GestorMatriculas
{
    /**
     * @throws ValidationException
     */
    public function matricularManual(Curso $curso, User $estudiante, User $ejecutor): Inscripcion
    {
        $this->validarCursoYEstudiante($curso, $estudiante, 'estudianteSeleccionadoId');

        $inscripcion = Inscripcion::where('curso_id', $curso->id)->where('estudiante_id', $estudiante->id)->first();

        if ($inscripcion?->estaActiva()) {
            throw ValidationException::withMessages([
                'estudianteSeleccionadoId' => "{$estudiante->name} ya está matriculado en este curso.",
            ]);
        }

        if ($inscripcion?->esDeApiExterna()) {
            if ($inscripcion->estado !== 'activo') {
                throw ValidationException::withMessages([
                    'estudianteSeleccionadoId' => 'La matrícula de este estudiante viene del sistema externo y allí figura inactiva. Debe reactivarse en el sistema de origen.',
                ]);
            }

            $inscripcion->update(['activo_local' => true, 'fecha_baja' => null, 'dado_de_baja_por' => null]);
            $descripcion = "Acceso local reactivado (matrícula de API): {$estudiante->name} en \"{$curso->nombre}\".";
        } elseif ($inscripcion) {
            // Reactivación de una baja manual: se conserva el historial (RF-30).
            $inscripcion->update(['estado' => 'activo', 'activo_local' => true, 'fecha_baja' => null, 'dado_de_baja_por' => null]);
            $descripcion = "Matrícula manual reactivada: {$estudiante->name} ({$estudiante->email}) en \"{$curso->nombre}\".";
        } else {
            $inscripcion = Inscripcion::create([
                'curso_id' => $curso->id,
                'estudiante_id' => $estudiante->id,
                'fecha_inscripcion' => now(),
                'origen' => 'manual',
                'estado' => 'activo',
                'activo_local' => true,
            ]);
            $descripcion = "Matrícula manual: {$estudiante->name} ({$estudiante->email}) en \"{$curso->nombre}\".";
        }

        $this->notificar($estudiante, $curso, 'manual');

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: $ejecutor,
            tipoEvento: 'matricula_manual',
            descripcion: $descripcion,
            usuarioAfectado: $estudiante,
            curso: $curso,
        );

        return $inscripcion;
    }

    /**
     * RF-27, RF-104, RF-105: sincroniza la matrícula informada por el sistema
     * externo (fuente de verdad). Devuelve la inscripción (o null si se
     * informó una baja de una matrícula inexistente).
     */
    public function sincronizarDesdeApi(Curso $curso, User $estudiante, string $estado, ?string $integracion = null): ?Inscripcion
    {
        $this->validarCursoYEstudiante($curso, $estudiante, 'usuario.rol');

        $inscripcion = Inscripcion::where('curso_id', $curso->id)->where('estudiante_id', $estudiante->id)->first();
        $origen = $integracion ? "integración \"{$integracion}\"" : 'integración externa';

        if ($estado === 'activo') {
            $nueva = ! $inscripcion || ! $inscripcion->estaActiva();

            if (! $inscripcion) {
                $inscripcion = Inscripcion::create([
                    'curso_id' => $curso->id,
                    'estudiante_id' => $estudiante->id,
                    'fecha_inscripcion' => now(),
                    'origen' => 'api',
                    'estado' => 'activo',
                    'activo_local' => true,
                ]);
            } elseif ($inscripcion->esDeApiExterna()) {
                // El sistema externo manda: se refleja su estado. El bloqueo de
                // acceso local que haya puesto el Supervisor se respeta.
                $inscripcion->update(['estado' => 'activo']);
                $nueva = $nueva && $inscripcion->activo_local;
            } else {
                // Matrícula manual previa: pasa a ser gestionada por el sistema externo.
                $inscripcion->update(['origen' => 'api', 'estado' => 'activo', 'activo_local' => true, 'fecha_baja' => null, 'dado_de_baja_por' => null]);
            }

            if ($nueva) {
                $this->notificar($estudiante, $curso, 'api');
            }

            BitacoraAuditoria::registrarAccionAdmin(
                ejecutor: null,
                tipoEvento: 'matricula_api',
                descripcion: "Matrícula activa sincronizada por {$origen}: {$estudiante->name} en \"{$curso->nombre}\".",
                usuarioAfectado: $estudiante,
                curso: $curso,
            );

            return $inscripcion;
        }

        if ($inscripcion) {
            $inscripcion->update(['estado' => 'inactivo', 'fecha_baja' => now()]);

            BitacoraAuditoria::registrarAccionAdmin(
                ejecutor: null,
                tipoEvento: 'matricula_api',
                descripcion: "Baja sincronizada por {$origen}: {$estudiante->name} en \"{$curso->nombre}\". El historial se conserva.",
                usuarioAfectado: $estudiante,
                curso: $curso,
            );
        }

        return $inscripcion;
    }

    /**
     * RF-29 a RF-32: baja lógica. Si la matrícula viene de la API, solo se
     * desactiva el acceso local.
     */
    public function darDeBaja(Inscripcion $inscripcion, User $ejecutor, ?string $motivo = null): void
    {
        $curso = $inscripcion->curso;

        if ($curso->estaFinalizado()) {
            throw ValidationException::withMessages(['inscripcion' => 'El curso está archivado y no admite cambios de matrícula.']);
        }

        $esApi = $inscripcion->esDeApiExterna();
        $esApi ? $inscripcion->desactivarAccesoLocal($ejecutor) : $inscripcion->darDeBaja($ejecutor);

        $estudiante = $inscripcion->estudiante;

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: $ejecutor,
            tipoEvento: 'baja_estudiante',
            descripcion: ($esApi ? 'Acceso local desactivado (matrícula de API)' : 'Baja manual')
                ." de {$estudiante?->name} en \"{$curso->nombre}\". El historial se conserva.",
            usuarioAfectado: $estudiante,
            curso: $curso,
            motivo: $motivo,
        );
    }

    /**
     * @throws ValidationException
     */
    protected function validarCursoYEstudiante(Curso $curso, User $estudiante, string $campo): void
    {
        if ($curso->estaFinalizado()) {
            throw ValidationException::withMessages([$campo => 'El curso está archivado y no admite nuevas matrículas.']);
        }

        if (! $estudiante->hasRole('estudiante')) {
            throw ValidationException::withMessages([$campo => 'Solo se pueden matricular cuentas con rol Estudiante.']);
        }

        if (! $estudiante->activo) {
            throw ValidationException::withMessages([$campo => 'La cuenta del estudiante está desactivada.']);
        }
    }

    protected function notificar(User $estudiante, Curso $curso, string $origen): void
    {
        try {
            $estudiante->notify(new MatriculadoEnCurso($curso, $origen));
        } catch (Throwable $e) {
            // El fallo del correo no revierte la matrícula; la notificación in-app ya se guardó.
            Log::warning('No se pudo enviar la notificación de matrícula', ['usuario_id' => $estudiante->id, 'error' => $e->getMessage()]);
        }
    }
}
