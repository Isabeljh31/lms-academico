<?php

namespace App\Livewire\Compartido\Cursos;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\NotaRubro;
use App\Models\ResultadoQuiz;
use App\Models\User;
use App\Notifications\NotaRubroRegistrada;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Evaluacion\ExportadorLibroCalificaciones;
use App\Services\Evaluacion\NotasDeEvaluacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ERS RF-57 a RF-65: Libro de Calificaciones.
 *
 * - Vista principal: notas por Rubro del Esquema de Evaluación y Nota Final
 *   ponderada (RF-58), en rojo si queda bajo la nota mínima (RF-64).
 * - Consultan el Profesor del curso y el Supervisor; solo el Profesor del
 *   curso registra y edita notas (RF-57, RF-65, matriz de la sección 5), mientras
 *   el curso no esté finalizado y el esquema sume 100 %. Toda edición de una
 *   nota ya registrada queda en la auditoría (RF-95).
 * - Las actividades seleccionadas por el profesor se promedian en los rubros
 *   automáticos. El resto conserva su carácter de práctica.
 * - Exportación a Excel del listado (RF-63).
 */
#[Layout('layouts.app')]
class LibroCalificaciones extends Component
{
    public Curso $curso;

    public string $vistaActiva = 'rubros';

    public bool $mostrarModalNota = false;

    #[Locked]
    public ?int $rubroIdModal = null;

    #[Locked]
    public ?int $estudianteIdModal = null;

    public string $notaInput = '';

    public string $motivoInput = '';

    public bool $mostrarModalTarea = false;

    #[Locked]
    public ?int $tareaIdModal = null;

    #[Locked]
    public ?int $estudianteTareaIdModal = null;

    public string $notaTareaInput = '';

    public string $feedbackTareaInput = '';

    public function mount(Curso $curso): void
    {
        Gate::authorize('supervisar', $curso);
        $this->curso = $curso;
    }

    public function getPuedeCalificarProperty(): bool
    {
        return Gate::allows('calificar', $this->curso) && ! $this->curso->estaFinalizado();
    }

    public function getEsquemaValidoProperty(): bool
    {
        $rubros = $this->curso->esquemaEvaluacion?->rubros ?? collect();

        return $rubros->isNotEmpty() && app(CalculadoraNotas::class)->pesosSumanCien($rubros->pluck('peso'));
    }

    // ==========================================
    // NOTAS POR RUBRO (RF-57, RF-65, RF-95)
    // ==========================================

    public function abrirModalNota(int $rubroId, int $estudianteId): void
    {
        $this->autorizarCalificacion();
        abort_if($this->rubroDelCurso($rubroId)->esAutomatico(), 403, 'Esta nota se calcula desde las actividades seleccionadas.');
        $this->estudianteMatriculado($estudianteId);

        $nota = NotaRubro::where('rubro_id', $rubroId)->where('estudiante_id', $estudianteId)->value('nota');

        $this->resetErrorBag();
        $this->rubroIdModal = $rubroId;
        $this->estudianteIdModal = $estudianteId;
        $this->notaInput = $nota !== null ? (string) (float) $nota : '';
        $this->motivoInput = '';
        $this->mostrarModalNota = true;
    }

    public function guardarNotaRubro(): void
    {
        $this->autorizarCalificacion();
        $rubro = $this->rubroDelCurso($this->rubroIdModal);
        abort_if($rubro->esAutomatico(), 403, 'Esta nota se calcula desde las actividades seleccionadas.');
        $estudiante = $this->estudianteMatriculado($this->estudianteIdModal);

        $anterior = NotaRubro::where('rubro_id', $rubro->id)->where('estudiante_id', $estudiante->id)->value('nota');

        $this->validate([
            'notaInput' => ['required', 'numeric', 'min:0', 'max:20', 'decimal:0,1'],
            'motivoInput' => [$anterior !== null ? 'required' : 'nullable', 'string', 'max:500'],
        ], [
            'notaInput.required' => 'Ingresa la nota (0 a 20).',
            'notaInput.max' => 'La nota máxima es 20.',
            'notaInput.decimal' => 'Usa como máximo un decimal.',
            'motivoInput.required' => 'Indica el motivo del ajuste: queda registrado en la auditoría.',
        ]);

        $nueva = round((float) $this->notaInput, 1);

        $notaRubro = NotaRubro::updateOrCreate(
            ['rubro_id' => $rubro->id, 'estudiante_id' => $estudiante->id],
            ['nota' => $nueva, 'registrado_por' => auth()->id()]
        );

        if ($anterior !== null && (float) $anterior !== $nueva) {
            BitacoraAuditoria::registrarCambioNota(
                ejecutor: auth()->user(),
                curso: $this->curso,
                estudiante: $estudiante,
                entidad: 'nota_rubro',
                entidadId: $notaRubro->id,
                valorAnterior: $anterior,
                valorNuevo: $nueva,
                motivo: $this->motivoInput
            );
        }

        $this->mostrarModalNota = false;
        $this->reset(['rubroIdModal', 'estudianteIdModal', 'notaInput', 'motivoInput']);
        if ($anterior === null || (float) $anterior !== $nueva) {
            $estudiante->notify(new NotaRubroRegistrada($this->curso, $rubro, esAjuste: $anterior !== null));
        }

        session()->flash('estado', $anterior !== null ? 'Nota actualizada y registrada en la auditoría.' : 'Nota registrada.');
    }

    // ==========================================
    // NOTAS DE ACTIVIDADES DE PRÁCTICA (tareas)
    // ==========================================

    public function abrirEdicionNota(int $estudianteId, int $tareaId): void
    {
        abort_unless($this->puedeCalificar, 403);
        $this->estudianteMatriculado($estudianteId);
        $this->curso->tareas()->findOrFail($tareaId);

        $entrega = Entrega::where('tarea_id', $tareaId)->where('estudiante_id', $estudianteId)->first();

        $this->resetErrorBag();
        $this->tareaIdModal = $tareaId;
        $this->estudianteTareaIdModal = $estudianteId;
        $this->notaTareaInput = $entrega?->nota !== null ? (string) (float) $entrega->nota : '';
        $this->feedbackTareaInput = $entrega?->feedback ?? '';
        $this->mostrarModalTarea = true;
    }

    public function guardarNotaTarea(): void
    {
        abort_unless($this->puedeCalificar, 403);
        $estudiante = $this->estudianteMatriculado($this->estudianteTareaIdModal);
        $tarea = $this->curso->tareas()->findOrFail($this->tareaIdModal);

        $this->validate([
            'notaTareaInput' => ['required', 'numeric', 'min:0', 'max:20'],
            'feedbackTareaInput' => ['nullable', 'string', 'max:2000'],
        ]);

        $entrega = Entrega::firstOrNew(['tarea_id' => $tarea->id, 'estudiante_id' => $estudiante->id]);
        $anterior = $entrega->nota;
        $nueva = round((float) $this->notaTareaInput, 2);

        $entrega->fill([
            'nota' => $nueva,
            'feedback' => $this->feedbackTareaInput ?: null,
            'estado' => 'calificado',
        ]);

        if (! $entrega->exists) {
            $entrega->comentario = 'Nota registrada por el docente sin entrega en la plataforma.';
        }

        $entrega->save();

        if ($anterior !== null && (float) $anterior !== $nueva) {
            BitacoraAuditoria::registrarCambioNota(
                ejecutor: auth()->user(),
                curso: $this->curso,
                estudiante: $estudiante,
                entidad: 'entrega_tarea',
                entidadId: $entrega->id,
                valorAnterior: $anterior,
                valorNuevo: $nueva,
                motivo: 'Edición desde el libro de calificaciones.'
            );
        }

        $this->mostrarModalTarea = false;
        $this->reset(['tareaIdModal', 'estudianteTareaIdModal', 'notaTareaInput', 'feedbackTareaInput']);
        session()->flash('estado', 'Nota de la tarea guardada.');
    }

    // ==========================================
    // EXPORTACIÓN (RF-63)
    // ==========================================

    /**
     * ERS RF-63: libro de calificaciones en Excel (resumen, notas por rubro y práctica).
     */
    public function exportarExcel(CalculadoraNotas $calculadora, ExportadorLibroCalificaciones $exportador): StreamedResponse
    {
        Gate::authorize('supervisar', $this->curso);

        $filas = $this->filasRubros($calculadora);
        $estudianteIds = $filas->pluck('estudiante.id');
        $notasPractica = [];

        Entrega::whereIn('tarea_id', $this->curso->tareas()->select('id'))->whereIn('estudiante_id', $estudianteIds)
            ->whereNotNull('nota')->get()
            ->each(function (Entrega $entrega) use (&$notasPractica) {
                $notasPractica["{$entrega->estudiante_id}_t{$entrega->tarea_id}"] = (float) $entrega->nota;
            });

        ResultadoQuiz::whereIn('quiz_id', $this->curso->quizzes()->select('id'))->whereIn('estudiante_id', $estudianteIds)->get()
            ->groupBy(fn (ResultadoQuiz $resultado) => "{$resultado->estudiante_id}_q{$resultado->quiz_id}")
            ->each(function (Collection $intentos, string $clave) use (&$notasPractica) {
                $notasPractica[$clave] = (float) $intentos->max('puntaje');
            });

        $nombre = 'calificaciones-'.str($this->curso->codigo ?: $this->curso->nombre)->slug().'-'.now()->format('Ymd').'.xlsx';

        return $exportador->libro($this->curso, $filas, $notasPractica)->descargar($nombre);
    }

    // ==========================================
    // AUXILIARES
    // ==========================================

    protected function autorizarCalificacion(): void
    {
        abort_unless($this->puedeCalificar, 403, 'Solo el profesor del curso registra notas y el curso no debe estar finalizado.');
        abort_unless($this->esquemaValido, 403, 'El esquema de evaluación debe sumar 100 % antes de registrar notas.');
    }

    protected function rubroDelCurso(?int $rubroId)
    {
        return $this->curso->esquemaEvaluacion?->rubros()->findOrFail($rubroId) ?? abort(404);
    }

    protected function estudianteMatriculado(?int $estudianteId): User
    {
        return User::whereKey($estudianteId)
            ->whereHas('inscripciones', fn ($q) => $q->where('curso_id', $this->curso->id)->where('estado', 'activo'))
            ->firstOrFail();
    }

    protected function estudiantes(): Collection
    {
        return $this->curso->inscripciones()->where('estado', 'activo')
            ->with('estudiante')->get()->pluck('estudiante')->filter()->sortBy('name')->values();
    }

    /**
     * @return Collection<int, array{estudiante: User, notas: array<int, float|null>, nota_final: float|null, aprobado: bool}>
     */
    protected function filasRubros(CalculadoraNotas $calculadora): Collection
    {
        $rubros = $this->curso->esquemaEvaluacion?->rubros ?? collect();
        $estudiantes = $this->estudiantes();

        $notas = app(NotasDeEvaluacion::class)->resolver($rubros, $estudiantes->pluck('id'));

        return $estudiantes->map(function (User $estudiante) use ($rubros, $notas, $calculadora) {
            $notasEstudiante = $notas[$estudiante->id] ?? [];
            $condicion = $calculadora->condicion($rubros, $notasEstudiante);

            return [
                'estudiante' => $estudiante,
                'notas' => $notasEstudiante,
                'nota_final' => $calculadora->notaFinal($rubros, $notasEstudiante),
                'rendimiento' => $calculadora->rendimiento($rubros, $notasEstudiante),
                'condicion' => $condicion,
                'completa' => in_array($condicion, ['aprobado', 'desaprobado'], true),
                'rubros_con_nota' => count(array_filter($notasEstudiante, fn ($nota) => $nota !== null)),
                'aprobado' => $condicion === 'aprobado',
            ];
        });
    }

    public function render(CalculadoraNotas $calculadora)
    {
        $esquema = $this->curso->esquemaEvaluacion()->with('rubros')->first();
        $rubros = $esquema?->rubros ?? collect();
        $filasRubros = $this->filasRubros($calculadora);
        $estudiantes = $filasRubros->pluck('estudiante');

        // Durante el periodo se muestra el rendimiento a la fecha; la aprobación se cuenta sobre esquemas completos.
        $rendimientos = $filasRubros->pluck('rendimiento')->filter(fn ($v) => $v !== null);
        $completas = $filasRubros->where('completa', true);
        $aprobados = $filasRubros->where('aprobado', true)->count();

        // Actividades de práctica: se muestran aparte (RF-48, RF-60).
        $tareas = $this->curso->tareas()->orderBy('fecha_entrega')->get();
        $quizzes = $this->curso->quizzes()->orderBy('id')->get();
        $entregas = Entrega::whereIn('tarea_id', $tareas->pluck('id'))->whereIn('estudiante_id', $estudiantes->pluck('id'))->get()
            ->keyBy(fn ($e) => "{$e->estudiante_id}_{$e->tarea_id}");
        $resultados = ResultadoQuiz::whereIn('quiz_id', $quizzes->pluck('id'))->whereIn('estudiante_id', $estudiantes->pluck('id'))->get()
            ->groupBy(fn ($r) => "{$r->estudiante_id}_{$r->quiz_id}")
            ->map(fn ($intentos) => $intentos->max('puntaje'));

        return view('livewire.compartido.cursos.libro-calificaciones', [
            'esquema' => $esquema,
            'rubros' => $rubros,
            'filasRubros' => $filasRubros,
            'sumaPesos' => $calculadora->sumarPesos($rubros->pluck('peso')),
            'notaMinima' => $calculadora->notaMinima(),
            'promedioCurso' => $rendimientos->isNotEmpty() ? round($rendimientos->avg(), 1) : null,
            'aprobados' => $aprobados,
            'enCurso' => $filasRubros->where('condicion', 'en_curso')->count(),
            'tasaAprobacion' => $completas->isNotEmpty() ? (int) round($aprobados / $completas->count() * 100) : null,
            'notaMax' => $rendimientos->max(),
            'notaMin' => $rendimientos->min(),
            'tareas' => $tareas,
            'quizzes' => $quizzes,
            'entregas' => $entregas,
            'resultados' => $resultados,
            'estudiantes' => $estudiantes,
            'estudianteModal' => $this->estudianteIdModal ? User::find($this->estudianteIdModal) : null,
            'rubroModal' => $this->rubroIdModal ? $rubros->firstWhere('id', $this->rubroIdModal) : null,
        ])->title("Libro de calificaciones — {$this->curso->nombre}");
    }
}
