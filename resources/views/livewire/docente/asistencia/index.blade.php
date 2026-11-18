{{--
    Asistencia del curso para el Profesor (maquetas «Asistencia»). Es la vista de
    Asistencia\Index para un usuario con solo el rol profesor: mismas acciones
    (crearSesion, eliminarSesion, abrirPaseLista, marcarTodos, guardarAsistencia,
    alternarUsoAsistencia) y mismos permisos que livewire/asistencia/index.blade.php,
    que sigue siendo la del Supervisor. Los indicadores, la vista por semana y el
    resumen por estudiante los lee el componente Docente\Asistencia\Tablero.
--}}
@use('App\Services\Reportes\DetalleCursoProfesor')
@php
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
    $tarjeta = 'rounded-[20px] bg-white shadow-[0_1px_2px_rgba(9,59,59,0.06),0_6px_20px_rgba(9,59,59,0.05)]';
    $botonContorno = "inline-flex h-9 items-center justify-center gap-[7px] whitespace-nowrap rounded-[10px] border-[1.5px] border-[#E0D8C8] bg-white px-3.5 text-[13px] font-bold text-[var(--p700)] transition-colors hover:bg-[var(--mint)] motion-reduce:transition-none {$enfoque}";
    $botonOscuro = "inline-flex h-10 items-center justify-center gap-[7px] whitespace-nowrap rounded-[11px] bg-[var(--p900)] px-4 text-[13.5px] font-bold text-white transition-colors hover:bg-[var(--p800)] motion-reduce:transition-none {$enfoque}";
    $virtual = $curso->modalidad === 'virtual';
    $paseLista = $vista === 'pase_lista' && $sesionActiva;
@endphp

{{-- relative: ancestro posicionado para los textos sr-only (radios y buscadores), como en las demás pantallas del profesor. --}}
<div class="relative mx-auto flex w-full max-w-[1320px] flex-col gap-4">
    <livewire:docente.cursos.encabezado-curso :curso="$curso" :compacto="true" activa="asistencia" :key="'encabezado-asistencia-'.$curso->id" />

    <x-gestion.alertas />

    @if ($curso->estaFinalizado())
        <div role="alert" class="flex items-start gap-3 rounded-xl bg-[var(--warn100)] px-4 py-3 text-sm text-[var(--warn800)]">
            <x-icono nombre="archivo" class="h-5 w-5 shrink-0" />
            <p>Curso archivado (finalizado): la asistencia se puede consultar, pero no admite cambios.</p>
        </div>
    @endif

    @if ($paseLista)
        @include('livewire.docente.asistencia._pase-lista')
    @else
        @php
            $totalSemanas = $curso->totalSemanas();
            $subtitulo = collect([
                $curso->nombre,
                $totalSemanas === 1 ? '1 semana' : "{$totalSemanas} semanas",
                $virtual ? null : DetalleCursoProfesor::horario($curso),
                $virtual ? 'sin asistencia por clase' : 'se pasa lista en cada clase',
            ])->filter()->join(' · ');
        @endphp

        <div class="mt-1.5 flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-[30px] font-extrabold leading-tight tracking-[-0.02em] text-[var(--p900)]">Asistencia</h1>
                <p class="mt-0.5 text-[14px] text-[#57524A]">{{ $subtitulo }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($this->editable && ! $virtual)
                    <button type="button" wire:click="$set('mostrarFormSesion', true)" class="{{ $botonContorno }} h-8 px-3 text-[12.5px]">
                        <x-icono nombre="mas" class="h-3.5 w-3.5" /> Nueva clase
                    </button>
                @endif
                <span class="inline-flex h-9 items-center rounded-full border-[1.5px] border-[#E0D8C8] bg-white px-4 text-[13px] font-bold text-[var(--p900)]">
                    Modo: {{ $virtual ? 'sin lista por clase' : 'lista por clase' }}
                </span>
            </div>
        </div>

        @if ($mostrarFormSesion && $this->editable && ! $virtual)
            <section aria-labelledby="titulo-nueva-clase" class="{{ $tarjeta }} p-6">
                <h2 id="titulo-nueva-clase" class="text-[17px] font-extrabold text-[var(--p900)]">Nueva clase</h2>
                <p class="mt-0.5 text-[13px] text-[#6B6559]">Para una clase fuera del horario del curso. Se agrega a la semana que corresponda a su fecha.</p>
                <form x-on:submit.prevent="$wire.crearSesion().then(() => Livewire.dispatch('asistencia-actualizada'))" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="Título" nombre="sesionTitulo" requerido>
                        <x-gestion.control id="sesionTitulo" wire:model="sesionTitulo" placeholder="Clase 1: Introducción" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Fecha y hora" nombre="sesionFecha" requerido>
                        <x-gestion.control id="sesionFecha" type="datetime-local" wire:model="sesionFecha" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Duración (minutos)" nombre="sesionDuracion" requerido>
                        <x-gestion.control id="sesionDuracion" type="number" min="15" max="480" wire:model="sesionDuracion" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Descripción" nombre="sesionDescripcion">
                        <x-gestion.control id="sesionDescripcion" wire:model="sesionDescripcion" placeholder="Aula: 204" />
                    </x-gestion.campo>
                    <div class="flex gap-2 sm:col-span-2 sm:justify-end">
                        <button type="button" wire:click="$set('mostrarFormSesion', false)" class="{{ $botonContorno }}">Cancelar</button>
                        <button type="submit" class="{{ $botonOscuro }}"><x-icono nombre="check" class="h-4 w-4" /> Crear clase</button>
                    </div>
                </form>
            </section>
        @endif

        @if (! $virtual && ! $curso->usa_asistencia)
            <section class="{{ $tarjeta }} flex flex-col items-center gap-3 px-6 py-12 text-center">
                <span class="grid h-12 w-12 place-items-center rounded-full bg-[var(--mint)] text-[var(--p700)]"><x-icono nombre="ojo-tachado" class="h-6 w-6" /></span>
                <h2 class="text-lg font-extrabold text-[var(--p900)]">Asistencia omitida en este curso</h2>
                <p class="max-w-md text-[13.5px] text-[#57524A]">El control de asistencia está desactivado. Los registros anteriores, si los hay, se conservan.</p>
                @if ($this->puedeConfigurar)
                    <button type="button" wire:click="alternarUsoAsistencia" class="{{ $botonOscuro }} mt-1">
                        <x-icono nombre="check" class="h-4 w-4" /> Reactivar el control de asistencia
                    </button>
                @endif
            </section>
        @else
            <livewire:docente.asistencia.tablero :curso="$curso" :key="'tablero-asistencia-'.$curso->id" />
            {{-- Solicitud de cambio de clase (menú ⋮ de cada clase): modal propio, solo en la vista del Profesor. --}}
            @if ($this->editable)
                <livewire:docente.asistencia.solicitud-cambio :curso="$curso" :key="'solicitud-cambio-'.$curso->id" />
                {{-- «Enlace de esta clase» (menú ⋮ de una clase remota): modal propio. --}}
                <livewire:docente.asistencia.enlace-clase :curso="$curso" :key="'enlace-clase-'.$curso->id" />
            @endif
        @endif

        {{-- «Omitir asistencia» (ERS RF-73): acción secundaria del curso, lejos de las acciones principales. --}}
        @if (! $virtual && $this->puedeConfigurar && $curso->usa_asistencia)
            <div class="flex justify-end">
                <button type="button" wire:click="alternarUsoAsistencia"
                    wire:confirm="¿Omitir el control de asistencia en este curso? Los registros ya guardados se conservan y puedes volver a activarlo."
                    class="inline-flex min-h-9 items-center gap-1.5 rounded-lg px-2 text-[12.5px] font-semibold text-[#6B6559] hover:text-[var(--p900)] hover:underline {{ $enfoque }}">
                    <x-icono nombre="ojo-tachado" class="h-3.5 w-3.5" /> Omitir asistencia en este curso
                </button>
            </div>
        @endif
    @endif
</div>
