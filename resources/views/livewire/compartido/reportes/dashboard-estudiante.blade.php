@php
    $etiquetas = $filas->map(fn ($f) => $f['curso']->nombre)->values();
    $promediosTareas = $filas->map(fn ($f) => $f['promedio_tareas'] ?? 0)->values();
    $promediosQuizzes = $filas->map(fn ($f) => $f['promedio_quizzes'] ?? 0)->values();
@endphp

<div class="py-8">
    {{-- Ancho de contenedor estandarizado (max-w-6xl) para todas las vistas del estudiante. --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div>
            <h1 class="text-2xl font-bold text-texto dark:text-slate-100">Mi progreso</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Tus promedios por curso, combinando tareas y quizzes.</p>
        </div>

        @if ($filas->isEmpty())
            <p class="text-slate-500 dark:text-slate-400">Todavía no estás inscrito en ningún curso.</p>
        @else
            {{-- Resumen: promedio general combinando todos los cursos inscritos. --}}
            <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro p-5 flex items-center gap-4">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-azul-oscuro/10 dark:bg-azul-oscuro/20 text-azul-oscuro dark:text-azul-fondo text-xl font-bold">
                    {{ $promedioGeneral ?? '—' }}
                </span>
                <div>
                    <p class="font-semibold text-texto dark:text-slate-100">Promedio general</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Promedio de tus notas finales ponderadas por rubros en tus {{ $filas->count() }} {{ $filas->count() === 1 ? 'curso' : 'cursos' }}. Las tareas y quizzes son de práctica y no se suman a la nota final.
                    </p>
                </div>
            </div>

            <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-4"
                wire:ignore
                x-data="{
                    init() {
                        new window.Chart(this.$refs.grafico, {
                            type: 'bar',
                            data: {
                                labels: @js($etiquetas),
                                datasets: [
                                    { label: 'Promedio tareas', data: @js($promediosTareas), backgroundColor: '#7FA9C9' },
                                    { label: 'Promedio quizzes', data: @js($promediosQuizzes), backgroundColor: '#B8B0D8' },
                                ],
                            },
                            options: { responsive: true, scales: { y: { beginAtZero: true, max: 20 } } },
                        });
                    },
                }"
            >
                <canvas x-ref="grafico" height="90"></canvas>
            </div>

            <div class="space-y-3">
                @foreach ($filas as $fila)
                    <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none overflow-hidden"
                        wire:key="curso-{{ $fila['curso']->id }}" x-data="{ abierto: false }">
                        {{-- Colapsada: promedio y boleta, siempre visibles --}}
                        <button type="button" @click="abierto = ! abierto" class="w-full flex items-center justify-between gap-4 p-5 text-left">
                            <div class="flex items-center gap-4 min-w-0">
                                <x-icono nombre="flecha-abajo" class="h-4 w-4 shrink-0 text-slate-400 transition-transform" x-bind:class="abierto ? 'rotate-180' : ''" />
                                <div class="min-w-0">
                                    <h2 class="font-semibold text-texto dark:text-slate-100 truncate">{{ $fila['curso']->nombre }}</h2>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ count($fila['tareas']) }} tareas - {{ count($fila['quizzes']) }} quizzes - {{ $fila['avance'] }}% de avance</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 shrink-0">
                                <div class="text-right">
                                    <p class="text-xl font-bold {{ $fila['condicion'] === 'desaprobado' ? 'text-red-600 dark:text-red-400' : 'text-texto dark:text-slate-100' }}">{{ $fila['nota_rubros'] ?? '—' }}</p>
                                    <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ $fila['condicion'] === 'en_curso' ? 'nota acumulada' : 'nota final' }}</p>
                                </div>
                                <a href="{{ route('reportes.boleta', $fila['curso']) }}" target="_blank" @click.stop
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-azul-oscuro dark:text-azul-fondo hover:underline">
                                    <x-icono nombre="descargar" class="h-4 w-4" /> Boleta PDF
                                </a>
                            </div>
                        </button>

                        {{-- Expandida: tabla real, no badges sueltos --}}
                        <div x-show="abierto" x-transition class="border-t border-celeste dark:border-azul-borde-oscuro">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-slate-400 uppercase">
                                            <th class="px-5 py-2 font-medium">Evaluación</th>
                                            <th class="px-5 py-2 font-medium">Peso</th>
                                            <th class="px-5 py-2 font-medium">Nota</th>
                                            <th class="px-5 py-2 font-medium">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-celeste dark:divide-azul-borde-oscuro">
                                        @foreach ($fila['tareas'] as $tarea)
                                            <tr>
                                                <td class="px-5 py-2 text-slate-700 dark:text-slate-200">{{ $tarea['titulo'] }}</td>
                                                <td class="px-5 py-2 text-slate-500 dark:text-slate-400">{{ $tarea['peso'] }}</td>
                                                <td class="px-5 py-2 text-slate-700 dark:text-slate-200">{{ $tarea['nota'] ?? '—' }}</td>
                                                <td class="px-5 py-2"><x-badge-estado :estado="$tarea['estado']" /></td>
                                            </tr>
                                        @endforeach
                                        @foreach ($fila['quizzes'] as $quiz)
                                            <tr>
                                                <td class="px-5 py-2 text-slate-700 dark:text-slate-200">{{ $quiz['tema'] }}</td>
                                                <td class="px-5 py-2 text-slate-500 dark:text-slate-400">—</td>
                                                <td class="px-5 py-2 text-slate-700 dark:text-slate-200">{{ $quiz['puntaje'] ?? '—' }}</td>
                                                <td class="px-5 py-2"><x-badge-estado :estado="$quiz['puntaje'] !== null ? 'calificado' : 'pendiente'" /></td>
                                            </tr>
                                        @endforeach
                                        @if (empty($fila['tareas']) && empty($fila['quizzes']))
                                            <tr>
                                                <td colspan="4" class="px-5 py-4 text-center text-slate-400">Este curso todavía no tiene tareas ni quizzes.</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
