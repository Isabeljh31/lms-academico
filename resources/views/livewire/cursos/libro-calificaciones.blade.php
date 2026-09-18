<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Encabezado y Navegación --}}
        <div>
            <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="text-xs font-semibold text-stone-500 hover:underline flex items-center gap-1 mb-2">
                &larr; Volver al curso {{ $curso->nombre }}
            </a>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-menta-oscuro/10 text-menta-oscuro">
                            📊
                        </span>
                        Libro de Calificaciones (Gradebook)
                    </h1>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                        Centro de calificaciones consolidado registro ponderado de tareas, quizzes y promedios finales.
                    </p>
                </div>
            </div>
        </div>

        {{-- Alertas de estado --}}
        @if (session('estado'))
            <div class="rounded-xl bg-exito-base/15 border border-exito-base/30 text-exito-oscuro p-4 text-sm flex items-center justify-between shadow-sm">
                <span>{{ session('estado') }}</span>
                <button type="button" onclick="this.parentElement.remove()" class="text-exito-oscuro/60 hover:text-exito-oscuro">&times;</button>
            </div>
        @endif

        {{-- KPIs del Curso --}}
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <span class="text-xs font-semibold uppercase text-stone-500">Promedio del Curso</span>
                <div class="text-3xl font-black {{ ($promedioCurso ?? 0) >= 10.5 ? 'text-exito-oscuro' : 'text-terracota-oscuro' }} mt-1">
                    {{ $promedioCurso !== null ? $promedioCurso . ' / 20' : '—' }}
                </div>
                <span class="text-xs text-stone-400 mt-1 block">Rendimiento global ponderado</span>
            </div>

            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <span class="text-xs font-semibold uppercase text-stone-500">Tasa de Aprobación</span>
                <div class="text-3xl font-black text-stone-900 dark:text-stone-100 mt-1">
                    {{ $tasaAprobacion }}%
                </div>
                <span class="text-xs text-stone-400 mt-1 block">{{ $aprobados }} estudiantes con nota &ge; 10.5</span>
            </div>

            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <span class="text-xs font-semibold uppercase text-stone-500">Nota Máxima</span>
                <div class="text-3xl font-black text-exito-oscuro mt-1">
                    {{ $notaMaxima !== null ? $notaMaxima : '—' }}
                </div>
                <span class="text-xs text-stone-400 mt-1 block">Mayor promedio registrado</span>
            </div>

            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <span class="text-xs font-semibold uppercase text-stone-500">Nota Mínima</span>
                <div class="text-3xl font-black text-terracota-oscuro mt-1">
                    {{ $notaMinima !== null ? $notaMinima : '—' }}
                </div>
                <span class="text-xs text-stone-400 mt-1 block">Menor promedio registrado</span>
            </div>
        </div>

        {{-- Matriz Tabular del Gradebook --}}
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-stone-600 dark:text-stone-300">
                    <thead class="bg-arena-claro/60 dark:bg-stone-700/60 text-xs text-stone-600 dark:text-stone-300 font-bold border-b border-arena-medio dark:border-stone-700">
                        <tr>
                            <th class="py-3 px-4 sticky left-0 bg-arena-claro/95 dark:bg-stone-700/95 z-10 min-w-[220px]">
                                Estudiante
                            </th>
                            @foreach ($tareas as $t)
                                <th class="py-3 px-3 text-center min-w-[130px]" title="Peso: {{ $t->peso_nota }} pts">
                                    <div class="truncate max-w-[120px] mx-auto">{{ $t->titulo }}</div>
                                    <span class="text-[10px] font-normal text-stone-500">Peso: {{ $t->peso_nota }}</span>
                                </th>
                            @endforeach
                            @foreach ($quizzes as $q)
                                <th class="py-3 px-3 text-center min-w-[130px]" title="Quiz: {{ $q->tema }}">
                                    <div class="truncate max-w-[120px] mx-auto">Quiz: {{ $q->tema }}</div>
                                    <span class="text-[10px] font-normal text-stone-500">20 pts</span>
                                </th>
                            @endforeach
                            <th class="py-3 px-3 text-center min-w-[110px] bg-stone-100 dark:bg-stone-700/80 font-bold">
                                Prom. Tareas
                            </th>
                            <th class="py-3 px-3 text-center min-w-[110px] bg-stone-100 dark:bg-stone-700/80 font-bold">
                                Prom. Quizzes
                            </th>
                            <th class="py-3 px-4 text-center min-w-[120px] sticky right-0 bg-arena-claro/95 dark:bg-stone-700/95 z-10 font-black text-stone-900 dark:text-stone-100">
                                Promedio Final
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-arena-medio/40 dark:divide-stone-700">
                        @forelse ($filasMatriz as $fila)
                            @php $est = $fila['estudiante']; @endphp
                            <tr class="hover:bg-stone-50 dark:hover:bg-stone-700/30 transition" wire:key="matriz-est-{{ $est->id }}">
                                <td class="py-3 px-4 sticky left-0 bg-white dark:bg-stone-800 z-10 border-r border-arena-medio/40 dark:border-stone-700">
                                    <div class="flex items-center gap-2.5">
                                        <img src="{{ $est->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($est->name).'&background=2F5A78&color=fff' }}"
                                            alt="{{ $est->name }}" class="h-7 w-7 rounded-full object-cover shrink-0">
                                        <div class="truncate max-w-[180px]">
                                            <div class="font-semibold text-stone-900 dark:text-stone-100 text-xs">{{ $est->name }}</div>
                                            <div class="text-[10px] text-stone-400 truncate">{{ $est->email }}</div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Notas de Tareas --}}
                                @foreach ($tareas as $t)
                                    @php $celda = $fila['notasTareas'][$t->id] ?? null; @endphp
                                    <td class="py-3 px-3 text-center">
                                        @if ($celda && $celda['nota'] !== null)
                                            <button wire:click="abrirEdicionNota({{ $est->id }}, {{ $t->id }})"
                                                class="inline-flex items-center justify-center font-bold px-2 py-1 rounded text-xs transition {{ $celda['nota'] >= 10.5 ? 'bg-exito-base/15 text-exito-oscuro hover:bg-exito-base/25' : 'bg-terracota-fondo/60 text-terracota-oscuro hover:bg-terracota-fondo' }}"
                                                title="Clic para editar calificación">
                                                {{ $celda['nota'] }}
                                            </button>
                                        @elseif ($celda && $celda['estado'] === 'entregado')
                                            <button wire:click="abrirEdicionNota({{ $est->id }}, {{ $t->id }})"
                                                class="inline-flex items-center justify-center text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800 hover:bg-amber-200">
                                                Revisar
                                            </button>
                                        @else
                                            <button wire:click="abrirEdicionNota({{ $est->id }}, {{ $t->id }})"
                                                class="text-stone-300 hover:text-stone-600 text-xs px-2 py-1" title="Asignar nota directa">
                                                —
                                            </button>
                                        @endif
                                    </td>
                                @endforeach

                                {{-- Notas de Quizzes --}}
                                @foreach ($quizzes as $q)
                                    @php $celdaQ = $fila['notasQuizzes'][$q->id] ?? null; @endphp
                                    <td class="py-3 px-3 text-center">
                                        @if ($celdaQ && $celdaQ['nota'] !== null)
                                            <span class="inline-flex items-center justify-center font-bold px-2 py-1 rounded text-xs {{ $celdaQ['nota'] >= 10.5 ? 'bg-verde-fondo/40 text-verde-oscuro' : 'bg-red-100 text-red-700' }}">
                                                {{ $celdaQ['nota'] }}
                                            </span>
                                        @else
                                            <span class="text-stone-300 text-xs">—</span>
                                        @endif
                                    </td>
                                @endforeach

                                {{-- Promedios de grupo --}}
                                <td class="py-3 px-3 text-center font-semibold text-xs bg-stone-50/50 dark:bg-stone-700/20">
                                    {{ $fila['promedioTareas'] !== null ? $fila['promedioTareas'] : '—' }}
                                </td>

                                <td class="py-3 px-3 text-center font-semibold text-xs bg-stone-50/50 dark:bg-stone-700/20">
                                    {{ $fila['promedioQuizzes'] !== null ? $fila['promedioQuizzes'] : '—' }}
                                </td>

                                {{-- Promedio Final del Curso --}}
                                <td class="py-3 px-4 text-center sticky right-0 bg-white dark:bg-stone-800 z-10 border-l border-arena-medio/40 dark:border-stone-700">
                                    @if ($fila['promedioFinal'] !== null)
                                        <span class="inline-flex items-center justify-center font-black px-2.5 py-1 rounded-full text-xs {{ $fila['promedioFinal'] >= 10.5 ? 'bg-exito-base/20 text-exito-oscuro' : 'bg-terracota-fondo text-terracota-oscuro' }}">
                                            {{ $fila['promedioFinal'] }}
                                        </span>
                                    @else
                                        <span class="text-stone-300 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 4 + $tareas->count() + $quizzes->count() }}" class="py-8 text-center text-stone-500">
                                    No hay estudiantes inscritos en este curso todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- Modal de Edición de Nota Directa --}}
    @if ($mostrarModalNota && $estudianteSeleccionado && $tareaSeleccionada)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/50 p-4">
            <div class="bg-white dark:bg-stone-800 rounded-2xl border border-arena-medio dark:border-stone-700 max-w-md w-full p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b pb-3 border-arena-medio/50">
                    <div>
                        <h3 class="text-lg font-bold text-stone-900 dark:text-stone-100">Registro de Calificación</h3>
                        <p class="text-xs text-stone-500">{{ $tareaSeleccionada->titulo }} &bull; {{ $estudianteSeleccionado->name }}</p>
                    </div>
                    <button wire:click="$set('mostrarModalNota', false)" class="text-stone-400 hover:text-stone-600 text-xl font-bold">&times;</button>
                </div>

                <form wire:submit="guardarNota" class="space-y-4">
                    <div>
                        <x-input-label for="nota" value="Nota (sobre 20)" />
                        <x-text-input wire:model="nota" id="nota" type="number" step="0.5" min="0" max="20" class="mt-1 block w-full text-lg font-bold" placeholder="Ej: 15.0" required autofocus />
                        <x-input-error :messages="$errors->get('nota')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="feedback" value="Retroalimentación (opcional)" />
                        <textarea wire:model="feedback" id="feedback" rows="3"
                            class="mt-1 block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm"
                            placeholder="Comentario para el alumno..."></textarea>
                        <x-input-error :messages="$errors->get('feedback')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button wire:click="$set('mostrarModalNota', false)" type="button">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Actualizar Calificación</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
