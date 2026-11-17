<?php

namespace App\Services\Asistencia;

use App\Models\Curso;
use App\Models\Salon;
use App\Models\SesionClase;
use App\Models\User;
use App\Services\ConfiguracionPlataforma;
use App\Services\Reportes\AsistenciaProfesor;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de disponibilidad para reprogramar una clase con salones reales
 * (extensión fuera del ERS). Una reprogramación se aplica sola solo si
 * evaluar() no devuelve razones:
 *
 * - Presencial: la clase ya tiene salón (de él salen la sede y el tipo) y el
 *   salón elegido está activo, es de la misma sede y del mismo tipo, tiene
 *   capacidad para los matriculados activos y está libre.
 * - Remota: no usa salón.
 * - Siempre: ningún docente del curso (titular o co-docente) tiene otra
 *   clase que se cruce, de cualquier curso; la clase original y la nueva
 *   cumplen el aviso mínimo; la nueva hora cae de lunes a sábado entre las
 *   07:00 y las 22:00.
 *
 * Un salón está ocupado si otra clase con ese salon_id se cruza, o si una
 * clase antigua (sin salon_id) tiene escrito «Aula: CÓDIGO» con su mismo
 * código (criterio conservador). El cruce se calcula en PHP sobre las
 * clases que empiezan entre un día antes del inicio y el fin, igual en
 * PostgreSQL y SQLite. El Profesor solo ve libre u ocupado, nunca qué curso
 * ocupa un salón.
 */
class DisponibilidadSalones
{
    public const HORA_PRIMERA = '07:00';

    public const HORA_ULTIMA = '22:00';

    public const PASO_MINUTOS = 30;

    public const MENSAJE_HORARIO_TOMADO = 'Ese horario ya fue tomado, elige otro.';

    public function __construct(protected ConfiguracionPlataforma $configuracion) {}

    public function avisoMinimoHoras(): int
    {
        return $this->configuracion->avisoMinimoReprogramacionHoras();
    }

    /** Sede de la clase: la de su salón actual o, si no tiene, la del curso. */
    public function sedeId(SesionClase $sesion): ?int
    {
        return $sesion->salon?->sede_id ?? $sesion->curso->sede_id;
    }

    public function matriculados(Curso $curso): int
    {
        return $curso->inscripcionesActivas()->count();
    }

    /**
     * Salones a los que se puede mover la clase: activos, de una sede activa,
     * de la misma sede y tipo que su salón actual y con capacidad para los
     * matriculados. Vacío si la clase no tiene salón.
     *
     * @return Collection<int, Salon>
     */
    public function candidatos(SesionClase $sesion): Collection
    {
        $actual = $sesion->salon;

        if ($actual === null) {
            return collect();
        }

        return Salon::query()
            ->activos()
            ->where('sede_id', $actual->sede_id)
            ->where('tipo_salon_id', $actual->tipo_salon_id)
            ->where('capacidad', '>=', $this->matriculados($sesion->curso))
            ->whereHas('sede', fn ($consulta) => $consulta->where('activa', true))
            ->with('tipo:id,nombre')
            ->orderBy('codigo')
            ->get();
    }

    /**
     * Candidatos libres en ese horario, para la lista que ve el Profesor
     * (código, tipo, capacidad y «Libre»).
     *
     * @return Collection<int, Salon>
     */
    public function salonesLibres(SesionClase $sesion, Carbon $inicio, int $duracion): Collection
    {
        $candidatos = $this->candidatos($sesion);

        if ($candidatos->isEmpty()) {
            return $candidatos;
        }

        $fin = $inicio->copy()->addMinutes($duracion);
        $clases = $this->clasesEnSalones($candidatos, $inicio, $fin, $sesion->id);

        return $candidatos->reject(fn (Salon $salon) => $this->estaOcupado($salon, $clases, $inicio, $fin))->values();
    }

    /**
     * Lista real del Supervisor al aprobar: salones activos (de una sede
     * activa) libres en ese horario, de la sede de la clase si se conoce o de
     * todas si no. No filtra por tipo ni capacidad: decide el Supervisor.
     *
     * @return Collection<int, Salon>
     */
    public function salonesParaSupervisor(SesionClase $sesion, Carbon $inicio, int $duracion): Collection
    {
        $sedeId = $this->sedeId($sesion);
        $salones = Salon::query()
            ->activos()
            ->when($sedeId !== null, fn ($consulta) => $consulta->where('sede_id', $sedeId))
            ->whereHas('sede', fn ($consulta) => $consulta->where('activa', true))
            ->with(['tipo:id,nombre', 'sede:id,nombre,codigo'])
            ->orderBy('codigo')
            ->get();

        if ($salones->isEmpty()) {
            return $salones;
        }

        $fin = $inicio->copy()->addMinutes($duracion);
        $clases = $this->clasesEnSalones($salones, $inicio, $fin, $sesion->id);

        return $salones->reject(fn (Salon $salon) => $this->estaOcupado($salon, $clases, $inicio, $fin))->values();
    }

    public function salonLibre(Salon $salon, Carbon $inicio, int $duracion, ?int $excluirSesionId = null): bool
    {
        $fin = $inicio->copy()->addMinutes($duracion);

        return ! $this->estaOcupado($salon, $this->clasesEnSalones(collect([$salon]), $inicio, $fin, $excluirSesionId), $inicio, $fin);
    }

    /** Ningún docente del curso tiene otra clase (de cualquier curso) que se cruce con ese horario. */
    public function docentesLibres(SesionClase $sesion, Carbon $inicio, int $duracion): bool
    {
        $fin = $inicio->copy()->addMinutes($duracion);

        return $this->clasesDeLosDocentes($sesion->curso, $inicio, $fin, $sesion->id)
            ->doesntContain(fn (SesionClase $clase) => self::seCruzan($clase, $inicio, $fin));
    }

    /** De lunes a sábado, empieza desde las 07:00 y termina a más tardar a las 22:00. */
    public function dentroDelHorario(Carbon $inicio, int $duracion): bool
    {
        $primera = $inicio->copy()->setTimeFromTimeString(self::HORA_PRIMERA);
        $ultima = $inicio->copy()->setTimeFromTimeString(self::HORA_ULTIMA);

        return ! $inicio->isSunday() && $inicio->gte($primera) && $inicio->copy()->addMinutes($duracion)->lte($ultima);
    }

    /**
     * Horas de inicio de un día, en pasos de 30 minutos de 07:00 a 22:00 (sin
     * domingos), con la duración de la clase original. Disponible = futura,
     * docentes libres y, si es presencial, algún salón candidato libre. Usa
     * dos consultas para todo el día (más la de candidatos).
     *
     * @return list<array{hora: string, disponible: bool}>
     */
    public function horas(SesionClase $sesion, Carbon $dia, bool $presencial): array
    {
        if ($dia->isSunday()) {
            return [];
        }

        $duracion = (int) $sesion->duracion_minutos;
        $desde = $dia->copy()->setTimeFromTimeString(self::HORA_PRIMERA);
        $hasta = $dia->copy()->setTimeFromTimeString(self::HORA_ULTIMA);
        $candidatos = $presencial ? $this->candidatos($sesion) : collect();
        $enSalones = $candidatos->isNotEmpty() ? $this->clasesEnSalones($candidatos, $desde, $hasta, $sesion->id) : collect();
        $deDocentes = $this->clasesDeLosDocentes($sesion->curso, $desde, $hasta, $sesion->id);
        $horas = [];

        for ($inicio = $desde->copy(); $inicio->copy()->addMinutes($duracion)->lte($hasta); $inicio->addMinutes(self::PASO_MINUTOS)) {
            $fin = $inicio->copy()->addMinutes($duracion);
            $docentesLibres = $deDocentes->doesntContain(fn (SesionClase $clase) => self::seCruzan($clase, $inicio, $fin));
            $hayLugar = ! $presencial || $candidatos->contains(fn (Salon $salon) => ! $this->estaOcupado($salon, $enSalones, $inicio, $fin));

            $horas[] = ['hora' => $inicio->format('H:i'), 'disponible' => $inicio->isFuture() && $docentesLibres && $hayLugar];
        }

        return $horas;
    }

    /**
     * Evalúa todas las reglas para mover la clase a ese horario. Sin razones
     * se aplica sola; con alguna, va a revisión del Supervisor. Un salón
     * ocupado no es una razón de revisión: se avisa con MENSAJE_HORARIO_TOMADO.
     * Los avisos (p. ej. fuera de las semanas del curso) no bloquean.
     *
     * @return array{razones: list<string>, salon_ocupado: bool, avisos: list<string>}
     */
    public function evaluar(SesionClase $sesion, Carbon $inicio, int $duracion, bool $presencial, ?Salon $salon): array
    {
        $razones = [];
        $ocupado = false;

        if ($presencial) {
            $actual = $sesion->salon;

            if ($actual === null) {
                $razones[] = 'La clase no tiene salón asignado.';
            } elseif ($salon === null) {
                $razones[] = 'No se eligió un salón.';
            } else {
                $matriculados = $this->matriculados($sesion->curso);
                $ocupado = ! $this->salonLibre($salon, $inicio, $duracion, $sesion->id);

                array_push($razones, ...array_values(array_filter([
                    ! $salon->activo || ! $salon->sede?->activa ? 'El salón elegido no está activo.' : null,
                    $salon->sede_id !== $actual->sede_id ? 'El salón elegido es de otra sede.' : null,
                    $salon->tipo_salon_id !== $actual->tipo_salon_id ? 'El salón elegido es de otro tipo.' : null,
                    $salon->capacidad < $matriculados ? "El salón elegido no tiene capacidad para {$matriculados} matriculados." : null,
                ])));
            }
        }

        if (! $this->docentesLibres($sesion, $inicio, $duracion)) {
            $razones[] = 'Un docente del curso tiene otra clase en ese horario.';
        }

        if (! $inicio->equalTo($sesion->fecha) && ! $this->dentroDelHorario($inicio, $duracion)) {
            $razones[] = 'Fuera del horario de reprogramación (lunes a sábado, de 07:00 a 22:00).';
        }

        $limite = now()->addHours($this->avisoMinimoHoras());

        if ($sesion->fecha->lt($limite) || $inicio->lt($limite)) {
            $razones[] = "Aviso menor a {$this->avisoMinimoHoras()} h.";
        }

        return [
            'razones' => $razones,
            'salon_ocupado' => $ocupado,
            'avisos' => array_values(array_filter([$this->avisoFueraDeSemanas($sesion->curso, $inicio)])),
        ];
    }

    /** Aviso no bloqueante: la nueva fecha cae fuera de las semanas del curso. */
    public function avisoFueraDeSemanas(Curso $curso, Carbon $inicio): ?string
    {
        if (! $curso->fecha_inicio) {
            return null;
        }

        $semanas = (int) ($curso->semanas_duracion ?: Curso::SEMANAS_PREDETERMINADAS);
        $primera = $curso->rangoSemana(1);
        $ultima = $curso->rangoSemana($semanas);

        return $primera && $ultima && ! $inicio->between($primera[0], $ultima[1])
            ? "La nueva fecha cae fuera de las {$semanas} semanas del curso."
            : null;
    }

    /**
     * Bloquea, siempre en este orden, las filas de los docentes del curso
     * (por id; solo se leen) y el salón. Así dos reprogramaciones que
     * compiten por el mismo docente o el mismo salón se atienden una tras
     * otra y la segunda vuelve a evaluar con los datos ya guardados. Debe
     * llamarse dentro de una transacción, antes de bloquear la clase.
     */
    public function bloquear(Curso $curso, ?int $salonId): ?Salon
    {
        User::query()
            ->whereIn('id', $this->docentesDelCurso($curso))
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        return $salonId !== null ? Salon::query()->with('sede')->whereKey($salonId)->lockForUpdate()->first() : null;
    }

    public static function seCruzan(SesionClase $clase, Carbon $inicio, Carbon $fin): bool
    {
        return $clase->fecha->lt($fin) && $clase->fecha->copy()->addMinutes((int) $clase->duracion_minutos)->gt($inicio);
    }

    /** Ids del titular y los co-docentes del curso (subconsulta). */
    protected function docentesDelCurso(Curso $curso): QueryBuilder
    {
        return DB::table('curso_profesor')->where('curso_id', $curso->id)->select('profesor_id')
            ->union(DB::table('cursos')->where('id', $curso->id)->select('profesor_id'));
    }

    /**
     * La clase ocupa el salón: por salon_id o, si es antigua, por el texto
     * «Aula: CÓDIGO» igual al código del salón.
     *
     * @param  Collection<int, SesionClase>  $clases
     */
    protected function estaOcupado(Salon $salon, Collection $clases, Carbon $inicio, Carbon $fin): bool
    {
        return $clases->contains(fn (SesionClase $clase) => self::seCruzan($clase, $inicio, $fin) && self::ocupa($clase, $salon));
    }

    public static function ocupa(SesionClase $clase, Salon $salon): bool
    {
        if ($clase->salon_id !== null) {
            return $clase->salon_id === $salon->id;
        }

        return mb_strtolower((string) AsistenciaProfesor::aula($clase)) === mb_strtolower($salon->codigo);
    }

    /**
     * Clases que empiezan entre un día antes del inicio y el fin, en esos
     * salones o con el aula solo en texto.
     *
     * @param  Collection<int, Salon>  $salones
     * @return Collection<int, SesionClase>
     */
    protected function clasesEnSalones(Collection $salones, Carbon $inicio, Carbon $fin, ?int $excluirSesionId): Collection
    {
        return SesionClase::query()
            ->when($excluirSesionId !== null, fn ($consulta) => $consulta->whereKeyNot($excluirSesionId))
            ->whereBetween('fecha', [$inicio->copy()->subDay(), $fin])
            ->where(fn ($consulta) => $consulta->whereIn('salon_id', $salones->pluck('id'))
                ->orWhere(fn ($antiguas) => $antiguas->whereNull('salon_id')->where('descripcion', 'like', '%Aula:%')))
            ->get(['id', 'fecha', 'duracion_minutos', 'salon_id', 'descripcion']);
    }

    /**
     * Clases de cualquier curso de los docentes de este curso que empiezan
     * entre un día antes del inicio y el fin.
     *
     * @return Collection<int, SesionClase>
     */
    protected function clasesDeLosDocentes(Curso $curso, Carbon $inicio, Carbon $fin, ?int $excluirSesionId): Collection
    {
        $docentes = $this->docentesDelCurso($curso);
        $cursos = DB::table('cursos')->select('id')
            ->whereIn('profesor_id', $docentes)
            ->orWhereIn('id', DB::table('curso_profesor')->select('curso_id')->whereIn('profesor_id', $docentes));

        return SesionClase::query()
            ->when($excluirSesionId !== null, fn ($consulta) => $consulta->whereKeyNot($excluirSesionId))
            ->whereIn('curso_id', $cursos)
            ->whereBetween('fecha', [$inicio->copy()->subDay(), $fin])
            ->get(['id', 'curso_id', 'fecha', 'duracion_minutos']);
    }
}
