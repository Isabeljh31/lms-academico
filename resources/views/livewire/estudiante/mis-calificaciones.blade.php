<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto space-y-6">

    {{-- Encabezado --}}
    <div>
        <h1 class="text-2xl font-bold text-texto dark:text-slate-100 flex items-center gap-2">
            <x-icono nombre="lista" class="h-6 w-6" /> Mis Calificaciones
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Nota final de cada curso y, si lo abres, el detalle de tareas y quizzes calificados.
        </p>
    </div>

    @if($filas->isEmpty())
    <div class="bg-white dark:bg-azul-superficie-oscura rounded-2xl border border-celeste dark:border-azul-borde-oscuro p-8 text-center">
        <p class="text-sm text-slate-500 dark:text-slate-400">Todavía no estás matriculado en ningún curso.</p>
    </div>
    @else

    {{-- Tarjetas de resumen --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-azul-superficie-oscura rounded-2xl border border-celeste dark:border-azul-borde-oscuro p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">Promedio general</p>
            <p class="text-2xl font-black text-azul-oscuro dark:text-cielo mt-1">
                {{ $promedioGeneral !== null ? number_format($promedioGeneral, 1) : '—' }}
            </p>
        </div>
        <div class="bg-white dark:bg-azul-superficie-oscura rounded-2xl border border-celeste dark:border-azul-borde-oscuro p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">Aprobados</p>
            <p class="text-2xl font-black text-green-600 dark:text-green-400 mt-1">{{ $totalAprobados }}</p>
        </div>
        <div class="bg-white dark:bg-azul-superficie-oscura rounded-2xl border border-celeste dark:border-azul-borde-oscuro p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">Desaprobados</p>
            <p class="text-2xl font-black text-red-600 dark:text-red-400 mt-1">{{ $totalDesaprobados }}</p>
        </div>
        <div class="bg-white dark:bg-azul-superficie-oscura rounded-2xl border border-celeste dark:border-azul-borde-oscuro p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">En curso</p>
            <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ $totalEnCurso }}</p>
        </div>
    </div>

    {{-- Listado por curso: nota final siempre visible, detalle de tareas/quizzes colapsable --}}
    <div class="space-y-3">
        @foreach($filas as $f)
        <div class="bg-white dark:bg-azul-superficie-oscura rounded-2xl border border-celeste dark:border-azul-borde-oscuro shadow-sm overflow-hidden"
            wire:key="curso-{{ $f['curso']->id }}" x-data="{ abierto: false }">
            <button type="button" @click="abierto = ! abierto" class="w-full flex items-center justify-between gap-4 p-5 text-left">
                <div class="flex items-center gap-4 min-w-0">
                    <x-icono nombre="flecha-abajo" class="h-4 w-4 shrink-0 text-slate-400 transition-transform" x-bind:class="abierto ? 'rotate-180' : ''" />
                    <div class="min-w-0">
                        <h2 class="font-medium text-texto dark:text-slate-100 truncate">{{ $f['curso']->nombre }}</h2>
                        <p class="text-xs text-slate-400 dark:text-slate-500">{{ $f['curso']->codigo }} · {{ $f['curso']->profesor?->name ?? 'Sin profesor' }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 shrink-0">
                    @if(!$f['tieneEsquema'])
                    <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-700 px-2.5 py-0.5 text-xs font-medium text-slate-500 dark:text-slate-400">Sin esquema</span>
                    @elseif($f['condicion'] === 'aprobado')
                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 dark:bg-green-900/20 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">
                        <x-icono nombre="check" class="h-3 w-3" /> Aprobado
                    </span>
                    @elseif($f['condicion'] === 'desaprobado')
                    <span class="inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900/20 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:text-red-400">
                        <x-icono nombre="cerrar" class="h-3 w-3" /> Desaprobado
                    </span>
                    @elseif($f['condicion'] === 'en_curso')
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-900/20 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">
                        <x-icono nombre="reloj" class="h-3 w-3" /> En curso
                    </span>
                    @else
                    <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-700 px-2.5 py-0.5 text-xs font-medium text-slate-500 dark:text-slate-400">Sin notas</span>
                    @endif

                    <div class="text-right">
                        @if($f['notaFinal'] !== null)
                        <span class="inline-flex items-center justify-center w-14 rounded-full px-2 py-1 text-sm font-bold
                            {{ $f['notaFinal'] >= $notaMinima
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                                : 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400' }}">
                            {{ number_format($f['notaFinal'], 1) }}
                        </span>
                        @else
                        <span class="text-slate-300 dark:text-slate-600">—</span>
                        @endif
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">nota final</p>
                    </div>

                    <a href="{{ route('cursos.boleta', $f['curso']) }}" @click.stop
                        class="inline-flex items-center gap-1 text-azul-oscuro dark:text-cielo text-xs font-semibold hover:underline">
                        Ver boleta <x-icono nombre="flecha-derecha" class="h-3 w-3" />
                    </a>
                </div>
            </button>

            {{-- Detalle: tareas y quizzes calificados, de práctica, nunca sumados a la nota final --}}
            <div x-show="abierto" x-transition class="border-t border-celeste dark:border-azul-borde-oscuro">
                @if($f['tareas']->isEmpty() && $f['quizzes']->isEmpty())
                <p class="text-xs text-slate-400 dark:text-slate-500 px-5 py-4">Todavía no tienes tareas ni quizzes calificados en este curso.</p>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-slate-400 uppercase">
                                <th class="px-5 py-2 font-medium">Evaluación</th>
                                <th class="px-5 py-2 font-medium">Tipo</th>
                                <th class="px-5 py-2 font-medium text-center">Nota</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-celeste dark:divide-azul-borde-oscuro">
                            @foreach($f['tareas'] as $tarea)
                            <tr>
                                <td class="px-5 py-2 text-slate-700 dark:text-slate-200">{{ $tarea['titulo'] }}</td>
                                <td class="px-5 py-2 text-slate-400 dark:text-slate-500">Tarea</td>
                                <td class="px-5 py-2 text-center font-semibold text-slate-700 dark:text-slate-200">{{ number_format($tarea['nota'], 1) }}</td>
                            </tr>
                            @endforeach
                            @foreach($f['quizzes'] as $quiz)
                            <tr>
                                <td class="px-5 py-2 text-slate-700 dark:text-slate-200">{{ $quiz['tema'] }}</td>
                                <td class="px-5 py-2 text-slate-400 dark:text-slate-500">Quiz</td>
                                <td class="px-5 py-2 text-center font-semibold text-slate-700 dark:text-slate-200">{{ number_format($quiz['puntaje'], 1) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 px-5 pb-3">Notas de práctica: no se suman a la nota final ponderada por rubros.</p>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    <p class="text-xs text-slate-400 dark:text-slate-500 text-center">
        Nota mínima aprobatoria: {{ $notaMinima }} / 20. "En curso" significa que aún faltan rubros por calificar.
    </p>
    @endif
</div>
