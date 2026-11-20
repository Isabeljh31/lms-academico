@php
    // Pantalla Cursos del Profesor (diseño "Tu mapa de trabajo", mock verde), con la paleta .tema-profesor del layout.
    $ListaCursos = \App\Livewire\Docente\Cursos\ListaCursos::class;
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
    $pastilla = 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-bold';
    $boton = "inline-flex min-h-11 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-[10px] px-4 text-sm font-semibold transition-colors motion-reduce:transition-none {$enfoque}";
    $botonSecundario = "{$boton} border-[1.5px] border-[#E0D8C8] bg-white text-[var(--p700)] hover:bg-[var(--mint)]";
    $enlaceDiscreto = "inline-flex items-center gap-1.5 rounded-lg text-[13px] font-semibold text-[var(--p700)] hover:underline {$enfoque}";
    // Chips de filtro: "sin contenido" en el ámbar de advertencia; el activo en verde oscuro.
    $chipBase = "inline-flex min-h-9 items-center gap-1.5 whitespace-nowrap rounded-full border-2 border-[#111111] px-3 text-[13px] font-semibold transition-colors motion-reduce:transition-none {$enfoque}";
    $chipActivo = 'bg-[var(--p900)] text-white hover:bg-[var(--p800)]';

    $estadosCurso = \App\Services\Reportes\CursosProfesor::ESTADOS_CURSO;
    $modalidades = \App\Services\Reportes\CursosProfesor::MODALIDADES;
    $plural = fn (int $n, string $uno, string $varios) => $n === 1 ? "1 {$uno}" : "{$n} {$varios}";
    $restantes = $filtrados - $cursos->count();
    $verArchivados = $filtroActivo === 'archivados';
    $chips = collect([
        'sin_contenido' => ['etiqueta' => 'Sin contenido la próxima semana', 'total' => $sinContenidoProximaSemana, 'icono' => 'alerta', 'clase' => 'bg-[var(--warn200)] text-[#6b4a0d] hover:bg-[#ffe68a]'],
        'borradores' => ['etiqueta' => 'Borradores', 'total' => $totalBorradores, 'icono' => null, 'clase' => 'bg-white text-[var(--p900)] hover:bg-[var(--mint)]'],
    ])->filter(fn (array $chip, string $clave) => $chip['total'] > 0 || $filtroActivo === $clave)->all();
    $cifras = [
        ['valor' => $total, 'etiqueta' => $total === 1 ? 'Curso a cargo' : 'Cursos a cargo', 'color' => 'text-[var(--p900)]'],
        ['valor' => $porCalificar, 'etiqueta' => 'Entregas por calificar', 'color' => 'text-[var(--p700)]'],
        ['valor' => $matriculados, 'etiqueta' => 'Estudiantes matriculados', 'color' => 'text-[var(--p900)]'],
    ];
    // Estado vacío de cada filtro (sin búsqueda): [título, texto].
    $vacios = [
        'activos' => ['No tienes cursos activos', 'Los cursos que el Supervisor archivó están en "Ver cursos archivados".'],
        'sin_contenido' => ['Todos tus cursos tienen contenido para la próxima semana ✓', 'Aquí aparecen los cursos cuya semana siguiente aún no tiene lecciones, tareas ni quizzes.'],
        'borradores' => ['No tienes cursos en borrador', 'Los borradores son cursos que el Supervisor aún no publica: los estudiantes no los ven.'],
        'archivados' => ['No tienes cursos archivados', 'Los cursos que el Supervisor archive aparecerán aquí, en solo lectura.'],
    ];
@endphp

{{-- Contenedor propio de la pantalla: hasta 1480 px de contenido + 40 px por lado, centrado en la ventana
     (sale del límite de 1440 px del <main> del layout sin cambiarlo para las demás pantallas). --}}
<div class="mx-[calc(50%-min(780px,50vw))] flex flex-col px-4 sm:px-6 lg:px-10">
    @include('livewire.docente.cursos._hero')

    {{-- Franja de cifras: banda blanca a todo el ancho de la ventana. --}}
    <section aria-label="Cifras de tus cursos" class="mx-[calc(50%-50vw)] mt-6 border-y-2 border-[#111111] bg-white">
        <dl class="mx-auto grid max-w-[1560px] grid-cols-3 px-4 py-4 sm:px-6 sm:py-5 lg:px-10">
            @foreach ($cifras as $cifra)
                <div class="flex flex-col items-center border-r border-[#ECE6DA] px-2 text-center last:border-r-0">
                    <dt class="order-2 text-xs text-[#6B6559] sm:text-[13px]">{{ $cifra['etiqueta'] }}</dt>
                    <dd class="order-1 text-2xl font-extrabold tabular-nums tracking-[-0.02em] sm:text-[28px] sm:leading-tight {{ $cifra['color'] }}">{{ $cifra['valor'] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <div class="pb-4 pt-9">
        <header class="flex flex-wrap items-center justify-between gap-x-5 gap-y-3">
            <div class="flex min-w-0 items-baseline gap-3">
                <h2 class="text-2xl font-extrabold leading-tight tracking-[-0.015em] text-[var(--p900)]">Tu mapa de trabajo</h2>
                <span class="whitespace-nowrap text-[13px] text-[#6B6559]">{{ $plural($total, 'curso activo', 'cursos activos') }}</span>
            </div>
            @if ($conControles && $total + $totalArchivados > 0)
                <div class="flex min-h-10 items-center gap-1 rounded-[10px] border-2 border-[#111111] bg-white pl-2.5 text-[13px] font-semibold text-[var(--p900)]">
                    <span class="whitespace-nowrap" aria-hidden="true">Ordenar:</span>
                    <x-docente.desplegable wire:model.live="orden" :opciones="$ListaCursos::ORDENES" etiqueta="Ordenar" placeholder="Más por calificar" variante="pastilla" />
                </div>
            @endif
        </header>

        {{-- Chips solo para lo que hay que atender (N > 0); pulsar de nuevo o "Mostrar todos" quita el filtro.
             Los archivados se abren con el enlace del final de la lista. --}}
        @if ($verArchivados)
            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1">
                <p class="text-sm font-bold text-[var(--p900)]">Cursos archivados <span class="ml-0.5 text-[13px] font-normal tabular-nums text-[#8F887B]">{{ $conteos['archivados'] }}</span></p>
                <button type="button" wire:click="$set('filtro', 'activos')" class="{{ $enlaceDiscreto }}">
                    <x-icono nombre="flecha-izquierda" class="h-[14px] w-[14px]" />
                    Volver a cursos activos
                </button>
            </div>
        @elseif ($chips !== [])
            <div class="mt-4 flex flex-wrap items-center gap-2.5" role="group" aria-label="Filtrar cursos">
                @foreach ($chips as $clave => $chip)
                    @php $activo = $filtroActivo === $clave; @endphp
                    <button type="button" wire:click="$set('filtro', '{{ $activo ? 'activos' : $clave }}')" wire:key="chip-{{ $clave }}"
                        aria-pressed="{{ $activo ? 'true' : 'false' }}"
                        class="{{ $chipBase }} {{ $activo ? $chipActivo : $chip['clase'] }}">
                        @if ($chip['icono'])
                            <x-icono :nombre="$chip['icono']" class="h-[15px] w-[15px] shrink-0" />
                        @endif
                        {{ $chip['etiqueta'] }} ({{ $chip['total'] }})
                        @if ($activo)
                            <x-icono nombre="cerrar" class="h-[13px] w-[13px] shrink-0" />
                        @endif
                    </button>
                @endforeach
                @if ($filtroActivo !== 'activos')
                    <button type="button" wire:click="$set('filtro', 'activos')" class="{{ $enlaceDiscreto }}">Mostrar todos</button>
                @endif
            </div>
        @endif

        @if ($total === 0 && $totalArchivados === 0)
            <section class="mt-6 flex flex-col items-center gap-3 rounded-2xl bg-white px-6 py-12 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-[var(--mint)] text-[var(--p700)]">
                    <x-icono nombre="libro" class="h-6 w-6" />
                </span>
                <h2 class="text-lg font-bold text-[var(--p900)]">Aún no tienes cursos a cargo</h2>
                <p class="max-w-md text-sm text-[#57524A]">Cuando el Supervisor te asigne un curso, como titular o como co-docente, aparecerá aquí.</p>
            </section>
        @else
            <section aria-labelledby="titulo-lista-cursos" class="flex flex-col">
                <h2 id="titulo-lista-cursos" class="sr-only">Lista de cursos</h2>

                @unless ($verArchivados)
                    <div class="mt-5">@include('livewire.docente.cursos._radar')</div>
                @endunless

                <div class="mt-5 flex min-w-0 flex-col gap-4">
                    @if ($filtrados === 0)
                        @php [$tituloVacio, $textoVacio] = $vacios[$filtroActivo]; @endphp
                        <div class="flex flex-col items-center gap-3 rounded-2xl bg-white px-6 py-10 text-center">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--mint)] text-[var(--p700)]">
                                <x-icono :nombre="$conBusqueda ? 'lupa' : 'check-circulo'" class="h-5 w-5" />
                            </span>
                            @if ($conBusqueda)
                                <h3 class="text-base font-bold text-[var(--p900)]">Ningún curso coincide con la búsqueda</h3>
                                <p class="text-sm text-[#57524A]">Prueba con otro nombre o código, o quita los filtros para ver tus cursos activos.</p>
                                <button type="button" wire:click="limpiarFiltros" class="{{ $botonSecundario }}">Limpiar filtros</button>
                            @else
                                <h3 class="text-base font-bold text-[var(--p900)]">{{ $tituloVacio }}</h3>
                                <p class="max-w-md text-sm text-[#57524A]">{{ $textoVacio }}</p>
                                @if ($filtroActivo !== 'activos')
                                    <button type="button" wire:click="$set('filtro', 'activos')" class="{{ $botonSecundario }}">Ver cursos activos</button>
                                @endif
                            @endif
                        </div>
                    @else
                        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4" aria-label="{{ $verArchivados ? 'Cursos archivados' : 'Cursos' }}">
                            @foreach ($cursos as $curso)
                                @php
                                    $datos = $resumen[$curso->id];
                                    $estadoTexto = $estadosCurso[$curso->estado][0] ?? ucfirst($curso->estado);
                                @endphp
                                <li wire:key="curso-{{ $curso->id }}" class="flex">@include('livewire.docente.cursos._fila-curso')</li>
                            @endforeach
                        </ul>

                        @if ($conControles)
                            <div class="flex flex-col items-center gap-3">
                                <p class="text-[13px] text-[#6B6559]" aria-live="polite">Mostrando {{ $cursos->count() }} de {{ $plural($filtrados, 'curso', 'cursos') }}</p>
                                @if ($restantes > 0)
                                    <button type="button" wire:click="verMas" class="{{ $botonSecundario }}">
                                        {{ match (true) {
                                            $restantes > $ListaCursos::POR_TANDA => 'Ver '.$ListaCursos::POR_TANDA.' más (quedan '.$restantes.')',
                                            $restantes === 1 => 'Ver el curso restante',
                                            default => 'Ver los '.$restantes.' restantes',
                                        } }}
                                    </button>
                                @endif
                            </div>
                        @endif
                    @endif

                    {{-- Archivados aparte: enlace discreto al final (solo si hay). --}}
                    @if ($verArchivados)
                        <div class="flex justify-center">
                            <button type="button" wire:click="$set('filtro', 'activos')" class="{{ $enlaceDiscreto }}">
                                <x-icono nombre="flecha-izquierda" class="h-[14px] w-[14px]" />
                                Volver a cursos activos
                            </button>
                        </div>
                    @elseif ($totalArchivados > 0)
                        <div class="flex justify-center">
                            <button type="button" wire:click="$set('filtro', 'archivados')" class="{{ $enlaceDiscreto }}">
                                Ver cursos archivados ({{ $conteos['archivados'] }})
                                <x-icono nombre="flecha-derecha" class="h-[14px] w-[14px]" />
                            </button>
                        </div>
                    @endif
                </div>
            </section>
        @endif
    </div>
</div>
