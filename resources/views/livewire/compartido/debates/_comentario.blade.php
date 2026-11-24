{{--
    Un comentario o respuesta del debate. Variables: $comentario, $docentes (ids),
    $puedeComentar y $puedeModerar (calculados una vez con DebatePolicy; cada
    acción vuelve a autorizar en el servidor). El contenido siempre va escapado.
--}}
@php
    $esDocente = in_array($comentario->user_id, $docentes, true);
    $puedeEditar = $puedeComentar && $comentario->user_id === auth()->id();
    $puedeEliminar = $puedeModerar || $puedeEditar;
@endphp
<div class="flex gap-3">
    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-celeste text-xs font-semibold text-azul-oscuro dark:bg-azul-borde-oscuro dark:text-azul-fondo" aria-hidden="true">
        {{ mb_strtoupper(mb_substr($comentario->autor?->name ?? '?', 0, 1)) }}
    </span>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="text-sm font-semibold text-stone-800 dark:text-stone-100">{{ $comentario->autor?->name ?? 'Usuario eliminado' }}</span>
            @if ($esDocente)
                <span class="rounded-full bg-azul-oscuro/10 px-2 py-0.5 text-[11px] font-semibold text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-azul-fondo">Docente</span>
            @endif
            <span class="text-xs text-stone-500 dark:text-stone-400">
                {{ $comentario->created_at->formatoFechaHora() }}@if ($comentario->editado_en) · editado @endif
            </span>
        </div>

        @if ($editandoId === $comentario->id)
            <div class="mt-2 space-y-2">
                <x-gestion.campo etiqueta="Editar comentario" nombre="edicion">
                    <x-gestion.control tipo="textarea" id="edicion" rows="3" wire:model="edicion" maxlength="{{ \App\Models\Debate::MAX_COMENTARIO }}" />
                </x-gestion.campo>
                <div class="flex justify-end gap-2">
                    <x-gestion.boton variante="secundario" tamano="sm" wire:click="cancelarEdicion">Cancelar</x-gestion.boton>
                    <x-gestion.boton tamano="sm" icono="check" wire:click="guardarEdicion" wire:loading.attr="disabled" wire:target="guardarEdicion">Guardar</x-gestion.boton>
                </div>
            </div>
        @else
            <p class="mt-1 whitespace-pre-line break-words text-sm text-stone-700 dark:text-stone-200">{{ $comentario->contenido }}</p>
        @endif

        @if ($editandoId !== $comentario->id && ($puedeEditar || $puedeEliminar || (! $comentario->esRespuesta() && $puedeComentar)))
            <div class="mt-1 flex flex-wrap gap-1">
                @if (! $comentario->esRespuesta() && $puedeComentar)
                    <x-gestion.boton variante="fantasma" tamano="sm" icono="chat" wire:click="responder({{ $comentario->id }})">Responder</x-gestion.boton>
                @endif
                @if ($puedeEditar)
                    <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="editar({{ $comentario->id }})">Editar</x-gestion.boton>
                @endif
                @if ($puedeEliminar)
                    <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminar({{ $comentario->id }})"
                        wire:confirm="{{ $comentario->esRespuesta() ? '¿Eliminar esta respuesta?' : '¿Eliminar este comentario y sus respuestas?' }}">Eliminar</x-gestion.boton>
                @endif
            </div>
        @endif
    </div>
</div>
