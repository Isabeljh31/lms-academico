<?php

namespace App\Livewire\Cursos\Concerns;

use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\RubroEvaluacion;
use App\Models\Tarea;
use App\Services\Evaluacion\AsignadorEvaluacion;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

/**
 * Formularios de tarea y quiz abiertos desde el asistente «¿Qué quieres
 * crear?» de Actividades (?tipo=…): el tipo decide qué bloques se ven y, en
 * las actividades calificadas, «¿A qué evaluación aporta?» sugiere la
 * evaluación por semana y la vincula al guardar (AsignadorEvaluacion).
 *
 * Sin columna propia, el tipo no se guarda: una actividad vinculada a una
 * evaluación es calificada y una sin vincular no cuenta para la nota.
 * Fase 3: aquí se enchufará la columna `categoria` de tareas y quizzes
 * (guardar $tipoActividad al crear y leerla al editar).
 *
 * El componente define tiposDeActividad() y tiene $curso y $modulo_id.
 */
trait EligeTipoYEvaluacion
{
    /** Tipo elegido en el asistente; vacío al editar o al crear sin el asistente (formulario completo de siempre). */
    #[Locked]
    public string $tipoActividad = '';

    public ?int $evaluacionId = null;

    /** El profesor cambió la evaluación sugerida: ya no se vuelve a sugerir al cambiar la semana. */
    public bool $evaluacionElegida = false;

    /** @var Collection<int, RubroEvaluacion>|null */
    protected ?Collection $rubrosDelCurso = null;

    /**
     * @return array<string, array{titulo: string, calificada: bool, examen: bool}>
     */
    abstract protected function tiposDeActividad(): array;

    protected function iniciarTipoDeActividad(): void
    {
        $tipo = (string) request()->query('tipo', '');

        if (! array_key_exists($tipo, $this->tiposDeActividad())) {
            return;
        }

        $this->tipoActividad = $tipo;
        $this->sugerirEvaluacion();
    }

    /** @return array{titulo: string, calificada: bool, examen: bool}|null */
    public function tipoElegido(): ?array
    {
        return $this->tiposDeActividad()[$this->tipoActividad] ?? null;
    }

    public function cuentaParaNota(): bool
    {
        return $this->tipoElegido()['calificada'] ?? false;
    }

    public function updatedModuloId(): void
    {
        if (! $this->evaluacionElegida) {
            $this->sugerirEvaluacion();
        }
    }

    public function updatedEvaluacionId(): void
    {
        $this->evaluacionElegida = true;
    }

    protected function sugerirEvaluacion(): void
    {
        if ($this->cuentaParaNota()) {
            $asignador = app(AsignadorEvaluacion::class);
            $this->evaluacionId = $asignador->sugerida($asignador->disponibles($this->rubros()), $this->semanaDeLaActividad())?->id;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function reglasEvaluacion(): array
    {
        if (! $this->cuentaParaNota()) {
            return [];
        }

        $ids = app(AsignadorEvaluacion::class)->disponibles($this->rubros())->modelKeys();

        return ['evaluacionId' => $ids === [] ? ['nullable'] : ['required', 'integer', Rule::in($ids)]];
    }

    /** @return array<string, string> */
    protected function mensajesEvaluacion(): array
    {
        return [
            'evaluacionId.required' => 'Elige a qué evaluación aporta.',
            'evaluacionId.in' => 'Elige una evaluación de este curso que no se califique a mano.',
        ];
    }

    /**
     * Vincula la actividad recién creada con la evaluación elegida y devuelve
     * el texto para el aviso de confirmación (vacío si no corresponde).
     */
    protected function asignarEvaluacionElegida(Tarea|Quiz $actividad): string
    {
        $rubro = $this->cuentaParaNota() && $this->evaluacionId !== null
            ? $this->rubros()->firstWhere('id', $this->evaluacionId)
            : null;

        if ($rubro === null) {
            return $this->cuentaParaNota() ? ' Todavía no cuenta para ninguna evaluación: asígnala desde Actividades.' : '';
        }

        $pesos = app(AsignadorEvaluacion::class)->asignar($this->curso, $rubro, $actividad, $this->tipoElegido()['examen'], auth()->user());

        return count($pesos) === 1
            ? " Cuenta para {$rubro->nombre}."
            : " Cuenta para {$rubro->nombre}. Pesos dentro de {$rubro->nombre}: "
                .collect($pesos)->map(fn (float $peso, string $titulo) => $titulo.' '.rtrim(rtrim(number_format($peso, 2, ',', ''), '0'), ',').' %')->implode(', ')
                .'. Puedes cambiarlos con «Cambiar reparto» en Actividades.';
    }

    /**
     * Datos del bloque «¿A qué evaluación aporta?».
     *
     * @return array{mostrar: bool, opciones?: array<int, string>, excluidas?: list<string>, sugerencia?: string|null, aviso?: string|null, reparto?: string|null}
     */
    protected function datosDeEvaluacion(): array
    {
        if (! $this->cuentaParaNota()) {
            return ['mostrar' => false];
        }

        $asignador = app(AsignadorEvaluacion::class);
        $rubros = $this->rubros()->loadMissing(['tareas', 'quizzes']);
        $disponibles = $asignador->disponibles($rubros);
        $semana = $this->semanaDeLaActividad();
        $sugerida = $asignador->sugerida($disponibles, $semana);
        $elegida = $disponibles->firstWhere('id', $this->evaluacionId);
        $vinculadas = $elegida ? $elegida->tareas->count() + $elegida->quizzes->count() : 0;

        return [
            'mostrar' => true,
            'opciones' => $disponibles->mapWithKeys(fn (RubroEvaluacion $rubro) => [
                $rubro->id => $rubro->nombre.($rubro->semana ? ' · Semana '.$rubro->semana : '').' · '.(float) $rubro->peso.' % de la nota final',
            ])->all(),
            'excluidas' => $rubros->diff($disponibles)->pluck('nombre')->all(),
            'sugerencia' => $sugerida && $elegida?->is($sugerida) && ! $this->evaluacionElegida
                ? ($semana === null
                    ? 'La sugerimos porque es la primera evaluación del curso.'
                    : "La sugerimos porque es la primera evaluación desde la semana {$semana}.")
                : null,
            'aviso' => $elegida && $asignador->esPosterior($elegida, $semana)
                ? "Esta actividad es de la semana {$semana} y {$elegida->nombre} es de la semana {$elegida->semana}. Puedes asignarla igual: contará para {$elegida->nombre}."
                : null,
            'reparto' => $vinculadas > 0
                ? "{$elegida->nombre} ya tiene {$vinculadas} ".($vinculadas === 1 ? 'actividad' : 'actividades').': el peso se repartirá entre todas. Luego puedes ajustarlo con «Cambiar reparto» en Actividades.'
                : null,
        ];
    }

    /** @return Collection<int, RubroEvaluacion> */
    protected function rubros(): Collection
    {
        return $this->rubrosDelCurso ??= $this->curso->esquemaEvaluacion()->with('rubros')->first()?->rubros ?? new EloquentCollection;
    }

    protected function semanaDeLaActividad(): ?int
    {
        $semana = $this->modulo_id
            ? Modulo::where('curso_id', $this->curso->id)->whereKey($this->modulo_id)->value('semana')
            : null;

        return $semana === null ? null : (int) $semana;
    }
}
