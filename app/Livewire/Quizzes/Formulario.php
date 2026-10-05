<?php

namespace App\Livewire\Quizzes;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Quiz;
use App\Notifications\CambioFechaQuiz;
use App\Notifications\NotaQuizModificada;
use App\Notifications\NuevoQuizPublicado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
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

    public ?Quiz $quiz = null;

    public string $tema = '';

    public ?int $modulo_id = null;

    /** @var array<int, array<string, mixed>> */
    public array $preguntas = [];

    public ?int $tiempo_limite_min = null;

    public int $intentos = 1;

    public bool $aleatorizar = false;

    public bool $mostrar_retroalimentacion = true;

    public string $fecha_inicio = '';

    public string $fecha_limite = '';

    public string $estado_contenido = 'publicado';

    public string $publicar_en = '';

    // Banco de preguntas (RF-47)
    public string $temaBanco = '';

    /** @var array<int, int|string> */
    public array $seleccionBanco = [];

    // Recálculo (RF-51)
    public bool $mostrarModalRecalculo = false;

    public function mount(Curso $curso, ?Quiz $quiz = null): void
    {
        $this->curso = $curso;

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
            $this->fecha_inicio = $quiz->fecha_inicio?->format('Y-m-d\TH:i') ?? '';
            $this->fecha_limite = $quiz->fecha_limite?->format('Y-m-d\TH:i') ?? '';
            $this->estado_contenido = $quiz->visible ? ($quiz->estado_contenido ?? 'publicado') : 'borrador';
            $this->publicar_en = $quiz->publicar_en?->format('Y-m-d\TH:i') ?? '';
        } else {
            Gate::authorize('crear', [Quiz::class, $curso]);
            $this->agregarPregunta();
        }

        abort_if($curso->estaFinalizado(), 403, 'El curso está archivado y no admite cambios.');
    }

    /** Con intentos registrados no se agregan ni quitan preguntas u opciones (el recálculo usa sus posiciones). */
    public function getEstructuraBloqueadaProperty(): bool
    {
        return (bool) $this->quiz?->resultados()->exists();
    }

    public function getEstudiantesAfectadosProperty(): int
    {
        return $this->quiz?->estudiantesConIntentos() ?? 0;
    }

    public function agregarPregunta(): void
    {
        abort_if($this->estructuraBloqueada, 403);

        $this->preguntas[] = $this->normalizarPregunta([]);
    }

    public function eliminarPregunta(int $indice): void
    {
        abort_if($this->estructuraBloqueada, 403);

        unset($this->preguntas[$indice]);
        $this->preguntas = array_values($this->preguntas);
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

        $seleccion = $this->curso->bancoPreguntasCurso()->whereIn('id', $this->seleccionBanco)->get();

        foreach ($seleccion as $item) {
            $this->preguntas[] = $this->normalizarPregunta($item->aPreguntaDeQuiz());
        }

        $this->seleccionBanco = [];
        session()->flash('estado', $seleccion->count().' pregunta(s) agregada(s) desde el banco.');
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

        $this->redirect(route('cursos.quizzes.index', $this->curso), navigate: true);
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
        ];
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

        return [
            'enunciado' => $pregunta['enunciado'],
            'tipo' => $pregunta['tipo'],
            'opciones' => array_values($pregunta['opciones']),
            'respuesta_correcta' => $correctas[0] ?? 0,
            'respuestas_correctas' => $correctas,
            'puntaje' => (float) $pregunta['puntaje'],
            'retroalimentacion' => $pregunta['retroalimentacion'] ?: null,
        ];
    }

    public function render()
    {
        return view('livewire.quizzes.formulario', [
            'modulos' => $this->curso->modulos()->orderBy('orden')->get(),
            'tipos' => Quiz::TIPOS_PREGUNTA,
            'temasBanco' => $this->curso->bancoPreguntasCurso()->distinct()->orderBy('tema')->pluck('tema'),
            'preguntasBanco' => $this->temaBanco !== ''
                ? $this->curso->bancoPreguntasCurso()->where('tema', $this->temaBanco)->orderBy('id')->get()
                : collect(),
        ])->title(($this->quiz ? 'Editar quiz' : 'Nuevo quiz').' — LearnUp');
    }
}
