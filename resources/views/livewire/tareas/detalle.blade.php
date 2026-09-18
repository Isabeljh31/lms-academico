@php
    // Paleta fría institucional (ver /DISEÑO FIGMA y ARQUITECTURA.md)
    // aplicada al estudiante. El profesor/admin conserva la paleta cálida.
    $paletaFria = auth()->user()->hasRole('estudiante');
@endphp

<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if (session('estado'))
            <div class="rounded-lg bg-exito-base/15 text-exito-oscuro px-4 py-3 text-sm font-medium">{{ session('estado') }}</div>
        @endif

        <div class="bg-white rounded-xl border p-6 {{ $paletaFria ? 'dark:bg-azul-superficie-oscura border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none' : 'dark:bg-stone-800 border-arena-medio dark:border-stone-700' }}">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $tarea->curso->nombre }}</span>
                    <h1 class="text-2xl font-bold mt-1 {{ $paletaFria ? 'text-texto dark:text-slate-100' : 'text-stone-900 dark:text-stone-100' }}">{{ $tarea->titulo }}</h1>
                </div>
                <div class="text-right">
                    <span class="text-xs text-slate-400 block">Vencimiento</span>
                    <span class="text-sm font-medium {{ $paletaFria ? 'text-texto dark:text-slate-200' : 'text-stone-700 dark:text-stone-200' }}">
                        {{ $tarea->fecha_limite?->translatedFormat('d M Y, H:i') ?? 'Sin fecha límite' }}
                    </span>
                    <span class="text-xs text-slate-400 block mt-1">Peso: {{ $tarea->peso_nota }}%</span>
                </div>
            </div>

            @if ($tarea->descripcion)
                <p class="mt-4 {{ $paletaFria ? 'text-slate-600 dark:text-slate-300' : 'text-stone-600 dark:text-stone-300' }}">{{ $tarea->descripcion }}</p>
            @endif
        </div>

        @if ($paletaFria)
            {{-- Rediseño visual del rol estudiante: entregar o ver mi entrega calificada. --}}
            <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-6">
                @if ($tarea->esGrupal())
                    <p class="inline-flex items-center gap-1.5 text-xs font-semibold text-lavanda-oscuro bg-lavanda-fondo/30 rounded-full px-2.5 py-1 mb-4">
                        <x-icono nombre="usuarios" class="h-3.5 w-3.5" /> Entrega grupal
                    </p>
                @endif

                @if ($this->miEntrega)
                    {{-- Recibo de confirmación de entrega del sistema LearnUp --}}
                    <div class="rounded-xl border border-exito-base/40 bg-exito-base/10 dark:bg-emerald-950/30 p-4 mb-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-exito-oscuro text-white text-xs font-bold">✓</span>
                                <div>
                                    <h3 class="text-sm font-bold text-exito-oscuro dark:text-emerald-300">Confirmación de Entrega Oficial</h3>
                                    <p class="text-xs text-slate-600 dark:text-slate-300">
                                        Número de confirmación: <span class="font-mono font-bold text-slate-800 dark:text-slate-100">REC-{{ str_pad($this->miEntrega->id, 6, '0', STR_PAD_LEFT) }}-{{ $this->miEntrega->created_at->format('Y') }}</span>
                                    </p>
                                </div>
                            </div>
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400 bg-white/60 dark:bg-black/20 px-2 py-1 rounded">
                                Fecha: {{ $this->miEntrega->created_at->format('d/m/Y H:i:s') }}
                            </span>
                        </div>
                    </div>

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
                            <div class="flex items-center justify-between">
                                <p class="text-base font-bold text-texto dark:text-slate-100">
                                    Nota: <span class="text-azul-oscuro dark:text-cielo font-black text-lg">{{ $this->miEntrega->nota }}</span> / 20
                                </p>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $this->miEntrega->nota >= 10.5 ? 'bg-verde-fondo/60 text-verde-oscuro' : 'bg-terracota-base/20 text-terracota-oscuro' }}">
                                    {{ $this->miEntrega->nota >= 10.5 ? 'Aprobado' : 'Desaprobado' }}
                                </span>
                            </div>
                            @if ($this->miEntrega->feedback)
                                <div class="mt-2 bg-slate-50 dark:bg-slate-800/60 rounded-lg p-3 border border-slate-100 dark:border-slate-700">
                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block mb-0.5">Comentarios del docente:</span>
                                    <p class="text-sm text-slate-700 dark:text-slate-200">{{ $this->miEntrega->feedback }}</p>
                                </div>
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
