@php
    // El widget se monta globalmente para los 3 roles (layouts/app.blade.php),
    // así que su color se condiciona al rol en vez de duplicar la vista.
    // Estudiante y Profesor usan la paleta fría con acento lavanda (acento
    // secundario del bot, ver ARQUITECTURA.md § nueva paleta de color);
    // Supervisor conserva la paleta cálida.
    $paletaFria = auth()->check() && ! auth()->user()->hasRole('supervisor');
@endphp

<div class="fixed bottom-5 right-5 z-50" @if (! $botHabilitado) style="display:none" @endif>
    @if ($botHabilitado && $abierto)
        <div class="mb-3 w-80 sm:w-96 h-[28rem] rounded-xl shadow-xl border flex flex-col overflow-hidden
            {{ $paletaFria ? 'bg-white dark:bg-azul-superficie-oscura border-celeste dark:border-azul-borde-oscuro' : 'bg-white dark:bg-stone-800 border-arena-medio dark:border-stone-700' }}">
            <div class="flex items-center justify-between px-4 py-3 text-white {{ $paletaFria ? 'bg-lavanda-oscuro' : 'bg-menta-oscuro' }}">
                <div>
                    <p class="text-sm font-semibold">Asistente del curso</p>
                    @if ($this->cursoActual)
                        <p class="text-xs {{ $paletaFria ? 'text-lavanda-fondo/90' : 'text-menta-fondo/80' }}">{{ $this->cursoActual->nombre }}</p>
                    @endif
                    @if (! $activo)
                        <p class="text-[10px] {{ $paletaFria ? 'text-coral-fondo' : 'text-terracota-fondo' }} mt-0.5">Modo simulado (sin API key)</p>
                    @endif
                </div>
                <button wire:click="alternar" class="text-white/80 hover:text-white">
                    <x-icono nombre="cerrar" class="h-5 w-5" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-4 py-3 space-y-3" x-data x-init="$el.scrollTop = $el.scrollHeight" x-effect="$el.scrollTop = $el.scrollHeight">
                @forelse ($mensajes as $m)
                    <div class="flex {{ $m['rol'] === 'usuario' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm whitespace-pre-line
                            {{ $m['rol'] === 'usuario'
                                ? ($paletaFria ? 'bg-lavanda-fondo/40 text-lavanda-oscuro dark:bg-lavanda-oscuro/30 dark:text-lavanda-fondo' : 'bg-menta-fondo text-menta-oscuro dark:bg-menta-oscuro/30 dark:text-menta-fondo')
                                : ($paletaFria ? 'bg-celeste/30 dark:bg-azul-borde-oscuro/60 text-slate-700 dark:text-slate-200' : 'bg-arena-claro dark:bg-stone-700 text-stone-700 dark:text-stone-200') }}">
                            {{ $m['contenido'] }}
                        </div>
                    </div>
                @empty
                    <p class="text-sm {{ $paletaFria ? 'text-slate-400' : 'text-stone-400' }} text-center mt-8">
                        Pregúntame sobre este curso: tareas, unidades, quizzes…
                    </p>
                @endforelse

                <div wire:loading wire:target="enviar" class="text-xs {{ $paletaFria ? 'text-slate-400' : 'text-stone-400' }}">El asistente está escribiendo…</div>
            </div>

            <form wire:submit="enviar" class="border-t p-3 flex items-center gap-2 {{ $paletaFria ? 'border-celeste dark:border-azul-borde-oscuro' : 'border-arena-medio dark:border-stone-700' }}">
                @if ($paletaFria)
                    <input wire:model="mensaje" type="text" placeholder="Escribe tu pregunta…"
                        class="flex-1 rounded-md border-slate-300 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 focus:border-lavanda-oscuro focus:ring-lavanda-oscuro text-sm">
                    <button type="submit" wire:loading.attr="disabled" wire:target="enviar"
                        class="px-3 py-2 rounded-md bg-lavanda-oscuro text-white text-sm font-semibold hover:bg-lavanda-oscuro/90 disabled:opacity-50">
                        Enviar
                    </button>
                @else
                    <input wire:model="mensaje" type="text" placeholder="Escribe tu pregunta…"
                        class="flex-1 rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
                    <button type="submit" wire:loading.attr="disabled" wire:target="enviar"
                        class="px-3 py-2 rounded-md bg-menta-oscuro text-white text-sm font-semibold hover:bg-menta-oscuro/90 disabled:opacity-50">
                        Enviar
                    </button>
                @endif
            </form>
        </div>
    @endif

    @if ($botHabilitado)
        <button wire:click="alternar"
            class="h-14 w-14 rounded-full text-white shadow-lg flex items-center justify-center transition
                {{ $paletaFria ? 'bg-lavanda-oscuro hover:bg-lavanda-oscuro/90' : 'bg-menta-oscuro hover:bg-menta-oscuro/90' }}"
            title="Asistente del LMS">
            <x-icono :nombre="$abierto ? 'cerrar' : 'chat'" class="h-6 w-6" />
        </button>
    @endif
</div>
