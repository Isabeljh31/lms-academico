<?php

namespace App\Livewire\Cursos;

use App\Enums\Rol;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Grupo;
use App\Models\Leccion;
use App\Models\LeccionCompletada;
use App\Models\Modulo;
use App\Models\User;
use App\Notifications\NuevoComunicado;
use App\Rules\TieneRol;
use App\Services\Multimedia\SubidorArchivos;
use App\Services\Reportes\ProgresoAcademicoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Detalle de un curso.
 * - Estudiante: pestañas de contenido, anuncios, tareas, quizzes, compañeros y grupo.
 * - Profesor / Supervisor: contenido por semanas y prácticas de campo (RF-34, RF-37) y anuncios (RF-81).
 * - Supervisor: clonación (RF-20, RF-21).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    public Curso $curso;

    public ?int $moduloMaterialId = null;

    public ?UploadedFile $material = null;

    // Edición de módulo (RF-34, RF-37)
    public bool $mostrarModalModulo = false;

    #[Locked]
    public ?int $moduloEditarId = null;

    public string $moduloTitulo = '';

    public string $moduloEstado = 'publicado';

    public string $moduloPublicarEn = '';

    /** ERS RF-67: actividades | lecciones | aprobacion (Modulo::CONDICIONES). */
    public string $moduloCondicion = 'actividades';

    /** Semana en la que está el contenido (permite moverlo a otra semana libre). */
    public string $moduloSemana = '';

    // Clonación (RF-20, flujo 9.4)
    public bool $mostrarModalClonar = false;

    public string $clonNombre = '';

    public string $clonCodigo = '';

    public ?int $clonProfesorId = null;

    // Anuncios del Curso (RF-81)
    public string $nuevoAnuncioTitulo = '';

    public string $nuevoAnuncioTexto = '';

    public string $nuevoAnuncioPrioridad = 'normal';

    public function mount(Curso $curso): void
    {
        Gate::authorize('ver', $curso);

        $this->curso = $curso;
    }

    public function getPuedeGestionarProperty(): bool
    {
        return Gate::allows('gestionar', $this->curso);
    }

    /** Consulta de solo lectura de tareas, quizzes, notas, asistencia y grupos. */
    public function getPuedeSupervisarProperty(): bool
    {
        return Gate::allows('supervisar', $this->curso);
    }

    public function getPuedeAdministrarProperty(): bool
    {
        return Gate::allows('administrar', $this->curso);
    }

    public function getEstaInscritoProperty(): bool
    {
        return auth()->user()->hasRole('estudiante') && auth()->user()->estaMatriculadoEn($this->curso);
    }

    public function getAnunciosProperty(): Collection
    {
        return $this->curso->comunicados()->with('autor')->where('activo', true)->latest()->get();
    }

    public function getAnunciosDelCursoProperty(): Collection
    {
        return $this->anuncios;
    }

    // ==========================================
    // ANUNCIOS DEL CURSO (RF-81, RF-84)
    // ==========================================

    public function publicarAnuncio(): void
    {
        Gate::authorize('gestionar', $this->curso);

        $this->validate([
            'nuevoAnuncioTitulo' => ['required', 'string', 'max:255'],
            'nuevoAnuncioTexto' => ['required', 'string', 'max:5000'],
            'nuevoAnuncioPrioridad' => ['required', 'in:informativo,normal,importante,urgente'],
        ], [
            'nuevoAnuncioTitulo.required' => 'Escribe un título para el anuncio.',
            'nuevoAnuncioTexto.required' => 'Escribe el mensaje del anuncio.',
        ]);

        $anuncio = $this->curso->comunicados()->create([
            'user_id' => auth()->id(),
            'titulo' => $this->nuevoAnuncioTitulo,
            'contenido' => $this->nuevoAnuncioTexto,
            'prioridad' => $this->nuevoAnuncioPrioridad,
            'destinatario' => 'estudiantes',
            'activo' => true,
            'notificado' => true,
        ]);

        Notification::send($this->estudiantesActivos(), new NuevoComunicado($anuncio));

        $this->reset(['nuevoAnuncioTitulo', 'nuevoAnuncioTexto', 'nuevoAnuncioPrioridad']);
        $this->curso->unsetRelation('comunicados');
        session()->flash('estado', 'Anuncio publicado y notificado a los estudiantes matriculados.');
    }

    public function eliminarAnuncio(int $anuncioId): void
    {
        Gate::authorize('gestionar', $this->curso);

        $this->curso->comunicados()->whereKey($anuncioId)->firstOrFail()->delete();
        $this->curso->unsetRelation('comunicados');
        session()->flash('estado', 'Anuncio eliminado.');
    }

    // ==========================================
    // DATOS DEL ESTUDIANTE (solo lectura)
    // ==========================================

    /**
     * Progreso de tareas por módulo para el estudiante (tareas visibles).
     *
     * @return array<int, array{total: int, completadas: int}>
     */
    /**
     * ERS RF-67: avance de cada módulo según la condición de completitud que
     * definió el Profesor.
     *
     * @return array<int, array{completado: bool, hechos: int, total: int, condicion: string}>
     */
    public function getProgresoModulosProperty(): array
    {
        if (! $this->estaInscrito) {
            return [];
        }

        return app(ProgresoAcademicoService::class)->estadoModulos(auth()->user(), $this->curso);
    }

    public function getResumenTareasEstudianteProperty(): Collection
    {
        if (! auth()->user()->hasRole('estudiante')) {
            return collect();
        }

        return $this->tareasVisiblesDelEstudiante()
            ->sortBy('fecha_entrega')
            ->values()
            ->map(fn ($tarea) => [
                'tarea' => $tarea,
                'entrega' => $tarea->entregas->first(),
                'estado' => $tarea->estadoPara($tarea->entregas->first()),
            ]);
    }

    public function getResumenQuizzesEstudianteProperty(): Collection
    {
        if (! auth()->user()->hasRole('estudiante')) {
            return collect();
        }

        return $this->curso->quizzes()
            ->visiblesParaEstudiante()
            ->with(['resultados' => fn ($q) => $q->where('estudiante_id', auth()->id())])
            ->get()
            ->map(fn ($quiz) => [
                'quiz' => $quiz,
                'resultado' => $quiz->resultadoDe(auth()->user()),
            ]);
    }

    /**
     * Compañeros de curso (solo para un estudiante matriculado, por
     * privacidad de la lista).
     */
    public function getCompanerosDelCursoProperty(): Collection
    {
        if (! auth()->user()->hasRole('estudiante') || ! $this->estaInscrito) {
            return collect();
        }

        $nombreGrupoPorEstudiante = $this->curso->grupos()
            ->with('estudiantes')
            ->get()
            ->flatMap(fn ($grupo) => $grupo->estudiantes->mapWithKeys(fn ($e) => [$e->id => $grupo->nombre]));

        return $this->curso->inscripcionesActivas()
            ->with('estudiante')
            ->get()
            ->pluck('estudiante')
            ->filter()
            ->sortBy('name')
            ->map(fn ($estudiante) => tap($estudiante, fn ($e) => $e->grupo_nombre = $nombreGrupoPorEstudiante[$e->id] ?? null))
            ->values();
    }

    public function getMiGrupoDelCursoProperty(): ?Grupo
    {
        if (! auth()->user()->hasRole('estudiante') || ! $this->estaInscrito) {
            return null;
        }

        return auth()->user()->grupoEnCurso($this->curso)?->load('estudiantes');
    }

    /** @return array<int, int> ids de lecciones completadas por el estudiante */
    public function getLeccionesCompletadasProperty(): array
    {
        if (! auth()->user()->hasRole('estudiante')) {
            return [];
        }

        return LeccionCompletada::where('estudiante_id', auth()->id())
            ->whereHas('leccion.modulo', fn ($q) => $q->where('curso_id', $this->curso->id))
            ->pluck('leccion_id')
            ->all();
    }

    /**
     * ERS RF-66/RF-67: el estudiante marca (o desmarca) una lección visible
     * de este curso como completada.
     */
    public function alternarLeccionCompletada(int $leccionId): void
    {
        abort_unless($this->estaInscrito, 403);

        $leccion = Leccion::whereKey($leccionId)
            ->publicadas()
            ->whereHas('modulo', fn ($q) => $q->where('curso_id', $this->curso->id)->visiblesParaEstudiante())
            ->firstOrFail();

        // RF-67: si el Profesor definió que se completa al abrir su recurso, no se marca a mano.
        if ($leccion->seCompletaAlAbrir()) {
            return;
        }

        $registro = LeccionCompletada::where('leccion_id', $leccion->id)->where('estudiante_id', auth()->id())->first();

        $registro
            ? $registro->delete()
            : LeccionCompletada::create(['leccion_id' => $leccion->id, 'estudiante_id' => auth()->id(), 'completada_en' => now()]);
    }

    /**
     * ERS 2.4 / RF-26: no existe autoinscripción. Se conserva el método para
     * responder 403 explícito si alguien intenta invocarlo.
     */
    public function inscribirme(): void
    {
        Gate::authorize('inscribirse', $this->curso);
    }

    // ==========================================
    // CONTENIDO POR SEMANAS (RF-34, RF-37)
    // ==========================================

    /**
     * Prepara la semana N (o las prácticas de campo, con $semana = null) para
     * cargarle contenido: crea su contenedor si aún no existe y abre su edición.
     */
    public function prepararSeccion(?int $semana = null): void
    {
        Gate::authorize('gestionar', $this->curso);
        $this->asegurarCursoEditable();

        abort_if($semana !== null && ($semana < 1 || $semana > $this->curso->totalSemanas()), 404);

        $modulo = $semana === null
            ? $this->curso->modulos()->firstOrCreate(['tipo_seccion' => Modulo::TIPO_PRACTICAS], ['titulo' => 'Prácticas de campo', 'materiales' => []])
            : $this->curso->modulos()->firstOrCreate(['semana' => $semana], ['titulo' => 'Semana '.$semana, 'materiales' => []]);

        $this->curso->unsetRelation('modulos');
        $this->abrirEditarModulo($modulo->id);
    }

    public function abrirEditarModulo(int $moduloId): void
    {
        Gate::authorize('gestionar', $this->curso);

        $modulo = $this->curso->modulos()->findOrFail($moduloId);
        $this->resetErrorBag();
        $this->moduloEditarId = $modulo->id;
        $this->moduloTitulo = $modulo->tema() ?? '';
        $this->moduloEstado = $modulo->estado_contenido ?? 'publicado';
        $this->moduloPublicarEn = $modulo->publicar_en?->format('Y-m-d\TH:i') ?? '';
        $this->moduloCondicion = $modulo->condicion_completitud ?? 'actividades';
        $this->moduloSemana = (string) ($modulo->semana ?? '');
        $this->mostrarModalModulo = true;
    }

    public function guardarModulo(): void
    {
        Gate::authorize('gestionar', $this->curso);
        $this->asegurarCursoEditable();

        $modulo = $this->curso->modulos()->findOrFail($this->moduloEditarId);

        $this->validate([
            'moduloTitulo' => ['nullable', 'string', 'max:255'],
            'moduloEstado' => ['required', Rule::in(['borrador', 'publicado', 'programado'])],
            'moduloPublicarEn' => ['nullable', 'required_if:moduloEstado,programado', 'date'],
            'moduloCondicion' => ['required', Rule::in(array_keys(Modulo::CONDICIONES))],
            'moduloSemana' => $modulo->esPracticas() ? ['nullable'] : [
                'required', 'integer', 'min:1', 'max:'.$this->curso->totalSemanas(),
                Rule::unique('modulos', 'semana')->where('curso_id', $this->curso->id)->ignore($modulo->id),
            ],
        ], [
            'moduloPublicarEn.required_if' => 'Indica la fecha y hora de publicación automática.',
            'moduloSemana.unique' => 'Esa semana ya tiene contenido. Elige una semana libre.',
        ]);

        $semana = $modulo->esPracticas() ? null : (int) $this->moduloSemana;

        $modulo->fill([
            'semana' => $semana,
            'orden' => $semana ?? 0,
            'estado_contenido' => $this->moduloEstado,
            'publicar_en' => $this->moduloEstado === 'programado' ? $this->moduloPublicarEn : null,
            'condicion_completitud' => $this->moduloCondicion,
        ]);
        // Sin tema propio, el título es la etiqueta de la sección ("Semana 3").
        $modulo->titulo = trim($this->moduloTitulo) !== '' ? trim($this->moduloTitulo) : $modulo->etiqueta();
        $modulo->save();

        $this->mostrarModalModulo = false;
        $this->reset(['moduloEditarId', 'moduloTitulo', 'moduloPublicarEn', 'moduloSemana']);
        $this->curso->unsetRelation('modulos');
        session()->flash('estado', "{$modulo->etiqueta()} actualizada.");
    }

    /** Vacía una semana (o las prácticas de campo): elimina su contenido si no tiene historial académico. */
    public function eliminarModulo(int $moduloId): void
    {
        Gate::authorize('gestionar', $this->curso);
        $this->asegurarCursoEditable();

        $modulo = $this->curso->modulos()->findOrFail($moduloId);

        if ($modulo->tieneHistorialAcademico()) {
            session()->flash('error', "{$modulo->etiqueta()} tiene entregas o resultados de estudiantes y no se puede vaciar. Pásala a Borrador para ocultarla.");

            return;
        }

        $modulo->delete();
        $this->curso->unsetRelation('modulos');
        session()->flash('estado', "Se vació {$modulo->etiqueta()}.");
    }

    public function subirMaterial(int $moduloId, SubidorArchivos $subidor): void
    {
        Gate::authorize('gestionar', $this->curso);
        $this->asegurarCursoEditable();

        $this->validate(['material' => SubidorArchivos::reglas()], SubidorArchivos::mensajes('material'));

        $modulo = Modulo::where('curso_id', $this->curso->id)->findOrFail($moduloId);

        $url = $subidor->subir($this->material, 'cursos/materiales', 'material');

        $modulo->update([
            'materiales' => [
                ...($modulo->materiales ?? []),
                ['nombre' => $this->material->getClientOriginalName(), 'url' => $url],
            ],
        ]);

        $this->material = null;
        $this->curso->unsetRelation('modulos');
    }

    // ==========================================
    // CLONAR CURSO (ERS RF-20, RF-21, RF-61)
    // ==========================================

    public function abrirClonar(): void
    {
        Gate::authorize('administrar', $this->curso);

        $this->resetErrorBag();
        $this->clonNombre = $this->curso->nombre.' (nuevo ciclo)';
        $this->clonCodigo = '';
        // Si el curso quedó a cargo del Supervisor (sin profesor), el clon tampoco preselecciona a nadie.
        $this->clonProfesorId = $this->curso->profesor?->hasRole(Rol::Profesor->value) ? $this->curso->profesor_id : null;
        $this->mostrarModalClonar = true;
    }

    public function clonarCurso(): void
    {
        Gate::authorize('administrar', $this->curso);

        $this->clonCodigo = (string) Curso::normalizarCodigo($this->clonCodigo);

        $this->validate([
            'clonNombre' => ['required', 'string', 'max:255'],
            'clonCodigo' => ['nullable', 'string', 'max:30', 'regex:'.Curso::FORMATO_CODIGO, Rule::unique('cursos', 'codigo')],
            'clonProfesorId' => ['nullable', new TieneRol(Rol::Profesor)],
        ], [
            'clonNombre.required' => 'Indica el nombre del nuevo curso.',
            'clonCodigo.unique' => 'Ya existe un curso con este código.',
            'clonCodigo.regex' => 'El código solo puede tener letras (A-Z), números y guiones, sin espacios ni tildes. Ejemplo: MAT-201.',
            'clonCodigo.max' => 'El código no puede tener más de 30 caracteres.',
        ]);

        $nuevoCurso = $this->curso->clonar($this->clonNombre, $this->clonProfesorId ?: null);

        if ($this->clonCodigo !== '') {
            $nuevoCurso->update(['codigo' => $this->clonCodigo]);
        }

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'clonacion_curso',
            descripcion: "Curso clonado: \"{$this->curso->nombre}\" → \"{$nuevoCurso->nombre}\". No se copiaron estudiantes, notas ni entregas.",
            curso: $nuevoCurso
        );

        $this->mostrarModalClonar = false;
        session()->flash('estado', 'Curso clonado como borrador. Ajusta fechas, sesiones y esquema de evaluación antes de publicarlo.');
        $this->redirectRoute('cursos.editar', $nuevoCurso->id, navigate: true);
    }

    public function render()
    {
        $esEstudiante = auth()->user()->hasRole('estudiante');

        $modulos = $this->curso->modulos()
            ->when($esEstudiante, fn ($q) => $q->visiblesParaEstudiante()->with('leccionesVisibles'))
            ->when(! $esEstudiante, fn ($q) => $q->withCount(['lecciones', 'tareas', 'quizzes']))
            ->orderBy('orden')
            ->get();

        return view('livewire.cursos.show', [
            'modulos' => $modulos,
            'secciones' => $esEstudiante ? $this->seccionesDelEstudiante($modulos) : $this->seccionesDeGestion($modulos),
            'profesores' => $this->puedeAdministrar ? User::role('profesor')->where('activo', true)->orderBy('name')->get() : collect(),
        ])->title("{$this->curso->nombre} — LearnUp");
    }

    // ==========================================
    // AUXILIARES
    // ==========================================

    protected function asegurarCursoEditable(): void
    {
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está archivado y no admite cambios.');
    }

    /**
     * Profesor/Supervisor: el bloque de prácticas de campo (solo si el curso
     * lo tiene) y TODAS las semanas del curso (1..N), con o sin contenido,
     * para prepararlas.
     *
     * @param  Collection<int, Modulo>  $modulos
     * @return array{practicas: ?array, semanas: list<array>}
     */
    protected function seccionesDeGestion(Collection $modulos): array
    {
        $porSemana = $modulos->whereNotNull('semana')->keyBy('semana');
        $sesiones = $this->curso->sesionesClase()->pluck('fecha');
        $practicas = $modulos->first(fn (Modulo $m) => $m->esPracticas());

        return [
            'practicas' => $practicas ? [
                ...$this->seccion(null, $practicas, $sesiones),
                // Mismo criterio que la vista del estudiante: el bloque aparece solo con contenido visible (RF-38).
                'visibleParaEstudiantes' => $practicas->esVisibleParaEstudiante() && (
                    $practicas->leccionesVisibles()->exists()
                    || $practicas->tareas()->visiblesParaEstudiante()->exists()
                    || $practicas->quizzes()->visiblesParaEstudiante()->exists()
                ),
            ] : null,
            'semanas' => array_map(
                fn (int $semana) => $this->seccion($semana, $porSemana->get($semana), $sesiones),
                range(1, $this->curso->totalSemanas()),
            ),
        ];
    }

    /**
     * Estudiante: el bloque de prácticas de campo solo si tiene contenido
     * visible, y las semanas 1..N; una semana sin contenido visible (vacía,
     * en borrador o programada, RF-38) se muestra como "Aún no disponible",
     * sin revelar su tema.
     *
     * @param  Collection<int, Modulo>  $modulos  solo los visibles para el estudiante
     * @return array{practicas: ?array, semanas: list<array>}
     */
    protected function seccionesDelEstudiante(Collection $modulos): array
    {
        $tareas = $this->resumenTareasEstudiante->groupBy(fn (array $fila) => $fila['tarea']->modulo_id);
        $quizzes = $this->resumenQuizzesEstudiante->groupBy(fn (array $fila) => $fila['quiz']->modulo_id);

        $conContenido = function (?int $semana, ?Modulo $modulo) use ($tareas, $quizzes): array {
            $seccion = [
                ...$this->seccion($semana, $modulo, collect()),
                'tareas' => $modulo ? $tareas->get($modulo->id, collect()) : collect(),
                'quizzes' => $modulo ? $quizzes->get($modulo->id, collect()) : collect(),
            ];
            $seccion['elementos'] = $modulo
                ? $modulo->leccionesVisibles->count() + $seccion['tareas']->count() + $seccion['quizzes']->count()
                : 0;

            return $seccion;
        };

        $practicas = $modulos->first(fn (Modulo $m) => $m->esPracticas());
        $practicas = $practicas ? $conContenido(null, $practicas) : null;
        $porSemana = $modulos->whereNotNull('semana')->keyBy('semana');

        return [
            'practicas' => $practicas && $practicas['elementos'] > 0 ? $practicas : null,
            'semanas' => array_map(function (int $semana) use ($conContenido, $porSemana) {
                $seccion = $conContenido($semana, $porSemana->get($semana));

                // Sin nada visible, la semana queda como "Aún no disponible".
                return $seccion['elementos'] > 0 ? $seccion : [...$seccion, 'modulo' => null];
            }, range(1, $this->curso->totalSemanas())),
        ];
    }

    /**
     * @param  Collection<int, Carbon>  $fechasSesiones
     * @return array{semana: ?int, etiqueta: string, modulo: ?Modulo, rango: ?array, clases: int}
     */
    protected function seccion(?int $semana, ?Modulo $modulo, Collection $fechasSesiones): array
    {
        $rango = $semana ? $this->curso->rangoSemana($semana) : null;

        return [
            'semana' => $semana,
            'etiqueta' => $semana ? 'Semana '.$semana : 'Prácticas de campo',
            'modulo' => $modulo,
            'rango' => $rango,
            // Sesiones de clase (RF-40) que caen en esa semana: se relacionan solo por fecha.
            'clases' => $rango ? $fechasSesiones->filter(fn ($fecha) => $fecha && $fecha->between($rango[0], $rango[1]))->count() : 0,
        ];
    }

    protected function estudiantesActivos(): Collection
    {
        return $this->curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter();
    }

    protected function tareasVisiblesDelEstudiante(): Collection
    {
        return $this->curso->tareas()
            ->visiblesParaEstudiante()
            ->with(['entregas' => fn ($q) => $q->where('estudiante_id', auth()->id())])
            ->get()
            ->filter(fn ($tarea) => Gate::allows('ver', $tarea))
            ->values();
    }
}
