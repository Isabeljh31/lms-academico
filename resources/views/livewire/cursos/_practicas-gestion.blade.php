{{--
    Carpeta "Prácticas de campo" en la pantalla del curso para Supervisor (y
    profesores con otro rol además). Práctica 01..N las define el Supervisor
    en el formulario del curso; quien gestiona el curso prepara la entrega de
    cada una (Tareas\Formulario ?practica=N). Mismo contenido que
    livewire/docente/cursos/_practicas, con los colores de esta pantalla.
--}}
@php
    $carpeta = $secciones['practicas'];
    $abiertaInicial = in_array(\App\Livewire\Cursos\Show::CLAVE_PRACTICAS, $semanasAbiertas, true);
@endphp
<section aria-labelledby="titulo-practicas-gestion" wire:key="practicas-gestion-{{ $curso->id }}" x-data="{ abierta: @js($abiertaInicial) }"
    class="rounded-xl border shadow-sm {{ $esAdminVista ? 'border-arena-medio border-l-4 border-l-terracota-base bg-arena-claro/60 dark:border-stone-700 dark:border-l-terracota-base dark:bg-stone-800' : 'border-celeste border-l-4 border-l-azul-oscuro bg-celeste/60 dark:border-azul-borde-oscuro dark:border-l-azul-oscuro dark:bg-azul-superficie-oscura' }}">
    <button type="button" id="practicas-encabezado" x-on:click="abierta = ! abierta" aria-controls="practicas-contenido" x-bind:aria-expanded="abierta.toString()"
        class="flex w-full items-center gap-3 p-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-cielo">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-white shadow-sm {{ $esAdminVista ? 'bg-terracota-oscuro' : 'bg-azul-oscuro' }}">
            <x-icono nombre="maletin" class="h-6 w-6" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-[11px] font-semibold uppercase tracking-wider {{ $esAdminVista ? 'text-terracota-oscuro dark:text-terracota-fondo' : 'text-azul-oscuro dark:text-azul-fondo' }}">Fuera de la numeración semanal</span>
            <span id="titulo-practicas-gestion" class="block text-base font-bold text-stone-800 dark:text-stone-100">Prácticas de campo</span>
            <span class="mt-0.5 block text-xs text-stone-500 dark:text-stone-400">
                {{ $carpeta['total'] }} {{ $carpeta['total'] === 1 ? 'práctica' : 'prácticas' }} - {{ $carpeta['preparadas'] }} con entrega preparada
            </span>
        </span>
        <x-icono nombre="flecha-abajo" class="h-5 w-5 shrink-0 text-stone-500 transition-transform duration-200 motion-reduce:transition-none" x-bind:class="{ 'rotate-180': abierta }" />
    </button>

    <ul id="practicas-contenido" role="region" aria-labelledby="practicas-encabezado" x-show="abierta" @unless ($abiertaInicial) style="display: none" @endunless
        class="divide-y divide-celeste/60 border-t border-celeste/60 bg-white/70 dark:divide-azul-borde-oscuro dark:border-azul-borde-oscuro dark:bg-azul-pagina-oscura/40">
        @foreach ($carpeta['items'] as $item)
            @php $tarea = $item['tarea']; @endphp
            <li wire:key="practica-gestion-{{ $item['numero'] }}" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 sm:pl-8">
                <x-icono nombre="documento" class="h-5 w-5 shrink-0 {{ $tarea ? 'text-azul-oscuro dark:text-cielo' : 'text-stone-400' }}" />
                <div class="min-w-0 flex-1 basis-48">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-stone-800 dark:text-stone-100">{{ $item['etiqueta'] }}</span>
                        @if ($tarea)
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $tarea->esVisibleParaEstudiante() ? 'bg-exito-base/15 text-exito-oscuro' : $claseBadgeNeutra }}">{{ $tarea->etiquetaEstadoPublicacion() }}</span>
                        @else
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Sin entrega</span>
                        @endif
                    </div>
                    <p class="text-xs text-stone-500 dark:text-stone-400">
                        @if ($tarea)
                            @if ($tarea->titulo !== $item['etiqueta']){{ $tarea->titulo }} - @endif
                            Entrega hasta {{ $tarea->fecha_entrega?->translatedFormat('d M, H:i') ?? 'sin fecha' }} - {{ $tarea->entregas_count }} {{ $tarea->entregas_count === 1 ? 'entrega recibida' : 'entregas recibidas' }}
                        @else
                            Aún no tiene su entrega preparada.
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-1">
                    @if ($tarea)
                        <x-gestion.boton variante="secundario" tamano="sm" icono="ojo" :href="route('cursos.tareas.show', [$curso, $tarea])">Ver entregas</x-gestion.boton>
                        @if ($editable)
                            <x-gestion.boton variante="secundario" tamano="sm" icono="lapiz" :href="route('cursos.tareas.editar', [$curso, $tarea])">Editar</x-gestion.boton>
                        @endif
                    @elseif ($editable)
                        <x-gestion.boton tamano="sm" icono="mas" :href="route('cursos.tareas.crear', [$curso, 'practica' => $item['numero']])">Crear entrega</x-gestion.boton>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>
