@php
    // Pantalla Cursos del Profesor (diseño "Tu mapa de trabajo"), con los tokens del Panel.
    $enfoque = 'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-cielo focus-visible:ring-offset-2';
    $pastilla = 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-bold';
    $boton = "inline-flex min-h-11 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 text-sm font-semibold transition active:scale-[.97] motion-reduce:transition-none motion-reduce:active:scale-100 {$enfoque}";
    $botonSecundario = "{$boton} border border-slate-200 bg-white text-texto hover:border-azul-oscuro hover:text-azul-oscuro";
    // Filtro de estado como pestañas subrayadas.
    $pestana = "inline-flex min-h-11 items-center gap-1.5 border-b-2 px-0.5 text-[13px] font-semibold transition motion-reduce:transition-none {$enfoque} focus-visible:ring-offset-0";
    $pestanaActiva = 'border-azul-oscuro text-azul-oscuro';
    $pestanaInactiva = 'border-transparent text-slate-500 hover:text-azul-oscuro';

    $estadosCurso = \App\Services\Reportes\CursosProfesor::ESTADOS_CURSO;
    $modalidades = \App\Services\Reportes\CursosProfesor::MODALIDADES;
    $plural = fn (int $n, string $uno, string $varios) => $n === 1 ? "1 {$uno}" : "{$n} {$varios}";
    $cierreTexto = function ($curso, array $datos): string {
        if ($curso->estaFinalizado()) {
            return 'Curso cerrado';
        }

        return $datos['cierre']['texto'] ?? 'Sin fechas';
    };
    $restantes = $filtrados - $cursos->count();
@endphp

<div class="mx-auto flex w-full max-w-[1200px] flex-col gap-5">
    @include('livewire.docente.cursos._hero')

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-azul-oscuro">Cursos</p>
            <h2 class="text-xl font-bold leading-tight tracking-tight text-texto sm:text-[1.375rem]">Tu mapa de trabajo</h2>
        </div>
        <span class="inline-flex items-center whitespace-nowrap rounded-full bg-celeste px-3 py-1 text-xs font-bold leading-5 text-azul-oscuro">{{ $plural($total, 'curso', 'cursos') }}</span>
    </header>

    @if ($total === 0)
        <section class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-celeste text-azul-oscuro">
                <x-icono nombre="libro" class="h-6 w-6" />
            </span>
            <h2 class="text-lg font-semibold text-texto">Aún no tienes cursos a cargo</h2>
            <p class="max-w-md text-sm text-slate-600">Cuando el Supervisor te asigne un curso, como titular o como co-docente, aparecerá aquí.</p>
        </section>
    @else
        <section aria-labelledby="titulo-lista-cursos" class="flex flex-col gap-4">
            <h2 id="titulo-lista-cursos" class="sr-only">Lista de cursos</h2>

            {{-- Filtros: estado, búsqueda y orden (solo con MIN_CONTROLES cursos o más) --}}
            @if ($conControles)
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-b border-celeste">
                    <div class="-mb-px flex flex-wrap gap-x-5" role="group" aria-label="Filtrar por estado">
                        @foreach (\App\Livewire\Docente\Cursos\ListaCursos::ESTADOS as $clave => $etiqueta)
                            @continue($clave === 'despublicado' && $conteos[$clave] === 0 && $estado !== $clave)
                            <button type="button" wire:click="$set('estado', '{{ $clave }}')" wire:key="estado-{{ $clave }}"
                                aria-pressed="{{ $estado === $clave ? 'true' : 'false' }}"
                                class="{{ $pestana }} {{ $estado === $clave ? $pestanaActiva : $pestanaInactiva }}">
                                {{ $etiqueta }} <span class="text-[11px] tabular-nums {{ $estado === $clave ? 'text-azul-oscuro/70' : 'text-slate-400' }}">{{ $conteos[$clave] }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="flex w-full flex-wrap items-center gap-2 pb-2 lg:w-auto lg:flex-1 lg:flex-nowrap lg:justify-end">
                        <label class="flex min-h-11 min-w-0 flex-1 basis-60 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-slate-500 focus-within:border-azul-oscuro focus-within:ring-[3px] focus-within:ring-cielo-claro lg:max-w-xs">
                            <x-icono nombre="lupa" class="h-[18px] w-[18px] shrink-0" />
                            <span class="sr-only">Buscar curso por nombre o código</span>
                            <input type="search" wire:model.live.debounce.350ms="busqueda" placeholder="Buscar por nombre o código" autocomplete="off"
                                class="w-full min-w-0 border-0 bg-transparent p-0 text-sm text-texto placeholder:text-slate-500 focus:outline-none focus:ring-0">
                        </label>

                        <label class="flex min-h-11 items-center gap-1 rounded-lg border border-slate-200 bg-white pl-3 text-sm font-semibold text-texto focus-within:border-azul-oscuro focus-within:ring-[3px] focus-within:ring-cielo-claro">
                            <span class="whitespace-nowrap">Ordenar:</span>
                            <select wire:model.live="orden" class="min-h-11 rounded-lg border-0 bg-transparent py-0 pl-1 pr-8 text-sm font-semibold text-texto focus:outline-none focus:ring-0">
                                @foreach (\App\Livewire\Docente\Cursos\ListaCursos::ORDENES as $clave => $etiqueta)
                                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_17rem] lg:items-start">
                <div class="flex min-w-0 flex-col gap-4">
                    @if ($filtrados === 0)
                        <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-white px-6 py-10 text-center">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-celeste text-azul-oscuro">
                                <x-icono nombre="lupa" class="h-5 w-5" />
                            </span>
                            <h3 class="text-base font-semibold text-texto">Ningún curso coincide con el filtro</h3>
                            <p class="text-sm text-slate-600">Prueba con otro nombre o código, o quita los filtros para ver todos tus cursos.</p>
                            <button type="button" wire:click="limpiarFiltros" class="{{ $botonSecundario }}">Limpiar filtros</button>
                        </div>
                    @else
                        <ul class="flex flex-col gap-2.5" aria-label="Cursos">
                            @foreach ($cursos as $curso)
                                @php
                                    $datos = $resumen[$curso->id];
                                    [$estadoTexto, $estadoClase] = $estadosCurso[$curso->estado] ?? [ucfirst($curso->estado), 'bg-celeste text-slate-600'];
                                @endphp
                                <li wire:key="curso-{{ $curso->id }}">@include('livewire.docente.cursos._fila-curso')</li>
                            @endforeach
                        </ul>

                        @if ($conControles)
                            <div class="flex flex-col items-center gap-3">
                                <p class="text-[13px] text-slate-500" aria-live="polite">Mostrando {{ $cursos->count() }} de {{ $plural($filtrados, 'curso', 'cursos') }}</p>
                                @if ($restantes > 0)
                                    <button type="button" wire:click="verMas" class="{{ $botonSecundario }}">
                                        {{ match (true) {
                                            $restantes > \App\Livewire\Docente\Cursos\ListaCursos::POR_TANDA => 'Ver '.\App\Livewire\Docente\Cursos\ListaCursos::POR_TANDA.' más (quedan '.$restantes.')',
                                            $restantes === 1 => 'Ver el curso restante',
                                            default => 'Ver los '.$restantes.' restantes',
                                        } }}
                                    </button>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>

                <div class="lg:sticky lg:top-6">@include('livewire.docente.cursos._radar')</div>
            </div>
        </section>
    @endif
</div>
