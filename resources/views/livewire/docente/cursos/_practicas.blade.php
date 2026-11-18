{{--
    Carpeta "Prácticas de campo" del detalle del curso (Profesor), encima de
    las semanas. Las prácticas (Práctica 01..N) las define el Supervisor en el
    formulario del curso; el profesor solo prepara la entrega de cada una: una
    tarea calificable donde el estudiante sube sus archivos (Tareas\Formulario ?practica=N).
--}}
@php
    $carpeta = $secciones['practicas'];
    $abiertaInicial = in_array(\App\Livewire\Cursos\Show::CLAVE_PRACTICAS, $semanasAbiertas, true);
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
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
    class="rounded-[20px] bg-white shadow-[0_1px_2px_rgba(9,59,59,0.06),0_6px_20px_rgba(9,59,59,0.05)] overflow-hidden">
    <button type="button" id="practicas-encabezado" x-on:click="alternar()"
        aria-controls="practicas-contenido" aria-expanded="{{ $abiertaInicial ? 'true' : 'false' }}" x-bind:aria-expanded="abierta.toString()"
        class="group flex w-full items-center gap-4 px-4 py-3 text-left {{ $enfoque }}">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[var(--p900)] text-white">
            <x-icono nombre="maletin" class="h-6 w-6" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-xs font-semibold uppercase tracking-wide text-[#6B6559]">Fuera de la numeración semanal</span>
            <span id="titulo-practicas-gestion" class="block text-base font-semibold text-[var(--p900)] group-hover:text-[var(--p700)]">Prácticas de campo</span>
            <span class="mt-0.5 block text-[13px] text-[#6B6559]">
                {{ $carpeta['total'] }} {{ $carpeta['total'] === 1 ? 'práctica' : 'prácticas' }} · {{ $carpeta['preparadas'] }} con entrega preparada
            </span>
        </span>
        <x-icono nombre="flecha-abajo" class="h-5 w-5 shrink-0 text-[#6B6559] transition-transform duration-200 motion-reduce:transition-none {{ $abiertaInicial ? 'rotate-180' : '' }}" x-bind:class="{ 'rotate-180': abierta }" />
    </button>

    <div id="practicas-contenido" role="region" aria-labelledby="practicas-encabezado"
        x-show="abierta" @unless ($abiertaInicial) style="display: none" @endunless
        class="border-t border-[#ECE6DA] bg-[#F4F0E8]">
        <ul aria-label="Prácticas de campo del curso">
            @foreach ($carpeta['items'] as $item)
                @php $tarea = $item['tarea']; @endphp
                <li wire:key="practica-profesor-{{ $item['numero'] }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-[#ECE6DA] px-4 py-3 first:border-t-0 sm:pl-8">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] {{ $tarea ? 'bg-[var(--mint)] text-[var(--p700)]' : 'border border-dashed border-[#E0D8C8] bg-white text-[#6B6559]' }}">
                        <x-icono nombre="documento" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1 basis-48">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-[15px] font-semibold text-[var(--p900)]">{{ $item['etiqueta'] }}</h3>
                            @if ($tarea)
                                <span class="{{ $pastilla }} {{ $tarea->esVisibleParaEstudiante() ? 'bg-[var(--ok100)] text-[var(--ok900)]' : 'bg-[var(--mint)] text-[#57524A]' }}">{{ $tarea->etiquetaEstadoPublicacion() }}</span>
                            @else
                                <span class="{{ $pastilla }} bg-[var(--warn200)] text-[var(--warn800)]">Sin entrega</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-[13px] text-[#6B6559]">
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
                            <a href="{{ route('cursos.tareas.show', [$curso, $tarea]) }}" wire:navigate class="{{ $botonFila }} text-[var(--p700)] hover:bg-[var(--mint)]">
                                <x-icono nombre="ojo" class="h-4 w-4" /> Ver entregas
                            </a>
                            @if ($editable)
                                <a href="{{ route('cursos.tareas.editar', [$curso, $tarea]) }}" wire:navigate class="{{ $botonFila }} text-[var(--p700)] hover:bg-[var(--mint)]" aria-label="Editar la entrega de {{ $item['etiqueta'] }}">
                                    <x-icono nombre="lapiz" class="h-4 w-4" /> Editar
                                </a>
                            @endif
                        @elseif ($editable)
                            <a href="{{ route('cursos.tareas.crear', [$curso, 'practica' => $item['numero']]) }}" wire:navigate
                                class="{{ $botonFila }} bg-[var(--p900)] text-white hover:bg-[var(--p800)]" aria-label="Crear la entrega de {{ $item['etiqueta'] }}">
                                <x-icono nombre="mas" class="h-4 w-4" /> Crear entrega
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
