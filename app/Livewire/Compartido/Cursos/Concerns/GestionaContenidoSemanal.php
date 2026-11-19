<?php

namespace App\Livewire\Compartido\Cursos\Concerns;

use App\Models\Leccion;
use App\Models\Modulo;
use App\Notifications\NuevoContenidoPublicado;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

/**
 * Contenido de cada semana en la pantalla del curso (Profesor), sin salir de
 * ella: acordeón por semana con sus secciones fijas (Leccion::SECCIONES) y
 * el formulario de cada sección en un modal.
 *
 * ERS RF-35: contenido de la semana. RF-36: archivo subido (10 MB) o enlace.
 * RF-37: Borrador | Publicado. RF-38: el estudiante solo ve lo publicado.
 * RF-67: condición de completitud. RF-84: aviso al publicar.
 *
 * Las lecciones antiguas se muestran en la sección equivalente
 * (Leccion::seccionDe) y su valor nuevo se escribe solo al guardarlas; los
 * datos que su sección ya no muestra se conservan. Una semana sin contenido
 * se crea al guardar su primer elemento.
 *
 * Usa $curso y el permiso "gestionar" de CursoPolicy del componente.
 */
trait GestionaContenidoSemanal
{
    /**
     * Campos del formulario de cada sección:
     * - texto: etiqueta del campo de texto (null = no tiene) y si es obligatorio.
     * - fuentes: de dónde sale el material ("ninguno", "archivo", "enlace").
     * - video: enlace guardado en video_url (null = no aplica) y si es obligatorio.
     *
     * @var array<string, array{texto: ?string, textoRequerido: bool, fuentes: array<int, string>, video: ?string, videoRequerido: bool}>
     */
    public const FORMULARIOS_LECCION = [
        'mensaje_inicio' => ['texto' => 'Mensaje', 'textoRequerido' => true, 'fuentes' => [], 'video' => null, 'videoRequerido' => false],
        'recursos' => ['texto' => 'Descripción', 'textoRequerido' => false, 'fuentes' => ['archivo', 'enlace'], 'video' => null, 'videoRequerido' => false],
        'actividades' => ['texto' => 'Instrucciones', 'textoRequerido' => true, 'fuentes' => ['ninguno', 'archivo', 'enlace'], 'video' => null, 'videoRequerido' => false],
        'grabacion' => ['texto' => null, 'textoRequerido' => false, 'fuentes' => [], 'video' => 'Enlace de la grabación', 'videoRequerido' => true],
        // Sección heredada: se edita como antes, todo opcional.
        'foro' => ['texto' => 'Descripción', 'textoRequerido' => false, 'fuentes' => ['ninguno', 'archivo', 'enlace'], 'video' => 'Enlace del video', 'videoRequerido' => false],
    ];

    /** @var array<string, string> */
    public const FUENTES_LECCION = [
        'ninguno' => 'Sin adjunto',
        'archivo' => 'Subir archivo',
        'enlace' => 'Pegar enlace',
    ];

    /** Clave de las prácticas de campo en $semanasAbiertas y en agregarLeccion(). */
    public const CLAVE_PRACTICAS = 0;

    /**
     * Semanas desplegadas en el acordeón (número de semana; 0 = prácticas de
     * campo). Se conservan entre re-renders para que guardar no las cierre.
     *
     * @var array<int, int>
     */
    public array $semanasAbiertas = [];

    /**
     * Sección desplegada de cada semana (número de semana => clave de
     * Leccion::SECCIONES o "foro"). Una sola por semana; todas empiezan
     * cerradas. Alpine la cambia sin pedir al servidor y se conserva entre
     * re-renders; guardar un elemento abre su sección.
     *
     * @var array<int, string>
     */
    public array $seccionesAbiertas = [];

    public bool $mostrarFormularioLeccion = false;

    /** Sección del formulario abierto (clave de Leccion::SECCIONES o "foro"). */
    #[Locked]
    public ?string $seccionLeccion = null;

    /** Semana del formulario abierto (0 = prácticas de campo). */
    #[Locked]
    public ?int $semanaLeccion = null;

    #[Locked]
    public ?int $leccionEditId = null;

    /** Estado con el que está guardada la lección que se edita (conserva "Programado" en las antiguas). */
    #[Locked]
    public ?string $estadoGuardado = null;

    /** Nombre del archivo ya subido de la lección que se edita (null si no tiene). */
    #[Locked]
    public ?string $archivoActual = null;

    /** Una lección antigua de tipo video pasa su enlace a Recursos: al guardar deja de estar en video_url. */
    #[Locked]
    public bool $moverVideoAMaterial = false;

    /** La lección que se edita guarda un video o material que su sección ya no muestra (se conserva). */
    #[Locked]
    public bool $conservaOculto = false;

    public string $titulo = '';

    public string $descripcion = '';

    public string $video_url = '';

    /** Enlace del material (solo enlaces externos; un archivo subido se maneja con $archivo). */
    public string $material_url = '';

    public string $fuente = 'ninguno';

    public $archivo = null;

    public bool $quitarArchivo = false;

    public string $estado = 'borrador';

    public string $fecha_publicacion = '';

    /** ERS RF-67: marcar | abrir_recurso (Leccion::CONDICIONES). */
    public string $condicion_completitud = 'marcar';

    /** true cuando el Profesor eligió la condición; si no, sigue el valor por defecto de la sección. */
    public bool $condicionElegida = false;

    /** Confirmación explícita antes de eliminar o vaciar. */
    public bool $mostrarConfirmacion = false;

    #[Locked]
    public string $confirmacionTitulo = '';

    #[Locked]
    public string $confirmacionMensaje = '';

    #[Locked]
    public string $confirmacionTextoBoton = '';

    #[Locked]
    public ?string $confirmacionAccion = null;

    #[Locked]
    public ?int $confirmacionObjetivoId = null;

    /**
     * Semana abierta al entrar: la pedida en la URL (?semana=N, la usa la
     * ruta antigua de lecciones), si no la semana en curso por fecha, si no la
     * primera con contenido y, si tampoco, la Semana 1.
     *
     * @param  Collection<int, Modulo>  $modulos  con withCount de lecciones, tareas y quizzes
     */
    protected function inicializarSemanasAbiertas(Collection $modulos, ?int $semanaPedida): void
    {
        $total = $this->curso->totalSemanas();

        if ($semanaPedida !== null && $semanaPedida >= self::CLAVE_PRACTICAS && $semanaPedida <= $total) {
            $this->semanasAbiertas = [$semanaPedida];

            return;
        }

        $enCurso = collect(range(1, $total))->first(function (int $semana) {
            $rango = $this->curso->rangoSemana($semana);

            return $rango && now()->between($rango[0], $rango[1]);
        });

        $conContenido = $modulos->whereNotNull('semana')->sortBy('semana')
            ->first(fn (Modulo $m) => ($m->lecciones_count + $m->tareas_count + $m->quizzes_count) > 0)?->semana;

        $this->semanasAbiertas = [$enCurso ?? $conContenido ?? 1];
    }

    /**
     * @return array{texto: ?string, textoRequerido: bool, fuentes: array<int, string>, video: ?string, videoRequerido: bool}
     */
    public function getFormularioLeccionProperty(): array
    {
        return self::FORMULARIOS_LECCION[$this->seccionLeccion ?? 'recursos'] ?? self::FORMULARIOS_LECCION['recursos'];
    }

    /**
     * "Al abrir su recurso" solo está disponible si hay un archivo o un enlace.
     *
     * @return array<string, string>
     */
    public function getOpcionesCompletitudProperty(): array
    {
        return $this->tieneRecurso() ? Leccion::CONDICIONES : ['marcar' => Leccion::CONDICIONES['marcar']];
    }

    /**
     * Borrador o Publicado. "Programado" solo aparece al editar una lección
     * que ya estaba programada, para no perder su fecha.
     *
     * @return array<string, string>
     */
    public function getEstadosLeccionProperty(): array
    {
        $estados = ['borrador' => 'Borrador', 'publicado' => 'Publicado'];

        if ($this->estadoGuardado === 'programado') {
            $estados['programado'] = 'Programado';
        }

        return $estados;
    }

    public function updatedFuente(): void
    {
        if ($this->fuente !== 'archivo') {
            $this->archivo = null;
        }

        if ($this->fuente !== 'enlace') {
            $this->material_url = '';
        }

        // El archivo actual se conserva solo mientras la fuente siga siendo "archivo".
        $this->quitarArchivo = $this->archivoActual !== null && $this->fuente !== 'archivo';
        $this->resetErrorBag(['archivo', 'material_url']);
        $this->ajustarCondicion();
    }

    /** Descarta lo que no sea "semana => sección válida" (el valor llega desde el navegador). */
    public function updatedSeccionesAbiertas(): void
    {
        $this->seccionesAbiertas = collect($this->seccionesAbiertas)
            ->filter(fn ($clave, $semana) => is_int($semana) && is_string($clave)
                && (array_key_exists($clave, Leccion::SECCIONES) || $clave === Leccion::SECCION_HEREDADA))
            ->all();
    }

    public function updatedCondicionCompletitud(): void
    {
        $this->condicionElegida = true;
    }

    public function updatedMaterialUrl(): void
    {
        $this->ajustarCondicion();
    }

    public function updatedVideoUrl(): void
    {
        $this->ajustarCondicion();
    }

    public function updatedArchivo(): void
    {
        $this->ajustarCondicion();
    }

    public function updatedQuitarArchivo(): void
    {
        $this->ajustarCondicion();
    }

    /**
     * Abre el formulario de una sección de la semana indicada (0 = prácticas
     * de campo). El Mensaje de inicio es único por semana: si ya existe, se
     * abre para editarlo.
     */
    public function agregarLeccion(string $seccion, int $semana): void
    {
        $this->autorizarContenido();
        abort_unless(array_key_exists($seccion, Leccion::SECCIONES), 404);
        $this->validarSemana($semana);

        if ($seccion === 'mensaje_inicio' && ($mensaje = $this->mensajeDeInicio($this->moduloDeSemana($semana)))) {
            $this->editarLeccion($mensaje->id);

            return;
        }

        $this->resetErrorBag();
        $this->limpiarFormularioLeccion();
        $this->seccionLeccion = $seccion;
        $this->semanaLeccion = $semana;
        $this->titulo = $seccion === 'mensaje_inicio' ? Leccion::SECCIONES['mensaje_inicio']['elemento'] : '';
        $this->fuente = $this->formularioLeccion['fuentes'][0] ?? 'ninguno';
        $this->ajustarCondicion();
        $this->abrirSemana($semana);
        $this->mostrarFormularioLeccion = true;
    }

    public function editarLeccion(int $id): void
    {
        $this->autorizarContenido();

        $leccion = $this->leccionDelCurso($id);
        $this->resetErrorBag();
        $this->limpiarFormularioLeccion();

        $materialEsEnlace = $leccion->material_url && SubidorArchivos::esEnlaceExterno($leccion->material_url);

        $this->leccionEditId = $leccion->id;
        $this->seccionLeccion = $leccion->seccion();
        $this->semanaLeccion = $leccion->modulo->semana ?? self::CLAVE_PRACTICAS;
        $this->estadoGuardado = $leccion->estado;
        $this->titulo = $leccion->titulo;
        $this->descripcion = $leccion->descripcion ?? '';
        $this->video_url = $this->formularioLeccion['video'] ? ($leccion->video_url ?? '') : '';
        $this->material_url = $materialEsEnlace ? $leccion->material_url : '';
        $this->archivoActual = $leccion->material_url && ! $materialEsEnlace ? ($leccion->material_nombre ?? 'Archivo subido') : null;
        $this->estado = $leccion->estado;
        $this->fecha_publicacion = $leccion->fecha_publicacion?->format('Y-m-d\TH:i') ?? '';
        $this->condicion_completitud = $leccion->condicion_completitud ?? 'marcar';
        $this->condicionElegida = true;

        $fuentes = $this->formularioLeccion['fuentes'];
        $this->fuente = match (true) {
            $fuentes === [] => 'ninguno',
            $this->archivoActual !== null => 'archivo',
            $this->material_url !== '' => 'enlace',
            default => $fuentes[0],
        };

        // Lección antigua de tipo video en Recursos: su enlace se edita como enlace del recurso.
        if ($this->seccionLeccion === 'recursos' && $this->fuente !== 'enlace' && $this->archivoActual === null && $leccion->video_url) {
            $this->fuente = 'enlace';
            $this->material_url = $leccion->video_url;
            $this->moverVideoAMaterial = true;
        }

        $this->conservaOculto = ($this->formularioLeccion['video'] === null && $leccion->video_url && ! $this->moverVideoAMaterial)
            || ($fuentes === [] && $leccion->material_url);

        if (! array_key_exists($this->condicion_completitud, $this->opcionesCompletitud)) {
            $this->condicion_completitud = 'marcar';
        }

        $this->abrirSemana($this->semanaLeccion);
        $this->mostrarFormularioLeccion = true;
    }

    public function cerrarFormularioLeccion(): void
    {
        $this->mostrarFormularioLeccion = false;
        $this->limpiarFormularioLeccion();
        $this->resetErrorBag();
    }

    public function guardarLeccion(SubidorArchivos $subidor): void
    {
        $this->autorizarContenido();
        abort_if($this->seccionLeccion === null || $this->semanaLeccion === null, 404);

        $leccion = $this->leccionEditId ? $this->leccionDelCurso($this->leccionEditId) : null;
        abort_if($leccion === null && ! array_key_exists($this->seccionLeccion, Leccion::SECCIONES), 404);

        $moduloActual = $leccion?->modulo ?? $this->moduloDeSemana($this->semanaLeccion);

        if ($this->seccionLeccion === 'mensaje_inicio' && ($mensaje = $this->mensajeDeInicio($moduloActual)) && $mensaje->id !== $leccion?->id) {
            $this->addError('seccionLeccion', 'Esta semana ya tiene un mensaje de inicio. Edítalo en lugar de crear otro.');

            return;
        }

        $this->validate($this->reglasLeccion(), $this->mensajesLeccion());

        $formulario = $this->formularioLeccion;
        $estabaVisible = $leccion?->esVisibleParaEstudiante() ?? false;

        $datos = [
            'titulo' => $this->titulo,
            'descripcion' => $formulario['texto'] ? ($this->descripcion ?: null) : $leccion?->descripcion,
            'video_url' => match (true) {
                $formulario['video'] !== null => $this->video_url ?: null,
                $this->moverVideoAMaterial => null,
                default => $leccion?->video_url,
            },
            'estado' => $this->estado,
            'fecha_publicacion' => $this->estado === 'programado' ? $this->fecha_publicacion : null,
            'condicion_completitud' => $this->condicion_completitud,
            'tipo_recurso' => $this->seccionLeccion,
            ...$this->datosMaterial($subidor, $leccion),
        ];

        if ($leccion) {
            $leccion->update($datos);
            $mensaje = 'Elemento actualizado.';
        } else {
            // Una semana sin contenido se crea al guardar su primer elemento (ya no hay paso "Preparar semana").
            $modulo = $moduloActual ?? $this->crearModuloDeSemana($this->semanaLeccion);
            $leccion = $modulo->lecciones()->create([
                ...$datos,
                'orden' => ((int) $modulo->lecciones()->max('orden')) + 1,
            ]);
            $mensaje = 'Elemento agregado a '.Leccion::SECCIONES[$this->seccionLeccion]['etiqueta'].'.';
        }

        $modulo = $leccion->modulo()->first();

        if (! $estabaVisible && $leccion->fresh()->esVisibleParaEstudiante() && $modulo->esVisibleParaEstudiante()) {
            $estudiantes = $this->curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter();
            Notification::send($estudiantes, new NuevoContenidoPublicado($leccion));
            $mensaje .= ' Se notificó a los estudiantes.';
        }

        $this->abrirSemana($this->semanaLeccion);
        // Solo una sección abierta por semana: la del elemento guardado.
        $this->seccionesAbiertas[$this->semanaLeccion] = $this->seccionLeccion;
        $this->cerrarFormularioLeccion();
        $this->curso->unsetRelation('modulos');
        session()->flash('estado', $mensaje);
    }

    /** Pide confirmación antes de eliminar un elemento. */
    public function confirmarEliminarLeccion(int $id): void
    {
        $this->autorizarContenido();
        $leccion = $this->leccionDelCurso($id);

        $this->pedirConfirmacion(
            'eliminarLeccion',
            $leccion->id,
            'Eliminar elemento',
            "Se eliminará \"{$leccion->titulo}\" de {$leccion->modulo->etiqueta()} ({$leccion->tipoRecurso()['etiqueta']}). Los estudiantes dejarán de verlo y se pierde su registro de avance. Esta acción no se puede deshacer.",
            'Eliminar',
        );
    }

    /** Pide confirmación antes de vaciar una semana o quitar las prácticas de campo, indicando qué se borra. */
    public function confirmarVaciarModulo(int $moduloId): void
    {
        $this->autorizarContenido();
        $modulo = $this->curso->modulos()->withCount(['lecciones', 'tareas', 'quizzes'])->findOrFail($moduloId);

        $contenido = collect([
            [$modulo->lecciones_count, 'elemento de contenido', 'elementos de contenido'],
            [$modulo->tareas_count, 'tarea', 'tareas'],
            [$modulo->quizzes_count, 'quiz', 'quizzes'],
        ])->map(fn (array $fila) => $fila[0].' '.($fila[0] === 1 ? $fila[1] : $fila[2]))->join(', ', ' y ');

        $this->pedirConfirmacion(
            'vaciarModulo',
            $modulo->id,
            $modulo->esPracticas() ? 'Quitar prácticas de campo' : "Vaciar {$modulo->etiqueta()}",
            ($modulo->esPracticas() ? 'Se quitarán las prácticas de campo' : "Se vaciará {$modulo->etiqueta()}")
                .": se eliminan {$contenido}. Si hay entregas o resultados de estudiantes, no se eliminará. Esta acción no se puede deshacer.",
            $modulo->esPracticas() ? 'Quitar' : 'Vaciar',
        );
    }

    public function ejecutarConfirmacion(): void
    {
        $accion = $this->confirmacionAccion;
        $objetivoId = $this->confirmacionObjetivoId;
        $this->cerrarConfirmacion();

        match ($accion) {
            'eliminarLeccion' => $this->eliminarLeccion($objetivoId),
            'vaciarModulo' => $this->eliminarModulo($objetivoId),
            default => abort(404),
        };
    }

    public function cerrarConfirmacion(): void
    {
        $this->reset(['mostrarConfirmacion', 'confirmacionTitulo', 'confirmacionMensaje', 'confirmacionTextoBoton', 'confirmacionAccion', 'confirmacionObjetivoId']);
    }

    public function eliminarLeccion(int $id): void
    {
        $this->autorizarContenido();

        $leccion = $this->leccionDelCurso($id);
        $this->abrirSemana($leccion->modulo->semana ?? self::CLAVE_PRACTICAS);
        $leccion->delete();
        $this->curso->unsetRelation('modulos');
        session()->flash('estado', 'Elemento eliminado.');
    }

    /**
     * Cambia el orden de un elemento con su vecino dentro de la misma sección
     * y renumera el orden de toda la semana.
     */
    public function moverLeccion(int $id, int $paso): void
    {
        $this->autorizarContenido();
        abort_unless(in_array($paso, [-1, 1], true), 404);

        $lecciones = $this->leccionDelCurso($id)->modulo->lecciones()->get()->values();
        $indiceActual = $lecciones->search(fn (Leccion $l) => $l->id === $id);
        $seccion = $lecciones[$indiceActual]->seccion();

        $deLaSeccion = $lecciones->filter(fn (Leccion $l) => $l->seccion() === $seccion)->keys()->values();
        $vecino = $deLaSeccion->get($deLaSeccion->search($indiceActual) + $paso);

        if ($vecino === null) {
            return;
        }

        $ordenadas = $lecciones->all();
        [$ordenadas[$indiceActual], $ordenadas[$vecino]] = [$ordenadas[$vecino], $ordenadas[$indiceActual]];

        foreach (array_values($ordenadas) as $indice => $elemento) {
            if ($elemento->orden !== $indice + 1) {
                $elemento->update(['orden' => $indice + 1]);
            }
        }

        $this->curso->unsetRelation('modulos');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function reglasLeccion(): array
    {
        $formulario = $this->formularioLeccion;
        $fuentes = $formulario['fuentes'];

        $reglas = [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => [$formulario['textoRequerido'] ? 'required' : 'nullable', 'string', 'max:10000'],
            'estado' => ['required', Rule::in(array_keys($this->estadosLeccion))],
            'fecha_publicacion' => ['nullable', 'required_if:estado,programado', 'date', 'after:now'],
            'condicion_completitud' => ['required', Rule::in(array_keys($this->opcionesCompletitud))],
        ];

        if ($formulario['video'] !== null) {
            $reglas['video_url'] = [$formulario['videoRequerido'] ? 'required' : 'nullable', 'url:http,https', 'max:500'];
        }

        if ($fuentes !== []) {
            $reglas['fuente'] = ['required', Rule::in($fuentes)];
            $reglas['material_url'] = [$this->fuente === 'enlace' ? 'required' : 'nullable', 'url:http,https', 'max:500'];
            $reglas['archivo'] = SubidorArchivos::reglas(
                $this->seccionLeccion === Leccion::SECCION_HEREDADA ? null : Leccion::EXTENSIONES_ARCHIVO,
                requerido: $this->fuente === 'archivo' && ! $this->conservaArchivo(),
            );
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesLeccion(): array
    {
        return [
            'descripcion.required' => 'Escribe '.($this->formularioLeccion['texto'] === 'Mensaje' ? 'el mensaje' : 'las instrucciones').'.',
            'fecha_publicacion.required_if' => 'Indica la fecha y hora de publicación automática.',
            'fecha_publicacion.after' => 'La fecha de publicación programada debe ser futura.',
            'video_url.required' => 'Pega el enlace de la grabación.',
            'video_url.url' => 'El enlace debe empezar con http:// o https://.',
            'material_url.required' => 'Pega el enlace del recurso.',
            'material_url.url' => 'El enlace debe empezar con http:// o https://.',
            'fuente.in' => 'Elige si subes un archivo o pegas un enlace.',
            'condicion_completitud.in' => 'Para completarse al abrir su recurso, el elemento necesita un archivo o un enlace.',
            ...SubidorArchivos::mensajes('archivo'),
            'archivo.required' => 'Sube un archivo o elige "Pegar enlace".',
            ...($this->seccionLeccion === Leccion::SECCION_HEREDADA ? [] : ['archivo.extensions' => 'Formato no permitido. Usa PDF, Word, Excel, PowerPoint o una imagen.']),
        ];
    }

    /**
     * Material de la lección (material_url y material_nombre): un archivo nuevo
     * o un enlace reemplazan al anterior; si la fuente sigue siendo "archivo"
     * se conserva el actual. En las secciones sin material (Mensaje de inicio,
     * Grabación) se conserva el que tuviera una lección antigua.
     *
     * @return array{material_url: ?string, material_nombre: ?string}
     */
    protected function datosMaterial(SubidorArchivos $subidor, ?Leccion $leccion): array
    {
        if ($this->formularioLeccion['fuentes'] === []) {
            return ['material_url' => $leccion?->material_url, 'material_nombre' => $leccion?->material_nombre];
        }

        if ($this->fuente === 'archivo' && $this->archivo) {
            return [
                'material_url' => $subidor->subir($this->archivo, 'cursos/lecciones', 'archivo', $this->seccionLeccion === Leccion::SECCION_HEREDADA ? null : Leccion::EXTENSIONES_ARCHIVO),
                'material_nombre' => $this->archivo->getClientOriginalName(),
            ];
        }

        if ($this->fuente === 'enlace' && $this->material_url !== '') {
            return ['material_url' => $this->material_url, 'material_nombre' => null];
        }

        if ($this->fuente === 'archivo' && $this->conservaArchivo()) {
            return ['material_url' => $leccion?->material_url, 'material_nombre' => $leccion?->material_nombre];
        }

        return ['material_url' => null, 'material_nombre' => null];
    }

    protected function conservaArchivo(): bool
    {
        return $this->archivoActual !== null && ! $this->quitarArchivo;
    }

    protected function tieneRecurso(): bool
    {
        $tieneMaterial = match ($this->fuente) {
            'archivo' => $this->archivo !== null || $this->conservaArchivo(),
            'enlace' => $this->material_url !== '',
            default => false,
        };

        return $tieneMaterial || ($this->formularioLeccion['video'] !== null && $this->video_url !== '');
    }

    /**
     * Mientras el Profesor no elija, la condición sigue el valor por defecto:
     * al abrir el recurso si hay archivo o enlace (salvo en el Mensaje de
     * inicio), y si no, marcarla como vista. Una opción que deja de ser válida
     * vuelve a "marcar".
     */
    protected function ajustarCondicion(): void
    {
        if (! $this->condicionElegida) {
            $this->condicion_completitud = $this->seccionLeccion !== 'mensaje_inicio' && $this->tieneRecurso() ? 'abrir_recurso' : 'marcar';
        }

        if (! array_key_exists($this->condicion_completitud, $this->opcionesCompletitud)) {
            $this->condicion_completitud = 'marcar';
        }
    }

    protected function limpiarFormularioLeccion(): void
    {
        $this->reset([
            'seccionLeccion', 'semanaLeccion', 'leccionEditId', 'estadoGuardado', 'archivoActual', 'moverVideoAMaterial', 'conservaOculto',
            'titulo', 'descripcion', 'video_url', 'material_url', 'fuente', 'archivo', 'quitarArchivo', 'fecha_publicacion', 'condicionElegida',
        ]);
        $this->estado = 'borrador';
        $this->condicion_completitud = 'marcar';
    }

    /**
     * @param  'eliminarLeccion'|'vaciarModulo'  $accion
     */
    protected function pedirConfirmacion(string $accion, int $objetivoId, string $titulo, string $mensaje, string $textoBoton): void
    {
        $this->confirmacionAccion = $accion;
        $this->confirmacionObjetivoId = $objetivoId;
        $this->confirmacionTitulo = $titulo;
        $this->confirmacionMensaje = $mensaje;
        $this->confirmacionTextoBoton = $textoBoton;
        $this->mostrarConfirmacion = true;
    }

    protected function abrirSemana(int $semana): void
    {
        $this->semanasAbiertas = collect($this->semanasAbiertas)->map(fn ($s) => (int) $s)->push($semana)->unique()->values()->all();
    }

    protected function validarSemana(int $semana): void
    {
        if ($semana === self::CLAVE_PRACTICAS) {
            abort_unless($this->curso->modulos()->where('tipo_seccion', Modulo::TIPO_PRACTICAS)->exists(), 404);

            return;
        }

        abort_if($semana < 1 || $semana > $this->curso->totalSemanas(), 404);
    }

    protected function moduloDeSemana(int $semana): ?Modulo
    {
        return $semana === self::CLAVE_PRACTICAS
            ? $this->curso->modulos()->where('tipo_seccion', Modulo::TIPO_PRACTICAS)->first()
            : $this->curso->modulos()->where('semana', $semana)->first();
    }

    protected function crearModuloDeSemana(int $semana): Modulo
    {
        $this->validarSemana($semana);

        return $semana === self::CLAVE_PRACTICAS
            ? $this->curso->modulos()->where('tipo_seccion', Modulo::TIPO_PRACTICAS)->firstOrFail()
            : $this->curso->modulos()->firstOrCreate(['semana' => $semana], ['titulo' => 'Semana '.$semana, 'orden' => $semana, 'materiales' => []]);
    }

    protected function mensajeDeInicio(?Modulo $modulo): ?Leccion
    {
        return $modulo?->lecciones()->where('tipo_recurso', 'mensaje_inicio')->first();
    }

    /** Lección de un módulo de este curso (protección IDOR). */
    protected function leccionDelCurso(int $id): Leccion
    {
        return Leccion::query()
            ->whereHas('modulo', fn ($query) => $query->where('curso_id', $this->curso->id))
            ->with('modulo')
            ->findOrFail($id);
    }

    protected function autorizarContenido(): void
    {
        Gate::authorize('gestionar', $this->curso);
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está archivado y no admite cambios.');
    }
}
