@php
    $paletaFria = auth()->user()->hasRole('estudiante');

    $acentosCurso = [
        ['texto' => 'text-azul-oscuro dark:text-azul-fondo', 'fondo' => 'bg-azul-oscuro/10 dark:bg-azul-oscuro/20'],
        ['texto' => 'text-azul-fondo dark:text-cielo',       'fondo' => 'bg-azul-fondo/10 dark:bg-azul-fondo/20'],
        ['texto' => 'text-cielo dark:text-cielo',             'fondo' => 'bg-cielo/10 dark:bg-cielo/20'],
    ];

    $codigoCurso = fn (string $nombre) =>
        strtoupper(substr(preg_replace('/[^A-Za-z]/', '', \Illuminate\Support\Str::ascii($nombre)), 0, 3));

    // Badge de estado del curso (ERS RF-22)
    $badgeEstado = fn(string $estado) => match($estado) {
        'publicado'    => ['color' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',   'label' => 'Publicado'],
        'borrador'     => ['color' => 'bg-stone-100 text-stone-600 dark:bg-stone-700 dark:text-stone-300',       'label' => 'Borrador'],
        'despublicado' => ['color' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',   'label' => 'Oculto'],
        'archivado'    => ['color' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',           'label' => 'Archivado'],
        default        => ['color' => 'bg-stone-100 text-stone-500',                                             'label' => ucfirst($estado)],
    };
@endphp

<div class="py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if($paletaFria)
        {{-- Encabezado --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold {{ $paletaFria ? 'text-texto dark:text-slate-100' : 'text-stone-900 dark:text-stone-100' }}">
                    Cursos
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    @if(auth()->user()->hasRole('profesor')) Tus cursos como profesor.
                    @elseif(auth()->user()->hasRole('supervisor')) Todos los cursos de la plataforma.
                    @else Tus cursos matriculados. @endif
                </p>
            </div>
            @if($puedeCrear)
            <a href="{{ route('cursos.crear') }}" wire:navigate
                class="inline-flex items-center gap-2 px-4 py-2 bg-menta-oscuro text-white text-sm font-semibold rounded-lg hover:bg-menta-oscuro/90 transition">
                + Nuevo curso
            </a>
            @endif
        </div>

        {{-- Filtros --}}
        <div class="flex flex-wrap gap-3 items-end">
            <input wire:model.live.debounce.300ms="busqueda" type="search"
                placeholder="Buscar curso…"
                class="w-full sm:w-72 rounded-lg text-sm border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base px-3 py-2">

            @if(!$paletaFria)
            {{-- Filtro de estado: solo supervisor/profesor ven esto --}}
            <select wire:model.live="filtroEstado"
                class="rounded-lg text-sm border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base px-3 py-2">
                <option value="">Todos los estados</option>
                <option value="publicado">Publicados</option>
                <option value="borrador">Borradores</option>
                <option value="despublicado">Ocultos</option>
                <option value="archivado">Archivados</option>
            </select>
            @endif
        </div>

        @else
        <x-gestion.encabezado titulo="Cursos" icono="libro"
            :descripcion="auth()->user()->hasRole('supervisor') ? 'Todos los cursos de la plataforma: crea, edita, publica, archiva y clona cursos.' : 'Los cursos que tienes a cargo como profesor.'">
            @if ($puedeCrear)
                <x-gestion.boton icono="mas" :href="route('cursos.crear')">Nuevo curso</x-gestion.boton>
            @endif
        </x-gestion.encabezado>

        <x-gestion.alertas />

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:w-2/3">
            <x-gestion.campo etiqueta="Buscar" nombre="busqueda">
                <x-gestion.control id="busqueda" type="search" wire:model.live.debounce.300ms="busqueda" placeholder="Nombre o código del curso" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Estado" nombre="filtroEstado">
                <x-gestion.control tipo="select" id="filtroEstado" wire:model.live="filtroEstado">
                    <option value="">Todos los estados</option>
                    <option value="publicado">Publicados</option>
                    <option value="borrador">Borradores</option>
                    <option value="despublicado">Ocultos</option>
                    <option value="archivado">Archivados</option>
                </x-gestion.control>
            </x-gestion.campo>
        </div>
        @endif

        @if($cursos->isEmpty() && $paletaFria)
            <p class="text-slate-500 dark:text-slate-400 py-8 text-center">No hay cursos que coincidan con tu búsqueda.</p>

        @elseif($cursos->isEmpty())
            <x-gestion.vacio icono="libro" titulo="No hay cursos para mostrar"
                :descripcion="$puedeCrear ? 'Crea el primer curso o ajusta los filtros.' : 'Aún no tienes cursos asignados. El Supervisor te asignará tus cursos.'" />

        @elseif($paletaFria)
        {{-- =========== Vista Estudiante =========== --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($cursos as $curso)
            @php $acento = $acentosCurso[crc32($curso->nombre) % count($acentosCurso)]; @endphp
            <a href="{{ route('cursos.show', $curso) }}" wire:navigate wire:key="curso-{{ $curso->id }}"
                class="block bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition">
                <div class="relative h-32 overflow-hidden flex items-center justify-center {{ $acento['fondo'] }}"
                    @if($curso->imagen_portada_url) style="background-image:url('{{ $curso->imagen_portada_url }}');background-size:cover;background-position:center;" @endif>
                    @if(!$curso->imagen_portada_url)
                    <span class="text-4xl font-black tracking-tight {{ $acento['texto'] }} opacity-70 select-none">{{ $codigoCurso($curso->nombre) }}</span>
                    @endif
                    {{-- Badge estado --}}
                    @if($curso->estado !== 'publicado')
                    @php $badge = $badgeEstado($curso->estado); @endphp
                    <span class="absolute top-2 right-2 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $badge['color'] }}">
                        {{ $badge['label'] }}
                    </span>
                    @endif
                </div>
                <div class="p-4">
                    <h2 class="font-semibold text-texto dark:text-slate-100 truncate">{{ $curso->nombre }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Prof. {{ $curso->profesor?->name }}</p>
                    {{-- Fechas ERS RF-19 --}}
                    @if($curso->fecha_inicio || $curso->fecha_fin)
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                        <x-icono nombre="calendario" class="inline h-3 w-3 -mt-0.5" /> {{ $curso->fecha_inicio?->formatoFecha() ?? '?' }} – {{ $curso->fecha_fin?->formatoFecha() ?? '?' }}
                    </p>
                    @endif
                    <div class="flex items-center justify-between mt-3 text-xs text-slate-500 dark:text-slate-400">
                        <span>{{ $curso->modulos->count() }} unidades</span>
                        <span>{{ $curso->inscripciones_count }} inscritos</span>
                    </div>
                    @if($inscritos->has($curso->id))
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
        {{-- =========== Vista Profesor / Supervisor =========== --}}
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($cursos as $curso)
            @php $badge = $badgeEstado($curso->estado); @endphp
            <a href="{{ route('cursos.show', $curso) }}" wire:navigate wire:key="curso-{{ $curso->id }}"
                class="block overflow-hidden rounded-xl border border-arena-medio bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-stone-700 dark:bg-stone-800">
                <div class="relative flex h-28 items-center justify-center bg-menta-fondo dark:bg-stone-700"
                    @if($curso->imagen_portada_url) style="background-image:url('{{ $curso->imagen_portada_url }}');background-size:cover;background-position:center;" @endif>
                    @unless($curso->imagen_portada_url)
                        <span class="select-none text-3xl font-black tracking-tight text-menta-oscuro/60 dark:text-menta-base/60">{{ $codigoCurso($curso->nombre) }}</span>
                    @endunless
                    <span class="absolute right-2 top-2 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge['color'] }}">{{ $badge['label'] }}</span>
                </div>
                <div class="space-y-1 p-4">
                    <h2 class="truncate font-semibold text-stone-800 dark:text-stone-100">{{ $curso->nombre }}</h2>
                    <p class="text-xs text-stone-500 dark:text-stone-400">
                        @if($curso->codigo) <span class="font-mono">{{ $curso->codigo }}</span> · @endif
                        Prof. {{ $curso->docentes()->pluck('name')->join(', ') ?: 'sin asignar' }}
                    </p>
                    @if($curso->fecha_inicio || $curso->semanas_duracion)
                    <p class="flex items-center gap-1 text-xs text-stone-500 dark:text-stone-400">
                        <x-icono nombre="calendario" class="h-3.5 w-3.5" />
                        @if($curso->fecha_inicio) {{ $curso->fecha_inicio->formatoFecha() }} @endif
                        @if($curso->fecha_inicio && $curso->fecha_fin) – {{ $curso->fecha_fin->formatoFecha() }} @endif
                        @if($curso->semanas_duracion) · {{ $curso->semanas_duracion }} sem. @endif
                    </p>
                    @endif
                    <div class="flex items-center justify-between pt-2 text-xs text-stone-500 dark:text-stone-400">
                        <span class="inline-flex items-center gap-1"><x-icono nombre="libro" class="h-3.5 w-3.5" /> {{ $curso->modulos->count() }} módulos</span>
                        <span class="inline-flex items-center gap-1"><x-icono nombre="usuarios" class="h-3.5 w-3.5" /> {{ $curso->inscripciones_activas_count }} matriculados</span>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
        <div>{{ $cursos->links() }}</div>
        @endif

    </div>
</div>
