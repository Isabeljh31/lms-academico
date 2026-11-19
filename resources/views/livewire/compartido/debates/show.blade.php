<div class="py-8">
    <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado :titulo="$debate->titulo" icono="chat" descripcion="Debate de {{ $curso->nombre }}">
            <x-gestion.boton variante="secundario" icono="flecha-izquierda" :href="route('cursos.debates.index', $curso)">Todos los debates</x-gestion.boton>
            @if ($puedeModerar)
                <x-gestion.boton variante="secundario" :icono="$debate->estaCerrado() ? 'refrescar' : 'prohibido'" wire:click="alternarCierre">
                    {{ $debate->estaCerrado() ? 'Reabrir debate' : 'Cerrar debate' }}
                </x-gestion.boton>
            @endif
        </x-gestion.encabezado>

        <x-gestion.alertas />

        <x-gestion.tarjeta titulo="Consigna" :descripcion="($debate->autor ? 'Publicado por '.$debate->autor->name.' · ' : '').$debate->created_at->formatoFechaHora()">
            <p class="whitespace-pre-line break-words text-sm text-stone-700 dark:text-stone-200">{{ $debate->consigna }}</p>
            @if (! $debate->estaPublicado())
                <p class="mt-3 text-xs font-medium text-slate-500 dark:text-slate-400">Borrador: los estudiantes todavía no lo ven y no se puede comentar.</p>
            @endif
        </x-gestion.tarjeta>

        @if ($debate->estaCerrado())
            <div role="status" class="flex items-start gap-3 rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                <x-icono nombre="prohibido" class="h-5 w-5 shrink-0" />
                <p class="flex-1">Este debate está cerrado: puedes leerlo, pero ya no admite comentarios.</p>
            </div>
        @endif

        <x-gestion.tarjeta titulo="Comentarios" :descripcion="$totalComentarios.' '.($totalComentarios === 1 ? 'comentario' : 'comentarios')" :relleno="false">
            @if ($comentarios->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-stone-500 dark:text-stone-400">Todavía no hay comentarios.</p>
            @else
                <ul class="divide-y divide-celeste/60 dark:divide-azul-borde-oscuro">
                    @foreach ($comentarios as $comentario)
                        <li class="space-y-3 px-4 py-4" wire:key="comentario-{{ $comentario->id }}">
                            @include('livewire.compartido.debates._comentario', ['comentario' => $comentario])

                            @if ($comentario->respuestas->isNotEmpty() || $respondiendoA === $comentario->id)
                                <ul class="ml-11 space-y-3 border-l-2 border-celeste pl-4 dark:border-azul-borde-oscuro" aria-label="Respuestas">
                                    @foreach ($comentario->respuestas as $respuestaComentario)
                                        <li wire:key="respuesta-{{ $respuestaComentario->id }}">
                                            @include('livewire.compartido.debates._comentario', ['comentario' => $respuestaComentario])
                                        </li>
                                    @endforeach

                                    @if ($respondiendoA === $comentario->id)
                                        <li class="space-y-2">
                                            <x-gestion.campo etiqueta="Tu respuesta" nombre="respuesta">
                                                <x-gestion.control tipo="textarea" id="respuesta" rows="3" wire:model="respuesta" maxlength="{{ \App\Models\Debate::MAX_COMENTARIO }}" />
                                            </x-gestion.campo>
                                            <div class="flex justify-end gap-2">
                                                <x-gestion.boton variante="secundario" tamano="sm" wire:click="cancelarRespuesta">Cancelar</x-gestion.boton>
                                                <x-gestion.boton tamano="sm" icono="check" wire:click="enviarRespuesta" wire:loading.attr="disabled" wire:target="enviarRespuesta">Responder</x-gestion.boton>
                                            </div>
                                        </li>
                                    @endif
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($comentarios->hasPages())
                    <div class="border-t border-celeste px-4 py-3 dark:border-azul-borde-oscuro">{{ $comentarios->links() }}</div>
                @endif
            @endif
        </x-gestion.tarjeta>

        @if ($puedeComentar)
            <x-gestion.tarjeta titulo="Participa en el debate">
                <div class="space-y-3">
                    <x-gestion.campo etiqueta="Tu comentario" nombre="nuevoComentario" ayuda="Máximo {{ \App\Models\Debate::MAX_COMENTARIO }} caracteres.">
                        <x-gestion.control tipo="textarea" id="nuevoComentario" rows="4" wire:model="nuevoComentario" maxlength="{{ \App\Models\Debate::MAX_COMENTARIO }}" />
                    </x-gestion.campo>
                    <div class="flex justify-end">
                        <x-gestion.boton icono="chat" wire:click="comentar" wire:loading.attr="disabled" wire:target="comentar">Publicar comentario</x-gestion.boton>
                    </div>
                </div>
            </x-gestion.tarjeta>
        @endif
    </div>
</div>
