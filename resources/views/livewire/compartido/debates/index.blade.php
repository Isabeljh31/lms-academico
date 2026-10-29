<div class="py-8">
    <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Debates" icono="chat" descripcion="{{ $curso->nombre }}">
            <x-gestion.boton variante="secundario" icono="flecha-izquierda" :href="route('cursos.show', $curso)">Volver al curso</x-gestion.boton>
            @if ($puedeGestionar)
                <x-gestion.boton icono="mas" wire:click="abrirNuevo">Nuevo debate</x-gestion.boton>
            @endif
        </x-gestion.encabezado>

        <x-gestion.alertas />

        @if ($debates->isEmpty())
            <x-gestion.vacio icono="chat" titulo="Este curso todavía no tiene debates"
                :descripcion="$puedeGestionar ? 'Plantea una pregunta o consigna para que el curso la discuta. Los estudiantes solo ven los debates publicados.' : 'Cuando el docente publique un debate, aparecerá aquí.'">
                @if ($puedeGestionar)
                    <x-gestion.boton icono="mas" wire:click="abrirNuevo">Crear el primer debate</x-gestion.boton>
                @endif
            </x-gestion.vacio>
        @else
            <x-gestion.tarjeta :relleno="false">
                <ul class="divide-y divide-celeste/60 dark:divide-azul-borde-oscuro">
                    @foreach ($debates as $debate)
                        <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center" wire:key="debate-{{ $debate->id }}">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('cursos.debates.show', [$curso, $debate]) }}" wire:navigate
                                        class="font-medium text-stone-800 hover:text-azul-oscuro hover:underline dark:text-stone-100">{{ $debate->titulo }}</a>
                                    @if (! $debate->estaPublicado())
                                        <span class="rounded-full bg-celeste px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200">Borrador</span>
                                    @endif
                                    @if ($debate->estaCerrado())
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Cerrado</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 line-clamp-2 text-sm text-stone-500 dark:text-stone-400">{{ $debate->consigna }}</p>
                                <p class="mt-1 inline-flex items-center gap-1 text-xs text-stone-500">
                                    <x-icono nombre="chat" class="h-3.5 w-3.5" />
                                    {{ $debate->comentarios_count }} {{ $debate->comentarios_count === 1 ? 'comentario' : 'comentarios' }}
                                    · {{ $debate->created_at->formatoFechaHora() }}
                                </p>
                            </div>
                            @if ($puedeGestionar)
                                <div class="flex shrink-0 flex-wrap gap-1">
                                    <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="editar({{ $debate->id }})">Editar</x-gestion.boton>
                                    <x-gestion.boton variante="fantasma" tamano="sm" :icono="$debate->estaCerrado() ? 'refrescar' : 'prohibido'" wire:click="alternarCierre({{ $debate->id }})">
                                        {{ $debate->estaCerrado() ? 'Reabrir' : 'Cerrar' }}
                                    </x-gestion.boton>
                                    <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminar({{ $debate->id }})"
                                        wire:confirm="¿Eliminar el debate {{ $debate->titulo }} y todos sus comentarios? Esta acción no se puede deshacer.">Eliminar</x-gestion.boton>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-gestion.tarjeta>
            <div>{{ $debates->links() }}</div>
        @endif
    </div>

    @if ($mostrarFormulario)
        <x-gestion.modal :titulo="$debateEditId ? 'Editar debate' : 'Nuevo debate'" cerrar="$set('mostrarFormulario', false)" ancho="max-w-2xl" icono="chat">
            <x-gestion.campo etiqueta="Título" nombre="titulo" requerido ayuda="Máximo {{ \App\Models\Debate::MAX_TITULO }} caracteres.">
                <x-gestion.control id="titulo" wire:model="titulo" maxlength="{{ \App\Models\Debate::MAX_TITULO }}" placeholder="¿Qué opinan sobre...?" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Consigna" nombre="consigna" requerido ayuda="Explica qué deben discutir y cómo participar.">
                <x-gestion.control tipo="textarea" id="consigna" rows="5" wire:model="consigna" maxlength="10000" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Estado" nombre="estado" requerido ayuda="Al publicarlo por primera vez se avisa a los estudiantes del curso.">
                <x-gestion.desplegable id="estado" etiqueta="Estado" wire:model="estado" :opciones="\App\Models\Debate::ESTADOS" />
            </x-gestion.campo>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarFormulario', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton icono="check" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">Guardar debate</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
