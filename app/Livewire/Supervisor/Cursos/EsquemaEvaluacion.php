<?php

namespace App\Livewire\Supervisor\Cursos;

use App\Enums\ModoCalculo;
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

    /**
     * Valida en vivo el campo que el Supervisor acaba de cambiar, para que un
     * peso inválido (p. ej. 120 o 33.333) se marque en rojo antes de guardar.
     */
    public function updatedRubros(mixed $valor, ?string $clave = null): void
    {
        if ($clave !== null && preg_match('/^\d+\.(nombre|semana|peso)$/', $clave)) {
            $this->validateOnly("rubros.{$clave}", $this->reglas(), $this->mensajes());
        }
    }

    /**
     * ERS RF-55: cada rubro tiene nombre, semana y peso. El peso admite como
     * máximo 2 decimales, así lo que se valida es exactamente lo que se guarda.
     *
     * @return array<string, array<int, string>>
     */
    protected function reglas(): array
    {
        return [
            'rubros' => ['required', 'array', 'min:1', 'max:20'],
            'rubros.*.nombre' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'rubros.*.semana' => ['required', 'integer', 'min:1', 'max:'.$this->curso->totalSemanas()],
            'rubros.*.peso' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensajes(): array
    {
        return [
            'rubros.min' => 'Agrega al menos un rubro.',
            'rubros.max' => 'El esquema admite como máximo 20 rubros.',
            'rubros.*.nombre.required' => 'Escribe el nombre del rubro.',
            'rubros.*.nombre.max' => 'El nombre del rubro no puede tener más de 100 caracteres.',
            'rubros.*.nombre.distinct' => 'Hay dos rubros con el mismo nombre.',
            'rubros.*.semana.required' => 'Indica la semana en que se realiza.',
            'rubros.*.semana.integer' => 'La semana debe ser un número entero.',
            'rubros.*.semana.min' => 'La semana debe ser 1 o mayor.',
            'rubros.*.semana.max' => 'La semana no puede superar la duración del curso (:max semanas).',
            'rubros.*.peso.required' => 'Indica el peso del rubro.',
            'rubros.*.peso.numeric' => 'El peso debe ser un número.',
            'rubros.*.peso.decimal' => 'El peso admite como máximo 2 decimales (ej. 33.33).',
            'rubros.*.peso.min' => 'El peso debe ser mayor que 0.',
            'rubros.*.peso.max' => 'Inválido: un rubro no puede pesar más de 100 %.',
        ];
    }

    /**
     * Mensaje con la cuenta concreta de lo que falta o sobra para llegar a 100 %.
     */
    public function mensajeSuma(): string
    {
        $suma = $this->sumaPesos;
        $diferencia = round(abs(100 - $suma), 2);
        $formato = fn (float $valor) => rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');

        return match (true) {
            $suma < 100 => "Los pesos suman {$formato($suma)} %. Faltan {$formato($diferencia)} % para llegar a 100 %.",
            $suma > 100 => "Los pesos suman {$formato($suma)} %. Sobran {$formato($diferencia)} %: deben sumar exactamente 100 %.",
            default => 'Los pesos suman 100 %.',
        };
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

        if ($id && $this->curso->esquemaEvaluacion?->rubros()->whereKey($id)->whereIn('modo_calculo', ModoCalculo::automaticos())->exists()) {
            $this->addError("rubros.$indice.nombre", 'El profesor debe desactivar el cálculo automático de esta evaluación antes de eliminarla.');

            return;
        }

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

        $this->validate($this->reglas(), $this->mensajes());

        // ERS RF-56: validación de backend obligatoria.
        if (! $calculadora->pesosSumanCien(array_column($this->rubros, 'peso'))) {
            $this->addError('rubros', $this->mensajeSuma().' Ajusta los pesos para guardar el esquema.');

            return;
        }

        $esquema = EsquemaModel::firstOrCreate(['curso_id' => $this->curso->id]);
        $anterior = $esquema->rubros->map->only(['nombre', 'semana', 'peso'])->values()->all();
        $idsActuales = array_filter(array_column($this->rubros, 'id'));

        if ($esquema->rubros()->whereNotIn('id', $idsActuales)->whereIn('modo_calculo', ModoCalculo::automaticos())->exists()) {
            $this->addError('rubros', 'El profesor debe desactivar el cálculo automático de las evaluaciones antes de eliminarlas.');

            return;
        }

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
        return view('livewire.supervisor.cursos.esquema-evaluacion', [
            'pesosOk' => $calculadora->pesosSumanCien(array_column($this->rubros, 'peso')),
            'notaMinima' => $calculadora->notaMinima(),
            'totalEstudiantes' => $this->curso->inscripcionesActivas()->count(),
            'notasRegistradas' => NotaRubro::whereIn('rubro_id', array_filter(array_column($this->rubros, 'id')))->whereNotNull('nota')->count(),
        ])->title("Esquema de evaluación — {$this->curso->nombre}");
    }
}
