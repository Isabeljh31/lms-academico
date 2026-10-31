<?php

namespace App\Livewire\Supervisor\Cursos;

use App\Enums\Rol;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\User;
use App\Rules\TieneRol;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Formulario de creación/edición de cursos (solo Supervisor).
 * ERS RF-18, RF-19, RF-22, RF-24, RF-25, RF-73; regla de negocio 8.
 */
#[Layout('layouts.app')]
class Formulario extends Component
{
    use WithFileUploads;

    public ?Curso $curso = null;

    public string $nombre = '';

    public string $codigo = '';

    /** Deja de autocompletar el código apenas el Supervisor lo edita a mano. */
    public bool $codigoEditadoManualmente = false;

    /** Nombre ya registrado parecido a este, para el aviso "¿quisiste decir...?". */
    public string $sugerenciaNombre = '';

    public string $descripcion = '';

    public ?int $profesor_id = null;

    public string $busquedaProfesorTitular = '';

    /** Solo disponible al crear: el curso queda a tu nombre hasta que asignes un profesor real (ver Admin > Profesores). */
    public bool $asignarProfesorDespues = false;

    public string $busquedaProfesorAdicional = '';

    /**
     * ERS RF-23: profesores adicionales (co-docentes), además del titular.
     *
     * @var array<int, int|string>
     */
    public array $profesoresAdicionales = [];

    public $portada = null;

    // estados: borrador | publicado | despublicado | archivado
    public string $estado = 'borrador';

    public string $fecha_inicio = '';

    /** Siempre calculada desde fecha_inicio + semanas_duracion; no es editable. */
    public string $fecha_fin = '';

    /** Semanas en que se organiza el contenido (Semana 1..N); por defecto Curso::SEMANAS_PREDETERMINADAS. */
    public string $semanas_duracion = '16';

    /** Vacío = sin límite de cupos. */
    public string $cupo_maximo = '';

    public string $modalidad = 'presencial';

    /**
     * Un bloque por cada día de clase: {dia, hora_inicio, hora_fin, aula}.
     * Lunes y viernes pueden tener horas (y aula, si es presencial)
     * distintas entre sí. No aplica a modalidad virtual (asíncrona).
     *
     * @var array<int, array{dia: string, hora_inicio: string, hora_fin: string, aula: string}>
     */
    public array $horarioBloques = [];

    public bool $usa_asistencia = true;

    public function mount(?Curso $curso = null): void
    {
        if ($curso && $curso->exists) {
            Gate::authorize('administrar', $curso);

            $this->curso = $curso;
            $this->nombre = $curso->nombre;
            $this->codigo = $curso->codigo ?? '';
            $this->codigoEditadoManualmente = filled($this->codigo);
            $this->descripcion = $curso->descripcion ?? '';
            $this->profesor_id = $curso->profesor_id;
            $this->busquedaProfesorTitular = $curso->profesor?->name ?? '';
            $this->profesoresAdicionales = $curso->profesoresAdicionales()->pluck('users.id')->map(fn ($id) => (string) $id)->all();
            $this->estado = $curso->estado;
            $this->fecha_inicio = $curso->fecha_inicio?->format('Y-m-d') ?? '';
            $this->fecha_fin = $curso->fecha_fin?->format('Y-m-d') ?? '';
            $this->semanas_duracion = (string) ($curso->semanas_duracion ?? $curso->totalSemanas());
            $this->cupo_maximo = (string) ($curso->cupo_maximo ?? '');
            $this->modalidad = $curso->modalidad ?? 'presencial';
            $this->horarioBloques = collect($curso->horario_bloques ?? [])->map(fn (array $bloque) => [
                'dia' => (string) ($bloque['dia'] ?? ''),
                'hora_inicio' => $bloque['hora_inicio'] ?? '',
                'hora_fin' => $bloque['hora_fin'] ?? '',
                'aula' => $bloque['aula'] ?? '',
            ])->values()->all();
            $this->usa_asistencia = (bool) $curso->usa_asistencia;
        } else {
            Gate::authorize('crear', Curso::class);
        }
    }

    public function updatingBusquedaProfesorTitular(): void
    {
        $this->profesor_id = null;
    }

    public function seleccionarProfesorTitular(int $id): void
    {
        $profesor = User::role(Rol::Profesor->value)->findOrFail($id);
        $this->profesor_id = $profesor->id;
        $this->busquedaProfesorTitular = $profesor->name;
    }

    public function cambiarProfesorTitular(): void
    {
        $this->profesor_id = null;
        $this->busquedaProfesorTitular = '';
    }

    /** No se puede ofrecer esto al editar: para quitarle el titular a un curso ya existente se usa Admin > Profesores. */
    public function updatedAsignarProfesorDespues(): void
    {
        if ($this->curso) {
            $this->asignarProfesorDespues = false;

            return;
        }

        $this->profesor_id = null;
        $this->busquedaProfesorTitular = '';
    }

    public function agregarProfesorAdicional(int $id): void
    {
        if ((int) $id === (int) $this->profesor_id || in_array((string) $id, $this->profesoresAdicionales, true)) {
            return;
        }

        $profesor = User::role(Rol::Profesor->value)->findOrFail($id);
        $this->profesoresAdicionales[] = (string) $profesor->id;
        $this->busquedaProfesorAdicional = '';
    }

    public function quitarProfesorAdicional(int $id): void
    {
        $this->profesoresAdicionales = collect($this->profesoresAdicionales)
            ->reject(fn ($existente) => (int) $existente === $id)->values()->all();
    }

    public function updatedFechaInicio(): void
    {
        $this->recalcularFechaFin();
    }

    public function updatedSemanasDuracion(): void
    {
        $this->recalcularFechaFin();
    }

    /** Última fecha del curso = fecha de inicio + la duración en semanas. Siempre fija, no editable. */
    protected function recalcularFechaFin(): void
    {
        if ($this->fecha_inicio === '') {
            return;
        }

        $semanas = (int) $this->semanas_duracion;

        if ($semanas < 1) {
            return;
        }

        try {
            $this->fecha_fin = Carbon::parse($this->fecha_inicio)->addWeeks($semanas)->format('Y-m-d');
        } catch (\Throwable) {
            // Fecha todavía incompleta mientras se escribe; la validación normal la atrapará al guardar.
        }
    }

    public function agregarBloqueHorario(): void
    {
        if (count($this->horarioBloques) >= 7) {
            return;
        }

        $this->horarioBloques[] = ['dia' => '', 'hora_inicio' => '', 'hora_fin' => '', 'aula' => ''];
    }

    public function quitarBloqueHorario(int $indice): void
    {
        unset($this->horarioBloques[$indice]);
        $this->horarioBloques = array_values($this->horarioBloques);
    }

    /** Minutos entre la hora de inicio y fin de un bloque, o null si falta algo o está invertido. */
    public function duracionBloque(array $bloque): ?int
    {
        if (blank($bloque['hora_inicio'] ?? null) || blank($bloque['hora_fin'] ?? null)) {
            return null;
        }

        try {
            $minutos = Carbon::parse($bloque['hora_inicio'])->diffInMinutes(Carbon::parse($bloque['hora_fin']), false);
        } catch (\Throwable) {
            return null;
        }

        return $minutos > 0 ? $minutos : null;
    }

    /**
     * Mientras se crea un curso (no al editar uno existente), arma el código
     * automáticamente apenas se escribe el nombre: hereda el prefijo de un
     * curso con el mismo nombre si ya existía (nueva sección de la misma
     * materia), o lo arma desde el texto si es nombre nuevo. Si el Supervisor
     * ya editó el código a mano, no se lo vuelve a pisar.
     */
    public function updatedNombre(): void
    {
        $this->sugerenciaNombre = $this->curso ? '' : (string) (Curso::nombreParecido($this->nombre) ?? '');

        if ($this->curso || $this->codigoEditadoManualmente) {
            return;
        }

        $this->codigo = (string) (Curso::sugerirCodigo($this->nombre) ?? '');
        $this->validarCodigoEnVivo();
    }

    public function usarNombreSugerido(): void
    {
        $this->nombre = $this->sugerenciaNombre;
        $this->sugerenciaNombre = '';
        $this->updatedNombre();
    }

    /** El código se muestra en mayúsculas apenas se escribe, y se valida al instante (no hasta guardar). */
    public function updatedCodigo(): void
    {
        $this->codigoEditadoManualmente = true;
        $this->codigo = (string) Curso::normalizarCodigo($this->codigo);
        $this->validarCodigoEnVivo();
    }

    protected function validarCodigoEnVivo(): void
    {
        $this->resetErrorBag('codigo');

        if ($this->codigo === '' || ! preg_match(Curso::FORMATO_CODIGO, $this->codigo)) {
            return;
        }

        $yaExiste = Curso::query()->where('codigo', $this->codigo)
            ->when($this->curso, fn ($q) => $q->whereKeyNot($this->curso->id))
            ->exists();

        if ($yaExiste) {
            $this->addError('codigo', 'Ya existe un curso con este código.');

            return;
        }

        $otraMateria = Curso::prefijoPerteneceAOtraMateria($this->codigo, $this->nombre, $this->curso?->id);

        if ($otraMateria) {
            $this->addError('codigo', "Ese prefijo ya pertenece a \"{$otraMateria}\". Usa uno distinto o elige ese curso en el campo Nombre.");
        }
    }

    public function getEsArchivadoProperty(): bool
    {
        return (bool) $this->curso?->estaFinalizado();
    }

    public function guardar(SubidorArchivos $subidor): void
    {
        $editando = $this->curso && $this->curso->exists;
        $editando ? Gate::authorize('administrar', $this->curso) : Gate::authorize('crear', Curso::class);

        // Regla de negocio 8 / RNF-14: un curso archivado solo puede cambiar de estado.
        if ($editando && $this->esArchivado) {
            $this->guardarSoloEstado();

            return;
        }

        $this->codigo = (string) Curso::normalizarCodigo($this->codigo);

        $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'codigo' => [
                'nullable', 'string', 'max:30', 'regex:'.Curso::FORMATO_CODIGO,
                Rule::unique('cursos', 'codigo')->ignore($this->curso?->id),
                function (string $atributo, mixed $valor, \Closure $fail): void {
                    $otraMateria = Curso::prefijoPerteneceAOtraMateria((string) $valor, $this->nombre, $this->curso?->id);

                    if ($otraMateria) {
                        $fail("Ese prefijo ya pertenece a \"{$otraMateria}\". Usa uno distinto o elige ese curso en el campo Nombre.");
                    }
                },
            ],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            // Si el titular no cambia se conserva aunque hoy sea el Supervisor
            // (curso sin profesor tras desasignarlo, ver Admin\Profesores\Index).
            // Al crear, "asignar después" deja el campo libre (se resuelve más abajo a tu propio id).
            'profesor_id' => $this->asignarProfesorDespues && ! $editando
                ? ['nullable']
                : ['required', (int) $this->profesor_id === $this->curso?->profesor_id
                    ? Rule::exists('users', 'id')
                    : new TieneRol(Rol::Profesor)],
            'profesoresAdicionales' => ['array', 'max:10', new TieneRol(Rol::Profesor)],
            'portada' => SubidorArchivos::reglas(config('archivos.extensiones.imagenes'), requerido: false),
            'estado' => ['required', Rule::in(Curso::ESTADOS)],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            // No puede quedar por debajo de la última semana que ya tiene contenido.
            'semanas_duracion' => ['required', 'integer', 'min:'.max(1, (int) $this->curso?->modulos()->max('semana')), 'max:104'],
            'cupo_maximo' => ['nullable', 'integer', 'min:'.max(1, (int) $this->curso?->inscripcionesActivas()->count()), 'max:500'],
            'modalidad' => ['required', Rule::in(Curso::MODALIDADES)],
            'horarioBloques' => [
                'array', 'max:7',
                function (string $atributo, array $valor, \Closure $fail): void {
                    $dias = collect($valor)->pluck('dia')->filter(fn ($dia) => $dia !== '')->map(fn ($dia) => (int) $dia);

                    if ($dias->count() !== $dias->unique()->count()) {
                        $fail('No repitas el mismo día en más de un bloque de horario.');
                    }
                },
            ],
            'horarioBloques.*.dia' => ['required', 'integer', 'between:1,7'],
            'horarioBloques.*.hora_inicio' => ['required', 'date_format:H:i'],
            'horarioBloques.*.hora_fin' => ['required', 'date_format:H:i', 'after:horarioBloques.*.hora_inicio'],
            'horarioBloques.*.aula' => [$this->modalidad === 'presencial' ? 'required' : 'nullable', 'string', 'max:50'],
            'usa_asistencia' => ['boolean'],
        ], [
            'profesor_id.required' => 'Selecciona el profesor titular del curso.',
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
            'codigo.unique' => 'Ya existe un curso con este código.',
            'codigo.regex' => 'El código solo puede tener letras (A-Z), números y guiones, sin espacios ni tildes. Ejemplo: MAT-201.',
            'codigo.max' => 'El código no puede tener más de 30 caracteres.',
            'semanas_duracion.required' => 'Indica cuántas semanas dura el curso.',
            'semanas_duracion.min' => 'La semana :min ya tiene contenido: la duración no puede ser menor. Vacía o mueve esas semanas primero.',
            'cupo_maximo.min' => 'Ya hay :min estudiantes matriculados: el cupo no puede ser menor a esa cantidad.',
            'horarioBloques.*.dia.required' => 'Elige el día de este bloque de horario.',
            'horarioBloques.*.hora_inicio.required' => 'Indica la hora de inicio de este bloque.',
            'horarioBloques.*.hora_fin.required' => 'Indica la hora de fin de este bloque.',
            'horarioBloques.*.hora_fin.after' => 'La hora de fin debe ser después de la hora de inicio.',
            'horarioBloques.*.aula.required' => 'Indica el aula de este bloque (el curso es presencial).',
            ...SubidorArchivos::mensajes('portada'),
        ]);

        // "Asignar después" (solo al crear): el curso queda a tu nombre hasta que
        // lo asignes a un profesor real desde Admin > Profesores (mismo mecanismo
        // que ya usa desasignarCurso con un titular retirado).
        $profesorIdFinal = $this->asignarProfesorDespues && ! $editando ? auth()->id() : (int) $this->profesor_id;

        $adicionales = collect($this->profesoresAdicionales)->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $profesorIdFinal)->unique()->values();

        // ERS RF-25: solo se publica un curso con contenido en al menos una semana (o en prácticas de campo).
        if ($this->estado === 'publicado' && (! $editando || $this->curso->modulos()->doesntExist())) {
            $this->addError('estado', 'Para publicar el curso debe tener contenido en al menos una semana. Guárdalo como borrador, prepara sus semanas y luego publícalo.');

            return;
        }

        // Un curso publicado sin nadie matriculado no tiene a quién mostrarle el contenido.
        if ($this->estado === 'publicado' && $editando && $this->curso->inscripcionesActivas()->doesntExist()) {
            $this->addError('estado', 'Para publicar el curso necesita al menos un estudiante matriculado. Matricúlalo primero desde "Matrícula".');

            return;
        }

        $portadaUrl = $this->curso?->imagen_portada_url;

        if ($this->portada) {
            $portadaUrl = $subidor->subir($this->portada, 'cursos/portadas', 'portada', config('archivos.extensiones.imagenes'));
            $subidor->eliminar($this->curso?->imagen_portada_url);
        }

        $datos = [
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion ?: null,
            'profesor_id' => $profesorIdFinal,
            'imagen_portada_url' => $portadaUrl,
            'estado' => $this->estado,
            'fecha_inicio' => $this->fecha_inicio ?: null,
            'fecha_fin' => $this->fecha_fin ?: null,
            'semanas_duracion' => $this->semanas_duracion ?: null,
            'cupo_maximo' => $this->cupo_maximo ?: null,
            'modalidad' => $this->modalidad,
            'horario_bloques' => in_array($this->modalidad, Curso::MODALIDADES_CON_HORARIO, true) && $this->horarioBloques !== []
                ? collect($this->horarioBloques)->map(fn (array $bloque) => [
                    'dia' => (int) $bloque['dia'],
                    'hora_inicio' => $bloque['hora_inicio'],
                    'hora_fin' => $bloque['hora_fin'],
                    'aula' => $this->modalidad === 'presencial' ? ($bloque['aula'] ?: null) : null,
                ])->values()->all()
                : null,
            'usa_asistencia' => $this->usa_asistencia,
        ];

        if ($editando) {
            $anterior = $this->curso->only(array_keys($datos)) + ['profesores_adicionales' => $this->curso->profesoresAdicionales()->pluck('users.id')->sort()->values()->all()];
            $this->curso->update($datos);
            $curso = $this->curso;
            $mensaje = "Curso \"{$curso->nombre}\" actualizado.";
        } else {
            $anterior = null;
            $curso = Curso::create($datos);
            $mensaje = "Curso \"{$curso->nombre}\" creado como {$curso->estado}. Agrega sus módulos y define su esquema de evaluación.";

            $sesionesGeneradas = $curso->generarSesionesDesdeHorario();

            if ($sesionesGeneradas > 0) {
                $mensaje .= " Se generaron {$sesionesGeneradas} sesiones de clase según el horario.";
            }
        }

        $curso->profesoresAdicionales()->sync($adicionales->all());

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'modificacion_curso',
            descripcion: ($editando ? 'Curso editado: ' : 'Curso creado: ')."\"{$curso->nombre}\" (estado {$curso->estado}).",
            curso: $curso,
            valorAnterior: $anterior,
            valorNuevo: $curso->only(array_keys($datos)) + ['profesores_adicionales' => $adicionales->sort()->values()->all()],
        );

        session()->flash('estado', $mensaje);
        $this->redirect(route('cursos.show', $curso), navigate: true);
    }

    protected function guardarSoloEstado(): void
    {
        $this->validate(['estado' => ['required', Rule::in(Curso::ESTADOS)]]);

        if ($this->estado === 'archivado') {
            $this->addError('estado', 'El curso ya está archivado. Solo puedes cambiar su estado para reabrirlo.');

            return;
        }

        $this->curso->update(['estado' => $this->estado]);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'modificacion_curso',
            descripcion: "Curso reabierto: \"{$this->curso->nombre}\".",
            curso: $this->curso,
            valorAnterior: 'archivado',
            valorNuevo: $this->estado,
        );

        session()->flash('estado', "El curso \"{$this->curso->nombre}\" se reabrió con estado {$this->estado}.");
        $this->redirect(route('cursos.show', $this->curso), navigate: true);
    }

    /** @return Collection<int, User> */
    protected function buscarProfesores(string $busqueda, array $excluirIds): Collection
    {
        $busqueda = trim($busqueda);

        if (mb_strlen($busqueda) < 2) {
            return collect();
        }

        return User::role(Rol::Profesor->value)
            ->where('activo', true)
            ->whereNotIn('id', $excluirIds)
            ->where(fn ($q) => $q->whereLike('name', "%{$busqueda}%")->orWhereLike('email', "%{$busqueda}%"))
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    public function render()
    {
        $profesoresAdicionalesIds = collect($this->profesoresAdicionales)->map(fn ($id) => (int) $id)->all();

        return view('livewire.supervisor.cursos.formulario', [
            'profesoresBusquedaTitular' => $this->profesor_id
                ? collect()
                : $this->buscarProfesores($this->busquedaProfesorTitular, $profesoresAdicionalesIds),
            'profesoresBusquedaAdicional' => $this->buscarProfesores(
                $this->busquedaProfesorAdicional,
                array_filter([$this->profesor_id, ...$profesoresAdicionalesIds]),
            ),
            'profesoresAdicionalesSeleccionados' => $profesoresAdicionalesIds
                ? User::whereIn('id', $profesoresAdicionalesIds)->orderBy('name')->get()
                : collect(),
            'nombresExistentes' => Curso::query()->when($this->curso, fn ($q) => $q->whereKeyNot($this->curso->id))->distinct()->orderBy('nombre')->pluck('nombre'),
            'estadosCurso' => [
                'borrador' => 'Borrador (no visible para estudiantes)',
                'publicado' => 'Publicado (visible para los estudiantes matriculados)',
                'despublicado' => 'Despublicado (oculto temporalmente)',
                'archivado' => 'Archivado (finalizado: ya no admite cambios)',
            ],
        ])->title(($this->curso ? 'Editar curso' : 'Nuevo curso').' — LearnUp');
    }
}
