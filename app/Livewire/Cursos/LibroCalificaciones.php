<?php

namespace App\Livewire\Cursos;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\EsquemaEvaluacion;
use App\Models\Entrega;
use App\Models\NotaRubro;
use App\Models\ResultadoQuiz;
use App\Models\RubroEvaluacion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ERS RF-57 a RF-65: Libro de Calificaciones basado en Rubros.
 *
 * El Libro de Calificaciones PRINCIPAL usa el Esquema de Evaluación (rubros + pesos).
 * Las tareas y quizzes quedan como sección de "Práctica/Actividades" separada.
 *
 * RF-58: Nota Final = Σ(nota_rubro * peso/100) ponderada por rubros.
 * RF-63: Vista exportable (preparado para PDF/Excel en Fase 3).
 * RF-64: Alerta visual roja para estudiantes con nota final < mínima aprobatoria.
 */
#[Layout('layouts.app')]
class LibroCalificaciones extends Component
{
    public Curso $curso;

    /** Vista activa: 'rubros' (principal ERS) | 'actividades' (tareas+quizzes, vista legacy) */
    public string $vistaActiva = 'rubros';

    // Modal de nota por rubro
    public bool $mostrarModalNota = false;
    public ?int $rubroIdModal = null;
    public ?int $estudianteIdModal = null;
    public string $notaInput = '';
    public string $motivoInput = '';

    // Modal legacy: nota de tarea directa
    public bool $mostrarModalTarea = false;
    public ?int $tareaIdModal = null;
    public ?int $estudianteTareaIdModal = null;
    public string $notaTareaInput = '';
    public string $feedbackTareaInput = '';

    public function mount(Curso $curso): void
    {
        Gate::authorize('gestionar', $curso);
        $this->curso = $curso;
    }

    // ==========================================
    // MODAL RUBROS (ERS RF-57, RF-65)
    // ==========================================

    public function abrirModalNota(int $rubroId, int $estudianteId): void
    {
        $nota = NotaRubro::where('rubro_id', $rubroId)
            ->where('estudiante_id', $estudianteId)
            ->first();

        $this->rubroIdModal      = $rubroId;
        $this->estudianteIdModal = $estudianteId;
        $this->notaInput         = $nota ? (string) $nota->nota : '';
        $this->motivoInput       = '';
        $this->mostrarModalNota  = true;
    }

    public function guardarNotaRubro(): void
    {
        $this->validate([
            'notaInput'   => ['required', 'numeric', 'min:0', 'max:20'],
            'motivoInput' => ['nullable', 'string', 'max:500'],
        ]);

        $anterior = NotaRubro::where('rubro_id', $this->rubroIdModal)
            ->where('estudiante_id', $this->estudianteIdModal)
            ->value('nota');

        $notaRubro = NotaRubro::updateOrCreate(
            ['rubro_id' => $this->rubroIdModal, 'estudiante_id' => $this->estudianteIdModal],
            ['nota' => $this->notaInput, 'registrado_por' => auth()->id()]
        );

        // ERS RF-65 / RF-95: auditar cambio de nota
        $rubro      = RubroEvaluacion::find($this->rubroIdModal);
        $estudiante = User::find($this->estudianteIdModal);

        BitacoraAuditoria::registrarCambioNota(
            ejecutor: auth()->user(),
            curso: $this->curso,
            estudiante: $estudiante,
            entidad: 'nota_rubro',
            entidadId: $notaRubro->id,
            valorAnterior: $anterior,
            valorNuevo: $this->notaInput,
            motivo: $this->motivoInput ?: null
        );

        $this->mostrarModalNota = false;
        $this->reset(['rubroIdModal', 'estudianteIdModal', 'notaInput', 'motivoInput']);
        session()->flash('estado', 'Nota del rubro guardada y auditoría registrada.');
    }

    // ==========================================
    // MODAL ACTIVIDADES / TAREA (legacy)
    // ==========================================

    public function abrirEdicionNota(int $estudianteId, int $tareaId): void
    {
        $entrega = Entrega::where('tarea_id', $tareaId)
            ->where('estudiante_id', $estudianteId)
            ->first();

        $this->tareaIdModal            = $tareaId;
        $this->estudianteTareaIdModal  = $estudianteId;
        $this->notaTareaInput          = $entrega ? (string) $entrega->nota : '';
        $this->feedbackTareaInput      = $entrega ? (string) $entrega->feedback : '';
        $this->mostrarModalTarea       = true;
    }

    public function guardarNotaTarea(): void
    {
        $this->validate([
            'notaTareaInput'    => ['required', 'numeric', 'min:0', 'max:20'],
            'feedbackTareaInput' => ['nullable', 'string', 'max:2000'],
        ]);

        $entrega = Entrega::firstOrNew([
            'tarea_id'     => $this->tareaIdModal,
            'estudiante_id' => $this->estudianteTareaIdModal,
        ]);
        $entrega->nota     = $this->notaTareaInput;
        $entrega->feedback = $this->feedbackTareaInput;
        $entrega->estado   = 'calificado';
        if (!$entrega->exists) {
            $entrega->archivo_url  = '#';
            $entrega->comentario   = 'Nota asignada directamente por el docente.';
        }
        $entrega->save();

        $this->mostrarModalTarea = false;
        $this->reset(['tareaIdModal', 'estudianteTareaIdModal', 'notaTareaInput', 'feedbackTareaInput']);
        session()->flash('estado', 'Nota de actividad guardada.');
    }

    // ==========================================
    // RENDER
    // ==========================================

    public function render()
    {
        $esquema = $this->curso->esquemaEvaluacion()->with('rubros')->first();
        $rubros  = $esquema?->rubros ?? collect();

        // ---- Vista Principal: Rubros ----
        $estudiantes = $this->curso->inscripcionesActivas()
            ->with('estudiante')
            ->get()
            ->pluck('estudiante')
            ->filter();

        $estudianteIds = $estudiantes->pluck('id');
        $rubroIds      = $rubros->pluck('id');

        $notasMap = NotaRubro::whereIn('rubro_id', $rubroIds)
            ->whereIn('estudiante_id', $estudianteIds)
            ->get()
            ->keyBy(fn ($n) => "{$n->rubro_id}_{$n->estudiante_id}");

        $notaMinima = config('app.nota_minima_aprobatoria', 10.5);
        $sumaPesos  = $esquema?->sumaPesos() ?? 0;
        $pesosOk    = abs($sumaPesos - 100) < 0.01;

        $filasRubros = $estudiantes->map(function (User $est) use ($rubros, $notasMap, $notaMinima) {
            $sumaFinal  = 0;
            $pesoTotal  = 0;
            $notas      = [];

            foreach ($rubros as $rubro) {
                $key  = "{$rubro->id}_{$est->id}";
                $nota = $notasMap->get($key);
                $notas[$rubro->id] = $nota;
                if ($nota && $nota->nota !== null) {
                    $sumaFinal += $nota->nota * ($rubro->peso / 100);
                    $pesoTotal += $rubro->peso;
                }
            }

            $notaFinal = $pesoTotal > 0 ? round($sumaFinal, 1) : null;

            return [
                'estudiante' => $est,
                'notas'      => $notas,
                'nota_final' => $notaFinal,
                'aprobado'   => $notaFinal !== null && $notaFinal >= $notaMinima,
            ];
        });

        // KPIs del curso por rubros
        $notasFinales = $filasRubros->pluck('nota_final')->filter(fn ($v) => $v !== null);
        $promedioCurso  = $notasFinales->isNotEmpty() ? round($notasFinales->avg(), 1) : null;
        $aprobados      = $notasFinales->filter(fn ($n) => $n >= $notaMinima)->count();
        $tasaAprobacion = $notasFinales->isNotEmpty()
            ? round(($aprobados / $notasFinales->count()) * 100)
            : 0;
        $notaMax = $notasFinales->max();
        $notaMin = $notasFinales->min();

        // ---- Vista Actividades (legacy tareas + quizzes) ----
        $tareas  = $this->curso->tareas()->orderBy('id')->get();
        $quizzes = $this->curso->quizzes()->orderBy('id')->get();

        $tareaIds = $tareas->pluck('id');
        $quizIds  = $quizzes->pluck('id');

        $todasEntregas   = Entrega::whereIn('tarea_id', $tareaIds)
            ->whereIn('estudiante_id', $estudianteIds)
            ->get()
            ->groupBy(fn ($e) => "{$e->estudiante_id}_{$e->tarea_id}");

        $todosResultados = ResultadoQuiz::whereIn('quiz_id', $quizIds)
            ->whereIn('estudiante_id', $estudianteIds)
            ->get()
            ->groupBy(fn ($r) => "{$r->estudiante_id}_{$r->quiz_id}");

        $filasActividades = $estudiantes->map(function (User $est) use ($tareas, $quizzes, $todasEntregas, $todosResultados, $notaMinima) {
            $notasTareas = [];
            $sumaP = 0; $pesoP = 0;
            foreach ($tareas as $t) {
                $e = $todasEntregas->get("{$est->id}_{$t->id}")?->first();
                $notasTareas[$t->id] = ['nota' => $e?->nota, 'estado' => $e?->estado ?? 'sin_entrega', 'entrega' => $e];
                if ($e?->nota !== null) { $sumaP += $e->nota * $t->peso_nota; $pesoP += $t->peso_nota; }
            }
            $notasQuizzes = [];
            $sumaQ = 0; $cntQ = 0;
            foreach ($quizzes as $q) {
                $r = $todosResultados->get("{$est->id}_{$q->id}")?->first();
                $notasQuizzes[$q->id] = ['nota' => $r?->puntaje, 'resultado' => $r];
                if ($r?->puntaje !== null) { $sumaQ += $r->puntaje; $cntQ++; }
            }
            $promT = $pesoP > 0 ? round($sumaP / $pesoP, 1) : null;
            $promQ = $cntQ > 0 ? round($sumaQ / $cntQ, 1) : null;
            $vals = collect([$promT, $promQ])->filter(fn ($v) => $v !== null);
            return [
                'estudiante'      => $est,
                'notasTareas'     => $notasTareas,
                'notasQuizzes'    => $notasQuizzes,
                'promedioTareas'  => $promT,
                'promedioQuizzes' => $promQ,
                'promedioFinal'   => $vals->isNotEmpty() ? round($vals->avg(), 1) : null,
            ];
        });

        return view('livewire.cursos.libro-calificaciones', [
            // Rubros
            'esquema'       => $esquema,
            'rubros'        => $rubros,
            'filasRubros'   => $filasRubros,
            'sumaPesos'     => $sumaPesos,
            'pesosOk'       => $pesosOk,
            'notaMinima'    => $notaMinima,
            // KPIs
            'promedioCurso'  => $promedioCurso,
            'aprobados'      => $aprobados,
            'tasaAprobacion' => $tasaAprobacion,
            'notaMax'        => $notaMax,
            'notaMin'        => $notaMin,
            // Actividades (legacy)
            'tareas'            => $tareas,
            'quizzes'           => $quizzes,
            'filasActividades'  => $filasActividades,
        ])->title("Libro de Calificaciones — {$this->curso->nombre}");
    }
}
