<?php

namespace App\Livewire\Compartido\Quizzes;

use App\Models\Quiz;
use App\Models\ResultadoQuiz;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * ERS RF-44 a RF-46: el estudiante rinde un quiz.
 * - Opción única, opción múltiple y verdadero/falso.
 * - Intentos, tiempo límite (verificado en el servidor), orden aleatorio y
 *   fechas de apertura/cierre según la configuración del quiz (RF-45).
 * - Resultado inmediato y retroalimentación si el quiz la muestra (RF-46).
 * Cuenta el mejor intento. El Profesor ve una vista previa sin poder enviar.
 */
#[Layout('layouts.app')]
class Resolver extends Component
{
    /** Margen para latencia de red al verificar el tiempo límite. */
    public const MARGEN_SEGUNDOS = 30;

    public Quiz $quiz;

    /** @var array<int, int|string|array<int, int|string>|null> */
    public array $respuestas = [];

    /** @var array<int, int> Orden en que se muestran las preguntas. */
    #[Locked]
    public array $orden = [];

    #[Locked]
    public ?int $iniciadoEn = null;

    #[Locked]
    public bool $intentando = false;

    public function mount(Quiz $quiz): void
    {
        Gate::authorize('ver', $quiz);
        $this->quiz = $quiz;
        $this->orden = array_keys($quiz->preguntas ?? []);

        // Primer intento: el formulario se muestra de inmediato, como antes.
        if (! $this->resultado && Gate::allows('responder', $quiz)) {
            $this->iniciarIntento();
        } elseif (! auth()->user()->hasRole('estudiante')) {
            $this->respuestas = array_fill(0, count($quiz->preguntas ?? []), null);
        }
    }

    public function getResultadoProperty(): ?ResultadoQuiz
    {
        return $this->quiz->resultadoDe(auth()->user());
    }

    public function getPuedeResponderProperty(): bool
    {
        return Gate::allows('responder', $this->quiz);
    }

    public function getSegundosRestantesProperty(): ?int
    {
        $limite = $this->quiz->tiempoLimiteMinutos();

        if (! $limite || ! $this->iniciadoEn) {
            return null;
        }

        return max(0, $this->iniciadoEn + $limite * 60 - now()->timestamp);
    }

    public function iniciarIntento(): void
    {
        Gate::authorize('responder', $this->quiz);

        $preguntas = $this->quiz->preguntas ?? [];
        $this->respuestas = array_map(
            fn (array $pregunta) => ($pregunta['tipo'] ?? 'opcion_unica') === 'opcion_multiple' ? [] : null,
            $preguntas
        );

        $this->orden = array_keys($preguntas);

        if ($this->quiz->aleatoriza()) {
            shuffle($this->orden);
        }

        $this->iniciadoEn = now()->timestamp;
        $this->intentando = true;
        $this->resetErrorBag();
    }

    public function enviar(): void
    {
        Gate::authorize('responder', $this->quiz);
        abort_unless($this->intentando && $this->iniciadoEn, 403);

        $limite = $this->quiz->tiempoLimiteMinutos();
        $transcurrido = now()->timestamp - $this->iniciadoEn;

        if ($limite && $transcurrido > $limite * 60 + self::MARGEN_SEGUNDOS) {
            $this->intentando = false;
            $this->addError('respuestas', 'Se agotó el tiempo del intento. Tus respuestas no se registraron.');

            return;
        }

        $preguntas = $this->quiz->preguntas ?? [];
        $reglas = ['respuestas' => ['required', 'array', 'size:'.count($preguntas)]];
        $mensajes = [];

        foreach ($preguntas as $indice => $pregunta) {
            $maximo = count($pregunta['opciones'] ?? []) - 1;

            if (($pregunta['tipo'] ?? 'opcion_unica') === 'opcion_multiple') {
                $reglas["respuestas.$indice"] = ['required', 'array', 'min:1'];
                $reglas["respuestas.$indice.*"] = ['integer', 'min:0', "max:$maximo"];
            } else {
                $reglas["respuestas.$indice"] = ['required', 'integer', 'min:0', "max:$maximo"];
            }

            $mensajes["respuestas.$indice.required"] = 'Responde esta pregunta antes de enviar.';
            $mensajes["respuestas.$indice.min"] = 'Marca al menos una opción.';
        }

        $this->validate($reglas, $mensajes);

        $this->quiz->resultados()->create([
            'estudiante_id' => auth()->id(),
            'intento' => $this->quiz->intentosUsados(auth()->user()) + 1,
            'respuestas' => $this->respuestas,
            'puntaje' => $this->quiz->calcularPuntaje($this->respuestas),
            'tiempo_usado_seg' => $transcurrido,
        ]);

        $this->quiz->unsetRelation('resultados');
        $this->intentando = false;
        $this->iniciadoEn = null;
    }

    public function render()
    {
        return view('livewire.compartido.quizzes.resolver', [
            'intentosUsados' => auth()->user()->hasRole('estudiante') ? $this->quiz->intentosUsados(auth()->user()) : 0,
        ])->title("{$this->quiz->tema} — LearnUp");
    }
}
