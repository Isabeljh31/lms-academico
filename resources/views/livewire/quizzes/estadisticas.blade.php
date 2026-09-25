<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div>
            <a href="{{ route('cursos.quizzes.index', $quiz->curso) }}" wire:navigate class="text-xs font-semibold text-stone-500 hover:underline flex items-center gap-1 mb-2">
                &larr; Volver a Quizzes de {{ $quiz->curso->nombre }}
            </a>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-menta-oscuro/10 text-menta-oscuro">📊</span>
                Estadísticas — {{ $quiz->tema }}
            </h1>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                {{ $quiz->modulo?->titulo }} · Preguntas ordenadas de mayor a menor tasa de error.
            </p>
        </div>

        {{-- KPIs generales --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <span class="text-xs font-semibold uppercase text-stone-500">Intentos registrados</span>
                <div class="text-3xl font-black text-stone-900 dark:text-stone-100 mt-1">{{ $totalIntentos }}</div>
                <span class="text-xs text-stone-400 mt-1 block">Estudiantes que ya resolvieron el quiz</span>
            </div>

            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <span class="text-xs font-semibold uppercase text-stone-500">Promedio general</span>
                <div class="text-3xl font-black {{ ($promedioGeneral ?? 0) >= 10.5 ? 'text-exito-oscuro' : 'text-terracota-oscuro' }} mt-1">
                    {{ $promedioGeneral !== null ? $promedioGeneral.' / 20' : '—' }}
                </div>
                <span class="text-xs text-stone-400 mt-1 block">Sobre todos los intentos</span>
            </div>
        </div>

        @if ($totalIntentos === 0)
            <p class="text-stone-500 dark:text-stone-400 bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-6">
                Todavía ningún estudiante ha resuelto este quiz — las estadísticas aparecerán aquí en cuanto haya el primer intento.
            </p>
        @else
            <div class="space-y-3">
                @foreach ($preguntas as $pregunta)
                    <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="text-xs font-semibold uppercase text-stone-400">Pregunta {{ $pregunta['indice'] + 1 }}</span>
                                <h3 class="font-medium text-stone-800 dark:text-stone-100">{{ $pregunta['enunciado'] }}</h3>
                            </div>
                            <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                                {{ $pregunta['tasa_error'] >= 50 ? 'bg-terracota-fondo/60 text-terracota-oscuro' : ($pregunta['tasa_error'] > 0 ? 'bg-arena-medio/60 text-stone-600' : 'bg-verde-fondo/50 text-verde-oscuro') }}">
                                {{ $pregunta['tasa_error'] }}% de error
                            </span>
                        </div>

                        <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">
                            {{ $pregunta['correctas'] }} de {{ $pregunta['total_respuestas'] }} respondieron correctamente
                        </p>

                        <div class="mt-3 space-y-1.5">
                            @foreach ($pregunta['distribucion'] as $opcion)
                                @php
                                    $porcentaje = $pregunta['total_respuestas'] > 0
                                        ? round(($opcion['elegida_por'] / $pregunta['total_respuestas']) * 100)
                                        : 0;
                                @endphp
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="w-32 sm:w-48 shrink-0 truncate {{ $opcion['es_correcta'] ? 'font-semibold text-verde-oscuro' : 'text-stone-500 dark:text-stone-400' }}">
                                        {{ $opcion['es_correcta'] ? '✓' : '' }} {{ $opcion['opcion'] }}
                                    </span>
                                    <div class="flex-1 h-2 rounded-full bg-arena-claro dark:bg-stone-700 overflow-hidden">
                                        <div class="h-full rounded-full {{ $opcion['es_correcta'] ? 'bg-verde-oscuro' : 'bg-terracota-base' }}" style="width: {{ $porcentaje }}%"></div>
                                    </div>
                                    <span class="w-16 shrink-0 text-right text-stone-400">{{ $opcion['elegida_por'] }} ({{ $porcentaje }}%)</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
