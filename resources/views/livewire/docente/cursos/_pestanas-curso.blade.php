{{-- Pestañas hacia las pantallas del curso (encabezado completo y compacto). La activa la fija EncabezadoCurso::$activa. --}}
<nav aria-label="Secciones del curso" class="flex flex-wrap gap-x-[26px] border-t border-[#ECE6DA] bg-[#F7F4EE] px-7">
    @foreach ($pestanas as $pestana)
        @php $esActiva = $pestana['clave'] === $activa; @endphp
        <a href="{{ $pestana['ruta'] }}" wire:navigate @if ($esActiva) aria-current="page" @endif
            class="relative inline-flex min-h-[48px] items-center gap-[7px] text-sm {{ $enfoque }} {{ $esActiva ? 'font-bold text-[var(--p900)] after:absolute after:inset-x-0 after:bottom-0 after:h-[3px] after:rounded-t-[3px] after:bg-[var(--p900)]' : 'text-[#57524A] hover:text-[var(--p900)]' }}">
            {{ $pestana['texto'] }}
            @if (($pestana['cuenta'] ?? 0) > 0)
                <span class="rounded-full bg-[#ECE6DA] px-[7px] py-px text-[11.5px] font-bold text-[#57524A]">{{ $pestana['cuenta'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
