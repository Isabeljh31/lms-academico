@php
    // Compartido entre roles; Estudiante usa la paleta fría nueva y
    // Profesor/Administrador conservan la anterior — ver ARQUITECTURA.md §
    // nueva paleta de color.
    $paletaFria = auth()->user()->hasRole('estudiante');

    // Acentos de curso (rotación estable por nombre): texto + fondo tenue
    // del mismo color, igual criterio que el panel de inicio del estudiante
    // (ver /DISEñO FIGMA) — sin degradado, solo el color de marca en
    // distinta intensidad.
    $acentosCurso = [
        ['texto' => 'text-azul-oscuro dark:text-azul-fondo', 'fondo' => 'bg-azul-oscuro/10 dark:bg-azul-oscuro/20'],
        ['texto' => 'text-azul-fondo dark:text-cielo', 'fondo' => 'bg-azul-fondo/10 dark:bg-azul-fondo/20'],
        ['texto' => 'text-cielo dark:text-cielo', 'fondo' => 'bg-cielo/10 dark:bg-cielo/20'],
    ];
    // Str::ascii() quita tildes antes de recortar, para que el código quede
    // consistente (p. ej. "Física" -> "FIS", no "Fís" con strtoupper solo).
    $codigoCurso = fn (string $nombre) => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', \Illuminate\Support\Str::ascii($nombre)), 0, 3));
@endphp

<div class="py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold {{ $paletaFria ? 'text-texto dark:text-slate-100' : 'text-stone-900 dark:text-stone-100' }}">Cursos</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    @if (auth()->user()->hasRole('profesor'))
                        Tus cursos como profesor.
                    @else
                        Catálogo de cursos disponibles.
                    @endif
                </p>
            </div>

            @if ($puedeCrear)
                <a href="{{ route('cursos.crear') }}" wire:navigate
                    class="inline-flex items-center px-4 py-2 bg-menta-oscuro text-white text-sm font-semibold rounded-md hover:bg-menta-oscuro/90">
                    + Nuevo curso
                </a>
            @endif
        </div>

        <input wire:model.live.debounce.300ms="busqueda" type="search" placeholder="Buscar curso…"
            class="w-full sm:w-80 rounded-md text-sm {{ $paletaFria
                ? 'border-slate-300 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 focus:border-azul-fondo focus:ring-azul-fondo'
                : 'border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base' }}">

        @if ($cursos->isEmpty())
            <p class="text-slate-500 dark:text-slate-400">No hay cursos todavía.</p>
        @elseif ($paletaFria)
            {{-- Rediseño visual del rol estudiante: portada = código del curso
                 sobre fondo tenue del acento, mismo estilo que el panel de
                 inicio (ver /DISEñO FIGMA). --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($cursos as $curso)
                    @php
                        $acento = $acentosCurso[crc32($curso->nombre) % count($acentosCurso)];
                    @endphp
                    <a href="{{ route('cursos.show', $curso) }}" wire:navigate wire:key="curso-{{ $curso->id }}"
                        class="block bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro overflow-hidden shadow-sm dark:shadow-none hover:shadow-lg hover:-translate-y-0.5 transition">
                        <div class="relative h-32 overflow-hidden flex items-center justify-center {{ $acento['fondo'] }}"
                            @if ($curso->imagen_portada_url) style="background-image:url('{{ $curso->imagen_portada_url }}');background-size:cover;background-position:center;" @endif>
                            @if (! $curso->imagen_portada_url)
                                <span class="text-4xl font-black tracking-tight {{ $acento['texto'] }} opacity-70 select-none">{{ $codigoCurso($curso->nombre) }}</span>
                            @endif
                        </div>
                        <div class="p-4">
                            <h2 class="font-semibold text-texto dark:text-slate-100 truncate">{{ $curso->nombre }}</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Prof. {{ $curso->profesor?->name }}</p>
                            <div class="flex items-center justify-between mt-3 text-xs text-slate-500 dark:text-slate-400">
                                <span>{{ $curso->modulos->count() }} unidades</span>
                                <span>{{ $curso->inscripciones_count }} inscritos</span>
                            </div>
                            @if ($inscritos->has($curso->id))
                                <span class="mt-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-celeste text-azul-oscuro dark:bg-azul-oscuro/20 dark:text-celeste">
                                    Inscrito
                                </span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div>{{ $cursos->links() }}</div>
        @else
            {{-- Vista de profesor/administrador: sin cambios respecto a la versión anterior. --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($cursos as $curso)
                    <a href="{{ route('cursos.show', $curso) }}" wire:navigate wire:key="curso-{{ $curso->id }}"
                        class="block bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 overflow-hidden shadow-sm hover:shadow-md transition">
                        <div class="h-32 bg-gradient-to-br from-menta-fondo to-arena-medio dark:from-stone-700 dark:to-stone-800"
                            @if ($curso->imagen_portada_url) style="background-image:url('{{ $curso->imagen_portada_url }}');background-size:cover;background-position:center;" @endif>
                        </div>
                        <div class="p-4">
                            <h2 class="font-semibold text-stone-800 dark:text-stone-100">{{ $curso->nombre }}</h2>
                            <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">Prof. {{ $curso->profesor?->name }}</p>
                            <div class="flex items-center justify-between mt-3 text-xs text-stone-500 dark:text-stone-400">
                                <span>{{ $curso->modulos->count() }} unidades</span>
                                <span>{{ $curso->inscripciones_count }} inscritos</span>
                            </div>
                            @if ($inscritos->has($curso->id))
                                <span class="mt-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-menta-fondo text-menta-oscuro">
                                    Inscrito
                                </span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div>{{ $cursos->links() }}</div>
        @endif
    </div>
</div>
