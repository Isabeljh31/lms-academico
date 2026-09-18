@php
    // Compartido entre roles; Estudiante usa la paleta fría nueva y
    // Profesor/Administrador conservan la anterior — ver ARQUITECTURA.md §
    // nueva paleta de color.
    $paletaFria = ! $this->puedeGestionar;
@endphp

<div class="py-8">
    {{-- Ancho de contenedor estandarizado (max-w-6xl) para todas las vistas del estudiante. --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <a href="{{ route('cursos.tareas.index', $tarea->curso) }}" wire:navigate
                class="text-sm {{ $paletaFria ? 'text-azul-oscuro dark:text-azul-fondo' : 'text-menta-oscuro dark:text-menta-base' }} hover:underline">&larr; Tareas</a>
            <h1 class="text-2xl font-bold {{ $paletaFria ? 'text-texto dark:text-slate-100' : 'text-stone-900 dark:text-stone-100' }}">{{ $tarea->titulo }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ $tarea->modulo?->titulo }} · Vence {{ $tarea->fecha_entrega->format('d/m/Y H:i') }} · Peso {{ $tarea->peso_nota }}
            </p>
        </div>

        @if (session('estado'))
            <div class="rounded-lg bg-exito-base/15 text-exito-oscuro px-4 py-3 text-sm">{{ session('estado') }}</div>
        @endif

        @if ($tarea->descripcion)
            <div class="bg-white rounded-xl border p-6 {{ $paletaFria ? 'dark:bg-azul-superficie-oscura border-celeste dark:border-azul-borde-oscuro' : 'dark:bg-stone-800 border-arena-medio dark:border-stone-700' }}">
                <p class="{{ $paletaFria ? 'text-slate-600 dark:text-slate-300' : 'text-stone-600 dark:text-stone-300' }} whitespace-pre-line">{{ $tarea->descripcion }}</p>
            </div>
        @endif

        @if ($paletaFria)
            {{-- Rediseño visual del rol estudiante: entregar o ver mi entrega calificada. --}}
            <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-6">
                @if ($tarea->esGrupal())
                    <p class="inline-flex items-center gap-1.5 text-xs font-semibold text-lavanda-oscuro bg-lavanda-fondo/30 rounded-full px-2.5 py-1 mb-4">
                        <x-icono nombre="usuarios" class="h-3.5 w-3.5" /> Entrega grupal
                    </p>
                @endif

                @if ($this->miEntrega)
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-texto dark:text-slate-100">
                            {{ $tarea->esGrupal() ? 'Entrega de tu grupo' : 'Tu entrega' }}
                        </h2>
                        <x-badge-estado :estado="$this->miEntrega->estado" />
                    </div>
                    @if ($tarea->esGrupal() && $this->miGrupo)
                        <p class="text-xs text-slate-400 mt-1">{{ $this->miGrupo->nombre }} — {{ $this->miGrupo->estudiantes->pluck('name')->implode(', ') }}</p>
                    @endif
                    <a href="{{ $this->miEntrega->archivo_url }}" target="_blank" class="text-sm text-azul-oscuro dark:text-azul-fondo hover:underline flex items-center gap-1 mt-2">
                        <x-icono nombre="material" class="h-4 w-4" /> Ver archivo entregado
                    </a>
                    @if ($this->miEntrega->comentario)
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">"{{ $this->miEntrega->comentario }}"</p>
                    @endif

                    @if ($this->miEntrega->estado === 'calificado')
                        <div class="mt-4 pt-4 border-t border-celeste dark:border-azul-borde-oscuro">
                            <p class="text-sm font-semibold text-texto dark:text-slate-100">Nota: {{ $this->miEntrega->nota }} / 20</p>
                            @if ($this->miEntrega->feedback)
                                <p class="text-sm text-slate-600 dark:text-slate-300 mt-1">{{ $this->miEntrega->feedback }}</p>
                            @endif
                        </div>
                    @endif
                @elseif ($tarea->esGrupal() && ! $this->miGrupo)
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Todavía no perteneces a ningún grupo en este curso. Pide a tu profesor que te agregue a uno antes de poder entregar esta tarea.
                    </p>
                @else
                    <h2 class="font-semibold text-texto dark:text-slate-100 mb-1">Entregar tarea</h2>
                    @if ($tarea->esGrupal() && $this->miGrupo)
                        <p class="text-xs text-slate-400 mb-4">
                            Entregarás por {{ $this->miGrupo->nombre }}: {{ $this->miGrupo->estudiantes->pluck('name')->implode(', ') }}.
                        </p>
                    @else
                        <div class="mb-4"></div>
                    @endif
                    <form wire:submit="entregar" class="space-y-4">
                        <div>
                            <input type="file" wire:model="archivo"
                                class="block w-full text-sm text-slate-600 dark:text-slate-400 file:me-4 file:rounded-md file:border-0 file:bg-azul-oscuro file:px-3 file:py-1.5 file:text-white file:text-sm hover:file:bg-azul-oscuro/90">
                            <span wire:loading wire:target="archivo" class="text-xs text-slate-400">Subiendo…</span>
                            <x-input-error :messages="$errors->get('archivo')" class="mt-2" />
                        </div>
                        <div>
                            <textarea wire:model="comentario" rows="2" placeholder="Comentario (opcional)…"
                                class="block w-full rounded-md border-slate-300 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 focus:border-azul-fondo focus:ring-azul-fondo"></textarea>
                        </div>
                        <x-primary-button>Entregar</x-primary-button>
                    </form>
                @endif
            </div>
        @else
            {{-- Vista de profesor/administrador: calificar entregas. En una tarea
                 grupal, las entregas de un mismo grupo se muestran juntas y se
                 califican en un solo formulario (la nota se propaga a todos). --}}
            @php
                // Cada grupo colapsa a una sola tarjeta; una entrega individual
                // (grupo_id null) sigue mostrándose como su propia tarjeta.
                $entregasAgrupadas = $entregas->groupBy(fn ($e) => $e->grupo_id ?? 'individual-'.$e->id);
            @endphp

            <div class="space-y-4">
                <h2 class="font-semibold text-stone-800 dark:text-stone-100">Entregas ({{ $entregasAgrupadas->count() }})</h2>

                @forelse ($entregasAgrupadas as $miembrosDelGrupo)
                    @php $entrega = $miembrosDelGrupo->first(); @endphp
                    <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4" wire:key="entrega-{{ $entrega->id }}">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-stone-800 dark:text-stone-100">
                                @if ($entrega->grupo_id)
                                    {{ $entrega->grupo->nombre }}
                                    <span class="font-normal text-stone-400 text-xs">({{ $miembrosDelGrupo->pluck('estudiante.name')->implode(', ') }})</span>
                                @else
                                    {{ $entrega->estudiante->name }}
                                @endif
                            </span>
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
                                <x-input-error :messages="$errors->get('notas.'.$entrega->id)" class="mt-1" />
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
