@php
    // Acento de portada (código + fondo tenue, sin degradado) para el rol
    // estudiante, elegido de forma estable a partir del nombre del curso —
    // mismo criterio que el widget "Mis cursos" del panel (ver /DISEñO
    // FIGMA). El profesor conserva su fondo original sin cambios.
    $acentosCurso = [
        ['texto' => 'text-azul-oscuro dark:text-azul-fondo', 'fondo' => 'bg-azul-oscuro/10 dark:bg-azul-oscuro/20'],
        ['texto' => 'text-azul-fondo dark:text-cielo', 'fondo' => 'bg-azul-fondo/10 dark:bg-azul-fondo/20'],
        ['texto' => 'text-cielo dark:text-cielo', 'fondo' => 'bg-cielo/10 dark:bg-cielo/20'],
    ];
    $acentoCurso = $acentosCurso[crc32($curso->nombre) % count($acentosCurso)];
    // Str::ascii() quita tildes antes de recortar, para que el código quede
    // consistente (p. ej. "Física" -> "FIS", no "Fís" con strtoupper solo).
    $codigoCurso = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', \Illuminate\Support\Str::ascii($curso->nombre)), 0, 3));

    // Estudiante y Profesor comparten la paleta azul; Supervisor conserva su acento cálido.
    $esAdminVista = auth()->user()->hasRole('supervisor');
    $claseBadgeNeutra = $esAdminVista ? 'bg-arena-medio text-stone-700 dark:bg-stone-700 dark:text-stone-200' : 'bg-celeste text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200';
    $claseSemanaConContenido = $esAdminVista ? 'bg-menta-fondo text-menta-oscuro dark:bg-stone-700 dark:text-menta-base' : 'bg-azul-oscuro/10 text-azul-oscuro dark:bg-azul-oscuro/20 dark:text-azul-fondo';
    $claseSemanaVacia = $esAdminVista ? 'border border-dashed border-arena-medio text-stone-400 dark:border-stone-600' : 'border border-dashed border-celeste text-slate-400 dark:border-azul-borde-oscuro';
@endphp

<div class="py-8">
    {{-- Ancho de contenedor estandarizado (max-w-6xl) para todas las vistas del estudiante. --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if (session('estado') && auth()->user()->hasRole('estudiante'))
            <div class="rounded-lg bg-exito-base/15 text-exito-oscuro px-4 py-3 text-sm font-medium">{{ session('estado') }}</div>
        @endif

        <div class="bg-white rounded-xl border overflow-hidden {{ $esAdminVista ? 'dark:bg-stone-800 border-arena-medio dark:border-stone-700' : 'dark:bg-azul-superficie-oscura border-celeste dark:border-azul-borde-oscuro' }}">
            <div class="relative h-40 overflow-hidden flex items-center justify-center {{ auth()->user()->hasRole('estudiante') ? $acentoCurso['fondo'] : ($esAdminVista ? 'bg-menta-fondo dark:bg-stone-700' : 'bg-celeste dark:bg-azul-borde-oscuro') }}"
                @if ($curso->imagen_portada_url) style="background-image:url('{{ $curso->portadaUrl() }}');background-size:cover;background-position:center;" @endif>
                @if (! $curso->imagen_portada_url && auth()->user()->hasRole('estudiante'))
                    {{-- Rediseño visual del rol estudiante: código del curso sobre fondo tenue del acento. --}}
                    <span class="text-5xl font-black tracking-tight {{ $acentoCurso['texto'] }} opacity-70 select-none">{{ $codigoCurso }}</span>
                @elseif (! $curso->imagen_portada_url)
                    {{-- Profesor / Supervisor sin portada: código del curso en la paleta de gestión. --}}
                    <span class="select-none text-4xl font-black tracking-tight {{ $esAdminVista ? 'text-menta-oscuro/60 dark:text-menta-base/60' : 'text-azul-oscuro/60 dark:text-azul-fondo/60' }}">{{ $curso->codigo ?? $codigoCurso }}</span>
                @endif
            </div>
            <div class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold {{ auth()->user()->hasRole('estudiante') ? 'text-texto dark:text-slate-100' : 'text-stone-900 dark:text-stone-100' }}">{{ $curso->nombre }}</h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Prof. {{ $curso->docentes()->pluck('name')->join(', ') }}
                        </p>
                        {{-- ERS RF-22: Badge de estado del curso --}}
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium mt-1
                            @if($curso->estado === 'publicado') bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400
                            @elseif($curso->estado === 'borrador') bg-stone-100 text-stone-600 dark:bg-stone-700 dark:text-stone-300
                            @elseif($curso->estado === 'despublicado') bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400
                            @elseif($curso->estado === 'archivado') bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400
                            @endif" id="badge-estado-curso">
                            {{ ucfirst($curso->estado) }}
                        </span>
                    </div>

                    @if (auth()->user()->hasRole('estudiante'))
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('cursos.tareas.index', $curso) }}" wire:navigate>
                            <x-secondary-button>Tareas</x-secondary-button>
                        </a>

                        <a href="{{ route('cursos.quizzes.index', $curso) }}" wire:navigate>
                            <x-secondary-button>Quizzes</x-secondary-button>
                        </a>

                        <a href="{{ route('cursos.mensajeria', $curso) }}" wire:navigate>
                            <x-secondary-button>Mensajería</x-secondary-button>
                        </a>

                        <a href="{{ route('cursos.debates.index', $curso) }}" wire:navigate>
                            <x-secondary-button>Debates</x-secondary-button>
                        </a>

                        @if (auth()->user()->hasRole('estudiante'))
                            @if ($this->estaInscrito)
                                <span class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-semibold bg-celeste/50 text-azul-oscuro">
                                    Ya estás inscrito
                                </span>
                                {{-- ERS RF-59: el estudiante accede a su boleta de notas --}}
                                <a href="{{ route('cursos.boleta', $curso) }}" wire:navigate>
                                    <x-secondary-button class="border-azul-oscuro text-azul-oscuro dark:text-azul-fondo hover:bg-celeste/40">
                                        <x-icono nombre="tarea" class="w-4 h-4 mr-1.5 inline" /> Mi Boleta
                                    </x-secondary-button>
                                </a>
                                {{-- ERS RF-72: historial de asistencia del estudiante --}}
                                @if ($curso->usa_asistencia)
                                    <a href="{{ route('cursos.mi-asistencia', $curso) }}" wire:navigate>
                                        <x-secondary-button class="border-azul-oscuro text-azul-oscuro dark:text-azul-fondo hover:bg-celeste/40">
                                            <x-icono nombre="calendario" class="w-4 h-4 mr-1.5 inline" /> Mi Asistencia
                                        </x-secondary-button>
                                    </a>
                                @endif
                            @else
                                <x-primary-button wire:click="inscribirme">Inscribirme</x-primary-button>
                            @endif
                        @endif
                    </div>
                    @else
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($this->puedeGestionar)
                            {{-- Tareas, Quizzes y Banco de preguntas en un solo apartado. --}}
                            <x-gestion.boton variante="secundario" tamano="sm" icono="tarea" :href="route('cursos.actividades', $curso)">Actividades</x-gestion.boton>
                        @elseif (! $esAdminVista)
                            <x-gestion.boton variante="secundario" tamano="sm" icono="tarea" :href="route('cursos.tareas.index', $curso)">Tareas</x-gestion.boton>
                            <x-gestion.boton variante="secundario" tamano="sm" icono="quiz" :href="route('cursos.quizzes.index', $curso)">Quizzes</x-gestion.boton>
                        @endif
                        @if (auth()->user()->hasRole('profesor'))
                            <x-gestion.boton variante="secundario" tamano="sm" icono="usuarios" :href="route('cursos.mensajeria', $curso)">Mensajería</x-gestion.boton>
                            <x-gestion.boton variante="secundario" tamano="sm" icono="chat" :href="route('cursos.debates.index', $curso)">Debates</x-gestion.boton>
                        @endif
                        @if ($this->puedeGestionar)
                            <x-gestion.boton variante="secundario" tamano="sm" icono="usuarios" :href="route('cursos.grupos.index', $curso)">Grupos</x-gestion.boton>
                        @endif
                        @if ($this->puedeSupervisar)
                            <x-gestion.boton variante="secundario" tamano="sm" icono="usuario-mas" :href="route('cursos.matriculas', $curso)">Matrícula</x-gestion.boton>
                            @if ($curso->usa_asistencia || $this->puedeGestionar)
                                <x-gestion.boton variante="secundario" tamano="sm" icono="calendario" :href="route('asistencia.index', $curso)">Asistencia</x-gestion.boton>
                            @endif
                            <x-gestion.boton variante="secundario" tamano="sm" icono="lista" :href="route('cursos.libro-calificaciones', $curso)">Libro de calificaciones</x-gestion.boton>
                        @endif
                        @if ($this->puedeAdministrar)
                            <x-gestion.boton variante="secundario" tamano="sm" icono="grafico" :href="route('cursos.esquema', $curso)">Esquema de evaluación</x-gestion.boton>
                            <x-gestion.boton variante="secundario" tamano="sm" icono="lapiz" :href="route('cursos.editar', $curso)">Editar</x-gestion.boton>
                            <x-gestion.boton variante="secundario" tamano="sm" icono="copiar" wire:click="abrirClonar">Clonar</x-gestion.boton>
                        @endif
                    </div>
                    @endif
                </div>

                @if ($curso->descripcion)
                    <p class="mt-4 {{ auth()->user()->hasRole('estudiante') ? 'text-slate-600 dark:text-slate-300' : 'text-stone-600 dark:text-stone-300' }}">{{ $curso->descripcion }}</p>
                @endif
            </div>
        </div>

        @if ($esAdminVista)
            <x-gestion.tarjeta titulo="Resumen administrativo">
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                    <div class="flex items-center justify-between gap-3 text-sm"><dt class="text-stone-500 dark:text-stone-400">Código</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $curso->codigo ?? 'sin asignar' }}</dd></div>
                    <div class="flex items-center justify-between gap-3 text-sm"><dt class="text-stone-500 dark:text-stone-400">Modalidad</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ ucfirst($curso->modalidad) }}</dd></div>
                    <div class="flex items-center justify-between gap-3 text-sm"><dt class="text-stone-500 dark:text-stone-400">Horario</dt><dd class="text-right font-medium text-stone-800 dark:text-stone-100">{{ $curso->horarioResumen() ?? ($curso->modalidad === 'virtual' ? 'Asíncrono' : 'Sin definir') }}</dd></div>
                    <div class="flex items-center justify-between gap-3 text-sm"><dt class="text-stone-500 dark:text-stone-400">Fechas</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $curso->fecha_inicio?->formatoFecha() ?? '—' }} – {{ $curso->fecha_fin?->formatoFecha() ?? '—' }} ({{ $curso->totalSemanas() }} sem.)</dd></div>
                    <div class="flex items-center justify-between gap-3 text-sm"><dt class="text-stone-500 dark:text-stone-400">Cupo</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $resumenAdmin['inscritos'] }}{{ $curso->cupo_maximo ? ' / '.$curso->cupo_maximo : ' (sin límite)' }} matriculados</dd></div>
                    <div class="flex items-center justify-between gap-3 text-sm"><dt class="text-stone-500 dark:text-stone-400">Docentes</dt><dd class="text-right font-medium text-stone-800 dark:text-stone-100">{{ $curso->docentes()->pluck('name')->join(', ') ?: 'Sin asignar' }}</dd></div>
                </dl>

                @if ($this->puedeMatricularRapido)
                    <div class="mt-4 border-t border-arena-medio pt-4 dark:border-stone-700">
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-stone-400 dark:text-stone-500">Matricular estudiante</p>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                            <div class="relative flex-1">
                                <label for="busquedaEstudianteRapida" class="sr-only">Buscar estudiante</label>
                                <x-gestion.control id="busquedaEstudianteRapida" wire:model.live.debounce.350ms="busquedaEstudianteRapida" placeholder="Nombre, correo o DNI del estudiante" autocomplete="off" />
                                @if ($estudiantesBusquedaRapida->isNotEmpty())
                                    <div class="absolute left-0 right-0 top-full z-20 mt-1 overflow-hidden rounded-xl border border-arena-medio bg-white shadow-lg dark:border-stone-600 dark:bg-stone-800">
                                        @foreach ($estudiantesBusquedaRapida as $est)
                                            <button type="button" wire:click="seleccionarEstudianteRapido({{ $est->id }})" wire:key="busqueda-rapida-{{ $est->id }}"
                                                class="flex w-full items-center gap-3 border-b border-arena-medio/60 px-4 py-2.5 text-left last:border-0 hover:bg-arena-claro dark:border-stone-700 dark:hover:bg-stone-700">
                                                <x-icono nombre="usuarios" class="h-4 w-4 shrink-0 text-stone-400" />
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-medium text-stone-800 dark:text-stone-100">{{ $est->name }}</span>
                                                    <span class="block truncate text-xs text-stone-500">{{ $est->email }}</span>
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif (mb_strlen(trim($busquedaEstudianteRapida)) >= 2 && ! $estudianteSeleccionadoId)
                                    <p class="mt-2 text-xs text-stone-500">Sin resultados: puede que ya esté matriculado o su cuenta esté desactivada.</p>
                                @endif
                                @error('estudianteSeleccionadoId')
                                    <p class="mt-1 flex items-center gap-1 text-xs font-medium text-red-600"><x-icono nombre="alerta" class="h-3.5 w-3.5" /> {{ $message }}</p>
                                @enderror
                            </div>
                            <x-gestion.boton tamano="sm" icono="usuario-mas" wire:click="matricularRapido" :disabled="! $estudianteSeleccionadoId"
                                wire:loading.attr="disabled" wire:target="matricularRapido">
                                Matricular
                            </x-gestion.boton>
                        </div>
                    </div>
                @endif

                <div class="mt-4 grid grid-cols-2 gap-4 border-t border-arena-medio pt-4 dark:border-stone-700 sm:grid-cols-5">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">Progreso</p>
                        <p class="text-lg font-bold text-stone-800 dark:text-stone-100">{{ $resumenAdmin['progreso_promedio'] }}%</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">Entregas</p>
                        <p class="text-lg font-bold text-stone-800 dark:text-stone-100">{{ $resumenAdmin['tasa_entrega'] }}%</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">Por calificar</p>
                        <p class="text-lg font-bold {{ $resumenAdmin['por_calificar'] > 0 ? 'text-terracota-oscuro' : 'text-stone-800 dark:text-stone-100' }}">{{ $resumenAdmin['por_calificar'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">Nota final prom.</p>
                        <p class="text-lg font-bold text-stone-800 dark:text-stone-100">{{ $resumenAdmin['promedio_final'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">Aprobación</p>
                        <p class="text-lg font-bold text-stone-800 dark:text-stone-100">{{ $resumenAdmin['tasa_aprobacion'] !== null ? $resumenAdmin['tasa_aprobacion'].'%' : '—' }}</p>
                    </div>
                </div>

                <div class="mt-4 border-t border-arena-medio pt-4 dark:border-stone-700">
                    <p class="text-xs font-semibold uppercase tracking-wide text-stone-400 dark:text-stone-500">Esquema de evaluación</p>
                    @if ($rubrosEvaluacion && $rubrosEvaluacion->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($rubrosEvaluacion as $rubro)
                                <span class="inline-flex items-center gap-1 rounded-full bg-arena-claro/70 px-2.5 py-1 text-sm font-medium text-stone-600 dark:bg-stone-700/60 dark:text-stone-300">
                                    {{ $rubro->nombre }} {{ rtrim(rtrim(number_format((float) $rubro->peso, 1), '0'), '.') }}%
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-2 text-sm text-stone-500">
                            Aún no se definió. <a href="{{ route('cursos.esquema', $curso) }}" wire:navigate class="font-medium text-menta-oscuro hover:underline dark:text-menta-base">Configurarlo</a>
                        </p>
                    @endif
                </div>

                @if ($resumenAdmin['bajo_rendimiento']->isNotEmpty())
                    <div class="mt-4 border-t border-arena-medio pt-4 dark:border-stone-700">
                        <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-terracota-oscuro dark:text-terracota-fondo">
                            <x-icono nombre="alerta" class="h-3.5 w-3.5" /> Bajo rendimiento ({{ $resumenAdmin['bajo_rendimiento']->count() }})
                        </p>
                        <ul class="mt-2 divide-y divide-arena-medio/60 dark:divide-stone-700">
                            @foreach ($resumenAdmin['bajo_rendimiento'] as $br)
                                <li class="flex flex-wrap items-center justify-between gap-2 py-1.5 text-sm">
                                    <span class="font-medium text-stone-800 dark:text-stone-100">{{ $br['estudiante']->name }}</span>
                                    <span class="text-stone-500 dark:text-stone-400">
                                        {{ $br['nota_final'] !== null ? 'Nota '.$br['nota_final'] : 'Sin notas' }} · Avance {{ $br['avance'] }}%
                                        @if ($br['vencidas'] > 0) · {{ $br['vencidas'] }} tareas vencidas @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-gestion.tarjeta>
        @endif

        @if (! auth()->user()->hasRole('estudiante'))
            {{-- Vista de Profesor / Supervisor --}}
            <x-gestion.alertas />

            @if ($curso->estaFinalizado())
                <div role="alert" class="flex items-start gap-3 rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                    <x-icono nombre="archivo" class="h-5 w-5 shrink-0" />
                    <p>Curso archivado (finalizado): se puede consultar, pero no admite cambios en su contenido ni en sus notas.</p>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 {{ $esAdminVista ? '' : 'lg:grid-cols-3' }}">
                <div class="space-y-6 {{ $esAdminVista ? '' : 'lg:col-span-2' }}">
                    @php $editable = $this->puedeGestionar && ! $curso->estaFinalizado(); @endphp

                    @include('livewire.compartido.cursos._informacion-curso')

                    {{-- Prácticas de campo: bloque propio, fuera de la numeración semanal. Solo aparece si el curso lo tiene. --}}
                    @if ($secciones['practicas'])
                        @php $practicas = $secciones['practicas']['modulo']; @endphp
                        <section aria-labelledby="titulo-practicas-gestion" wire:key="practicas-gestion-{{ $practicas->id }}"
                            @if ($this->puedeGestionar)
                                x-data="{
                                    get abierta() { return (this.$wire.semanasAbiertas ?? []).map(Number).includes(0) },
                                    alternar() {
                                        const otras = (this.$wire.semanasAbiertas ?? []).map(Number).filter(s => s !== 0);
                                        this.$wire.semanasAbiertas = this.abierta ? otras : [...otras, 0];
                                    },
                                }"
                            @endif
                            class="rounded-xl border shadow-sm {{ $esAdminVista ? 'border-arena-medio border-l-4 border-l-terracota-base bg-arena-claro/60 dark:border-stone-700 dark:border-l-terracota-base dark:bg-stone-800' : 'border-celeste border-l-4 border-l-azul-oscuro bg-celeste/60 dark:border-azul-borde-oscuro dark:border-l-azul-oscuro dark:bg-azul-superficie-oscura' }}">
                            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-white shadow-sm {{ $esAdminVista ? 'bg-terracota-oscuro' : 'bg-azul-oscuro' }}">
                                    <x-icono nombre="maletin" class="h-6 w-6" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider {{ $esAdminVista ? 'text-terracota-oscuro dark:text-terracota-fondo' : 'text-azul-oscuro dark:text-azul-fondo' }}">Fuera de la numeración semanal</p>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 id="titulo-practicas-gestion" class="text-base font-bold text-stone-800 dark:text-stone-100">
                                            {{ 'Prácticas de campo' }}@if ($practicas->tema() && $practicas->tema() !== 'Prácticas de campo')<span class="font-normal text-stone-600 dark:text-stone-300">: {{ $practicas->tema() }}</span>@endif
                                        </h2>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $practicas->esVisibleParaEstudiante() ? 'bg-exito-base/15 text-exito-oscuro' : $claseBadgeNeutra }}">
                                            {{ $practicas->etiquetaEstadoPublicacion() }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-stone-500 dark:text-stone-400">
                                        {{ $practicas->lecciones_count }} lecciones - {{ $practicas->tareas_count }} tareas - {{ $practicas->quizzes_count }} quizzes
                                        @unless ($secciones['practicas']['visibleParaEstudiantes'])
                                            - los estudiantes no ven este bloque hasta que tenga contenido publicado
                                        @endunless
                                    </p>
                                </div>
                                @if ($this->puedeGestionar)
                                    @php $practicasAbiertas = in_array(\App\Livewire\Compartido\Cursos\Show::CLAVE_PRACTICAS, $semanasAbiertas, true); @endphp
                                    <div class="flex items-center gap-1">
                                        <x-gestion.boton variante="secundario" tamano="sm" icono="flecha-abajo" x-on:click="alternar()"
                                            aria-controls="practicas-contenido" aria-expanded="{{ $practicasAbiertas ? 'true' : 'false' }}" x-bind:aria-expanded="abierta.toString()">
                                            <span x-text="abierta ? 'Ocultar contenido' : 'Ver contenido'">{{ $practicasAbiertas ? 'Ocultar contenido' : 'Ver contenido' }}</span>
                                        </x-gestion.boton>
                                        @if ($editable)
                                            @include('livewire.compartido.cursos._menu-semana', ['modulo' => $practicas, 'numero' => \App\Livewire\Compartido\Cursos\Show::CLAVE_PRACTICAS, 'etiqueta' => 'Prácticas de campo'])
                                        @endif
                                    </div>
                                @endif
                            </div>
                            @if ($this->puedeGestionar)
                                <div id="practicas-contenido" role="region" aria-labelledby="titulo-practicas-gestion"
                                    x-show="abierta" @unless ($practicasAbiertas) style="display: none" @endunless
                                    class="border-t border-celeste/60 bg-white/70 px-4 py-3 dark:border-azul-borde-oscuro dark:bg-azul-pagina-oscura/40">
                                    @include('livewire.compartido.cursos._secciones-leccion', ['modulo' => $practicas, 'semanaClave' => \App\Livewire\Compartido\Cursos\Show::CLAVE_PRACTICAS])
                                </div>
                            @endif
                        </section>
                    @endif

                    <x-gestion.tarjeta titulo="Contenido por semanas"
                        :descripcion="'El curso dura '.$curso->totalSemanas().' semanas'.($curso->semanas_duracion ? '' : ' (duración sugerida: el Supervisor aún no la definió)').'. Las semanas en borrador o programadas no son visibles para los estudiantes.'"
                        :relleno="false">
                        @if (! $secciones['practicas'] && $editable)
                            <x-slot name="acciones">
                                <x-gestion.boton variante="secundario" tamano="sm" icono="maletin" wire:click="prepararSeccion(null)">Agregar prácticas de campo</x-gestion.boton>
                            </x-slot>
                        @endif
                        @if ($esAdminVista)
                            @php $semanasConContenido = collect($secciones['semanas'])->filter(fn ($s) => $s['modulo'])->count(); @endphp
                            <details>
                                <summary class="flex cursor-pointer items-center justify-between gap-2 px-4 py-3 text-sm font-medium text-stone-600 hover:bg-arena-claro/40 dark:text-stone-300 dark:hover:bg-stone-700/40">
                                    <span>Ver detalle semana por semana</span>
                                    <span class="text-xs font-normal text-stone-400">{{ $semanasConContenido }} de {{ count($secciones['semanas']) }} con contenido</span>
                                </summary>
                                <div class="border-t border-arena-medio dark:border-stone-700">
                                    @include('livewire.compartido.cursos._lista-semanas')
                                </div>
                            </details>
                        @elseif ($this->puedeGestionar)
                            @include('livewire.compartido.cursos._semanas-profesor')
                        @else
                            @include('livewire.compartido.cursos._lista-semanas')
                        @endif
                        @if ($this->puedeAdministrar && ! $curso->estaFinalizado())
                            <p class="border-t border-arena-medio px-4 py-3 text-xs text-stone-500 dark:border-stone-700 dark:text-stone-400">
                                La cantidad de semanas se define en <a href="{{ route('cursos.editar', $curso) }}" wire:navigate class="font-medium text-menta-oscuro hover:underline dark:text-menta-base">Editar curso</a> (Duración en semanas).
                            </p>
                        @endif
                    </x-gestion.tarjeta>
                </div>

                @unless ($esAdminVista)
                    <div class="space-y-6">
                        <x-gestion.tarjeta titulo="Anuncios del curso" descripcion="Avisos para los estudiantes matriculados. Cada anuncio les llega como notificación.">
                            @if ($this->puedeGestionar && ! $curso->estaFinalizado())
                                <form wire:submit="publicarAnuncio" class="space-y-3">
                                    <x-gestion.campo etiqueta="Título" nombre="nuevoAnuncioTitulo" requerido>
                                        <x-gestion.control id="nuevoAnuncioTitulo" wire:model="nuevoAnuncioTitulo" placeholder="Recordatorio de la práctica 1" />
                                    </x-gestion.campo>
                                    <x-gestion.campo etiqueta="Mensaje" nombre="nuevoAnuncioTexto" requerido>
                                        <x-gestion.control tipo="textarea" id="nuevoAnuncioTexto" wire:model="nuevoAnuncioTexto" />
                                    </x-gestion.campo>
                                    <div class="flex items-end gap-2">
                                        <x-gestion.campo etiqueta="Prioridad" nombre="nuevoAnuncioPrioridad" class="flex-1">
                                            <x-gestion.control tipo="select" id="nuevoAnuncioPrioridad" wire:model="nuevoAnuncioPrioridad">
                                                <option value="normal">Normal</option>
                                                <option value="importante">Importante</option>
                                                <option value="urgente">Urgente</option>
                                            </x-gestion.control>
                                        </x-gestion.campo>
                                        <x-gestion.boton type="submit" icono="megafono">Publicar</x-gestion.boton>
                                    </div>
                                </form>
                            @endif

                            <div class="mt-4 space-y-3">
                                @forelse ($this->anuncios as $anuncio)
                                    <article class="rounded-lg border border-celeste bg-celeste/40 p-3 dark:border-azul-borde-oscuro dark:bg-azul-pagina-oscura" wire:key="anuncio-doc-{{ $anuncio->id }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <h4 class="text-sm font-semibold text-texto dark:text-slate-100">{{ $anuncio->titulo }}</h4>
                                            <div class="flex items-center gap-1.5">
                                                @if (in_array($anuncio->prioridad, ['urgente', 'importante'], true))
                                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">{{ ucfirst($anuncio->prioridad) }}</span>
                                                @endif
                                                @if ($this->puedeGestionar)
                                                    <button type="button" wire:click="eliminarAnuncio({{ $anuncio->id }})" wire:confirm="¿Eliminar el anuncio {{ $anuncio->titulo }}?"
                                                        class="text-slate-400 hover:text-red-600" aria-label="Eliminar anuncio {{ $anuncio->titulo }}">
                                                        <x-icono nombre="papelera" class="h-4 w-4" />
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                        <p class="mt-1 whitespace-pre-line text-xs text-stone-600 dark:text-stone-300">{{ $anuncio->contenido }}</p>
                                        <span class="mt-2 block text-[11px] text-stone-400">{{ $anuncio->created_at->diffForHumans() }} - {{ $anuncio->autor?->name }}</span>
                                    </article>
                                @empty
                                    <x-gestion.vacio icono="megafono" titulo="Sin anuncios" descripcion="Aún no se publicaron anuncios en este curso." />
                                @endforelse
                            </div>
                        </x-gestion.tarjeta>
                    </div>
                @endunless
            </div>

            @if ($mostrarModalClonar)
                <x-gestion.modal titulo="Clonar curso para un nuevo ciclo" cerrar="$set('mostrarModalClonar', false)"
                    descripcion="Se copian módulos, lecciones, tareas, quizzes y el esquema de evaluación como borrador. No se copian estudiantes, notas, entregas ni resultados.">
                    <x-gestion.campo etiqueta="Nombre del nuevo curso" nombre="clonNombre" requerido>
                        <x-gestion.control id="clonNombre" wire:model="clonNombre" />
                    </x-gestion.campo>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-gestion.campo etiqueta="Código" nombre="clonCodigo" ayuda="Opcional. Letras, números y guiones.">
                            <x-gestion.control id="clonCodigo" wire:model="clonCodigo" placeholder="MAT-101-2027" maxlength="30" class="uppercase" />
                        </x-gestion.campo>
                        <x-gestion.campo etiqueta="Profesor titular" nombre="clonProfesorId">
                            <x-gestion.control tipo="select" id="clonProfesorId" wire:model="clonProfesorId">
                                @foreach ($profesores as $prof)
                                    <option value="{{ $prof->id }}">{{ $prof->name }}</option>
                                @endforeach
                            </x-gestion.control>
                        </x-gestion.campo>
                    </div>
                    <x-slot name="pie">
                        <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalClonar', false)">Cancelar</x-gestion.boton>
                        <x-gestion.boton icono="copiar" wire:click="clonarCurso" wire:loading.attr="disabled" wire:target="clonarCurso">Clonar curso</x-gestion.boton>
                    </x-slot>
                </x-gestion.modal>
            @endif

            @if ($mostrarModalCarpeta)
                <x-gestion.modal titulo="Nueva carpeta" cerrar="$set('mostrarModalCarpeta', false)"
                    descripcion="Agrupa documentos o enlaces relacionados (ej. 'Sílabo', 'Actas de reunión').">
                    <x-gestion.campo etiqueta="Nombre de la carpeta" nombre="nuevaCarpetaNombre" requerido>
                        <x-gestion.control id="nuevaCarpetaNombre" wire:model="nuevaCarpetaNombre" placeholder="Sílabo" maxlength="100" autocomplete="off" />
                    </x-gestion.campo>
                    <x-slot name="pie">
                        <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalCarpeta', false)">Cancelar</x-gestion.boton>
                        <x-gestion.boton wire:click="crearCarpeta" wire:loading.attr="disabled" wire:target="crearCarpeta">Crear carpeta</x-gestion.boton>
                    </x-slot>
                </x-gestion.modal>
            @endif

            @if ($mostrarModalRecurso)
                <x-gestion.modal titulo="Agregar archivo o enlace" cerrar="$set('mostrarModalRecurso', false)">
                    <x-gestion.campo etiqueta="Nombre" nombre="nuevoRecursoNombre" requerido ayuda="Cómo se mostrará en la lista.">
                        <x-gestion.control id="nuevoRecursoNombre" wire:model="nuevoRecursoNombre" placeholder="Sílabo 2026-II" maxlength="150" autocomplete="off" />
                    </x-gestion.campo>
                    <div>
                        <span class="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">Tipo</span>
                        <div class="inline-flex rounded-lg border border-arena-medio bg-arena-claro/50 p-1 dark:border-stone-600 dark:bg-stone-900/40">
                            @foreach (['archivo' => 'Archivo', 'enlace' => 'Enlace'] as $valor => $etiqueta)
                                <button type="button" wire:click="$set('nuevoRecursoTipo', '{{ $valor }}')"
                                    class="rounded-md px-3 py-1.5 text-sm font-semibold transition {{ $nuevoRecursoTipo === $valor
                                        ? 'bg-white text-menta-oscuro shadow-sm dark:bg-stone-700 dark:text-menta-base'
                                        : 'text-stone-500 hover:text-stone-700 dark:text-stone-400 dark:hover:text-stone-200' }}">
                                    {{ $etiqueta }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    @if ($nuevoRecursoTipo === 'archivo')
                        <x-gestion.campo etiqueta="Archivo" nombre="nuevoRecursoArchivo" requerido ayuda="PDF, Word, Excel u otros documentos. Máximo 10 MB.">
                            <input type="file" id="nuevoRecursoArchivo" wire:model="nuevoRecursoArchivo"
                                class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-lg file:border-0 file:bg-menta-oscuro file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white dark:text-stone-300">
                            <div wire:loading wire:target="nuevoRecursoArchivo" class="mt-1 text-xs text-stone-500">Subiendo...</div>
                        </x-gestion.campo>
                    @else
                        <x-gestion.campo etiqueta="Enlace" nombre="nuevoRecursoUrl" requerido>
                            <x-gestion.control id="nuevoRecursoUrl" type="url" icono="enlace" wire:model="nuevoRecursoUrl" placeholder="https://..." />
                        </x-gestion.campo>
                    @endif
                    <x-slot name="pie">
                        <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalRecurso', false)">Cancelar</x-gestion.boton>
                        <x-gestion.boton wire:click="agregarRecurso" wire:loading.attr="disabled" wire:target="agregarRecurso">Agregar</x-gestion.boton>
                    </x-slot>
                </x-gestion.modal>
            @endif

            @if ($mostrarModalModulo)
                <x-gestion.modal titulo="Editar sección" cerrar="$set('mostrarModalModulo', false)">
                    <x-gestion.campo etiqueta="Tema" nombre="moduloTitulo" ayuda="Opcional. Se muestra junto a la semana, por ejemplo Semana 3: La derivada.">
                        <x-gestion.control id="moduloTitulo" wire:model="moduloTitulo" placeholder="Tema de la semana" />
                    </x-gestion.campo>
                    @if ($moduloSemana !== '')
                        <x-gestion.campo etiqueta="Semana" nombre="moduloSemana" requerido ayuda="Mueve todo el contenido (lecciones, tareas y quizzes) a otra semana libre.">
                            <x-gestion.control tipo="select" id="moduloSemana" wire:model="moduloSemana">
                                @foreach (range(1, $curso->totalSemanas()) as $numeroSemana)
                                    <option value="{{ $numeroSemana }}">Semana {{ $numeroSemana }}</option>
                                @endforeach
                            </x-gestion.control>
                        </x-gestion.campo>
                    @endif
                    <x-gestion.campo etiqueta="Estado" nombre="moduloEstado" requerido ayuda="Borrador y Programado no son visibles para los estudiantes hasta su publicación.">
                        <x-gestion.control tipo="select" id="moduloEstado" wire:model.live="moduloEstado">
                            <option value="borrador">Borrador</option>
                            <option value="publicado">Publicado</option>
                            <option value="programado">Programado</option>
                        </x-gestion.control>
                    </x-gestion.campo>
                    @if ($moduloEstado === 'programado')
                        <x-gestion.campo etiqueta="Publicar automáticamente el" nombre="moduloPublicarEn" requerido>
                            <x-gestion.control id="moduloPublicarEn" type="datetime-local" wire:model="moduloPublicarEn" />
                        </x-gestion.campo>
                    @endif
                    <x-gestion.campo etiqueta="Se considera completado al" nombre="moduloCondicion" requerido ayuda="Los estudiantes ven el avance de la sección según esta condición.">
                        <x-gestion.control tipo="select" id="moduloCondicion" wire:model="moduloCondicion">
                            @foreach (\App\Models\Modulo::CONDICIONES as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </x-gestion.control>
                    </x-gestion.campo>
                    <x-slot name="pie">
                        <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalModulo', false)">Cancelar</x-gestion.boton>
                        <x-gestion.boton wire:click="guardarModulo">Guardar</x-gestion.boton>
                    </x-slot>
                </x-gestion.modal>
            @endif

            {{-- Contenido de la semana (Profesor): formulario de la sección y confirmación de eliminar o vaciar. --}}
            @if ($mostrarFormularioLeccion && $seccionLeccion)
                @include('livewire.compartido.cursos._formulario-leccion')
            @endif

            @if ($mostrarConfirmacion)
                <x-gestion.modal :titulo="$confirmacionTitulo" cerrar="cerrarConfirmacion()" icono="alerta">
                    <p class="text-sm text-stone-700 dark:text-stone-200">{{ $confirmacionMensaje }}</p>
                    <x-slot name="pie">
                        <x-gestion.boton variante="secundario" wire:click="cerrarConfirmacion">Cancelar</x-gestion.boton>
                        <x-gestion.boton variante="peligro" icono="papelera" wire:click="ejecutarConfirmacion"
                            wire:loading.attr="disabled" wire:target="ejecutarConfirmacion">{{ $confirmacionTextoBoton }}</x-gestion.boton>
                    </x-slot>
                </x-gestion.modal>
            @endif
        @else
            {{-- Rediseño visual del rol estudiante: pestañas por módulo (ver ARQUITECTURA.md). --}}
            

            <div x-data="{ pestaña: 'unidades' }">
                <div class="flex flex-wrap gap-1 border-b border-celeste dark:border-azul-borde-oscuro mb-4">
                    @foreach (['unidades' => 'Contenido', 'anuncios' => 'Anuncios', 'tareas' => 'Tareas', 'quizzes' => 'Quizzes', 'companeros' => 'Compañeros', 'migrupo' => 'Mi grupo'] as $clave => $etiqueta)
                        <button @click="pestaña = '{{ $clave }}'"
                            :class="pestaña === '{{ $clave }}' ? 'border-azul-oscuro text-azul-oscuro dark:text-azul-fondo' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700'"
                            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition flex items-center gap-1.5">
                            {{ $etiqueta }}
                            @if ($clave === 'anuncios' && $this->anuncios->count() > 0)
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-azul-oscuro text-white dark:bg-cielo dark:text-azul-superficie-oscura">
                                    {{ $this->anuncios->count() }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Contenido: bloque propio de Prácticas de campo (solo si tiene contenido visible) y,
                     debajo, Semana 1..N. Una semana sin contenido visible (RF-38) se muestra como
                     "Aún no disponible"; la semana en curso se abre sola. --}}
                <div x-show="pestaña === 'unidades'" class="space-y-6">
                    @include('livewire.compartido.cursos._informacion-curso')

                    @if ($secciones['practicas'])
                        @php
                            $seccion = $secciones['practicas'];
                            $modulo = $seccion['modulo'];
                            $prog = $this->progresoModulos[$modulo->id] ?? null;
                            $pct = $prog && $prog['total'] > 0 ? (int) round(($prog['hechos'] / $prog['total']) * 100) : null;
                        @endphp
                        <section aria-labelledby="titulo-practicas" wire:key="practicas-est-{{ $modulo->id }}"
                            x-data="{ expandido: false }"
                            class="overflow-hidden rounded-xl border border-azul-fondo/25 border-l-4 border-l-azul-fondo bg-celeste/50 shadow-sm dark:border-azul-borde-oscuro dark:border-l-azul-fondo dark:bg-azul-oscuro/15 dark:shadow-none">
                            <div @click="expandido = !expandido" class="flex cursor-pointer select-none items-center gap-4 p-4">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-azul-fondo text-white shadow-sm">
                                    <x-icono nombre="maletin" class="h-6 w-6" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-azul-fondo dark:text-cielo">Fuera de la numeración semanal</p>
                                    <h3 id="titulo-practicas" class="text-lg font-bold text-texto dark:text-slate-100">Prácticas de campo</h3>
                                    @if ($modulo->tema() && $modulo->tema() !== 'Prácticas de campo')
                                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ $modulo->tema() }}</p>
                                    @endif
                                    @if ($pct !== null)
                                        <div class="mt-1.5 flex items-center gap-2">
                                            <div class="h-1.5 max-w-[160px] flex-1 overflow-hidden rounded-full bg-white/80 dark:bg-azul-borde-oscuro">
                                                <div class="h-full rounded-full bg-azul-fondo" style="width: {{ $pct }}%"></div>
                                            </div>
                                            <span class="inline-flex items-center gap-1 text-xs {{ $prog['completado'] ? 'font-medium text-exito-oscuro dark:text-exito-base' : 'text-slate-500 dark:text-slate-400' }}" title="Se completa al: {{ $prog['condicion'] }}">
                                                @if ($prog['completado'])<x-icono nombre="check-circulo" class="h-3.5 w-3.5" />@endif
                                                {{ $prog['hechos'] }} de {{ $prog['total'] }} completados
                                            </span>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="hidden text-xs text-slate-500 sm:inline dark:text-slate-400">{{ $seccion['elementos'] }} {{ $seccion['elementos'] === 1 ? 'actividad' : 'actividades' }}</span>
                                    <svg class="h-5 w-5 text-azul-fondo transition-transform duration-200" :class="expandido ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                            <div x-show="expandido" x-collapse class="space-y-2 border-t border-azul-fondo/15 bg-white/70 p-4 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura/60">
                                @include('livewire.compartido.cursos.partials.elementos-seccion', ['leccionesCompletadas' => $this->leccionesCompletadas])
                            </div>
                        </section>
                    @endif

                    <section aria-labelledby="titulo-semanas" class="space-y-3">
                        @if ($secciones['practicas'])
                            <h2 id="titulo-semanas" class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Semanas del curso</h2>
                        @endif
                        @foreach ($secciones['semanas'] as $seccion)
                            @php
                                $modulo = $seccion['modulo'];
                                $enCurso = $seccion['rango'] && now()->between($seccion['rango'][0], $seccion['rango'][1]);
                            @endphp
                            @if (! $modulo)
                                {{-- Semana sin contenido visible: no se revela su tema ni si hay algo en borrador. --}}
                                <div wire:key="semana-vacia-{{ $seccion['semana'] }}"
                                    class="flex items-center gap-3 rounded-xl border border-dashed border-celeste bg-white/60 px-4 py-3 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura/50 {{ $enCurso ? 'ring-1 ring-azul-oscuro/30' : '' }}">
                                    <span class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-lg bg-slate-100 leading-none text-slate-400 dark:bg-slate-800">
                                        <span class="text-[9px] font-semibold uppercase tracking-wide">Sem</span>
                                        <span class="text-base font-bold">{{ $seccion['semana'] }}</span>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-x-2">
                                            <h3 class="font-medium text-slate-500 dark:text-slate-400">{{ $seccion['etiqueta'] }}</h3>
                                            @if ($enCurso)
                                                <span class="rounded-full bg-azul-oscuro px-2 py-0.5 text-[10px] font-semibold text-white">Semana actual</span>
                                            @endif
                                            @if ($seccion['rango'])
                                                <span class="text-xs text-slate-400">{{ $seccion['rango'][0]->translatedFormat('d M') }} – {{ $seccion['rango'][1]->translatedFormat('d M') }}</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-slate-400">Aún no disponible</p>
                                    </div>
                                </div>
                            @else
                                @php
                                    $prog = $this->progresoModulos[$modulo->id] ?? null;
                                    $pct = $prog && $prog['total'] > 0 ? (int) round(($prog['hechos'] / $prog['total']) * 100) : null;
                                @endphp
                                <div x-data="{ expandido: @js($enCurso) }" wire:key="modulo-est-{{ $modulo->id }}"
                                    class="rounded-xl border border-celeste bg-white p-4 shadow-sm dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:shadow-none {{ $enCurso ? 'ring-1 ring-azul-oscuro/30 dark:ring-azul-fondo/40' : '' }}">
                                    <div @click="expandido = !expandido" class="flex cursor-pointer select-none items-center gap-3">
                                        <span class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-lg leading-none {{ $enCurso ? 'bg-azul-oscuro text-white' : 'bg-celeste/50 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-celeste' }}">
                                            <span class="text-[9px] font-semibold uppercase tracking-wide opacity-80">Sem</span>
                                            <span class="text-base font-bold">{{ $seccion['semana'] }}</span>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                <h3 class="font-semibold text-texto dark:text-slate-100">{{ $seccion['etiqueta'] }}</h3>
                                                @if ($enCurso)
                                                    <span class="rounded-full bg-azul-oscuro px-2 py-0.5 text-[10px] font-semibold text-white">Semana actual</span>
                                                @endif
                                                @if ($seccion['rango'])
                                                    <span class="text-xs text-slate-400">{{ $seccion['rango'][0]->translatedFormat('d M') }} – {{ $seccion['rango'][1]->translatedFormat('d M') }}</span>
                                                @endif
                                            </div>
                                            @if ($modulo->tema())
                                                <p class="truncate text-sm text-slate-600 dark:text-slate-300">{{ $modulo->tema() }}</p>
                                            @endif
                                            @if ($pct !== null)
                                                <div class="mt-1.5 flex items-center gap-2">
                                                    <div class="h-1.5 max-w-[160px] flex-1 overflow-hidden rounded-full bg-celeste/60 dark:bg-azul-borde-oscuro">
                                                        <div class="h-full rounded-full bg-azul-oscuro dark:bg-azul-fondo" style="width: {{ $pct }}%"></div>
                                                    </div>
                                                    <span class="inline-flex items-center gap-1 text-xs {{ $prog['completado'] ? 'font-medium text-exito-oscuro dark:text-exito-base' : 'text-slate-500 dark:text-slate-400' }}" title="Se completa al: {{ $prog['condicion'] }}">
                                                        @if ($prog['completado'])<x-icono nombre="check-circulo" class="h-3.5 w-3.5" />@endif
                                                        {{ $prog['hechos'] }} de {{ $prog['total'] }} completados
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="hidden text-xs text-slate-400 sm:inline">{{ $seccion['elementos'] }} {{ $seccion['elementos'] === 1 ? 'elemento' : 'elementos' }}</span>
                                            <svg class="h-5 w-5 text-slate-400 transition-transform duration-200" :class="expandido ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div x-show="expandido" x-collapse class="mt-4 space-y-2 border-t border-celeste pt-4 dark:border-azul-borde-oscuro">
                                        @include('livewire.compartido.cursos.partials.elementos-seccion', ['leccionesCompletadas' => $this->leccionesCompletadas])
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </section>
                </div>

                {{-- Anuncios del Curso --}}
                <div x-show="pestaña === 'anuncios'" class="space-y-4">
                    @forelse ($this->anuncios as $anuncio)
                        <div class="rounded-xl border border-celeste dark:border-azul-borde-oscuro bg-white dark:bg-azul-superficie-oscura p-5 shadow-sm" wire:key="anuncio-est-{{ $anuncio->id }}">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-azul-oscuro/10 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-cielo">
                                        <x-icono nombre="megafono" class="h-4 w-4" />
                                    </span>
                                    <h3 class="font-bold text-texto dark:text-slate-100 text-base">{{ $anuncio->titulo }}</h3>
                                </div>
                                @if ($anuncio->prioridad === 'urgente')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                        Urgente
                                    </span>
                                @elseif ($anuncio->prioridad === 'importante')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                        Importante
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                        Aviso
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-400 mb-3 flex items-center gap-1.5">
                                <span>Por Prof. {{ $anuncio->autor?->name ?? 'Docente' }}</span>
                                <span>•</span>
                                <span>{{ $anuncio->created_at->diffForHumans() }} ({{ $anuncio->created_at->formatoFechaHora() }})</span>
                            </div>
                            <div class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line leading-relaxed">
                                {{ $anuncio->contenido }}
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-celeste dark:border-azul-borde-oscuro p-8 text-center text-slate-500 dark:text-slate-400">
                            <x-icono nombre="megafono" class="h-8 w-8 mx-auto mb-2 text-slate-400 opacity-60" />
                            <p class="font-medium text-sm">No hay anuncios publicados en este curso todavía.</p>
                            <p class="text-xs text-slate-400 mt-1">Tu profesor compartirá comunicados y avisos importantes aquí.</p>
                        </div>
                    @endforelse
                </div>

                {{-- Tareas --}}
                <div x-show="pestaña === 'tareas'" class="space-y-3">
                    @forelse ($this->resumenTareasEstudiante as $fila)
                        <a href="{{ route('cursos.tareas.show', [$curso, $fila['tarea']]) }}" wire:navigate wire:key="tarea-est-{{ $fila['tarea']->id }}"
                            class="flex items-center gap-3 rounded-xl border border-celeste dark:border-azul-borde-oscuro bg-white dark:bg-azul-superficie-oscura p-3 shadow-sm dark:shadow-none transition hover:shadow-md">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-azul-oscuro/10 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-cielo">
                                <x-icono nombre="tarea" class="h-5 w-5" />
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-texto dark:text-slate-100 truncate">{{ $fila['tarea']->titulo }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">
                                    Vence: {{ $fila['tarea']->fecha_entrega?->translatedFormat('d M, H:i') ?? 'Sin fecha límite' }}
                                </span>
                            </span>
                            <x-badge-estado :estado="$fila['estado']" />
                        </a>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">Este curso todavía no tiene tareas.</p>
                    @endforelse
                </div>

                {{-- Quizzes --}}
                <div x-show="pestaña === 'quizzes'" class="space-y-3">
                    @forelse ($this->resumenQuizzesEstudiante as $fila)
                        <a href="{{ route('cursos.quizzes.show', [$curso, $fila['quiz']]) }}" wire:navigate wire:key="quiz-est-{{ $fila['quiz']->id }}"
                            class="flex items-center gap-3 rounded-xl border border-celeste dark:border-azul-borde-oscuro bg-white dark:bg-azul-superficie-oscura p-3 shadow-sm dark:shadow-none transition hover:shadow-md">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-lavanda-fondo/40 text-lavanda-oscuro dark:bg-lavanda-oscuro/30 dark:text-lavanda-fondo">
                                <x-icono nombre="quiz" class="h-5 w-5" />
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-texto dark:text-slate-100 truncate">{{ $fila['quiz']->tema }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ count($fila['quiz']->preguntas) }} preguntas</span>
                            </span>
                            @if ($fila['resultado'])
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-verde-fondo/50 text-verde-oscuro">
                                    {{ $fila['resultado']->puntaje }} / 20
                                </span>
                            @else
                                <x-badge-estado estado="pendiente" />
                            @endif
                        </a>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">Este curso todavía no tiene quizzes.</p>
                    @endforelse
                </div>

                {{-- Compañeros: solo estudiantes ya inscritos ven quién más está en el curso. --}}
                <div x-show="pestaña === 'companeros'" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @forelse ($this->companerosDelCurso as $companero)
                        @php $esUnoMismo = $companero->is(auth()->user()); @endphp
                        <div class="flex items-center gap-3 rounded-xl border border-celeste dark:border-azul-borde-oscuro bg-white dark:bg-azul-superficie-oscura p-3" wire:key="companero-{{ $companero->id }}">
                            <img src="{{ $companero->fotoUrl('2F5A78') }}"
                                class="h-9 w-9 shrink-0 rounded-full object-cover" alt="{{ $companero->name }}">
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-texto dark:text-slate-100 truncate">
                                    {{ $companero->name }} @if ($esUnoMismo) <span class="text-slate-400 font-normal">(tú)</span> @endif
                                </span>
                                @if ($companero->grupo_nombre)
                                    <span class="block text-xs text-slate-400 truncate">{{ $companero->grupo_nombre }}</span>
                                @endif
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400 sm:col-span-2">Todavía no hay más estudiantes inscritos en este curso.</p>
                    @endforelse
                </div>

                {{-- Mi grupo: nombre del grupo + integrantes, o aviso de que no tiene uno. --}}
                <div x-show="pestaña === 'migrupo'">
                    @if ($this->miGrupoDelCurso)
                        <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-5">
                            <div class="flex items-center gap-3 mb-4">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-lavanda-fondo/40 text-lavanda-oscuro dark:bg-lavanda-oscuro/30 dark:text-lavanda-fondo">
                                    <x-icono nombre="usuarios" class="h-5 w-5" />
                                </span>
                                <h3 class="font-semibold text-texto dark:text-slate-100">{{ $this->miGrupoDelCurso->nombre }}</h3>
                            </div>

                            <p class="text-xs font-semibold uppercase text-slate-400 mb-2">Integrantes</p>
                            <div class="space-y-2">
                                @foreach ($this->miGrupoDelCurso->estudiantes as $integrante)
                                    @php $esUnoMismo = $integrante->is(auth()->user()); @endphp
                                    <div class="flex items-center gap-3" wire:key="integrante-{{ $integrante->id }}">
                                        <img src="{{ $integrante->fotoUrl('2F5A78') }}"
                                            class="h-8 w-8 shrink-0 rounded-full object-cover" alt="{{ $integrante->name }}">
                                        <span class="text-sm text-texto dark:text-slate-100">
                                            {{ $integrante->name }} @if ($esUnoMismo) <span class="text-slate-400">(tú)</span> @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-6 text-center text-slate-500 dark:text-slate-400 text-sm">
                            Todavía no tienes un grupo asignado en este curso. Cuando tu profesor te agregue a uno, aparecerá aquí.
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
