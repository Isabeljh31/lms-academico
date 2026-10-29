{{--
    Fila de la tabla del detalle: una entrega individual, o un grupo (una fila por grupo;
    la nota se aplica a todos sus integrantes). Las ya calificadas (con "Incluir ya calificadas")
    muestran la nota y abren el mismo modal. Recibe $fila (de Index::filasPendientes o filasCalificadas),
    $grupal, $colorUrgencia, $puntoUrgencia y $enfoque.
--}}
@php
    $entrega = $fila['entrega'];
    $iniciales = fn (string $nombre) => collect(preg_split('/\s+/', trim($nombre)) ?: [])
        ->filter()->take(2)->map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)))->join('');
    // "Carolina Vargas": nombre y primer apellido.
    $corto = fn (string $nombre) => collect(preg_split('/\s+/', trim($nombre)) ?: [])->filter()->take(2)->join(' ');
    $miembros = $fila['miembros'];
    $esperando = match (true) {
        $fila['dias'] <= 0 => 'hoy',
        $fila['dias'] === 1 => '1 día',
        default => $fila['dias'].' días',
    };
    $quien = $grupal ? ($fila['grupo'] ?? 'Grupo').' ('.collect($miembros)->join(', ', ' y ').')' : ($miembros[0] ?? 'Estudiante');
@endphp

<tr wire:key="fila-{{ $fila['clave'] }}" class="border-t border-[#F4F0E8] text-[13.5px] transition-colors first:border-t-0 hover:bg-[#F4F0E8] motion-reduce:transition-none {{ $fila['calificada'] ? 'bg-[#F7F4EE] opacity-80' : '' }}"
    x-bind:class="densidad === 'comoda' ? '{{ $grupal ? 'h-[68px]' : 'h-14' }}' : '{{ $grupal ? 'h-14' : 'h-[42px]' }}'">
    <td class="py-1.5 pl-[18px] pr-3">
        @if ($grupal)
            <div class="flex items-center gap-2.5">
                <span class="flex shrink-0" aria-hidden="true">
                    @foreach (array_slice($miembros, 0, 2) as $indice => $miembro)
                        <span class="grid h-[30px] w-[30px] place-items-center rounded-full border-2 border-white bg-[var(--mint)] text-[10px] font-extrabold text-[var(--p700)] {{ $indice > 0 ? '-ml-[9px]' : '' }}">{{ $iniciales($miembro) }}</span>
                    @endforeach
                </span>
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-x-2">
                        <span class="text-sm font-semibold text-[var(--p900)]">{{ $fila['grupo'] ?? 'Grupo' }}</span>
                        <span class="whitespace-nowrap rounded-md bg-[var(--mint)] px-2 py-px text-[11.5px] font-bold text-[var(--p700)]">{{ count($miembros) === 1 ? '1 integrante' : count($miembros).' integrantes' }}</span>
                    </p>
                    <p class="mt-px text-[12.5px] text-[#57524A]" title="{{ collect($miembros)->join(', ') }}">
                        {{ collect(array_slice($miembros, 0, 2))->map($corto)->join(', ').(count($miembros) > 2 ? ' +'.(count($miembros) - 2) : '') }}
                    </p>
                </div>
            </div>
        @else
            <div class="flex items-center gap-2.5">
                <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-full bg-[var(--mint)] text-[10px] font-extrabold text-[var(--p700)]" aria-hidden="true">{{ $iniciales($miembros[0] ?? '') ?: '?' }}</span>
                <span class="break-words font-semibold text-[var(--p900)]">{{ $miembros[0] ?? 'Estudiante' }}</span>
            </div>
        @endif
    </td>
    <td class="whitespace-nowrap px-3 py-1.5 text-[13px] text-[#57524A]">
        <time datetime="{{ $entrega->updated_at->toIso8601String() }}">{{ rtrim($entrega->updated_at->locale('es')->isoFormat('D MMM'), '.') }} · {{ $entrega->updated_at->format('H:i') }}</time>
    </td>
    <td class="px-3 py-1.5">
        @if ($fila['calificada'])
            <span class="inline-flex items-center gap-[7px] whitespace-nowrap text-[12.5px] font-semibold text-[var(--p700)]">
                <x-icono nombre="check-circulo" class="h-[14px] w-[14px] shrink-0" />
                Nota {{ $fila['nota'] !== null ? rtrim(rtrim(number_format($fila['nota'], 2, '.', ''), '0'), '.') : '—' }}/20
            </span>
        @else
            <span class="inline-flex items-center gap-[7px] whitespace-nowrap text-[12.5px] font-semibold {{ $colorUrgencia[$fila['urgencia']] }}">
                <span class="h-[9px] w-[9px] shrink-0 rounded-full {{ $puntoUrgencia[$fila['urgencia']] }}" aria-hidden="true"></span>{{ $esperando }}
            </span>
        @endif
    </td>
    <td class="py-1.5 pl-3 pr-[18px]">
        @if ($fila['calificada'])
            <button type="button" wire:click="abrirCalificar({{ $entrega->id }})"
                aria-label="Ver o editar la nota de {{ $quien }}"
                class="inline-flex h-[30px] w-full items-center justify-center gap-[7px] whitespace-nowrap rounded-lg border-[1.5px] border-[#E0D8C8] bg-white text-[12px] font-bold text-[var(--p700)] transition-colors hover:bg-[var(--mint)] motion-reduce:transition-none {{ $enfoque }}">
                Ver / editar nota
            </button>
        @else
            <button type="button" wire:click="abrirCalificar({{ $entrega->id }})"
                aria-label="Calificar la entrega de {{ $quien }}"
                class="inline-flex h-[30px] w-full items-center justify-center gap-[7px] rounded-lg bg-[var(--p900)] text-[12.5px] font-bold text-white transition-colors hover:bg-[var(--p800)] motion-reduce:transition-none {{ $enfoque }}">
                <x-icono nombre="lapiz" class="h-[14px] w-[14px]" />
                Calificar
            </button>
        @endif
    </td>
</tr>
