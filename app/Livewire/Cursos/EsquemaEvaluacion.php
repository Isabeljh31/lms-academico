<?php

namespace App\Livewire\Cursos;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\EsquemaEvaluacion as EsquemaModel;
use App\Models\NotaRubro;
use App\Services\Evaluacion\CalculadoraNotas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Esquema de Evaluación de un Curso (solo Supervisor).
 * ERS RF-54/RF-55: rubros con nombre, semana y peso.
 * ERS RF-56 (regla 1): se guarda solo si los pesos suman exactamente 100 %;
 *   la validación está en el backend (CalculadoraNotas), no solo en la vista.
 * ERS RF-62 (regla 8): un curso archivado no admite cambios en su esquema.
 * ERS RF-96: toda modificación queda en la auditoría con valores antes/después.
 * Un rubro con notas registradas no se elimina (conservaría el historial).
 */
#[Layout('layouts.app')]
class EsquemaEvaluacion extends Component
{
    public Curso $curso;

    /** @var array<int, array{id: int|null, nombre: string, semana: int|string|null, peso: float|string}> */
    public array $rubros = [];

    public function mount(Curso $curso): void
    {
        Gate::authorize('administrar', $curso);

        $this->curso = $curso;
        $esquema = EsquemaModel::firstOrCreate(['curso_id' => $curso->id]);

        $this->rubros = $esquema->rubros->map(fn ($rubro) => [
            'id' => $rubro->id,
            'nombre' => $rubro->nombre,
            'semana' => $rubro->semana,
            'peso' => (float) $rubro->peso,
        ])->all();

        if ($this->rubros === []) {
            $this->agregarRubro();
        }
    }

    public function getEditableProperty(): bool
    {
        return ! $this->curso->estaFinalizado() && ! $this->curso->esquemaEvaluacion?->estaBloqueado();
    }

    public function getSumaPesosProperty(): float
    {
        return app(CalculadoraNotas::class)->sumarPesos(array_column($this->rubros, 'peso'));
    }

    public function agregarRubro(): void
    {
        $this->rubros[] = ['id' => null, 'nombre' => 'Evaluación '.(count($this->rubros) + 1), 'semana' => null, 'peso' => 0];
    }

    public function quitarRubro(int $indice): void
    {
        Gate::authorize('administrar', $this->curso);
        abort_unless($this->editable, 403, 'El esquema de un curso finalizado no se puede modificar.');

        $id = $this->rubros[$indice]['id'] ?? null;

        if ($id && NotaRubro::where('rubro_id', $id)->whereNotNull('nota')->exists()) {
            $this->addError("rubros.$indice.nombre", 'Este rubro ya tiene notas registradas y no se puede eliminar.');

            return;
        }

        unset($this->rubros[$indice]);
        $this->rubros = array_values($this->rubros);
    }

    public function guardar(CalculadoraNotas $calculadora): void
    {
        Gate::authorize('administrar', $this->curso);
        abort_unless($this->editable, 403, 'El esquema de un curso finalizado no se puede modificar.');

        $this->validate([
            'rubros' => ['required', 'array', 'min:1', 'max:20'],
            'rubros.*.nombre' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'rubros.*.semana' => ['nullable', 'integer', 'min:1', 'max:'.($this->curso->semanas_duracion ?: 52)],
            'rubros.*.peso' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ], [
            'rubros.*.nombre.required' => 'Escribe el nombre del rubro.',
            'rubros.*.nombre.distinct' => 'Hay dos rubros con el mismo nombre.',
            'rubros.*.peso.required' => 'Indica el peso del rubro.',
            'rubros.*.peso.min' => 'El peso debe ser mayor que 0.',
            'rubros.*.semana.max' => 'La semana no puede superar la duración del curso.',
        ]);

        // ERS RF-56: validación de backend obligatoria.
        if (! $calculadora->pesosSumanCien(array_column($this->rubros, 'peso'))) {
            $this->addError('rubros', "Los pesos suman {$this->sumaPesos} %. Deben sumar exactamente 100 % para guardar el esquema.");

            return;
        }

        $esquema = EsquemaModel::firstOrCreate(['curso_id' => $this->curso->id]);
        $anterior = $esquema->rubros->map->only(['nombre', 'semana', 'peso'])->values()->all();
        $idsActuales = array_filter(array_column($this->rubros, 'id'));

        $conNotas = $esquema->rubros()->whereNotIn('id', $idsActuales)
            ->whereHas('notas', fn ($q) => $q->whereNotNull('nota'))
            ->pluck('nombre');

        if ($conNotas->isNotEmpty()) {
            $this->addError('rubros', 'No se pueden eliminar rubros con notas registradas: '.$conNotas->implode(', ').'.');

            return;
        }

        DB::transaction(function () use ($esquema, $idsActuales) {
            $esquema->rubros()->whereNotIn('id', $idsActuales)->delete();

            foreach (array_values($this->rubros) as $orden => $rubro) {
                $datos = [
                    'nombre' => trim($rubro['nombre']),
                    'semana' => $rubro['semana'] ?: null,
                    'peso' => round((float) $rubro['peso'], 2),
                    'orden' => $orden + 1,
                ];

                $rubro['id']
                    ? $esquema->rubros()->whereKey($rubro['id'])->firstOrFail()->update($datos)
                    : $esquema->rubros()->create($datos);
            }
        });

        $esquema->refresh();

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'modificacion_esquema',
            descripcion: "Esquema de evaluación guardado para \"{$this->curso->nombre}\" ({$esquema->rubros->count()} rubros, 100 %).",
            curso: $this->curso,
            valorAnterior: $anterior,
            valorNuevo: $esquema->rubros->map->only(['nombre', 'semana', 'peso'])->values()->all(),
        );

        $this->rubros = $esquema->rubros->map(fn ($r) => ['id' => $r->id, 'nombre' => $r->nombre, 'semana' => $r->semana, 'peso' => (float) $r->peso])->all();
        session()->flash('estado', 'Esquema de evaluación guardado. Los pesos suman 100 %.');
    }

    public function render(CalculadoraNotas $calculadora)
    {
        return view('livewire.cursos.esquema-evaluacion', [
            'pesosOk' => $calculadora->pesosSumanCien(array_column($this->rubros, 'peso')),
            'notaMinima' => $calculadora->notaMinima(),
            'totalEstudiantes' => $this->curso->inscripcionesActivas()->count(),
            'notasRegistradas' => NotaRubro::whereIn('rubro_id', array_filter(array_column($this->rubros, 'id')))->whereNotNull('nota')->count(),
        ])->title("Esquema de evaluación — {$this->curso->nombre}");
    }
}
