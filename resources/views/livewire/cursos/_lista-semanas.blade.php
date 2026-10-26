<ul class="divide-y {{ $esAdminVista ? 'divide-arena-medio/60 dark:divide-stone-700' : 'divide-celeste/60 dark:divide-azul-borde-oscuro' }}">
    @foreach ($secciones['semanas'] as $seccion)
        @php $modulo = $seccion['modulo']; @endphp
        <li wire:key="seccion-{{ $seccion['semana'] }}" class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold {{ $modulo ? $claseSemanaConContenido : $claseSemanaVacia }}">
                {{ $seccion['semana'] }}
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-medium {{ $modulo ? 'text-stone-800 dark:text-stone-100' : 'text-stone-500 dark:text-stone-400' }}">
                        {{ $seccion['etiqueta'] }}@if ($modulo?->tema())<span class="font-normal text-stone-600 dark:text-stone-300">: {{ $modulo->tema() }}</span>@endif
                    </h3>
                    @if ($modulo)
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $modulo->esVisibleParaEstudiante() ? 'bg-exito-base/15 text-exito-oscuro' : $claseBadgeNeutra }}">
                            {{ $modulo->etiquetaEstadoPublicacion() }}
                        </span>
                    @endif
                </div>
                <p class="mt-0.5 text-xs text-stone-500 dark:text-stone-400">
                    @if ($seccion['rango'])
                        {{ $seccion['rango'][0]->translatedFormat('d M') }} – {{ $seccion['rango'][1]->translatedFormat('d M') }}
                        @if ($seccion['clases'] > 0) - {{ $seccion['clases'] }} {{ $seccion['clases'] === 1 ? 'clase' : 'clases' }} @endif
                        -
                    @endif
                    @if ($modulo)
                        {{ $modulo->lecciones_count }} lecciones - {{ $modulo->tareas_count }} tareas - {{ $modulo->quizzes_count }} quizzes
                    @else
                        Sin contenido
                    @endif
                </p>
            </div>
            @if ($this->puedeGestionar)
                <div class="flex flex-wrap gap-1">
                    @if ($modulo)
                        <x-gestion.boton tamano="sm" icono="documento" :href="route('lecciones.index', $modulo)">Lecciones</x-gestion.boton>
                        @if ($editable)
                            <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="abrirEditarModulo({{ $modulo->id }})">Editar</x-gestion.boton>
                            <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminarModulo({{ $modulo->id }})"
                                wire:confirm="¿Vaciar {{ $seccion['etiqueta'] }}? Se eliminan sus lecciones, tareas y quizzes. Si tiene entregas o resultados de estudiantes no se eliminará.">
                                Vaciar
                            </x-gestion.boton>
                        @endif
                    @elseif ($editable)
                        <x-gestion.boton variante="secundario" tamano="sm" icono="mas" wire:click="prepararSeccion({{ $seccion['semana'] }})">Preparar semana</x-gestion.boton>
                    @endif
                </div>
            @endif
        </li>
    @endforeach
</ul>
