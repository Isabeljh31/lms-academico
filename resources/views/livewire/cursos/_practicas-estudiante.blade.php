{{--
    Carpeta "Prácticas de campo" del estudiante, encima de las semanas (como
    la carpeta de Blackboard). Muestra Práctica 01..N, las que definió el
    Supervisor: cada una abre su entrega (la tarea donde sube sus archivos);
    una práctica sin entrega visible (sin preparar, en borrador o programada,
    RF-38) se muestra como "Aún no disponible", sin revelar nada más.
--}}
@php $carpeta = $secciones['practicas']; @endphp
<section aria-labelledby="titulo-practicas" wire:key="practicas-est-{{ $curso->id }}" x-data="{ expandido: false }"
    class="overflow-hidden rounded-xl border border-azul-fondo/25 border-l-4 border-l-azul-fondo bg-celeste/50 shadow-sm dark:border-azul-borde-oscuro dark:border-l-azul-fondo dark:bg-azul-oscuro/15 dark:shadow-none">
    <button type="button" x-on:click="expandido = ! expandido" x-bind:aria-expanded="expandido.toString()" aria-controls="practicas-est-contenido"
        class="flex w-full items-center gap-4 p-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-cielo">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-azul-fondo text-white shadow-sm">
            <x-icono nombre="maletin" class="h-6 w-6" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-azul-fondo dark:text-cielo">Fuera de la numeración semanal</span>
            <span id="titulo-practicas" class="block text-lg font-bold text-texto dark:text-slate-100">Prácticas de campo</span>
            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $carpeta['total'] }} {{ $carpeta['total'] === 1 ? 'práctica' : 'prácticas' }}</span>
        </span>
        <svg class="h-5 w-5 shrink-0 text-azul-fondo transition-transform duration-200" x-bind:class="expandido ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <ul id="practicas-est-contenido" x-show="expandido" x-collapse style="display: none"
        class="divide-y divide-azul-fondo/15 border-t border-azul-fondo/15 bg-white/70 dark:divide-azul-borde-oscuro dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura/60">
        @foreach ($carpeta['items'] as $item)
            <li wire:key="practica-est-{{ $item['numero'] }}">
                @if ($item['fila'])
                    @php $tarea = $item['fila']['tarea']; @endphp
                    <a href="{{ route('cursos.tareas.show', [$curso, $tarea]) }}" wire:navigate
                        class="flex items-center gap-3 px-4 py-3 transition hover:bg-celeste/40 sm:pl-8 dark:hover:bg-azul-oscuro/20">
                        <x-icono nombre="documento" class="h-5 w-5 shrink-0 text-azul-oscuro dark:text-cielo" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-texto dark:text-slate-100">{{ $item['etiqueta'] }}@if ($tarea->titulo !== $item['etiqueta'])<span class="font-normal text-slate-600 dark:text-slate-300">: {{ $tarea->titulo }}</span>@endif</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Fecha de entrega: {{ $tarea->fecha_entrega?->translatedFormat('d \d\e F, H:i') ?? 'sin fecha límite' }}</span>
                        </span>
                        <x-badge-estado :estado="$item['fila']['estado']" />
                    </a>
                @else
                    <div class="flex items-center gap-3 px-4 py-3 sm:pl-8">
                        <x-icono nombre="documento" class="h-5 w-5 shrink-0 text-slate-300 dark:text-slate-600" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-slate-500 dark:text-slate-400">{{ $item['etiqueta'] }}</span>
                            <span class="block text-xs text-slate-400">Aún no disponible</span>
                        </span>
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
</section>
