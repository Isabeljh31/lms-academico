{{--
    Tarea de la lista izquierda; al elegirla se abre a la derecha. Si ya está calificada
    del todo (solo con "Incluir ya calificadas"), se muestra atenuada y con check.
    Recibe $grupoTarea (de Index::agruparPorTarea), $conCurso (sin agrupar por curso),
    $seleccionada, $colorUrgencia, $puntoUrgencia, $plural y $enfoque.
--}}
@php
    $Index = \App\Livewire\Profesor\PorCalificar\Index::class;
    $activa = $seleccionada && $seleccionada['tarea']->id === $grupoTarea['tarea']->id;
    $primeraLista = $grupoTarea['primera'];
    $avance = $grupoTarea['avance'];
    $etiqueta = 'whitespace-nowrap inline-flex items-center gap-1 rounded-full bg-[#F4F0E8] px-[7px] py-px text-[11px] font-bold text-[#57524A]';
@endphp

<button type="button" wire:click="$set('tarea', {{ $grupoTarea['tarea']->id }})" wire:key="tarea-lista-{{ $grupoTarea['tarea']->id }}"
    @if ($activa) aria-current="true" @endif
    class="my-0.5 block rounded-[10px] border-[1.5px] px-3 py-[9px] text-left transition-colors motion-reduce:transition-none {{ $conCurso ? 'w-full' : 'ml-11 w-[calc(100%-2.75rem)]' }} {{ $activa ? 'border-[var(--p700)] bg-[var(--mint)]' : 'border-transparent hover:bg-[#F4F0E8]' }} {{ $grupoTarea['completa'] && ! $activa ? 'opacity-70' : '' }} {{ $enfoque }}">
    @if ($conCurso)
        <span class="block break-words text-[11px] font-bold uppercase tracking-[0.08em] text-[#57524A]">{{ $grupoTarea['tarea']->curso->nombre }} · {{ $Index::etiquetaSemana($grupoTarea['semana']) }}</span>
    @endif
    <span class="flex items-start gap-1.5">
        @if ($grupoTarea['completa'])
            <x-icono nombre="check-circulo" class="mt-px h-[15px] w-[15px] shrink-0 text-[var(--p700)]" />
        @endif
        <span class="block break-words text-[13.5px] font-bold leading-tight text-[var(--p900)]">{{ $grupoTarea['tarea']->titulo }}</span>
    </span>
    <span class="mt-1 flex flex-wrap items-center gap-1">
        <span class="{{ $etiqueta }}">
            <x-icono nombre="{{ $grupoTarea['grupal'] ? 'usuarios' : 'persona' }}" class="h-3 w-3" />
            {{ $grupoTarea['grupal'] ? 'Grupal' : 'Individual' }}
        </span>
        @if ($mostrarTipo)
            <span class="{{ $etiqueta }}">{{ $Index::TIPOS[$grupoTarea['tipo']] }}</span>
        @endif
    </span>
    <span class="mt-1 block text-[12.5px] text-[#3F3B34]">
        {{ $avance['calificadas'] }} de {{ $avance['total'] }} {{ $grupoTarea['grupal'] ? ($avance['total'] === 1 ? 'grupo calificado' : 'grupos calificados') : ($avance['total'] === 1 ? 'calificada' : 'calificadas') }}
        @if ($grupoTarea['total'] > 0)
            · <strong class="text-[var(--p900)]">{{ $grupoTarea['total'] }}</strong> por calificar
        @endif
    </span>
    @if ($grupoTarea['total'] > 0)
        <span class="mt-1 flex items-center gap-1.5 text-[12.5px] font-semibold {{ $colorUrgencia[$primeraLista['urgencia']] }}">
            <span class="h-[9px] w-[9px] shrink-0 rounded-full {{ $puntoUrgencia[$primeraLista['urgencia']] }}" aria-hidden="true"></span>
            la más antigua espera {{ $Index::textoEspera($primeraLista['dias']) }}
        </span>
    @endif
</button>
