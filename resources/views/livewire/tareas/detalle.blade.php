<div class="py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <a href="{{ route('cursos.tareas.index', $tarea->curso) }}" wire:navigate class="text-sm text-menta-oscuro dark:text-menta-base hover:underline">&larr; Tareas</a>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">{{ $tarea->titulo }}</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400">
                {{ $tarea->modulo?->titulo }} · Vence {{ $tarea->fecha_entrega->format('d/m/Y H:i') }} · Peso {{ $tarea->peso_nota }}
            </p>
        </div>

        @if (session('estado'))
            <div class="rounded-lg bg-exito-base/15 text-exito-oscuro px-4 py-3 text-sm">{{ session('estado') }}</div>
        @endif

        @if ($tarea->descripcion)
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-6">
                <p class="text-stone-600 dark:text-stone-300 whitespace-pre-line">{{ $tarea->descripcion }}</p>
            </div>
        @endif

        @if (! $this->puedeGestionar)
            {{-- Vista de estudiante: entregar o ver mi entrega calificada --}}
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-6">
                @if ($this->miEntrega)
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-stone-800 dark:text-stone-100">Tu entrega</h2>
                        <x-badge-estado :estado="$this->miEntrega->estado" />
                    </div>
                    <a href="{{ $this->miEntrega->archivo_url }}" target="_blank" class="text-sm text-menta-oscuro dark:text-menta-base hover:underline flex items-center gap-1 mt-2">
                        <x-icono nombre="material" class="h-4 w-4" /> Ver archivo entregado
                    </a>
                    @if ($this->miEntrega->comentario)
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-2">"{{ $this->miEntrega->comentario }}"</p>
                    @endif

                    @if ($this->miEntrega->estado === 'calificado')
                        <div class="mt-4 pt-4 border-t border-arena-medio dark:border-stone-700">
                            <p class="text-sm font-semibold text-stone-800 dark:text-stone-100">Nota: {{ $this->miEntrega->nota }} / 20</p>
                            @if ($this->miEntrega->feedback)
                                <p class="text-sm text-stone-600 dark:text-stone-300 mt-1">{{ $this->miEntrega->feedback }}</p>
                            @endif
                        </div>
                    @endif
                @else
                    <h2 class="font-semibold text-stone-800 dark:text-stone-100 mb-4">Entregar tarea</h2>
                    <form wire:submit="entregar" class="space-y-4">
                        <div>
                            <input type="file" wire:model="archivo"
                                class="block w-full text-sm text-stone-600 dark:text-stone-400 file:me-4 file:rounded-md file:border-0 file:bg-menta-oscuro file:px-3 file:py-1.5 file:text-white file:text-sm hover:file:bg-menta-oscuro/90">
                            <span wire:loading wire:target="archivo" class="text-xs text-stone-400">Subiendo…</span>
                            <x-input-error :messages="$errors->get('archivo')" class="mt-2" />
                        </div>
                        <div>
                            <textarea wire:model="comentario" rows="2" placeholder="Comentario (opcional)…"
                                class="block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base"></textarea>
                        </div>
                        <x-primary-button>Entregar</x-primary-button>
                    </form>
                @endif
            </div>
        @else
            {{-- Vista de profesor/administrador: calificar entregas --}}
            <div class="space-y-4">
                <h2 class="font-semibold text-stone-800 dark:text-stone-100">Entregas ({{ $entregas->count() }})</h2>

                @forelse ($entregas as $entrega)
                    <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4" wire:key="entrega-{{ $entrega->id }}">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-stone-800 dark:text-stone-100">{{ $entrega->estudiante->name }}</span>
                            <x-badge-estado :estado="$entrega->estado" />
                        </div>
                        <a href="{{ $entrega->archivo_url }}" target="_blank" class="text-sm text-menta-oscuro dark:text-menta-base hover:underline block mt-1">
                            📎 Ver archivo
                        </a>
                        @if ($entrega->comentario)
                            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">"{{ $entrega->comentario }}"</p>
                        @endif

                        <form wire:submit="calificar({{ $entrega->id }})" class="mt-3 flex flex-wrap items-start gap-3">
                            <div>
                                <input type="number" step="0.01" min="0" max="20" wire:model="notas.{{ $entrega->id }}" placeholder="Nota /20"
                                    class="w-24 rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
                                <x-input-error :messages="$errors->get("notas.$entrega->id")" class="mt-1" />
                            </div>
                            <div class="flex-1 min-w-[200px]">
                                <input type="text" wire:model="feedbacks.{{ $entrega->id }}" placeholder="Feedback…"
                                    class="w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
                            </div>
                            <x-secondary-button type="submit">Calificar</x-secondary-button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-stone-500 dark:text-stone-400">Nadie ha entregado esta tarea todavía.</p>
                @endforelse
            </div>
        @endif
    </div>
</div>
