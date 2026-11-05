{{--
    Carpeta "Prácticas de campo" del detalle del curso (Profesor), encima de
    las semanas. Las prácticas (Práctica 01..N) las define el Supervisor en el
    formulario del curso; el profesor solo prepara la entrega de cada una: una
    tarea calificable donde el estudiante sube sus archivos (Tareas\Formulario ?practica=N).
--}}
@php
    $carpeta = $secciones['practicas'];
    $abiertaInicial = in_array(\App\Livewire\Cursos\Show::CLAVE_PRACTICAS, $semanasAbiertas, true);
    $enfoque = 'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-cielo focus-visible:ring-offset-2';
    $pastilla = 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-bold';
    $botonFila = "inline-flex min-h-11 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-3 text-sm font-semibold transition motion-reduce:transition-none {$enfoque}";
@endphp
<section aria-labelledby="titulo-practicas-gestion" wire:key="practicas-gestion-{{ $curso->id }}"
    x-data="{
        get abierta() { return (this.$wire.semanasAbiertas ?? []).map(Number).includes(0) },
        alternar() {
            const otras = (this.$wire.semanasAbiertas ?? []).map(Number).filter(s => s !== 0);
            this.$wire.semanasAbiertas = this.abierta ? otras : [...otras, 0];
        },
    }"
    class="rounded-xl border border-celeste bg-white shadow-sm overflow-hidden">
    <button type="button" id="practicas-encabezado" x-on:click="alternar()"
        aria-controls="practicas-contenido" aria-expanded="{{ $abiertaInicial ? 'true' : 'false' }}" x-bind:aria-expanded="abierta.toString()"
        class="group flex w-full items-center gap-4 px-4 py-3 text-left {{ $enfoque }}">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-azul-oscuro text-white">
            <x-icono nombre="maletin" class="h-6 w-6" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Fuera de la numeración semanal</span>
            <span id="titulo-practicas-gestion" class="block text-base font-semibold text-texto group-hover:text-azul-oscuro">Prácticas de campo</span>
            <span class="mt-0.5 block text-[13px] text-slate-500">
                {{ $carpeta['total'] }} {{ $carpeta['total'] === 1 ? 'práctica' : 'prácticas' }} · {{ $carpeta['preparadas'] }} con entrega preparada
            </span>
        </span>
        <x-icono nombre="flecha-abajo" class="h-5 w-5 shrink-0 text-slate-500 transition-transform duration-200 motion-reduce:transition-none {{ $abiertaInicial ? 'rotate-180' : '' }}" x-bind:class="{ 'rotate-180': abierta }" />
    </button>

    <div id="practicas-contenido" role="region" aria-labelledby="practicas-encabezado"
        x-show="abierta" @unless ($abiertaInicial) style="display: none" @endunless
        class="border-t border-celeste bg-fondo/60">
        <ul aria-label="Prácticas de campo del curso">
            @foreach ($carpeta['items'] as $item)
                @php $tarea = $item['tarea']; @endphp
                <li wire:key="practica-profesor-{{ $item['numero'] }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-celeste px-4 py-3 first:border-t-0 sm:pl-8">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] {{ $tarea ? 'bg-celeste text-azul-oscuro' : 'border border-dashed border-slate-400 bg-white text-slate-500' }}">
                        <x-icono nombre="documento" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1 basis-48">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-[15px] font-semibold text-texto">{{ $item['etiqueta'] }}</h3>
                            @if ($tarea)
                                <span class="{{ $pastilla }} {{ $tarea->esVisibleParaEstudiante() ? 'bg-exito-base/15 text-exito-oscuro' : 'bg-celeste text-slate-600' }}">{{ $tarea->etiquetaEstadoPublicacion() }}</span>
                            @else
                                <span class="{{ $pastilla }} bg-amber-100 text-amber-800">Sin entrega</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-[13px] text-slate-500">
                            @if ($tarea)
                                @if ($tarea->titulo !== $item['etiqueta']){{ $tarea->titulo }} · @endif
                                Entrega hasta {{ $tarea->fecha_entrega?->translatedFormat('d M, H:i') ?? 'sin fecha' }}
                                · {{ $tarea->entregas_count }} {{ $tarea->entregas_count === 1 ? 'entrega recibida' : 'entregas recibidas' }}
                            @else
                                Prepara la entrega para que los estudiantes suban sus archivos.
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-1">
                        @if ($tarea)
                            <a href="{{ route('cursos.tareas.show', [$curso, $tarea]) }}" wire:navigate class="{{ $botonFila }} text-azul-oscuro hover:bg-celeste">
                                <x-icono nombre="ojo" class="h-4 w-4" /> Ver entregas
                            </a>
                            @if ($editable)
                                <a href="{{ route('cursos.tareas.editar', [$curso, $tarea]) }}" wire:navigate class="{{ $botonFila }} text-azul-oscuro hover:bg-celeste" aria-label="Editar la entrega de {{ $item['etiqueta'] }}">
                                    <x-icono nombre="lapiz" class="h-4 w-4" /> Editar
                                </a>
                            @endif
                        @elseif ($editable)
                            <a href="{{ route('cursos.tareas.crear', [$curso, 'practica' => $item['numero']]) }}" wire:navigate
                                class="{{ $botonFila }} bg-azul-oscuro text-white hover:bg-azul-oscuro/90" aria-label="Crear la entrega de {{ $item['etiqueta'] }}">
                                <x-icono nombre="mas" class="h-4 w-4" /> Crear entrega
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
