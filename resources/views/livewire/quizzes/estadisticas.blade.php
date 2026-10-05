<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <x-gestion.encabezado titulo="Estadísticas: {{ $quiz->tema }}" icono="grafico"
            descripcion="{{ $quiz->modulo?->nombreCompleto() }} · Preguntas ordenadas de mayor a menor tasa de error (último intento de cada estudiante)."
            :volver="route('cursos.quizzes.index', $quiz->curso)" volver-texto="Volver a quizzes" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-gestion.indicador etiqueta="Intentos registrados" :valor="$totalIntentos" icono="usuarios" />
            <x-gestion.indicador etiqueta="Promedio general" :valor="$promedioGeneral !== null ? $promedioGeneral.' / 20' : '—'" icono="grafico"
                :tono="($promedioGeneral ?? 0) >= $notaMinima ? 'exito' : 'terracota'" detalle="Sobre todos los intentos" />
        </div>
        @if ($totalIntentos === 0)
            <x-gestion.vacio icono="grafico" titulo="Todavía nadie resolvió este quiz" descripcion="Las estadísticas aparecerán con el primer intento." />
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
                                        @if ($opcion['es_correcta']) <x-icono nombre="check" class="inline h-3 w-3" /> @endif {{ $opcion['opcion'] }}
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
