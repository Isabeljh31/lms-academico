<div class="py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Encabezado --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-menta-oscuro/10 text-menta-oscuro">
                        📢
                    </span>
                    Comunicados Institucionales
                </h1>
                <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                    Anuncios del sistema para coordinar fechas de entrega, cierres de actas y comunicados oficiales.
                </p>
            </div>

            <x-primary-button wire:click="$toggle('mostrarFormulario')">
                {{ $mostrarFormulario ? 'Ocultar formulario' : '+ Redactar comunicado' }}
            </x-primary-button>
        </div>

        {{-- Alertas de estado --}}
        @if (session('estado'))
            <div class="rounded-xl bg-exito-base/15 border border-exito-base/30 text-exito-oscuro p-4 text-sm flex items-center justify-between shadow-sm">
                <span>{{ session('estado') }}</span>
                <button type="button" onclick="this.parentElement.remove()" class="text-exito-oscuro/60 hover:text-exito-oscuro">&times;</button>
            </div>
        @endif

        {{-- Formulario de Redacción --}}
        @if ($mostrarFormulario)
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-6 shadow-sm space-y-4">
                <h2 class="text-lg font-bold text-stone-900 dark:text-stone-100 border-b pb-2 border-arena-medio/60">
                    Nuevo Comunicado Institucional
                </h2>

                <form wire:submit="crearComunicado" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <x-input-label for="titulo" value="Título del Comunicado" />
                            <x-text-input wire:model="titulo" id="titulo" class="mt-1 block w-full text-sm" placeholder="Ej: Cierre de ingreso de calificaciones para la Unidad 1" required autofocus />
                            <x-input-error :messages="$errors->get('titulo')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="prioridad" value="Nivel de Prioridad" />
                            <select wire:model="prioridad" id="prioridad"
                                class="mt-1 block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
                                <option value="informativo">🔵 Informativo</option>
                                <option value="urgente">🔴 Urgente / Crítico</option>
                            </select>
                            <x-input-error :messages="$errors->get('prioridad')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="destinatario" value="Audiencia Destinataria" />
                        <select wire:model="destinatario" id="destinatario"
                            class="mt-1 block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
                            <option value="profesores">👨‍🏫 Solo Profesores (Claustro Docente)</option>
                            <option value="todos">🌐 Toda la Institución (Profesores y Estudiantes)</option>
                            <option value="estudiantes">🎓 Solo Estudiantes</option>
                        </select>
                        <x-input-error :messages="$errors->get('destinatario')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="contenido" value="Mensaje / Directiva" />
                        <textarea wire:model="contenido" id="contenido" rows="4"
                            class="mt-1 block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm"
                            placeholder="Detalla las instrucciones oficiales para los docentes..." required></textarea>
                        <x-input-error :messages="$errors->get('contenido')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button wire:click="$set('mostrarFormulario', false)" type="button">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Publicar en Paneles</x-primary-button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Historial de Comunicados Publicados --}}
        <div class="space-y-4">
            <h2 class="text-base font-bold text-stone-800 dark:text-stone-200 uppercase tracking-wider text-xs">
                Comunicados Emitidos ({{ $comunicados->total() }})
            </h2>

            @forelse ($comunicados as $item)
                <div class="bg-white dark:bg-stone-800 rounded-xl border {{ $item->activo ? ($item->prioridad === 'urgente' ? 'border-red-300 dark:border-red-900' : 'border-arena-medio dark:border-stone-700') : 'border-stone-200 opacity-60' }} p-6 shadow-sm transition" wire:key="comunicado-{{ $item->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="space-y-2 max-w-3xl">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($item->prioridad === 'urgente')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                        🚨 Urgente
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">
                                        ℹ️ Informativo
                                    </span>
                                @endif

                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-arena-claro text-stone-700 dark:bg-stone-700 dark:text-stone-200">
                                    Destino: {{ ucfirst($item->destinatario) }}
                                </span>

                                @if (! $item->activo)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-stone-100 text-stone-500">
                                        Inactivo (Oculto)
                                    </span>
                                @endif
                            </div>

                            <h3 class="text-lg font-bold text-stone-900 dark:text-stone-100">
                                {{ $item->titulo }}
                            </h3>

                            <p class="text-sm text-stone-600 dark:text-stone-300 whitespace-pre-line leading-relaxed">
                                {{ $item->contenido }}
                            </p>

                            <div class="text-xs text-stone-400 pt-2 flex items-center gap-2">
                                <span>Por {{ $item->autor?->name ?? 'Administración' }}</span>
                                <span>•</span>
                                <span>{{ $item->created_at->translatedFormat('d M Y H:i') }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button wire:click="toggleActivo({{ $item->id }})"
                                class="text-xs font-semibold px-3 py-1.5 rounded-lg border {{ $item->activo ? 'border-stone-300 hover:bg-stone-100 text-stone-700' : 'border-exito-base/50 text-exito-oscuro hover:bg-exito-base/10' }}">
                                {{ $item->activo ? 'Desactivar' : 'Reactivar' }}
                            </button>

                            <button wire:click="eliminar({{ $item->id }})" wire:confirm="¿Seguro que deseas eliminar este comunicado?"
                                class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-8 text-center text-stone-500">
                    No hay comunicados institucionales registrados.
                </div>
            @endforelse

            <div class="mt-4">
                {{ $comunicados->links() }}
            </div>
        </div>

    </div>
</div>
