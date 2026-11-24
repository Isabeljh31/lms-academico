<?php

namespace App\Services\Asistencia;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Salon;
use App\Models\SesionClase;
use App\Models\User;
use App\Services\Reportes\AsistenciaProfesor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * «Asignar salón a las clases futuras de un curso» (Supervisor, curso por
 * curso). Solo toca las clases futuras sin salon_id cuya línea «Aula: X»
 * coincide EXACTAMENTE (mayúsculas incluidas) con el código de un salón
 * activo; las demás no se tocan. La descripción no cambia (ya dice ese
 * código). Antes de escribir guarda un respaldo JSON en el disco local
 * (backups-reprogramacion/asignaciones/) y, si todos los salones asignados
 * son de una misma sede, fija cursos.sede_id. No es una migración masiva.
 */
class AsignacionSalones
{
    public const CARPETA_RESPALDOS = 'backups-reprogramacion/asignaciones';

    /**
     * @return array{asignables: Collection<int, array{sesion: SesionClase, aula: string, salon: Salon}>, sin_coincidencia: Collection<int, array{sesion: SesionClase, aula: ?string}>}
     */
    public function vistaPrevia(Curso $curso): array
    {
        return $this->clasificar($this->clasesFuturasSinSalon($curso)->get());
    }

    /**
     * @return array{asignadas: int, sede_id: ?int, sedes_distintas: bool, respaldo: string}
     */
    public function asignar(User $supervisor, Curso $curso): array
    {
        Gate::forUser($supervisor)->authorize('gestionar', Salon::class);

        return DB::transaction(function () use ($supervisor, $curso) {
            $clasificadas = $this->clasificar($this->clasesFuturasSinSalon($curso)->lockForUpdate()->get());
            $asignables = $clasificadas['asignables'];

            if ($asignables->isEmpty()) {
                throw ValidationException::withMessages(['asignacion' => 'No hay clases futuras con un aula que coincida exactamente con un salón.']);
            }

            $sedes = $asignables->pluck('salon.sede_id')->unique()->values();
            $sedeNueva = $sedes->count() === 1 ? $sedes->first() : null;
            $respaldo = self::CARPETA_RESPALDOS.'/'.now()->format('Ymd-His').'-curso-'.$curso->id.'.json';

            Storage::disk('local')->put($respaldo, json_encode([
                'curso_id' => $curso->id,
                'sede_id' => $curso->sede_id,
                'sesiones' => $asignables->map(fn (array $fila) => $fila['sesion']->only(['id', 'salon_id', 'descripcion']))->values(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            foreach ($asignables as $fila) {
                $fila['sesion']->update(['salon_id' => $fila['salon']->id]);
            }

            $sedeAnterior = $curso->sede_id;

            if ($sedeNueva !== null) {
                $curso->update(['sede_id' => $sedeNueva]);
            }

            BitacoraAuditoria::registrarAccionAdmin(
                $supervisor,
                'catalogo_salones',
                "Salones asignados a {$asignables->count()} clases futuras del curso {$curso->nombre}.",
                null,
                $curso,
                ['sede_id' => $sedeAnterior],
                [
                    'sede_id' => $curso->sede_id,
                    'clases' => $asignables->map(fn (array $fila) => ['sesion_id' => $fila['sesion']->id, 'salon' => $fila['salon']->codigo])->values(),
                    'respaldo' => $respaldo,
                ],
            );

            return [
                'asignadas' => $asignables->count(),
                'sede_id' => $curso->sede_id,
                'sedes_distintas' => $sedes->count() > 1,
                'respaldo' => $respaldo,
            ];
        });
    }

    /** @return Builder<SesionClase> */
    protected function clasesFuturasSinSalon(Curso $curso): Builder
    {
        return SesionClase::query()
            ->where('curso_id', $curso->id)
            ->whereNull('salon_id')
            ->where('fecha', '>', now())
            ->orderBy('fecha')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, SesionClase>  $clases
     * @return array{asignables: Collection<int, array{sesion: SesionClase, aula: string, salon: Salon}>, sin_coincidencia: Collection<int, array{sesion: SesionClase, aula: ?string}>}
     */
    protected function clasificar(Collection $clases): array
    {
        $aulas = $clases->map(fn (SesionClase $sesion) => AsistenciaProfesor::aula($sesion));
        $salones = Salon::query()->activos()->whereIn('codigo', $aulas->filter()->unique()->values())->get()
            // Coincidencia exacta también en motores que comparan sin distinguir mayúsculas.
            ->keyBy('codigo');

        $filas = $clases->map(function (SesionClase $sesion, int $indice) use ($aulas, $salones) {
            $aula = $aulas[$indice];
            $salon = $aula !== null ? $salones->first(fn (Salon $salon) => $salon->codigo === $aula) : null;

            return ['sesion' => $sesion, 'aula' => $aula, 'salon' => $salon];
        });

        return [
            'asignables' => $filas->filter(fn (array $fila) => $fila['salon'] !== null)->values(),
            'sin_coincidencia' => $filas->filter(fn (array $fila) => $fila['salon'] === null)
                ->map(fn (array $fila) => ['sesion' => $fila['sesion'], 'aula' => $fila['aula']])->values(),
        ];
    }
}
