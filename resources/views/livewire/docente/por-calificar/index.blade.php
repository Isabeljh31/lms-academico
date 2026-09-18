<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Encabezado --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-terracota-fondo text-terracota-oscuro">
                        📝
                    </span>
                    Bandeja Por Calificar (Needs Grading)
                </h1>
                <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                    Cola centralizada de entregas Califica y retroalimenta los trabajos de todos tus cursos en un solo lugar, desde una vista centralizada.
                </p>
            </div>
        </div>

        {{-- Alertas de estado --}}
        @if (session('estado'))
            <div class="rounded-xl bg-exito-base/15 border border-exito-base/30 text-exito-oscuro p-4 text-sm flex items-center justify-between shadow-sm">
                <span>{{ session('estado') }}</span>
                <button type="button" onclick="this.parentElement.remove()" class="text-exito-oscuro/60 hover:text-exito-oscuro">&times;</button>
            </div>
        @endif

        {{-- Tarjetas de Resumen Rápido --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase text-stone-500">Pendientes en Cola</div>
                <div class="text-3xl font-black text-terracota-oscuro mt-1">{{ $totalPendientes }}</div>
                <div class="text-xs text-stone-400 mt-1">Entregas de alumnos esperando nota</div>
            </div>

            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase text-stone-500">Con Retraso (>3 días)</div>
                <div class="text-3xl font-black {{ $conAtraso > 0 ? 'text-red-600' : 'text-exito-oscuro' }} mt-1">
                    {{ $conAtraso }}
                </div>
                <div class="text-xs text-stone-400 mt-1">
                    {{ $conAtraso > 0 ? 'Requieren atención prioritaria' : 'Al día con las revisiones' }}
                </div>
            </div>

            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase text-stone-500">Cursos a Cargo</div>
                <div class="text-3xl font-black text-stone-900 dark:text-stone-100 mt-1">{{ $cursos->count() }}</div>
                <div class="text-xs text-stone-400 mt-1">Asignaturas activas asignadas</div>
            </div>
        </div>

        {{-- Filtros y Búsqueda --}}
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 shadow-sm flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[240px]">
                <input wire:model.live.debounce.300ms="busqueda" type="text"
                    placeholder="Buscar por nombre de alumno o título de tarea..."
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 focus:border-menta-base focus:ring-menta-base text-sm">
            </div>

            <div class="w-full sm:w-auto">
                <select wire:model.live="cursoFiltroId"
                    class="w-full sm:w-auto rounded-lg border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 focus:border-menta-base focus:ring-menta-base text-sm">
                    <option value="">-- Todos los cursos --</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Tabla de Entregas por Calificar --}}
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-stone-600 dark:text-stone-300">
                    <thead class="bg-arena-claro/50 dark:bg-stone-700/50 text-xs uppercase tracking-wider text-stone-500 font-bold border-b border-arena-medio dark:border-stone-700">
                        <tr>
                            <th class="py-3 px-4">Estudiante / Grupo</th>
                            <th class="py-3 px-4">Asignatura</th>
                            <th class="py-3 px-4">Actividad / Tarea</th>
                            <th class="py-3 px-4">Fecha de Envío</th>
                            <th class="py-3 px-4 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-arena-medio/40 dark:divide-stone-700">
                        @forelse ($entregas as $entrega)
                            @php
                                $diasEspera = $entrega->created_at->diffInDays(now());
                                $esUrgente = $diasEspera >= 3;
                            @endphp
                            <tr class="hover:bg-stone-50 dark:hover:bg-stone-700/30 transition {{ $esUrgente ? 'bg-red-50/30 dark:bg-red-950/10' : '' }}" wire:key="ent-{{ $entrega->id }}">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $entrega->estudiante?->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($entrega->estudiante?->name ?? 'A').'&background=2F5A78&color=fff' }}"
                                            alt="{{ $entrega->estudiante?->name }}" class="h-8 w-8 rounded-full object-cover shrink-0">
                                        <div>
                                            <div class="font-semibold text-stone-900 dark:text-stone-100">
                                                {{ $entrega->estudiante?->name }}
                                            </div>
                                            @if ($entrega->grupo)
                                                <span class="inline-flex items-center text-[10px] font-bold px-1.5 py-0.5 rounded bg-lavanda-fondo/50 text-lavanda-oscuro">
                                                    👥 {{ $entrega->grupo->nombre }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <span class="font-medium text-stone-800 dark:text-stone-200">
                                        {{ $entrega->tarea->curso->nombre }}
                                    </span>
                                </td>

                                <td class="py-3 px-4">
                                    <div>
                                        <div class="font-semibold text-stone-900 dark:text-stone-100">
                                            {{ $entrega->tarea->titulo }}
                                        </div>
                                        <div class="text-xs text-stone-400">
                                            Peso: {{ $entrega->tarea->peso_nota }} pts
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs {{ $esUrgente ? 'text-red-600 font-bold' : 'text-stone-500' }}">
                                            {{ $entrega->created_at->diffForHumans() }}
                                        </span>
                                        @if ($esUrgente)
                                            <span class="text-xs" title="Más de 3 días esperando nota">⚠️</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-stone-400">
                                        {{ $entrega->created_at->format('d/m/Y H:i') }}
                                    </div>
                                </td>

                                <td class="py-3 px-4 text-center">
                                    <button wire:click="abrirCalificar({{ $entrega->id }})"
                                        class="inline-flex items-center gap-1 bg-menta-oscuro hover:bg-menta-oscuro/90 text-white font-semibold text-xs px-3 py-1.5 rounded-lg shadow-sm transition">
                                        Calificar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-stone-500">
                                    <div class="text-2xl mb-1">🎉</div>
                                    <p class="font-semibold">¡No hay entregas pendientes de calificación!</p>
                                    <p class="text-xs text-stone-400 mt-1">Todos los trabajos de tus cursos están calificados al día.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-arena-medio/40 dark:border-stone-700">
                {{ $entregas->links() }}
            </div>
        </div>

    </div>

    {{-- Modal de Calificación Rápida --}}
    @if ($mostrarModalCalificar && $entregaSeleccionada)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/50 p-4">
            <div class="bg-white dark:bg-stone-800 rounded-2xl border border-arena-medio dark:border-stone-700 max-w-lg w-full p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b pb-3 border-arena-medio/50">
                    <div>
                        <h3 class="text-lg font-bold text-stone-900 dark:text-stone-100">Calificar Entrega</h3>
                        <p class="text-xs text-stone-500">{{ $entregaSeleccionada->tarea->curso->nombre }} &bull; {{ $entregaSeleccionada->tarea->titulo }}</p>
                    </div>
                    <button wire:click="$set('mostrarModalCalificar', false)" class="text-stone-400 hover:text-stone-600 text-xl font-bold">&times;</button>
                </div>

                {{-- Datos del Estudiante y Trabajo --}}
                <div class="bg-stone-50 dark:bg-stone-700/40 rounded-xl p-3 text-xs space-y-1.5 border border-stone-200 dark:border-stone-600">
                    <div class="flex justify-between">
                        <span class="text-stone-500 font-medium">Estudiante:</span>
                        <span class="font-bold text-stone-800 dark:text-stone-200">{{ $entregaSeleccionada->estudiante?->name }}</span>
                    </div>

                    @if ($entregaSeleccionada->comentario)
                        <div>
                            <span class="text-stone-500 font-medium block">Comentario del alumno:</span>
                            <p class="italic text-stone-700 dark:text-stone-300 mt-0.5">"{{ $entregaSeleccionada->comentario }}"</p>
                        </div>
                    @endif

                    @if ($entregaSeleccionada->archivo_url)
                        <div class="pt-1">
                            <a href="{{ $entregaSeleccionada->archivo_url }}" target="_blank"
                                class="inline-flex items-center gap-1 font-semibold text-menta-oscuro dark:text-menta-base hover:underline">
                                📎 Ver archivo entregado
                            </a>
                        </div>
                    @endif
                </div>

                <form wire:submit="guardarCalificacion" class="space-y-4">
                    <div>
                        <x-input-label for="nota" value="Nota (sobre 20)" />
                        <x-text-input wire:model="nota" id="nota" type="number" step="0.5" min="0" max="20" class="mt-1 block w-full text-lg font-bold" placeholder="Ej: 16.5" required autofocus />
                        <x-input-error :messages="$errors->get('nota')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="feedback" value="Retroalimentación para el alumno" />
                        <textarea wire:model="feedback" id="feedback" rows="3"
                            class="mt-1 block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm"
                            placeholder="Excelente trabajo, observa los comentarios en la página 2..."></textarea>
                        <x-input-error :messages="$errors->get('feedback')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button wire:click="$set('mostrarModalCalificar', false)" type="button">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar y Notificar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
