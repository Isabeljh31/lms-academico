@php
    $radio = 42;
    $circunferencia = 2 * M_PI * $radio;
    $recorrido = $circunferencia * ($progreso_general / 100);

    // Colores planos de portada (sin degradado, solo tonos de la paleta
    // fría del estudiante) para cursos sin imagen, elegidos de forma
    // estable a partir del nombre del curso — ver ARQUITECTURA.md § nueva
    // paleta de color.
    $coloresPortada = [
        'bg-azul-oscuro',
        'bg-coral-oscuro',
        'bg-lavanda-oscuro',
        'bg-azul-fondo',
    ];

    $diasSemana = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
@endphp

<div class="space-y-10">
    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-2xl bg-azul-oscuro text-white p-6 sm:p-8 flex flex-wrap items-center gap-6 justify-between">
        {{-- Elemento decorativo discreto: patrón geométrico de baja opacidad --}}
        <svg class="absolute -right-10 -top-10 h-56 w-56 text-white/10 pointer-events-none" fill="none" viewBox="0 0 200 200">
            <circle cx="100" cy="100" r="99" stroke="currentColor" stroke-width="1.5" />
            <circle cx="100" cy="100" r="70" stroke="currentColor" stroke-width="1.5" />
            <circle cx="100" cy="100" r="41" stroke="currentColor" stroke-width="1.5" />
        </svg>

        <div class="relative">
            <p class="text-celeste/90 text-sm">Bienvenido de vuelta</p>
            <h1 class="text-2xl sm:text-3xl font-bold mt-1">Hola, {{ explode(' ', auth()->user()->name)[0] }}</h1>
            <p class="text-celeste/90 text-sm mt-2 max-w-md">
                @if ($cursos->isEmpty())
                    Todavía no estás inscrito en ningún curso. Explora el catálogo para empezar.
                @else
                    Sigues avanzando en {{ $cursos->count() }} {{ $cursos->count() === 1 ? 'curso' : 'cursos' }}. ¡Vamos por más!
                @endif
            </p>
            <a href="{{ route('cursos.index') }}" wire:navigate class="inline-flex items-center gap-1 mt-4 text-sm font-semibold bg-white/15 hover:bg-white/25 rounded-md px-3 py-1.5 transition">
                Ver catálogo de cursos <x-icono nombre="flecha-derecha" class="h-4 w-4" />
            </a>
        </div>

        {{-- Anillo de progreso general --}}
        <div class="relative h-28 w-28 shrink-0">
            <svg class="h-28 w-28 -rotate-90">
                <circle cx="56" cy="56" r="{{ $radio }}" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width="10" />
                <circle cx="56" cy="56" r="{{ $radio }}" fill="none" stroke="white" stroke-width="10" stroke-linecap="round"
                    stroke-dasharray="{{ $circunferencia }}" stroke-dashoffset="{{ $circunferencia - $recorrido }}" />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-2xl font-bold">{{ $progreso_general }}%</span>
                <span class="text-[10px] text-celeste/90">progreso</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Columna principal: cursos + próximas entregas --}}
        <div class="lg:col-span-2 space-y-8">
            {{-- Widget: Mis cursos --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="flex items-center gap-2 text-lg font-semibold text-texto dark:text-slate-100">
                        <x-icono nombre="libro" class="h-5 w-5 text-azul-oscuro dark:text-azul-fondo" />
                        Mis cursos
                    </h2>
                    @if ($cursos->isNotEmpty())
                        <a href="{{ route('cursos.index') }}" wire:navigate class="text-xs font-semibold text-azul-oscuro dark:text-azul-fondo hover:underline flex items-center gap-1">
                            Ver todo <x-icono nombre="flecha-derecha" class="h-3.5 w-3.5" />
                        </a>
                    @endif
                </div>

                @if ($cursos->isEmpty())
                    <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-6 text-center text-slate-500 dark:text-slate-400">
                        Aún no tienes cursos. <a href="{{ route('cursos.index') }}" wire:navigate class="text-azul-oscuro dark:text-azul-fondo hover:underline">Inscríbete en uno</a>.
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($cursos as $fila)
                            @php
                                $curso = $fila['curso'];
                                $colorPortada = $coloresPortada[crc32($curso->nombre) % count($coloresPortada)];
                                $iniciales = collect(explode(' ', $curso->nombre))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
                            @endphp
                            <a href="{{ route('cursos.show', $curso) }}" wire:navigate wire:key="curso-{{ $curso->id }}"
                                class="block bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro overflow-hidden shadow-sm dark:shadow-none hover:shadow-md dark:hover:border-azul-fondo/40 hover:-translate-y-0.5 transition">
                                <div class="relative h-20 overflow-hidden {{ $colorPortada }}"
                                    @if ($curso->imagen_portada_url) style="background-image:url('{{ $curso->imagen_portada_url }}');background-size:cover;background-position:center;" @endif>
                                    @if (! $curso->imagen_portada_url)
                                        <span class="absolute -right-2 -bottom-4 text-6xl font-black text-white/20 select-none leading-none">{{ $iniciales }}</span>
                                    @endif
                                </div>
                                <div class="p-4">
                                    <h3 class="font-semibold text-texto dark:text-slate-100 truncate">{{ $curso->nombre }}</h3>

                                    <div class="mt-3">
                                        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 mb-1">
                                            <span>Progreso</span>
                                            <span>{{ $fila['progreso'] }}%</span>
                                        </div>
                                        <div class="h-1.5 rounded-full bg-celeste/50 dark:bg-azul-borde-oscuro overflow-hidden">
                                            <div class="h-full rounded-full bg-azul-oscuro dark:bg-azul-fondo" style="width: {{ $fila['progreso'] }}%"></div>
                                        </div>
                                    </div>

                                    @if ($fila['proxima_fecha'])
                                        <p class="text-xs text-coral-oscuro dark:text-coral-fondo mt-3 flex items-center gap-1">
                                            <x-icono nombre="reloj" class="h-3.5 w-3.5" />
                                            Próxima entrega: {{ $fila['proxima_fecha']->format('d/m') }}
                                        </p>
                                    @else
                                        <p class="text-xs text-verde-oscuro dark:text-verde-fondo mt-3 flex items-center gap-1">
                                            <x-icono nombre="check-circulo" class="h-3.5 w-3.5" />
                                            Sin entregas pendientes
                                        </p>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Widget: Próximas entregas --}}
            <div>
                <h2 class="flex items-center gap-2 text-lg font-semibold text-texto dark:text-slate-100 mb-3">
                    <x-icono nombre="tarea" class="h-5 w-5 text-azul-oscuro dark:text-azul-fondo" />
                    Próximas entregas
                </h2>

                @if ($proximas_entregas->isEmpty())
                    <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-6 text-center text-slate-500 dark:text-slate-400 text-sm flex items-center justify-center gap-2">
                        <x-icono nombre="check-circulo" class="h-5 w-5 text-verde-oscuro" />
                        No tienes tareas ni quizzes pendientes.
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach ($proximas_entregas as $item)
                            <a href="{{ $item['url'] }}" wire:navigate wire:key="pendiente-{{ $item['tipo'] }}-{{ $loop->index }}"
                                class="flex items-center gap-3 rounded-xl border p-3 shadow-sm dark:shadow-none transition hover:shadow-md
                                    {{ $item['atrasado']
                                        ? 'border-coral-oscuro/40 bg-coral-fondo/40 dark:bg-coral-oscuro/15'
                                        : 'border-celeste dark:border-azul-borde-oscuro bg-white dark:bg-azul-superficie-oscura' }}">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg
                                    {{ $item['atrasado'] ? 'bg-coral-oscuro text-white' : 'bg-celeste/50 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-celeste' }}">
                                    <x-icono :nombre="$item['tipo'] === 'quiz' ? 'quiz' : 'tarea'" class="h-5 w-5" />
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm font-medium text-texto dark:text-slate-100 truncate">{{ $item['titulo'] }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400 truncate">{{ $item['curso']->nombre }}</span>
                                </span>
                                <x-badge-estado :estado="$item['atrasado'] ? 'atrasado' : 'pendiente'" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Columna lateral: calendario + accesos rápidos --}}
        <div class="space-y-8">
            {{-- Widget: mini-calendario --}}
            <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-5"
                x-data="{ diaAbierto: null }">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-texto dark:text-slate-100 flex items-center gap-2 capitalize">
                        <x-icono nombre="calendario" class="h-5 w-5 text-azul-oscuro dark:text-azul-fondo" />
                        {{ $calendario['nombre_mes'] }}
                    </h2>
                    <a href="{{ route('evento-calendario.index') }}" wire:navigate class="text-xs font-semibold text-azul-oscuro dark:text-azul-fondo hover:underline">
                        Ver todo
                    </a>
                </div>

                <div class="grid grid-cols-7 gap-1 text-center">
                    @foreach ($diasSemana as $d)
                        <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500">{{ $d }}</span>
                    @endforeach

                    @for ($i = 0; $i < $calendario['offset_primer_dia']; $i++)
                        <span></span>
                    @endfor

                    @for ($dia = 1; $dia <= $calendario['total_dias']; $dia++)
                        @php
                            $esHoy = $dia === $calendario['hoy'];
                            $eventosDia = $calendario['dias_con_evento'][$dia] ?? [];
                        @endphp
                        <button type="button" wire:key="dia-{{ $dia }}"
                            @if (count($eventosDia)) @click="diaAbierto = (diaAbierto === {{ $dia }} ? null : {{ $dia }})" @endif
                            class="relative h-7 rounded-md text-xs flex items-center justify-center transition
                                {{ $esHoy ? 'bg-azul-oscuro text-white font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-celeste/30 dark:hover:bg-azul-borde-oscuro' }}">
                            {{ $dia }}
                            @if (count($eventosDia) && ! $esHoy)
                                <span class="absolute bottom-0.5 h-1 w-1 rounded-full bg-coral-oscuro"></span>
                            @elseif (count($eventosDia) && $esHoy)
                                <span class="absolute bottom-0.5 h-1 w-1 rounded-full bg-white"></span>
                            @endif
                        </button>

                        @if (count($eventosDia))
                            <template x-if="diaAbierto === {{ $dia }}">
                                <div class="col-span-7 -mt-1 mb-1 rounded-md bg-celeste/30 dark:bg-azul-borde-oscuro/60 px-3 py-2 text-left text-xs text-slate-600 dark:text-slate-300">
                                    @foreach ($eventosDia as $titulo)
                                        <p class="truncate">{{ $titulo }}</p>
                                    @endforeach
                                </div>
                            </template>
                        @endif
                    @endfor
                </div>

                <div class="mt-4 pt-4 border-t border-celeste dark:border-azul-borde-oscuro space-y-3">
                    @forelse ($proximosEventos as $evento)
                        <div class="flex items-start gap-3" wire:key="evento-preview-{{ $loop->index }}">
                            <div class="flex flex-col items-center justify-center h-9 w-9 shrink-0 rounded-lg bg-celeste/30 dark:bg-azul-borde-oscuro/60 text-slate-700 dark:text-slate-200">
                                <span class="text-[9px] uppercase leading-none">{{ $evento['fecha']->translatedFormat('M') }}</span>
                                <span class="text-sm font-bold leading-none">{{ $evento['fecha']->format('d') }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm text-texto dark:text-slate-100 truncate">{{ $evento['titulo'] }}</p>
                                <p class="text-xs text-slate-400">{{ $evento['fecha']->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">Sin eventos próximos.</p>
                    @endforelse
                </div>
            </div>

            {{-- Widget: accesos rápidos --}}
            <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-5">
                <h2 class="font-semibold text-texto dark:text-slate-100 mb-4">Accesos rápidos</h2>
                <div class="grid grid-cols-3 gap-3">
                    <a href="{{ route('evento-calendario.index') }}" wire:navigate
                        class="flex flex-col items-center gap-2 rounded-xl border border-celeste dark:border-azul-borde-oscuro p-3 text-center hover:border-azul-fondo hover:bg-celeste/30 dark:hover:bg-azul-borde-oscuro/40 transition">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-celeste/50 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-celeste">
                            <x-icono nombre="calendario" class="h-5 w-5" />
                        </span>
                        <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Calendario</span>
                    </a>

                    <button type="button" wire:click="$dispatch('abrir-bot')"
                        class="flex flex-col items-center gap-2 rounded-xl border border-celeste dark:border-azul-borde-oscuro p-3 text-center hover:border-lavanda-oscuro hover:bg-lavanda-fondo/20 dark:hover:bg-azul-borde-oscuro/40 transition">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-lavanda-fondo/40 text-lavanda-oscuro dark:bg-lavanda-oscuro/30 dark:text-lavanda-fondo">
                            <x-icono nombre="chat" class="h-5 w-5" />
                        </span>
                        <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Asistente</span>
                    </button>

                    <a href="{{ route('cursos.index') }}" wire:navigate
                        class="flex flex-col items-center gap-2 rounded-xl border border-celeste dark:border-azul-borde-oscuro p-3 text-center hover:border-azul-fondo hover:bg-celeste/30 dark:hover:bg-azul-borde-oscuro/40 transition">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-celeste/50 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-celeste">
                            <x-icono nombre="libro" class="h-5 w-5" />
                        </span>
                        <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Mis cursos</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
