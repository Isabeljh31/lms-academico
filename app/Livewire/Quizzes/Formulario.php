<?php

namespace App\Livewire\Quizzes;

use App\Models\BancoPregunta;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Quiz;
use App\Notifications\CambioFechaQuiz;
use App\Notifications\NotaQuizModificada;
use App\Notifications\NuevoQuizPublicado;
use App\Services\Evaluacion\EstadisticaFallosBanco;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * ERS RF-44/RF-45: quiz con preguntas de opción única, múltiple y V/F,
 * puntaje por pregunta, retroalimentación, intentos, tiempo límite,
 * aleatorización y fechas. RF-47: preguntas desde el banco.
 * RF-50 a RF-53: si el quiz ya tiene intentos, se pueden corregir el texto y
 * la respuesta correcta; el sistema advierte cuántos estudiantes se ven
 * afectados y pregunta si recalcular. El recálculo se audita y se notifica.
 */
#[Layout('layouts.app')]
class Formulario extends Component
{
    public Curso $curso;

    /** Se abrió desde la pantalla Actividades (?desde=actividades): al guardar o cancelar vuelve allí. */
    #[Locked]
    public bool $desdeActividades = false;

    public ?Quiz $quiz = null;

    public string $tema = '';

    public ?int $modulo_id = null;

    /** @var array<int, array<string, mixed>> */
    public array $preguntas = [];

    public ?int $tiempo_limite_min = null;

    public int $intentos = 1;

    public bool $aleatorizar = false;

    public bool $mostrar_retroalimentacion = true;

    /**
     * Puntaje total del quiz (escala 0–20) para repartir entre las preguntas.
     * Se guarda en configuracion.puntaje_total. Es una guía: la nota del
     * estudiante se sigue calculando sobre 20 (Quiz::calcularPuntaje).
     */
    public string $puntaje_total = '20';

    public const PUNTAJE_TOTAL_POR_DEFECTO = 20;

    public const PUNTAJE_MINIMO_PREGUNTA = 0.5;

    /** @var array<int, string> */
    protected const REGLAS_PUNTAJE_TOTAL = ['required', 'numeric', 'gt:0', 'max:20'];

    /** @var array<string, string> */
    protected const MENSAJES_PUNTAJE_TOTAL = [
        'puntaje_total.required' => 'Indica el puntaje total del quiz.',
        'puntaje_total.numeric' => 'El puntaje total debe ser un número.',
        'puntaje_total.gt' => 'El puntaje total debe ser mayor que 0.',
        'puntaje_total.max' => 'El puntaje total no puede superar 20.',
    ];

    public string $fecha_inicio = '';

    public string $fecha_limite = '';

    public string $estado_contenido = 'publicado';

    public string $publicar_en = '';

    // Banco de preguntas (RF-47)
    /** Panel "Agregar desde el banco de preguntas" abierto. */
    public bool $mostrarBanco = false;

    /**
     * Unidad actual del banco (la que el profesor eligió en el Banco, guardada
     * en su navegador); las anteriores están archivadas.
     */
    #[Locked]
    public string $unidadActualBanco = '';

    /** Primer paso del panel: una unidad no archivada o BancoPregunta::SIN_UNIDAD; luego el tema de esa unidad. */
    public string $unidadBanco = '';

    public string $temaBanco = '';

    public string $busquedaBanco = '';

    /** @var array<int, int|string> */
    public array $seleccionBanco = [];

    /** Muestra también las preguntas de las unidades archivadas, con su % de fallos. */
    public bool $incluirAnteriores = false;

    /** Orden de las preguntas archivadas: 'fallos' (más falladas primero) o 'tema'. */
    public string $ordenAnteriores = 'fallos';

    public string $cantidadMasFalladas = '5';

    // Recálculo (RF-51)
    public bool $mostrarModalRecalculo = false;

    public function mount(Curso $curso, ?Quiz $quiz = null): void
    {
        $this->curso = $curso;
        $this->desdeActividades = request()->query('desde') === 'actividades';

        if ($quiz?->exists) {
            Gate::authorize('gestionar', $quiz);

            $this->quiz = $quiz;
            $this->tema = $quiz->tema;
            $this->modulo_id = $quiz->modulo_id;
            $this->preguntas = array_map(fn (array $p) => $this->normalizarPregunta($p), $quiz->preguntas ?? []);
            $this->tiempo_limite_min = $quiz->tiempoLimiteMinutos();
            $this->intentos = $quiz->intentosPermitidos();
            $this->aleatorizar = $quiz->aleatoriza();
            $this->mostrar_retroalimentacion = $quiz->muestraRetroalimentacion();
            $this->puntaje_total = (string) round((float) ($quiz->configuracion['puntaje_total'] ?? self::PUNTAJE_TOTAL_POR_DEFECTO), 2);
            $this->fecha_inicio = $quiz->fecha_inicio?->format('Y-m-d\TH:i') ?? '';
            $this->fecha_limite = $quiz->fecha_limite?->format('Y-m-d\TH:i') ?? '';
            $this->estado_contenido = $quiz->visible ? ($quiz->estado_contenido ?? 'publicado') : 'borrador';
            $this->publicar_en = $quiz->publicar_en?->format('Y-m-d\TH:i') ?? '';

            // Si los puntajes guardados no son un reparto en partes iguales, se respetan (cuentan como manuales).
            if (! $this->coincideConRepartoIgual()) {
                $this->preguntas = array_map(fn (array $p) => [...$p, 'puntaje_manual' => true], $this->preguntas);
            }
        } else {
            // Un quiz nuevo empieza sin preguntas: el formulario ofrece el banco o escribir una nueva.
            Gate::authorize('crear', [Quiz::class, $curso]);
        }

        abort_if($curso->estaFinalizado(), 403, 'El curso está archivado y no admite cambios.');
    }

    /** Con intentos registrados no se agregan ni quitan preguntas u opciones (el recálculo usa sus posiciones). */
    public function getEstructuraBloqueadaProperty(): bool
    {
        return (bool) $this->quiz?->resultados()->exists();
    }

    /** Suma de los puntajes de las preguntas, para compararla con el puntaje total del quiz. */
    public function getSumaPuntajesProperty(): float
    {
        return round(collect($this->preguntas)->sum(fn (array $p) => is_numeric($p['puntaje'] ?? null) ? (float) $p['puntaje'] : 0), 2);
    }

    /**
     * Reparte el puntaje total en partes iguales (2 decimales); lo que no
     * cuadra por el redondeo va a la última pregunta, así la suma es exacta.
     * Con intentos registrados no se permite (misma regla que la estructura).
     */
    public function repartirEnPartesIguales(): void
    {
        abort_if($this->estructuraBloqueada, 403);
        $this->autorizar();
        $this->validateOnly('puntaje_total', ['puntaje_total' => self::REGLAS_PUNTAJE_TOTAL], self::MENSAJES_PUNTAJE_TOTAL);

        $cantidad = count($this->preguntas);

        if ($cantidad === 0) {
            return;
        }

        if (! $this->aplicarReparto()) {
            $this->addError('puntaje_total', "Con {$cantidad} preguntas, cada una debe valer al menos ".self::PUNTAJE_MINIMO_PREGUNTA.': sube el total o quita preguntas.');

            return;
        }

        // Repartir a pedido deja todos los puntajes otra vez en automático.
        $this->preguntas = array_map(fn (array $p) => [...$p, 'puntaje_manual' => false], $this->preguntas);
    }

    /** true si algún puntaje lo cambió el profesor a mano (entonces ya no se reparte solo). */
    public function getHayPuntajeManualProperty(): bool
    {
        return collect($this->preguntas)->contains(fn (array $p) => (bool) ($p['puntaje_manual'] ?? false));
    }

    public function updatedPuntajeTotal(): void
    {
        $this->repartirSiCorresponde();
    }

    /**
     * "El puntaje total manda": al agregar o quitar preguntas (o cambiar el
     * total) se reparte solo, salvo que haya puntajes puestos a mano o el quiz
     * ya tenga intentos. Si el total no es válido o no alcanza el mínimo por
     * pregunta, no se toca nada (queda visible la suma frente al total).
     */
    protected function repartirSiCorresponde(): void
    {
        if ($this->estructuraBloqueada || $this->hayPuntajeManual || $this->preguntas === []) {
            return;
        }

        if (! is_numeric($this->puntaje_total) || (float) $this->puntaje_total <= 0 || (float) $this->puntaje_total > 20) {
            return;
        }

        $this->aplicarReparto();
    }

    /**
     * Total ÷ preguntas con 2 decimales; el ajuste del redondeo va a la última.
     * Devuelve false (sin cambiar nada) si alguna quedaría bajo el mínimo.
     */
    protected function aplicarReparto(): bool
    {
        $reparto = $this->repartoIgual();

        if ($reparto === null) {
            return false;
        }

        foreach (array_keys($this->preguntas) as $posicion => $indice) {
            $this->preguntas[$indice]['puntaje'] = $reparto[$posicion];
        }

        return true;
    }

    /**
     * @return list<float>|null
     */
    protected function repartoIgual(): ?array
    {
        $cantidad = count($this->preguntas);

        if ($cantidad === 0 || ! is_numeric($this->puntaje_total)) {
            return null;
        }

        $total = round((float) $this->puntaje_total, 2);
        $parte = round($total / $cantidad, 2);
        $ultima = round($total - $parte * ($cantidad - 1), 2);

        if (min($parte, $ultima) < self::PUNTAJE_MINIMO_PREGUNTA) {
            return null;
        }

        return [...array_fill(0, $cantidad - 1, $parte), $ultima];
    }

    protected function coincideConRepartoIgual(): bool
    {
        $reparto = $this->repartoIgual();

        return $reparto !== null && collect($this->preguntas)->values()
            ->every(fn (array $p, int $i) => abs((float) $p['puntaje'] - $reparto[$i]) < 0.005);
    }

    public function getEstudiantesAfectadosProperty(): int
    {
        return $this->quiz?->estudiantesConIntentos() ?? 0;
    }

    public function agregarPregunta(): void
    {
        abort_if($this->estructuraBloqueada, 403);

        $this->preguntas[] = $this->normalizarPregunta([]);
        $this->repartirSiCorresponde();
    }

    public function eliminarPregunta(int $indice): void
    {
        abort_if($this->estructuraBloqueada, 403);

        unset($this->preguntas[$indice]);
        $this->preguntas = array_values($this->preguntas);
        $this->repartirSiCorresponde();
    }

    /** Una pregunta copiada del banco pasa de solo lectura a editable (el banco no cambia). */
    public function editarPreguntaDelBanco(int $indice): void
    {
        abort_if($this->estructuraBloqueada, 403);
        abort_unless(isset($this->preguntas[$indice]), 404);

        $this->preguntas[$indice]['editando'] = true;
    }

    /**
     * Abre el panel en la unidad actual que el profesor eligió en el Banco
     * (la vista la lee del navegador); si no hay una válida, la primera del curso.
     */
    public function abrirBanco(?string $unidadGuardada = null): void
    {
        abort_if($this->estructuraBloqueada, 403);

        $unidades = BancoPregunta::unidadesDe($this->curso);
        $this->unidadActualBanco = in_array($unidadGuardada, $unidades, true) ? $unidadGuardada : ($unidades[0] ?? '');
        $this->unidadBanco = $this->unidadActualBanco;
        $this->mostrarBanco = true;
    }

    public function cerrarBanco(): void
    {
        $this->reset(['mostrarBanco', 'unidadBanco', 'temaBanco', 'busquedaBanco', 'seleccionBanco', 'incluirAnteriores', 'ordenAnteriores', 'cantidadMasFalladas']);
    }

    /** Lo ya marcado se conserva al cambiar de unidad o de tema: se agrega todo junto. */
    public function updatedUnidadBanco(): void
    {
        $this->reset(['temaBanco', 'busquedaBanco']);
    }

    public function updatedTemaBanco(): void
    {
        $this->reset(['busquedaBanco']);
    }

    /** Marca las N preguntas archivadas visibles con más fallos (solo las que tienen datos). */
    public function seleccionarMasFalladas(): void
    {
        abort_if($this->estructuraBloqueada, 403);

        $this->validateOnly('cantidadMasFalladas', ['cantidadMasFalladas' => ['required', 'integer', 'min:1', 'max:50']], [
            'cantidadMasFalladas.*' => 'Escribe un número entre 1 y 50.',
        ]);

        $masFalladas = $this->preguntasArchivadas()
            ->filter(fn ($item) => $item->fallos['porcentaje'] !== null)
            ->sortByDesc(fn ($item) => [$item->fallos['porcentaje'], $item->fallos['respuestas']])
            ->take((int) $this->cantidadMasFalladas)
            ->map(fn ($item) => (string) $item->id);

        $this->seleccionBanco = array_values(array_unique([...array_map('strval', $this->seleccionBanco), ...$masFalladas]));
    }

    public function agregarOpcion(int $indicePregunta): void
    {
        abort_if($this->estructuraBloqueada, 403);

        if (count($this->preguntas[$indicePregunta]['opciones']) < 8) {
            $this->preguntas[$indicePregunta]['opciones'][] = '';
        }
    }

    public function eliminarOpcion(int $indicePregunta, int $indiceOpcion): void
    {
        abort_if($this->estructuraBloqueada, 403);

        $pregunta = &$this->preguntas[$indicePregunta];

        if (count($pregunta['opciones']) <= 2) {
            return;
        }

        unset($pregunta['opciones'][$indiceOpcion]);
        $pregunta['opciones'] = array_values($pregunta['opciones']);

        $ajustar = fn (int $i) => $i > $indiceOpcion ? $i - 1 : $i;
        $pregunta['respuestas_correctas'] = array_values(array_map($ajustar, array_filter(
            array_map('intval', $pregunta['respuestas_correctas']),
            fn (int $i) => $i !== $indiceOpcion
        ))) ?: [0];
        $pregunta['respuesta_correcta'] = min($ajustar((int) $pregunta['respuesta_correcta']), count($pregunta['opciones']) - 1);
    }

    public function updatedPreguntas(mixed $valor, ?string $clave = null): void
    {
        // Si se reemplaza el arreglo completo, se completa el formato de cada pregunta.
        if ($clave === null) {
            $this->preguntas = array_map(fn (array $p) => $this->normalizarPregunta($p), array_values($this->preguntas));

            return;
        }

        // Un puntaje cambiado a mano ya no se pisa al repartir solo.
        if (str_ends_with($clave, '.puntaje')) {
            $this->preguntas[(int) explode('.', $clave)[0]]['puntaje_manual'] = true;
        }

        // Al cambiar el tipo, se ajustan las opciones de verdadero/falso.
        if ($clave !== null && str_ends_with($clave, '.tipo')) {
            $indice = (int) explode('.', $clave)[0];

            if ($valor === 'verdadero_falso') {
                $this->preguntas[$indice]['opciones'] = ['Verdadero', 'Falso'];
                $this->preguntas[$indice]['respuesta_correcta'] = 0;
                $this->preguntas[$indice]['respuestas_correctas'] = [0];
            }
        }
    }

    public function agregarDesdeBanco(): void
    {
        abort_if($this->estructuraBloqueada, 403);
        Gate::authorize('gestionar', $this->curso);

        $seleccion = $this->curso->bancoPreguntasCurso()->whereIn('id', $this->seleccionBanco)->orderBy('id')->get();

        // Se COPIAN al quiz (sin vínculo con el banco); se guarda el contenido original para saber si luego se editan.
        foreach ($seleccion as $item) {
            $pregunta = $this->normalizarPregunta([...$item->aPreguntaDeQuiz(), 'banco_pregunta_id' => $item->id, 'banco_unidad' => $item->unidad, 'banco_tema' => $item->tema]);
            $this->preguntas[] = [...$pregunta, 'original' => $this->contenidoCorregible($pregunta)];
        }

        $this->repartirSiCorresponde();
        $this->cerrarBanco();
        session()->flash('estado', $seleccion->count() === 1
            ? '1 pregunta agregada desde el banco.'
            : $seleccion->count().' preguntas agregadas desde el banco.');
    }

    public function guardar(): void
    {
        $this->autorizar();
        $this->validarFormulario();

        // RF-51: con intentos registrados y cambios que afectan la corrección,
        // se pregunta explícitamente si recalcular.
        if ($this->quiz && $this->estructuraBloqueada && $this->cambiaLaCorreccion()) {
            $this->mostrarModalRecalculo = true;

            return;
        }

        $this->persistir(recalcular: false);
    }

    public function confirmarGuardado(bool $recalcular): void
    {
        $this->autorizar();
        $this->validarFormulario();
        $this->mostrarModalRecalculo = false;
        $this->persistir($recalcular);
    }

    protected function persistir(bool $recalcular): void
    {
        $preguntas = array_map(fn (array $p) => $this->prepararParaGuardar($p), $this->preguntas);

        $datos = [
            'tema' => $this->tema,
            'modulo_id' => $this->modulo_id,
            'preguntas' => $preguntas,
            'configuracion' => [
                'tiempo_limite_min' => $this->tiempo_limite_min ?: null,
                'intentos' => $this->intentos,
                'aleatorizar' => $this->aleatorizar,
                'mostrar_retroalimentacion' => $this->mostrar_retroalimentacion,
                'puntaje_total' => round((float) $this->puntaje_total, 2),
            ],
            'fecha_inicio' => $this->fecha_inicio ?: null,
            'fecha_limite' => $this->fecha_limite ?: null,
            'visible' => $this->estado_contenido !== 'borrador',
            'estado_contenido' => $this->estado_contenido === 'borrador' ? 'publicado' : $this->estado_contenido,
            'publicar_en' => $this->estado_contenido === 'programado' ? $this->publicar_en : null,
        ];

        $eraVisible = $this->quiz?->esVisibleParaEstudiante() ?? false;
        $fechaLimiteAnterior = $this->quiz?->fecha_limite?->copy();
        $cambios = collect();

        DB::transaction(function () use ($datos, $recalcular, &$cambios) {
            if ($this->quiz) {
                $this->quiz->update($datos);
            } else {
                $this->quiz = $this->curso->quizzes()->create($datos);
            }

            if ($recalcular) {
                $cambios = $this->quiz->recalcularResultados();

                foreach ($cambios as $cambio) {
                    BitacoraAuditoria::registrarCambioNota(
                        ejecutor: auth()->user(),
                        curso: $this->curso,
                        estudiante: $cambio['resultado']->estudiante,
                        entidad: 'resultado_quiz',
                        entidadId: $cambio['resultado']->id,
                        valorAnterior: $cambio['anterior'],
                        valorNuevo: $cambio['nuevo'],
                        motivo: "Recálculo tras corregir preguntas del quiz \"{$this->quiz->tema}\"."
                    );
                }
            }
        });

        // RF-53: aviso a cada estudiante cuya nota cambió (una vez por estudiante, con su mejor intento).
        $cambios->groupBy(fn ($c) => $c['resultado']->estudiante_id)->each(function ($cambiosDelEstudiante) {
            $estudiante = $cambiosDelEstudiante->first()['resultado']->estudiante;
            $mejorAntes = $cambiosDelEstudiante->max('anterior');
            $mejorAhora = (float) $this->quiz->resultadoDe($estudiante)?->puntaje;
            $estudiante->notify(new NotaQuizModificada($this->quiz, (float) $mejorAntes, $mejorAhora));
        });

        $estudiantes = $this->curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter();
        $quizActual = $this->quiz->fresh();

        if (! $eraVisible && $quizActual->esVisibleParaEstudiante()) {
            Notification::send($estudiantes, new NuevoQuizPublicado($this->quiz));
        } elseif ($eraVisible && $quizActual->esVisibleParaEstudiante()
            && $fechaLimiteAnterior?->format('Y-m-d H:i') !== $quizActual->fecha_limite?->format('Y-m-d H:i')) {
            // RF-84: cambio de fecha de un quiz que los estudiantes ya ven.
            Notification::send($estudiantes, new CambioFechaQuiz($quizActual, $fechaLimiteAnterior));
        }

        session()->flash('estado', $recalcular
            ? "Quiz guardado. Se recalcularon {$cambios->count()} intento(s) y se notificó a los estudiantes afectados."
            : 'Quiz guardado.');

        $this->redirect($this->rutaVolver(), navigate: true);
    }

    public function rutaVolver(): string
    {
        return $this->desdeActividades
            ? route('cursos.actividades', [$this->curso, 'tipo' => 'quizzes'])
            : route('cursos.quizzes.index', $this->curso);
    }

    protected function autorizar(): void
    {
        $this->quiz
            ? Gate::authorize('gestionar', $this->quiz)
            : Gate::authorize('crear', [Quiz::class, $this->curso]);
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está archivado y no admite cambios.');
    }

    protected function validarFormulario(): void
    {
        $this->preguntas = array_map(fn (array $p) => $this->normalizarPregunta($p), array_values($this->preguntas));

        foreach ($this->preguntas as $i => $pregunta) {
            $this->preguntas[$i]['respuestas_correctas'] = array_values(array_unique(array_map('intval', (array) ($pregunta['respuestas_correctas'] ?? []))));
        }

        $reglas = [
            'tema' => ['required', 'string', 'max:255'],
            'modulo_id' => ['required', Rule::exists('modulos', 'id')->where('curso_id', $this->curso->id)],
            'preguntas' => ['required', 'array', 'min:1', 'max:100'],
            'preguntas.*.enunciado' => ['required', 'string', 'max:1000'],
            'preguntas.*.tipo' => ['required', Rule::in(array_keys(Quiz::TIPOS_PREGUNTA))],
            'preguntas.*.opciones' => ['required', 'array', 'min:2', 'max:8'],
            'preguntas.*.opciones.*' => ['required', 'string', 'max:255'],
            'preguntas.*.puntaje' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'preguntas.*.retroalimentacion' => ['nullable', 'string', 'max:1000'],
            'tiempo_limite_min' => ['nullable', 'integer', 'min:1', 'max:600'],
            'intentos' => ['required', 'integer', 'min:1', 'max:10'],
            'puntaje_total' => self::REGLAS_PUNTAJE_TOTAL,
            'aleatorizar' => ['boolean'],
            'mostrar_retroalimentacion' => ['boolean'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_limite' => array_filter(['nullable', 'date', $this->fecha_inicio !== '' ? 'after:fecha_inicio' : null]),
            'estado_contenido' => ['required', Rule::in(['borrador', 'publicado', 'programado'])],
            'publicar_en' => ['nullable', 'required_if:estado_contenido,programado', 'date'],
        ];

        foreach ($this->preguntas as $i => $pregunta) {
            $maximo = count($pregunta['opciones'] ?? []) - 1;

            if ($pregunta['tipo'] === 'opcion_multiple') {
                $reglas["preguntas.$i.respuestas_correctas"] = ['required', 'array', 'min:1'];
                $reglas["preguntas.$i.respuestas_correctas.*"] = ['integer', 'min:0', "max:$maximo"];
            } else {
                $reglas["preguntas.$i.respuesta_correcta"] = ['required', 'integer', 'min:0', "max:$maximo"];
            }
        }

        $this->validate($reglas, [
            ...self::MENSAJES_PUNTAJE_TOTAL,
            'preguntas.required' => 'Agrega al menos una pregunta al quiz: desde el banco de preguntas o escribiendo una nueva.',
            'preguntas.min' => 'Agrega al menos una pregunta al quiz: desde el banco de preguntas o escribiendo una nueva.',
            'preguntas.*.enunciado.required' => 'Escribe el enunciado de la pregunta.',
            'preguntas.*.opciones.*.required' => 'Completa el texto de todas las opciones.',
            'preguntas.*.respuestas_correctas.required' => 'Marca al menos una respuesta correcta.',
            'modulo_id.required' => 'Selecciona la semana del quiz.',
            'fecha_limite.after' => 'La fecha límite debe ser posterior a la de inicio.',
            'publicar_en.required_if' => 'Indica cuándo se publicará automáticamente.',
        ]);
    }

    protected function cambiaLaCorreccion(): bool
    {
        $anteriores = $this->quiz->preguntas ?? [];

        foreach ($this->preguntas as $i => $pregunta) {
            $nueva = $this->prepararParaGuardar($pregunta);
            $anterior = $anteriores[$i] ?? [];

            if (Quiz::correctasDe($nueva) !== Quiz::correctasDe($anterior)
                || Quiz::puntajeDe($nueva) !== Quiz::puntajeDe($anterior)
                || ($nueva['tipo'] ?? 'opcion_unica') !== ($anterior['tipo'] ?? 'opcion_unica')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $pregunta
     * @return array<string, mixed>
     */
    protected function normalizarPregunta(array $pregunta): array
    {
        $correcta = (int) ($pregunta['respuesta_correcta'] ?? 0);

        return [
            'enunciado' => $pregunta['enunciado'] ?? '',
            'tipo' => $pregunta['tipo'] ?? 'opcion_unica',
            'opciones' => array_values($pregunta['opciones'] ?? ['', '']),
            'respuesta_correcta' => $correcta,
            'respuestas_correctas' => array_map('intval', (array) ($pregunta['respuestas_correctas'] ?? [$correcta])),
            'puntaje' => $pregunta['puntaje'] ?? 1,
            'retroalimentacion' => $pregunta['retroalimentacion'] ?? '',
            // Copiada del banco: unidad y tema de origen y si se editó después (se guardan en el JSON del quiz).
            'banco_tema' => $pregunta['banco_tema'] ?? null,
            'banco_pregunta_id' => isset($pregunta['banco_pregunta_id']) ? (int) $pregunta['banco_pregunta_id'] : null,
            'banco_unidad' => $pregunta['banco_unidad'] ?? null,
            'editada' => (bool) ($pregunta['editada'] ?? false),
            // Solo del formulario (no se guardan).
            'original' => $pregunta['original'] ?? null,
            'editando' => (bool) ($pregunta['editando'] ?? false),
            'puntaje_manual' => (bool) ($pregunta['puntaje_manual'] ?? false),
        ];
    }

    /**
     * Lo que define la pregunta y su corrección, sin el puntaje (que decide el
     * quiz): sirve para saber si una pregunta del banco se editó.
     *
     * @param  array<string, mixed>  $pregunta
     * @return array<string, mixed>
     */
    protected function contenidoCorregible(array $pregunta): array
    {
        $correctas = ($pregunta['tipo'] ?? 'opcion_unica') === 'opcion_multiple'
            ? array_values(array_unique(array_map('intval', (array) ($pregunta['respuestas_correctas'] ?? []))))
            : [(int) ($pregunta['respuesta_correcta'] ?? 0)];
        sort($correctas);

        return [
            'enunciado' => trim((string) ($pregunta['enunciado'] ?? '')),
            'tipo' => $pregunta['tipo'] ?? 'opcion_unica',
            'opciones' => array_map(fn ($o) => trim((string) $o), array_values($pregunta['opciones'] ?? [])),
            'correctas' => $correctas,
            'retroalimentacion' => trim((string) ($pregunta['retroalimentacion'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $pregunta
     */
    protected function estaEditada(array $pregunta): bool
    {
        if (($pregunta['banco_tema'] ?? null) === null) {
            return false;
        }

        return is_array($pregunta['original'] ?? null)
            ? $this->contenidoCorregible($pregunta) !== $pregunta['original']
            : (bool) ($pregunta['editada'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $pregunta
     * @return array<string, mixed>
     */
    protected function prepararParaGuardar(array $pregunta): array
    {
        $multiple = $pregunta['tipo'] === 'opcion_multiple';
        $correctas = $multiple
            ? array_values(array_unique(array_map('intval', (array) $pregunta['respuestas_correctas'])))
            : [(int) $pregunta['respuesta_correcta']];
        sort($correctas);

        $guardada = [
            'enunciado' => $pregunta['enunciado'],
            'tipo' => $pregunta['tipo'],
            'opciones' => array_values($pregunta['opciones']),
            'respuesta_correcta' => $correctas[0] ?? 0,
            'respuestas_correctas' => $correctas,
            'puntaje' => (float) $pregunta['puntaje'],
            'retroalimentacion' => $pregunta['retroalimentacion'] ?: null,
        ];

        // Solo las copiadas del banco llevan su origen; las escritas a mano quedan igual que antes.
        if (($pregunta['banco_tema'] ?? null) !== null) {
            $guardada['banco_pregunta_id'] = $pregunta['banco_pregunta_id'] ?? null;
            $guardada['banco_unidad'] = $pregunta['banco_unidad'] ?? null;
            $guardada['banco_tema'] = $pregunta['banco_tema'];
            $guardada['editada'] = $this->estaEditada($pregunta);
        }

        return $guardada;
    }

    /**
     * Opciones del paso "Unidad": la actual y las posteriores (las archivadas
     * se eligen con "Incluir unidades anteriores") y "Sin unidad" si hay
     * preguntas sin una unidad válida del curso.
     *
     * @return array<string, string> valor => etiqueta
     */
    protected function unidadesDelBanco(): array
    {
        $validas = BancoPregunta::unidadesDe($this->curso);
        $archivadas = BancoPregunta::unidadesArchivadas($this->curso, $this->unidadActualBanco);
        $presentes = $this->curso->bancoPreguntasCurso()->distinct()->pluck('unidad')->all();

        $unidades = array_filter($validas, fn ($unidad) => ! in_array($unidad, $archivadas, true)
            && ($unidad === $this->unidadActualBanco || in_array($unidad, $presentes, true)));
        $opciones = collect($unidades)->mapWithKeys(fn ($u) => [$u => $u === $this->unidadActualBanco ? "{$u} (actual)" : $u])->all();

        if (collect($presentes)->contains(fn ($unidad) => ! in_array($unidad, $validas, true))) {
            $opciones[BancoPregunta::SIN_UNIDAD] = 'Sin unidad';
        }

        return $opciones;
    }

    protected function bancoDeLaUnidad(): HasMany
    {
        return $this->unidadBanco === BancoPregunta::SIN_UNIDAD
            ? $this->curso->bancoPreguntasCurso()->where(fn ($q) => $q->whereNull('unidad')->orWhereNotIn('unidad', BancoPregunta::unidadesDe($this->curso)))
            : $this->curso->bancoPreguntasCurso()->where('unidad', $this->unidadBanco);
    }

    /**
     * Preguntas de las unidades archivadas (filtradas por el buscador), cada
     * una con su estadística de fallos, en el orden elegido.
     *
     * @return Collection<int, BancoPregunta>
     */
    protected function preguntasArchivadas(): Collection
    {
        $archivadas = BancoPregunta::unidadesArchivadas($this->curso, $this->unidadActualBanco);

        if (! $this->incluirAnteriores || $archivadas === []) {
            return collect();
        }

        $fallos = app(EstadisticaFallosBanco::class)->paraCurso($this->curso);
        $posicion = array_flip($archivadas);

        $preguntas = $this->curso->bancoPreguntasCurso()
            ->whereIn('unidad', $archivadas)
            ->when(trim($this->busquedaBanco) !== '', fn ($q) => $q->where('pregunta', 'like', '%'.trim($this->busquedaBanco).'%'))
            ->get()
            ->each(fn (BancoPregunta $item) => $item->fallos = $fallos[$item->id] ?? ['respuestas' => 0, 'incorrectas' => 0, 'porcentaje' => null]);

        // Más falladas primero (las "Sin datos" al final); o por unidad y tema.
        return $this->ordenAnteriores === 'tema'
            ? $preguntas->sortBy(fn ($item) => [$posicion[$item->unidad], mb_strtolower($item->tema), $item->id])->values()
            : $preguntas->sortBy(fn ($item) => [
                $item->fallos['porcentaje'] === null ? 1 : 0,
                -($item->fallos['porcentaje'] ?? 0),
                -$item->fallos['respuestas'],
                $item->id,
            ])->values();
    }

    public function render()
    {
        return view('livewire.quizzes.formulario', [
            'modulos' => $this->curso->modulos()->orderBy('orden')->get(),
            'tipos' => Quiz::TIPOS_PREGUNTA,
            'estaEditada' => fn (array $pregunta) => $this->estaEditada($pregunta),
            'hayBanco' => $this->curso->bancoPreguntasCurso()->exists(),
            'unidadesBanco' => $this->mostrarBanco ? $this->unidadesDelBanco() : [],
            'temasBanco' => $this->mostrarBanco && $this->unidadBanco !== ''
                ? $this->bancoDeLaUnidad()->distinct()->orderBy('tema')->pluck('tema')
                : collect(),
            'preguntasBanco' => $this->mostrarBanco && $this->unidadBanco !== '' && $this->temaBanco !== ''
                ? $this->bancoDeLaUnidad()
                    ->where('tema', $this->temaBanco)
                    ->when(trim($this->busquedaBanco) !== '', fn ($q) => $q->where('pregunta', 'like', '%'.trim($this->busquedaBanco).'%'))
                    ->orderBy('id')
                    ->get()
                : collect(),
            'archivadasBanco' => $this->mostrarBanco ? BancoPregunta::unidadesArchivadas($this->curso, $this->unidadActualBanco) : [],
            'preguntasArchivadas' => $this->mostrarBanco ? $this->preguntasArchivadas() : collect(),
        ])->title(($this->quiz ? 'Editar quiz' : 'Nuevo quiz').' — LearnUp');
    }
}
