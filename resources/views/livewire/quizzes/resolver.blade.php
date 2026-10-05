@php
    // Compartido entre roles; Estudiante usa la paleta fría nueva y
    // Profesor/Administrador conservan la anterior — ver ARQUITECTURA.md §
    // nueva paleta de color.
    $paletaFria = auth()->user()->hasRole('estudiante');
    $tarjeta = 'bg-white rounded-xl border p-6 '.($paletaFria ? 'dark:bg-azul-superficie-oscura border-celeste dark:border-azul-borde-oscuro' : 'dark:bg-stone-800 border-arena-medio dark:border-stone-700');
    $textoPrincipal = $paletaFria ? 'text-texto dark:text-slate-100' : 'text-stone-800 dark:text-stone-100';
    $textoSecundario = $paletaFria ? 'text-slate-600 dark:text-slate-300' : 'text-stone-600 dark:text-stone-300';
    $acento = $paletaFria ? 'text-azul-oscuro dark:text-azul-fondo' : 'text-menta-oscuro dark:text-menta-base';
@endphp

<div class="py-8">
    {{-- Ancho de contenedor estandarizado (max-w-6xl) para todas las vistas del estudiante. --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <a href="{{ route('cursos.quizzes.index', $quiz->curso) }}" wire:navigate
                class="text-sm {{ $acento }} hover:underline">&larr; Quizzes</a>
            <h1 class="text-2xl font-bold {{ $paletaFria ? 'text-texto dark:text-slate-100' : 'text-stone-900 dark:text-stone-100' }}">{{ $quiz->tema }}</h1>
            <p class="text-xs text-slate-400 mt-1">
                {{ count($quiz->preguntas ?? []) }} preguntas
                · {{ $quiz->intentosPermitidos() }} {{ $quiz->intentosPermitidos() === 1 ? 'intento' : 'intentos' }}
                @if ($quiz->tiempoLimiteMinutos()) · {{ $quiz->tiempoLimiteMinutos() }} min por intento @endif
                @if ($quiz->fecha_limite) · disponible hasta el {{ $quiz->fecha_limite->formatoFechaHora() }} @endif
            </p>
        </div>

        @if (! $paletaFria)
            <div class="rounded-lg bg-arena-claro/60 dark:bg-stone-800 px-4 py-3 text-sm text-stone-600 dark:text-stone-300 flex items-center gap-2">
                <x-icono nombre="ojo" class="h-4 w-4" /> Vista previa del profesor: las respuestas no se registran.
            </div>
        @endif

        @if ($this->resultado && ! $intentando)
            <div class="{{ $tarjeta }} text-center">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $quiz->intentosPermitidos() > 1 ? 'Tu mejor resultado' : 'Ya resolviste este quiz' }}</p>
                <p class="text-4xl font-bold {{ $acento }} mt-2">{{ $this->resultado->puntaje }} / 20</p>
                <p class="text-xs text-slate-400 mt-2">Intentos usados: {{ $intentosUsados }} de {{ $quiz->intentosPermitidos() }}</p>
                @if ($this->puedeResponder)
                    <div class="mt-4">
                        <x-primary-button type="button" wire:click="iniciarIntento">Nuevo intento</x-primary-button>
                    </div>
                @endif
            </div>

            @if ($quiz->muestraRetroalimentacion())
                @foreach ($quiz->preguntas as $i => $pregunta)
                    @php
                        $respuesta = $this->resultado->respuestas[$i] ?? null;
                        $correcta = \App\Models\Quiz::respuestaEsCorrecta($pregunta, $respuesta);
                        $elegidas = array_map('intval', (array) $respuesta);
                        $indicesCorrectos = \App\Models\Quiz::correctasDe($pregunta);
                    @endphp
                    <div class="{{ $tarjeta }}" wire:key="retro-{{ $i }}">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <p class="font-medium {{ $textoPrincipal }}">{{ $i + 1 }}. {{ $pregunta['enunciado'] }}</p>
                            <span class="shrink-0 inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $correcta ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' }}">
                                <x-icono :nombre="$correcta ? 'check' : 'cerrar'" class="h-3.5 w-3.5" /> {{ $correcta ? 'Correcta' : 'Incorrecta' }}
                            </span>
                        </div>
                        <ul class="space-y-1 text-sm">
                            @foreach ($pregunta['opciones'] as $j => $opcion)
                                <li class="flex items-center gap-2 {{ in_array($j, $indicesCorrectos, true) ? 'font-semibold text-emerald-700 dark:text-emerald-300' : $textoSecundario }}">
                                    <x-icono :nombre="in_array($j, $elegidas, true) ? 'check-circulo' : 'x-circulo'" class="h-4 w-4 {{ in_array($j, $elegidas, true) ? '' : 'opacity-20' }}" />
                                    {{ $opcion }}
                                </li>
                            @endforeach
                        </ul>
                        @if (! empty($pregunta['retroalimentacion']))
                            <p class="mt-3 text-sm {{ $textoSecundario }}"><span class="font-semibold">Retroalimentación:</span> {{ $pregunta['retroalimentacion'] }}</p>
                        @endif
                    </div>
                @endforeach
            @endif
        @elseif ($paletaFria && ! $intentando)
            <div class="{{ $tarjeta }} text-center">
                <p class="text-sm {{ $textoSecundario }}">
                    @if (! $quiz->estaAbierto())
                        Este quiz no está disponible en este momento.
                    @else
                        No puedes rendir este quiz.
                    @endif
                </p>
            </div>
        @else
            <form wire:submit="enviar" class="space-y-4"
                @if ($this->segundosRestantes !== null)
                    x-data="{ restantes: {{ $this->segundosRestantes }} }"
                    x-init="const t = setInterval(() => { restantes--; if (restantes <= 0) { clearInterval(t); $wire.enviar(); } }, 1000)"
                @endif>
                @if ($this->segundosRestantes !== null)
                    <div class="sticky top-16 z-10 {{ $tarjeta }} !py-3 flex items-center justify-between">
                        <span class="text-sm {{ $textoSecundario }} flex items-center gap-2"><x-icono nombre="reloj" class="h-4 w-4" /> Tiempo restante</span>
                        <span class="font-mono font-bold {{ $acento }}" x-text="Math.floor(restantes / 60) + ':' + String(restantes % 60).padStart(2, '0')"></span>
                    </div>
                @endif

                @foreach ($orden as $posicion => $i)
                    @php $pregunta = $quiz->preguntas[$i]; $multiple = ($pregunta['tipo'] ?? 'opcion_unica') === 'opcion_multiple'; @endphp
                    <div class="{{ $tarjeta }}" wire:key="pregunta-{{ $i }}">
                        <p class="font-medium {{ $textoPrincipal }} mb-3">{{ $posicion + 1 }}. {{ $pregunta['enunciado'] }}</p>
                        @if ($multiple)
                            <p class="text-xs text-slate-400 -mt-2 mb-3">Puedes marcar más de una opción.</p>
                        @endif
                        <div class="space-y-2">
                            @foreach ($pregunta['opciones'] as $j => $opcion)
                                <label class="flex items-center gap-2 text-sm {{ $textoSecundario }} cursor-pointer">
                                    <input type="{{ $multiple ? 'checkbox' : 'radio' }}" wire:model="respuestas.{{ $i }}" value="{{ $j }}"
                                        class="{{ $multiple ? 'rounded ' : '' }}{{ $paletaFria ? 'text-azul-oscuro focus:ring-azul-fondo' : 'text-menta-oscuro focus:ring-menta-base' }}">
                                    {{ $opcion }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('respuestas.'.$i)" class="mt-2" />
                    </div>
                @endforeach

                <x-input-error :messages="$errors->get('respuestas')" class="mt-2" />

                @if ($paletaFria)
                    <x-primary-button wire:loading.attr="disabled" wire:target="enviar">Enviar respuestas</x-primary-button>
                @endif
            </form>
        @endif
    </div>
</div>
